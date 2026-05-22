<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => ''];
$security = new Security();
$raw = file_get_contents('php://input');
$json = json_decode($raw, true);
$req = is_array($json) ? $json : $_POST;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$security->validateCSRFToken($req['csrf_token'] ?? '')) {
    $response['message'] = 'Invalid security token';
    echo json_encode($response); exit;
}

$userId = $_SESSION['user_id'] ?? null;
$action = $req['action'] ?? ($_GET['action'] ?? '');

try {
    $db = Database::getInstance()->getConnection();

    switch ($action) {

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

                $stmt = $db->prepare("INSERT INTO p2p_trades (offer_id, seller_id, buyer_id, coin_type, quantity, total_price, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
                $stmt->execute([$offerId, $sellerId, $buyerId, $offer['coin_type'], $qty, $totalPrice]);
                $tradeId = $db->lastInsertId();

                $db->commit();
                $orderType = $offer['type'] === 'sell' ? 'buy' : 'sell';
                createNotification($db, (int)$offer['agent_id'], $userId, 'p2p_order_placed', 'New ' . $orderType . ' order placed for ' . $qty . ' ' . $offer['coin_type'] . ' coins (৳' . number_format($totalPrice, 0) . ').');
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
                $stmt = $db->prepare("UPDATE p2p_trades SET status = 'paid', payment_method = ?, sender_phone = ?, txid = ?, paid_at = NOW() WHERE id = ?");
                $stmt->execute([$method, $senderPhone, $txid, $tradeId]);
                createNotification($db, (int)$trade['seller_id'], $userId, 'p2p_payment_received', 'Payment marked for trade #' . $tradeId . '. ' . ($trade['offer_type'] === 'sell' ? 'Release coins to buyer.' : 'Release coins to complete the trade.'));
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

                // Seller has the coins - deduct from seller, give to buyer
                $stmt = $db->prepare("SELECT $coinCol FROM users WHERE id = ? FOR UPDATE");
                $stmt->execute([(int)$trade['seller_id']]);
                $sellerRow = $stmt->fetch();
                if (!$sellerRow || (int)$sellerRow[$coinCol] < $qty) { $db->rollBack(); $response['message'] = 'Insufficient coins to complete this trade.'; break; }

                $stmt = $db->prepare("UPDATE users SET $coinCol = $coinCol - ? WHERE id = ?");
                $stmt->execute([$qty, (int)$trade['seller_id']]);
                $stmt = $db->prepare("UPDATE users SET $coinCol = $coinCol + ? WHERE id = ?");
                $stmt->execute([$qty, (int)$trade['buyer_id']]);

                $stmt = $db->prepare("UPDATE p2p_trades SET status = 'completed', completed_at = NOW() WHERE id = ?");
                $stmt->execute([$tradeId]);

                $stmt = $db->prepare("INSERT INTO coin_transactions (from_user_id, to_user_id, type, coin_type, amount, description, ref_id) VALUES (?, ?, 'p2p_sell', ?, ?, ?, ?)");
                $stmt->execute([(int)$trade['seller_id'], (int)$trade['buyer_id'], $trade['coin_type'], $qty, 'P2P trade #' . $tradeId, $tradeId]);

                $db->commit();
                createNotification($db, (int)$trade['buyer_id'], $userId, 'p2p_trade_completed', 'Trade #' . $tradeId . ' completed! ' . $qty . ' ' . $trade['coin_type'] . ' coins released.');
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

                // Restore offer remaining (no refund needed, funds weren't held)
                $stmt = $db->prepare("UPDATE p2p_offers SET remaining = remaining + ?, status = 'active' WHERE id = ?");
                $stmt->execute([$qty, (int)$trade['offer_id']]);

                $stmt = $db->prepare("UPDATE p2p_trades SET status = 'cancelled' WHERE id = ?");
                $stmt->execute([$tradeId]);
                $db->commit();
                $response = ['success' => true, 'message' => 'Order cancelled.'];
            } catch (Throwable $e) { $db->rollBack(); $response['message'] = 'Server error.'; }
            break;

        // ─── REPORT TRADE ───
        case 'report_p2p_trade':
            if (!$userId) { $response['message'] = 'Please log in.'; break; }
            $tradeId = (int)($req['trade_id'] ?? 0);
            $reason = trim($req['reason'] ?? '');
            $details = trim($req['details'] ?? '');
            if ($tradeId < 1 || $reason === '') { $response['message'] = 'Please provide a reason.'; break; }
            try {
                $stmt = $db->prepare("SELECT * FROM p2p_trades WHERE id = ? AND (buyer_id = ? OR seller_id = ?)");
                $stmt->execute([$tradeId, $userId, $userId]);
                $trade = $stmt->fetch();
                if (!$trade) { $response['message'] = 'Trade not found.'; break; }
                $reportedId = (int)$trade['buyer_id'] === (int)$userId ? (int)$trade['seller_id'] : (int)$trade['buyer_id'];
                $stmt = $db->prepare("INSERT INTO p2p_reports (trade_id, reporter_id, reported_id, reason, details) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$tradeId, $userId, $reportedId, $reason, $details]);
                createNotification($db, 0, $userId, 'p2p_order_placed', 'Report filed for trade #' . $tradeId . '. Admin will review.');
                $response = ['success' => true, 'message' => 'Report submitted. Admin will review.'];
            } catch (Throwable $e) { $response['message'] = 'Server error.'; }
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
                $stmt = $db->prepare("SELECT t.*, o.type AS offer_type, ob.username AS buyer_name, os.username AS seller_name FROM p2p_trades t JOIN p2p_offers o ON o.id = t.offer_id LEFT JOIN users ob ON ob.id = t.buyer_id LEFT JOIN users os ON os.id = t.seller_id WHERE t.buyer_id = ? OR t.seller_id = ? ORDER BY t.created_at DESC LIMIT 30");
                $stmt->execute([$userId, $userId]);
                $trades = $stmt->fetchAll();
                $response = ['success' => true, 'trades' => $trades];
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
                $response = ['success' => true, 'message' => 'Message sent!'];
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
                $stmt = $db->prepare("SELECT m.*, u.username, u.full_name FROM p2p_chat_messages m JOIN users u ON u.id = m.sender_id WHERE m.trade_id = ? ORDER BY m.created_at ASC");
                $stmt->execute([$tradeId]);
                $msgs = $stmt->fetchAll();
                $response = ['success' => true, 'messages' => $msgs];
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
                foreach ($settings as $s) {
                    $method = trim($s['method'] ?? '');
                    $number = trim($s['number'] ?? '');
                    $instruction = trim($s['instruction'] ?? 'send_money');
                    if (!in_array($method, ['bkash','nagad','rocket'])) continue;
                    if ($number === '') continue;
                    $stmt = $db->prepare("INSERT INTO p2p_payment_settings (user_id, method, number, instruction) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE number = VALUES(number), instruction = VALUES(instruction)");
                    $stmt->execute([$userId, $method, $number, $instruction]);
                }
                $response = ['success' => true, 'message' => 'Payment settings saved!'];
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

        default:
            $response['message'] = 'Unknown action.';
    }
} catch (Throwable $e) {
    $response['message'] = 'Server error.';
}

echo json_encode($response);
exit;
