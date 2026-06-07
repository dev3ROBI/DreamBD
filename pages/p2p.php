<?php
$db = Database::getInstance()->getConnection();
$viewerId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['role'] ?? 'user';
$userName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? '';
$security = new Security();
$csrfToken = $security->generateCSRFToken();

$bronzeCoins = 0; $silverCoins = 0; $goldCoins = 0;
$sellOffers = []; $buyOffers = []; $myTrades = []; $myOffers = [];
$paymentSettings = [];

if ($viewerId) {
    $stmt = $db->prepare("SELECT bronze_coins, silver_coins, gold_coins, balance, avatar, registered_at FROM users WHERE id = ?");
    $stmt->execute([$viewerId]);
    $u = $stmt->fetch();
    $bronzeCoins = (int)($u['bronze_coins'] ?? 0);
    $silverCoins = (int)($u['silver_coins'] ?? 0);
    $goldCoins = (int)($u['gold_coins'] ?? 0);
    $balance = (float)($u['balance'] ?? 0);
    $userAvatar = $u['avatar'] ?? 'default.png';
    $userJoined = $u['registered_at'] ?? null;
}
$stmt = $db->prepare("SELECT o.*, u.username, u.full_name, u.avatar, u.registered_at FROM p2p_offers o JOIN users u ON u.id = o.agent_id WHERE o.type = 'sell' AND o.status = 'active' AND o.remaining > 0 ORDER BY o.price_per_coin ASC LIMIT 50");
$stmt->execute(); $sellOffers = $stmt->fetchAll();
$stmt = $db->prepare("SELECT o.*, u.username, u.full_name, u.avatar, u.registered_at FROM p2p_offers o JOIN users u ON u.id = o.agent_id WHERE o.type = 'buy' AND o.status = 'active' AND o.remaining > 0 ORDER BY o.price_per_coin DESC LIMIT 50");
$stmt->execute(); $buyOffers = $stmt->fetchAll();

if ($viewerId) {
    $stmt = $db->prepare("SELECT * FROM p2p_offers WHERE agent_id = ? ORDER BY created_at DESC LIMIT 20");
    $stmt->execute([$viewerId]); $myOffers = $stmt->fetchAll();
    $stmt = $db->prepare("SELECT t.*, o.type AS offer_type, ob.username AS buyer_name, os.username AS seller_name FROM p2p_trades t JOIN p2p_offers o ON o.id = t.offer_id LEFT JOIN users ob ON ob.id = t.buyer_id LEFT JOIN users os ON os.id = t.seller_id WHERE t.buyer_id = ? OR t.seller_id = ? ORDER BY t.created_at DESC LIMIT 30");
    $stmt->execute([$viewerId, $viewerId]); $myTrades = $stmt->fetchAll();
    $stmt = $db->prepare("SELECT * FROM p2p_payment_settings WHERE user_id = ?");
    $stmt->execute([$viewerId]); $paymentSettings = $stmt->fetchAll();
}

// Real trade count per merchant for offer cards
$tradeCountMap = [];
$ratingMap = [];
if (!empty($sellOffers) || !empty($buyOffers)) {
    $agentIds = array_unique(array_merge(
        array_column($sellOffers, 'agent_id'),
        array_column($buyOffers, 'agent_id')
    ));
    if ($agentIds) {
        $phs = implode(',', array_fill(0, count($agentIds), '?'));
        $tcStmt = $db->prepare("SELECT o.agent_id, COUNT(t.id) as cnt FROM p2p_offers o JOIN p2p_trades t ON t.offer_id = o.id WHERE o.agent_id IN ($phs) AND t.status = 'completed' GROUP BY o.agent_id");
        $tcStmt->execute(array_values($agentIds));
        foreach ($tcStmt->fetchAll() as $row) {
            $tradeCountMap[(int)$row['agent_id']] = (int)$row['cnt'];
        }
        $ratStmt = $db->prepare("SELECT merchant_id, ROUND(AVG(rating),1) as avg_rating, COUNT(*) as total_reviews FROM p2p_reviews WHERE merchant_id IN ($phs) GROUP BY merchant_id");
        $ratStmt->execute(array_values($agentIds));
        foreach ($ratStmt->fetchAll() as $row) {
            $ratingMap[(int)$row['merchant_id']] = ['avg' => (float)$row['avg_rating'], 'count' => (int)$row['total_reviews']];
        }
    }
}

$coinEmoji = ['bronze'=>'<img src="assets/images/coin-bronze.svg" class="p2p-coin-svg" alt="">','silver'=>'<img src="assets/images/coin-silver.svg" class="p2p-coin-svg" alt="">','gold'=>'<img src="assets/images/coin-gold.svg" class="p2p-coin-svg" alt="">'];
$coinValues = ['bronze'=>25, 'silver'=>50, 'gold'=>100];
$coinLabels = ['bronze'=>'Bronze','silver'=>'Silver','gold'=>'Gold'];
$statusBadge = ['pending'=>'yellow','paid'=>'blue','completed'=>'green','cancelled'=>'red','disputed'=>'orange'];
?>
<style>
:root { --p2p-bg:#f5f7fa; --p2p-card:#fff; --p2p-border:#e2e8f0; --p2p-text:#1f2937; --p2p-muted:#6b7280; --p2p-accent:#8b5cf6; --p2p-green:#059669; --p2p-red:#dc2626; --p2p-yellow:#d97706 }
.dark { --p2p-bg:#0f172a; --p2p-card:#1e293b; --p2p-border:#334155; --p2p-text:#f1f5f9; --p2p-muted:#94a3b8 }
/* ═══ CUSTOM SELECT COMPONENT ═══ */
.p2p-custom-select { position:relative; width:100% }
.p2p-select-trigger { display:flex; align-items:center; gap:10px; padding:10px 14px; background:var(--p2p-card); border:2px solid var(--p2p-border); border-radius:12px; cursor:pointer; font-size:.82rem; font-weight:700; color:var(--p2p-text); transition:all .2s }
.p2p-select-trigger:hover { border-color:var(--p2p-accent); background:rgba(139,92,246,.04) }
.p2p-select-trigger span { flex: 1; text-align: left; }
.p2p-select-trigger img { width:20px; height:20px }
.p2p-select-trigger::after { content:'\f078'; font-family:'Font Awesome 6 Free'; font-weight:900; font-size:10px; opacity:.4; transition:transform .2s; margin-left:4px }
.p2p-custom-select.active .p2p-select-trigger::after { transform:rotate(180deg); opacity:1; color:var(--p2p-accent) }
.p2p-select-options { position:absolute; top:calc(100% + 6px); left:0; right:0; background:var(--p2p-card); border:1px solid var(--p2p-border); border-radius:12px; box-shadow:0 12px 32px rgba(0,0,0,.12); z-index:200; display:none; padding:6px; animation:p2pSelectIn .2s ease; max-height:220px; overflow-y:auto; scrollbar-width:none }
.p2p-select-options::-webkit-scrollbar { display:none }
@keyframes p2pSelectIn { from { opacity:0; transform:translateY(10px) } to { opacity:1; transform:translateY(0) } }
.p2p-select-option { display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:8px; cursor:pointer; font-size:.8rem; font-weight:600; color:var(--p2p-muted); transition:all .15s; text-align: left; margin-bottom:3px; }
.p2p-select-option:hover { background:var(--p2p-bg); color:var(--p2p-accent) }
.p2p-select-option.active { background:rgba(139,92,246,.1); color:var(--p2p-accent) }
.p2p-select-option img { width:20px; height:20px }
.p2p-custom-select.active .p2p-select-options { display:block }
.p2p-custom-select.active .p2p-select-trigger { border-color:var(--p2p-accent); box-shadow:0 0 0 4px rgba(139,92,246,.1) }

.p2p-page { max-width:800px; margin:0 auto; padding:0 12px 3rem; font-family:'Plus Jakarta Sans',sans-serif }

/* ═══ HERO ═══ */
.p2p-hero-v3 { display:flex; align-items:center; gap:20px; padding:24px 28px; border-radius:28px; background:linear-gradient(135deg,#0f172a,#1e1b4b,#1a0533); margin:1.5rem 0; position:relative; overflow:hidden; border:1px solid rgba(139,92,246,.15) }
.p2p-hero-v3::before { content:''; position:absolute; top:-100px; right:-100px; width:300px; height:300px; border-radius:50%; background:radial-gradient(circle,rgba(139,92,246,.2),transparent 70%) }
.p2p-hero-v3-content { flex:1; position:relative; z-index:1 }
.p2p-hero-v3 h1 { font-size:1.6rem; font-weight:900; color:#fff; margin:0 0 4px; letter-spacing:-.03em }
.p2p-hero-v3 p { font-size:.82rem; color:#94a3b8; margin:0 }

/* ═══ TOP BAR (coin stats + actions) ═══ */
.p2p-topbar-v3 { display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:12px; margin-bottom:20px }
.p2p-topbar-v3-icon { font-size:22px; margin-bottom:4px; display:flex; align-items:center; justify-content:center; gap:4px }
.p2p-topbar-v3-icon img { width:24px; height:24px }
.p2p-topbar-v3-label { font-size:.6rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--p2p-muted); margin-bottom:2px }
.p2p-topbar-v3-value { font-size:18px; font-weight:900; letter-spacing:-.3px; color:var(--p2p-text) }
.p2p-topbar-v3-value.green { color:#059669 }
@media(max-width:500px){ .p2p-topbar-v3 { grid-template-columns:1fr 1fr } }

/* ═══ TAB BAR ═══ */
.p2p-tab-panel { display: none; animation:p2pTabIn .25s ease }
.p2p-tab-panel.active { display: block; }
@keyframes p2pTabIn { from { opacity:0; transform:translateY(8px) } to { opacity:1; transform:translateY(0) } }

/* Tab panel content sections */
.p2p-section-card { background:var(--p2p-card); border:1px solid var(--p2p-border); border-radius:20px; overflow:hidden; margin-bottom:16px }
.p2p-section-card-head { padding:16px 20px; font-size:.9rem; font-weight:800; border-bottom:1px solid var(--p2p-border); display:flex; align-items:center; gap:8px }
.p2p-section-card-body { padding:16px 20px }
.p2p-order-filters { display:flex; gap:8px; margin-bottom:12px; flex-wrap:wrap; align-items:center }
.p2p-date-filter { position:relative; flex:0 0 168px }
.p2p-date-filter i { position:absolute; left:12px; top:50%; transform:translateY(-50%); font-size:.72rem; color:var(--p2p-accent); pointer-events:none; z-index:1 }
.p2p-date-filter input { width:100%; height:42px; padding:9px 12px 9px 34px; border-radius:12px; border:2px solid var(--p2p-border); font-size:.78rem; font-weight:700; outline:none; font-family:'Plus Jakarta Sans',sans-serif; background:var(--p2p-card); color:var(--p2p-text); box-sizing:border-box; cursor:pointer; transition:all .2s; color-scheme:light }
.dark .p2p-date-filter input { color-scheme:dark }
.p2p-date-filter input:hover { border-color:var(--p2p-accent); background:rgba(139,92,246,.04) }
.p2p-date-filter input:focus { border-color:var(--p2p-accent); box-shadow:0 0 0 4px rgba(139,92,246,.1) }
.p2p-date-filter input::-webkit-calendar-picker-indicator { opacity:.55; cursor:pointer; padding:4px; border-radius:6px }
.p2p-date-filter input::-webkit-calendar-picker-indicator:hover { background:rgba(139,92,246,.1); opacity:1 }
@media(max-width:520px){ .p2p-order-filters { display:grid; grid-template-columns:1fr 1fr; gap:8px } .p2p-order-filters .p2p-custom-select { min-width:0 !important; max-width:none !important } .p2p-date-filter { grid-column:1 / -1; flex:auto } }
.p2p-select { 
  appearance:none; -webkit-appearance:none; -moz-appearance:none; 
  background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236b7280' d='M6 8L1 3h10z'/%3E%3C/svg%3E"); 
  background-repeat:no-repeat; background-position:right 14px center; 
  padding:10px 40px 10px 14px; border-radius:12px; border:2px solid var(--p2p-border); 
  font-size:.82rem; font-weight:700; outline:none; font-family:'Plus Jakarta Sans',sans-serif; 
  background-color:var(--p2p-card); color:var(--p2p-text); width:100%; box-sizing:border-box; 
  cursor:pointer; transition:all .2s; box-shadow: 0 2px 4px rgba(0,0,0,0.02);
}
.p2p-select:hover { border-color:var(--p2p-accent); background-color:rgba(139,92,246,.02); }
.dark .p2p-select:hover { background-color:rgba(139,92,246,.04) }
.p2p-select:focus { border-color:var(--p2p-accent); box-shadow:0 0 0 4px rgba(139,92,246,.1) }
.p2p-select option { padding:10px 12px; font-weight:500; background:var(--p2p-card); color:var(--p2p-text); }

.p2p-btn-submit-report {
  width: 100%;
  padding: 13px;
  border-radius: 14px;
  border: 0;
  font-size: .9rem;
  font-weight: 800;
  color: #fff;
  background: linear-gradient(135deg, #ef4444, #dc2626);
  cursor: pointer;
  transition: all .2s;
  font-family: 'Plus Jakarta Sans', sans-serif;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
}
.p2p-btn-submit-report:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(239, 68, 68, 0.3);
  background: linear-gradient(135deg, #f87171, #ef4444);
}
.p2p-btn-submit-report:active {
  transform: translateY(0);
}
.p2p-btn-submit-report:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
}
.p2p-tabbar-v3 { display:flex; gap:4px; margin-bottom:16px; background:rgba(255,255,255,.7); backdrop-filter:blur(12px); border-radius:16px; padding:4px; position:sticky; top:80px; z-index:50; border:1px solid var(--p2p-border); box-shadow:0 4px 16px rgba(0,0,0,.04) }
.dark .p2p-tabbar-v3 { background:rgba(30,41,59,.8); border-color:#374151 }
.p2p-tabbar-btn { flex:1; padding:10px 8px; border-radius:10px; border:0; font-size:.8rem; font-weight:700; cursor:pointer; transition:all .2s; background:transparent; color:var(--p2p-muted); display:flex; align-items:center; justify-content:center; gap:6px; white-space:nowrap; font-family:'Plus Jakarta Sans',sans-serif }
.p2p-tabbar-btn:hover { color:var(--p2p-text) }
.p2p-tabbar-btn.active { background:var(--p2p-card); color:var(--p2p-accent); box-shadow:0 2px 8px rgba(0,0,0,.08) }
.p2p-tabbar-btn .badge { background:#e5e7eb; color:#6b7280; font-size:.55rem; font-weight:800; padding:2px 7px; border-radius:999px; margin-left:2px }
.p2p-tabbar-btn.active .badge { background:#ede9fe; color:#7c3aed }
.dark .p2p-tabbar-btn .badge { background:#4b5563; color:#9ca3af }
.dark .p2p-tabbar-btn.active .badge { background:rgba(139,92,246,.2); color:#a78bfa }

/* ═══ OFFER GRID (Binance-style cards) ═══ */
.p2p-offer-grid-v3 { display:grid; gap:12px; margin-bottom:24px }
.p2p-offer-card-v3 { 
  background:var(--p2p-card); border-radius:20px; padding:0; cursor:pointer; 
  transition:all .3s cubic-bezier(.34,1.56,.64,1); position:relative; overflow:hidden;
  border:1px solid var(--p2p-border); box-shadow:0 4px 16px rgba(0,0,0,.03)
}
.p2p-offer-card-v3::before { content:''; position:absolute; inset:0; border-radius:20px; padding:1px; background:linear-gradient(135deg,rgba(139,92,246,.15),transparent 40%,transparent 60%,rgba(5,150,105,.1)); -webkit-mask:linear-gradient(#fff 0 0) content-box,linear-gradient(#fff 0 0); -webkit-mask-composite:xor; mask:linear-gradient(#fff 0 0) content-box,linear-gradient(#fff 0 0); mask-composite:exclude; pointer-events:none }
.p2p-offer-card-v3:hover { transform:translateY(-4px); box-shadow:0 16px 48px rgba(139,92,246,.12); border-color:rgba(139,92,246,.3) }
.p2p-offer-card-v3-inner { padding:18px 20px; position:relative; z-index:1 }



/* ═══ OFFER DETAIL MODAL (full Binance-style) ═══ */
.p2p-detail-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.6); z-index:99999; align-items:center; justify-content:center; backdrop-filter:blur(6px) }
.p2p-detail-panel { 
  background:var(--p2p-card); width:100%; max-width:480px; max-height:92vh; border-radius:28px; 
  overflow-y:auto; animation:p2pModalIn .3s ease; box-shadow:0 10px 40px rgba(0,0,0,.2);
  scrollbar-width: none; -ms-overflow-style: none;
}
.p2p-detail-panel::-webkit-scrollbar { display: none; }
@keyframes p2pModalIn { from { opacity:0; transform:translateY(30px) scale(.98) } to { opacity:1; transform:translateY(0) scale(1) } }
.p2p-detail-head { display:flex; align-items:center; justify-content:space-between; padding:18px 22px; border-bottom:1px solid var(--p2p-border); position:sticky; top:0; background:var(--p2p-card); z-index:2 }
.p2p-detail-head h3 { margin:0; font-size:1.05rem; font-weight:800; display:flex; align-items:center; gap:8px }
.p2p-detail-close { width:34px; height:34px; border-radius:50%; border:0; background:rgba(0,0,0,.05); cursor:pointer; display:flex; align-items:center; justify-content:center; color:#6b7280; font-size:14px; transition:all .2s; backdrop-filter:blur(4px) }
.p2p-detail-close:hover { background:rgba(239,68,68,.12); color:#dc2626; transform:rotate(90deg) }
.dark .p2p-detail-close { background:rgba(255,255,255,.08); color:#9ca3af }
.dark .p2p-detail-close:hover { background:rgba(239,68,68,.2); color:#fca5a5 }
.p2p-detail-body { padding:20px 22px 24px }

/* ═══ ORDER FLOW ═══ */
.p2p-order-steps { display:flex; gap:8px; margin-bottom:20px; justify-content:center }
.p2p-order-step { display:flex; align-items:center; gap:6px; font-size:.7rem; font-weight:600; color:var(--p2p-muted) }
.p2p-order-step .num { width:22px; height:22px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:.65rem; font-weight:800; background:#e5e7eb; color:#6b7280 }
.p2p-order-step.active .num { background:var(--p2p-accent); color:#fff }
.p2p-order-step.done .num { background:#059669; color:#fff }
.p2p-order-step.done { color:#059669 }
.p2p-order-step-conn { width:20px; height:2px; background:#e5e7eb }
.p2p-order-step-conn.done { background:#059669 }

/* ═══ CHAT ═══ */
/* ═══ CHAT MODAL — Redesigned ═══ */
.p2p-chat-modal { display:flex; flex-direction:column; height:100%; background:var(--p2p-card); position:relative }
.p2p-chat-top { padding:14px 18px; border-bottom:1px solid var(--p2p-border); display:flex; align-items:center; gap:12px; flex-shrink:0; position:sticky; top:0; background:var(--p2p-card); z-index:2 }
.p2p-chat-top-avatar { width:34px; height:34px; border-radius:50%; background:linear-gradient(135deg,#8b5cf6,#6366f1); color:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:800; flex-shrink:0 }
.p2p-chat-top-info { flex:1; min-width:0 }
.p2p-chat-top-name { font-size:.82rem; font-weight:800; color:var(--p2p-text); display:flex; align-items:center; gap:6px }
.p2p-chat-top-name .badge { font-size:.5rem; background:#059669; color:#fff; padding:1px 6px; border-radius:99px; font-weight:700 }
.p2p-chat-top-sub { font-size:.65rem; color:var(--p2p-muted) }
.p2p-chat-msgs { 
  flex:1; overflow-y:auto; padding:16px 16px 8px; display:flex; flex-direction:column; gap:10px;
  scrollbar-width: thin; scrollbar-color:var(--p2p-border) transparent;
  background: linear-gradient(180deg, var(--p2p-card) 0%, rgba(139,92,246,.02) 100%);
}
.p2p-chat-msgs::-webkit-scrollbar { width:4px }
.p2p-chat-msgs::-webkit-scrollbar-thumb { background:var(--p2p-border); border-radius:99px }
.p2p-chat-date-divider { text-align:center; font-size:.6rem; font-weight:700; color:var(--p2p-muted); padding:8px 0 4px; opacity:.6; letter-spacing:.3px; text-transform:uppercase }
.p2p-chat-msg { max-width:88%; padding:10px 14px; border-radius:18px; font-size:.8rem; line-height:1.45; word-wrap:break-word; position:relative; animation:p2pMsgIn .25s ease }
@keyframes p2pMsgIn { from { opacity:0; transform:translateY(8px) } to { opacity:1; transform:translateY(0) } }
.p2p-chat-msg.own { align-self:flex-end; background:linear-gradient(135deg,var(--p2p-accent),#7c3aed); color:#fff; border-bottom-right-radius:4px; box-shadow:0 2px 8px rgba(139,92,246,.2) }
.p2p-chat-msg.other { align-self:flex-start; background:#f1f5f9; color:#1f2937; border-bottom-left-radius:4px; box-shadow:0 1px 4px rgba(0,0,0,.04) }
.dark .p2p-chat-msg.other { background:#1e293b; color:#e2e8f0; box-shadow:0 1px 4px rgba(0,0,0,.15) }
.p2p-chat-msg .sender { font-size:.6rem; font-weight:700; margin-bottom:3px; opacity:.7; display:flex; align-items:center; gap:4px }
.p2p-chat-msg .sender .avi { width:16px; height:16px; border-radius:50%; background:linear-gradient(135deg,#8b5cf6,#6366f1); color:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:8px; font-weight:800 }
.p2p-chat-msg .time { font-size:.5rem; opacity:.5; margin-top:4px; display:block; text-align:right }
.p2p-chat-msg.own .time { opacity:.6 }
.p2p-chat-msg img { max-width:100%; border-radius:12px; display:block; margin-bottom:6px; cursor:pointer; transition:transform .2s }
.p2p-chat-msg img:hover { transform:scale(1.02) }
.p2p-chat-input-wrap { display:flex; gap:8px; padding:12px 16px; border-top:1px solid var(--p2p-border); flex-shrink:0; background:var(--p2p-card); position:sticky; bottom:0; align-items:flex-end }
.p2p-chat-input-wrap .attach-btn { background:none; border:0; color:var(--p2p-muted); font-size:1.2rem; padding:0 4px 8px; cursor:pointer; transition:color .15s; display:flex; align-items:center }
.p2p-chat-input-wrap .attach-btn:hover { color:var(--p2p-accent) }
.p2p-chat-input-wrap input { flex:1; padding:11px 16px; border-radius:20px; border:2px solid var(--p2p-border); font-size:.82rem; outline:none; transition:border-color .2s,box-shadow .2s; font-family:'Plus Jakarta Sans',sans-serif; font-weight:500; background:var(--p2p-bg); color:var(--p2p-text); resize:none }
.p2p-chat-input-wrap input:focus { border-color:var(--p2p-accent); box-shadow:0 0 0 3px rgba(139,92,246,.1) }
.p2p-chat-input-wrap .send-btn { width:40px; height:40px; border-radius:50%; border:0; background:linear-gradient(135deg,var(--p2p-accent),#7c3aed); color:#fff; cursor:pointer; transition:all .2s; font-size:1rem; display:flex; align-items:center; justify-content:center; flex-shrink:0; box-shadow:0 2px 8px rgba(139,92,246,.25) }
.p2p-chat-input-wrap .send-btn:hover { transform:scale(1.08); box-shadow:0 4px 14px rgba(139,92,246,.35) }
.p2p-chat-input-wrap .send-btn:active { transform:scale(.95) }
.p2p-chat-empty { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; color:var(--p2p-muted); gap:8px; padding:40px 20px; text-align:center }
.p2p-chat-empty i { font-size:2.5rem; opacity:.2 }
.p2p-chat-empty p { font-size:.8rem; font-weight:500; opacity:.6; margin:0 }

/* ═══ PAYMENT SETTINGS ═══ */
.p2p-pay-settings { display:grid; gap:14px; margin-top:16px }
.p2p-pay-method { background:var(--p2p-card); border:1px solid var(--p2p-border); border-radius:16px; padding:16px 18px }
.p2p-pay-method-head { display:flex; align-items:center; gap:10px; margin-bottom:10px }
.p2p-pay-method-head img { height:24px }
.p2p-pay-method-head strong { font-size:.85rem }
.p2p-pay-input { width:100%; padding:10px 12px; border-radius:10px; border:2px solid var(--p2p-border); font-size:.78rem; font-weight:600; outline:none; box-sizing:border-box; margin-bottom:8px; font-family:'Plus Jakarta Sans',sans-serif; background:var(--p2p-card); color:var(--p2p-text) }
.p2p-pay-input:focus { border-color:var(--p2p-accent) }
.p2p-pay-select { padding:8px 12px; border-radius:10px; border:2px solid var(--p2p-border); font-size:.75rem; font-weight:600; outline:none; font-family:'Plus Jakarta Sans',sans-serif; background:var(--p2p-card); color:var(--p2p-text); margin-bottom:8px }

/* ═══ CONVERSION ═══ */
.p2p-convert-card { background:var(--p2p-card); border:1px solid var(--p2p-border); border-radius:20px; padding:20px; margin-bottom:20px }
.p2p-convert-card h3 { margin:0 0 14px; font-size:.95rem; font-weight:800; display:flex; align-items:center; gap:8px }
.p2p-convert-grid { display:grid; grid-template-columns:1fr auto 1fr; gap:10px; align-items:center }
.p2p-convert-grid select, .p2p-convert-grid input { padding:10px 12px; border-radius:12px; border:2px solid var(--p2p-border); font-size:.82rem; font-weight:600; outline:none; font-family:'Plus Jakarta Sans',sans-serif; background:var(--p2p-card); color:var(--p2p-text); width:100%; box-sizing:border-box }
.p2p-convert-grid select:focus, .p2p-convert-grid input:focus { border-color:var(--p2p-accent) }
.p2p-convert-swap { width:40px; height:40px; border-radius:50%; border:0; background:#f3f4f6; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:16px; color:var(--p2p-muted); transition:all .15s; flex-shrink:0 }
.p2p-convert-swap:hover { background:var(--p2p-accent); color:#fff }
.p2p-convert-rate { text-align:center; font-size:.75rem; color:var(--p2p-muted); margin:10px 0 14px; padding:8px; background:#f9fafb; border-radius:10px }
.dark .p2p-convert-rate { background:#1a2332 }
.p2p-convert-btn { width:100%; padding:13px; border-radius:14px; border:0; font-size:.9rem; font-weight:800; color:#fff; background:linear-gradient(135deg,var(--p2p-accent),#6366f1); cursor:pointer; transition:all .2s; font-family:'Plus Jakarta Sans',sans-serif }
.p2p-convert-btn:hover { transform:translateY(-2px); box-shadow:0 8px 24px rgba(139,92,246,.3) }

/* ═══ ACTIVE TRADES ═══ */
/* ═══ ORDERS TAB — Premium Redesign ═══ */
.p2p-trade-item { background:var(--p2p-card); border:1px solid var(--p2p-border); border-radius:16px; padding:0; margin-bottom:10px; transition:all .2s; overflow:hidden; position:relative }
.p2p-trade-item:hover { border-color:var(--p2p-accent); box-shadow:0 4px 16px rgba(139,92,246,.08) }
.dark .p2p-trade-item:hover { box-shadow:0 4px 16px rgba(139,92,246,.06) }
.p2p-trade-item::before { content:''; position:absolute; left:0; top:0; bottom:0; width:3px; border-radius:0 3px 3px 0 }
.p2p-trade-item[data-status="completed"]::before { background:#059669 }
.p2p-trade-item[data-status="pending"]::before { background:#f59e0b }
.p2p-trade-item[data-status="paid"]::before { background:#3b82f6 }
.p2p-trade-item[data-status="cancelled"]::before { background:#ef4444 }
.p2p-trade-item[data-status="disputed"]::before { background:#f97316 }
.p2p-trade-top { display:flex; align-items:center; gap:10px; padding:12px 16px 8px 20px }
.p2p-trade-icon { width:36px; height:36px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:14px; flex-shrink:0; background:linear-gradient(135deg,#f5f3ff,#ede9fe); color:#7c3aed }
.dark .p2p-trade-icon { background:rgba(139,92,246,.15); color:#a78bfa }
.p2p-trade-icon.sell { background:linear-gradient(135deg,#fef3c7,#fde68a); color:#d97706 }
.dark .p2p-trade-icon.sell { background:rgba(251,191,36,.15); color:#fbbf24 }
.p2p-trade-meta { flex:1; min-width:0 }
.p2p-trade-meta-top { display:flex; align-items:center; gap:8px; flex-wrap:wrap }
.p2p-trade-id { font-size:.65rem; font-weight:700; color:var(--p2p-muted); letter-spacing:.3px }
.p2p-trade-partner { font-size:.75rem; font-weight:600; color:var(--p2p-text); display:flex; align-items:center; gap:4px }
.p2p-trade-partner .avi { width:16px; height:16px; border-radius:50%; background:linear-gradient(135deg,#8b5cf6,#6366f1); color:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:7px; font-weight:800; flex-shrink:0 }
.p2p-trade-status { font-size:.55rem; font-weight:700; padding:2px 10px; border-radius:999px; text-transform:capitalize; letter-spacing:.3px; display:inline-flex; align-items:center; gap:3px }
.p2p-trade-status.yellow { background:#fef9c3; color:#854d0e }
.p2p-trade-status.blue { background:#dbeafe; color:#1e40af }
.p2p-trade-status.green { background:#d1fae5; color:#065f46 }
.p2p-trade-status.red { background:#fef2f2; color:#991b1b }
.p2p-trade-status.orange { background:#ffedd5; color:#9a3412 }
.dark .p2p-trade-status.yellow { background:rgba(251,191,36,.15); color:#fbbf24 }
.dark .p2p-trade-status.blue { background:rgba(59,130,246,.15); color:#93c5fd }
.dark .p2p-trade-status.green { background:rgba(5,150,105,.15); color:#34d399 }
.dark .p2p-trade-status.red { background:rgba(220,38,38,.15); color:#fca5a5 }
.dark .p2p-trade-status.orange { background:rgba(251,146,60,.15); color:#fdba74 }
.p2p-trade-detail { display:flex; gap:6px; padding:0 16px 8px 20px; flex-wrap:wrap }
.p2p-trade-detail .chip { display:inline-flex; align-items:center; gap:4px; font-size:.65rem; font-weight:600; color:var(--p2p-muted); background:var(--p2p-bg); padding:4px 10px; border-radius:8px; border:1px solid var(--p2p-border) }
.dark .p2p-trade-detail .chip { background:rgba(255,255,255,.03) }
.p2p-trade-detail .chip strong { color:var(--p2p-text) }
.p2p-trade-detail .chip i { font-size:.55rem; opacity:.6 }
.p2p-trade-actions { display:flex; gap:4px; padding:8px 16px 12px 20px; flex-wrap:wrap; border-top:1px solid var(--p2p-border); background:var(--p2p-bg) }
.dark .p2p-trade-actions { background:rgba(0,0,0,.1) }
.p2p-trade-actions button { padding:6px 12px; border-radius:10px; border:0; font-size:.65rem; font-weight:700; cursor:pointer; transition:all .15s; font-family:'Plus Jakarta Sans',sans-serif; display:inline-flex; align-items:center; gap:4px }
.p2p-btn-chat { background:#f3f4f6; color:#374151 }
.p2p-btn-chat:hover { background:#e5e7eb }
.dark .p2p-btn-chat { background:rgba(255,255,255,.08); color:#e2e8f0 }
.dark .p2p-btn-chat:hover { background:rgba(255,255,255,.12) }
.p2p-btn-confirm { background:linear-gradient(135deg,#059669,#10b981); color:#fff }
.p2p-btn-confirm:hover { transform:translateY(-1px); box-shadow:0 4px 12px rgba(5,150,105,.3) }
.p2p-btn-cancel { background:#fee2e2; color:#991b1b }
.p2p-btn-cancel:hover { background:#fecaca }
.dark .p2p-btn-cancel { background:rgba(220,38,38,.2); color:#fca5a5 }
.p2p-btn-primary { background:linear-gradient(135deg,#8b5cf6,#7c3aed); color:#fff }
.p2p-btn-primary:hover { transform:translateY(-1px); box-shadow:0 4px 12px rgba(139,92,246,.3) }
.p2p-btn-report { padding:6px 10px !important; border-radius:50% !important; background:#fef2f2; color:#dc2626; font-size:.65rem }
.p2p-btn-report:hover { background:#fee2e2 }
.dark .p2p-btn-report { background:rgba(220,38,38,.2); color:#fca5a5 }
.dark .p2p-btn-report:hover { background:rgba(220,38,38,.3) }
.p2p-trade-time { font-size:.6rem; color:var(--p2p-muted); opacity:.7; display:flex; align-items:center; gap:3px; margin-left:auto }

/* ═══ PAYMENT DETAIL DISPLAY (inside order) ═══ */
.p2p-pay-detail { background:#f9fafb; border-radius:14px; padding:14px 16px; margin:12px 0; border:1px dashed var(--p2p-border) }
.dark .p2p-pay-detail { background:#1a2332 }
.p2p-pay-detail-row { display:flex; align-items:center; justify-content:space-between; padding:5px 0; font-size:.8rem }
.p2p-pay-detail-row .lbl { color:var(--p2p-muted); font-weight:500 }
.p2p-pay-detail-row .val { font-weight:700; color:var(--p2p-text) }
.p2p-pay-detail .copy-btn { padding:2px 8px; border-radius:6px; border:0; background:#e5e7eb; font-size:.6rem; font-weight:600; cursor:pointer; transition:all .1s; margin-left:4px }
.p2p-pay-detail .copy-btn:hover { background:#d1d5db }

/* ═══ PAYMENT DETAIL V3 (order summary) ═══ */
.p2p-pay-detail-v3 { background:linear-gradient(135deg,#f0fdf4,#ecfdf5); border:2px solid #bbf7d0; border-radius:16px; padding:16px; margin-bottom:16px }
.dark .p2p-pay-detail-v3 { background:rgba(5,150,105,.1); border-color:rgba(5,150,105,.3) }

/* ═══ PAYMENT WARNING BOX ═══ */
.p2p-pay-warning { margin:16px 0 12px; padding:14px; border-radius:14px; background:#fefce8; border:1px solid #fde68a; text-align:center }
.dark .p2p-pay-warning { background:rgba(217,119,6,.1); border-color:rgba(217,119,6,.3) }
.p2p-pay-warning-title { font-size:.7rem; font-weight:700; color:#92400e; text-transform:uppercase; letter-spacing:.5px; margin-bottom:8px }
.dark .p2p-pay-warning-title { color:#fbbf24 }
.p2p-pay-warning-note { font-size:.7rem; color:#92400e; margin-top:4px }
.dark .p2p-pay-warning-note { color:#fbbf24 }
.p2p-pay-phone { font-size:.82rem; font-weight:800; color:#1f2937 }
.dark .p2p-pay-phone { color:var(--p2p-text) }
.p2p-pay-method-card { padding:10px 12px; margin-bottom:8px; border-radius:12px; text-align:left; background:#f8fafc }
.dark .p2p-pay-method-card { background:rgba(30,41,59,.5) }

/* ═══ FEEDBACK ═══ */
.p2p-fb { padding:10px 14px; border-radius:12px; font-size:.78rem; font-weight:600; margin-top:12px; display:none }
.p2p-fb.success { display:block; background:#d1fae5; color:#065f46; border:1px solid #a7f3d0 }
.p2p-fb.error { display:block; background:#fef2f2; color:#991b1b; border:1px solid #fecaca }
.dark .p2p-fb.success { background:rgba(5,150,105,.15); color:#34d399; border-color:rgba(5,150,105,.3) }
.dark .p2p-fb.error { background:rgba(220,38,38,.15); color:#fca5a5; border-color:rgba(220,38,38,.3) }
.p2p-fb.info { display:block; background:#dbeafe; color:#1e40af; border:1px solid #bfdbfe }
.dark .p2p-fb.info { background:rgba(59,130,246,.15); color:#93c5fd; border-color:rgba(59,130,246,.3) }

/* ═══ MY OFFERS (agent) ═══ */
.p2p-myoffer-list { display:grid; gap:8px }
.p2p-myoffer-item { display:flex; align-items:center; justify-content:space-between; padding:10px 14px; background:var(--p2p-card); border:1px solid var(--p2p-border); border-radius:12px }
.p2p-myoffer-item .info { display:flex; flex-direction:column; gap:2px }
.p2p-myoffer-item .info strong { font-size:.82rem; display:flex; align-items:center; gap:4px }
.p2p-myoffer-item .info strong img { width:16px; height:16px }
.p2p-myoffer-item .info span { font-size:.68rem; color:var(--p2p-muted) }
.p2p-myoffer-item .meta { display:flex; align-items:center; gap:8px }
.p2p-myoffer-item .meta .remain { font-size:.7rem; font-weight:600; color:var(--p2p-muted) }
.p2p-myoffer-item .meta .st { font-size:.6rem; font-weight:700; padding:2px 8px; border-radius:999px }
.p2p-myoffer-item .meta .st.active { background:#d1fae5; color:#065f46 }
.p2p-myoffer-item .meta .st.cancelled { background:#fef2f2; color:#991b1b }
.p2p-myoffer-item .meta .st.completed { background:#f5f3ff; color:#5b21b6 }

/* ═══ TOPBAR ITEMS ═══ */
.p2p-topbar-v3-item { background:var(--p2p-card); border:1px solid var(--p2p-border); border-radius:16px; padding:14px 16px; text-align:center; transition:all .2s }
.p2p-topbar-v3-item:hover { border-color:rgba(139,92,246,.3) }

/* ═══ OFFER CARD INTERNALS ═══ */
.p2p-offer-card-v3-top { display:flex; align-items:center; gap:10px; margin-bottom:14px }
.p2p-offer-card-v3-avatar { width:40px; height:40px; border-radius:50%; background:linear-gradient(135deg,#8b5cf6,#6366f1); color:#fff; display:flex; align-items:center; justify-content:center; font-size:.9rem; font-weight:800; flex-shrink:0 }
.p2p-offer-card-v3-user .name { font-size:.85rem; font-weight:700; color:var(--p2p-text); display:flex; align-items:center; gap:5px }
.p2p-offer-card-v3-user .verified { width:16px; height:16px; background:#3b82f6; color:#fff; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:.5rem }
.p2p-offer-card-v3-user .role { font-size:.7rem; color:var(--p2p-muted); margin-top:2px }
.p2p-offer-card-v3-user .orders { color:var(--p2p-accent); font-weight:600 }
.p2p-offer-card-v3-body { display:grid; grid-template-columns:repeat(4,1fr); gap:8px; margin-bottom:14px; padding:12px; background:var(--p2p-bg); border-radius:12px }
@media(max-width:480px){ .p2p-offer-card-v3-body { grid-template-columns:1fr 1fr } }
.p2p-offer-card-v3-cell .lbl { font-size:.58rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--p2p-muted); margin-bottom:3px }
.p2p-offer-card-v3-cell .val { font-size:.85rem; font-weight:800; color:var(--p2p-text) }
.p2p-offer-card-v3-cell .val.price { color:var(--p2p-accent) }
.p2p-offer-card-v3-cell .coin-tag { display:inline-flex; align-items:center; gap:3px }
.p2p-offer-card-v3-footer { display:flex; align-items:center; justify-content:space-between; padding-top:10px; border-top:1px solid var(--p2p-border) }
.p2p-offer-card-v3-footer .payment-icons { display:flex; gap:5px; align-items:center }
.p2p-offer-card-v3-footer .payment-icons img { height:18px; border-radius:4px; opacity:.8 }
.p2p-offer-card-v3-footer .action-badge { font-size:.72rem; font-weight:800; padding:5px 16px; border-radius:999px; background:linear-gradient(135deg,#8b5cf6,#6366f1); color:#fff }
.p2p-coin-svg { width:20px; height:20px; vertical-align:middle }

/* ═══ COIN FILTER ═══ */
.p2p-coin-filter { display:flex; gap:6px; margin-bottom:14px; flex-wrap:wrap }
.p2p-coin-filter-btn { padding:5px 14px; border-radius:999px; border:1.5px solid var(--p2p-border); font-size:.72rem; font-weight:700; cursor:pointer; background:var(--p2p-card); color:var(--p2p-muted); transition:all .18s; font-family:'Plus Jakarta Sans',sans-serif; white-space:nowrap }
.p2p-coin-filter-btn:hover { border-color:var(--p2p-accent); color:var(--p2p-accent) }
.p2p-coin-filter-btn.active { background:rgba(139,92,246,.1); border-color:var(--p2p-accent); color:var(--p2p-accent) }

/* ═══ ORDER MODAL INTERNALS ═══ */
.seller-card { display:flex; align-items:center; gap:12px; padding:14px; background:var(--p2p-bg); border-radius:14px; margin-bottom:16px }
.seller-card .avatar { width:44px; height:44px; border-radius:50%; background:linear-gradient(135deg,#8b5cf6,#6366f1); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1rem; font-weight:800; flex-shrink:0 }
.seller-card .info .name { font-size:.9rem; font-weight:700; color:var(--p2p-text) }
.seller-card .info .stats { font-size:.7rem; color:var(--p2p-muted); margin-top:2px }
.info-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:16px }
.info-grid .box { background:var(--p2p-bg); border-radius:12px; padding:10px 12px }
.info-grid .box .lbl { font-size:.58rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--p2p-muted); margin-bottom:3px }
.info-grid .box .val { font-size:.9rem; font-weight:800; color:var(--p2p-text) }
.info-grid .box .val.green { color:var(--p2p-green) }
.qty-presets { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:10px }
.qty-presets button { padding:6px 12px; border-radius:8px; border:1.5px solid var(--p2p-border); background:var(--p2p-card); font-size:.72rem; font-weight:700; cursor:pointer; color:var(--p2p-muted); transition:all .15s; font-family:'Plus Jakarta Sans',sans-serif }
.qty-presets button:hover { border-color:var(--p2p-accent); color:var(--p2p-accent) }
.qty-input { width:100%; padding:12px 14px; border-radius:12px; border:2px solid var(--p2p-border); font-size:.95rem; font-weight:700; outline:none; box-sizing:border-box; font-family:'Plus Jakarta Sans',sans-serif; background:var(--p2p-card); color:var(--p2p-text); margin-bottom:10px; transition:all .2s }
.qty-input:focus { border-color:var(--p2p-accent); box-shadow:0 0 0 4px rgba(139,92,246,.1) }
.total-display { background:var(--p2p-bg); border-radius:12px; padding:12px 14px; font-size:.9rem; font-weight:800; color:var(--p2p-text); margin-bottom:12px; display:flex; align-items:center; justify-content:space-between }
.total-display .sub { font-size:.65rem; font-weight:500; color:var(--p2p-muted) }
.action-btn { width:100%; padding:14px; border-radius:14px; border:0; font-size:.95rem; font-weight:800; color:#fff; cursor:pointer; transition:all .2s; font-family:'Plus Jakarta Sans',sans-serif; display:flex; align-items:center; justify-content:center; gap:8px; margin-bottom:8px }
.action-btn.buy { background:linear-gradient(135deg,#059669,#10b981); box-shadow:0 4px 16px rgba(5,150,105,.2) }
.action-btn.buy:hover { transform:translateY(-2px); box-shadow:0 8px 24px rgba(5,150,105,.35) }
.action-btn.sell { background:linear-gradient(135deg,#dc2626,#ef4444); box-shadow:0 4px 16px rgba(220,38,38,.2) }
.action-btn.sell:hover { transform:translateY(-2px); box-shadow:0 8px 24px rgba(220,38,38,.35) }
.action-btn:disabled { opacity:.6; cursor:not-allowed; transform:none !important }

/* ═══ PAYMENT CONFIRM FORM ═══ */
.p2p-confirm-pay-form { display:flex; flex-direction:column; gap:10px; margin-bottom:14px }
.p2p-confirm-pay-form .field { display:flex; flex-direction:column; gap:4px }
.p2p-confirm-pay-form .field label { font-size:.72rem; font-weight:700; color:var(--p2p-muted) }
.p2p-confirm-pay-form .field input { width:100%; padding:11px 14px; border-radius:12px; border:2px solid var(--p2p-border); font-size:.85rem; font-weight:600; outline:none; box-sizing:border-box; font-family:'Plus Jakarta Sans',sans-serif; background:var(--p2p-card); color:var(--p2p-text); transition:border-color .2s }
.p2p-confirm-pay-form .field input:focus { border-color:var(--p2p-accent); box-shadow:0 0 0 4px rgba(139,92,246,.1) }

/* ═══ PAY DETAIL V3 ROWS ═══ */
.p2p-pay-detail-v3-row { display:flex; align-items:center; justify-content:space-between; padding:6px 0; font-size:.82rem }
.p2p-pay-detail-v3-row .lbl { color:var(--p2p-muted); font-weight:500 }
.p2p-pay-detail-v3-row .val { font-weight:800; color:var(--p2p-text) }

/* ═══ MOBILE TAB SCROLL ═══ */
@media(max-width:600px) {
  .p2p-tabbar-v3 { overflow-x:auto; scrollbar-width:none; flex-wrap:nowrap }
  .p2p-tabbar-v3::-webkit-scrollbar { display:none }
  .p2p-tabbar-btn { flex-shrink:0 }
}
@media(max-width:480px) {
  .p2p-detail-panel { max-width:none; max-height:none; border-radius:0; width:100vw; height:100dvh; }
  .p2p-detail-panel.p2p-detail-v3 { max-width:none; }
}
</style>

<div class="p2p-page" id="p2pPage" data-csrf="<?php echo htmlspecialchars($csrfToken); ?>" data-user-id="<?php echo (int)($viewerId ?? 0); ?>" data-role="<?php echo htmlspecialchars($userRole); ?>">

<?php if (!$viewerId): ?>
<div style="text-align:center;padding:4rem 1.5rem;color:var(--p2p-muted)"><i class="fas fa-right-to-bracket" style="font-size:2.5rem;display:block;margin-bottom:12px;opacity:.3"></i><p style="font-size:1rem;font-weight:500">Please <a href="index.php?page=login" data-page="login" style="color:var(--p2p-accent);font-weight:700">sign in</a> to trade.</p></div>
<?php else: ?>

<!-- ═══ HERO (Redesigned) ═══ -->
<div class="p2p-hero-v3" style="display:flex;align-items:stretch;gap:0;padding:0;background:linear-gradient(135deg,#0f172a,#1e1b4b,#1a0533);border-radius:24px;overflow:hidden;margin:1.5rem 0;border:1px solid rgba(139,92,246,.15);position:relative">
    <div style="position:absolute;top:-80px;right:-80px;width:250px;height:250px;border-radius:50%;background:radial-gradient(circle,rgba(139,92,246,.15),transparent 70%);pointer-events:none"></div>
    <div style="position:absolute;bottom:-60px;left:-60px;width:200px;height:200px;border-radius:50%;background:radial-gradient(circle,rgba(5,150,105,.1),transparent 70%);pointer-events:none"></div>
    <?php if ($userRole === 'merchant'):
        $myTotalTrades = 0; $myCompletedTrades = 0; $myTotalVolume = 0;
        foreach ($myTrades as $mt) {
            if ($mt['seller_id'] == $viewerId || $mt['buyer_id'] == $viewerId) {
                $myTotalTrades++;
                if ($mt['status'] === 'completed') { $myCompletedTrades++; $myTotalVolume += (float)$mt['total_price']; }
            }
        }
        $completionRate = $myTotalTrades > 0 ? round(($myCompletedTrades / $myTotalTrades) * 100) : 100;
        $myActiveOffers = 0;
        foreach ($myOffers as $mo) { if ($mo['status'] === 'active') $myActiveOffers++; }
        $myAvgRating = 0; $myReviewCount = 0;
        if (isset($ratingMap[$viewerId])) { $myAvgRating = $ratingMap[$viewerId]['avg']; $myReviewCount = $ratingMap[$viewerId]['count']; }
    ?>
    <div style="display:flex;align-items:center;gap:20px;flex:1;padding:24px 28px;position:relative;z-index:1;flex-wrap:wrap">
        <div style="flex-shrink:0;position:relative">
            <div style="width:72px;height:72px;border-radius:50%;overflow:hidden;border:3px solid rgba(139,92,246,.4);box-shadow:0 0 0 4px rgba(139,92,246,.15)">
                <img src="assets/avatars/<?php echo htmlspecialchars($userAvatar); ?>" alt="" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'" style="width:100%;height:100%;object-fit:cover">
                <div style="display:none;width:100%;height:100%;background:linear-gradient(135deg,#8b5cf6,#6366f1);color:#fff;align-items:center;justify-content:center;font-size:1.6rem;font-weight:800"><?php echo strtoupper(mb_substr($userName,0,1)); ?></div>
            </div>
            <div style="position:absolute;bottom:0;right:0;width:18px;height:18px;background:#10b981;border:3px solid #0f172a;border-radius:50%;z-index:2"></div>
        </div>
        <div style="flex:1;min-width:200px">
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                <h1 style="font-size:1.4rem;font-weight:900;color:#fff;margin:0;letter-spacing:-.03em"><?php echo htmlspecialchars($userName); ?></h1>
                <span style="background:linear-gradient(135deg,#059669,#10b981);color:#fff;font-size:.6rem;font-weight:700;padding:3px 10px;border-radius:999px;display:inline-flex;align-items:center;gap:4px"><i class="fas fa-check-circle"></i> Verified</span>
                <?php if ($myReviewCount > 0): ?>
                <span style="background:rgba(251,191,36,.15);color:#fbbf24;font-size:.6rem;font-weight:700;padding:3px 10px;border-radius:999px;display:inline-flex;align-items:center;gap:4px"><i class="fas fa-star"></i> <?php echo $myAvgRating; ?> (<?php echo $myReviewCount; ?>)</span>
                <?php endif; ?>
            </div>
            <p style="margin:4px 0 0;font-size:.78rem;color:#94a3b8"><?php echo $userJoined ? 'Merchant since ' . date('F Y', strtotime($userJoined)) : 'Verified Merchant'; ?> · <span style="color:#10b981"><i class="fas fa-circle" style="font-size:.5rem;vertical-align:middle"></i> Online</span></p>
            <div style="display:flex;gap:14px;margin-top:10px;flex-wrap:wrap">
                <div><span style="font-size:1.1rem;font-weight:900;color:#fff"><?php echo $myCompletedTrades; ?></span><span style="font-size:.6rem;color:#64748b;display:block;text-transform:uppercase;letter-spacing:.5px">Trades</span></div>
                <div><span style="font-size:1.1rem;font-weight:900;color:#fff"><?php echo $completionRate; ?>%</span><span style="font-size:.6rem;color:#64748b;display:block;text-transform:uppercase;letter-spacing:.5px">Fill Rate</span></div>
                <div><span style="font-size:1.1rem;font-weight:900;color:#fff">৳<?php echo number_format($myTotalVolume,0); ?></span><span style="font-size:.6rem;color:#64748b;display:block;text-transform:uppercase;letter-spacing:.5px">Volume</span></div>
                <div><span style="font-size:1.1rem;font-weight:900;color:#a78bfa"><?php echo $myActiveOffers; ?></span><span style="font-size:.6rem;color:#64748b;display:block;text-transform:uppercase;letter-spacing:.5px">Offers</span></div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div style="display:flex;align-items:center;gap:16px;flex:1;padding:24px 28px;position:relative;z-index:1">
        <div style="width:56px;height:56px;border-radius:16px;background:linear-gradient(135deg,#8b5cf6,#6366f1);display:flex;align-items:center;justify-content:center;font-size:1.5rem;color:#fff;flex-shrink:0;box-shadow:0 8px 24px rgba(139,92,246,.3)"><i class="fas fa-arrow-right-arrow-left"></i></div>
        <div style="flex:1">
            <h1 style="font-size:1.4rem;font-weight:900;color:#fff;margin:0;letter-spacing:-.03em">P2P Trading</h1>
            <p style="font-size:.82rem;color:#94a3b8;margin:2px 0 0">Buy and sell coins directly with verified merchants</p>
        </div>
    </div>
    <?php endif; ?>
    <div style="display:flex;align-items:center;padding:24px 28px;position:relative;z-index:1;flex-shrink:0;border-left:1px solid rgba(139,92,246,.1)">
        <span style="font-size:.65rem;font-weight:700;color:#a78bfa;background:rgba(139,92,246,.15);padding:8px 14px;border-radius:999px;border:1px solid rgba(139,92,246,.2);white-space:nowrap" id="p2pBalDisplay"><i class="fas fa-wallet"></i> ৳<span id="p2pBalVal"><?php echo number_format($balance??0,0); ?></span></span>
    </div>
</div>


<!-- ═══ TOP STAT BAR ═══ -->
<div class="p2p-topbar-v3">
    <div class="p2p-topbar-v3-item bronze">
        <div class="p2p-topbar-v3-icon"><?php echo $coinEmoji['bronze']; ?></div>
        <div class="p2p-topbar-v3-label">Bronze</div>
        <div class="p2p-topbar-v3-value"><?php echo number_format($bronzeCoins); ?></div>
    </div>
    <div class="p2p-topbar-v3-item silver">
        <div class="p2p-topbar-v3-icon"><?php echo $coinEmoji['silver']; ?></div>
        <div class="p2p-topbar-v3-label">Silver</div>
        <div class="p2p-topbar-v3-value"><?php echo number_format($silverCoins); ?></div>
    </div>
    <div class="p2p-topbar-v3-item gold">
        <div class="p2p-topbar-v3-icon"><?php echo $coinEmoji['gold']; ?></div>
        <div class="p2p-topbar-v3-label">Gold</div>
        <div class="p2p-topbar-v3-value gold"><?php echo number_format($goldCoins); ?></div>
    </div>
    <div class="p2p-topbar-v3-item balance">
        <div class="p2p-topbar-v3-icon"><i class="fas fa-wallet" style="font-size:22px;color:#059669"></i></div>
        <div class="p2p-topbar-v3-label">Balance</div>
        <div class="p2p-topbar-v3-value green">৳<?php echo number_format($balance??0,0); ?></div>
    </div>
</div>

<!-- ═══ TAB BAR ═══ -->
<div class="p2p-tabbar-v3">
    <button class="p2p-tabbar-btn active" data-tab="buy"><i class="fas fa-cart-shopping"></i> Buy <span class="badge"><?php echo count($sellOffers); ?></span></button>
    <button class="p2p-tabbar-btn" data-tab="sell"><i class="fas fa-dollar-sign"></i> Sell <span class="badge"><?php echo count($buyOffers); ?></span></button>
    <button class="p2p-tabbar-btn" data-tab="orders"><i class="fas fa-clock"></i> Orders <span class="badge"><?php echo count($myTrades); ?></span></button>
    <button class="p2p-tabbar-btn" data-tab="convert"><i class="fas fa-arrows-rotate"></i> Convert</button>
    <?php if ($userRole === 'merchant' || $userRole === 'admin'): ?>
    <button class="p2p-tabbar-btn" data-tab="offers"><i class="fas fa-tag"></i> Offers</button>
    <?php endif; ?>
    <button class="p2p-tabbar-btn" data-tab="settings"><i class="fas fa-gear"></i> Payment</button>
</div>

<!-- ════════════════════════════════════ -->
<!-- ═══ TAB: BUY ═══ -->
<!-- ════════════════════════════════════ -->
<div class="p2p-tab-panel active" id="tabBuy">
    <div class="p2p-coin-filter">
        <button class="p2p-coin-filter-btn active" data-coin="all">All</button>
        <button class="p2p-coin-filter-btn" data-coin="bronze">Bronze</button>
        <button class="p2p-coin-filter-btn" data-coin="silver">Silver</button>
        <button class="p2p-coin-filter-btn" data-coin="gold">Gold</button>
        <div style="flex:1;min-width:140px;position:relative">
            <input type="text" class="p2p-search-input" placeholder="🔍 Search merchant..." style="width:100%;padding:5px 12px;border-radius:999px;border:1.5px solid var(--p2p-border);font-size:.72rem;font-weight:600;outline:none;font-family:'Plus Jakarta Sans',sans-serif;background:var(--p2p-card);color:var(--p2p-text);box-sizing:border-box;transition:all .15s" oninput="filterOffers(this)">
        </div>
    </div>
    <?php if (empty($sellOffers)): ?>
    <div class="p2p-offer-grid-v3"><div style="text-align:center;padding:3rem;color:var(--p2p-muted)"><i class="fas fa-store" style="font-size:2rem;display:block;margin-bottom:8px;opacity:.3"></i><p style="font-weight:500">No sell offers available</p></div></div>
    <?php else: ?>
    <div class="p2p-offer-grid-v3">
    <?php foreach ($sellOffers as $o):
        $uname = htmlspecialchars($o['full_name'] ?: $o['username']);
        $initial = strtoupper(mb_substr($uname, 0, 1));
        $ctype = $o['coin_type'];
    ?>
    <div class="p2p-offer-card-v3" data-offer-id="<?php echo (int)$o['id']; ?>" data-type="buy" data-coin="<?php echo $ctype; ?>" data-price="<?php echo (float)$o['price_per_coin']; ?>" data-remaining="<?php echo (int)$o['remaining']; ?>" data-min="<?php echo (int)$o['min_amount']; ?>" data-max="<?php echo (int)$o['max_amount']; ?>" data-agent="<?php echo $uname; ?>" data-agent-id="<?php echo (int)$o['agent_id']; ?>">
        <div class="p2p-offer-card-v3-inner">
            <div class="p2p-offer-card-v3-top">
                <div class="p2p-offer-card-v3-avatar" onclick="event.stopPropagation();openMerchantProfile(<?php echo (int)$o['agent_id']; ?>)" style="cursor:pointer">
                    <img src="assets/avatars/<?php echo htmlspecialchars($o['avatar'] ?? 'default.png'); ?>" alt="" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'" style="width:100%;height:100%;border-radius:50%;object-fit:cover">
                    <div style="display:none;width:100%;height:100%;border-radius:50%;background:linear-gradient(135deg,#8b5cf6,#6366f1);color:#fff;align-items:center;justify-content:center;font-size:.9rem;font-weight:800"><?php echo $initial; ?></div>
                </div>
                <div class="p2p-offer-card-v3-user">
                    <div class="name"><a onclick="event.stopPropagation();openMerchantProfile(<?php echo (int)$o['agent_id']; ?>)" style="cursor:pointer;color:inherit;text-decoration:none"><?php echo $uname; ?></a> <span class="verified"><i class="fas fa-check"></i></span></div>
                    <div class="role"><span>Seller</span> · <span class="orders"><i class="fas fa-check-circle"></i> <?php echo number_format($tradeCountMap[(int)$o['agent_id']] ?? 0); ?> orders</span><?php if (isset($ratingMap[(int)$o['agent_id']])): ?> · <span style="color:#d97706"><i class="fas fa-star"></i> <?php echo $ratingMap[(int)$o['agent_id']]['avg']; ?></span><span style="color:var(--p2p-muted);font-size:.6rem"> (<?php echo $ratingMap[(int)$o['agent_id']]['count']; ?>)</span><?php endif; ?></div>
                </div>
            </div>
            <div class="p2p-offer-card-v3-body">
                <div class="p2p-offer-card-v3-cell"><div class="lbl">Coin</div><div class="val"><span class="coin-tag"><?php echo $coinEmoji[$ctype]; ?> <?php echo $coinLabels[$ctype]; ?></span></div></div>
                <div class="p2p-offer-card-v3-cell"><div class="lbl">Price</div><div class="val price">৳<?php echo number_format((float)$o['price_per_coin'],0); ?></div></div>
                <div class="p2p-offer-card-v3-cell"><div class="lbl">Available</div><div class="val"><?php echo number_format((int)$o['remaining']); ?></div></div>
                <div class="p2p-offer-card-v3-cell"><div class="lbl">Limit</div><div class="val"><?php echo (int)$o['min_amount']; ?>-<?php echo (int)$o['max_amount'] ?: '∞'; ?></div></div>
            </div>
            <div class="p2p-offer-card-v3-footer">
                <div class="payment-icons">
                    <img src="assets/images/payment-icon/bkash-logo-mobile-banking.png" alt="bKash">
                    <img src="assets/images/payment-icon/nagad-logo-mobile-banking.png" alt="Nagad">
                    <img src="assets/images/payment-icon/rocket-logo-mobile-banking.png" alt="Rocket">
                </div>
                <span class="action-badge">Buy</span>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (count($sellOffers) >= 50): ?>
    <button class="p2p-convert-btn" id="loadMoreBuyBtn" style="margin-top:6px;font-size:.82rem;padding:10px;background:var(--p2p-card);color:var(--p2p-accent);border:1.5px solid var(--p2p-border);background:none;width:100%" onclick="loadMoreOffers('sell',0)"><i class="fas fa-plus"></i> Load More Offers</button>
    <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ════════════════════════════════════ -->
<!-- ═══ TAB: SELL ═══ -->
<!-- ════════════════════════════════════ -->
<div class="p2p-tab-panel" id="tabSell">
    <div class="p2p-coin-filter">
        <button class="p2p-coin-filter-btn active" data-coin="all">All</button>
        <button class="p2p-coin-filter-btn" data-coin="bronze">Bronze</button>
        <button class="p2p-coin-filter-btn" data-coin="silver">Silver</button>
        <button class="p2p-coin-filter-btn" data-coin="gold">Gold</button>
        <div style="flex:1;min-width:140px;position:relative">
            <input type="text" class="p2p-search-input" placeholder="🔍 Search merchant..." style="width:100%;padding:5px 12px;border-radius:999px;border:1.5px solid var(--p2p-border);font-size:.72rem;font-weight:600;outline:none;font-family:'Plus Jakarta Sans',sans-serif;background:var(--p2p-card);color:var(--p2p-text);box-sizing:border-box;transition:all .15s" oninput="filterOffers(this)">
        </div>
    </div>
    <?php if (empty($buyOffers)): ?>
    <div class="p2p-offer-grid-v3"><div style="text-align:center;padding:3rem;color:var(--p2p-muted)"><i class="fas fa-store" style="font-size:2rem;display:block;margin-bottom:8px;opacity:.3"></i><p style="font-weight:500">No buy offers available</p></div></div>
    <?php else: ?>
    <div class="p2p-offer-grid-v3">
    <?php foreach ($buyOffers as $o):
        $uname = htmlspecialchars($o['full_name'] ?: $o['username']);
        $initial = strtoupper(mb_substr($uname, 0, 1));
        $ctype = $o['coin_type'];
    ?>
    <div class="p2p-offer-card-v3" data-offer-id="<?php echo (int)$o['id']; ?>" data-type="sell" data-coin="<?php echo $ctype; ?>" data-price="<?php echo (float)$o['price_per_coin']; ?>" data-remaining="<?php echo (int)$o['remaining']; ?>" data-min="<?php echo (int)$o['min_amount']; ?>" data-max="<?php echo (int)$o['max_amount']; ?>" data-agent="<?php echo $uname; ?>" data-agent-id="<?php echo (int)$o['agent_id']; ?>">
        <div class="p2p-offer-card-v3-inner">
            <div class="p2p-offer-card-v3-top">
                <div class="p2p-offer-card-v3-avatar" onclick="event.stopPropagation();openMerchantProfile(<?php echo (int)$o['agent_id']; ?>)" style="cursor:pointer">
                    <img src="assets/avatars/<?php echo htmlspecialchars($o['avatar'] ?? 'default.png'); ?>" alt="" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'" style="width:100%;height:100%;border-radius:50%;object-fit:cover">
                    <div style="display:none;width:100%;height:100%;border-radius:50%;background:linear-gradient(135deg,#8b5cf6,#6366f1);color:#fff;align-items:center;justify-content:center;font-size:.9rem;font-weight:800"><?php echo $initial; ?></div>
                </div>
                <div class="p2p-offer-card-v3-user">
                    <div class="name"><a onclick="event.stopPropagation();openMerchantProfile(<?php echo (int)$o['agent_id']; ?>)" style="cursor:pointer;color:inherit;text-decoration:none"><?php echo $uname; ?></a> <span class="verified"><i class="fas fa-check"></i></span></div>
                    <div class="role"><span>Buyer</span> · <span class="orders"><i class="fas fa-check-circle"></i> <?php echo number_format($tradeCountMap[(int)$o['agent_id']] ?? 0); ?> orders</span><?php if (isset($ratingMap[(int)$o['agent_id']])): ?> · <span style="color:#d97706"><i class="fas fa-star"></i> <?php echo $ratingMap[(int)$o['agent_id']]['avg']; ?></span><span style="color:var(--p2p-muted);font-size:.6rem"> (<?php echo $ratingMap[(int)$o['agent_id']]['count']; ?>)</span><?php endif; ?></div>
                </div>
            </div>
            <div class="p2p-offer-card-v3-body">
                <div class="p2p-offer-card-v3-cell"><div class="lbl">Coin</div><div class="val"><span class="coin-tag"><?php echo $coinEmoji[$ctype]; ?> <?php echo $coinLabels[$ctype]; ?></span></div></div>
                <div class="p2p-offer-card-v3-cell"><div class="lbl">Price</div><div class="val price">৳<?php echo number_format((float)$o['price_per_coin'],0); ?></div></div>
                <div class="p2p-offer-card-v3-cell"><div class="lbl">Available</div><div class="val"><?php echo number_format((int)$o['remaining']); ?></div></div>
                <div class="p2p-offer-card-v3-cell"><div class="lbl">Limit</div><div class="val"><?php echo (int)$o['min_amount']; ?>-<?php echo (int)$o['max_amount'] ?: '∞'; ?></div></div>
            </div>
            <div class="p2p-offer-card-v3-footer">
                <div class="payment-icons">
                    <img src="assets/images/payment-icon/bkash-logo-mobile-banking.png" alt="bKash">
                    <img src="assets/images/payment-icon/nagad-logo-mobile-banking.png" alt="Nagad">
                    <img src="assets/images/payment-icon/rocket-logo-mobile-banking.png" alt="Rocket">
                </div>
                <span class="action-badge">Sell</span>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (count($buyOffers) >= 50): ?>
    <button class="p2p-convert-btn" id="loadMoreSellBtn" style="margin-top:6px;font-size:.82rem;padding:10px;background:var(--p2p-card);color:var(--p2p-accent);border:1.5px solid var(--p2p-border);background:none;width:100%" onclick="loadMoreOffers('buy',0)"><i class="fas fa-plus"></i> Load More Offers</button>
    <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ════════════════════════════════════ -->
<!-- ═══ TAB: ORDERS ═══ -->
<!-- ════════════════════════════════════ -->
<div class="p2p-tab-panel" id="tabOrders">
    <div class="p2p-section-card">
    <div class="p2p-section-card-head"><i class="fas fa-clock-rotate-left" style="color:var(--p2p-accent)"></i> My Trades <span style="font-size:.6rem;font-weight:600;color:var(--p2p-muted);margin-left:auto"><?php echo count($myTrades); ?> total</span></div>
    <div class="p2p-section-card-body">
    <div class="p2p-order-filters">
        <div class="p2p-custom-select" style="flex:1;min-width:110px;max-width:150px" id="tradeFilterStatusWrap">
            <div class="p2p-select-trigger"><i class="fas fa-filter"></i> <span>All Status</span></div>
            <div class="p2p-select-options">
                <div class="p2p-select-option active" data-value="all">All Status</div>
                <div class="p2p-select-option" data-value="pending"><span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#f59e0b;margin-right:4px"></span> Pending</div>
                <div class="p2p-select-option" data-value="paid"><span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#3b82f6;margin-right:4px"></span> Paid</div>
                <div class="p2p-select-option" data-value="completed"><span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#059669;margin-right:4px"></span> Completed</div>
                <div class="p2p-select-option" data-value="cancelled"><span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#ef4444;margin-right:4px"></span> Cancelled</div>
                <div class="p2p-select-option" data-value="disputed"><span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#f97316;margin-right:4px"></span> Disputed</div>
            </div>
            <input type="hidden" id="tradeFilterStatus" value="all">
        </div>
        <div class="p2p-custom-select" style="flex:1;min-width:100px;max-width:130px" id="tradeFilterCoinWrap">
            <div class="p2p-select-trigger"><i class="fas fa-coins"></i> <span>All Coins</span></div>
            <div class="p2p-select-options">
                <div class="p2p-select-option active" data-value="all">All Coins</div>
                <div class="p2p-select-option" data-value="bronze">Bronze</div>
                <div class="p2p-select-option" data-value="silver">Silver</div>
                <div class="p2p-select-option" data-value="gold">Gold</div>
            </div>
            <input type="hidden" id="tradeFilterCoin" value="all">
        </div>
        <div class="p2p-date-filter">
            <i class="fas fa-calendar-days"></i>
            <input type="date" id="tradeFilterDate" onchange="applyTradeFilters()" aria-label="Filter trades by date">
        </div>
    </div>
    <?php if (empty($myTrades)): ?>
    <div style="text-align:center;padding:2.5rem;color:var(--p2p-muted);font-size:.85rem"><i class="fas fa-inbox" style="font-size:1.8rem;display:block;margin-bottom:8px;opacity:.3"></i> No trades yet</div>
    <?php else: foreach ($myTrades as $t):
        $isBuyer = (int)$t['buyer_id'] === (int)$viewerId;
        $isSeller = (int)$t['seller_id'] === (int)$viewerId;
        $otherName = $isBuyer ? $t['seller_name'] : $t['buyer_name'];
        $st = $t['status'];
        $stClass = $statusBadge[$st] ?? 'yellow';
        $tradeActions = '';
        // For sell offers (user is buying from merchant): merchant is seller
        // For buy offers (user is selling to merchant): user is seller
        $offerType = $t['offer_type'] ?? 'sell';
        // User is buyer → they need to pay (pending) or wait for release (paid)
        // User is seller → they need to wait for buyer payment (pending) or release coins (paid)
        if ($st === 'pending' && $isBuyer) $tradeActions = '<button class="p2p-btn-primary" onclick="openPaymentForm('.(int)$t['id'].')"><i class="fas fa-credit-card"></i> Pay Now</button>';
        if ($st === 'paid' && $isSeller && $offerType === 'sell') $tradeActions = '<button class="p2p-btn-confirm" onclick="confirmReceived('.(int)$t['id'].')"><i class="fas fa-check"></i> Release Coins</button>';
        if ($st === 'paid' && $isSeller && $offerType === 'buy') $tradeActions = '<button class="p2p-btn-confirm" onclick="confirmReceived('.(int)$t['id'].')"><i class="fas fa-check"></i> Release Coins</button>';
        if ($st === 'paid' && $isBuyer) $tradeActions = '<span style="font-size:.7rem;font-weight:600;color:#d97706"><i class="fas fa-clock"></i> Waiting for seller to release...</span>';
        if ($st === 'pending' || $st === 'paid') $tradeActions .= '<span style="flex:1"></span><button class="p2p-btn-cancel" onclick="cancelOrder('.(int)$t['id'].')"><i class="fas fa-xmark"></i> Cancel</button>';
        if ($st === 'completed' || $st === 'cancelled') $tradeActions .= '<span style="flex:1"></span><button class="p2p-btn-chat" onclick="openTradeChat('.(int)$t['id'].')"><i class="fas fa-comment"></i> Chat</button>';
        if ($st === 'paid' || $st === 'pending') $tradeActions .= '<button class="p2p-btn-report" onclick="disputeTrade('.(int)$t['id'].')" style="background:#ef444415;color:#ef4444;"><i class="fas fa-gavel"></i> Appeal</button>';
        else $tradeActions .= '<button class="p2p-btn-report" onclick="reportTrade('.(int)$t['id'].')"><i class="fas fa-flag"></i></button>';
    ?>
    <div class="p2p-trade-item" data-trade-id="<?php echo (int)$t['id']; ?>" data-status="<?php echo $st; ?>" data-coin="<?php echo $t['coin_type']; ?>" data-created="<?php echo date('Y-m-d', strtotime($t['created_at'])); ?>">
        <div class="p2p-trade-top">
            <div class="p2p-trade-icon <?php echo $offerType === 'buy' ? 'sell' : ''; ?>"><i class="fas <?php echo $isBuyer ? 'fa-cart-shopping' : 'fa-coins'; ?>"></i></div>
            <div class="p2p-trade-meta">
                <div class="p2p-trade-meta-top">
                    <span class="p2p-trade-id">#<?php echo (int)$t['id']; ?></span>
                    <span class="p2p-trade-partner"><span class="avi"><?php echo strtoupper(mb_substr($otherName??'U',0,1)); ?></span> <?php echo htmlspecialchars($otherName??'User'); ?></span>
                    <span class="p2p-trade-status <?php echo $stClass; ?>"><i class="fas <?php echo $st==='completed'?'fa-check-circle':($st==='pending'?'fa-clock':($st==='paid'?'fa-credit-card':($st==='cancelled'?'fa-xmark-circle':'fa-flag'))); ?>"></i> <?php echo ucfirst($st); ?></span>
                    <span class="p2p-trade-time"><i class="fas fa-clock"></i> <?php echo date('M j, g:ia', strtotime($t['created_at'])); ?></span>
                </div>
            </div>
        </div>
        <div class="p2p-trade-detail">
            <span class="chip"><i class="fas fa-coins"></i> <strong><?php echo $coinLabels[$t['coin_type']]??$t['coin_type']; ?></strong></span>
            <span class="chip"><i class="fas fa-cube"></i> Qty: <strong><?php echo (int)$t['quantity']; ?></strong></span>
            <span class="chip"><i class="fas fa-bangladeshi-taka-sign"></i> <strong>৳<?php echo number_format((float)$t['total_price'],0); ?></strong></span>
            <span class="chip"><i class="fas fa-arrow-right-arrow-left"></i> <?php echo $isBuyer ? 'Buy' : 'Sell'; ?></span>
        </div>
        <?php if ($tradeActions): ?>
        <div class="p2p-trade-actions">
            <button class="p2p-btn-chat" onclick="openTradeChat(<?php echo (int)$t['id']; ?>)"><i class="fas fa-comment"></i> Chat</button>
            <?php echo $tradeActions; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; endif; ?>
    <?php if (count($myTrades) >= 30): ?>
    <button class="p2p-convert-btn" id="loadMoreTradesBtn" style="margin-top:10px;font-size:.82rem;padding:10px;background:var(--p2p-card);color:var(--p2p-accent);border:1.5px solid var(--p2p-border);background:none" onclick="loadMoreTrades()"><i class="fas fa-plus"></i> Load More Trades</button>
    <?php endif; ?>
    </div>
    </div>
</div>

<!-- ════════════════════════════════════ -->
<!-- ═══ TAB: CONVERT ═══ -->
<!-- ════════════════════════════════════ -->
<div class="p2p-tab-panel" id="tabConvert">
    <div class="p2p-convert-card">
        <h3><i class="fas fa-arrows-rotate" style="color:var(--p2p-accent)"></i> Convert Coins</h3>
        <div class="p2p-convert-rate">
            <span>1 Gold = 2 Silver = 4 Bronze &nbsp;·&nbsp; Gold=৳100 &nbsp; Silver=৳50 &nbsp; Bronze=৳25</span>
        </div>
        <div class="p2p-convert-grid" style="display:grid;grid-template-columns:1fr auto 1fr;align-items:center;gap:12px;margin-bottom:16px">
            <div class="p2p-custom-select" id="selectConvFrom">
                <div class="p2p-select-trigger" data-value="bronze">
                    <span>Bronze</span>

                </div>
                <div class="p2p-select-options">
                    <div class="p2p-select-option active" data-value="bronze">Bronze</div>
                    <div class="p2p-select-option" data-value="silver">Silver</div>
                    <div class="p2p-select-option" data-value="gold">Gold</div>
                </div>
                <input type="hidden" id="convFrom" value="bronze">
            </div>

            <button class="p2p-convert-swap" id="convSwap" style="width:40px;height:40px;border-radius:50%;border:1px solid var(--p2p-border);background:var(--p2p-card);color:var(--p2p-accent);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .2s">
                <i class="fas fa-arrow-right-arrow-left"></i>
            </button>

            <div class="p2p-custom-select" id="selectConvTo">
                <div class="p2p-select-trigger" data-value="gold">
                    <span>Gold</span>

                </div>
                <div class="p2p-select-options">
                    <div class="p2p-select-option" data-value="bronze">Bronze</div>
                    <div class="p2p-select-option" data-value="silver">Silver</div>
                    <div class="p2p-select-option active" data-value="gold">Gold</div>
                </div>
                <input type="hidden" id="convTo" value="gold">
            </div>
        </div>
        <div style="margin:10px 0">
            <input type="number" id="convQty" min="1" placeholder="Enter quantity" style="width:100%;padding:10px 12px;border-radius:12px;border:2px solid var(--p2p-border);font-size:.9rem;font-weight:700;outline:none;box-sizing:border-box;font-family:'Plus Jakarta Sans',sans-serif;background:var(--p2p-card);color:var(--p2p-text)" class="p2p-convert-input">
        </div>
        <div id="convResult" style="font-size:.78rem;color:var(--p2p-muted);text-align:center;margin-bottom:10px;min-height:20px"></div>
        <button class="p2p-convert-btn" id="convBtn"><i class="fas fa-arrows-rotate"></i> Convert</button>
        <div class="p2p-fb" id="convFb"></div>
    </div>
    <div style="font-size:.7rem;color:var(--p2p-muted);text-align:center;padding:8px">
        <i class="fas fa-info-circle"></i> Conversion uses the value system: Bronze=৳25, Silver=৳50, Gold=৳100. Any leftover value is forfeited (no fractional coins).
    </div>
</div>

<!-- ════════════════════════════════════ -->
<!-- ═══ TAB: OFFERS (agent) ═══ -->
<!-- ════════════════════════════════════ -->
<div class="p2p-tab-panel" id="tabOffers">
    <?php if ($userRole === 'merchant' || $userRole === 'admin'): ?>
    <div class="p2p-section-card">
        <div class="p2p-section-card-head"><i class="fas fa-circle-plus" style="color:#10b981"></i> Create Offer</div>
        <form id="p2pCreateForm" class="p2p-section-card-body">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px">
                <div>
                    <label style="font-size:.72rem;font-weight:700;color:var(--p2p-muted);display:block;margin-bottom:4px">Type</label>
                    <div class="p2p-custom-select" id="selectOfferType">
                        <div class="p2p-select-trigger" data-value="sell">
                            <span>Sell</span>
        
                        </div>
                        <div class="p2p-select-options">
                            <div class="p2p-select-option active" data-value="sell">Sell</div>
                            <div class="p2p-select-option" data-value="buy">Buy</div>
                        </div>
                        <input type="hidden" id="p2pOfferType" value="sell">
                    </div>
                </div>
                <div>
                    <label style="font-size:.72rem;font-weight:700;color:var(--p2p-muted);display:block;margin-bottom:4px">Coin</label>
                    <div class="p2p-custom-select" id="selectOfferCoin">
                        <div class="p2p-select-trigger" data-value="bronze">
                            <span>Bronze</span>
        
                        </div>
                        <div class="p2p-select-options">
                            <div class="p2p-select-option active" data-value="bronze">Bronze</div>
                            <div class="p2p-select-option" data-value="silver">Silver</div>
                            <div class="p2p-select-option" data-value="gold">Gold</div>
                        </div>
                        <input type="hidden" id="p2pOfferCoin" value="bronze">
                    </div>
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px">
                <div><label style="font-size:.72rem;font-weight:700;color:var(--p2p-muted);display:block;margin-bottom:4px">Price per coin (৳)</label><input type="number" id="p2pOfferPrice" style="width:100%;padding:10px 12px;border-radius:12px;border:2px solid var(--p2p-border);font-size:.82rem;font-weight:600;outline:none;box-sizing:border-box;font-family:'Plus Jakarta Sans',sans-serif;background:var(--p2p-card);color:var(--p2p-text)" min="1" required></div>
                <div><label style="font-size:.72rem;font-weight:700;color:var(--p2p-muted);display:block;margin-bottom:4px">Quantity</label><input type="number" id="p2pOfferQty" style="width:100%;padding:10px 12px;border-radius:12px;border:2px solid var(--p2p-border);font-size:.82rem;font-weight:600;outline:none;box-sizing:border-box;font-family:'Plus Jakarta Sans',sans-serif;background:var(--p2p-card);color:var(--p2p-text)" min="1" required></div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px">
                <div><label style="font-size:.72rem;font-weight:700;color:var(--p2p-muted);display:block;margin-bottom:4px">Min per trade</label><input type="number" id="p2pOfferMin" value="1" min="1" style="width:100%;padding:10px 12px;border-radius:12px;border:2px solid var(--p2p-border);font-size:.82rem;font-weight:600;outline:none;box-sizing:border-box;font-family:'Plus Jakarta Sans',sans-serif;background:var(--p2p-card);color:var(--p2p-text)"></div>
                <div><label style="font-size:.72rem;font-weight:700;color:var(--p2p-muted);display:block;margin-bottom:4px">Max (0=∞)</label><input type="number" id="p2pOfferMax" value="0" min="0" style="width:100%;padding:10px 12px;border-radius:12px;border:2px solid var(--p2p-border);font-size:.82rem;font-weight:600;outline:none;box-sizing:border-box;font-family:'Plus Jakarta Sans',sans-serif;background:var(--p2p-card);color:var(--p2p-text)"></div>
            </div>
            <button type="submit" class="p2p-convert-btn" id="p2pCreateBtn"><i class="fas fa-check"></i> Publish Offer</button>
            <div class="p2p-fb" id="p2pCreateFb"></div>
        </form>
    </div>
    <?php else: ?>
    <div class="p2p-section-card">
        <div class="p2p-section-card-head"><i class="fas fa-lock" style="color:var(--p2p-muted)"></i> Create Offer</div>
        <div class="p2p-section-card-body" style="text-align:center;padding:2rem">
            <i class="fas fa-store-slash" style="font-size:2rem;color:var(--p2p-muted);opacity:.3;display:block;margin-bottom:8px"></i>
            <p style="font-size:.85rem;font-weight:600;color:var(--p2p-muted);margin:0">Only verified merchants can create P2P offers.</p>
            <p style="font-size:.75rem;color:var(--p2p-muted);margin:6px 0 0">Contact admin to become a verified merchant.</p>
        </div>
    </div>
    <?php endif; ?>
    <div class="p2p-section-card">
    <div class="p2p-section-card-head"><i class="fas fa-tag" style="color:var(--p2p-accent)"></i> My Offers</div>
    <div class="p2p-section-card-body">
    <div class="p2p-myoffer-list">
        <?php if (empty($myOffers)): ?>
        <div style="text-align:center;padding:2rem;color:var(--p2p-muted)"><i class="fas fa-tag" style="font-size:1.5rem;display:block;margin-bottom:6px;opacity:.3"></i><p style="font-size:.82rem">No offers created yet</p></div>
        <?php else: foreach ($myOffers as $o): ?>
        <div class="p2p-myoffer-item">
            <div class="info">
                <strong><span style="color:<?php echo $o['coin_type']==='gold'?'#d97706':($o['coin_type']==='silver'?'#6b7280':'#b45309'); ?>"><?php echo $coinEmoji[$o['coin_type']]; ?> <?php echo $coinLabels[$o['coin_type']]; ?></span></strong>
                <span><?php echo ucfirst($o['type']); ?> @ ৳<?php echo number_format((float)$o['price_per_coin'],0); ?></span>
            </div>
            <div class="meta">
                <span class="remain"><?php echo number_format((int)$o['remaining']); ?>/<?php echo number_format((int)$o['quantity']); ?></span>
                <span class="st <?php echo $o['status']; ?>"><?php echo ucfirst($o['status']); ?></span>
                <?php if ($o['status']==='active'): ?>
                <button onclick="editOffer(<?php echo (int)$o['id']; ?>,<?php echo (float)$o['price_per_coin']; ?>,<?php echo (int)$o['min_amount']; ?>,<?php echo (int)$o['max_amount']; ?>)" class="p2p-btn-chat" style="width:26px;height:26px;border-radius:50%;border:0;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:11px;padding:0"><i class="fas fa-pen"></i></button>
                <button onclick="cancelOffer(<?php echo (int)$o['id']; ?>)" class="p2p-btn-cancel" style="width:26px;height:26px;border-radius:50%;border:0;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:11px;padding:0"><i class="fas fa-xmark"></i></button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>
    </div>
</div>
</div>

<!-- ════════════════════════════════════ -->
<!-- ═══ TAB: PAYMENT SETTINGS ═══ -->
<!-- ════════════════════════════════════ -->
<div class="p2p-tab-panel" id="tabSettings">
    <div class="p2p-section-card">
        <div class="p2p-section-card-head"><i class="fas fa-gear" style="color:var(--p2p-accent)"></i> Payment Settings</div>
        <div class="p2p-section-card-body">
            <p style="font-size:.75rem;color:var(--p2p-muted);margin:0 0 14px;line-height:1.5">
                <i class="fas fa-info-circle"></i> 
                Configure your payment methods below. Buyers will see these when they place an order with you.
            </p>
            <div id="paySettingsContainer">
                <?php
                $methods = ['bkash' => ['label'=>'bKash','color'=>'#E2136E'], 'nagad' => ['label'=>'Nagad','color'=>'#E8522E'], 'rocket' => ['label'=>'Rocket','color'=>'#CC0000']];
                $psIndex = [];
                foreach ($paymentSettings as $ps) { $psIndex[$ps['method']] = $ps; }
                foreach ($methods as $m => $minfo):
                    $num = isset($psIndex[$m]) ? $psIndex[$m]['number'] : '';
                    $inst = isset($psIndex[$m]) ? $psIndex[$m]['instruction'] : 'send_money';
                    $configured = $num !== '';
                ?>
                <div style="background:var(--p2p-card);border:1px solid var(--p2p-border);border-radius:14px;padding:14px 16px;margin-bottom:10px;transition:all .15s">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
                        <i class="fas fa-circle" style="color:<?php echo $configured ? '#059669' : '#d1d5db'; ?>;font-size:.5rem"></i>
                        <strong style="color:<?php echo $minfo['color']; ?>;font-size:.85rem"><?php echo $minfo['label']; ?></strong>
                        <?php if ($configured): ?>
                        <span style="margin-left:auto;font-size:.6rem;font-weight:700;color:#059669;background:#d1fae5;padding:2px 8px;border-radius:999px">
                            <i class="fas fa-check"></i> Configured
                        </span>
                        <?php else: ?>
                        <span style="margin-left:auto;font-size:.6rem;font-weight:700;color:var(--p2p-muted);background:#f3f4f6;padding:2px 8px;border-radius:999px">
                            <i class="fas fa-plus"></i> Not Set
                        </span>
                        <?php endif; ?>
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <input type="tel" class="payMethodInput" data-method="<?php echo $m; ?>" data-field="number" placeholder="Merchant number (01XXXXXXXXX)" value="<?php echo htmlspecialchars($num); ?>"
                            style="flex:1;min-width:160px;padding:10px 12px;border-radius:10px;border:2px solid var(--p2p-border);font-size:.78rem;font-weight:600;outline:none;font-family:'Plus Jakarta Sans',sans-serif;background:var(--p2p-card);color:var(--p2p-text);box-sizing:border-box;transition:border-color .2s">
                        <div class="p2p-custom-select payInstrSelect" data-method="<?php echo $m; ?>" style="min-width:130px">
                            <div class="p2p-select-trigger payInstrTrigger" data-value="<?php echo $inst; ?>">
                                <span><?php echo $inst==='send_money' ? 'Send Money' : 'Cash Out'; ?></span>
                            </div>
                            <div class="p2p-select-options">
                                <div class="p2p-select-option <?php echo $inst==='send_money'?'active':''; ?>" data-value="send_money" style="color:#059669;font-weight:700">Send Money</div>
                                <div class="p2p-select-option <?php echo $inst==='cashout'?'active':''; ?>" data-value="cashout" style="color:#dc2626;font-weight:700">Cash Out</div>
                            </div>
                            <input type="hidden" class="payInstrInput" data-method="<?php echo $m; ?>" value="<?php echo $inst; ?>">
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <button class="p2p-convert-btn" id="savePaySettings" style="margin-top:4px"><i class="fas fa-save"></i> Save Payment Methods</button>
                <div class="p2p-fb" id="paySettingsFb"></div>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>
</div>

<!-- ════════════════════════════════════ -->
<!-- ═══ OFFER DETAIL + ORDER MODAL ═══ -->
<!-- ════════════════════════════════════ -->
<div class="p2p-detail-overlay" id="offerDetailOverlay">
    <div class="p2p-detail-panel p2p-detail-v3" id="offerDetailPanel">
        <div class="p2p-detail-head">
            <h3 id="detailTitle">Offer Details</h3>
            <button class="p2p-detail-close" onclick="closeDetail()"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="p2p-detail-body" id="detailBody"></div>
    </div>
</div>

<!-- ════════════════════════════════════ -->
<!-- ═══ CHAT MODAL — Redesigned ═══ -->
<!-- ════════════════════════════════════ -->
<div class="p2p-detail-overlay" id="chatOverlay">
    <div class="p2p-detail-panel" style="max-width:420px;max-height:min(92vh,700px);padding:0;background:var(--p2p-card);overflow:hidden">
        <div class="p2p-chat-modal">
            <div class="p2p-chat-top" id="chatTop">
                <div class="p2p-chat-top-avatar" id="chatTopAvatar">?</div>
                <div class="p2p-chat-top-info">
                    <div class="p2p-chat-top-name" id="chatTopName">Trade Partner</div>
                    <div class="p2p-chat-top-sub" id="chatTopSub">#<span id="chatTradeId">0</span></div>
                </div>
                <button class="p2p-detail-close" onclick="closeChat()" style="position:static"><i class="fas fa-xmark"></i></button>
            </div>
            <div class="p2p-chat-msgs" id="chatMessages">
                <div class="p2p-chat-empty" id="chatEmpty">
                    <i class="fas fa-comment-dots"></i>
                    <p>No messages yet. Say hello!</p>
                </div>
            </div>
            <div class="p2p-chat-input-wrap">
                <input type="file" id="chatImageInput" accept="image/png, image/jpeg" style="display:none" onchange="uploadChatImage()">
                <button class="attach-btn" id="chatAttachBtn" onclick="document.getElementById('chatImageInput').click()"><i class="fas fa-paperclip"></i></button>
                <input type="text" id="chatInput" placeholder="Type a message..." maxlength="500">
                <button class="send-btn" id="chatSendBtn"><i class="fas fa-paper-plane"></i></button>
            </div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════ -->
<!-- ═══ REPORT MODAL ═══ -->
<!-- ════════════════════════════════════ -->
<div class="p2p-detail-overlay" id="reportOverlay">
    <div class="p2p-detail-panel" style="max-width:400px">
        <div class="p2p-detail-head">
            <h3 id="reportTitle"><i class="fas fa-flag" style="color:#dc2626"></i> Report / Dispute Trade</h3>
            <button class="p2p-detail-close" onclick="document.getElementById('reportOverlay').style.display='none';document.body.style.overflow=''"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="p2p-detail-body" id="reportBody">
            <p style="font-size:.82rem;color:var(--p2p-muted);margin-bottom:12px">Report an issue with this trade. Admin will review and take action.</p>
            <div style="margin-bottom:12px">
                <label style="font-size:.72rem;font-weight:700;color:var(--p2p-muted);display:block;margin-bottom:4px">Reason</label>
                <div class="p2p-custom-select" id="reportReasonSelect">
                    <div class="p2p-select-trigger">
                        <span>Scam / Fraud</span>
                    </div>
                    <div class="p2p-select-options">
                        <div class="p2p-select-option active" data-value="scam">Scam / Fraud</div>
                        <div class="p2p-select-option" data-value="no_payment">Buyer didn't pay</div>
                        <div class="p2p-select-option" data-value="no_release">Seller didn't release coins</div>
                        <div class="p2p-select-option" data-value="wrong_amount">Wrong amount sent</div>
                        <div class="p2p-select-option" data-value="other">Other</div>
                    </div>
                    <input type="hidden" id="reportReason" value="scam">
                </div>
            </div>
            <div style="margin-bottom:14px">
                <label style="font-size:.72rem;font-weight:700;color:var(--p2p-muted);display:block;margin-bottom:4px">Details</label>
                <textarea id="reportDetails" style="width:100%;padding:10px 12px;border-radius:12px;border:2px solid var(--p2p-border);font-size:.82rem;outline:none;box-sizing:border-box;font-family:'Plus Jakarta Sans',sans-serif;background:var(--p2p-card);color:var(--p2p-text);resize:vertical" rows="3" placeholder="Describe the issue..."></textarea>
            </div>
            <input type="hidden" id="reportMode" value="report">
            <button class="p2p-btn-submit-report" id="reportSubmitBtn" onclick="submitReport()"><i class="fas fa-paper-plane"></i> Submit</button>
            <div class="p2p-fb" id="reportFb"></div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════ -->
<!-- ═══ EDIT OFFER MODAL ═══ -->
<!-- ════════════════════════════════════ -->
<div class="p2p-detail-overlay" id="editOfferOverlay">
    <div class="p2p-detail-panel" style="max-width:400px">
        <div class="p2p-detail-head">
            <h3><i class="fas fa-pen" style="color:var(--p2p-accent)"></i> Edit Offer</h3>
            <button class="p2p-detail-close" onclick="document.getElementById('editOfferOverlay').style.display='none';document.body.style.overflow=''"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="p2p-detail-body">
            <input type="hidden" id="editOfferId">
            <div style="margin-bottom:12px">
                <label style="font-size:.72rem;font-weight:700;color:var(--p2p-muted);display:block;margin-bottom:4px">Price per coin (৳)</label>
                <input type="number" id="editOfferPrice" min="1" style="width:100%;padding:10px 12px;border-radius:12px;border:2px solid var(--p2p-border);font-size:.85rem;font-weight:600;outline:none;box-sizing:border-box;font-family:'Plus Jakarta Sans',sans-serif;background:var(--p2p-card);color:var(--p2p-text)">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px">
                <div>
                    <label style="font-size:.72rem;font-weight:700;color:var(--p2p-muted);display:block;margin-bottom:4px">Min per trade</label>
                    <input type="number" id="editOfferMin" min="1" style="width:100%;padding:10px 12px;border-radius:12px;border:2px solid var(--p2p-border);font-size:.82rem;font-weight:600;outline:none;box-sizing:border-box;font-family:'Plus Jakarta Sans',sans-serif;background:var(--p2p-card);color:var(--p2p-text)">
                </div>
                <div>
                    <label style="font-size:.72rem;font-weight:700;color:var(--p2p-muted);display:block;margin-bottom:4px">Max (0=∞)</label>
                    <input type="number" id="editOfferMax" min="0" style="width:100%;padding:10px 12px;border-radius:12px;border:2px solid var(--p2p-border);font-size:.82rem;font-weight:600;outline:none;box-sizing:border-box;font-family:'Plus Jakarta Sans',sans-serif;background:var(--p2p-card);color:var(--p2p-text)">
                </div>
            </div>
            <button class="p2p-convert-btn" id="editOfferBtn"><i class="fas fa-save"></i> Update Offer</button>
            <div class="p2p-fb" id="editOfferFb"></div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════ -->
<!-- ═══ REVIEW MODAL ═══ -->
<!-- ════════════════════════════════════ -->
<div class="p2p-detail-overlay" id="reviewOverlay">
    <div class="p2p-detail-panel" style="max-width:380px">
        <div class="p2p-detail-head">
            <h3><i class="fas fa-star" style="color:#d97706"></i> Rate Merchant</h3>
            <button class="p2p-detail-close" onclick="document.getElementById('reviewOverlay').style.display='none';document.body.style.overflow=''"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="p2p-detail-body" style="text-align:center">
            <p style="font-size:.85rem;color:var(--p2p-muted);margin:0 0 16px">How was your trading experience?</p>
            <input type="hidden" id="reviewTradeId">
            <div id="starRating" style="font-size:2rem;margin-bottom:12px;cursor:pointer;user-select:none">
                <span data-star="1" onclick="setRating(1)" style="color:#d1d5db">★</span>
                <span data-star="2" onclick="setRating(2)" style="color:#d1d5db">★</span>
                <span data-star="3" onclick="setRating(3)" style="color:#d1d5db">★</span>
                <span data-star="4" onclick="setRating(4)" style="color:#d1d5db">★</span>
                <span data-star="5" onclick="setRating(5)" style="color:#d1d5db">★</span>
            </div>
            <input type="hidden" id="reviewRating" value="0">
            <textarea id="reviewComment" placeholder="Share your experience (optional)" style="width:100%;padding:10px 12px;border-radius:12px;border:2px solid var(--p2p-border);font-size:.82rem;outline:none;box-sizing:border-box;font-family:'Plus Jakarta Sans',sans-serif;background:var(--p2p-card);color:var(--p2p-text);resize:none" rows="2"></textarea>
            <button class="p2p-convert-btn" id="reviewBtn" style="margin-top:12px"><i class="fas fa-paper-plane"></i> Submit Review</button>
            <div class="p2p-fb" id="reviewFb"></div>
            <button onclick="document.getElementById('reviewOverlay').style.display='none';document.body.style.overflow=''" style="margin-top:8px;background:none;border:none;font-size:.75rem;color:var(--p2p-muted);cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif">Skip</button>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════ -->
<!-- ═══ MERCHANT PROFILE MODAL ═══ -->
<!-- ════════════════════════════════════ -->
<div class="p2p-detail-overlay" id="merchantProfileOverlay">
    <div class="p2p-detail-panel" style="max-width:460px">
        <div class="p2p-detail-head">
            <h3><i class="fas fa-store" style="color:var(--p2p-accent)"></i> Merchant Profile</h3>
            <button class="p2p-detail-close" onclick="document.getElementById('merchantProfileOverlay').style.display='none';document.body.style.overflow=''"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="p2p-detail-body" id="merchantProfileBody">
            <div style="text-align:center;padding:20px" id="merchantProfileLoading"><i class="fas fa-spinner fa-spin" style="font-size:1.5rem;color:var(--p2p-muted)"></i></div>
        </div>
    </div>
</div>

<script>
(function(){
    // ═══ CUSTOM SELECT LOGIC ═══
    function initAllCustomSelects(parent) {
        var root = parent || document;
        root.querySelectorAll('.p2p-custom-select').forEach(function(container) {
            var trigger = container.querySelector('.p2p-select-trigger');
            var input = container.querySelector('input[type="hidden"]');
            if (container._bound) return;
            container._bound = true;

            trigger.addEventListener('click', function(e) {
                e.stopPropagation();
                document.querySelectorAll('.p2p-custom-select').forEach(function(c) { if (c !== container) c.classList.remove('active'); });
                container.classList.toggle('active');
            });

            container.querySelectorAll('.p2p-select-option').forEach(function(opt) {
                opt.addEventListener('click', function(e) {
                    e.stopPropagation();
                    var val = opt.getAttribute('data-value');
                    input.value = val;
                    trigger.querySelector('span').innerHTML = opt.innerHTML;
                    
                    container.querySelectorAll('.p2p-select-option').forEach(function(o) { o.classList.remove('active'); });
                    opt.classList.add('active');
                    container.classList.remove('active');
                    
                    if (container.id === 'selectConvFrom' || container.id === 'selectConvTo') updateConvPreview();
                    if (container.id === 'tradeFilterStatusWrap' || container.id === 'tradeFilterCoinWrap') applyTradeFilters();
                });
            });
        });
    }
    initAllCustomSelects();

    document.addEventListener('click', function() {
        document.querySelectorAll('.p2p-custom-select').forEach(function(c) { c.classList.remove('active'); });
    });

    // ═══ FORM & TRADE LOGIC ═══
    var currentReportTradeId = 0;
    
    window.disputeTrade = function(tradeId) {
        currentReportTradeId = tradeId;
        document.getElementById('reportMode').value = 'dispute';
        document.getElementById('reportTitle').innerHTML = '<i class="fas fa-gavel" style="color:#dc2626"></i> Appeal / Dispute Trade';
        var over = document.getElementById('reportOverlay');
        over.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        
        var input = document.getElementById('reportReason');
        if (input) input.value = 'scam';
        var trigger = document.querySelector('#reportReasonSelect .p2p-select-trigger span');
        if (trigger) trigger.textContent = 'Scam / Fraud';
        document.querySelectorAll('#reportReasonSelect .p2p-select-option').forEach(function(o){
            o.classList.toggle('active', o.getAttribute('data-value') === 'scam');
        });
        document.getElementById('reportDetails').value = '';
        hideFb('reportFb');
        initAllCustomSelects(over);
    };
    
    window.reportTrade = function(tradeId) {
        currentReportTradeId = tradeId;
        document.getElementById('reportMode').value = 'report';
        document.getElementById('reportTitle').innerHTML = '<i class="fas fa-flag" style="color:#dc2626"></i> Report Trade';
        var over = document.getElementById('reportOverlay');
        over.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        
        // Reset report select
        var input = document.getElementById('reportReason');
        if (input) input.value = 'scam';
        var trigger = document.querySelector('#reportReasonSelect .p2p-select-trigger span');
        if (trigger) trigger.textContent = 'Scam / Fraud';
        document.querySelectorAll('#reportReasonSelect .p2p-select-option').forEach(function(o){
            o.classList.toggle('active', o.getAttribute('data-value') === 'scam');
        });
        
        document.getElementById('reportDetails').value = '';
        hideFb('reportFb');
        initAllCustomSelects(over);
    };
    window.submitReport = function() {
        var reason = document.getElementById('reportReason').value;
        var details = document.getElementById('reportDetails').value.trim();
        var mode = document.getElementById('reportMode').value;
        var actionName = mode === 'dispute' ? 'file_dispute' : 'report_p2p_trade';
        if (!currentReportTradeId) return;
        var btn = document.getElementById('reportSubmitBtn');
        btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
        fetch('handlers/p2p_handler.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json','X-Requested-With': 'XMLHttpRequest'},
            body: JSON.stringify({action: actionName, trade_id: currentReportTradeId, reason: reason, details: details, csrf_token: csrfToken})
        })
        .then(function(r){ return r.json(); })
        .then(function(res){
            if (res.success) {
                showFb('reportFb', res.message, 'success');
                setTimeout(function(){ document.getElementById('reportOverlay').style.display='none'; document.body.style.overflow=''; location.reload(); }, 1500);
            } else {
                showFb('reportFb', res.message, 'error');
                btn.disabled = false; btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Report';
            }
        })
        .catch(function(){ showFb('reportFb', 'Network error.', 'error'); btn.disabled = false; btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Report'; });
    };

    // ═══ OPEN PAYMENT FORM (from Orders tab - Pay Now) ═══
    window.openPaymentForm = function(tradeId) {
        fetch('handlers/p2p_handler.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json','X-Requested-With': 'XMLHttpRequest'},
            body: JSON.stringify({action: 'get_p2p_trade', trade_id: tradeId, csrf_token: csrfToken})
        })
        .then(function(r){ return r.json(); })
        .then(function(res){
            if (requestToken !== chatRequestToken || currentChatTradeId != tradeId) return;
            if (res.success && res.trade) {
                var t = res.trade;
                document.getElementById('offerDetailOverlay').style.display = 'flex';
                document.body.style.overflow = 'hidden';
                showPaymentForm(t.id, res.payment_settings, parseFloat(t.total_price), t.coin_type, parseInt(t.quantity), t.offer_type === 'sell' ? 'buy' : 'sell', t.offer_type);
            }
        });
    };

    // ═══ PRICE CHART ═══ (removed – was fake random data)

    var csrfToken = document.getElementById('p2pPage')?.getAttribute('data-csrf') || '';
    var userId = parseInt(document.getElementById('p2pPage')?.getAttribute('data-user-id') || '0');
    var userRole = document.getElementById('p2pPage')?.getAttribute('data-role') || 'user';
    var currentChatTradeId = null;
    var chatInterval = null;
    var chatRequestToken = 0;

    // ═══ TABS ═══
    document.querySelectorAll('.p2p-tabbar-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            document.querySelectorAll('.p2p-tabbar-btn').forEach(function(b){ b.classList.remove('active'); });
            document.querySelectorAll('.p2p-tab-panel').forEach(function(p){ p.classList.remove('active'); });
            this.classList.add('active');
            var target = document.getElementById('tab' + this.getAttribute('data-tab').charAt(0).toUpperCase() + this.getAttribute('data-tab').slice(1));
            if (target) target.classList.add('active');
            // Reset coin filters when switching tabs
            target && target.querySelectorAll('.p2p-coin-filter-btn').forEach(function(b,i){ b.classList.toggle('active', i===0); });
            target && target.querySelectorAll('.p2p-offer-card-v3').forEach(function(c){ c.style.display=''; });
        });
    });

    // ═══ COIN FILTER ═══
    document.querySelectorAll('.p2p-coin-filter-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var panel = this.closest('.p2p-tab-panel');
            var coin = this.getAttribute('data-coin');
            this.closest('.p2p-coin-filter').querySelectorAll('.p2p-coin-filter-btn').forEach(function(b){ b.classList.remove('active'); });
            this.classList.add('active');
            panel.querySelectorAll('.p2p-offer-card-v3').forEach(function(card) {
                card.style.display = (coin === 'all' || card.getAttribute('data-coin') === coin) ? '' : 'none';
            });
        });
    });

    // ═══ OFFER CARDS → CLICK → DETAIL MODAL ═══
    document.querySelectorAll('.p2p-offer-card-v3').forEach(function(card){
        card.addEventListener('click', function(){
            var offerId = this.getAttribute('data-offer-id');
            var type = this.getAttribute('data-type');
            var coin = this.getAttribute('data-coin');
            var price = parseFloat(this.getAttribute('data-price'));
            var remaining = parseInt(this.getAttribute('data-remaining'));
            var minAmt = parseInt(this.getAttribute('data-min'));
            var maxAmt = parseInt(this.getAttribute('data-max'));
            var agent = this.getAttribute('data-agent');
            var agentId = parseInt(this.getAttribute('data-agent-id'));
            showOfferDetail(offerId, type, coin, price, remaining, minAmt, maxAmt, agent, agentId);
        });
    });

    function showOfferDetail(offerId, type, coin, price, remaining, minAmt, maxAmt, agent, agentId) {
        var coinLabels = {bronze:'Bronze',silver:'Silver',gold:'Gold'};
        var coinIcons = {bronze:'🥉',silver:'🥈',gold:'🥇'};
        var actionLabel = type === 'buy' ? 'Buy' : 'Sell';
        var over = document.getElementById('offerDetailOverlay');
        var panel = document.getElementById('detailBody');
        document.getElementById('detailTitle').innerHTML = (type === 'buy' ? 'Buy' : 'Sell') + ' ' + coinIcons[coin] + ' ' + coinLabels[coin];

        panel.innerHTML =
            '<div class="seller-card">' +
                '<div class="avatar">' + agent.charAt(0).toUpperCase() + '</div>' +
                '<div class="info"><div class="name">' + agent + '</div><div class="stats">' + actionLabel + ' Offer</div></div>' +
            '</div>' +
            '<div class="info-grid">' +
                '<div class="box"><div class="lbl">Coin</div><div class="val">' + coinIcons[coin] + ' ' + coinLabels[coin] + '</div></div>' +
                '<div class="box"><div class="lbl">Price</div><div class="val green">৳' + price.toFixed(0) + '</div></div>' +
                '<div class="box"><div class="lbl">Available</div><div class="val">' + remaining + '</div></div>' +
                '<div class="box"><div class="lbl">Limits</div><div class="val">' + minAmt + ' - ' + (maxAmt || '∞') + '</div></div>' +
            '</div>' +
            '<div class="qty-presets">';
        // Preset buttons
        var presets = [minAmt, minAmt*5, minAmt*10, minAmt*25];
        if (maxAmt > 0) presets = presets.filter(function(p){ return p <= maxAmt; });
        presets = presets.filter(function(p,i,a){ return a.indexOf(p)===i; });
        presets.forEach(function(p){
            if (p <= remaining) panel.innerHTML += '<button type="button" onclick="document.getElementById(\'orderQty\').value=\''+p+'\';updateOrderTotal('+price+','+remaining+','+minAmt+','+(maxAmt||0)+')">' + p + '</button>';
        });
        panel.innerHTML += '</div>' +
            '<input type="number" class="qty-input" id="orderQty" value="' + minAmt + '" min="' + minAmt + '" max="' + (maxAmt||remaining) + '" oninput="updateOrderTotal('+price+','+remaining+','+minAmt+','+(maxAmt||0)+')">' +
            '<div class="total-display" id="orderTotal">Total: ৳' + (price * minAmt).toFixed(0) + ' <span class="sub">Including all fees</span></div>' +
            '<button class="action-btn ' + type + '" id="placeOrderBtn" onclick="placeOrder(' + offerId + ',\'' + type + '\',' + price + ',' + remaining + ',' + minAmt + ',' + (maxAmt||0) + ')"><i class="fas fa-shopping-cart"></i> ' + actionLabel + ' Now</button>' +
            '<div class="p2p-fb" id="orderFb"></div>';

        over.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    window.updateOrderTotal = function(price, remaining, minAmt, maxAmt) {
        var qty = parseInt(document.getElementById('orderQty').value) || 0;
        if (qty < minAmt) qty = minAmt;
        if (maxAmt > 0 && qty > maxAmt) qty = maxAmt;
        if (qty > remaining) qty = remaining;
        document.getElementById('orderTotal').textContent = 'Total: ৳' + (price * qty).toFixed(0);
    };

    window.closeDetail = function() {
        document.getElementById('offerDetailOverlay').style.display = 'none';
        document.body.style.overflow = '';
    };
    document.getElementById('offerDetailOverlay').addEventListener('click', function(e) {
        if (e.target === this) closeDetail();
    });

    // ═══ PLACE ORDER ═══
    window.placeOrder = function(offerId, type, price, remaining, minAmt, maxAmt) {
        var qty = parseInt(document.getElementById('orderQty').value) || 0;
        if (qty < minAmt) qty = minAmt;
        if (maxAmt > 0 && qty > maxAmt) qty = maxAmt;
        if (qty > remaining) qty = remaining;
        if (qty < 1) { showFb('orderFb', 'Invalid quantity.', 'error'); return; }

        var btn = document.getElementById('placeOrderBtn');
        btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Placing order...';
        hideFb('orderFb');

        fetch('handlers/p2p_handler.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json','X-Requested-With': 'XMLHttpRequest'},
            body: JSON.stringify({action: 'place_p2p_order', offer_id: offerId, quantity: qty, csrf_token: csrfToken})
        })
        .then(function(r){ return r.json(); })
        .then(function(res){
            if (res.success) {
                // If user is selling (merchant has buy offer), show different screen
                if (res.offer_type === 'buy') {
                    showOrderPlacedSeller(res.trade_id, res.total_price, res.coin_type, res.quantity);
                } else {
                    // User is buying → show merchant payment details
                    fetch('handlers/p2p_handler.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json','X-Requested-With': 'XMLHttpRequest'},
                        body: JSON.stringify({action: 'get_merchant_payment_details', merchant_id: res.agent_id, csrf_token: csrfToken})
                    })
                    .then(function(r2){ return r2.json(); })
                    .then(function(res2){
                        var paySettings = res2.success ? res2.payment_settings : [];
                        showPaymentForm(res.trade_id, paySettings, res.total_price, res.coin_type, res.quantity, type, res.offer_type);
                    })
                    .catch(function(){
                        showPaymentForm(res.trade_id, [], res.total_price, res.coin_type, res.quantity, type, res.offer_type);
                    });
                }
            } else {
                showFb('orderFb', res.message, 'error');
                btn.disabled = false; btn.innerHTML = '<i class="fas fa-shopping-cart"></i> ' + (type === 'buy' ? 'Buy' : 'Sell') + ' Now';
            }
        })
        .catch(function(){
            showFb('orderFb', 'Network error.', 'error');
            btn.disabled = false; btn.innerHTML = '<i class="fas fa-shopping-cart"></i> ' + (type === 'buy' ? 'Buy' : 'Sell') + ' Now';
        });
    };

    function showPaymentForm(tradeId, paySettings, totalPrice, coinType, qty, type, offerType) {
        var panel = document.getElementById('detailBody');
        var coinLabels = {bronze:'🥉 Bronze',silver:'🥈 Silver',gold:'🥇 Gold'};
        var isBuyOrder = type === 'buy'; // User is buying coins from merchant

        // ── ORDER SUMMARY ──
        var html = '<div class="p2p-order-steps"><div class="p2p-order-step done"><span class="num">1</span> Order Placed</div><div class="p2p-order-step active"><span class="num">2</span> Pay Merchant</div><div class="p2p-order-step"><span class="num">3</span> Receive Coins</div></div>';
        html += '<div class="p2p-pay-detail-v3">';
        html += '<div class="p2p-pay-detail-v3-row"><span class="lbl">Order</span><span class="val">#' + tradeId + '</span></div>';
        html += '<div class="p2p-pay-detail-v3-row"><span class="lbl">Coin</span><span class="val">' + coinLabels[coinType] + '</span></div>';
        html += '<div class="p2p-pay-detail-v3-row"><span class="lbl">Quantity</span><span class="val">' + qty + '</span></div>';
        html += '<div class="p2p-pay-detail-v3-row" style="border-top:1px dashed var(--p2p-border);padding-top:8px;margin-top:4px"><span class="lbl">Total</span><span class="val" style="font-size:1.1rem">৳' + totalPrice.toFixed(0) + '</span></div>';
        html += '</div>';

        // ── MERCHANT PAYMENT DETAILS (like Add Money) ──
        if (paySettings && paySettings.length > 0) {
            html += '<div class="p2p-pay-warning">';
            html += '<div class="p2p-pay-warning-title"><i class="fas fa-hand-holding-dollar"></i> Send Payment To Merchant</div>';
            paySettings.forEach(function(pm){
                var mName = {bkash:'bKash',nagad:'Nagad',rocket:'Rocket'}[pm.method] || pm.method.toUpperCase();
                var mColor = {bkash:'#E2136E',nagad:'#E8522E',rocket:'#CC0000'}[pm.method] || '#6b7280';
                var instrLabel = pm.instruction === 'cashout' ? 'Cash Out' : 'Send Money';
                html += '<div class="p2p-pay-method-card" style="border:1px solid ' + mColor + '20">';
                html += '<div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">';
                html += '<img src="assets/images/payment-icon/' + pm.method + '-logo-mobile-banking.png" alt="" onerror="this.style.display=\'none\'" style="height:22px">';
                html += '<strong style="color:' + mColor + ';font-size:.85rem">' + mName + '</strong>';
                html += '<span style="margin-left:auto;font-size:.65rem;font-weight:700;color:' + mColor + ';background:' + mColor + '15;padding:2px 8px;border-radius:999px">' + instrLabel + '</span></div>';
                html += '<div class="p2p-pay-phone">' + pm.number + ' <button onclick="copyText(\'' + pm.number + '\', this)" style="background:none;border:none;color:' + mColor + ';cursor:pointer;font-size:.7rem"><i class="fas fa-copy"></i></button></div>';
                html += '</div>';
            });
            html += '<div class="p2p-pay-warning-note"><i class="fas fa-info-circle"></i> Send exactly <strong>৳' + totalPrice.toFixed(0) + '</strong> using any method above</div>';
            html += '</div>';
        }

        // ── CONFIRM PAYMENT FORM ──
        html += '<div style="font-size:.75rem;font-weight:700;color:var(--p2p-muted);margin-bottom:8px"><i class="fas fa-check-circle" style="color:#059669"></i> After sending payment, fill below:</div>';
        html += '<div class="p2p-confirm-pay-form">';
        html += '<div class="field"><label>💳 Payment Method</label>' +
            '<div class="p2p-custom-select" id="payMethodSelect">' +
            '<div class="p2p-select-trigger"><span>bKash</span></div>' +
            '<div class="p2p-select-options">';
        ['bkash','nagad','rocket'].forEach(function(m, idx){
            var label = {bkash:'bKash',nagad:'Nagad',rocket:'Rocket'}[m];
            html += '<div class="p2p-select-option' + (idx===0?' active':'') + '" data-value="' + m + '">' + label + '</div>';
        });
        html += '</div><input type="hidden" id="payMethod" value="bkash"></div></div>';
        html += '<div class="field"><label>📱 Your Phone Number</label><input type="tel" id="payPhone" placeholder="01XXXXXXXXX"></div>';
        html += '<div class="field"><label>🔗 Transaction ID</label><input type="text" id="payTxid" placeholder="Enter TXID" style="text-transform:uppercase"></div>';
        html += '</div>';

        html += '<button class="action-btn buy" onclick="confirmPayment(' + tradeId + ')"><i class="fas fa-check-circle"></i> I Have Paid</button>';
        html += '<div class="p2p-fb" id="payFb"></div>';

        panel.innerHTML = html;
        initAllCustomSelects(panel);
    }

    // ═══ SHOW ORDER PLACED (for sellers — buy offers) ═══
    function showOrderPlacedSeller(tradeId, totalPrice, coinType, qty) {
        var panel = document.getElementById('detailBody');
        var coinLabels = {bronze:'🥉 Bronze',silver:'🥈 Silver',gold:'🥇 Gold'};
        var html = '<div class="p2p-order-steps"><div class="p2p-order-step done"><span class="num">1</span> Order Placed</div><div class="p2p-order-step active"><span class="num">2</span> Awaiting Payment</div><div class="p2p-order-step"><span class="num">3</span> Complete</div></div>';
        html += '<div style="text-align:center;padding:24px 16px">';
        html += '<div style="width:64px;height:64px;border-radius:50%;background:#d1fae5;display:flex;align-items:center;justify-content:center;margin:0 auto 14px"><i class="fas fa-check" style="font-size:1.5rem;color:#059669"></i></div>';
        html += '<h3 style="font-size:1rem;font-weight:800;color:var(--p2p-text);margin:0 0 6px">Order Placed Successfully!</h3>';
        html += '<p style="font-size:.8rem;color:var(--p2p-muted);margin:0 0 16px;line-height:1.5">Your <strong>' + qty + ' ' + coinLabels[coinType] + '</strong> are held in escrow.<br>Waiting for the merchant to send you payment.</p>';
        html += '</div>';
        html += '<div class="p2p-pay-detail-v3">';
        html += '<div class="p2p-pay-detail-v3-row"><span class="lbl">Trade</span><span class="val">#' + tradeId + '</span></div>';
        html += '<div class="p2p-pay-detail-v3-row"><span class="lbl">Coin</span><span class="val">' + coinLabels[coinType] + '</span></div>';
        html += '<div class="p2p-pay-detail-v3-row"><span class="lbl">Quantity</span><span class="val">' + qty + '</span></div>';
        html += '<div class="p2p-pay-detail-v3-row" style="border-top:1px dashed var(--p2p-border);padding-top:8px;margin-top:4px"><span class="lbl">Total</span><span class="val" style="font-size:1.1rem">৳' + totalPrice.toFixed(0) + '</span></div>';
        html += '</div>';
        html += '<div style="background:#fefce8;border:1px solid #fde68a;border-radius:12px;padding:12px 14px;margin:14px 0;text-align:center;font-size:.72rem;color:#92400e;line-height:1.5">';
        html += '<i class="fas fa-info-circle"></i> Once the merchant confirms payment, you can release the coins from the <strong>Orders</strong> tab.';
        html += '</div>';
        html += '<button class="p2p-convert-btn" onclick="closeDetail()" style="background:linear-gradient(135deg,var(--p2p-accent),#6366f1)"><i class="fas fa-check"></i> Done</button>';
        panel.innerHTML = html;
    }

    window.confirmPayment = function(tradeId) {
        var method = document.getElementById('payMethod').value;
        var phone = document.getElementById('payPhone').value.trim();
        var txid = document.getElementById('payTxid').value.trim().toUpperCase();
        if (!/^01[3-9]\d{8}$/.test(phone)) { showFb('payFb', 'Enter a valid phone number.', 'error'); return; }
        if (txid.length < 4) { showFb('payFb', 'Enter a valid Transaction ID.', 'error'); return; }

        var btn = document.querySelector('.action-btn.buy');
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Confirming...'; }

        fetch('handlers/p2p_handler.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json','X-Requested-With': 'XMLHttpRequest'},
            body: JSON.stringify({action: 'confirm_p2p_payment', trade_id: tradeId, method: method, sender_phone: phone, txid: txid, csrf_token: csrfToken})
        })
        .then(function(r){ return r.json(); })
        .then(function(res){
            if (res.success) {
                showFb('payFb', 'Payment confirmed! Awaiting seller to release coins.', 'success');
                setTimeout(function(){ closeDetail(); location.reload(); }, 2000);
            } else {
                showFb('payFb', res.message, 'error');
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-check-circle"></i> I Have Paid'; }
            }
        })
        .catch(function(){ showFb('payFb', 'Network error.', 'error'); if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-check-circle"></i> I Have Paid'; } });
    };

    window.copyText = function(text, btn) {
        var doFlash = function(b) {
            if (!b) return;
            var orig = b.innerHTML;
            b.innerHTML = '<i class="fas fa-check"></i> Copied!';
            setTimeout(function(){ b.innerHTML = orig; }, 1500);
        };
        function fallback(t, b) {
            var ta = document.createElement('textarea');
            ta.value = t; ta.style.cssText = 'position:fixed;opacity:0;top:0;left:0';
            document.body.appendChild(ta); ta.focus(); ta.select();
            try { document.execCommand('copy'); doFlash(b); } catch(e) {}
            document.body.removeChild(ta);
        }
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(function(){ doFlash(btn); }).catch(function(){ fallback(text, btn); });
        } else { fallback(text, btn); }
    };

    // ═══ ORDER ACTIONS (from Orders tab) ═══
    window.confirmReceived = function(tradeId) {
        if (!confirm('Release coins to complete this trade? This action cannot be undone.')) return;
        fetch('handlers/p2p_handler.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json','X-Requested-With': 'XMLHttpRequest'},
            body: JSON.stringify({action: 'confirm_p2p_received', trade_id: tradeId, csrf_token: csrfToken})
        })
        .then(function(r){ return r.json(); })
        .then(function(res){ if (res.success) location.reload(); else alert(res.message); });
    };

    window.cancelOrder = function(tradeId) {
        if (!confirm('Cancel this order?')) return;
        fetch('handlers/p2p_handler.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json','X-Requested-With': 'XMLHttpRequest'},
            body: JSON.stringify({action: 'cancel_p2p_order', trade_id: tradeId, csrf_token: csrfToken})
        })
        .then(function(r){ return r.json(); })
        .then(function(res){ if (res.success) location.reload(); else alert(res.message); });
    };

    // ═══ CHAT ═══
    window.openTradeChat = function(tradeId) {
        if (chatInterval) { clearInterval(chatInterval); chatInterval = null; }
        currentChatTradeId = tradeId;
        chatRequestToken++;
        var requestToken = chatRequestToken;
        document.getElementById('chatOverlay').style.display = 'flex';
        document.body.style.overflow = 'hidden';
        document.getElementById('chatTradeId').textContent = tradeId;
        document.getElementById('chatTopName').textContent = 'Trade #' + tradeId;
        document.getElementById('chatTopAvatar').textContent = '#';
        document.getElementById('chatTopSub').textContent = '';
        document.getElementById('chatInput').value = '';
        document.getElementById('chatImageInput').value = '';
        // Clear old messages immediately
        var chatMessages = document.getElementById('chatMessages');
        chatMessages.setAttribute('data-trade-id', tradeId);
        chatMessages.innerHTML = '<div class="p2p-chat-empty"><i class="fas fa-spinner fa-pulse"></i><p>Loading...</p></div>';
        // Fetch trade details to show partner info
        fetch('handlers/p2p_handler.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json','X-Requested-With': 'XMLHttpRequest'},
            body: JSON.stringify({action:'get_p2p_trade', trade_id: tradeId, csrf_token: csrfToken})
        })
        .then(function(r){ return r.json(); })
        .then(function(res){
            if (res.success && res.trade) {
                var t = res.trade;
                var partner = t.buyer_id == userId ? (t.seller_name || 'Seller') : (t.buyer_name || 'Buyer');
                document.getElementById('chatTopName').textContent = partner;
                document.getElementById('chatTopAvatar').textContent = partner.charAt(0).toUpperCase();
                document.getElementById('chatTopSub').textContent = t.coin_type.charAt(0).toUpperCase() + t.coin_type.slice(1) + ' \u00B7 ' + t.quantity + ' coins \u00B7 ' + t.status;
            }
        });
        loadChatMessages(tradeId, requestToken);
        chatInterval = setInterval(function(){ loadChatMessages(tradeId, requestToken); }, 3000);
    };

    window.closeChat = function() {
        document.getElementById('chatOverlay').style.display = 'none';
        document.body.style.overflow = '';
        if (chatInterval) { clearInterval(chatInterval); chatInterval = null; }
        currentChatTradeId = null;
    };
    document.getElementById('chatOverlay').addEventListener('click', function(e) { if (e.target === this) closeChat(); });

    document.getElementById('chatSendBtn').addEventListener('click', function(){
        var input = document.getElementById('chatInput');
        var msg = input.value.trim();
        if (!msg || !currentChatTradeId) return;
        fetch('handlers/p2p_handler.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json','X-Requested-With': 'XMLHttpRequest'},
            body: JSON.stringify({action: 'send_p2p_message', trade_id: currentChatTradeId, message: msg, csrf_token: csrfToken})
        })
        .then(function(r){ return r.json(); })
        .then(function(res){
            if (res.success) {
                input.value = '';
                if (res.messages) renderChatMessages(res.messages);
                else loadChatMessages(currentChatTradeId);
            } else {
                alert(res.message || 'Could not send message.');
            }
        });
    });

    document.getElementById('chatInput').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') document.getElementById('chatSendBtn').click();
    });

    window.uploadChatImage = function() {
        var input = document.getElementById('chatImageInput');
        if (!input.files || input.files.length === 0 || !currentChatTradeId) return;
        var formData = new FormData();
        formData.append('action', 'upload_chat_image');
        formData.append('trade_id', currentChatTradeId);
        formData.append('csrf_token', csrfToken);
        formData.append('chat_image', input.files[0]);

        var btn = document.getElementById('chatAttachBtn');
        var oldHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        btn.disabled = true;

        fetch('handlers/p2p_handler.php', {
            method: 'POST',
            body: formData
        })
        .then(function(r){ return r.json(); })
        .then(function(res){
            btn.innerHTML = oldHtml;
            btn.disabled = false;
            input.value = '';
            if (res.success) {
                if (res.messages) renderChatMessages(res.messages);
                else loadChatMessages(currentChatTradeId);
            }
            else { alert(res.message); }
        })
        .catch(function(){
            btn.innerHTML = oldHtml;
            btn.disabled = false;
            input.value = '';
        });
    };

    function renderChatMessages(messages) {
            var container = document.getElementById('chatMessages');
            if (!container) return;
            if (!messages || !messages.length) {
                container.innerHTML = '<div class="p2p-chat-empty"><i class="fas fa-comment-dots"></i><p>No messages yet.</p></div>';
                return;
            }
            var html = '';
            var lastDate = '';
            for (var i = 0; i < messages.length; i++) {
                var m = messages[i];
                if (m.created_at) {
                    var d = new Date(m.created_at.replace(' ','T'));
                    if (!isNaN(d)) {
                        var dateStr = d.toLocaleDateString('en-US', {month:'short', day:'numeric', year: d.getFullYear() !== new Date().getFullYear() ? 'numeric' : undefined});
                        if (dateStr !== lastDate) {
                            lastDate = dateStr;
                            var today = new Date();
                            var isToday = d.getDate() === today.getDate() && d.getMonth() === today.getMonth() && d.getFullYear() === today.getFullYear();
                            var isYesterday = new Date(today.getTime() - 86400000).getDate() === d.getDate() && new Date(today.getTime() - 86400000).getMonth() === d.getMonth();
                            html += '<div class="p2p-chat-date-divider">' + (isToday ? 'Today' : isYesterday ? 'Yesterday' : dateStr) + '</div>';
                        }
                    }
                }
                var isOwn = parseInt(m.sender_id) === userId;
                html += '<div class="p2p-chat-msg ' + (isOwn ? 'own' : 'other') + '">';
                if (!isOwn) {
                    var initial = (m.full_name || m.username || 'U').charAt(0).toUpperCase();
                    html += '<div class="sender"><span class="avi">' + initial + '</span> ' + (m.full_name || m.username || 'User') + '</div>';
                }
                if (m.image_path) {
                    html += '<img src="assets/images/chat/' + m.image_path + '" onclick="window.open(this.src,\'_blank\')">';
                }
                if (m.message) {
                    var safeMsg = m.message.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
                    html += safeMsg;
                }
                if (m.created_at) {
                    var dt = new Date(m.created_at.replace(' ','T'));
                    if (!isNaN(dt)) html += '<span class="time">' + dt.toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'}) + '</span>';
                }
                html += '</div>';
            }
            container.innerHTML = html;
            container.scrollTop = container.scrollHeight;
    }

    function loadChatMessages(tradeId, token) {
        fetch('handlers/p2p_handler.php?action=get_p2p_messages&trade_id=' + encodeURIComponent(tradeId) + '&csrf_token=' + encodeURIComponent(csrfToken))
        .then(function(r){ return r.json(); })
        .then(function(res){
            if (currentChatTradeId != tradeId || (token !== undefined && token !== chatRequestToken)) return;
            if (!res.success || !res.messages) {
                renderChatMessages([]);
                return;
            }
            renderChatMessages(res.messages);
        })
        .catch(function(){});
    }

    // ═══ CREATE OFFER ═══
    var createForm = document.getElementById('p2pCreateForm');
    if (createForm) {
        createForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var type = document.getElementById('p2pOfferType').value;
            var coin = document.getElementById('p2pOfferCoin').value;
            var price = parseFloat(document.getElementById('p2pOfferPrice').value) || 0;
            var qty = parseInt(document.getElementById('p2pOfferQty').value) || 0;
            var minAmt = parseInt(document.getElementById('p2pOfferMin').value) || 1;
            var maxAmt = parseInt(document.getElementById('p2pOfferMax').value) || 0;
            if (price < 1 || qty < 1) { showFb('p2pCreateFb', 'Invalid price or quantity.', 'error'); return; }

            document.getElementById('p2pCreateBtn').disabled = true;
            document.getElementById('p2pCreateBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
            hideFb('p2pCreateFb');

            fetch('handlers/p2p_handler.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json','X-Requested-With': 'XMLHttpRequest'},
                body: JSON.stringify({action: 'create_p2p_offer', type: type, coin_type: coin, price: price, quantity: qty, min_amount: minAmt, max_amount: maxAmt, csrf_token: csrfToken})
            })
            .then(function(r){ return r.json(); })
            .then(function(res){
                if (res.success) { showFb('p2pCreateFb', 'Offer created!', 'success'); setTimeout(function(){ location.reload(); }, 1000); }
                else { showFb('p2pCreateFb', res.message, 'error');
                    document.getElementById('p2pCreateBtn').disabled = false;
                    document.getElementById('p2pCreateBtn').innerHTML = '<i class="fas fa-check"></i> Publish Offer'; }
            })
            .catch(function(){ showFb('p2pCreateFb', 'Network error.', 'error');
                document.getElementById('p2pCreateBtn').disabled = false;
                document.getElementById('p2pCreateBtn').innerHTML = '<i class="fas fa-check"></i> Publish Offer'; });
        });
    }

    window.cancelOffer = function(offerId) {
        if (!confirm('Cancel this offer?')) return;
        fetch('handlers/p2p_handler.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json','X-Requested-With': 'XMLHttpRequest'},
            body: JSON.stringify({action: 'cancel_p2p_offer', offer_id: offerId, csrf_token: csrfToken})
        })
        .then(function(r){ return r.json(); })
        .then(function(res){ if (res.success) location.reload(); });
    };

    // ═══ SAVE PAYMENT SETTINGS ═══
    var savePayBtn = document.getElementById('savePaySettings');
    if (savePayBtn) {
        savePayBtn.addEventListener('click', function() {
            var settings = [];
            ['bkash','nagad','rocket'].forEach(function(m){
                var number = document.querySelector('.payMethodInput[data-method="'+m+'"][data-field="number"]');
                var instrInput = document.querySelector('.payInstrInput[data-method="'+m+'"]');
                if (number && number.value.trim()) {
                    settings.push({method: m, number: number.value.trim(), instruction: instrInput ? instrInput.value : 'send_money'});
                }
            });
            if (settings.length === 0) { showFb('paySettingsFb', 'At least one payment method required.', 'error'); return; }

            savePayBtn.disabled = true; savePayBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...'; hideFb('paySettingsFb');
            fetch('handlers/p2p_handler.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json','X-Requested-With': 'XMLHttpRequest'},
                body: JSON.stringify({action: 'save_p2p_payment_settings', settings: settings, csrf_token: csrfToken})
            })
            .then(function(r){ return r.json(); })
            .then(function(res){
                if (res.success) { showFb('paySettingsFb', 'Payment settings saved!', 'success');
                    savePayBtn.innerHTML = '<i class="fas fa-check"></i> Saved!'; setTimeout(function(){ savePayBtn.innerHTML = '<i class="fas fa-save"></i> Save Payment Methods'; savePayBtn.disabled = false; }, 2000); }
                else { showFb('paySettingsFb', res.message, 'error'); savePayBtn.disabled = false; savePayBtn.innerHTML = '<i class="fas fa-save"></i> Save Settings'; }
            })
            .catch(function(){ showFb('paySettingsFb', 'Network error.', 'error'); savePayBtn.disabled = false; savePayBtn.innerHTML = '<i class="fas fa-save"></i> Save Settings'; });
        });
    }

    // ═══ CONVERSION ═══
    var convFrom = document.getElementById('convFrom');
    var convTo = document.getElementById('convTo');
    var convQty = document.getElementById('convQty');
    var convResult = document.getElementById('convResult');

    function updateConvPreview() {
        var from = convFrom.value;
        var to = convTo.value;
        var qty = parseInt(convQty.value) || 0;
        if (from === to || qty < 1) { convResult.textContent = ''; return; }
        var values = {bronze:25, silver:50, gold:100};
        var fromVal = values[from];
        var toVal = values[to];
        var totalFromVal = qty * fromVal;
        var resultQty = Math.floor(totalFromVal / toVal);
        var remainder = totalFromVal % toVal;
        if (resultQty < 1) { convResult.textContent = 'Not enough value for conversion.'; return; }
        convResult.innerHTML = '<strong>' + qty + ' ' + from + '</strong> → <strong>' + resultQty + ' ' + to + '</strong>' + (remainder > 0 ? ' <span style="color:var(--p2p-muted);font-size:.7rem">(' + remainder + ' ৳ unused)</span>' : '');
    }

    if (convQty) convQty.addEventListener('input', updateConvPreview);

    document.getElementById('convSwap').addEventListener('click', function(){
        var tmp = convFrom.value;
        var tTo = convTo.value;
        
        // Update hidden inputs
        convFrom.value = tTo;
        convTo.value = tmp;

        // Sync visible triggers and active options
        [ {id: 'selectConvFrom', val: tTo}, {id: 'selectConvTo', val: tmp} ].forEach(function(item) {
            var container = document.getElementById(item.id);
            var opt = container.querySelector('.p2p-select-option[data-value="' + item.val + '"]');
            if (opt) {
                container.querySelector('.p2p-select-trigger span').innerHTML = opt.innerHTML;
                container.querySelectorAll('.p2p-select-option').forEach(function(o) { o.classList.remove('active'); });
                opt.classList.add('active');
            }
        });

        updateConvPreview();
    });

    document.getElementById('convBtn').addEventListener('click', function(){
        var from = convFrom.value;
        var to = convTo.value;
        var qty = parseInt(convQty.value) || 0;
        if (from === to) { showFb('convFb', 'Select different coin types.', 'error'); return; }
        if (qty < 1) { showFb('convFb', 'Enter quantity.', 'error'); return; }

        var btn = this; btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Converting...'; hideFb('convFb');
        fetch('handlers/p2p_handler.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json','X-Requested-With': 'XMLHttpRequest'},
            body: JSON.stringify({action: 'convert_coins', from_type: from, to_type: to, quantity: qty, csrf_token: csrfToken})
        })
        .then(function(r){ return r.json(); })
        .then(function(res){
            if (res.success) { showFb('convFb', res.message, 'success'); setTimeout(function(){ location.reload(); }, 1500); }
            else { showFb('convFb', res.message, 'error'); btn.disabled = false; btn.innerHTML = '<i class="fas fa-arrows-rotate"></i> Convert'; }
        })
        .catch(function(){ showFb('convFb', 'Network error.', 'error'); btn.disabled = false; btn.innerHTML = '<i class="fas fa-arrows-rotate"></i> Convert'; });
    });

    // ═══ HELPERS ═══
    function showFb(id, msg, type) {
        var el = document.getElementById(id);
        if (!el) return;
        el.className = 'p2p-fb ' + type;
        el.textContent = msg;
        el.style.display = 'block';
    }
    function hideFb(id) {
        var el = document.getElementById(id);
        if (el) { el.style.display = 'none'; el.className = 'p2p-fb'; }
    }

    // ═══ SEARCH BY MERCHANT NAME ═══
    window.filterOffers = function(input) {
        var query = input.value.toLowerCase().trim();
        input.closest('.p2p-tab-panel').querySelectorAll('.p2p-offer-card-v3').forEach(function(card) {
            var agent = card.getAttribute('data-agent').toLowerCase();
            card.style.display = query === '' || agent.indexOf(query) !== -1 ? '' : 'none';
        });
    };

    // ═══ TRADE FILTERS ═══
    window.applyTradeFilters = function() {
        var status = document.getElementById('tradeFilterStatus').value;
        var coin = document.getElementById('tradeFilterCoin').value;
        var date = document.getElementById('tradeFilterDate').value;
        document.querySelectorAll('#tabOrders .p2p-trade-item').forEach(function(item) {
            var show = true;
            if (status !== 'all' && item.getAttribute('data-status') !== status) show = false;
            if (coin !== 'all' && item.getAttribute('data-coin') !== coin) show = false;
            if (date && item.getAttribute('data-created') !== date) show = false;
            item.style.display = show ? '' : 'none';
        });
    };

    // ═══ LOAD MORE OFFERS ═══
    var offerOffsets = {sell: 50, buy: 50};
    window.loadMoreOffers = function(type, offset) {
        var btn = document.getElementById('loadMore' + (type==='sell'?'Buy':'Sell') + 'Btn');
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...'; }
        var off = offerOffsets[type] || 0;
        fetch('handlers/p2p_handler.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json','X-Requested-With': 'XMLHttpRequest'},
            body: JSON.stringify({action: 'load_more_offers', type: type, offset: off, csrf_token: csrfToken})
        })
        .then(function(r){ return r.json(); })
        .then(function(res){
            if (res.success && res.offers && res.offers.length > 0) {
                var grid = document.getElementById('tab'+(type==='sell'?'Buy':'Sell')).querySelector('.p2p-offer-grid-v3');
                if (!grid) return;
                var coinIcons = {bronze:'🥉',silver:'🥈',gold:'🥇'};
                var coinLabels = {bronze:'Bronze',silver:'Silver',gold:'Gold'};
                res.offers.forEach(function(o){
                    var uname = o.full_name || o.username;
                    var html = '<div class="p2p-offer-card-v3" data-offer-id="' + o.id + '" data-type="' + (type==='sell'?'buy':'sell') + '" data-coin="' + o.coin_type + '" data-price="' + o.price_per_coin + '" data-remaining="' + o.remaining + '" data-min="' + o.min_amount + '" data-max="' + o.max_amount + '" data-agent="' + uname + '" data-agent-id="' + o.agent_id + '">' +
                        '<div class="p2p-offer-card-v3-inner">' +
                            '<div class="p2p-offer-card-v3-top">' +
                                '<div class="p2p-offer-card-v3-avatar">' + uname.charAt(0).toUpperCase() + '</div>' +
                                '<div class="p2p-offer-card-v3-user">' +
                                    '<div class="name">' + uname + ' <span class="verified"><i class="fas fa-check"></i></span></div>' +
                                    '<div class="role"><span>' + (type==='sell'?'Seller':'Buyer') + '</span></div>' +
                                '</div>' +
                            '</div>' +
                            '<div class="p2p-offer-card-v3-body">' +
                                '<div class="p2p-offer-card-v3-cell"><div class="lbl">Coin</div><div class="val"><span class="coin-tag">' + coinIcons[o.coin_type] + ' ' + coinLabels[o.coin_type] + '</span></div></div>' +
                                '<div class="p2p-offer-card-v3-cell"><div class="lbl">Price</div><div class="val price">৳' + parseFloat(o.price_per_coin).toFixed(0) + '</div></div>' +
                                '<div class="p2p-offer-card-v3-cell"><div class="lbl">Available</div><div class="val">' + o.remaining + '</div></div>' +
                                '<div class="p2p-offer-card-v3-cell"><div class="lbl">Limit</div><div class="val">' + o.min_amount + '-' + (parseInt(o.max_amount)||'∞') + '</div></div>' +
                            '</div>' +
                            '<div class="p2p-offer-card-v3-footer">' +
                                '<div class="payment-icons">' +
                                    '<img src="assets/images/payment-icon/bkash-logo-mobile-banking.png" alt="bKash">' +
                                    '<img src="assets/images/payment-icon/nagad-logo-mobile-banking.png" alt="Nagad">' +
                                    '<img src="assets/images/payment-icon/rocket-logo-mobile-banking.png" alt="Rocket">' +
                                '</div>' +
                                '<span class="action-badge">' + (type==='sell'?'Buy':'Sell') + '</span>' +
                            '</div>' +
                        '</div>' +
                    '</div>';
                    grid.insertAdjacentHTML('beforeend', html);
                    // Rebind click
                    grid.lastElementChild.addEventListener('click', function(){
                        var d = this.dataset;
                        showOfferDetail(d.offerId, d.type, d.coin, parseFloat(d.price), parseInt(d.remaining), parseInt(d.min), parseInt(d.max), d.agent, parseInt(d.agentId));
                    });
                });
                offerOffsets[type] = off + res.offers.length;
                if (!res.has_more && btn) { btn.style.display = 'none'; }
            }
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-plus"></i> Load More Offers'; }
        })
        .catch(function(){ if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-plus"></i> Load More Offers'; }});
    };

    // ═══ LOAD MORE TRADES ═══
    var tradeOffset = 30;
    window.loadMoreTrades = function() {
        var btn = document.getElementById('loadMoreTradesBtn');
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...'; }
        fetch('handlers/p2p_handler.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json','X-Requested-With': 'XMLHttpRequest'},
            body: JSON.stringify({action: 'load_more_trades', offset: tradeOffset, csrf_token: csrfToken})
        })
        .then(function(r){ return r.json(); })
        .then(function(res){
            if (res.success && res.trades && res.trades.length > 0) {
                var container = document.querySelector('#tabOrders .p2p-section-card-body');
                var statusBadge = {pending:'yellow',paid:'blue',completed:'green',cancelled:'red',disputed:'orange'};
                var coinLabels = {bronze:'Bronze',silver:'Silver',gold:'Gold'};
                res.trades.forEach(function(t){
                    var isBuyer = parseInt(t.buyer_id) === userId;
                    var isSeller = parseInt(t.seller_id) === userId;
                    var otherName = isBuyer ? t.buyer_name : t.seller_name;
                    var st = t.status;
                    var stClass = statusBadge[st] || 'yellow';
                    var offerType = t.offer_type || 'sell';
                    var actions = '';
                    if (st === 'pending' && isBuyer) actions += '<button class="p2p-btn-primary" onclick="openPaymentForm('+t.id+')"><i class="fas fa-credit-card"></i> Pay Now</button>';
                    if (st === 'paid' && isSeller) actions += '<button class="p2p-btn-confirm" onclick="confirmReceived('+t.id+')"><i class="fas fa-check"></i> Release Coins</button>';
                    if (st === 'paid' && isBuyer) actions += '<span style="font-size:.7rem;font-weight:600;color:#d97706"><i class="fas fa-clock"></i> Waiting...</span>';
                    if (st === 'pending' || st === 'paid') actions += '<span style="flex:1"></span><button class="p2p-btn-cancel" onclick="cancelOrder('+t.id+')"><i class="fas fa-xmark"></i> Cancel</button>';
                    actions += '<button class="p2p-btn-chat" onclick="openTradeChat('+t.id+')"><i class="fas fa-comment"></i> Chat</button>';
                    if (st === 'paid' || st === 'pending') actions += '<button class="p2p-btn-report" onclick="disputeTrade('+t.id+')" style="background:#ef444415;color:#ef4444;"><i class="fas fa-gavel"></i> Appeal</button>';

                    var icon = isBuyer ? 'fa-cart-shopping' : 'fa-coins';
                    var stIcon = st==='completed'?'fa-check-circle':(st==='pending'?'fa-clock':(st==='paid'?'fa-credit-card':(st==='cancelled'?'fa-xmark-circle':'fa-flag')));
                    var html = '<div class="p2p-trade-item" data-trade-id="' + t.id + '" data-status="' + st + '" data-coin="' + t.coin_type + '" data-created="' + t.created_at.substring(0,10) + '">' +
                        '<div class="p2p-trade-top">' +
                            '<div class="p2p-trade-icon ' + (offerType==='buy'?'sell':'') + '"><i class="fas ' + icon + '"></i></div>' +
                            '<div class="p2p-trade-meta">' +
                                '<div class="p2p-trade-meta-top">' +
                                    '<span class="p2p-trade-id">#' + t.id + '</span>' +
                                    '<span class="p2p-trade-partner"><span class="avi">' + (otherName||'U').charAt(0).toUpperCase() + '</span> ' + (otherName||'User') + '</span>' +
                                    '<span class="p2p-trade-status ' + stClass + '"><i class="fas ' + stIcon + '"></i> ' + st.charAt(0).toUpperCase() + st.slice(1) + '</span>' +
                                    '<span class="p2p-trade-time"><i class="fas fa-clock"></i> ' + new Date(t.created_at).toLocaleDateString('en-US',{month:'short',day:'numeric'}) + '</span>' +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                        '<div class="p2p-trade-detail">' +
                            '<span class="chip"><i class="fas fa-coins"></i> <strong>' + (coinLabels[t.coin_type]||t.coin_type) + '</strong></span>' +
                            '<span class="chip"><i class="fas fa-cube"></i> Qty: <strong>' + t.quantity + '</strong></span>' +
                            '<span class="chip"><i class="fas fa-bangladeshi-taka-sign"></i> <strong>\u09F3' + parseFloat(t.total_price).toFixed(0) + '</strong></span>' +
                            '<span class="chip"><i class="fas fa-arrow-right-arrow-left"></i> ' + (isBuyer?'Buy':'Sell') + '</span>' +
                        '</div>' +
                        '<div class="p2p-trade-actions"><button class="p2p-btn-chat" onclick="openTradeChat('+t.id+')"><i class="fas fa-comment"></i> Chat</button>' + actions + '</div>' +
                    '</div>';
                    container.insertAdjacentHTML('beforeend', html);
                });
                tradeOffset += res.trades.length;
                if (!res.has_more && btn) { btn.style.display = 'none'; }
            }
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-plus"></i> Load More Trades'; }
        })
        .catch(function(){ if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-plus"></i> Load More Trades'; }});
    };

    // ═══ EDIT OFFER ═══
    window.editOffer = function(id, price, min, max) {
        document.getElementById('editOfferId').value = id;
        document.getElementById('editOfferPrice').value = price;
        document.getElementById('editOfferMin').value = min;
        document.getElementById('editOfferMax').value = max;
        document.getElementById('editOfferOverlay').style.display = 'flex';
        document.body.style.overflow = 'hidden';
        hideFb('editOfferFb');
    };

    document.getElementById('editOfferBtn').addEventListener('click', function(){
        var id = document.getElementById('editOfferId').value;
        var price = parseFloat(document.getElementById('editOfferPrice').value) || 0;
        var min = parseInt(document.getElementById('editOfferMin').value) || 1;
        var max = parseInt(document.getElementById('editOfferMax').value) || 0;
        if (price < 1) { showFb('editOfferFb', 'Invalid price.', 'error'); return; }
        if (max > 0 && max < min) { showFb('editOfferFb', 'Max must be >= Min.', 'error'); return; }

        var btn = this; btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...'; hideFb('editOfferFb');
        fetch('handlers/p2p_handler.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json','X-Requested-With': 'XMLHttpRequest'},
            body: JSON.stringify({action: 'edit_p2p_offer', offer_id: id, price: price, min_amount: min, max_amount: max, csrf_token: csrfToken})
        })
        .then(function(r){ return r.json(); })
        .then(function(res){
            if (res.success) { showFb('editOfferFb', 'Offer updated!', 'success'); setTimeout(function(){ document.getElementById('editOfferOverlay').style.display='none'; document.body.style.overflow=''; location.reload(); }, 1000); }
            else { showFb('editOfferFb', res.message, 'error'); btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Update Offer'; }
        })
        .catch(function(){ showFb('editOfferFb', 'Network error.', 'error'); btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Update Offer'; });
    });

    // ═══ STAR RATING ═══
    var currentRating = 0;
    window.setRating = function(val) {
        currentRating = val;
        document.getElementById('reviewRating').value = val;
        document.querySelectorAll('#starRating span').forEach(function(s){
            var star = parseInt(s.getAttribute('data-star'));
            s.style.color = star <= val ? '#d97706' : '#d1d5db';
        });
    };

    window.submitReview = function() {
        var tradeId = document.getElementById('reviewTradeId').value;
        var rating = document.getElementById('reviewRating').value;
        var comment = document.getElementById('reviewComment').value.trim();
        if (!rating || parseInt(rating) < 1) { showFb('reviewFb', 'Please select a rating.', 'error'); return; }
        var btn = document.getElementById('reviewBtn');
        btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...'; hideFb('reviewFb');
        fetch('handlers/p2p_handler.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json','X-Requested-With': 'XMLHttpRequest'},
            body: JSON.stringify({action: 'submit_p2p_review', trade_id: tradeId, rating: rating, comment: comment, csrf_token: csrfToken})
        })
        .then(function(r){ return r.json(); })
        .then(function(res){
            if (res.success) { showFb('reviewFb', 'Review submitted! Thank you.', 'success'); setTimeout(function(){ document.getElementById('reviewOverlay').style.display='none'; document.body.style.overflow=''; }, 1500); }
            else { showFb('reviewFb', res.message, 'error'); btn.disabled = false; btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Review'; }
        })
        .catch(function(){ showFb('reviewFb', 'Network error.', 'error'); btn.disabled = false; btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Review'; });
    };

    // ═══ ADD REVIEW BUTTON TO COMPLETED TRADES ═══
    // Modify trade actions for completed trades: add Rate button
    document.querySelectorAll('.p2p-trade-item[data-status="completed"]').forEach(function(item){
        var actions = item.querySelector('.p2p-trade-actions');
        if (actions && !actions.querySelector('.p2p-btn-rate')) {
            actions.insertAdjacentHTML('afterbegin', '<button class="p2p-btn-confirm p2p-btn-rate" onclick="openReviewModal(' + item.getAttribute('data-trade-id') + ')" style="padding:6px 10px;font-size:.65rem"><i class="fas fa-star"></i> Rate</button>');
        }
    });

    window.openReviewModal = function(tradeId) {
        document.getElementById('reviewTradeId').value = tradeId;
        document.getElementById('reviewRating').value = 0;
        currentRating = 0;
        document.getElementById('reviewComment').value = '';
        document.querySelectorAll('#starRating span').forEach(function(s){ s.style.color = '#d1d5db'; });
        hideFb('reviewFb');
        document.getElementById('reviewOverlay').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    };

    document.getElementById('reviewBtn').addEventListener('click', submitReview);

    // Close edit/review modals on overlay click
    document.getElementById('editOfferOverlay').addEventListener('click', function(e) { if (e.target === this) { this.style.display='none'; document.body.style.overflow=''; } });
    document.getElementById('reviewOverlay').addEventListener('click', function(e) { if (e.target === this) { this.style.display='none'; document.body.style.overflow=''; } });
    document.getElementById('merchantProfileOverlay').addEventListener('click', function(e) { if (e.target === this) { this.style.display='none'; document.body.style.overflow=''; } });

    // ═══ MERCHANT PROFILE MODAL ═══
    window.openMerchantProfile = function(merchantId) {
        var over = document.getElementById('merchantProfileOverlay');
        var body = document.getElementById('merchantProfileBody');
        over.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        body.innerHTML = '<div style="text-align:center;padding:20px"><i class="fas fa-spinner fa-spin" style="font-size:1.5rem;color:var(--p2p-muted)"></i></div>';

        fetch('handlers/p2p_handler.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json','X-Requested-With': 'XMLHttpRequest'},
            body: JSON.stringify({action: 'get_merchant_profile', merchant_id: merchantId, csrf_token: csrfToken})
        })
        .then(function(r){ return r.json(); })
        .then(function(res){
            if (!res.success || !res.merchant) { body.innerHTML = '<div style="text-align:center;padding:2rem;color:var(--p2p-muted)">Merchant not found.</div>'; return; }
            var m = res.merchant;
            var s = res.stats;
            var reviews = res.reviews || [];
            var avatarHtml = '<img src="assets/avatars/' + (m.avatar||'default.png') + '" alt="" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'" style="width:72px;height:72px;border-radius:50%;object-fit:cover;border:3px solid var(--p2p-accent)">' +
                '<div style="display:none;width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,#8b5cf6,#6366f1);color:#fff;align-items:center;justify-content:center;font-size:1.6rem;font-weight:800;border:3px solid var(--p2p-accent)">' + (m.full_name||m.username).charAt(0).toUpperCase() + '</div>';

            var joined = new Date(m.registered_at);
            var joinedStr = joined.toLocaleDateString('en-US', {year:'numeric', month:'long'});

            var starsHtml = '';
            var fullStars = Math.floor(s.avg_rating);
            var halfStar = (s.avg_rating - fullStars) >= 0.5;
            for (var i=0; i<5; i++) {
                if (i < fullStars) starsHtml += '<i class="fas fa-star" style="color:#d97706;font-size:.85rem"></i>';
                else if (i === fullStars && halfStar) starsHtml += '<i class="fas fa-star-half-alt" style="color:#d97706;font-size:.85rem"></i>';
                else starsHtml += '<i class="far fa-star" style="color:#d1d5db;font-size:.85rem"></i>';
            }

            var reviewHtml = '';
            if (reviews.length === 0) {
                reviewHtml = '<div style="text-align:center;padding:16px;color:var(--p2p-muted);font-size:.75rem">No reviews yet</div>';
            } else {
                reviews.forEach(function(rv){
                    var rvAvatar = '<img src="assets/avatars/' + (rv.avatar||'default.png') + '" alt="" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'" style="width:28px;height:28px;border-radius:50%;object-fit:cover">' +
                        '<div style="display:none;width:28px;height:28px;border-radius:50%;background:#8b5cf6;color:#fff;align-items:center;justify-content:center;font-size:.65rem;font-weight:700">' + (rv.full_name||rv.username).charAt(0).toUpperCase() + '</div>';
                    var rvStars = '';
                    for (var j=0; j<5; j++) rvStars += '<i class="' + (j < parseInt(rv.rating) ? 'fas' : 'far') + ' fa-star" style="color:#d97706;font-size:.6rem"></i>';
                    reviewHtml += '<div style="display:flex;gap:10px;padding:10px 0;border-bottom:1px solid var(--p2p-border)">' +
                        '<div style="flex-shrink:0;width:28px;height:28px">' + rvAvatar + '</div>' +
                        '<div style="flex:1;min-width:0">' +
                            '<div style="display:flex;align-items:center;gap:6px">' +
                                '<span style="font-size:.72rem;font-weight:700;color:var(--p2p-text)">' + (rv.full_name||rv.username) + '</span>' +
                                '<span style="font-size:.55rem;color:var(--p2p-muted)">' + new Date(rv.created_at).toLocaleDateString('en-US', {month:'short', day:'numeric'}) + '</span>' +
                            '</div>' +
                            '<div style="margin:2px 0">' + rvStars + '</div>' +
                            (rv.comment ? '<div style="font-size:.72rem;color:var(--p2p-muted);line-height:1.4">' + rv.comment + '</div>' : '') +
                        '</div>' +
                    '</div>';
                });
            }

            body.innerHTML =
                '<div style="text-align:center;padding:12px 0 16px;border-bottom:1px solid var(--p2p-border);margin-bottom:14px">' +
                    '<div style="width:72px;height:72px;margin:0 auto 10px">' + avatarHtml + '</div>' +
                    '<h3 style="font-size:1.1rem;font-weight:800;color:var(--p2p-text);margin:0">' + (m.full_name||m.username) + '</h3>' +
                    '<p style="font-size:.72rem;color:var(--p2p-muted);margin:3px 0 6px">@' + m.username + ' · Joined ' + joinedStr + '</p>' +
                    '<div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap">' +
                        '<span style="font-size:.6rem;font-weight:700;background:#059669;color:#fff;padding:2px 10px;border-radius:999px"><i class="fas fa-check-circle"></i> Verified Merchant</span>' +
                        (s.avg_rating > 0 ? '<span style="font-size:.6rem;font-weight:700;background:#fef3c7;color:#92400e;padding:2px 10px;border-radius:999px"><i class="fas fa-star"></i> ' + s.avg_rating + ' (' + s.total_reviews + ')</span>' : '') +
                    '</div>' +
                '</div>' +
                '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:14px">' +
                    '<div style="background:var(--p2p-bg);border-radius:10px;padding:10px;text-align:center">' +
                        '<div style="font-size:.58rem;font-weight:700;text-transform:uppercase;color:var(--p2p-muted);margin-bottom:3px">Trades</div>' +
                        '<div style="font-size:1rem;font-weight:900;color:var(--p2p-text)">' + s.completed_trades + '</div>' +
                    '</div>' +
                    '<div style="background:var(--p2p-bg);border-radius:10px;padding:10px;text-align:center">' +
                        '<div style="font-size:.58rem;font-weight:700;text-transform:uppercase;color:var(--p2p-muted);margin-bottom:3px">Fill Rate</div>' +
                        '<div style="font-size:1rem;font-weight:900;color:var(--p2p-text)">' + s.completion_rate + '%</div>' +
                    '</div>' +
                    '<div style="background:var(--p2p-bg);border-radius:10px;padding:10px;text-align:center">' +
                        '<div style="font-size:.58rem;font-weight:700;text-transform:uppercase;color:var(--p2p-muted);margin-bottom:3px">Volume</div>' +
                        '<div style="font-size:1rem;font-weight:900;color:var(--p2p-text)">৳' + s.volume.toLocaleString('en-US', {minimumFractionDigits:0, maximumFractionDigits:0}) + '</div>' +
                    '</div>' +
                '</div>' +
                '<div style="display:flex;gap:8px;margin-bottom:14px">' +
                    '<div style="flex:1;background:var(--p2p-bg);border-radius:10px;padding:10px;text-align:center">' +
                        '<div style="font-size:.58rem;font-weight:700;text-transform:uppercase;color:var(--p2p-muted);margin-bottom:3px">Active Offers</div>' +
                        '<div style="font-size:1rem;font-weight:900;color:var(--p2p-accent)">' + s.active_offers + '</div>' +
                    '</div>' +
                    '<div style="flex:1;background:var(--p2p-bg);border-radius:10px;padding:10px;text-align:center">' +
                        '<div style="font-size:.58rem;font-weight:700;text-transform:uppercase;color:var(--p2p-muted);margin-bottom:3px">Rating</div>' +
                        '<div style="font-size:.85rem;font-weight:700;color:#d97706">' + starsHtml + '</div>' +
                    '</div>' +
                '</div>' +
                '<div style="border-top:1px solid var(--p2p-border);padding-top:12px">' +
                    '<h4 style="font-size:.78rem;font-weight:700;color:var(--p2p-text);margin:0 0 4px"><i class="fas fa-star" style="color:#d97706"></i> Reviews (' + s.total_reviews + ')</h4>' +
                    reviewHtml +
                '</div>';
        })
        .catch(function(){
            body.innerHTML = '<div style="text-align:center;padding:2rem;color:var(--p2p-muted)">Failed to load profile.</div>';
        });
    };

})();
</script>
