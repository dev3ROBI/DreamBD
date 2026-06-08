<?php
$db = Database::getInstance()->getConnection();
$viewerId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['role'] ?? 'user';
$userName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? '';
$security = new Security();
$csrfToken = $security->generateCSRFToken();

$players = getMarketPlayers($db, 'free_agent');
$auctions = getActiveAuctions($db);
$myClubs = $viewerId ? getUserClubs($db, $viewerId) : [];
$playerDetail = null;
$detailUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if ($detailUserId) $playerDetail = getPlayerDetail($db, $detailUserId);
?>
<style>
:root { --pm-primary:#7c3aed; --pm-card:#fff; --pm-bg:#f5f7fa; --pm-border:#e2e8f0; --pm-text:#1f2937; --pm-muted:#6b7280; --pm-green:#059669; --pm-red:#dc2626 }
.dark { --pm-card:#1e293b; --pm-bg:#0f172a; --pm-border:#334155; --pm-text:#f1f5f9; --pm-muted:#94a3b8 }

.pm-page { max-width:1100px; margin:0 auto; padding:0 16px 3rem; font-family:'Plus Jakarta Sans',sans-serif }
.pm-hero { padding:28px 24px; border-radius:24px; background:linear-gradient(135deg,#0f172a,#1e1b4b,#1a0533); margin:0 0 24px; position:relative; overflow:hidden; border:1px solid rgba(139,92,246,.15) }
.pm-hero::before { content:''; position:absolute; top:-120px; right:-80px; width:350px; height:350px; border-radius:50%; background:radial-gradient(circle,rgba(236,72,153,.15),transparent 70%) }
.pm-hero-content { position:relative; z-index:1 }
.pm-hero h1 { font-size:1.7rem; font-weight:900; color:#fff; margin:0; letter-spacing:-.03em }
.pm-hero p { font-size:.85rem; color:#94a3b8; margin:4px 0 0 }

.pm-btn { display:inline-flex; align-items:center; gap:8px; padding:10px 20px; border-radius:12px; font-size:.8rem; font-weight:700; border:none; cursor:pointer; font-family:'Plus Jakarta Sans',sans-serif; transition:all .2s; text-decoration:none }
.pm-btn-primary { background:#7c3aed; color:#fff }
.pm-btn-primary:hover { background:#6d28d9; transform:translateY(-1px) }
.pm-btn-sm { padding:7px 14px; font-size:.75rem; border-radius:8px }
.pm-btn-outline { background:transparent; color:var(--pm-primary, #7c3aed); border:2px solid var(--pm-primary, #7c3aed) }
.pm-btn-outline:hover { background:rgba(124,58,237,.08) }
.pm-btn-danger { background:#dc2626; color:#fff }
.pm-btn-danger:hover { background:#b91c1c }
.pm-btn-ghost { background:transparent; color:var(--pm-muted); border:1px solid var(--pm-border) }
.pm-btn-ghost:hover { background:var(--pm-bg) }

.pm-tabs { display:flex; gap:4px; margin-bottom:20px; background:var(--pm-card); border-radius:14px; padding:4px; border:1px solid var(--pm-border) }
.pm-tab { flex:1; padding:10px; border-radius:10px; border:none; font-size:.78rem; font-weight:700; cursor:pointer; background:transparent; color:var(--pm-muted); font-family:'Plus Jakarta Sans',sans-serif; transition:all .2s }
.pm-tab:hover { color:var(--pm-text) }
.pm-tab.active { background:#7c3aed; color:#fff }

.pm-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:14px }
.pm-card { background:var(--pm-card); border-radius:18px; padding:18px; border:1px solid var(--pm-border); transition:all .25s; position:relative; overflow:hidden }
.pm-card:hover { transform:translateY(-2px); box-shadow:0 8px 24px rgba(0,0,0,.06); border-color:var(--pm-primary, #7c3aed) }
.pm-card-top { display:flex; align-items:center; gap:12px; margin-bottom:10px }
.pm-card-avatar { width:44px; height:44px; border-radius:50%; object-fit:cover; border:2px solid var(--pm-border) }
.pm-card-info { flex:1 }
.pm-card-name { font-size:.88rem; font-weight:800; color:var(--pm-text); margin:0 }
.pm-card-status { font-size:.65rem; font-weight:700; padding:2px 8px; border-radius:999px; display:inline-block }
.pm-card-status.free_agent { background:rgba(148,163,184,.15); color:var(--pm-muted) }
.pm-card-status.active { background:rgba(16,185,129,.12); color:var(--pm-green) }
.pm-card-body { margin-bottom:10px }
.pm-card-row { display:flex; justify-content:space-between; font-size:.75rem; color:var(--pm-muted); padding:3px 0; border-bottom:1px solid var(--pm-border) }
.pm-card-row:last-child { border-bottom:none }
.pm-card-row span:last-child { font-weight:700; color:var(--pm-text) }
.pm-card-actions { display:flex; gap:6px; flex-wrap:wrap }
.pm-card-badge { position:absolute; top:10px; right:10px; font-size:.55rem; font-weight:800; padding:3px 8px; border-radius:999px; text-transform:uppercase; letter-spacing:.3px }
.pm-card-badge.auction { background:rgba(245,158,11,.12); color:#d97706 }

.pm-detail { background:var(--pm-card); border-radius:24px; padding:24px; border:1px solid var(--pm-border); margin-bottom:24px; box-shadow:0 4px 20px rgba(0,0,0,.04) }
.pm-detail-head { display:flex; align-items:center; gap:20px; margin-bottom:20px }
.pm-detail-avatar { width:72px; height:72px; border-radius:50%; object-fit:cover; border:3px solid var(--pm-border) }
.pm-detail-info { flex:1 }
.pm-detail-name { font-size:1.3rem; font-weight:900; color:var(--pm-text); margin:0; line-height:1.2 }
.pm-detail-meta { font-size:.8rem; color:var(--pm-muted); margin-top:2px }
.pm-detail-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(120px,1fr)); gap:10px; margin-bottom:16px }
.pm-detail-stat { padding:12px; border-radius:12px; background:var(--pm-bg); text-align:center }
.pm-detail-stat-value { font-size:1.2rem; font-weight:800; color:var(--pm-text) }
.pm-detail-stat-label { font-size:.65rem; font-weight:600; color:var(--pm-muted); text-transform:uppercase; letter-spacing:.3px }
.pm-detail-actions { display:flex; gap:10px; flex-wrap:wrap }

.pm-transfer-list { display:flex; flex-direction:column; gap:6px }
.pm-transfer-item { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:10px; background:var(--pm-bg); font-size:.78rem; color:var(--pm-text) }
.pm-transfer-item i { font-size:.7rem; opacity:.5 }

.pm-modal-overlay { position:fixed; inset:0; z-index:200; background:rgba(0,0,0,.5); backdrop-filter:blur(4px); display:none; align-items:center; justify-content:center; padding:16px }
.pm-modal-overlay.active { display:flex }
.pm-modal-panel { background:var(--pm-card); border-radius:24px; padding:24px; max-width:460px; width:100%; max-height:90vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,.15); animation:pmModalIn .25s ease }
@keyframes pmModalIn { from { opacity:0; transform:scale(.95) translateY(10px) } to { opacity:1; transform:scale(1) translateY(0) } }
.pm-modal-title { font-size:1.1rem; font-weight:800; margin:0 0 16px; color:var(--pm-text) }

.pm-form { display:flex; flex-direction:column; gap:14px }
.pm-form-group { display:flex; flex-direction:column; gap:4px }
.pm-form-label { font-size:.75rem; font-weight:700; color:var(--pm-muted); text-transform:uppercase; letter-spacing:.3px }
.pm-form-input, .pm-form-select { padding:10px 14px; border-radius:10px; border:2px solid var(--pm-border); font-size:.85rem; background:var(--pm-bg); color:var(--pm-text); font-family:'Plus Jakarta Sans',sans-serif; transition:border-color .2s; outline:none }
.pm-form-input:focus, .pm-form-select:focus { border-color:#7c3aed; box-shadow:0 0 0 3px rgba(124,58,237,.1) }
.pm-form-row { display:grid; grid-template-columns:1fr 1fr; gap:12px }

.pm-empty { text-align:center; padding:40px 20px; color:var(--pm-muted) }
.pm-empty i { font-size:2.5rem; margin-bottom:12px; opacity:.4 }
.pm-empty p { font-size:.9rem; margin:0 }

@media (max-width:768px) {
    .pm-grid { grid-template-columns:1fr }
    .pm-detail-head { flex-direction:column; text-align:center }
    .pm-detail-actions { justify-content:center }
    .pm-detail-stats { grid-template-columns:1fr 1fr }
    .pm-form-row { grid-template-columns:1fr }
}
</style>

<div class="pm-page" id="pmPage">
    <div class="pm-hero">
        <div class="pm-hero-content">
            <h1><i class="fas fa-gavel"></i> Player Market</h1>
            <p>Buy, sell, and bid on players. Build your dream squad.</p>
        </div>
    </div>

    <?php if ($playerDetail): ?>
    <!-- Player Detail -->
    <div class="pm-detail" id="playerDetail">
        <a href="index.php?page=player-market" class="pm-btn pm-btn-ghost pm-btn-sm" style="margin-bottom:12px">&larr; Back to Market</a>
        <div class="pm-detail-head">
            <img src="assets/images/avatars/<?php echo htmlspecialchars($playerDetail['avatar'] ?? 'default.png'); ?>" alt="" class="pm-detail-avatar" onerror="this.src='assets/images/avatars/default.png'">
            <div class="pm-detail-info">
                <div class="pm-detail-name"><?php echo htmlspecialchars($playerDetail['full_name'] ?: $playerDetail['username']); ?></div>
                <div class="pm-detail-meta">
                    <?php if ($playerDetail['nickname']): ?>@<?php echo htmlspecialchars($playerDetail['nickname']); ?> · <?php endif; ?>
                    Status: <?php echo $playerDetail['status']; ?>
                    <?php if ($playerDetail['club_name']): ?> · <span style="color:<?php echo htmlspecialchars($playerDetail['club_colour'] ?? '#7c3aed'); ?>;font-weight:700"><?php echo htmlspecialchars($playerDetail['club_name']); ?></span><?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($playerDetail['stats']): $s = $playerDetail['stats']; ?>
        <div class="pm-detail-stats">
            <div class="pm-detail-stat"><div class="pm-detail-stat-value"><?php echo (int)$s['total_matches']; ?></div><div class="pm-detail-stat-label">Matches</div></div>
            <div class="pm-detail-stat"><div class="pm-detail-stat-value"><?php echo (int)$s['total_goals']; ?></div><div class="pm-detail-stat-label">Goals</div></div>
            <div class="pm-detail-stat"><div class="pm-detail-stat-value"><?php echo (int)$s['total_assists']; ?></div><div class="pm-detail-stat-label">Assists</div></div>
            <div class="pm-detail-stat"><div class="pm-detail-stat-value"><?php echo (int)$s['total_wins']; ?></div><div class="pm-detail-stat-label">Wins</div></div>
            <div class="pm-detail-stat"><div class="pm-detail-stat-value"><?php echo (int)$s['total_kills']; ?></div><div class="pm-detail-stat-label">Kills</div></div>
        </div>
        <?php endif; ?>

        <div class="pm-detail-actions">
            <?php if ($viewerId && $playerDetail['status'] === 'free_agent' && $playerDetail['owner_id'] != $viewerId): ?>
            <button class="pm-btn pm-btn-primary pm-btn-sm" onclick="openBuyModal(<?php echo $playerDetail['id']; ?>, '<?php echo htmlspecialchars($playerDetail['full_name'] ?: $playerDetail['username']); ?>', <?php echo $playerDetail['market_value'] ?: 0; ?>)"><i class="fas fa-shopping-cart"></i> Buy (৳<?php echo number_format($playerDetail['market_value'] ?: 0); ?>)</button>
            <?php endif; ?>
            <?php if ($viewerId && (int)$playerDetail['owner_id'] === $viewerId): ?>
            <button class="pm-btn pm-btn-outline pm-btn-sm" onclick="openAuctionModal(<?php echo $playerDetail['id']; ?>)"><i class="fas fa-gavel"></i> List for Auction</button>
            <button class="pm-btn pm-btn-danger pm-btn-sm" onclick="releasePlayer(<?php echo $playerDetail['id']; ?>)"><i class="fas fa-user-slash"></i> Release</button>
            <?php endif; ?>
            <?php if ($viewerId && $myClubs && $playerDetail['current_club_id'] === null): ?>
                <?php foreach ($myClubs as $c): ?>
                <button class="pm-btn pm-btn-sm" style="background:<?php echo htmlspecialchars($c['colour'] ?? '#7c3aed'); ?>;color:#fff" onclick="hirePlayer(<?php echo $playerDetail['id']; ?>, <?php echo $c['id']; ?>)"><i class="fas fa-user-plus"></i> Hire to <?php echo htmlspecialchars($c['name']); ?></button>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if (!empty($playerDetail['transfers'])): ?>
        <h4 style="font-size:.85rem;font-weight:800;margin:20px 0 10px;color:var(--pm-text)"><i class="fas fa-exchange-alt"></i> Transfer History</h4>
        <div class="pm-transfer-list">
            <?php foreach ($playerDetail['transfers'] as $t): ?>
            <div class="pm-transfer-item">
                <i class="fas fa-circle" style="color:<?php echo $t['type']==='auction'?'#f59e0b':($t['type']==='direct_sale'?'#10b981':'#dc2626'); ?>"></i>
                <span style="font-weight:700;text-transform:uppercase;font-size:.7rem"><?php echo $t['type']; ?></span>
                <?php if ($t['from_club_name'] || $t['from_owner_name']): ?>
                <span><?php echo htmlspecialchars($t['from_club_name'] ?: $t['from_owner_name'] ?: 'System'); ?></span>
                <?php endif; ?>
                <?php if ($t['to_club_name'] || $t['to_owner_name']): ?>
                <i class="fas fa-arrow-right"></i>
                <span><?php echo htmlspecialchars($t['to_club_name'] ?: $t['to_owner_name']); ?></span>
                <?php endif; ?>
                <?php if ($t['amount'] > 0): ?><span style="font-weight:700;color:var(--pm-green)">৳<?php echo number_format($t['amount']); ?></span><?php endif; ?>
                <span style="margin-left:auto;font-size:.7rem;color:var(--pm-muted)"><?php echo date('M j, Y', strtotime($t['created_at'])); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="pm-tabs">
        <button class="pm-tab active" data-tab="free"><i class="fas fa-users"></i> Free Agents (<?php echo count($players); ?>)</button>
        <button class="pm-tab" data-tab="auctions"><i class="fas fa-gavel"></i> Live Auctions (<?php echo count($auctions); ?>)</button>
    </div>

    <!-- Free Agents -->
    <div class="pm-tab-content active" id="tabFree">
        <?php if (empty($players)): ?>
        <div class="pm-empty"><i class="fas fa-users-slash"></i><p>No free agents available.</p></div>
        <?php else: ?>
        <div class="pm-grid">
            <?php foreach ($players as $p): ?>
            <div class="pm-card">
                <div class="pm-card-top">
                    <img src="assets/images/avatars/<?php echo htmlspecialchars($p['avatar'] ?? 'default.png'); ?>" alt="" class="pm-card-avatar" onerror="this.src='assets/images/avatars/default.png'">
                    <div class="pm-card-info">
                        <div class="pm-card-name"><?php echo htmlspecialchars($p['full_name'] ?: $p['username']); ?></div>
                        <span class="pm-card-status <?php echo $p['status']; ?>"><?php echo str_replace('_', ' ', $p['status']); ?></span>
                    </div>
                </div>
                <div class="pm-card-body">
                    <div class="pm-card-row"><span>Market Value</span><span>৳<?php echo number_format($p['market_value'] ?: 0); ?></span></div>
                    <?php if ($p['rating'] > 0): ?>
                    <div class="pm-card-row"><span>Rating</span><span><?php echo $p['rating']; ?> / 5.0</span></div>
                    <?php endif; ?>
                </div>
                <div class="pm-card-actions">
                    <a href="index.php?page=player-market&user_id=<?php echo $p['user_id']; ?>" class="pm-btn pm-btn-sm pm-btn-ghost"><i class="fas fa-eye"></i> Details</a>
                    <?php if ($viewerId && (int)$p['owner_id'] === $viewerId): ?>
                    <button class="pm-btn pm-btn-sm pm-btn-outline" onclick="openAuctionModal(<?php echo $p['id']; ?>)"><i class="fas fa-gavel"></i> Auction</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Live Auctions -->
    <div class="pm-tab-content" id="tabAuctions" style="display:none">
        <?php if (empty($auctions)): ?>
        <div class="pm-empty"><i class="fas fa-hourglass"></i><p>No active auctions right now.</p></div>
        <?php else: ?>
        <div class="pm-grid">
            <?php foreach ($auctions as $a): ?>
            <?php $timeLeft = max(0, strtotime($a['end_time']) - time()); $hours = floor($timeLeft / 3600); $mins = floor(($timeLeft % 3600) / 60); ?>
            <div class="pm-card">
                <span class="pm-card-badge auction"><i class="fas fa-gavel"></i> LIVE</span>
                <div class="pm-card-top">
                    <img src="assets/images/avatars/<?php echo htmlspecialchars($a['player_avatar'] ?? 'default.png'); ?>" alt="" class="pm-card-avatar" onerror="this.src='assets/images/avatars/default.png'">
                    <div class="pm-card-info">
                        <div class="pm-card-name"><?php echo htmlspecialchars($a['player_full'] ?: $a['player_name']); ?></div>
                        <span style="font-size:.7rem;color:var(--pm-muted)">by <?php echo htmlspecialchars($a['seller_name']); ?></span>
                    </div>
                </div>
                <div class="pm-card-body">
                    <div class="pm-card-row"><span>Current Bid</span><span style="color:var(--pm-green);font-size:.9rem">৳<?php echo number_format($a['highest_bid'] ?: $a['base_price']); ?></span></div>
                    <div class="pm-card-row"><span>Base Price</span><span>৳<?php echo number_format($a['base_price']); ?></span></div>
                    <div class="pm-card-row"><span>Total Bids</span><span><?php echo (int)$a['total_bids']; ?></span></div>
                    <div class="pm-card-row"><span>Time Left</span><span style="font-weight:800;color:<?php echo $timeLeft < 3600 ? '#dc2626' : 'var(--pm-text)'; ?>"><?php echo $hours; ?>h <?php echo $mins; ?>m</span></div>
                </div>
                <div class="pm-card-actions">
                    <a href="index.php?page=player-market&user_id=<?php echo $a['player_id']; ?>" class="pm-btn pm-btn-sm pm-btn-ghost"><i class="fas fa-eye"></i> Details</a>
                    <?php if ($viewerId && (int)$a['seller_id'] !== $viewerId): ?>
                    <button class="pm-btn pm-btn-sm pm-btn-primary" onclick="openBidModal(<?php echo $a['id']; ?>, <?php echo $a['highest_bid'] ?: $a['base_price']; ?>, <?php echo $a['min_increment'] ?: 50; ?>)"><i class="fas fa-gavel"></i> Bid</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Buy Modal -->
<div class="pm-modal-overlay" id="buyModal">
    <div class="pm-modal-panel">
        <h3 class="pm-modal-title">Confirm Purchase</h3>
        <form class="pm-form" id="buyForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            <input type="hidden" name="action" value="buy_player">
            <input type="hidden" name="player_id" id="buyPlayerId">
            <input type="hidden" name="price" id="buyPrice">
            <p id="buyInfo" style="font-size:.9rem;color:var(--pm-muted);margin:0"></p>
            <button type="submit" class="pm-btn pm-btn-primary" style="justify-content:center"><i class="fas fa-shopping-cart"></i> Confirm Purchase</button>
        </form>
    </div>
</div>

<!-- Bid Modal -->
<div class="pm-modal-overlay" id="bidModal">
    <div class="pm-modal-panel">
        <h3 class="pm-modal-title">Place a Bid</h3>
        <form class="pm-form" id="bidForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            <input type="hidden" name="action" value="place_bid">
            <input type="hidden" name="auction_id" id="bidAuctionId">
            <div class="pm-form-group">
                <label class="pm-form-label">Your Bid Amount</label>
                <input class="pm-form-input" type="number" name="amount" id="bidAmount" min="0" step="50" required>
                <span id="bidInfo" style="font-size:.75rem;color:var(--pm-muted);margin-top:2px"></span>
            </div>
            <button type="submit" class="pm-btn pm-btn-primary" style="justify-content:center"><i class="fas fa-gavel"></i> Place Bid</button>
        </form>
    </div>
</div>

<!-- Auction Modal -->
<div class="pm-modal-overlay" id="auctionModal">
    <div class="pm-modal-panel">
        <h3 class="pm-modal-title">List Player for Auction</h3>
        <form class="pm-form" id="auctionForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            <input type="hidden" name="action" value="list_player_auction">
            <input type="hidden" name="player_id" id="auctionPlayerId">
            <div class="pm-form-group">
                <label class="pm-form-label">Base Price (৳)</label>
                <input class="pm-form-input" type="number" name="base_price" placeholder="e.g. 500" min="1" required>
            </div>
            <div class="pm-form-group">
                <label class="pm-form-label">Duration (hours)</label>
                <select class="pm-form-select" name="duration_hours">
                    <option value="6">6 hours</option>
                    <option value="12">12 hours</option>
                    <option value="24" selected>24 hours</option>
                    <option value="48">48 hours</option>
                </select>
            </div>
            <button type="submit" class="pm-btn pm-btn-primary" style="justify-content:center"><i class="fas fa-check"></i> List for Auction</button>
        </form>
    </div>
</div>

<script>
function openBuyModal(playerId, name, price) {
    document.getElementById('buyPlayerId').value = playerId;
    document.getElementById('buyPrice').value = price;
    document.getElementById('buyInfo').textContent = 'Buy ' + name + ' for ৳' + price.toLocaleString() + '?';
    document.getElementById('buyModal').classList.add('active');
}
document.getElementById('buyForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var btn = this.querySelector('button[type="submit"]');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    fetch('handlers/tournament_handler.php', { method:'POST', body:new URLSearchParams(new FormData(this)) })
    .then(r => r.json()).then(res => {
        btn.disabled = false; btn.innerHTML = '<i class="fas fa-shopping-cart"></i> Confirm Purchase';
        if (res.success) { document.getElementById('buyModal').classList.remove('active'); showToast('Player purchased!','success'); setTimeout(() => location.reload(), 800); }
        else showToast(res.message || 'Error','error');
    }).catch(function() { btn.disabled = false; btn.innerHTML = '<i class="fas fa-shopping-cart"></i> Confirm Purchase'; showToast('Server error','error'); });
});

function openBidModal(auctionId, currentPrice, minIncrement) {
    document.getElementById('bidAuctionId').value = auctionId;
    var minBid = Math.ceil((currentPrice + minIncrement) / 50) * 50;
    document.getElementById('bidAmount').value = minBid;
    document.getElementById('bidAmount').min = minBid;
    document.getElementById('bidInfo').textContent = 'Min bid: ৳' + minBid.toLocaleString() + ' (current: ৳' + currentPrice.toLocaleString() + ')';
    document.getElementById('bidModal').classList.add('active');
}
document.getElementById('bidForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var btn = this.querySelector('button[type="submit"]');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Placing...';
    fetch('handlers/tournament_handler.php', { method:'POST', body:new URLSearchParams(new FormData(this)) })
    .then(r => r.json()).then(res => {
        btn.disabled = false; btn.innerHTML = '<i class="fas fa-gavel"></i> Place Bid';
        if (res.success) { document.getElementById('bidModal').classList.remove('active'); showToast('Bid placed!','success'); setTimeout(() => location.reload(), 800); }
        else showToast(res.message || 'Error','error');
    }).catch(function() { btn.disabled = false; btn.innerHTML = '<i class="fas fa-gavel"></i> Place Bid'; showToast('Server error','error'); });
});

function openAuctionModal(playerId) {
    document.getElementById('auctionPlayerId').value = playerId;
    document.getElementById('auctionModal').classList.add('active');
}
document.getElementById('auctionForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var btn = this.querySelector('button[type="submit"]');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Listing...';
    fetch('handlers/tournament_handler.php', { method:'POST', body:new URLSearchParams(new FormData(this)) })
    .then(r => r.json()).then(res => {
        btn.disabled = false; btn.innerHTML = '<i class="fas fa-check"></i> List for Auction';
        if (res.success) { document.getElementById('auctionModal').classList.remove('active'); showToast('Player listed!','success'); setTimeout(() => location.reload(), 800); }
        else showToast(res.message || 'Error','error');
    }).catch(function() { btn.disabled = false; btn.innerHTML = '<i class="fas fa-check"></i> List for Auction'; showToast('Server error','error'); });
});

function releasePlayer(playerId) {
    if (!confirm('Release this player? This cannot be undone.')) return;
    var data = new URLSearchParams({ action:'release_player', player_id:playerId, csrf_token:'<?php echo $csrfToken; ?>' });
    fetch('handlers/tournament_handler.php', { method:'POST', body:data })
    .then(r => r.json()).then(res => {
        if (res.success) { showToast('Player released.','success'); setTimeout(() => location.reload(), 600); }
        else showToast(res.message || 'Error','error');
    });
}

function hirePlayer(playerId, clubId) {
    if (!confirm('Hire this player to your club?')) return;
    var data = new URLSearchParams({ action:'hire_player', player_id:playerId, club_id:clubId, csrf_token:'<?php echo $csrfToken; ?>' });
    fetch('handlers/tournament_handler.php', { method:'POST', body:data })
    .then(r => r.json()).then(res => {
        if (res.success) { showToast('Player hired!','success'); setTimeout(() => location.reload(), 600); }
        else showToast(res.message || 'Error','error');
    });
}

// Tabs
document.querySelectorAll('.pm-tab').forEach(function(tab) {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.pm-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.pm-tab-content').forEach(c => c.style.display = 'none');
        this.classList.add('active');
        var target = document.getElementById('tab' + this.dataset.tab.charAt(0).toUpperCase() + this.dataset.tab.slice(1));
        if (target) target.style.display = 'block';
    });
});

// Modal overlay close
document.querySelectorAll('.pm-modal-overlay').forEach(function(m) {
    m.addEventListener('click', function(e) { if (e.target === this) this.classList.remove('active'); });
});

function showToast(msg, type) {
    var t = document.createElement('div');
    t.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);padding:12px 24px;border-radius:14px;font-size:.85rem;font-weight:700;z-index:9999;color:#fff;background:'+(type==='success'?'#059669':'#dc2626')+';box-shadow:0 8px 24px rgba(0,0,0,.15);animation:pmFadeIn .3s ease';
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(function() { t.style.opacity='0'; t.style.transition='opacity .3s'; setTimeout(function() { t.remove(); }, 300); }, 2500);
}
if (!document.getElementById('pmToastStyle')) {
    var s = document.createElement('style'); s.id='pmToastStyle';
    s.textContent = '@keyframes pmFadeIn{from{opacity:0;transform:translateX(-50%) translateY(10px)}to{opacity:1;transform:translateX(-50%) translateY(0)}}';
    document.head.appendChild(s);
}
</script>
