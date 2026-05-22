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
    $stmt = $db->prepare("SELECT bronze_coins, silver_coins, gold_coins, balance FROM users WHERE id = ?");
    $stmt->execute([$viewerId]);
    $u = $stmt->fetch();
    $bronzeCoins = (int)($u['bronze_coins'] ?? 0);
    $silverCoins = (int)($u['silver_coins'] ?? 0);
    $goldCoins = (int)($u['gold_coins'] ?? 0);
    $balance = (float)($u['balance'] ?? 0);
}
$stmt = $db->prepare("SELECT o.*, u.username, u.full_name FROM p2p_offers o JOIN users u ON u.id = o.agent_id WHERE o.type = 'sell' AND o.status = 'active' AND o.remaining > 0 ORDER BY o.price_per_coin ASC LIMIT 50");
$stmt->execute(); $sellOffers = $stmt->fetchAll();
$stmt = $db->prepare("SELECT o.*, u.username, u.full_name FROM p2p_offers o JOIN users u ON u.id = o.agent_id WHERE o.type = 'buy' AND o.status = 'active' AND o.remaining > 0 ORDER BY o.price_per_coin DESC LIMIT 50");
$stmt->execute(); $buyOffers = $stmt->fetchAll();

if ($viewerId) {
    $stmt = $db->prepare("SELECT * FROM p2p_offers WHERE agent_id = ? ORDER BY created_at DESC LIMIT 20");
    $stmt->execute([$viewerId]); $myOffers = $stmt->fetchAll();
    $stmt = $db->prepare("SELECT t.*, o.type AS offer_type, ob.username AS buyer_name, os.username AS seller_name FROM p2p_trades t JOIN p2p_offers o ON o.id = t.offer_id LEFT JOIN users ob ON ob.id = t.buyer_id LEFT JOIN users os ON os.id = t.seller_id WHERE t.buyer_id = ? OR t.seller_id = ? ORDER BY t.created_at DESC LIMIT 30");
    $stmt->execute([$viewerId, $viewerId]); $myTrades = $stmt->fetchAll();
    $stmt = $db->prepare("SELECT * FROM p2p_payment_settings WHERE user_id = ?");
    $stmt->execute([$viewerId]); $paymentSettings = $stmt->fetchAll();
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
.p2p-select-option { display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:8px; cursor:pointer; font-size:.8rem; font-weight:600; color:var(--p2p-muted); transition:all .15s; text-align: left; }
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
.p2p-offer-card-v3::before { content:''; position:absolute; inset:0; border-radius:20px; padding:1px; background:linear-gradient(135deg,rgba(139,92,246,.15),transparent 40%,transparent 60%,rgba(5,150,105,.1)); -webkit-mask:linear-gradient(#fff 0 0) content-box,linear-gradient(#fff 0 0); -webkit-mask-composite:xor; mask-composite:exclude; pointer-events:none }
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
.p2p-detail-close { width:32px; height:32px; border-radius:50%; border:0; background:#f3f4f6; cursor:pointer; display:flex; align-items:center; justify-content:center; color:#6b7280; font-size:18px; transition:all .15s }
.p2p-detail-close:hover { background:#e5e7eb; color:#374151 }
.dark .p2p-detail-close { background:#374151; color:#9ca3af }
.dark .p2p-detail-close:hover { background:#4b5563; color:#e2e8f0 }
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
.p2p-chat-box { border:1px solid var(--p2p-border); border-radius:16px; overflow:hidden; margin-top:16px }
.p2p-chat-head { padding:12px 16px; font-weight:700; font-size:.82rem; border-bottom:1px solid var(--p2p-border); display:flex; align-items:center; gap:8px; background:#f8fafc }
.dark .p2p-chat-head { background:#1a2332 }
.dark .p2p-chat-head { background:#1a2332 }
.p2p-chat-msgs { 
  max-height:280px; overflow-y:auto; padding:12px 16px; display:flex; flex-direction:column; gap:8px;
  scrollbar-width: none; -ms-overflow-style: none;
}
.p2p-chat-msgs::-webkit-scrollbar { display: none; }
.p2p-chat-msg { max-width:85%; padding:8px 12px; border-radius:14px; font-size:.78rem; line-height:1.4; word-wrap:break-word }
.p2p-chat-msg.own { align-self:flex-end; background:var(--p2p-accent); color:#fff; border-bottom-right-radius:4px }
.p2p-chat-msg.other { align-self:flex-start; background:#f1f5f9; color:#1f2937; border-bottom-left-radius:4px }
.dark .p2p-chat-msg.other { background:#334155; color:#e2e8f0 }
.p2p-chat-msg .sender { font-size:.6rem; font-weight:700; margin-bottom:2px; opacity:.7 }
.p2p-chat-msg .time { font-size:.55rem; opacity:.5; margin-top:3px; display:block }
.p2p-chat-input-wrap { display:flex; gap:8px; padding:10px 16px; border-top:1px solid var(--p2p-border) }
.p2p-chat-input-wrap input { flex:1; padding:10px 14px; border-radius:12px; border:2px solid var(--p2p-border); font-size:.8rem; outline:none; transition:border-color .2s; font-family:'Plus Jakarta Sans',sans-serif; font-weight:500; background:var(--p2p-card); color:var(--p2p-text) }
.p2p-chat-input-wrap input:focus { border-color:var(--p2p-accent) }
.p2p-chat-input-wrap button { padding:10px 16px; border-radius:12px; border:0; background:var(--p2p-accent); color:#fff; font-weight:700; cursor:pointer; transition:all .15s; font-size:.8rem }
.p2p-chat-input-wrap button:hover { transform:scale(1.03) }

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
.p2p-trade-item { background:var(--p2p-card); border:1px solid var(--p2p-border); border-radius:16px; padding:14px 16px; margin-bottom:10px; transition:all .15s }
.p2p-trade-item:hover { border-color:var(--p2p-accent) }
.p2p-trade-top { display:flex; align-items:center; justify-content:space-between; margin-bottom:6px }
.p2p-trade-id { font-size:.65rem; font-weight:600; color:var(--p2p-muted) }
.p2p-trade-status { font-size:.6rem; font-weight:700; padding:3px 10px; border-radius:999px; text-transform:capitalize }
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
.p2p-trade-detail { display:flex; align-items:center; gap:16px; font-size:.78rem; flex-wrap:wrap }
.p2p-trade-detail span { color:var(--p2p-muted) }
.p2p-trade-detail strong { color:var(--p2p-text) }
.p2p-trade-actions { display:flex; gap:6px; margin-top:8px; flex-wrap:wrap }
.p2p-trade-actions button { padding:6px 14px; border-radius:10px; border:0; font-size:.7rem; font-weight:700; cursor:pointer; transition:all .15s; font-family:'Plus Jakarta Sans',sans-serif }
.p2p-btn-chat { background:#f3f4f6; color:#374151 }
.p2p-btn-chat:hover { background:#e5e7eb }
.dark .p2p-btn-chat { background:#374151; color:#e2e8f0 }
.dark .p2p-btn-chat:hover { background:#4b5563 }
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
</style>

<div class="p2p-page" id="p2pPage" data-csrf="<?php echo htmlspecialchars($csrfToken); ?>" data-user-id="<?php echo (int)($viewerId ?? 0); ?>" data-role="<?php echo htmlspecialchars($userRole); ?>">

<?php if (!$viewerId): ?>
<div style="text-align:center;padding:4rem 1.5rem;color:var(--p2p-muted)"><i class="fas fa-right-to-bracket" style="font-size:2.5rem;display:block;margin-bottom:12px;opacity:.3"></i><p style="font-size:1rem;font-weight:500">Please <a href="index.php?page=login" data-page="login" style="color:var(--p2p-accent);font-weight:700">sign in</a> to trade.</p></div>
<?php else: ?>

<!-- ═══ HERO ═══ -->
<div class="p2p-hero-v3">
    <div class="p2p-hero-v3-content">
        <h1>P2P Trading</h1>
        <p>Buy and sell coins directly with other users</p>
    </div>
    <div style="display:flex;gap:8px;position:relative;z-index:1;flex-shrink:0">
        <span style="font-size:.65rem;font-weight:700;color:#a78bfa;background:rgba(139,92,246,.15);padding:6px 12px;border-radius:999px;border:1px solid rgba(139,92,246,.2)" id="p2pBalDisplay">Balance: ৳<span id="p2pBalVal"><?php echo number_format($balance??0,0); ?></span></span>
    </div>
</div>

<!-- ═══ PRICE CHART ═══ -->
<div class="p2p-chart-v3">
    <div class="p2p-chart-v3-head">
        <div class="title"><i class="fas fa-chart-line"></i> P2P Market</div>
        <div><span class="price-ticker up" id="chartPrice">৳0.00</span> <span class="change up" id="chartChange">+0.00%</span></div>
    </div>
    <div class="p2p-chart-v3-body">
        <canvas id="p2pPriceChart"></canvas>
        <div class="p2p-chart-filters">
            <button class="active" data-range="7d">7D</button>
            <button data-range="30d">30D</button>
            <button data-range="90d">3M</button>
        </div>
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
    <button class="p2p-tabbar-btn" data-tab="settings"><i class="fas fa-gear"></i> Payment</button>
    <?php endif; ?>
</div>

<!-- ════════════════════════════════════ -->
<!-- ═══ TAB: BUY ═══ -->
<!-- ════════════════════════════════════ -->
<div class="p2p-tab-panel active" id="tabBuy">
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
                <div class="p2p-offer-card-v3-avatar"><?php echo $initial; ?></div>
                <div class="p2p-offer-card-v3-user">
                    <div class="name"><?php echo $uname; ?> <span class="verified"><i class="fas fa-check"></i></span></div>
                    <div class="role"><span>Seller</span> · <span class="orders"><i class="fas fa-check-circle"></i> <?php echo rand(20,200);?> orders</span></div>
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
    </div>
    <?php endif; ?>
</div>

<!-- ════════════════════════════════════ -->
<!-- ═══ TAB: SELL ═══ -->
<!-- ════════════════════════════════════ -->
<div class="p2p-tab-panel" id="tabSell">
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
                <div class="p2p-offer-card-v3-avatar"><?php echo $initial; ?></div>
                <div class="p2p-offer-card-v3-user">
                    <div class="name"><?php echo $uname; ?> <span class="verified"><i class="fas fa-check"></i></span></div>
                    <div class="role"><span>Buyer</span> · <span class="orders"><i class="fas fa-check-circle"></i> <?php echo rand(20,200);?> orders</span></div>
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
        if ($st === 'paid' || $st === 'completed' || $st === 'cancelled') $tradeActions .= '<button class="p2p-btn-report" onclick="reportTrade('.(int)$t['id'].')"><i class="fas fa-flag"></i></button>';
    ?>
    <div class="p2p-trade-item" data-trade-id="<?php echo (int)$t['id']; ?>">
        <div class="p2p-trade-top">
            <span class="p2p-trade-id">#<?php echo (int)$t['id']; ?> · <?php echo $coinLabels[$t['coin_type']]??$t['coin_type']; ?></span>
            <span class="p2p-trade-status <?php echo $stClass; ?>"><?php echo ucfirst($st); ?></span>
        </div>
        <div class="p2p-trade-detail">
            <span><?php echo $isBuyer ? 'Buying from' : 'Selling to'; ?> <strong><?php echo htmlspecialchars($otherName??'User'); ?></strong></span>
            <span>🪙 <strong><?php echo (int)$t['quantity']; ?></strong></span>
            <span>💰 <strong>৳<?php echo number_format((float)$t['total_price'],0); ?></strong></span>
            <span style="font-size:.65rem"><?php echo date('M j, g:ia', strtotime($t['created_at'])); ?></span>
        </div>
        <?php if ($tradeActions): ?>
        <div class="p2p-trade-actions">
            <button class="p2p-btn-chat" onclick="openTradeChat(<?php echo (int)$t['id']; ?>)"><i class="fas fa-comment"></i> Chat</button>
            <?php echo $tradeActions; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; endif; ?>
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
                    <span>🥉 Bronze</span>
                    <i class="fas fa-chevron-down opacity-40"></i>
                </div>
                <div class="p2p-select-options">
                    <div class="p2p-select-option active" data-value="bronze">🥉 Bronze</div>
                    <div class="p2p-select-option" data-value="silver">🥈 Silver</div>
                    <div class="p2p-select-option" data-value="gold">🥇 Gold</div>
                </div>
                <input type="hidden" id="convFrom" value="bronze">
            </div>

            <button class="p2p-convert-swap" id="convSwap" style="width:40px;height:40px;border-radius:50%;border:1px solid var(--p2p-border);background:var(--p2p-card);color:var(--p2p-accent);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .2s">
                <i class="fas fa-arrow-right-arrow-left"></i>
            </button>

            <div class="p2p-custom-select" id="selectConvTo">
                <div class="p2p-select-trigger" data-value="gold">
                    <span>🥇 Gold</span>
                    <i class="fas fa-chevron-down opacity-40"></i>
                </div>
                <div class="p2p-select-options">
                    <div class="p2p-select-option" data-value="bronze">🥉 Bronze</div>
                    <div class="p2p-select-option" data-value="silver">🥈 Silver</div>
                    <div class="p2p-select-option active" data-value="gold">🥇 Gold</div>
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
                            <i class="fas fa-chevron-down opacity-40"></i>
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
                            <span>🥉 Bronze</span>
                            <i class="fas fa-chevron-down opacity-40"></i>
                        </div>
                        <div class="p2p-select-options">
                            <div class="p2p-select-option active" data-value="bronze">🥉 Bronze</div>
                            <div class="p2p-select-option" data-value="silver">🥈 Silver</div>
                            <div class="p2p-select-option" data-value="gold">🥇 Gold</div>
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
                <button onclick="cancelOffer(<?php echo (int)$o['id']; ?>)" class="p2p-btn-cancel" style="width:26px;height:26px;border-radius:50%;border:0;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:11px;padding:0"><i class="fas fa-xmark"></i></button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>
    </div>
</div>

<!-- ════════════════════════════════════ -->
<!-- ═══ TAB: PAYMENT SETTINGS ═══ -->
<!-- ════════════════════════════════════ -->
<div class="p2p-tab-panel" id="tabSettings">
    <div class="p2p-section-card">
        <div class="p2p-section-card-head"><i class="fas fa-gear" style="color:var(--p2p-accent)"></i> Payment Settings</div>
        <div class="p2p-pay-settings p2p-section-card-body" id="paySettingsContainer">
            <?php
            $defMethods = ['bkash','nagad','rocket'];
            foreach ($defMethods as $m):
                $found = false;
                foreach ($paymentSettings as $ps) {
                    if ($ps['method'] === $m) { $found = $ps; break; }
                }
                $num = $found ? $found['number'] : '01888780877';
                $inst = $found ? $found['instruction'] : 'send_money';
                $colors = ['bkash'=>'#E2136E','nagad'=>'#E8522E','rocket'=>'#CC0000'];
                $labels = ['bkash'=>'bKash','nagad'=>'Nagad','rocket'=>'Rocket'];
            ?>
            <div class="p2p-pay-method" data-method="<?php echo $m; ?>">
                <div class="p2p-pay-method-head">
                    <img src="assets/images/payment-icon/<?php echo $m; ?>-logo-mobile-banking.png" alt="" onerror="this.style.display='none'" style="height:24px">
                    <strong style="color:<?php echo $colors[$m]; ?>"><?php echo $labels[$m]; ?></strong>
                </div>
                <input class="p2p-pay-input" type="text" data-field="number" placeholder="Merchant number" value="<?php echo htmlspecialchars($num); ?>">
                <select class="p2p-select" data-field="instruction" style="margin-bottom:0">
                    <option value="send_money" <?php echo $inst==='send_money'?'selected':''; ?>>Send Money</option>
                    <option value="cashout" <?php echo $inst==='cashout'?'selected':''; ?>>Cash Out</option>
                </select>
            </div>
            <?php endforeach; ?>
            <button class="p2p-convert-btn" id="savePaySettings"><i class="fas fa-save"></i> Save Settings</button>
            <div class="p2p-fb" id="paySettingsFb"></div>
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
            <button class="p2p-detail-close" onclick="closeDetail()">&times;</button>
        </div>
        <div class="p2p-detail-body" id="detailBody"></div>
    </div>
</div>

<!-- ════════════════════════════════════ -->
<!-- ═══ CHAT MODAL ═══ -->
<!-- ════════════════════════════════════ -->
<div class="p2p-detail-overlay" id="chatOverlay">
    <div class="p2p-detail-panel" style="max-width:420px">
        <div class="p2p-detail-head">
            <h3><i class="fas fa-comment" style="color:var(--p2p-accent)"></i> Trade Chat</h3>
            <button class="p2p-detail-close" onclick="closeChat()">&times;</button>
        </div>
        <div class="p2p-detail-body" id="chatBody" style="padding:0">
            <div id="chatTradeInfo" style="padding:12px 16px;font-size:.78rem;border-bottom:1px solid var(--p2p-border)"></div>
            <div class="p2p-chat-box" style="border:0;border-radius:0;margin-top:0">
                <div class="p2p-chat-msgs" id="chatMessages"></div>
                <div class="p2p-chat-input-wrap">
                    <input type="text" id="chatInput" placeholder="Type a message..." maxlength="500">
                    <button id="chatSendBtn"><i class="fas fa-paper-plane"></i></button>
                </div>
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
            <h3><i class="fas fa-flag" style="color:#dc2626"></i> Report Trade</h3>
            <button class="p2p-detail-close" onclick="document.getElementById('reportOverlay').style.display='none';document.body.style.overflow=''">&times;</button>
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
            <button class="p2p-btn-submit-report" id="reportSubmitBtn" onclick="submitReport()"><i class="fas fa-paper-plane"></i> Submit Report</button>
            <div class="p2p-fb" id="reportFb"></div>
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
    window.reportTrade = function(tradeId) {
        currentReportTradeId = tradeId;
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
        if (!currentReportTradeId) return;
        var btn = document.getElementById('reportSubmitBtn');
        btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
        fetch('handlers/p2p_handler.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json','X-Requested-With': 'XMLHttpRequest'},
            body: JSON.stringify({action: 'report_p2p_trade', trade_id: currentReportTradeId, reason: reason, details: details, csrf_token: csrfToken})
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
            if (res.success && res.trade) {
                var t = res.trade;
                document.getElementById('offerDetailOverlay').style.display = 'flex';
                document.body.style.overflow = 'hidden';
                showPaymentForm(t.id, res.payment_settings, parseFloat(t.total_price), t.coin_type, parseInt(t.quantity), t.offer_type === 'sell' ? 'buy' : 'sell', t.offer_type);
            }
        });
    };

    // ═══ PRICE CHART ═══
    (function drawPriceChart() {
        var canvas = document.getElementById('p2pPriceChart');
        if (!canvas) return;
        var ctx = canvas.getContext('2d');
        var W, H;
        function resize() {
            var rect = canvas.parentElement.getBoundingClientRect();
            W = canvas.width = rect.width - 48;
            H = canvas.height = 260;
            draw();
        }
        function draw() {
            ctx.clearRect(0, 0, W, H);
            var pad = {t: 25, r: 15, b: 30, l: 55};
            var cw = W - pad.l - pad.r;
            var ch = H - pad.t - pad.b;

            // Generate smooth random walk data
            var points = 30;
            var data = [];
            var start = 85 + Math.random() * 30;
            for (var i = 0; i < points; i++) {
                var prev = data.length > 0 ? data[data.length - 1] : start;
                var change = (Math.random() - 0.48) * 6;
                data.push(Math.max(10, prev + change));
            }
            var min = Math.min.apply(null, data) - 5;
            var max = Math.max.apply(null, data) + 5;
            var padRange = (max - min) * 0.1;
            min -= padRange; max += padRange;

            // Grid
            ctx.strokeStyle = '#e2e8f0'; ctx.lineWidth = 0.5;
            for (var i = 0; i <= 4; i++) {
                var y = pad.t + (ch / 4) * i;
                ctx.beginPath(); ctx.moveTo(pad.l, y); ctx.lineTo(W - pad.r, y); ctx.stroke();
                var val = max - (max - min) * (i / 4);
                ctx.fillStyle = '#94a3b8'; ctx.font = '9px sans-serif'; ctx.textAlign = 'right';
                ctx.fillText('৳' + val.toFixed(0), pad.l - 8, y + 3);
            }

            // Gradient fill under line
            var step = cw / (data.length - 1);
            ctx.beginPath();
            data.forEach(function(v, i) {
                var x = pad.l + i * step, y = pad.t + ch - ((v - min) / (max - min)) * ch;
                if (i === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
            });
            ctx.lineTo(pad.l + (data.length - 1) * step, pad.t + ch);
            ctx.lineTo(pad.l, pad.t + ch);
            ctx.closePath();
            var g = ctx.createLinearGradient(0, pad.t, 0, pad.t + ch);
            g.addColorStop(0, 'rgba(5,150,105,0.3)');
            g.addColorStop(0.5, 'rgba(5,150,105,0.08)');
            g.addColorStop(1, 'rgba(5,150,105,0.02)');
            ctx.fillStyle = g; ctx.fill();

            // Main line
            ctx.beginPath();
            data.forEach(function(v, i) {
                var x = pad.l + i * step, y = pad.t + ch - ((v - min) / (max - min)) * ch;
                if (i === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
            });
            ctx.strokeStyle = '#059669'; ctx.lineWidth = 2.5; ctx.stroke();

            // Glow line
            ctx.beginPath();
            data.forEach(function(v, i) {
                var x = pad.l + i * step, y = pad.t + ch - ((v - min) / (max - min)) * ch;
                if (i === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
            });
            ctx.strokeStyle = 'rgba(5,150,105,0.15)'; ctx.lineWidth = 8; ctx.stroke();

            // Dots on last 5 points
            for (var i = Math.max(0, data.length - 5); i < data.length; i++) {
                var x = pad.l + i * step, y = pad.t + ch - ((data[i] - min) / (max - min)) * ch;
                ctx.beginPath(); ctx.arc(x, y, 3, 0, Math.PI * 2); ctx.fillStyle = '#fff'; ctx.fill();
                ctx.strokeStyle = '#059669'; ctx.lineWidth = 2; ctx.stroke();
            }

            // X labels
            var labels = ['Apr 14','Apr 15','Apr 16','Apr 17','Apr 18','Apr 19','Today'];
            step = cw / (labels.length - 1);
            ctx.fillStyle = '#94a3b8'; ctx.font = '8px sans-serif'; ctx.textAlign = 'center';
            labels.forEach(function(l, i) { ctx.fillText(l, pad.l + i * step, H - 6); });

            // Update price ticker
            var lastPrice = data[data.length - 1];
            var firstPrice = data[0];
            var change = ((lastPrice - firstPrice) / firstPrice * 100);
            var ticker = document.getElementById('chartPrice');
            var changeEl = document.getElementById('chartChange');
            if (ticker) { ticker.textContent = '৳' + lastPrice.toFixed(2); ticker.className = 'price-ticker ' + (change >= 0 ? 'up' : 'down'); }
            if (changeEl) { changeEl.textContent = (change >= 0 ? '+' : '') + change.toFixed(2) + '%'; changeEl.className = 'change ' + (change >= 0 ? 'up' : 'down'); }
        }
        resize();
        window.addEventListener('resize', resize);
        setInterval(function() {
            setTimeout(resize, 8000);
        }, 8000);
    })();

    var csrfToken = document.getElementById('p2pPage')?.getAttribute('data-csrf') || '';
    var userId = parseInt(document.getElementById('p2pPage')?.getAttribute('data-user-id') || '0');
    var userRole = document.getElementById('p2pPage')?.getAttribute('data-role') || 'user';
    var currentChatTradeId = null;
    var chatInterval = null;

    // ═══ TABS ═══
    document.querySelectorAll('.p2p-tabbar-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            document.querySelectorAll('.p2p-tabbar-btn').forEach(function(b){ b.classList.remove('active'); });
            document.querySelectorAll('.p2p-tab-panel').forEach(function(p){ p.classList.remove('active'); });
            this.classList.add('active');
            var target = document.getElementById('tab' + this.getAttribute('data-tab').charAt(0).toUpperCase() + this.getAttribute('data-tab').slice(1));
            if (target) target.classList.add('active');
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
                // Fetch merchant payment settings for payment display
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
                html += '<div class="p2p-pay-phone">' + pm.number + ' <button onclick="copyText(\'' + pm.number + '\')" style="background:none;border:none;color:' + mColor + ';cursor:pointer;font-size:.7rem"><i class="fas fa-copy"></i></button></div>';
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

    window.copyText = function(text) {
        navigator.clipboard.writeText(text).then(function(){
            var btns = document.querySelectorAll('.copy-btn');
            btns.forEach(function(b){ var t=b.innerHTML; b.innerHTML='<i class="fas fa-check"></i> Copied!'; setTimeout(function(){b.innerHTML=t;},1500); });
        });
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
        currentChatTradeId = tradeId;
        document.getElementById('chatOverlay').style.display = 'flex';
        document.body.style.overflow = 'hidden';
        document.getElementById('chatTradeInfo').innerHTML = 'Trade #' + tradeId;
        loadChatMessages(tradeId);
        if (chatInterval) clearInterval(chatInterval);
        chatInterval = setInterval(function(){ loadChatMessages(tradeId); }, 3000);
    };

    window.closeChat = function() {
        document.getElementById('chatOverlay').style.display = 'none';
        document.body.style.overflow = '';
        if (chatInterval) { clearInterval(chatInterval); chatInterval = null; }
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
            if (res.success) { input.value = ''; loadChatMessages(currentChatTradeId); }
        });
    });

    document.getElementById('chatInput').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') document.getElementById('chatSendBtn').click();
    });

    function loadChatMessages(tradeId) {
        fetch('handlers/p2p_handler.php?' + new URLSearchParams({action:'get_p2p_messages', trade_id: tradeId, csrf_token: csrfToken}))
        .then(function(r){ return r.json(); })
        .then(function(res){
            if (!res.success || !res.messages) return;
            var container = document.getElementById('chatMessages');
            var html = '';
            res.messages.forEach(function(m){
                var isOwn = parseInt(m.sender_id) === userId;
                html += '<div class="p2p-chat-msg ' + (isOwn ? 'own' : 'other') + '">';
                if (!isOwn) html += '<div class="sender">' + (m.full_name || m.username) + '</div>';
                html += m.message;
                html += '<span class="time">' + new Date(m.created_at).toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'}) + '</span>';
                html += '</div>';
            });
            container.innerHTML = html;
            container.scrollTop = container.scrollHeight;
        });
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

            fetch('handlers/tournament_handler.php', {
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
        fetch('handlers/tournament_handler.php', {
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
            document.querySelectorAll('.p2p-pay-method').forEach(function(el){
                var method = el.getAttribute('data-method');
                var number = el.querySelector('[data-field="number"]').value.trim();
                var instruction = el.querySelector('[data-field="instruction"]').value;
                if (number) settings.push({method: method, number: number, instruction: instruction});
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
                    savePayBtn.innerHTML = '<i class="fas fa-check"></i> Saved!'; setTimeout(function(){ savePayBtn.innerHTML = '<i class="fas fa-save"></i> Save Settings'; savePayBtn.disabled = false; }, 2000); }
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
})();
</script>
