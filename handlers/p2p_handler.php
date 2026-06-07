<?php
require_once __DIR__ . '/../includes/session.php';
dream_start_session();
require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => ''];
$security = new Security();
$raw = file_get_contents('php://input');
$json = json_decode($raw, true);
$req = is_array($json) ? $json : array_merge($_POST, $_GET);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$security->validateCSRFToken($req['csrf_token'] ?? '')) {
    $response['message'] = 'Invalid security token';
    echo json_encode($response); exit;
}

$userId = $_SESSION['user_id'] ?? null;
$action = $req['action'] ?? ($_GET['action'] ?? '');

try {
    $db = Database::getInstance()->getConnection();

    // ─── AUTO-CANCEL PENDING TRADES OLDER THAN 15 MINS ───
    try {
        $stmt = $db->prepare("SELECT id, offer_id, seller_id, buyer_id, coin_type, quantity FROM p2p_trades WHERE status = 'pending' AND created_at < NOW() - INTERVAL 15 MINUTE FOR UPDATE");
        $stmt->execute();
        $expiredTrades = $stmt->fetchAll();
        require_once __DIR__ . '/../includes/mail_templates.php';
        require_once __DIR__ . '/../includes/mailer.php';
        $mailer = Mailer::getInstance();
        foreach ($expiredTrades as $et) {
            $coinCol = $et['coin_type'] . '_coins';
            // Escrow refund to seller
            $db->prepare("UPDATE users SET $coinCol = $coinCol + ? WHERE id = ?")->execute([$et['quantity'], $et['seller_id']]);
            // Restore offer remaining
            $db->prepare("UPDATE p2p_offers SET remaining = remaining + ?, status = 'active' WHERE id = ?")->execute([$et['quantity'], $et['offer_id']]);
            // Mark cancelled
            $db->prepare("UPDATE p2p_trades SET status = 'cancelled' WHERE id = ?")->execute([$et['id']]);
            // Notify both parties
            $stmtU = $db->prepare("SELECT id, username, email FROM users WHERE id IN (?, ?)");
            $stmtU->execute([$et['buyer_id'], $et['seller_id']]);
            $users = $stmtU->fetchAll();
            foreach ($users as $u) {
                try {
                    $body = MailTemplates::orderCancelled($u['username'], (int)$et['id'], (int)$et['quantity'], $et['coin_type'], 'auto_timeout');
                    $mailer->send($u['email'], 'Trade #' . $et['id'] . ' Auto-Cancelled', $body, 'noreply@robicodes.xyz', 'RobiCodes P2P');
                } catch (Throwable $em) { /* skip mail error */ }
            }
        }
    } catch (Throwable $e) { /* ignore auto cancel errors silently */ }

    $loadP2PMessages = function (int $tradeId) use ($db): array {
        $stmt = $db->prepare("SELECT m.id, m.trade_id, m.sender_id, m.message, m.image_path, m.created_at, u.username, u.full_name FROM p2p_chat_messages m JOIN users u ON u.id = m.sender_id WHERE m.trade_id = ? ORDER BY m.created_at ASC");
        $stmt->execute([$tradeId]);
        return $stmt->fetchAll();
    };

    switch ($action) {

        // ─── P2P: CREATE OFFER (Merchants Only) ───
        case 'create_p2p_offer':
            if (!$userId) { $response['message'] = 'Please log in.'; break; }
            $userRole = $_SESSION['role'] ?? 'user';
            if ($userRole !== 'merchant' && $userRole !== 'admin') { $response['message'] = 'Only verified merchants can create P2P offers.'; break; }
            $type = trim($req['type'] ?? '');
            $coinType = trim($req['coin_type'] ?? 'bronze');
            $price = abs((float)($req['price'] ?? 0));
            $quantity = abs((int)($req['quantity'] ?? 0));
            $minAmt = abs((int)($req['min_amount'] ?? 1));
            $maxAmt = abs((int)($req['max_amount'] ?? 0));
            if (!in_array($type, ['buy','sell'])) { $response['message'] = 'Invalid type.'; break; }
            if (!in_array($coinType, ['bronze','silver','gold'])) { $response['message'] = 'Invalid coin type.'; break; }
            if ($price < 1) { $response['message'] = 'Price must be at least ৳1.'; break; }
            if ($quantity < 1) { $response['message'] = 'Quantity must be at least 1.'; break; }
            try {
                $stmt = $db->prepare("INSERT INTO p2p_offers (agent_id, type, coin_type, price_per_coin, quantity, remaining, min_amount, max_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$userId, $type, $coinType, $price, $quantity, $quantity, $minAmt, $maxAmt]);
                $response = ['success' => true, 'message' => 'P2P offer created!'];
            } catch (Throwable $e) { $response['message'] = 'Server error.'; }
            break;

        // ─── P2P: CANCEL OFFER ───
        case 'cancel_p2p_offer':
            if (!$userId) { $response['message'] = 'Please log in.'; break; }
            $offerId = (int)($req['offer_id'] ?? 0);
            try {
                $stmt = $db->prepare("UPDATE p2p_offers SET status = 'cancelled' WHERE id = ? AND agent_id = ? AND status = 'active'");
                $stmt->execute([$offerId, $userId]);
                if ($stmt->rowCount()) {
                    $response = ['success' => true, 'message' => 'Offer cancelled.'];
                } else {
                    $response['message'] = 'Offer not found or already inactive.';
                }
            } catch (Throwable $e) { $response['message'] = 'Server error.'; }
            break;


        // ─── PLACE ORDER (user clicks buy/sell) ───
        case 'place_p2p_order':
            if (!$userId) { $response['message'] = 'Please log in.'; break; }
            $offerId = (int)($req['offer_id'] ?? 0);
            $qty = abs((int)($req['quantity'] ?? 0));
            if ($qty < 1) { $response['message'] = 'Quantity must be at least 1.'; break; }
            try {
                $db->beginTransaction();
                $stmt = $db->prepare("SELECT o.*, u.full_name, u.username FROM p2p_offers o JOIN users u ON u.id = o.agent_id WHERE o.id = ? AND o.status = 'active' AND o.remaining > 0 FOR UPDATE");
                $stmt->execute([$offerId]);
                $offer = $stmt->fetch();
                if (!$offer) { $db->rollBack(); $response['message'] = 'Offer not available.'; break; }
                if ((int)$offer['agent_id'] === (int)$userId) { $db->rollBack(); $response['message'] = 'You cannot trade with your own offer.'; break; }
                if ($qty > (int)$offer['remaining']) { $db->rollBack(); $response['message'] = 'Only ' . $offer['remaining'] . ' coins remaining.'; break; }
                $min = (int)$offer['min_amount']; $max = (int)$offer['max_amount'];
                if ($qty < $min) { $db->rollBack(); $response['message'] = 'Minimum ' . $min . ' coins.'; break; }
                if ($max > 0 && $qty > $max) { $db->rollBack(); $response['message'] = 'Maximum ' . $max . ' coins.'; break; }
                $totalPrice = $qty * (float)$offer['price_per_coin'];

                // For buy offers (merchant buys, user sells): check user has enough coins
                if ($offer['type'] === 'buy') {
                    $coinCol = $offer['coin_type'] . '_coins';
                    $stmt = $db->prepare("SELECT $coinCol FROM users WHERE id = ? FOR UPDATE");
                    $stmt->execute([$userId]);
                    $user = $stmt->fetch();
                    if (!$user || (int)$user[$coinCol] < $qty) { $db->rollBack(); $response['message'] = 'Insufficient ' . $offer['coin_type'] . ' coins.'; break; }
                }

                // Update offer remaining
                $newRemaining = (int)$offer['remaining'] - $qty;
                $newStatus = $newRemaining < 1 ? 'completed' : 'active';
                $stmt = $db->prepare("UPDATE p2p_offers SET remaining = ?, status = ? WHERE id = ?");
                $stmt->execute([$newRemaining, $newStatus, $offerId]);

                $sellerId = $offer['type'] === 'sell' ? (int)$offer['agent_id'] : $userId;
                $buyerId = $offer['type'] === 'sell' ? $userId : (int)$offer['agent_id'];

                // ESCROW: Deduct coins from seller immediately
                $coinCol = $offer['coin_type'] . '_coins';
                $stmt = $db->prepare("SELECT $coinCol FROM users WHERE id = ? FOR UPDATE");
                $stmt->execute([$sellerId]);
                $sellerRow = $stmt->fetch();
                if (!$sellerRow || (int)$sellerRow[$coinCol] < $qty) {
                    $db->rollBack();
                    $response['message'] = 'Seller has insufficient ' . $offer['coin_type'] . ' coins.';
                    break;
                }
                $stmt = $db->prepare("UPDATE users SET $coinCol = $coinCol - ? WHERE id = ?");
                $stmt->execute([$qty, $sellerId]);

                $stmt = $db->prepare("INSERT INTO p2p_trades (offer_id, seller_id, buyer_id, coin_type, quantity, total_price, status, sender_phone) VALUES (?, ?, ?, ?, ?, ?, 'pending', '')");
                $stmt->execute([$offerId, $sellerId, $buyerId, $offer['coin_type'], $qty, $totalPrice]);
                $tradeId = $db->lastInsertId();

                $db->commit();
                $orderType = $offer['type'] === 'sell' ? 'buy' : 'sell';
                createNotification($db, (int)$offer['agent_id'], $userId, 'p2p_order_placed', 'New ' . $orderType . ' order placed for ' . $qty . ' ' . $offer['coin_type'] . ' coins (৳' . number_format($totalPrice, 0) . ').');

                // Email notification
                require_once __DIR__ . '/../includes/mailer.php';
                require_once __DIR__ . '/../includes/mail_templates.php';
                if ($offer['type'] === 'sell') {
                    // User is buying → email to buyer
                    $userEmail = $_SESSION['email'] ?? '';
                    if ($userEmail) {
                        try {
                            $mailer = Mailer::getInstance();
                            $body = MailTemplates::orderPlaced($_SESSION['username'] ?? 'User', 'buy', $offer['coin_type'], $qty, $totalPrice, (int)$tradeId);
                            $mailer->send($userEmail, 'Order Placed - P2P Trade #' . $tradeId, $body);
                        } catch (Throwable $e) { error_log("Order email error: " . $e->getMessage()); }
                    }
                } else {
                    // User is selling → email to user (seller)
                    $userEmail = $_SESSION['email'] ?? '';
                    if ($userEmail) {
                        try {
                            $mailer = Mailer::getInstance();
                            $body = MailTemplates::orderPlaced($_SESSION['username'] ?? 'User', 'sell', $offer['coin_type'], $qty, $totalPrice, (int)$tradeId);
                            $mailer->send($userEmail, 'Order Placed - P2P Trade #' . $tradeId, $body);
                        } catch (Throwable $e) { error_log("Order email error: " . $e->getMessage()); }
                    }
                }

                $response = ['success' => true, 'message' => 'Order placed!', 'trade_id' => $tradeId, 'total_price' => $totalPrice, 'coin_type' => $offer['coin_type'], 'quantity' => $qty, 'offer_type' => $offer['type'], 'agent_id' => (int)$offer['agent_id']];
            } catch (Throwable $e) { $db->rollBack(); $response['message'] = 'Server error.'; }
            break;

        // ─── CONFIRM PAYMENT (buyer marks as paid) ───
        case 'confirm_p2p_payment':
            if (!$userId) { $response['message'] = 'Please log in.'; break; }
            $tradeId = (int)($req['trade_id'] ?? 0);
            $method = trim($req['method'] ?? 'bkash');
            $senderPhone = trim($req['sender_phone'] ?? '');
            $txid = strtoupper(trim($req['txid'] ?? ''));
            if ($txid === '' || strlen($txid) < 4) { $response['message'] = 'Enter a valid Transaction ID.'; break; }
            if (!preg_match('/^01[3-9]\d{8}$/', $senderPhone)) { $response['message'] = 'Valid phone number required.'; break; }
            try {
                $stmt = $db->prepare("SELECT t.*, o.type AS offer_type FROM p2p_trades t JOIN p2p_offers o ON o.id = t.offer_id WHERE t.id = ? AND t.buyer_id = ? AND t.status = 'pending' FOR UPDATE");
                $stmt->execute([$tradeId, $userId]);
                $trade = $stmt->fetch();
                if (!$trade) { $response['message'] = 'Trade not found or already processed.'; break; }
                $stmt = $db->prepare("UPDATE p2p_trades SET status = 'paid', payment_method = ?, sender_phone = ?, txid = ? WHERE id = ?");
                $stmt->execute([$method, $senderPhone, $txid, $tradeId]);
                createNotification($db, (int)$trade['seller_id'], $userId, 'p2p_payment_received', 'Payment marked for trade #' . $tradeId . '. ' . ($trade['offer_type'] === 'sell' ? 'Release coins to buyer.' : 'Release coins to complete the trade.'));

                // Email to seller
                $stmt = $db->prepare("SELECT email, username, full_name FROM users WHERE id = ?");
                $stmt->execute([(int)$trade['seller_id']]);
                $sellerUser = $stmt->fetch();
                if ($sellerUser) {
                    require_once __DIR__ . '/../includes/mailer.php';
                    require_once __DIR__ . '/../includes/mail_templates.php';
                    try {
                        $mailer = Mailer::getInstance();
                        $body = MailTemplates::paymentConfirmed($sellerUser['full_name'] ?: $sellerUser['username'], $tradeId, (int)$trade['quantity'], $trade['coin_type']);
                        $mailer->send($sellerUser['email'], 'Payment Confirmed - Trade #' . $tradeId, $body);
                    } catch (Throwable $e) { error_log("Payment email error: " . $e->getMessage()); }
                }

                $response = ['success' => true, 'message' => 'Payment confirmed!'];
            } catch (Throwable $e) { $response['message'] = 'Server error (' . $e->getMessage() . ').'; }
            break;

        // ─── CONFIRM RECEIVED / RELEASE COINS (seller releases coins after payment) ───
        case 'confirm_p2p_received':
            if (!$userId) { $response['message'] = 'Please log in.'; break; }
            $tradeId = (int)($req['trade_id'] ?? 0);
            try {
                $db->beginTransaction();
                $stmt = $db->prepare("SELECT t.*, o.type AS offer_type FROM p2p_trades t JOIN p2p_offers o ON o.id = t.offer_id WHERE t.id = ? AND t.status = 'paid' FOR UPDATE");
                $stmt->execute([$tradeId]);
                $trade = $stmt->fetch();
                if (!$trade) { $db->rollBack(); $response['message'] = 'Trade not found or not in paid status.'; break; }
                $isSeller = (int)$trade['seller_id'] === (int)$userId;
                if (!$isSeller) { $db->rollBack(); $response['message'] = 'Only the seller can release coins.'; break; }
                $coinCol = $trade['coin_type'] . '_coins';
                $qty = (int)$trade['quantity'];

                // ESCROW: Coins were already deducted from seller. Now credit the buyer.
                $stmt = $db->prepare("UPDATE users SET $coinCol = $coinCol + ? WHERE id = ?");
                $stmt->execute([$qty, (int)$trade['buyer_id']]);

                $stmt = $db->prepare("UPDATE p2p_trades SET status = 'completed', completed_at = NOW() WHERE id = ?");
                $stmt->execute([$tradeId]);

                $stmt = $db->prepare("INSERT INTO coin_transactions (from_user_id, to_user_id, type, coin_type, amount, description, ref_id) VALUES (?, ?, 'p2p_sell', ?, ?, ?, ?)");
                $stmt->execute([(int)$trade['seller_id'], (int)$trade['buyer_id'], $trade['coin_type'], $qty, 'P2P trade #' . $tradeId, $tradeId]);

                $db->commit();
                createNotification($db, (int)$trade['buyer_id'], $userId, 'p2p_trade_completed', 'Trade #' . $tradeId . ' completed! ' . $qty . ' ' . $trade['coin_type'] . ' coins released.');

                // Email to buyer
                $stmt = $db->prepare("SELECT email, username, full_name FROM users WHERE id = ?");
                $stmt->execute([(int)$trade['buyer_id']]);
                $buyerUser = $stmt->fetch();
                if ($buyerUser) {
                    require_once __DIR__ . '/../includes/mailer.php';
                    require_once __DIR__ . '/../includes/mail_templates.php';
                    try {
                        $mailer = Mailer::getInstance();
                        $body = MailTemplates::tradeCompleted($buyerUser['full_name'] ?: $buyerUser['username'], 'buyer', $tradeId, $qty, $trade['coin_type']);
                        $mailer->send($buyerUser['email'], 'Trade Completed - #' . $tradeId, $body);
                    } catch (Throwable $e) { error_log("Complete email error: " . $e->getMessage()); }
                }
                // Email to seller
                $stmt = $db->prepare("SELECT email, username, full_name FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $sellerUser = $stmt->fetch();
                if ($sellerUser) {
                    require_once __DIR__ . '/../includes/mailer.php';
                    require_once __DIR__ . '/../includes/mail_templates.php';
                    try {
                        $mailer = Mailer::getInstance();
                        $body = MailTemplates::tradeCompleted($sellerUser['full_name'] ?: $sellerUser['username'], 'seller', $tradeId, $qty, $trade['coin_type']);
                        $mailer->send($sellerUser['email'], 'Trade Completed - #' . $tradeId, $body);
                    } catch (Throwable $e) { error_log("Complete email error: " . $e->getMessage()); }
                }

                $response = ['success' => true, 'message' => 'Coins released to buyer!'];
            } catch (Throwable $e) { $db->rollBack(); $response['message'] = 'Server error.'; }
            break;

        // ─── CANCEL ORDER ───
        case 'cancel_p2p_order':
            if (!$userId) { $response['message'] = 'Please log in.'; break; }
            $tradeId = (int)($req['trade_id'] ?? 0);
            try {
                $db->beginTransaction();
                $stmt = $db->prepare("SELECT * FROM p2p_trades WHERE id = ? AND (buyer_id = ? OR seller_id = ?) AND status IN ('pending','paid') FOR UPDATE");
                $stmt->execute([$tradeId, $userId, $userId]);
                $trade = $stmt->fetch();
                if (!$trade) { $db->rollBack(); $response['message'] = 'Trade not found.'; break; }
                $qty = (int)$trade['quantity'];

                // ESCROW: Refund held coins to the seller
                $coinCol = $trade['coin_type'] . '_coins';
                $stmt = $db->prepare("UPDATE users SET $coinCol = $coinCol + ? WHERE id = ?");
                $stmt->execute([$qty, (int)$trade['seller_id']]);

                // Restore offer remaining
                $stmt = $db->prepare("UPDATE p2p_offers SET remaining = remaining + ?, status = 'active' WHERE id = ?");
                $stmt->execute([$qty, (int)$trade['offer_id']]);

                $stmt = $db->prepare("UPDATE p2p_trades SET status = 'cancelled' WHERE id = ?");
                $stmt->execute([$tradeId]);
                $db->commit();

                // Decide who cancelled
                $cancellerId = (int)$userId;
                $isBuyerCancel = $cancellerId === (int)$trade['buyer_id'];
                $reason = $isBuyerCancel ? 'user_cancelled' : 'merchant_cancelled';

                // Notify both parties
                require_once __DIR__ . '/../includes/mail_templates.php';
                require_once __DIR__ . '/../includes/mailer.php';
                $mailer = Mailer::getInstance();
                $stmtU = $db->prepare("SELECT id, username, email FROM users WHERE id IN (?, ?)");
                $stmtU->execute([$trade['buyer_id'], $trade['seller_id']]);
                $users = $stmtU->fetchAll();
                foreach ($users as $u) {
                    try {
                        $body = MailTemplates::orderCancelled($u['username'], $tradeId, $qty, $trade['coin_type'], $reason);
                        $mailer->send($u['email'], 'Trade #' . $tradeId . ' Cancelled', $body, 'noreply@robicodes.xyz', 'RobiCodes P2P');
                    } catch (Throwable $em) { /* skip mail error */ }
                }

                $response = ['success' => true, 'message' => 'Order cancelled.'];
            } catch (Throwable $e) { $db->rollBack(); $response['message'] = 'Server error.'; }
            break;

        // ─── DISPUTE / APPEAL TRADE ───
        case 'file_dispute':
            if (!$userId) { $response['message'] = 'Please log in.'; break; }
            $tradeId = (int)($req['trade_id'] ?? 0);
            $reason = trim($req['reason'] ?? '');
            $details = trim($req['details'] ?? '');
            if ($tradeId < 1 || $reason === '') { $response['message'] = 'Please provide a reason.'; break; }
            try {
                $db->beginTransaction();
                $stmt = $db->prepare("SELECT * FROM p2p_trades WHERE id = ? AND (buyer_id = ? OR seller_id = ?)");
                $stmt->execute([$tradeId, $userId, $userId]);
                $trade = $stmt->fetch();
                if (!$trade) { $db->rollBack(); $response['message'] = 'Trade not found.'; break; }
                if ($trade['status'] !== 'paid' && $trade['status'] !== 'pending') { $db->rollBack(); $response['message'] = 'Only pending/paid trades can be disputed.'; break; }
                
                // Change status to disputed
                $stmt = $db->prepare("UPDATE p2p_trades SET status = 'disputed' WHERE id = ?");
                $stmt->execute([$tradeId]);

                $reportedId = (int)$trade['buyer_id'] === (int)$userId ? (int)$trade['seller_id'] : (int)$trade['buyer_id'];
                $stmt = $db->prepare("INSERT INTO p2p_reports (trade_id, reporter_id, reported_id, reason, details) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$tradeId, $userId, $reportedId, $reason, $details]);
                
                $db->commit();
                createNotification($db, $reportedId, $userId, 'p2p_dispute', 'A dispute has been filed against trade #' . $tradeId . '. Trade is locked until admin reviews.');
                $response = ['success' => true, 'message' => 'Dispute filed. The trade is locked for admin review.'];
            } catch (Throwable $e) { $db->rollBack(); $response['message'] = 'Server error.'; }
            break;


        // ─── GET TRADE DETAIL ───
        case 'get_p2p_trade':
            if (!$userId) { $response['message'] = 'Please log in.'; break; }
            $tradeId = (int)($req['trade_id'] ?? 0);
            try {
                $stmt = $db->prepare("SELECT t.*, o.type AS offer_type, o.price_per_coin, ob.full_name AS buyer_name, os.full_name AS seller_name FROM p2p_trades t JOIN p2p_offers o ON o.id = t.offer_id LEFT JOIN users ob ON ob.id = t.buyer_id LEFT JOIN users os ON os.id = t.seller_id WHERE t.id = ? AND (t.buyer_id = ? OR t.seller_id = ?)");
                $stmt->execute([$tradeId, $userId, $userId]);
                $trade = $stmt->fetch();
                if (!$trade) { $response['message'] = 'Trade not found.'; break; }
                $stmt = $db->prepare("SELECT method, number, instruction FROM p2p_payment_settings WHERE user_id = ?");
                $stmt->execute([(int)$trade['seller_id']]);
                $paySettings = $stmt->fetchAll();
                $response = ['success' => true, 'trade' => $trade, 'payment_settings' => $paySettings];
            } catch (Throwable $e) { $response['message'] = 'Server error.'; }
            break;

        // ─── GET USER TRADES ───
        case 'get_user_trades':
            if (!$userId) { $response['message'] = 'Please log in.'; break; }
            try {
                $stmt = $db->prepare("SELECT t.*, o.type AS offer_type, ob.username AS buyer_name, os.username AS seller_name, (SELECT COUNT(*) FROM p2p_chat_messages cm WHERE cm.trade_id = t.id AND cm.sender_id != ? AND cm.is_read = 0) AS unread_count FROM p2p_trades t JOIN p2p_offers o ON o.id = t.offer_id LEFT JOIN users ob ON ob.id = t.buyer_id LEFT JOIN users os ON os.id = t.seller_id WHERE t.buyer_id = ? OR t.seller_id = ? ORDER BY t.created_at DESC LIMIT 30");
                $stmt->execute([$userId, $userId, $userId]);
                $trades = $stmt->fetchAll();
                $response = ['success' => true, 'trades' => $trades];
            } catch (Throwable $e) { $response['message'] = 'Server error.'; }
            break;

        // ─── GET UNREAD CHAT COUNTS ───
        case 'get_unread_chat_counts':
            if (!$userId) { $response['message'] = 'Please log in.'; break; }
            try {
                $stmt = $db->prepare("SELECT t.id AS trade_id, COUNT(m.id) AS unread_count FROM p2p_trades t JOIN p2p_chat_messages m ON m.trade_id = t.id WHERE (t.buyer_id = ? OR t.seller_id = ?) AND m.sender_id != ? AND m.is_read = 0 GROUP BY t.id");
                $stmt->execute([$userId, $userId, $userId]);
                $counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                $response = ['success' => true, 'counts' => $counts ? $counts : new stdClass()];
            } catch (Throwable $e) { $response['message'] = 'Server error.'; }
            break;

        // ─── SEND CHAT MESSAGE ───
        case 'send_p2p_message':
            if (!$userId) { $response['message'] = 'Please log in.'; break; }
            $tradeId = (int)($req['trade_id'] ?? 0);
            $message = trim($req['message'] ?? '');
            if ($message === '') { $response['message'] = 'Message cannot be empty.'; break; }
            try {
                $stmt = $db->prepare("SELECT id FROM p2p_trades WHERE id = ? AND (buyer_id = ? OR seller_id = ?)");
                $stmt->execute([$tradeId, $userId, $userId]);
                if (!$stmt->fetch()) { $response['message'] = 'Trade not found.'; break; }
                $stmt = $db->prepare("INSERT INTO p2p_chat_messages (trade_id, sender_id, message) VALUES (?, ?, ?)");
                $stmt->execute([$tradeId, $userId, $message]);
                $response = ['success' => true, 'message' => 'Message sent!', 'messages' => $loadP2PMessages($tradeId)];
            } catch (Throwable $e) { $response['message'] = 'Server error.'; }
            break;

        // ─── GET CHAT MESSAGES ───
        case 'get_p2p_messages':
            if (!$userId) { $response['message'] = 'Please log in.'; break; }
            $tradeId = (int)($req['trade_id'] ?? 0);
            try {
                $stmt = $db->prepare("SELECT id FROM p2p_trades WHERE id = ? AND (buyer_id = ? OR seller_id = ?)");
                $stmt->execute([$tradeId, $userId, $userId]);
                if (!$stmt->fetch()) { $response['message'] = 'Trade not found.'; break; }
                
                // Mark incoming messages as read
                $updateStmt = $db->prepare("UPDATE p2p_chat_messages SET is_read = 1 WHERE trade_id = ? AND sender_id != ? AND is_read = 0");
                $updateStmt->execute([$tradeId, $userId]);

                $response = ['success' => true, 'messages' => $loadP2PMessages($tradeId)];
            } catch (Throwable $e) { $response['message'] = 'Server error.'; }
            break;

        // ─── UPLOAD CHAT IMAGE ───
        case 'upload_chat_image':
            if (!$userId) { $response['message'] = 'Please log in.'; break; }
            $tradeId = (int)($_POST['trade_id'] ?? 0);
            if (!isset($_FILES['chat_image']) || $_FILES['chat_image']['error'] !== UPLOAD_ERR_OK) {
                $response['message'] = 'Image upload failed.'; break;
            }
            try {
                $stmt = $db->prepare("SELECT id FROM p2p_trades WHERE id = ? AND (buyer_id = ? OR seller_id = ?)");
                $stmt->execute([$tradeId, $userId, $userId]);
                if (!$stmt->fetch()) { $response['message'] = 'Trade not found.'; break; }
                
                $ext = strtolower(pathinfo($_FILES['chat_image']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg','jpeg','png'])) { $response['message'] = 'Only JPG/PNG allowed.'; break; }
                
                $uploadDir = __DIR__ . '/../assets/images/chat/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $filename = 'p2p_' . $tradeId . '_' . time() . '_' . rand(100,999) . '.' . $ext;
                
                if (move_uploaded_file($_FILES['chat_image']['tmp_name'], $uploadDir . $filename)) {
                    $stmt = $db->prepare("INSERT INTO p2p_chat_messages (trade_id, sender_id, message, image_path) VALUES (?, ?, '', ?)");
                    $stmt->execute([$tradeId, $userId, $filename]);
                    $response = ['success' => true, 'message' => 'Image sent!', 'messages' => $loadP2PMessages($tradeId)];
                } else {
                    $response['message'] = 'Failed to save image.';
                }
            } catch (Throwable $e) { $response['message'] = 'Server error.'; }
            break;

        // ─── GET P2P PAYMENT SETTINGS (for merchant details) ───
        case 'get_merchant_payment_details':
            if (!$userId) { $response['message'] = 'Please log in.'; break; }
            $merchantId = (int)($req['merchant_id'] ?? 0);
            if (!$merchantId) { $response['message'] = 'Invalid merchant.'; break; }
            try {
                $stmt = $db->prepare("SELECT method, number, instruction FROM p2p_payment_settings WHERE user_id = ?");
                $stmt->execute([$merchantId]);
                $settings = $stmt->fetchAll();
                $response = ['success' => true, 'payment_settings' => $settings];
            } catch (Throwable $e) { $response['message'] = 'Server error.'; }
            break;

        // ─── GET P2P PAYMENT SETTINGS ───
        case 'get_p2p_payment_settings':
            if (!$userId) { $response['message'] = 'Please log in.'; break; }
            try {
                $stmt = $db->prepare("SELECT * FROM p2p_payment_settings WHERE user_id = ?");
                $stmt->execute([$userId]);
                $settings = $stmt->fetchAll();
                if (empty($settings)) {
                    foreach (['bkash','nagad','rocket'] as $m) {
                        $stmt = $db->prepare("INSERT IGNORE INTO p2p_payment_settings (user_id, method, number, instruction) VALUES (?, ?, '01888780877', 'send_money')");
                        $stmt->execute([$userId, $m]);
                    }
                    $stmt = $db->prepare("SELECT * FROM p2p_payment_settings WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    $settings = $stmt->fetchAll();
                }
                $response = ['success' => true, 'settings' => $settings];
            } catch (Throwable $e) { $response['message'] = 'Server error.'; }
            break;

        // ─── SAVE P2P PAYMENT SETTINGS ───
        case 'save_p2p_payment_settings':
            if (!$userId) { $response['message'] = 'Please log in.'; break; }
            $settings = $req['settings'] ?? [];
            if (empty($settings) || !is_array($settings)) { $response['message'] = 'Invalid settings.'; break; }
            try {
                $isNew = false;
                foreach ($settings as $s) {
                    $method = trim($s['method'] ?? '');
                    $number = trim($s['number'] ?? '');
                    $instruction = trim($s['instruction'] ?? 'send_money');
                    if (!in_array($method, ['bkash','nagad','rocket'])) continue;
                    if ($number === '') continue;
                    $stmt = $db->prepare("SELECT id FROM p2p_payment_settings WHERE user_id = ? AND method = ?");
                    $stmt->execute([$userId, $method]);
                    if (!$stmt->fetch()) $isNew = true;
                    $stmt = $db->prepare("INSERT INTO p2p_payment_settings (user_id, method, number, instruction) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE number = VALUES(number), instruction = VALUES(instruction)");
                    $stmt->execute([$userId, $method, $number, $instruction]);
                }
                $response = ['success' => true, 'message' => 'Payment settings saved!'];
                $username = $_SESSION['username'] ?? 'User';
                $action = $isNew ? 'added' : 'updated';
                $methodLabels = ['bkash'=>'bKash','nagad'=>'Nagad','rocket'=>'Rocket'];
                $instLabels = ['send_money'=>'Send Money','cashout'=>'Cash Out'];
                $details = [];
                foreach ($settings as $s) {
                    $m = trim($s['method'] ?? ''); $n = trim($s['number'] ?? ''); $i = trim($s['instruction'] ?? 'send_money');
                    if (!in_array($m, ['bkash','nagad','rocket']) || $n === '') continue;
                    $details[] = ($methodLabels[$m] ?? $m) . ': ' . $n . ' (' . ($instLabels[$i] ?? $i) . ')';
                }
                $detailStr = implode(', ', $details);
                createNotification($db, $userId, $userId, 'p2p_payment_updated', "Payment method $action: $detailStr");
                $userEmail = $_SESSION['email'] ?? '';
                if ($userEmail) {
                    require_once __DIR__ . '/../includes/mailer.php';
                    require_once __DIR__ . '/../includes/mail_templates.php';
                    try {
                        $validMethods = array_filter($settings, function($s) {
                            $m = trim($s['method'] ?? ''); $n = trim($s['number'] ?? '');
                            return in_array($m, ['bkash','nagad','rocket']) && $n !== '';
                        });
                        if ($validMethods) {
                            $mailer = Mailer::getInstance();
                            $body = MailTemplates::paymentMethodUpdated($username, $isNew ? 'added' : 'updated', $validMethods);
                            $imgDir = __DIR__ . '/../assets/images/payment-icon';
                            $embedded = [];
                            foreach (['bkash','nagad','rocket'] as $m) {
                                $f = $imgDir . '/' . $m . '-logo-mobile-banking.png';
                                if (file_exists($f)) $embedded[$m . '-logo'] = $f;
                            }
                            $mailer->send($userEmail, 'Payment Method ' . ($isNew ? 'Added' : 'Updated'), $body, null, null, $embedded);
                        }
                    } catch (Throwable $e) { error_log("Payment settings email error: " . $e->getMessage()); }
                }
            } catch (Throwable $e) { $response['message'] = 'Server error.'; }
            break;

        // ─── COIN CONVERSION ───
        case 'convert_coins':
            if (!$userId) { $response['message'] = 'Please log in.'; break; }
            $fromType = trim($req['from_type'] ?? '');
            $toType = trim($req['to_type'] ?? '');
            $qty = abs((int)($req['quantity'] ?? 0));
            if (!in_array($fromType, ['bronze','silver','gold']) || !in_array($toType, ['bronze','silver','gold'])) { $response['message'] = 'Invalid coin type.'; break; }
            if ($fromType === $toType) { $response['message'] = 'Cannot convert to same type.'; break; }
            if ($qty < 1) { $response['message'] = 'Minimum 1 coin.'; break; }

            $values = ['bronze'=>25, 'silver'=>50, 'gold'=>100];
            $fromVal = $values[$fromType];
            $toVal = $values[$toType];
            $totalFromVal = $qty * $fromVal;
            $resultQty = intdiv($totalFromVal, $toVal);
            $remainder = $totalFromVal % $toVal;
            if ($resultQty < 1) { $response['message'] = "Not enough value. $qty $fromType = ৳$totalFromVal, minimum 1 $toType = ৳$toVal."; break; }

            try {
                $db->beginTransaction();
                $coinColFrom = $fromType . '_coins';
                $coinColTo = $toType . '_coins';
                $stmt = $db->prepare("SELECT $coinColFrom FROM users WHERE id = ? FOR UPDATE");
                $stmt->execute([$userId]);
                $u = $stmt->fetch();
                if (!$u || (int)$u[$coinColFrom] < $qty) { $db->rollBack(); $response['message'] = 'Insufficient ' . $fromType . ' coins.'; break; }

                $stmt = $db->prepare("UPDATE users SET $coinColFrom = $coinColFrom - ? WHERE id = ?");
                $stmt->execute([$qty, $userId]);
                $stmt = $db->prepare("UPDATE users SET $coinColTo = $coinColTo + ? WHERE id = ?");
                $stmt->execute([$resultQty, $userId]);

                $db->commit();
                $remainderNote = $remainder > 0 ? " ($remainder ৳ value unused)" : '';
                createNotification($db, $userId, $userId, 'coin_conversion', "Converted $qty $fromType → $resultQty $toType coins.$remainderNote");
                $response = ['success' => true, 'message' => "Converted $qty $fromType → $resultQty $toType!$remainderNote"];
            } catch (Throwable $e) { $db->rollBack(); $response['message'] = 'Server error.'; }
            break;

        // ─── EDIT P2P OFFER ───
        case 'edit_p2p_offer':
            if (!$userId) { $response['message'] = 'Please log in.'; break; }
            $userRole = $_SESSION['role'] ?? 'user';
            if ($userRole !== 'merchant' && $userRole !== 'admin') { $response['message'] = 'Only merchants can edit offers.'; break; }
            $offerId = (int)($req['offer_id'] ?? 0);
            $price = abs((float)($req['price'] ?? 0));
            $minAmt = abs((int)($req['min_amount'] ?? 1));
            $maxAmt = abs((int)($req['max_amount'] ?? 0));
            if ($offerId < 1 || $price < 1) { $response['message'] = 'Invalid price.'; break; }
            if ($maxAmt > 0 && $maxAmt < $minAmt) { $response['message'] = 'Max must be >= Min.'; break; }
            try {
                $stmt = $db->prepare("UPDATE p2p_offers SET price_per_coin = ?, min_amount = ?, max_amount = ? WHERE id = ? AND agent_id = ? AND status = 'active'");
                $stmt->execute([$price, $minAmt, $maxAmt, $offerId, $userId]);
                if ($stmt->rowCount()) {
                    $response = ['success' => true, 'message' => 'Offer updated!'];
                } else {
                    $response['message'] = 'Offer not found or inactive.';
                }
            } catch (Throwable $e) { $response['message'] = 'Server error.'; }
            break;

        // ─── SUBMIT P2P REVIEW ───
        case 'submit_p2p_review':
            if (!$userId) { $response['message'] = 'Please log in.'; break; }
            $tradeId = (int)($req['trade_id'] ?? 0);
            $rating = (int)($req['rating'] ?? 0);
            $comment = trim($req['comment'] ?? '');
            if ($rating < 1 || $rating > 5) { $response['message'] = 'Rating must be 1-5.'; break; }
            try {
                $stmt = $db->prepare("SELECT * FROM p2p_trades WHERE id = ? AND (buyer_id = ? OR seller_id = ?) AND status = 'completed'");
                $stmt->execute([$tradeId, $userId, $userId]);
                $trade = $stmt->fetch();
                if (!$trade) { $response['message'] = 'Trade not found or not completed.'; break; }
                $stmt = $db->prepare("SELECT id, agent_id FROM p2p_offers WHERE id = ?");
                $stmt->execute([$trade['offer_id']]);
                $offer = $stmt->fetch();
                if (!$offer) { $response['message'] = 'Offer not found.'; break; }
                // Determine merchant: the offer creator (agent) is always the merchant
                $merchantId = (int)$offer['agent_id'];
                // Check if user already reviewed this merchant
                $stmt = $db->prepare("SELECT id FROM p2p_reviews WHERE reviewer_id = ? AND merchant_id = ?");
                $stmt->execute([$userId, $merchantId]);
                if ($stmt->fetch()) { $response['message'] = 'You have already reviewed this merchant.'; break; }
                $stmt = $db->prepare("INSERT INTO p2p_reviews (trade_id, reviewer_id, merchant_id, rating, comment) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$tradeId, $userId, $merchantId, $rating, $comment]);
                $response = ['success' => true, 'message' => 'Review submitted! Thank you.'];
            } catch (Throwable $e) { $response['message'] = 'Server error.'; }
            break;

        // ─── GET MERCHANT RATING ───
        case 'get_merchant_rating':
            $merchantId = (int)($req['merchant_id'] ?? 0);
            if (!$merchantId) { $response['message'] = 'Invalid merchant.'; break; }
            try {
                $stmt = $db->prepare("SELECT ROUND(AVG(rating),1) AS avg_rating, COUNT(*) AS total_reviews FROM p2p_reviews WHERE merchant_id = ?");
                $stmt->execute([$merchantId]);
                $row = $stmt->fetch();
                $response = ['success' => true, 'avg_rating' => (float)($row['avg_rating'] ?? 0), 'total_reviews' => (int)($row['total_reviews'] ?? 0)];
            } catch (Throwable $e) { $response['message'] = 'Server error.'; }
            break;

        // ─── GET MERCHANT PROFILE ───
        case 'get_merchant_profile':
            $merchantId = (int)($req['merchant_id'] ?? 0);
            if (!$merchantId) { $response['message'] = 'Invalid merchant.'; break; }
            try {
                $stmt = $db->prepare("SELECT id, username, full_name, avatar, registered_at, role, EXISTS(SELECT 1 FROM user_sessions us WHERE us.user_id = users.id AND us.expires_at > NOW() AND us.last_activity >= (UNIX_TIMESTAMP() - 300)) AS is_online FROM users WHERE id = ? AND role IN ('merchant','admin')");
                $stmt->execute([$merchantId]);
                $merchant = $stmt->fetch();
                if (!$merchant) { $response['message'] = 'Merchant not found.'; break; }

                // Trade stats
                $stmt = $db->prepare("SELECT COUNT(*) AS total, SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) AS completed, COALESCE(SUM(CASE WHEN status='completed' THEN total_price ELSE 0 END),0) AS volume FROM p2p_trades WHERE seller_id = ? OR buyer_id = ?");
                $stmt->execute([$merchantId, $merchantId]);
                $stats = $stmt->fetch();
                $totalTrades = (int)($stats['total'] ?? 0);
                $completedTrades = (int)($stats['completed'] ?? 0);
                $volume = (float)($stats['volume'] ?? 0);
                $completionRate = $totalTrades > 0 ? round(($completedTrades / $totalTrades) * 100) : 0;

                // Active offers
                $stmt = $db->prepare("SELECT COUNT(*) FROM p2p_offers WHERE agent_id = ? AND status = 'active' AND remaining > 0");
                $stmt->execute([$merchantId]);
                $activeOffers = (int)$stmt->fetchColumn();

                // Rating
                $stmt = $db->prepare("SELECT ROUND(AVG(rating),1) AS avg_rating, COUNT(*) AS total_reviews FROM p2p_reviews WHERE merchant_id = ?");
                $stmt->execute([$merchantId]);
                $ratingRow = $stmt->fetch();
                $avgRating = (float)($ratingRow['avg_rating'] ?? 0);
                $totalReviews = (int)($ratingRow['total_reviews'] ?? 0);

                // Recent reviews with reviewer info
                $stmt = $db->prepare("SELECT r.id, r.rating, r.comment, r.created_at, u.full_name, u.username, u.avatar FROM p2p_reviews r JOIN users u ON u.id = r.reviewer_id WHERE r.merchant_id = ? ORDER BY r.created_at DESC LIMIT 10");
                $stmt->execute([$merchantId]);
                $reviews = $stmt->fetchAll();

                $response = ['success' => true, 'merchant' => $merchant, 'stats' => ['total_trades' => $totalTrades, 'completed_trades' => $completedTrades, 'completion_rate' => $completionRate, 'volume' => $volume, 'active_offers' => $activeOffers, 'avg_rating' => $avgRating, 'total_reviews' => $totalReviews], 'reviews' => $reviews];
            } catch (Throwable $e) { $response['message'] = 'Server error.'; }
            break;

        // ─── LOAD MORE OFFERS ───
        case 'load_more_offers':
            $type = $req['type'] ?? 'sell';
            $offset = (int)($req['offset'] ?? 0);
            $limit = 20;
            $orderDir = $type === 'sell' ? 'ASC' : 'DESC';
            try {
                $stmt = $db->prepare("SELECT o.*, u.username, u.full_name, u.avatar, EXISTS(SELECT 1 FROM user_sessions us WHERE us.user_id = u.id AND us.expires_at > NOW() AND us.last_activity >= (UNIX_TIMESTAMP() - 300)) AS is_online FROM p2p_offers o JOIN users u ON u.id = o.agent_id WHERE o.type = ? AND o.status = 'active' AND o.remaining > 0 ORDER BY o.price_per_coin $orderDir LIMIT ? OFFSET ?");
                $stmt->execute([$type, $limit, $offset]);
                $offers = $stmt->fetchAll();
                $response = ['success' => true, 'offers' => $offers, 'has_more' => count($offers) === $limit];
            } catch (Throwable $e) { $response['message'] = 'Server error.'; }
            break;

        // ─── LOAD MORE TRADES ───
        case 'load_more_trades':
            if (!$userId) { $response['message'] = 'Please log in.'; break; }
            $offset = (int)($req['offset'] ?? 0);
            $limit = 20;
            try {
                $stmt = $db->prepare("SELECT t.*, o.type AS offer_type, ob.username AS buyer_name, os.username AS seller_name, (SELECT COUNT(*) FROM p2p_chat_messages cm WHERE cm.trade_id = t.id AND cm.sender_id != ? AND cm.is_read = 0) AS unread_count FROM p2p_trades t JOIN p2p_offers o ON o.id = t.offer_id LEFT JOIN users ob ON ob.id = t.buyer_id LEFT JOIN users os ON os.id = t.seller_id WHERE t.buyer_id = ? OR t.seller_id = ? ORDER BY t.created_at DESC LIMIT ? OFFSET ?");
                $stmt->execute([$userId, $userId, $userId, $limit, $offset]);
                $trades = $stmt->fetchAll();
                $response = ['success' => true, 'trades' => $trades, 'has_more' => count($trades) === $limit];
            } catch (Throwable $e) { $response['message'] = 'Server error.'; }
            break;

        default:
            $response['message'] = 'Unknown action.';
    }
} catch (Throwable $e) {
    $response['message'] = 'Server error.';
}

echo json_encode($response);
exit;
