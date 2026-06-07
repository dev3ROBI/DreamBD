<?php
$db = Database::getInstance()->getConnection();
$viewerId = $_SESSION['user_id'] ?? null;
$userName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User';
$userRole = $_SESSION['role'] ?? 'user';
$userAvatar = $_SESSION['avatar'] ?? 'default.png';

$balance = 0; $bronzeCoins = 0; $silverCoins = 0; $goldCoins = 0;
if ($viewerId) {
    $stmt = $db->prepare("SELECT balance, bronze_coins, silver_coins, gold_coins FROM users WHERE id = ?");
    $stmt->execute([$viewerId]);
    $u = $stmt->fetch();
    $balance = (float)($u['balance'] ?? 0);
    $bronzeCoins = (int)($u['bronze_coins'] ?? 0);
    $silverCoins = (int)($u['silver_coins'] ?? 0);
    $goldCoins = (int)($u['gold_coins'] ?? 0);
}

// Load per-method payment settings from DB
$pmData = ['bkash' => ['number' => '01888780877', 'instruction' => 'send_money'],
           'nagad' => ['number' => '01888780877', 'instruction' => 'send_money'],
           'rocket' => ['number' => '01888780877', 'instruction' => 'send_money']];
try {
    $stmt = $db->query("SELECT `key`, `value` FROM site_settings WHERE `key` LIKE 'payment_%'");
    while ($row = $stmt->fetch()) {
        foreach (['bkash','nagad','rocket'] as $m) {
            if ($row['key'] === "payment_{$m}_number") $pmData[$m]['number'] = $row['value'] ?: '01888780877';
            if ($row['key'] === "payment_{$m}_instruction") $pmData[$m]['instruction'] = $row['value'] ?: 'send_money';
        }
    }
} catch (Throwable $e) {}

// Combined history
$history = [];
if ($viewerId) {
    // Payment requests
    $stmt = $db->prepare("SELECT 'payment' AS src, id, NULL AS from_id, user_id AS to_id, method AS ref1, transaction_id AS ref2, amount, status, purpose, created_at FROM payment_requests WHERE user_id = ?");
    $stmt->execute([$viewerId]);
    $payments = $stmt->fetchAll();
    foreach ($payments as &$p) { $history[] = $p; } unset($p);

    // Coin transactions
    $stmt = $db->prepare("SELECT 'coin' AS src, ct.id, ct.from_user_id, ct.to_user_id, ct.type AS ref1, ct.description AS ref2, ct.amount, ct.type AS status, '' AS purpose, ct.created_at, u.username AS other_username FROM coin_transactions ct LEFT JOIN users u ON (CASE WHEN ct.type='transfer_sent' THEN u.id=ct.to_user_id ELSE u.id=ct.from_user_id END) WHERE ct.from_user_id = ? OR ct.to_user_id = ?");
    $stmt->execute([$viewerId, $viewerId]);
    $coinTx = $stmt->fetchAll();
    foreach ($coinTx as &$c) { $history[] = $c; } unset($c);
}
usort($history, function($a, $b) { return strtotime($b['created_at']) - strtotime($a['created_at']); });
$history = array_slice($history, 0, 100);

// Send coins cooldown check
$canSend = true;
$lastSend = '';
if ($viewerId) {
    $stmt = $db->prepare("SELECT created_at FROM coin_transactions WHERE from_user_id = ? AND type = 'transfer_sent' AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) LIMIT 1");
    $stmt->execute([$viewerId]);
    $canSend = !$stmt->fetch();
    if (!$canSend) {
        $stmt = $db->prepare("SELECT MAX(created_at) AS last FROM coin_transactions WHERE from_user_id = ? AND type = 'transfer_sent'");
        $stmt->execute([$viewerId]);
        $r = $stmt->fetch();
        $lastSend = $r['last'] ?? '';
    }
}

$csrfToken = $security->generateCSRFToken();
$avatarPath = $userAvatar && $userAvatar !== 'default.png'
    ? (str_starts_with($userAvatar, 'assets/') ? htmlspecialchars($userAvatar) : 'assets/avatars/' . htmlspecialchars($userAvatar))
    : 'assets/avatars/default.png';
?>
<style>
/* ── Page Reset ── */
.bal-v3-page { max-width:540px; margin:0 auto; padding:16px 12px 48px; position:relative; z-index:1; font-family:'Plus Jakarta Sans',sans-serif }
@keyframes balFadeIn { from { opacity:0; transform:translateY(20px) scale(.96) } to { opacity:1; transform:translateY(0) scale(1) } }

/* ── Transaction Detail Premium ── */
.tx-detail-hero { padding:24px 20px; text-align:center; background:linear-gradient(135deg,var(--bg-secondary),var(--bg-tertiary)); border-bottom:1px solid var(--border-light) }
.tx-detail-amount { font-size:32px; font-weight:900; letter-spacing:-.5px; margin-bottom:4px; display:flex; align-items:center; justify-content:center; gap:8px }
.tx-detail-amount.pos { color:#059669 }
.tx-detail-amount.neg { color:#dc2626 }
.tx-detail-amount.neutral { color:var(--text-primary) }
.tx-detail-amount img { width:32px; height:32px }
.tx-detail-status { font-size:11px; font-weight:800; text-transform:uppercase; padding:4px 12px; border-radius:999px; display:inline-block; letter-spacing:.5px }
.tx-detail-status.completed, .tx-detail-status.pos { background:#d1fae5; color:#065f46 }
.tx-detail-status.pending { background:#fef9c3; color:#854d0e }
.tx-detail-status.cancelled, .tx-detail-status.neg { background:#fef2f2; color:#991b1b }
.tx-detail-grid { padding:20px }
.tx-detail-row { display:flex; justify-content:space-between; align-items:center; padding:12px 0; border-bottom:1px solid var(--border-light) }
.tx-detail-row:last-child { border-bottom:0 }
.tx-detail-lbl { font-size:13px; font-weight:600; color:var(--text-tertiary) }
.tx-detail-val { font-size:13px; font-weight:700; color:var(--text-primary); text-align:right }
.tx-detail-copy { margin-left:6px; color:var(--primary); cursor:pointer; font-size:12px }

/* ── Close button premium ── */
.gp-modal-close-btn { width:34px; height:34px; border-radius:50%; border:0; background:rgba(0,0,0,.05); cursor:pointer; display:flex; align-items:center; justify-content:center; color:#6b7280; font-size:14px; transition:all .2s; backdrop-filter:blur(4px); flex-shrink:0 }
.gp-modal-close-btn:hover { background:rgba(239,68,68,.12); color:#dc2626; transform:rotate(90deg) }
.dark .gp-modal-close-btn { background:rgba(255,255,255,.08); color:#9ca3af }
.dark .gp-modal-close-btn:hover { background:rgba(239,68,68,.2); color:#fca5a5 }

/* ── Result Box Premium ── */
.gp-result-box { background:#fff; border-radius:32px; padding:2.2rem 2rem 1.8rem; max-width:340px; width:100%; text-align:center; box-shadow:0 50px 100px rgba(0,0,0,.3); animation:gpModalIn .4s cubic-bezier(.34,1.56,.64,1); position:relative; overflow:hidden }
.dark .gp-result-box { background:#1e293b }
.gp-result-box::before { content:''; position:absolute; top:0; left:0; right:0; height:4px; background:linear-gradient(90deg,#059669,#10b981); border-radius:32px 32px 0 0 }
.gp-result-box.fail::before { background:linear-gradient(90deg,#dc2626,#ef4444) }
.gp-result-icon-wrap { position:relative; width:80px; height:80px; margin:0 auto 14px }
.gp-result-circle { position:absolute; inset:0; width:80px; height:80px; transform:rotate(-90deg) }
.gp-result-circle-bg { fill:none; stroke:#e5e7eb; stroke-width:4 }
.dark .gp-result-circle-bg { stroke:#374151 }
.gp-result-circle-fill { fill:none; stroke-width:4; stroke-linecap:round; transition:stroke-dashoffset .6s cubic-bezier(.34,1.56,.64,1) }
.gp-result-circle-fill.success { stroke:#059669 }
.gp-result-circle-fill.fail { stroke:#dc2626 }
.gp-result-icon { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; font-size:32px; color:#059669; animation:gpResultPop .4s cubic-bezier(.34,1.56,.64,1) .2s both }
.gp-result-box.fail .gp-result-icon { color:#dc2626 }
@keyframes gpResultPop { 0% { transform:scale(0); opacity:0 } 100% { transform:scale(1); opacity:1 } }
.gp-result-title { font-size:20px; font-weight:800; color:#111827; margin-bottom:6px }
.dark .gp-result-title { color:#f1f5f9 }
.gp-result-sub { font-size:13px; color:#6b7280; line-height:1.5; margin-bottom:16px }
.dark .gp-result-sub { color:#94a3b8 }
.gp-result-dismiss { padding:10px 28px; border-radius:999px; border:0; font-size:13px; font-weight:700; cursor:pointer; background:#f3f4f6; color:#374151; transition:all .15s; font-family:'Plus Jakarta Sans',sans-serif }
.gp-result-dismiss:hover { background:#e5e7eb; transform:translateY(-1px) }
.dark .gp-result-dismiss { background:#374151; color:#e2e8f0 }
.dark .gp-result-dismiss:hover { background:#4b5563 }

/* ── History scrollable ── */
.bal-v3-history-scroll { max-height:420px; overflow-y:auto; overflow-x:hidden; -ms-overflow-style:none; scrollbar-width:thin; scrollbar-color:#d1d5db transparent }
.bal-v3-history-scroll::-webkit-scrollbar { width:4px }
.bal-v3-history-scroll::-webkit-scrollbar-track { background:transparent }
.bal-v3-history-scroll::-webkit-scrollbar-thumb { background:#d1d5db; border-radius:99px }
.dark .bal-v3-history-scroll::-webkit-scrollbar-thumb { background:#4b5563 }

/* ── Send Coins modal premium ── */
.sc-balance-card { background:linear-gradient(135deg,#f5f3ff,#ede9fe); border-radius:16px; padding:16px; margin-bottom:16px; border:1px solid #ddd6fe; display:flex; align-items:center; justify-content:space-between }
.sc-balance-label { font-size:10px; font-weight:700; color:#7c3aed; text-transform:uppercase; letter-spacing:.8px; margin-bottom:2px }
.sc-balance-value { font-size:26px; font-weight:900; color:#5b21b6; display:flex; align-items:center; gap:6px }
.sc-balance-value img { width:26px; height:26px }
.sc-field-wrap { margin-bottom:14px; position:relative }
.sc-field-wrap label { display:block; font-size:12px; font-weight:700; color:#64748b; margin-bottom:6px; letter-spacing:.2px }
.dark .sc-field-wrap label { color:#94a3b8 }
.sc-input { width:100%; padding:13px 14px; border-radius:14px; border:2px solid #e2e8f0; background:#fff; font-size:15px; font-weight:600; outline:none; box-sizing:border-box; transition:border-color .2s,box-shadow .2s; font-family:'Plus Jakarta Sans',sans-serif; color:#1f2937 }
.sc-input:focus { border-color:#8b5cf6; box-shadow:0 0 0 4px rgba(139,92,246,.1) }
.dark .sc-input { background:#1e293b; border-color:#334155; color:#f1f5f9 }
.dark .sc-input:focus { border-color:#a78bfa; box-shadow:0 0 0 4px rgba(167,139,250,.15) }

/* ── User search results dropdown ── */
.sc-user-results { position:absolute; top:100%; left:0; right:0; z-index:50; background:#fff; border-radius:14px; border:1px solid #e2e8f0; box-shadow:0 16px 48px rgba(0,0,0,.12); max-height:220px; overflow-y:auto; margin-top:4px; display:none }
.dark .sc-user-results { background:#1e293b; border-color:#334155; box-shadow:0 16px 48px rgba(0,0,0,.3) }
.sc-user-result-item { display:flex; align-items:center; gap:10px; padding:10px 14px; cursor:pointer; transition:all .1s; border-bottom:1px solid #f1f5f9 }
.sc-user-result-item:last-child { border-bottom:0 }
.sc-user-result-item:hover { background:#f5f3ff }
.dark .sc-user-result-item:hover { background:rgba(139,92,246,.1) }
.sc-user-result-item .avatar { width:36px; height:36px; border-radius:50%; background:linear-gradient(135deg,#8b5cf6,#6366f1); display:flex; align-items:center; justify-content:center; color:#fff; font-size:13px; font-weight:800; flex-shrink:0 }
.sc-user-result-item .info { flex:1 }
.sc-user-result-item .info .name { font-size:13px; font-weight:700; color:#1f2937 }
.dark .sc-user-result-item .info .name { color:#f1f5f9 }
.sc-user-result-item .info .uname { font-size:11px; color:#6b7280 }
.dark .sc-user-result-item .info .uname { color:#94a3b8 }
.sc-qty-preset { padding:6px 14px; border-radius:999px; border:1.5px solid #e2e8f0; background:#fff; font-size:12px; font-weight:700; color:#475569; cursor:pointer; transition:all .15s; font-family:'Plus Jakarta Sans',sans-serif }
.sc-qty-preset:hover { border-color:#8b5cf6; color:#7c3aed; background:#f5f3ff }
.dark .sc-qty-preset { background:#1e293b; border-color:#334155; color:#94a3b8 }
.dark .sc-qty-preset:hover { border-color:#a78bfa; color:#c4b5fd; background:rgba(139,92,246,.1) }
@media(max-width:480px) {
  .gp-modal-box, .gp-modal-box--premium { max-width:none; max-height:none; border-radius:0; width:100vw; height:100dvh; }
}
</style>

<input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
<div class="bal-v3-page">
    <!-- ═══ User Hero ═══ -->
    <div class="bal-v3-hero">
        <div class="bal-v3-hero-content">
            <div class="bal-v3-avatar">
                <img src="<?php echo $avatarPath; ?>" alt="" onerror="this.src='assets/avatars/default.png'">
            </div>
            <div style="flex:1">
                <div style="font-size:22px;font-weight:800;color:#fff;letter-spacing:-.3px"><?php echo htmlspecialchars($userName); ?></div>
                <div style="display:inline-flex;align-items:center;gap:5px;margin-top:5px;padding:3px 14px;border-radius:999px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;background:rgba(255,255,255,.12);color:rgba(255,255,255,.85);border:1px solid rgba(255,255,255,.08)">
                    <i class="fas <?php echo $userRole === 'agent' ? 'fa-crown' : 'fa-gamepad'; ?>" style="font-size:9px"></i>
                    <?php echo $userRole === 'agent' ? 'Agent' : ($userRole === 'user' ? 'Gamer' : ucfirst($userRole)); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══ Stats ═══ -->
    <div class="bal-v3-stats">
        <div class="bal-v3-stat green">
            <div class="bal-v3-stat-icon"><i class="fas fa-wallet" style="font-size:24px;color:#059669"></i></div>
            <div class="bal-v3-stat-label">Balance</div>
            <div class="bal-v3-stat-value green">৳<?php echo number_format($balance, 0); ?></div>
        </div>
        <div class="bal-v3-stat bronze">
            <div class="bal-v3-stat-icon"><img src="assets/images/coin-bronze.svg" alt="Bronze"></div>
            <div class="bal-v3-stat-label">Bronze</div>
            <div class="bal-v3-stat-value bronze"><?php echo number_format($bronzeCoins, 0); ?></div>
        </div>
        <div class="bal-v3-stat silver">
            <div class="bal-v3-stat-icon"><img src="assets/images/coin-silver.svg" alt="Silver"></div>
            <div class="bal-v3-stat-label">Silver</div>
            <div class="bal-v3-stat-value silver"><?php echo number_format($silverCoins, 0); ?></div>
        </div>
        <div class="bal-v3-stat gold">
            <div class="bal-v3-stat-icon"><img src="assets/images/coin-gold.svg" alt="Gold"></div>
            <div class="bal-v3-stat-label">Gold</div>
            <div class="bal-v3-stat-value gold"><?php echo number_format($goldCoins, 0); ?></div>
        </div>
    </div>

    <!-- ═══ Actions ═══ -->
    <div class="bal-v3-actions">
        <button class="bal-v3-btn green" id="openAddMoneyBtn">
            <i class="fas fa-plus-circle"></i> Add Money
        </button>
        <button class="bal-v3-btn purple" id="openSendCoinsBtn">
            <i class="fas fa-arrow-right-arrow-left"></i> Send Coins
        </button>
    </div>

    <!-- ═══ Combined History ═══ -->
    <div class="bal-v3-history">
        <div class="bal-v3-history-header">
            <div class="title"><i class="fas fa-clock-rotate" style="color:#9ca3af"></i> Transaction History</div>
            <span style="font-size:11px;color:#9ca3af"><?php echo count($history); ?> total</span>
        </div>
        <div class="bal-v3-history-scroll" id="historyScroll">
        <?php if (empty($history)): ?>
        <div style="padding:36px 18px;text-align:center;color:#9ca3af;font-size:14px">No transactions yet.<br><span style="font-size:12px">Tap <strong>Add Money</strong> to get started!</span></div>
        <?php else: foreach ($history as $h): 
            $isPayment = $h['src'] === 'payment';
            if ($isPayment) {
                $iconClass = $h['purpose'] === 'agent_activation' ? 'agent' : 'payment';
                $iconHtml = $h['purpose'] === 'agent_activation' ? '<i class="fas fa-crown"></i>' : '<i class="fas fa-credit-card"></i>';
                $amtClass = 'neutral';
                $badgeHtml = '<span class="badge ' . $h['status'] . '">' . ($h['status'] === 'completed' ? 'Completed' : ucfirst($h['status'])) . '</span>';
            } else {
                $iconClass = $h['ref1'] === 'transfer_sent' ? 'sent' : ($h['ref1'] === 'purchase' ? 'purchase' : ((strpos($h['ref1'] ?? '', 'conversion') !== false) ? 'conversion' : 'received'));
                if ($h['ref1'] === 'transfer_sent') $iconHtml = '<i class="fas fa-arrow-up"></i>';
                elseif ($h['ref1'] === 'purchase') $iconHtml = '<i class="fas fa-coins"></i>';
                elseif (strpos($h['ref1'] ?? '', 'conversion') !== false) $iconHtml = '<i class="fas fa-arrows-rotate"></i>';
                else $iconHtml = '<i class="fas fa-arrow-down"></i>';
                $amtClass = $h['ref1'] === 'transfer_sent' ? 'neg' : 'pos';
                $badgeHtml = '';
            }
            // Prepare detail JSON
            $detailData = [
                'type' => $isPayment ? 'payment' : 'coin',
                'id' => $h['id'],
                'amount' => (float)$h['amount'],
                'status' => $h['status'],
                'date' => date('M j, Y \a\t g:ia', strtotime($h['created_at'])),
                'ref1' => $h['ref1'],
                'ref2' => $h['ref2'],
                'purpose' => $h['purpose'],
                'other_user' => $h['other_username'] ?? null
            ];
        ?>
        <div class="bal-v3-history-item" onclick='showTxDetail(<?php echo json_encode($detailData); ?>)'>
            <div class="bal-v3-history-icon-wrap <?php echo $iconClass; ?>">
                <?php echo $iconHtml; ?>
            </div>
            <div class="bal-v3-history-info">
                <div class="main">
                    <?php if ($isPayment): 
                        echo $h['purpose'] === 'agent_activation' ? 'Agent Activation' : strtoupper(htmlspecialchars($h['ref1']));
                    else:
                        echo $h['ref1'] === 'transfer_sent' ? 'Sent to ' . htmlspecialchars($h['other_username']??'User') : ($h['ref1'] === 'purchase' ? 'Coin Purchase' : 'Received from ' . htmlspecialchars($h['other_username']??'User'));
                    endif; ?>
                </div>
                <div class="sub">
                    <?php if ($isPayment): 
                        echo htmlspecialchars($h['ref2']); ?> · <?php endif;
                    echo date('M j, g:ia', strtotime($h['created_at'])); ?>
                    <?php if ($badgeHtml): ?> · <?php echo $badgeHtml; ?><?php endif; ?>
                </div>
            </div>
            <div class="bal-v3-history-amount <?php echo $amtClass; ?>">
                <?php if ($isPayment): ?>৳<?php echo number_format((float)$h['amount'], 0);
                else: echo ($h['ref1']==='transfer_sent' ? '-' : '+') . number_format((int)$h['amount']); endif; ?>
            </div>
        </div>
        <?php endforeach; endif; ?>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════════ -->
<!-- ═══ TRANSACTION DETAIL MODAL ═══ -->
<!-- ════════════════════════════════════════════════════════════ -->
<div class="gp-modal-overlay" id="txDetailOverlay">
    <div class="gp-modal-box gp-modal-box--premium" style="max-width:380px">
        <div class="gp-modal-header gp-modal-header--premium">
            <div class="gp-modal-header-title"><i class="fas fa-receipt" style="color:#64748b"></i> Transaction Details</div>
            <button onclick="closeTxDetail()" class="gp-modal-close-btn"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="gp-modal-body gp-modal-body--premium" id="txDetailBody" style="padding:0">
            <!-- Dynamic Content -->
        </div>
    </div>
</div>


<!-- ════════════════════════════════════════════════════════════ -->
<!-- ═══ ADD MONEY MODAL (Premium Bangla Design) ═══ -->
<!-- ════════════════════════════════════════════════════════════ -->
<div class="gp-modal-overlay" id="addMoneyOverlay">
    <div class="gp-modal-box gp-modal-box--premium">
        <div class="gp-modal-header gp-modal-header--premium">
            <div class="gp-modal-header-title">
                <i class="fas fa-plus-circle" style="color:#059669"></i> Add Money
            </div>
            <button onclick="closeAddMoney()" class="gp-modal-close-btn"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="gp-modal-body gp-modal-body--premium">
            <!-- Info note -->
            <div class="ba-pay-note ba-pay-note--addmoney">
                <i class="fas fa-info-circle"></i> সর্বনিম্ন ১০ টাকা থেকে সর্বোচ্চ ৩২০০ টাকা অ্যাড করতে পারবেন
            </div>

            <!-- Method (images only, no labels) -->
            <div class="ba-method-grid-addmoney">
                <?php $methods = ['bkash'=>['label'=>'bKash','color'=>'#E2136E'],'nagad'=>['label'=>'Nagad','color'=>'#E8522E'],'rocket'=>['label'=>'Rocket','color'=>'#CC0000']]; ?>
                <?php foreach ($methods as $mk => $mv): ?>
                <div class="ba-method-card ba-method-card--addmoney active" data-method="<?php echo $mk; ?>">
                    <img src="assets/images/payment-icon/<?php echo $mk; ?>-logo-mobile-banking.png" alt="<?php echo $mv['label']; ?>" onerror="this.style.display='none'">
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Amount -->
            <div style="margin-bottom:14px">
                <div style="position:relative">
                    <span style="position:absolute;left:14px;top:50%;transform:translateY(-50%);font-size:18px;font-weight:900;color:var(--p2p-text)">৳</span>
                    <input type="number" id="modalAmount" min="10" max="3200" step="1" placeholder="পরিমাণ লিখুন" style="width:100%;padding:14px 14px 14px 40px;border-radius:12px;border:2px solid var(--p2p-border);background:var(--p2p-card);font-size:22px;font-weight:700;outline:none;box-sizing:border-box;text-align:center;transition:border-color .2s,box-shadow .2s;color:var(--p2p-text)">
                </div>
                <div style="display:flex;gap:6px;margin-top:8px;flex-wrap:wrap;justify-content:center">
                    <?php foreach ([10,20,50,100,200,500] as $p): ?>
                    <button type="button" class="gp-preset-btn gp-preset-btn--sm" data-val="<?php echo $p; ?>">৳<?php echo $p; ?></button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Merchant + Instructions (premium) -->
            <div id="modalMerchantBox" class="ba-merchant-box--premium" style="display:none"></div>

            <!-- TXID + Phone -->
            <div style="display:grid;gap:12px;margin-bottom:16px">
                <div>
                    <label class="ba-input-label">📱 আপনার মোবাইল নাম্বার (Sent From)</label>
                    <div style="position:relative">
                        <span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:14px;z-index:1"><i class="fas fa-mobile-screen"></i></span>
                        <input type="tel" id="modalPhone" placeholder="01XXXXXXXXX" pattern="01[3-9]\d{8}" class="ba-input ba-input--premium" style="padding-left:36px">
                    </div>
                </div>
                <div>
                    <label class="ba-input-label">🔗 ট্রান্সজেকশন আইডি</label>
                    <div style="position:relative">
                        <span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:14px;z-index:1"><i class="fas fa-hashtag"></i></span>
                        <input type="text" id="modalTxid" placeholder="ট্রান্সজেকশন আইডি দিন" style="text-transform:uppercase;padding-left:36px" class="ba-input ba-input--premium">
                    </div>
                </div>
            </div>

            <button id="modalSubmitBtn" class="ba-submit-btn">
                <i class="fas fa-check-circle"></i> VERIFY
            </button>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════════ -->
<!-- ═══ SUCCESS / FAIL OVERLAY (Premium) ═══ -->
<!-- ════════════════════════════════════════════════════════════ -->
<div class="gp-success-overlay" id="resultOverlay">
    <div class="gp-result-box" id="resultBox">
        <div class="gp-result-icon-wrap" id="resultIconWrap">
            <svg class="gp-result-circle" viewBox="0 0 80 80">
                <circle class="gp-result-circle-bg" cx="40" cy="40" r="36"/>
                <circle class="gp-result-circle-fill" id="resultCircleFill" cx="40" cy="40" r="36" stroke-dasharray="226" stroke-dashoffset="226"/>
            </svg>
            <div class="gp-result-icon" id="resultIcon"><i class="fas fa-check"></i></div>
        </div>
        <div class="gp-result-title" id="resultTitle">Success!</div>
        <div class="gp-result-sub" id="resultSub">Your payment request has been submitted</div>
        <button class="gp-result-dismiss" id="resultDismissBtn">Got it</button>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════════ -->
<!-- ═══ SEND COINS MODAL ═══ -->
<!-- ════════════════════════════════════════════════════════════ -->
<div class="gp-modal-overlay" id="sendCoinsOverlay">
    <div class="gp-modal-box gp-modal-box--premium">
        <div class="gp-modal-header gp-modal-header--premium">
            <div class="gp-modal-header-title">
                <i class="fas fa-arrow-right-arrow-left" style="color:#8b5cf6"></i> Send Coins
            </div>
            <button onclick="closeSendCoins()" class="gp-modal-close-btn"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="gp-modal-body gp-modal-body--premium" id="sendCoinsBody">
            <?php if ($canSend): ?>
            <form id="scForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <div class="sc-balance-card">
                    <div>
                        <div class="sc-balance-label">Your Bronze Balance</div>
                        <div class="sc-balance-value"><img src="assets/images/coin-bronze.svg" alt=""> <?php echo number_format($bronzeCoins); ?></div>
                    </div>
                    <div style="font-size:11px;font-weight:700;color:#8b5cf6;background:rgba(139,92,246,.12);border-radius:999px;padding:5px 12px">1x / 24h</div>
                </div>
                <div class="sc-field-wrap">
                    <label><i class="fas fa-user" style="color:#8b5cf6;margin-right:4px"></i> Receiver</label>
                    <input type="text" id="scReceiver" class="sc-input" placeholder="Search by username or email..." autocomplete="off" required>
                    <div class="sc-user-results" id="scUserResults"></div>
                </div>
                <div class="sc-field-wrap">
                    <label><i class="fas fa-coins" style="color:#8b5cf6;margin-right:4px"></i> Amount (bronze coins)</label>
                    <div style="display:flex;gap:8px">
                        <button type="button" class="sc-qty-preset" data-val="1">1</button>
                        <button type="button" class="sc-qty-preset" data-val="5">5</button>
                        <button type="button" class="sc-qty-preset" data-val="10">10</button>
                        <button type="button" class="sc-qty-preset" data-val="25">25</button>
                        <button type="button" class="sc-qty-preset" data-val="50">50</button>
                    </div>
                    <input type="number" id="scAmount" class="sc-input" placeholder="0" min="1" style="margin-top:8px" required>
                </div>
                <div style="background:var(--p2p-bg);border-radius:12px;padding:10px 14px;margin-bottom:16px;display:flex;align-items:center;gap:8px;font-size:12px;color:var(--p2p-muted)">
                    <i class="fas fa-info-circle" style="color:#8b5cf6"></i> No fee. 24h cooldown applies. You can send once per day.
                </div>
                <button type="submit" class="ba-submit-btn" id="scSendBtn" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed);box-shadow:0 4px 16px rgba(124,58,237,.25)">
                    <i class="fas fa-paper-plane"></i> Send Coins
                </button>
                <div class="gp-feedback hidden" id="scFeedback" style="margin-top:10px"></div>
            </form>
            <?php else: ?>
            <div style="text-align:center;padding:2rem 1rem">
                <div style="width:72px;height:72px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:32px;color:#fff;background:linear-gradient(135deg,#f59e0b,#d97706);box-shadow:0 8px 24px rgba(245,158,11,.3)">
                    <i class="fas fa-clock"></i>
                </div>
                <div style="font-size:18px;font-weight:800;color:var(--p2p-text);margin-bottom:6px">Cooldown Active</div>
                <div style="font-size:13px;color:var(--p2p-muted);line-height:1.5;margin-bottom:4px">You can send bronze coins again after 24h from your last transfer.</div>
                <div style="font-size:12px;font-weight:700;color:#d97706;background:#fef3c7;border-radius:8px;padding:8px 12px;display:inline-block">Last sent: <?php echo $lastSend ? date('M j, g:ia', strtotime($lastSend)) : 'recently'; ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function(){
    var pmData = <?php echo json_encode($pmData); ?>;
    var csrfToken = document.querySelector('[name=csrf_token]')?.value || '';

    function gpModalBody(open) { document.body.classList.toggle('gp-modal-open', open); document.body.style.overflow = open ? 'hidden' : ''; }

    // ═══════════════════════════════════════
    // TRANSACTION DETAIL MODAL
    // ═══════════════════════════════════════
    var txOverlay = document.getElementById('txDetailOverlay');
    var txBody = document.getElementById('txDetailBody');

    window.showTxDetail = function(d) {
        var isCoin = d.type === 'coin';
        var isNeg = d.ref1 === 'transfer_sent';
        var amtClass = isCoin ? (isNeg ? 'neg' : 'pos') : 'neutral';
        var symbol = isCoin ? '<img src="assets/images/coin-bronze.svg" alt="">' : '৳';
        var statusLabel = d.status === 'completed' ? 'Completed' : (isCoin ? (isNeg ? 'Sent' : 'Received') : d.status.toUpperCase());
        
        var html = '<div class="tx-detail-hero">' +
            '<div class="tx-detail-amount ' + amtClass + '">' + (isCoin ? (isNeg ? '-' : '+') : '') + symbol + ' ' + d.amount.toLocaleString() + '</div>' +
            '<div class="tx-detail-status ' + d.status + ' ' + amtClass + '">' + statusLabel + '</div>' +
            '</div>' +
            '<div class="tx-detail-grid">' +
            '<div class="tx-detail-row"><span class="tx-detail-lbl">Transaction ID</span><span class="tx-detail-val">#' + d.id + '</span></div>' +
            '<div class="tx-detail-row"><span class="tx-detail-lbl">Date</span><span class="tx-detail-val">' + d.date + '</span></div>';
        
        if (isCoin) {
            var actionType = d.ref1 === 'transfer_sent' ? 'Transfer Sent' : (d.ref1 === 'purchase' ? 'Coin Purchase' : 'Transfer Received');
            html += '<div class="tx-detail-row"><span class="tx-detail-lbl">Type</span><span class="tx-detail-val">' + actionType + '</span></div>';
            if (d.other_user) {
                html += '<div class="tx-detail-row"><span class="tx-detail-lbl">' + (isNeg ? 'Recipient' : 'Sender') + '</span><span class="tx-detail-val">@' + d.other_user + '</span></div>';
            }
            if (d.ref2) {
                html += '<div class="tx-detail-row"><span class="tx-detail-lbl">Note</span><span class="tx-detail-val">' + d.ref2 + '</span></div>';
            }
        } else {
            html += '<div class="tx-detail-row"><span class="tx-detail-lbl">Method</span><span class="tx-detail-val">' + d.ref1.toUpperCase() + '</span></div>' +
                '<div class="tx-detail-row"><span class="tx-detail-lbl">Transaction Ref</span><span class="tx-detail-val">' + d.ref2 + ' <i class="fas fa-copy tx-detail-copy" onclick="copyValue(\'' + d.ref2 + '\', this)"></i></span></div>';
            if (d.purpose === 'agent_activation') {
                html += '<div class="tx-detail-row"><span class="tx-detail-lbl">Purpose</span><span class="tx-detail-val">Agent Activation</span></div>';
            }
        }
        
        html += '</div>';
        txBody.innerHTML = html;
        txOverlay.style.display = 'flex';
        gpModalBody(true);
    };

    window.closeTxDetail = function() { txOverlay.style.display = 'none'; gpModalBody(false); };
    txOverlay.addEventListener('click', function(e) { if (e.target === txOverlay) closeTxDetail(); });

    window.copyValue = function(val, el) {
        navigator.clipboard.writeText(val).then(function(){
            var orig = el.className;
            el.className = 'fas fa-check tx-detail-copy';
            el.style.color = '#059669';
            setTimeout(function(){ el.className = orig; el.style.color = ''; }, 1500);
        });
    };

    // ═══════════════════════════════════════
    // ADD MONEY MODAL
    // ═══════════════════════════════════════
    var addOverlay = document.getElementById('addMoneyOverlay');
    var methodCards = addOverlay.querySelectorAll('.ba-method-card--addmoney');
    var modalAmount = document.getElementById('modalAmount');
    var modalPhone = document.getElementById('modalPhone');
    var modalTxid = document.getElementById('modalTxid');
    var modalSubmit = document.getElementById('modalSubmitBtn');
    var modalMerchant = document.getElementById('modalMerchantBox');
    var currentMethod = 'bkash';

    window.closeAddMoney = function() { addOverlay.style.display = 'none'; gpModalBody(false); };

    document.getElementById('openAddMoneyBtn').addEventListener('click', function() {
        addOverlay.style.display = 'flex';
        gpModalBody(true);
        updateMerchant();
    });
    addOverlay.addEventListener('click', function(e) { if (e.target === addOverlay) closeAddMoney(); });

    function getMerchantHTML(method, amount) {
        var d = pmData[method];
        if (!d) return '';
        var label = d.instruction === 'cashout' ? 'ক্যাশ আউট' : 'সেন্ড মানি';
        var colors = {bkash:'#E2136E', nagad:'#E8522E', rocket:'#CC0000'};
        var names = {bkash:'bKash', nagad:'Nagad', rocket:'Rocket'};
        var dials = {bkash:'*247#', nagad:'*167#', rocket:'*322#'};
        var c = colors[method] || '#6b7280';
        var n = names[method] || method.toUpperCase();
        var mNum = d.number || '01888780877';
        return '<div class="ba-merchant-header"><img src="assets/images/payment-icon/'+method+'-logo-mobile-banking.png" alt="" onerror="this.style.display=\'none\'"><span style="color:'+c+'">'+n+'</span></div>' +
            '<div class="ba-instr-step">১. <strong>'+dials[method]+'</strong> ডায়াল করুন অথবা '+n+' অ্যাপ খুলুন।</div>' +
            '<div class="ba-instr-step">২. <strong>"'+label+'"</strong> অপশন সিলেক্ট করুন।</div>' +
            '<div class="ba-instr-step">৩. প্রাপক নম্বর লিখুনঃ <strong class="ba-merchant-num" style="color:'+c+'">'+mNum+'</strong> <button onclick="copyMerchant(\''+method+'\')" class="ba-copy-btn"><i class="fas fa-copy"></i> কপি</button></div>' +
            '<div class="ba-instr-step">৪. টাকার পরিমাণ লিখুনঃ <strong>৳'+amount+'</strong></div>' +
            '<div class="ba-instr-step">৫. আপনার পিন দিন এবং কনফার্ম বাটনে ক্লিক করুন।</div>' +
            '<div class="ba-instr-step">৬. কনফার্মেশন মেসেজ থেকে <strong>Transaction ID</strong> কপি করে নিচে দিন।</div>' +
            '<div class="ba-instr-footer">এখন নিচের বক্সে TXID দিন এবং <strong style="color:#059669">VERIFY</strong> বাটনে ক্লিক করুন। ✅</div>';
    }

    window.copyMerchant = function(m) {
        var d = pmData[m];
        if (!d) return;
        navigator.clipboard.writeText(d.number).then(function(){
            modalMerchant.querySelectorAll('.ba-copy-btn').forEach(function(b){ var t=b.innerHTML; b.innerHTML='<i class="fas fa-check"></i> কপি হয়েছে!'; setTimeout(function(){b.innerHTML=t;},1500); });
        });
    };

    function updateMerchant() {
        var amt = parseFloat(modalAmount.value) || 0;
        if (amt < 10) amt = 10; if (amt > 3200) amt = 3200;
        modalMerchant.innerHTML = getMerchantHTML(currentMethod, amt);
        modalMerchant.style.display = 'block';
    }

    methodCards.forEach(function(card) {
        card.addEventListener('click', function() {
            methodCards.forEach(function(c) {
                c.classList.remove('active');
            });
            card.classList.add('active');
            var m = card.getAttribute('data-method');
            currentMethod = m;
            updateMerchant();
        });
    });

    addOverlay.querySelectorAll('.gp-preset-btn').forEach(function(b) {
        b.addEventListener('click', function() { modalAmount.value = this.getAttribute('data-val'); updateMerchant(); });
    });
    modalAmount.addEventListener('input', updateMerchant);

    modalSubmit.addEventListener('click', function() {
        var method = currentMethod;
        var phone = modalPhone.value.trim();
        var txid = modalTxid.value.trim().toUpperCase();
        var amount = parseFloat(modalAmount.value) || 0;

        if (amount < 10) { showModalMsg('সর্বনিম্ন ১০ টাকা', 'error'); return; }
        if (amount > 3200) { showModalMsg('সর্বোচ্চ ৩২০০ টাকা', 'error'); return; }
        if (!/^01[3-9]\d{8}$/.test(phone)) { showModalMsg('একটি বৈধ মোবাইল নাম্বার দিন', 'error'); return; }
        if (txid.length < 4) { showModalMsg('একটি বৈধ Transaction ID দিন', 'error'); return; }

        modalSubmit.disabled = true;
        modalSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
        hideModalMsg();

        fetch('handlers/tournament_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({
                action: 'submit_payment',
                method: method,
                sender_phone: phone,
                transaction_id: txid,
                amount: amount,
                csrf_token: csrfToken
            })
        })
        .then(function(r){ return r.json(); })
        .then(function(res){
            closeAddMoney();
            if (res.success) {
                showResult(true, 'Payment Submitted!', 'Admin will verify and approve your ৳' + amount.toFixed(0) + ' payment shortly.');
            } else {
                showResult(false, 'Submission Failed', res.message || 'Please try again.');
            }
        })
        .catch(function(){
            closeAddMoney();
            showResult(false, 'Network Error', 'Could not reach server. Please try again.');
        })
        .finally(function(){
            modalSubmit.disabled = false;
            modalSubmit.innerHTML = '<i class="fas fa-check-circle"></i> VERIFY';
        });
    });

    function showModalMsg(text, type) {
        var el = document.getElementById('modalMsg') || (function(){
            var d = document.createElement('div'); d.id = 'modalMsg';
            d.style.cssText = 'padding:10px 14px;border-radius:8px;margin-bottom:12px;font-size:13px;font-weight:500;display:block';
            modalSubmit.parentNode.insertBefore(d, modalSubmit);
            return d;
        })();
        el.style.background = type === 'success' ? '#f0fdf4' : '#fef2f2';
        el.style.border = type === 'success' ? '1px solid #bbf7d0' : '1px solid #fecaca';
        el.style.color = type === 'success' ? '#166534' : '#991b1b';
        el.innerHTML = text;
        el.style.display = 'block';
    }
    function hideModalMsg() {
        var el = document.getElementById('modalMsg');
        if (el) el.style.display = 'none';
    }

    // ═══════════════════════════════════════
    // SUCCESS / FAIL RESULT (Premium)
    // ═══════════════════════════════════════
    var resultOverlay = document.getElementById('resultOverlay');
    var resultBox = document.getElementById('resultBox');
    var resultIcon = document.getElementById('resultIcon');
    var resultTitle = document.getElementById('resultTitle');
    var resultSub = document.getElementById('resultSub');
    var resultCircleFill = document.getElementById('resultCircleFill');
    var resultDismiss = document.getElementById('resultDismissBtn');
    var resultTimer = null;

    window.showResult = function(success, title, sub) {
        if (resultTimer) { clearTimeout(resultTimer); resultTimer = null; }
        resultOverlay.style.display = 'flex'; gpModalBody(true);
        resultBox.classList.remove('fail');
        resultCircleFill.classList.remove('success', 'fail');
        if (success) {
            resultBox.classList.remove('fail');
            resultCircleFill.classList.add('success');
            resultIcon.innerHTML = '<i class="fas fa-check"></i>';
        } else {
            resultBox.classList.add('fail');
            resultCircleFill.classList.add('fail');
            resultIcon.innerHTML = '<i class="fas fa-xmark"></i>';
        }
        resultTitle.textContent = title;
        resultSub.textContent = sub;
        // Trigger circle animation
        requestAnimationFrame(function() {
            resultCircleFill.style.strokeDashoffset = '0';
        });
        if (success) {
            resultTimer = setTimeout(function() {
                resultOverlay.style.display = 'none'; gpModalBody(false);
                location.reload();
            }, 3000);
        } else {
            resultTimer = setTimeout(function() {
                resultOverlay.style.display = 'none'; gpModalBody(false);
            }, 4000);
        }
    };

    resultDismiss.addEventListener('click', function() {
        if (resultTimer) { clearTimeout(resultTimer); resultTimer = null; }
        resultOverlay.style.display = 'none'; gpModalBody(false);
    });
    resultOverlay.addEventListener('click', function(e) { if (e.target === resultOverlay) { if (resultTimer) clearTimeout(resultTimer); resultOverlay.style.display = 'none'; gpModalBody(false); } });

    // ═══════════════════════════════════════
    // SEND COINS MODAL (Balance page)
    // ═══════════════════════════════════════
    var scOverlay = document.getElementById('sendCoinsOverlay');
    var scForm = document.getElementById('scForm');
    var scReceiver = document.getElementById('scReceiver');
    var scUserResults = document.getElementById('scUserResults');
    var scSearchTimer = null;
    var scSelectedUser = null;

    window.closeSendCoins = function() { scOverlay.style.display = 'none'; gpModalBody(false); scUserResults.style.display = 'none'; scSelectedUser = null; };

    document.getElementById('openSendCoinsBtn').addEventListener('click', function() {
        scOverlay.style.display = 'flex';
        gpModalBody(true);
        if (scReceiver) { scReceiver.value = ''; scReceiver.focus(); }
        if (scUserResults) scUserResults.style.display = 'none';
        scSelectedUser = null;
    });
    scOverlay.addEventListener('click', function(e) { if (e.target === scOverlay) closeSendCoins(); });

    // User autocomplete
    if (scReceiver) {
        scReceiver.addEventListener('input', function() {
            var q = this.value.trim();
            if (scSearchTimer) clearTimeout(scSearchTimer);
            if (q.length < 1) { scUserResults.style.display = 'none'; scSelectedUser = null; return; }
            scSearchTimer = setTimeout(function() {
                fetch('handlers/tournament_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ action: 'search_users', query: q, csrf_token: csrfToken })
                })
                .then(function(r){ return r.json(); })
                .then(function(res){
                    if (!res.users || res.users.length === 0) { scUserResults.style.display = 'none'; return; }
                    var html = '';
                    res.users.forEach(function(u){
                        var initial = (u.full_name || u.username).charAt(0).toUpperCase();
                        html += '<div class="sc-user-result-item" data-id="' + u.id + '" data-username="' + u.username + '">' +
                            '<div class="avatar">' + initial + '</div>' +
                            '<div class="info"><div class="name">' + (u.full_name || u.username) + '</div><div class="uname">@' + u.username + '</div></div>' +
                            '</div>';
                    });
                    scUserResults.innerHTML = html;
                    scUserResults.style.display = 'block';
                    scUserResults.querySelectorAll('.sc-user-result-item').forEach(function(item){
                        item.addEventListener('click', function(){
                            scReceiver.value = this.getAttribute('data-username');
                            scSelectedUser = this.getAttribute('data-username');
                            scUserResults.style.display = 'none';
                        });
                    });
                });
            }, 250);
        });
        scReceiver.addEventListener('blur', function() {
            setTimeout(function(){ scUserResults.style.display = 'none'; }, 200);
        });
        scReceiver.addEventListener('focus', function() {
            if (scUserResults.children.length > 0) scUserResults.style.display = 'block';
        });
    }

    // Quantity presets
    document.querySelectorAll('.sc-qty-preset').forEach(function(btn){
        btn.addEventListener('click', function(){
            document.getElementById('scAmount').value = this.getAttribute('data-val');
        });
    });

    if (scForm) {
        scForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var rec = scReceiver.value.trim();
            var amt = parseInt(document.getElementById('scAmount').value) || 0;
            var btn = document.getElementById('scSendBtn');
            var fb = document.getElementById('scFeedback');
            if (!rec) { fb.className = 'gp-feedback error'; fb.textContent = 'Enter a receiver.'; fb.classList.remove('hidden'); return; }
            if (amt < 1) { fb.className = 'gp-feedback error'; fb.textContent = 'Minimum 1 coin.'; fb.classList.remove('hidden'); return; }
            btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...'; fb.classList.add('hidden');
            fetch('handlers/tournament_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ action: 'transfer_coins', receiver: rec, amount: amt, csrf_token: csrfToken })
            })
            .then(function(r){ return r.json(); })
            .then(function(res){
                closeSendCoins();
                if (res.success) {
                    showResult(true, 'Coins Sent!', amt + ' bronze coins sent to ' + rec + '.');
                } else {
                    showResult(false, 'Send Failed', res.message || 'Please try again.');
                }
            })
            .catch(function(){
                closeSendCoins();
                showResult(false, 'Network Error', 'Could not reach server.');
            });
        });
    }

    // ═══════════════════════════════════════
    // HISTORY LAZY LOAD (scroll)
    // ═══════════════════════════════════════
    var historyScroll = document.getElementById('historyScroll');
    if (historyScroll && historyScroll.scrollHeight > historyScroll.clientHeight) {
        // Lazy load more on scroll if items are truncated
        var historyLoading = false;
        var historyPage = 1;
        historyScroll.addEventListener('scroll', function() {
            if (historyLoading) return;
            if (this.scrollTop + this.clientHeight >= this.scrollHeight - 40) {
                historyLoading = true;
                // Could fetch more via AJAX; for now just a visual indicator
                historyLoading = false;
            }
        });
    }
})();
</script>
