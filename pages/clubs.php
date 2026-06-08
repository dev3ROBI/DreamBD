<?php
$db = Database::getInstance()->getConnection();
$viewerId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['role'] ?? 'user';
$security = new Security();
$csrfToken = $security->generateCSRFToken();

$myClubs = $viewerId ? getUserClubs($db, $viewerId) : [];
$allClubs = getClubStandings($db);
$clubDetail = null; $clubMembers = [];
$clubId = isset($_GET['club_id']) ? (int)$_GET['club_id'] : 0;
if ($clubId) {
    $clubDetail = getClub($db, $clubId);
    $clubMembers = $clubDetail ? getClubMembers($db, $clubId) : [];
}
?>
<style>
:root { --cl-primary:#7c3aed; --cl-card:#fff; --cl-bg:#f5f7fa; --cl-border:#e2e8f0; --cl-text:#1f2937; --cl-muted:#6b7280; --cl-green:#059669; --cl-red:#dc2626 }
.dark { --cl-card:#1e293b; --cl-bg:#0f172a; --cl-border:#334155; --cl-text:#f1f5f9; --cl-muted:#94a3b8 }

.cl-page { max-width:1100px; margin:0 auto; padding:0 16px 3rem; font-family:'Plus Jakarta Sans',sans-serif }
.cl-hero { padding:28px 24px; border-radius:24px; background:linear-gradient(135deg,#0f172a,#1e1b4b,#1a0533); margin:0 0 24px; position:relative; overflow:hidden; border:1px solid rgba(139,92,246,.15) }
.cl-hero::before { content:''; position:absolute; top:-120px; right:-80px; width:350px; height:350px; border-radius:50%; background:radial-gradient(circle,rgba(139,92,246,.2),transparent 70%) }
.cl-hero-content { position:relative; z-index:1 }
.cl-hero h1 { font-size:1.7rem; font-weight:900; color:#fff; margin:0 0 4px; letter-spacing:-.03em }
.cl-hero p { font-size:.85rem; color:#94a3b8; margin:0 }
.cl-hero-actions { display:flex; gap:10px; margin-top:16px; flex-wrap:wrap }
.cl-btn { display:inline-flex; align-items:center; gap:8px; padding:10px 20px; border-radius:12px; font-size:.8rem; font-weight:700; border:none; cursor:pointer; font-family:'Plus Jakarta Sans',sans-serif; transition:all .2s; text-decoration:none }
.cl-btn-primary { background:#7c3aed; color:#fff }
.cl-btn-primary:hover { background:#6d28d9; transform:translateY(-1px) }
.cl-btn-outline { background:rgba(255,255,255,.1); color:#fff; border:1px solid rgba(255,255,255,.2) }
.cl-btn-outline:hover { background:rgba(255,255,255,.18) }
.cl-btn-sm { padding:7px 14px; font-size:.75rem; border-radius:8px }
.cl-btn-danger { background:#dc2626; color:#fff }
.cl-btn-danger:hover { background:#b91c1c }
.cl-btn-ghost { background:transparent; color:var(--cl-muted); border:1px solid var(--cl-border) }
.cl-btn-ghost:hover { background:var(--cl-bg); color:var(--cl-primary, var(--cl-text)) }

.cl-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:16px; margin-bottom:28px }
.cl-card { background:var(--cl-card); border-radius:20px; padding:20px; border:1px solid var(--cl-border); box-shadow:0 1px 4px rgba(0,0,0,.03); transition:all .25s; cursor:pointer; position:relative; overflow:hidden }
.cl-card:hover { transform:translateY(-2px); box-shadow:0 8px 24px rgba(0,0,0,.06); border-color:var(--cl-primary, #7c3aed) }
.cl-card-top { display:flex; align-items:center; gap:14px; margin-bottom:12px }
.cl-card-logo { width:48px; height:48px; border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:1.3rem; font-weight:900; color:#fff; flex-shrink:0 }
.cl-card-info { flex:1 }
.cl-card-name { font-size:.95rem; font-weight:800; color:var(--cl-text); margin:0; line-height:1.2 }
.cl-card-tag { font-size:.7rem; font-weight:700; color:var(--cl-primary, #7c3aed); text-transform:uppercase; letter-spacing:.5px }
.cl-card-stats { display:flex; gap:12px; margin-top:10px; padding-top:10px; border-top:1px solid var(--cl-border) }
.cl-card-stat { text-align:center; flex:1 }
.cl-card-stat-value { font-size:1rem; font-weight:800; color:var(--cl-text) }
.cl-card-stat-label { font-size:.6rem; font-weight:600; color:var(--cl-muted); text-transform:uppercase; letter-spacing:.3px }
.cl-card-role { position:absolute; top:12px; right:12px; font-size:.6rem; font-weight:800; text-transform:uppercase; padding:3px 10px; border-radius:999px; background:rgba(124,58,237,.12); color:#7c3aed; letter-spacing:.3px }
.cl-card-role.owner { background:rgba(245,158,11,.12); color:#d97706 }

/* Club Detail */
.cl-detail { margin-bottom:28px }
.cl-detail-header { display:flex; align-items:center; gap:20px; padding:24px; border-radius:24px; background:var(--cl-card); border:1px solid var(--cl-border); margin-bottom:16px }
.cl-detail-logo { width:72px; height:72px; border-radius:18px; display:flex; align-items:center; justify-content:center; font-size:1.8rem; font-weight:900; color:#fff; flex-shrink:0 }
.cl-detail-info { flex:1 }
.cl-detail-name { font-size:1.4rem; font-weight:900; color:var(--cl-text); margin:0; line-height:1.1 }
.cl-detail-tag { font-size:.75rem; font-weight:700; color:var(--cl-primary, #7c3aed) }
.cl-detail-meta { display:flex; gap:16px; margin-top:6px; font-size:.8rem; color:var(--cl-muted) }
.cl-detail-stats { display:flex; gap:20px; margin-top:10px; flex-wrap:wrap }
.cl-detail-stat { text-align:center; padding:8px 18px; border-radius:12px; background:var(--cl-bg) }
.cl-detail-stat-value { font-size:1.1rem; font-weight:800; color:var(--cl-text) }
.cl-detail-stat-label { font-size:.65rem; font-weight:600; color:var(--cl-muted); text-transform:uppercase }

.cl-tabs { display:flex; gap:4px; margin-bottom:16px; background:var(--cl-card); border-radius:14px; padding:4px; border:1px solid var(--cl-border) }
.cl-tab { flex:1; padding:10px; border-radius:10px; border:none; font-size:.78rem; font-weight:700; cursor:pointer; background:transparent; color:var(--cl-muted); font-family:'Plus Jakarta Sans',sans-serif; transition:all .2s }
.cl-tab:hover { color:var(--cl-text) }
.cl-tab.active { background:#7c3aed; color:#fff }

.cl-members { display:flex; flex-direction:column; gap:8px }
.cl-member { display:flex; align-items:center; gap:12px; padding:12px 16px; border-radius:14px; background:var(--cl-card); border:1px solid var(--cl-border); transition:all .15s }
.cl-member:hover { border-color:var(--cl-primary, #7c3aed) }
.cl-member-avatar { width:40px; height:40px; border-radius:50%; object-fit:cover; background:var(--cl-bg) }
.cl-member-info { flex:1 }
.cl-member-name { font-size:.85rem; font-weight:700; color:var(--cl-text) }
.cl-member-joined { font-size:.7rem; color:var(--cl-muted) }
.cl-member-role { font-size:.65rem; font-weight:800; padding:3px 10px; border-radius:999px; text-transform:uppercase; letter-spacing:.3px }
.cl-member-role.owner { background:rgba(245,158,11,.12); color:#d97706 }
.cl-member-role.manager { background:rgba(59,130,246,.12); color:#2563eb }
.cl-member-role.player { background:rgba(16,185,129,.12); color:#059669 }
.cl-member-role.sub { background:rgba(148,163,184,.2); color:var(--cl-muted) }

.cl-modal-overlay { position:fixed; inset:0; z-index:200; background:rgba(0,0,0,.5); backdrop-filter:blur(4px); display:none; align-items:center; justify-content:center; padding:16px }
.cl-modal-overlay.active { display:flex }
.cl-modal-panel { background:var(--cl-card); border-radius:24px; padding:24px; max-width:480px; width:100%; max-height:90vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,.15); animation:clModalIn .25s ease }
@keyframes clModalIn { from { opacity:0; transform:scale(.95) translateY(10px) } to { opacity:1; transform:scale(1) translateY(0) } }
.cl-modal-title { font-size:1.1rem; font-weight:800; margin:0 0 16px; color:var(--cl-text) }
.cl-form { display:flex; flex-direction:column; gap:14px }
.cl-form-group { display:flex; flex-direction:column; gap:4px }
.cl-form-label { font-size:.75rem; font-weight:700; color:var(--cl-muted); text-transform:uppercase; letter-spacing:.3px }
.cl-form-input, .cl-form-select, .cl-form-textarea { padding:10px 14px; border-radius:10px; border:2px solid var(--cl-border); font-size:.85rem; background:var(--cl-bg); color:var(--cl-text); font-family:'Plus Jakarta Sans',sans-serif; transition:border-color .2s; outline:none }
.cl-form-input:focus, .cl-form-select:focus, .cl-form-textarea:focus { border-color:#7c3aed; box-shadow:0 0 0 3px rgba(124,58,237,.1) }
.cl-form-textarea { min-height:80px; resize:vertical }
.cl-form-row { display:grid; grid-template-columns:1fr 1fr; gap:12px }
.cl-color-options { display:flex; gap:8px; flex-wrap:wrap }
.cl-color-opt { width:32px; height:32px; border-radius:50%; border:3px solid transparent; cursor:pointer; transition:all .15s }
.cl-color-opt.active, .cl-color-opt:hover { border-color:var(--cl-text); transform:scale(1.1) }

.cl-empty { text-align:center; padding:40px 20px; color:var(--cl-muted) }
.cl-empty i { font-size:2.5rem; margin-bottom:12px; opacity:.4 }
.cl-empty p { font-size:.9rem; margin:0 }

.cl-leaderboard { background:var(--cl-card); border-radius:20px; border:1px solid var(--cl-border); overflow:hidden }
.cl-lb-header { display:grid; grid-template-columns:40px 1fr 80px 80px 60px; gap:8px; padding:12px 16px; font-size:.7rem; font-weight:700; color:var(--cl-muted); text-transform:uppercase; background:var(--cl-bg); border-bottom:1px solid var(--cl-border) }
.cl-lb-row { display:grid; grid-template-columns:40px 1fr 80px 80px 60px; gap:8px; padding:12px 16px; align-items:center; border-bottom:1px solid var(--cl-border); transition:background .15s; font-size:.82rem }
.cl-lb-row:last-child { border-bottom:none }
.cl-lb-row:hover { background:var(--cl-bg) }
.cl-lb-rank { font-weight:800; color:var(--cl-muted); text-align:center }
.cl-lb-rank.gold { color:#f59e0b } .cl-lb-rank.silver { color:#94a3b8 } .cl-lb-rank.bronze { color:#cd7f32 }
.cl-lb-club { display:flex; align-items:center; gap:8px; font-weight:700; color:var(--cl-text) }
.cl-lb-club-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0 }
.cl-lb-val { font-weight:700; color:var(--cl-text); text-align:center }
.cl-lb-prize { color:var(--cl-green); text-align:center; font-weight:700 }

@media (max-width:768px) {
    .cl-grid { grid-template-columns:1fr }
    .cl-detail-header { flex-direction:column; text-align:center }
    .cl-detail-stats { justify-content:center }
    .cl-lb-header, .cl-lb-row { grid-template-columns:30px 1fr 60px 60px 50px; font-size:.75rem }
    .cl-hero h1 { font-size:1.3rem }
    .cl-form-row { grid-template-columns:1fr }
}
</style>

<div class="cl-page" id="clPage">
    <!-- Hero -->
    <div class="cl-hero">
        <div class="cl-hero-content">
            <h1><i class="fas fa-flag"></i> Clubs & Teams</h1>
            <p>Create your club, manage players, and compete on the leaderboard.</p>
            <div class="cl-hero-actions">
                <?php if ($viewerId): ?>
                <button class="cl-btn cl-btn-primary" onclick="openClubModal()"><i class="fas fa-plus"></i> Create Club</button>
                <?php endif; ?>
                <a href="index.php?page=player-market" class="cl-btn cl-btn-outline"><i class="fas fa-store"></i> Player Market</a>
            </div>
        </div>
    </div>

    <?php if ($myClubs): ?>
    <!-- My Clubs -->
    <h2 style="font-size:1rem;font-weight:800;margin:0 0 12px;color:var(--cl-text)"><i class="fas fa-star"></i> My Clubs</h2>
    <div class="cl-grid">
        <?php foreach ($myClubs as $c): ?>
        <div class="cl-card" onclick="viewClub(<?php echo $c['id']; ?>)">
            <span class="cl-card-role <?php echo $c['my_role']; ?>"><?php echo htmlspecialchars($c['my_role']); ?></span>
            <div class="cl-card-top">
                <div class="cl-card-logo" style="background:<?php echo htmlspecialchars($c['colour'] ?? '#7c3aed'); ?>"><?php echo strtoupper(substr($c['tag'], 0, 2)); ?></div>
                <div class="cl-card-info">
                    <div class="cl-card-name"><?php echo htmlspecialchars($c['name']); ?></div>
                    <div class="cl-card-tag"><?php echo htmlspecialchars($c['tag']); ?></div>
                </div>
            </div>
            <div class="cl-card-stats">
                <div class="cl-card-stat"><div class="cl-card-stat-value"><?php echo (int)$c['member_count']; ?></div><div class="cl-card-stat-label">Members</div></div>
                <div class="cl-card-stat"><div class="cl-card-stat-value"><?php echo (int)$c['trophies']; ?></div><div class="cl-card-stat-label">Trophies</div></div>
                <div class="cl-card-stat"><div class="cl-card-stat-value">#<?php echo (int)$c['rank'] ?: '--'; ?></div><div class="cl-card-stat-label">Rank</div></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Club Detail Section -->
    <?php if ($clubDetail): ?>
    <div class="cl-detail" id="clubDetail">
        <div class="cl-detail-header">
            <div class="cl-detail-logo" style="background:<?php echo htmlspecialchars($clubDetail['colour'] ?? '#7c3aed'); ?>"><?php echo strtoupper(substr($clubDetail['tag'], 0, 2)); ?></div>
            <div class="cl-detail-info">
                <div class="cl-detail-name"><?php echo htmlspecialchars($clubDetail['name']); ?></div>
                <div class="cl-detail-tag">#<?php echo htmlspecialchars($clubDetail['tag']); ?> · by <?php echo htmlspecialchars($clubDetail['owner_full'] ?: $clubDetail['owner_name']); ?></div>
                <?php if ($clubDetail['description']): ?><p style="margin:6px 0 0;font-size:.82rem;color:var(--cl-muted)"><?php echo nl2br(htmlspecialchars($clubDetail['description'])); ?></p><?php endif; ?>
                <div class="cl-detail-stats">
                    <div class="cl-detail-stat"><div class="cl-detail-stat-value"><?php echo (int)$clubDetail['member_count']; ?></div><div class="cl-detail-stat-label">Members</div></div>
                    <div class="cl-detail-stat"><div class="cl-detail-stat-value"><?php echo (int)$clubDetail['trophies']; ?></div><div class="cl-detail-stat-label">Trophies</div></div>
                    <div class="cl-detail-stat"><div class="cl-detail-stat-value"><?php echo (int)$clubDetail['total_points']; ?></div><div class="cl-detail-stat-label">Points</div></div>
                    <div class="cl-detail-stat"><div class="cl-detail-stat-value">#<?php echo (int)$clubDetail['rank'] ?: '--'; ?></div><div class="cl-detail-stat-label">Rank</div></div>
                </div>
            </div>
            <?php if ($viewerId): ?>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <?php
                $isOwner = $clubDetail['owner_id'] == $viewerId;
                $isMember = false;
                foreach ($clubMembers as $m) { if ((int)$m['id'] === $viewerId) { $isMember = true; break; } }
                ?>
                <?php if ($isOwner): ?>
                <button class="cl-btn cl-btn-sm cl-btn-primary" onclick="openEditClubModal(<?php echo $clubDetail['id']; ?>)"><i class="fas fa-edit"></i> Edit</button>
                <?php elseif ($isMember): ?>
                <button class="cl-btn cl-btn-sm cl-btn-danger" onclick="leaveClub(<?php echo $clubDetail['id']; ?>)"><i class="fas fa-sign-out-alt"></i> Leave</button>
                <?php else: ?>
                <button class="cl-btn cl-btn-sm cl-btn-primary" onclick="joinClub(<?php echo $clubDetail['id']; ?>)"><i class="fas fa-plus"></i> Join</button>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="cl-tabs">
            <button class="cl-tab active" data-tab="members"><i class="fas fa-users"></i> Members (<?php echo count($clubMembers); ?>)</button>
            <button class="cl-tab" data-tab="leaderboard"><i class="fas fa-trophy"></i> Leaderboard</button>
        </div>

        <div class="cl-tab-content active" id="tabMembers">
            <div class="cl-members">
                <?php foreach ($clubMembers as $m): ?>
                <div class="cl-member">
                    <img src="<?php echo htmlspecialchars('assets/images/avatars/' . ($m['avatar'] ?? 'default.png')); ?>" alt="" class="cl-member-avatar" onerror="this.src='assets/images/avatars/default.png'">
                    <div class="cl-member-info">
                        <div class="cl-member-name"><?php echo htmlspecialchars($m['full_name'] ?: $m['username']); ?></div>
                        <div class="cl-member-joined">Joined <?php echo date('M j, Y', strtotime($m['joined_at'])); ?></div>
                    </div>
                    <span class="cl-member-role <?php echo $m['role']; ?>"><?php echo $m['role']; ?></span>
                    <?php if ($isOwner && $m['role'] !== 'owner'): ?>
                    <button class="cl-btn cl-btn-sm cl-btn-ghost" onclick="removeMember(this, <?php echo $clubDetail['id']; ?>, <?php echo $m['id']; ?>)"><i class="fas fa-times"></i></button>
                    <?php endif; ?>
                </div>
                <?php endforeach; if (empty($clubMembers)): ?>
                <div class="cl-empty"><i class="fas fa-users"></i><p>No members yet.</p></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="cl-tab-content" id="tabLeaderboard" style="display:none">
            <div class="cl-leaderboard">
                <div class="cl-lb-header">
                    <span>#</span><span>Player</span><span>Points</span><span>Prize</span><span>Rank</span>
                </div>
                <div id="clLbBody">
                    <div class="cl-empty"><i class="fas fa-trophy"></i><p>Loading...</p></div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- All Clubs Leaderboard -->
    <h2 style="font-size:1rem;font-weight:800;margin:24px 0 12px;color:var(--cl-text)"><i class="fas fa-trophy"></i> Club Rankings</h2>
    <div class="cl-leaderboard">
        <div class="cl-lb-header">
            <span>#</span><span>Club</span><span>Points</span><span>Trophies</span><span>Members</span>
        </div>
        <?php $i=0; foreach ($allClubs as $c): $i++; ?>
        <a href="index.php?page=clubs&club_id=<?php echo $c['id']; ?>" class="cl-lb-row" style="text-decoration:none">
            <div class="cl-lb-rank <?php if ($i===1) echo 'gold'; elseif ($i===2) echo 'silver'; elseif ($i===3) echo 'bronze'; ?>"><?php echo $i; ?></div>
            <div class="cl-lb-club"><span class="cl-lb-club-dot" style="background:<?php echo htmlspecialchars($c['colour'] ?? '#7c3aed'); ?>"></span><?php echo htmlspecialchars($c['name']); ?></div>
            <div class="cl-lb-val"><?php echo number_format((int)$c['total_points']); ?></div>
            <div class="cl-lb-val"><?php echo (int)$c['trophies']; ?></div>
            <div class="cl-lb-val"><?php echo (int)$c['member_count']; ?></div>
        </a>
        <?php endforeach; if (empty($allClubs)): ?>
        <div class="cl-empty" style="padding:30px"><i class="fas fa-flag"></i><p>No clubs yet. Create the first one!</p></div>
        <?php endif; ?>
    </div>
</div>

<!-- Create Club Modal -->
<div class="cl-modal-overlay" id="createClubModal">
    <div class="cl-modal-panel">
        <h3 class="cl-modal-title"><i class="fas fa-flag"></i> Create New Club</h3>
        <form class="cl-form" id="createClubForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            <input type="hidden" name="action" value="create_club">
            <div class="cl-form-group">
                <label class="cl-form-label">Club Name</label>
                <input class="cl-form-input" name="name" placeholder="e.g. Alpha Esports" required>
            </div>
            <div class="cl-form-row">
                <div class="cl-form-group">
                    <label class="cl-form-label">Tag (2-10 chars)</label>
                    <input class="cl-form-input" name="tag" placeholder="e.g. ALPHA" maxlength="10" required>
                </div>
                <div class="cl-form-group">
                    <label class="cl-form-label">Region</label>
                    <input class="cl-form-input" name="region" placeholder="e.g. Bangladesh">
                </div>
            </div>
            <div class="cl-form-group">
                <label class="cl-form-label">Club Colour</label>
                <div class="cl-color-options" id="clubColorPicker">
                    <?php $colors = ['#7c3aed','#2563eb','#059669','#d97706','#dc2626','#ec4899','#06b6d4','#8b5cf6','#10b981']; foreach ($colors as $c): ?>
                    <div class="cl-color-opt<?php if ($c==='#7c3aed') echo ' active'; ?>" style="background:<?php echo $c; ?>" data-color="<?php echo $c; ?>" onclick="selectClubColor(this)"></div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="colour" id="clubColourInput" value="#7c3aed">
            </div>
            <div class="cl-form-group">
                <label class="cl-form-label">Description (optional)</label>
                <textarea class="cl-form-textarea" name="description" placeholder="Tell us about your club..."></textarea>
            </div>
            <button type="submit" class="cl-btn cl-btn-primary" style="justify-content:center;margin-top:4px"><i class="fas fa-check"></i> Create Club</button>
        </form>
    </div>
</div>

<script>
function openClubModal() { document.getElementById('createClubModal').classList.add('active'); }
function closeClubModal() { document.getElementById('createClubModal').classList.remove('active'); }
document.getElementById('createClubModal').addEventListener('click', function(e) { if (e.target === this) closeClubModal(); });

function selectClubColor(el) {
    document.querySelectorAll('#clubColorPicker .cl-color-opt').forEach(o => o.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('clubColourInput').value = el.dataset.color;
}

document.getElementById('createClubForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var form = this, btn = form.querySelector('button[type="submit"]');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
    var data = new FormData(form);
    fetch('handlers/tournament_handler.php', { method:'POST', body:new URLSearchParams(data) })
    .then(r => r.json()).then(res => {
        btn.disabled = false; btn.innerHTML = '<i class="fas fa-check"></i> Create Club';
        if (res.success) { closeClubModal(); showToast('Club created!','success'); setTimeout(() => location.reload(), 800); }
        else showToast(res.message || 'Error','error');
    }).catch(() => { btn.disabled = false; btn.innerHTML = '<i class="fas fa-check"></i> Create Club'; showToast('Server error','error'); });
});

function viewClub(id) { window.location.href = 'index.php?page=clubs&club_id=' + id; }

function joinClub(id) {
    var data = new URLSearchParams({ action:'join_club', club_id:id, csrf_token:'<?php echo $csrfToken; ?>' });
    fetch('handlers/tournament_handler.php', { method:'POST', body:data })
    .then(r => r.json()).then(res => {
        if (res.success) { showToast('Joined club!','success'); setTimeout(() => location.reload(), 600); }
        else showToast(res.message || 'Error','error');
    });
}

function leaveClub(id) {
    if (!confirm('Leave this club?')) return;
    var data = new URLSearchParams({ action:'leave_club', club_id:id, csrf_token:'<?php echo $csrfToken; ?>' });
    fetch('handlers/tournament_handler.php', { method:'POST', body:data })
    .then(r => r.json()).then(res => {
        if (res.success) { showToast('Left club.','success'); setTimeout(() => location.reload(), 600); }
        else showToast(res.message || 'Error','error');
    });
}

function removeMember(btn, clubId, userId) {
    if (!confirm('Remove this member?')) return;
    var data = new URLSearchParams({ action:'fire_player', club_id:clubId, player_user_id:userId, csrf_token:'<?php echo $csrfToken; ?>' });
    fetch('handlers/tournament_handler.php', { method:'POST', body:data })
    .then(r => r.json()).then(res => {
        if (res.success) { btn.closest('.cl-member').remove(); showToast('Member removed.','success'); }
        else showToast(res.message || 'Error','error');
    });
}

// Tabs
document.querySelectorAll('.cl-tab').forEach(function(tab) {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.cl-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.cl-tab-content').forEach(c => c.style.display = 'none');
        this.classList.add('active');
        var target = document.getElementById('tab' + this.dataset.tab.charAt(0).toUpperCase() + this.dataset.tab.slice(1));
        if (target) target.style.display = 'block';
        if (this.dataset.tab === 'leaderboard' && document.getElementById('clLbBody')) loadClubLeaderboard();
    });
});

function loadClubLeaderboard() {
    var body = document.getElementById('clLbBody');
    body.innerHTML = '<div class="cl-empty"><i class="fas fa-spinner fa-spin"></i><p>Loading...</p></div>';
    var data = new URLSearchParams({ action:'get_leaderboard', club_id:<?php echo $clubId; ?>, limit:20 });
    fetch('handlers/tournament_handler.php', { method:'POST', body:data })
    .then(r => r.json()).then(res => {
        if (!res.success || !res.leaderboard || !res.leaderboard.length) {
            body.innerHTML = '<div class="cl-empty"><i class="fas fa-trophy"></i><p>No rankings yet.</p></div>'; return;
        }
        var html = '';
        res.leaderboard.forEach(function(p, i) {
            var rankClass = i===0 ? 'gold' : (i===1 ? 'silver' : (i===2 ? 'bronze' : ''));
            html += '<div class="cl-lb-row" onclick="window.location.href=\'index.php?page=player-market&user_id=' + p.user_id + '\'" style="cursor:pointer">';
            html += '<div class="cl-lb-rank ' + rankClass + '">' + (i+1) + '</div>';
            html += '<div style="display:flex;align-items:center;gap:8px;font-weight:600;color:var(--cl-text)">';
            if (p.avatar) html += '<img src="assets/images/avatars/' + p.avatar + '" style="width:28px;height:28px;border-radius:50%;object-fit:cover" onerror="this.style.display=\'none\'">';
            html += (p.nickname || p.full_name || p.username) + '</div>';
            html += '<div class="cl-lb-val">' + (p.total_points || 0) + '</div>';
            html += '<div class="cl-lb-prize">৳' + (parseFloat(p.total_prize) || 0).toLocaleString() + '</div>';
            html += '<div class="cl-lb-val">' + (p.best_rank || '--') + '</div></div>';
        });
        body.innerHTML = html;
    }).catch(function() { body.innerHTML = '<div class="cl-empty"><i class="fas fa-exclamation-triangle"></i><p>Failed to load.</p></div>'; });
}

function showToast(msg, type) {
    var t = document.createElement('div');
    t.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);padding:12px 24px;border-radius:14px;font-size:.85rem;font-weight:700;z-index:9999;color:#fff;background:'+(type==='success'?'#059669':'#dc2626')+';box-shadow:0 8px 24px rgba(0,0,0,.15);animation:clFadeIn .3s ease';
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(function() { t.style.opacity='0'; t.style.transition='opacity .3s'; setTimeout(function() { t.remove(); }, 300); }, 2500);
}
// inject keyframes
if (!document.getElementById('clToastStyle')) {
    var s = document.createElement('style'); s.id='clToastStyle';
    s.textContent = '@keyframes clFadeIn{from{opacity:0;transform:translateX(-50%) translateY(10px)}to{opacity:1;transform:translateX(-50%) translateY(0)}}';
    document.head.appendChild(s);
}

document.addEventListener('DOMContentLoaded', function() {
    <?php if ($clubId): ?>loadClubLeaderboard();<?php endif; ?>
});
</script>
