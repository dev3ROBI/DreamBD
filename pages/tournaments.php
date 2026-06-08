<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';

$db = Database::getInstance()->getConnection();
$viewerId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['role'] ?? 'user';
$security = new Security();
$csrfToken = $security->generateCSRFToken();

$tournaments = [];
try { $tournaments = getTournamentsWithCounts($db, null, 50); } catch (Throwable $e) { $tournaments = []; }

$myRegistrations = [];
if ($viewerId) {
    try { $myRegistrations = getUserTournamentRegistrations($db, $viewerId); } catch (Throwable $e) {}
}

$myTeams = [];
if ($viewerId) {
    try { $myTeams = getUserTeams($db, $viewerId); } catch (Throwable $e) {}
}

$userBalance = 0;
if ($viewerId) {
    try {
        $stmt = $db->prepare("SELECT balance, agent_verified_at FROM users WHERE id = ?");
        $stmt->execute([$viewerId]);
        $u = $stmt->fetch();
        $userBalance = (float)($u['balance'] ?? 0);
        $agentVerifiedAt = $u['agent_verified_at'] ?? null;
    } catch (Throwable $e) {}
}

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

$statusStats = ['upcoming' => 0, 'live' => 0, 'ongoing' => 0, 'completed' => 0, 'cancelled' => 0];
$totalPrize = 0;
foreach ($tournaments as $t) {
    $s = $t['status'] ?? 'upcoming';
    if (isset($statusStats[$s])) $statusStats[$s]++;
    $prize = (float) str_replace(',', '', $t['prize_money'] ?? '0');
    $totalPrize += $prize;
}

$statusBadgeClasses = [
    'live' => 'badge-live', 'upcoming' => 'badge-upcoming',
    'ongoing' => 'badge-ongoing', 'completed' => 'badge-completed', 'cancelled' => 'badge-cancelled',
];

$categories = ['PUBG','Free Fire','Valorant','Call of Duty','Fortnite','League of Legends','Dota 2','CS:GO','Overwatch','Apex Legends','Minecraft','Rocket League','FIFA','GTA Online','eFootball','Other'];
$gameIcons = ['fa-gamepad','fa-crosshairs','fa-chess','fa-dice','fa-joystick','fa-headset','fa-keyboard','fa-shield-halved','fa-bullseye','fa-skull','fa-fighter-jet','fa-gun','fa-swords','fa-axe','fa-bow-arrow','fa-futbol'];
$palette = ['#7c3aed','#2563eb','#ec4899','#ef4444','#f59e0b','#10b981','#06b6d4','#f97316','#64748b'];

$gameSkills = [];
$playerStats = [];
if ($viewerId) {
    try {
        $stmt = $db->prepare("SELECT game, skill_level, game_icon FROM game_skills WHERE user_id = ? ORDER BY game");
        $stmt->execute([$viewerId]);
        $gameSkills = $stmt->fetchAll();
    } catch (Throwable $e) { $gameSkills = []; }
    try {
        $stmt = $db->prepare("SELECT COALESCE(SUM(matches_played),0) AS total_matches, COALESCE(SUM(wins),0) AS total_wins, COALESCE(SUM(kills),0) AS total_kills, COALESCE(SUM(goals),0) AS total_goals, COALESCE(SUM(assists),0) AS total_assists, COALESCE(SUM(score),0) AS total_score FROM player_stats WHERE user_id = ?");
        $stmt->execute([$viewerId]);
        $playerStats = $stmt->fetch() ?: [];
    } catch (Throwable $e) { $playerStats = []; }
}
?>

<style>
:root { --gp-bg:#f5f7fa; --gp-card:#fff; --gp-border:#e2e8f0; --gp-text:#1f2937; --gp-muted:#6b7280; --gp-accent:#8b5cf6; --gp-green:#059669; --gp-red:#dc2626; --gp-yellow:#d97706; --gp-shadow:0 4px 16px rgba(0,0,0,.03) }
.dark { --gp-bg:#0f172a; --gp-card:#1e293b; --gp-border:#334155; --gp-text:#f1f5f9; --gp-muted:#94a3b8 }

/* ═══ PAGE ═══ */
.gp-page { max-width:900px; margin:0 auto; padding:0 12px 2rem }

/* ═══ HERO ═══ */
.gp-hero { position:relative; padding:28px 24px 0; border-radius:0 0 32px 32px; min-height:auto; overflow:hidden }
.gp-hero-bg { position:absolute; inset:0; z-index:0; background:linear-gradient(135deg,#0f172a,#1e1b4b,#1a0533); min-height:360px; border-radius:0 0 32px 32px }
.gp-hero-bg::before { content:''; position:absolute; top:-120px; right:-120px; width:320px; height:320px; border-radius:50%; background:radial-gradient(circle,rgba(139,92,246,.18),transparent 70%) }
.gp-hero-bg::after { content:''; position:absolute; bottom:-80px; left:-80px; width:240px; height:240px; border-radius:50%; background:radial-gradient(circle,rgba(5,150,105,.1),transparent 70%) }
.gp-hero-content { position:relative; z-index:1; text-align:center; padding:10px 0 4px }
.gp-hero-badge { display:inline-flex; align-items:center; gap:6px; padding:6px 16px; border-radius:999px; background:rgba(139,92,246,.15); color:#a78bfa; font-size:.72rem; font-weight:700; letter-spacing:.3px; margin-bottom:14px; border:1px solid rgba(139,92,246,.2) }
.gp-hero-content h1 { font-size:clamp(24px,4vw,38px); font-weight:900; color:#fff; letter-spacing:-.03em; line-height:1.15; margin:0 0 6px }
.gp-hero-content p { font-size:.85rem; color:#94a3b8; margin:0 0 16px }
.text-gradient { background:linear-gradient(135deg,#a78bfa,#c084fc); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text }
.gp-hero-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; padding:20px 0 24px; position:relative; z-index:1 }
.gp-stat-card { padding:22px 14px; border-radius:20px; background:rgba(255,255,255,.92); backdrop-filter:blur(14px); border:1px solid rgba(255,255,255,.5); box-shadow:0 4px 20px rgba(0,0,0,.04); transition:all .35s cubic-bezier(.34,1.56,.64,1); display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center }
.dark .gp-stat-card { background:rgba(30,41,59,.88); border-color:rgba(71,85,105,.35) }
.gp-stat-card:hover { transform:translateY(-5px); box-shadow:0 16px 40px rgba(0,0,0,.08) }
.gp-stat-icon { font-size:26px; width:54px; height:54px; display:flex; align-items:center; justify-content:center; border-radius:16px; margin-bottom:10px }
.gp-stat-card:nth-child(1) .gp-stat-icon { background:linear-gradient(135deg,#5b21b6,#7c3aed); color:#fff; box-shadow:0 6px 20px rgba(91,33,182,.35) }
.gp-stat-card:nth-child(2) .gp-stat-icon { background:linear-gradient(135deg,#d97706,#f59e0b); color:#fff; box-shadow:0 6px 20px rgba(217,119,6,.35) }
.gp-stat-card:nth-child(3) .gp-stat-icon { background:linear-gradient(135deg,#dc2626,#ef4444); color:#fff; box-shadow:0 6px 20px rgba(220,38,38,.35) }
.gp-stat-card:nth-child(4) .gp-stat-icon { background:linear-gradient(135deg,#059669,#10b981); color:#fff; box-shadow:0 6px 20px rgba(5,150,105,.35) }
.gp-stat-value { font-size:26px; font-weight:900; line-height:1.1; color:var(--gp-text); display:block }
.gp-stat-label { font-size:12px; color:var(--gp-muted); font-weight:600; display:block; margin-top:3px }

/* ═══ PROFILE BAR ═══ */
.gp-profile-bar { display:flex; align-items:center; gap:14px; padding:14px 20px; margin:0 0 18px; border-radius:20px; background:var(--gp-card); border:1px solid var(--gp-border); box-shadow:var(--gp-shadow) }
.gp-profile-bar-avatar { width:46px; height:46px; border-radius:50%; overflow:hidden; flex-shrink:0; border:2px solid rgba(139,92,246,.2); box-shadow:0 2px 8px rgba(139,92,246,.12) }
.gp-profile-bar-avatar img { width:100%; height:100%; object-fit:cover }
.gp-profile-bar-info { flex:1; min-width:0 }
.gp-profile-bar-info strong { display:block; font-size:14px; font-weight:700; color:var(--gp-text) }
.gp-profile-bar-balance { display:flex; align-items:center; gap:5px; margin-left:6px; padding:5px 12px; border-radius:12px; background:rgba(16,185,129,.08); border:1px solid rgba(16,185,129,.12) }
.gp-balance-label { font-size:10px; color:var(--gp-muted) }
.gp-balance-value { font-size:15px; font-weight:800; color:var(--gp-green) }
.gp-profile-bar-actions { display:flex; align-items:center; gap:6px }

/* ═══ SECTION SWITCHER ═══ */
.gp-section-switcher { display:flex; gap:8px; margin-bottom:20px; padding:4px; background:var(--gp-card); border:1px solid var(--gp-border); border-radius:16px; overflow:hidden }
.gp-switcher-btn { flex:1; padding:10px 16px; border-radius:12px; border:0; font-size:.8rem; font-weight:700; cursor:pointer; transition:all .2s; background:transparent; color:var(--gp-muted); display:flex; align-items:center; justify-content:center; gap:6px; font-family:'Plus Jakarta Sans',sans-serif; text-decoration:none }
.gp-switcher-btn:hover { color:var(--gp-text) }
.gp-switcher-btn.active { background:rgba(139,92,246,.1); color:var(--gp-accent); box-shadow:0 2px 8px rgba(139,92,246,.08) }

/* ═══ SECTION ═══ */
.gp-section { padding:16px 8px }
.gp-section-header { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:0 12px 16px; flex-wrap:wrap }
.gp-section-header h2 { font-size:1.15rem; font-weight:800; display:flex; align-items:center; gap:8px; color:var(--gp-text); flex-shrink:0; letter-spacing:-.02em }
.gp-section-header h2 i { font-size:1rem; color:var(--gp-accent) }

/* ═══ TABS ═══ */
.gp-tabs { display:flex; gap:4px; flex-wrap:nowrap; overflow-x:auto; -webkit-overflow-scrolling:touch; scrollbar-width:none }
.gp-tabs::-webkit-scrollbar { display:none }
.gp-tab { border-radius:10px; font-weight:700; transition:all .18s; padding:7px 16px; font-size:.75rem; background:rgba(148,163,184,.08); color:var(--gp-muted); border:1px solid transparent; cursor:pointer; flex-shrink:0; white-space:nowrap; font-family:'Plus Jakarta Sans',sans-serif }
.gp-tab:hover { background:rgba(139,92,246,.08); color:var(--gp-accent); border-color:rgba(139,92,246,.1) }
.gp-tab.active { background:rgba(139,92,246,.1); color:var(--gp-accent); border-color:rgba(139,92,246,.2); box-shadow:0 2px 8px rgba(139,92,246,.1) }

/* ═══ SEARCH ═══ */
.gp-search { position:relative; width:260px; flex-shrink:0; border-radius:12px; border:2px solid var(--gp-border); background:var(--gp-card); transition:border-color .2s,box-shadow .2s }
.gp-search:focus-within { border-color:var(--gp-accent); box-shadow:0 0 0 4px rgba(139,92,246,.1) }
.gp-search-icon { position:absolute; left:13px; top:50%; transform:translateY(-50%); font-size:14px; color:var(--gp-muted); pointer-events:none; transition:color .2s }
.gp-search:focus-within .gp-search-icon { color:var(--gp-accent) }
.gp-search-input { width:100%; padding:10px 42px 10px 40px; border:0; border-radius:12px; font:inherit; font-size:.78rem; background:transparent; color:var(--gp-text); box-sizing:border-box; outline:none; font-family:'Plus Jakarta Sans',sans-serif }
.gp-search-input::placeholder { color:var(--gp-muted) }
.gp-search-clear { position:absolute; right:8px; top:50%; transform:translateY(-50%); width:22px; height:22px; border-radius:50%; border:0; background:rgba(148,163,184,.12); color:var(--gp-muted); display:none; align-items:center; justify-content:center; cursor:pointer; font-size:10px; transition:background .2s,transform .2s; padding:0; line-height:1 }
.gp-search-clear:hover { background:rgba(139,92,246,.12); color:var(--gp-accent); transform:translateY(-50%) scale(1.1) }

/* ═══ CARD GRID ═══ */
.gp-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(340px,1fr)); gap:16px; padding:0 8px }
.gp-card { background:var(--gp-card); border-radius:22px; padding:0; transition:all .35s cubic-bezier(.34,1.56,.64,1); position:relative; overflow:hidden; border:1px solid var(--gp-border); box-shadow:0 4px 16px rgba(0,0,0,.02); display:flex; flex-direction:column }
.gp-card::before { content:''; position:absolute; inset:0; border-radius:22px; padding:1px; background:linear-gradient(135deg,rgba(139,92,246,.12),transparent 40%,transparent 60%,rgba(5,150,105,.08)); -webkit-mask:linear-gradient(#fff 0 0) content-box,linear-gradient(#fff 0 0); -webkit-mask-composite:xor; mask:linear-gradient(#fff 0 0) content-box,linear-gradient(#fff 0 0); mask-composite:exclude; pointer-events:none }
.gp-card:hover { transform:translateY(-4px); box-shadow:0 16px 48px rgba(139,92,246,.08); border-color:rgba(139,92,246,.2) }
.gp-card-accent { height:4px; flex-shrink:0 }
.gp-card-head { display:flex; align-items:center; gap:10px; padding:16px 18px 0 }
.gp-card-icon { width:44px; height:44px; border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:20px; flex-shrink:0 }
.gp-badge { font-size:.6rem; font-weight:700; padding:4px 12px; border-radius:999px; letter-spacing:.3px; display:inline-flex; align-items:center; gap:4px }
.badge-live { background:rgba(5,150,105,.12); color:#059669 }
.badge-upcoming { background:rgba(59,130,246,.12); color:#3b82f6 }
.badge-ongoing { background:rgba(251,191,36,.12); color:#d97706 }
.badge-completed { background:rgba(139,92,246,.12); color:#7c3aed }
.badge-cancelled { background:rgba(220,38,38,.12); color:#dc2626 }
.dark .badge-live { background:rgba(5,150,105,.15); color:#34d399 }
.dark .badge-upcoming { background:rgba(59,130,246,.15); color:#93c5fd }
.dark .badge-ongoing { background:rgba(251,191,36,.15); color:#fbbf24 }
.dark .badge-completed { background:rgba(139,92,246,.15); color:#a78bfa }
.dark .badge-cancelled { background:rgba(220,38,38,.15); color:#fca5a5 }
.gp-card-body { padding:10px 18px 6px; flex:1 }
.gp-card-body h3 { font-size:1rem; font-weight:800; color:var(--gp-text); margin:0 0 4px; letter-spacing:-.02em }
.gp-card-tag { font-size:.7rem; color:var(--gp-muted); display:inline-flex; align-items:center; gap:4px; margin-right:8px }
.gp-card-host { font-size:.7rem; color:var(--gp-muted); display:inline-flex; align-items:center; gap:4px }
.gp-card-body p { font-size:.78rem; color:var(--gp-muted); margin:4px 0 0; line-height:1.45 }
.gp-card-meta { display:grid; grid-template-columns:1fr 1fr; gap:6px 12px; padding:4px 18px 12px; font-size:.75rem; color:var(--gp-muted) }
.gp-card-meta > div { display:flex; align-items:center; gap:6px }
.gp-card-meta i { font-size:.65rem; width:14px; text-align:center; opacity:.7 }
.gp-countdown { font-size:.68rem; font-weight:700; color:var(--gp-accent); margin-left:auto }
.gp-card-actions { display:flex; align-items:center; gap:6px; padding:8px 18px 16px; flex-wrap:wrap; border-top:1px solid var(--gp-border); margin:0 18px 0; padding:12px 0 16px }

/* ═══ BUTTONS (P2P style) ═══ */
.gp-btn { display:inline-flex; align-items:center; justify-content:center; gap:6px; border:0; cursor:pointer; transition:all .2s; font-weight:700; font-family:'Plus Jakarta Sans',sans-serif; text-decoration:none; flex-shrink:0; font-size:.78rem; padding:10px 18px; border-radius:12px }
.gp-btn:active { transform:scale(.97) }
.gp-btn-primary { background:linear-gradient(135deg,#8b5cf6,#7c3aed); color:#fff; box-shadow:0 4px 12px rgba(139,92,246,.2) }
.gp-btn-primary:hover { transform:translateY(-2px); box-shadow:0 8px 20px rgba(139,92,246,.3) }
.gp-btn-accent { background:linear-gradient(135deg,#8b5cf6,#7c3aed); color:#fff; box-shadow:0 4px 12px rgba(139,92,246,.2) }
.gp-btn-accent:hover { transform:translateY(-2px); box-shadow:0 8px 20px rgba(139,92,246,.3) }
.gp-btn-gradient { background:linear-gradient(135deg,#8b5cf6,#6366f1); color:#fff; box-shadow:0 4px 14px rgba(139,92,246,.25) }
.gp-btn-gradient:hover { transform:translateY(-2px); box-shadow:0 8px 24px rgba(139,92,246,.35) }
.gp-btn-gradient--gold { background:linear-gradient(135deg,#f59e0b,#d97706); box-shadow:0 4px 14px rgba(245,158,11,.25) }
.gp-btn-gradient--gold:hover { transform:translateY(-2px); box-shadow:0 8px 24px rgba(245,158,11,.35) }
.gp-btn-ghost { background:transparent; color:var(--gp-muted); border:1px solid transparent }
.gp-btn-ghost:hover { background:rgba(148,163,184,.08); color:var(--gp-text) }
.gp-btn-outline { background:transparent; color:var(--gp-text); border:2px solid var(--gp-border) }
.gp-btn-outline:hover { border-color:var(--gp-accent); color:var(--gp-accent); background:rgba(139,92,246,.04) }
.gp-btn-success { background:linear-gradient(135deg,#059669,#10b981); color:#fff; box-shadow:0 4px 12px rgba(5,150,105,.2) }
.gp-btn-success:hover { transform:translateY(-2px); box-shadow:0 8px 20px rgba(5,150,105,.3) }
.gp-btn-disabled { background:rgba(148,163,184,.08); color:var(--gp-muted); cursor:not-allowed; opacity:.6; transform:none !important; box-shadow:none !important }
.gp-btn-sm { font-size:.72rem; padding:9px 16px; border-radius:10px }
.gp-btn-xs { font-size:.65rem; padding:6px 12px; border-radius:9px }
.gp-agent-badge { display:inline-flex; align-items:center; gap:4px; font-size:.6rem; font-weight:700; padding:3px 10px; border-radius:999px; background:rgba(251,191,36,.12); color:#d97706 }
.gp-modal-actions .gp-btn { min-width:80px; justify-content:center }

/* ═══ ROLE BADGE ═══ */
.gp-role-badge { display:inline-flex; align-items:center; gap:4px; font-size:.6rem; font-weight:700; padding:2px 10px; border-radius:999px; margin-top:2px }
.gp-role-agent { background:rgba(251,191,36,.12); color:#d97706 }
.gp-role-gamer { background:rgba(139,92,246,.1); color:var(--gp-accent) }

/* ═══ MY STUFF ═══ */
.gp-my-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; padding:0 8px }
.gp-my-panel { background:var(--gp-card); border:1px solid var(--gp-border); border-radius:20px; overflow:hidden; box-shadow:var(--gp-shadow) }
.gp-my-panel-header { display:flex; align-items:center; gap:8px; padding:14px 20px; font-size:.85rem; font-weight:800; border-bottom:1px solid var(--gp-border); color:var(--gp-text) }
.gp-my-panel-header i { color:var(--gp-accent) }
.gp-my-panel-header .gp-count { margin-left:auto; font-size:.7rem; font-weight:700; background:rgba(139,92,246,.1); color:var(--gp-accent); padding:2px 10px; border-radius:999px }
.gp-my-list { padding:4px }
.gp-my-item { display:flex; align-items:center; gap:12px; padding:12px 16px; border-radius:14px; transition:background .15s }
.gp-my-item:hover { background:var(--gp-bg) }
.gp-my-icon { width:40px; height:40px; border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0 }
.gp-my-info { flex:1; min-width:0 }
.gp-my-info strong { display:block; font-size:.82rem; font-weight:700; color:var(--gp-text) }
.gp-my-info span { font-size:.7rem; color:var(--gp-muted); display:flex; align-items:center; gap:4px }
.gp-my-empty { text-align:center; padding:24px 16px; font-size:.78rem; color:var(--gp-muted) }
.gp-agent-quick { display:flex; gap:8px; padding:14px 20px }
.gp-agent-quick .gp-btn { flex:1; justify-content:center }

/* ═══ EMPTY STATE ═══ */
.gp-empty { text-align:center; padding:48px 20px; color:var(--gp-muted); grid-column:1/-1 }
.gp-empty i { font-size:2.5rem; display:block; margin-bottom:12px; opacity:.3 }
.gp-empty h3 { font-size:1.05rem; font-weight:700; color:var(--gp-text); margin:0 0 4px }
.gp-empty p { font-size:.8rem; margin:0 }

/* ═══ LEADERBOARD ═══ */
.gp-lb-row { display:grid; grid-template-columns:36px 1fr 80px 90px 60px; gap:8px; padding:12px 16px; align-items:center; border-bottom:1px solid var(--gp-border); transition:background .15s; font-size:.78rem; cursor:pointer; text-decoration:none; color:inherit }
.gp-lb-row:last-child { border-bottom:none }
.gp-lb-row:hover { background:var(--gp-bg) }
.gp-lb-rank { font-weight:800; color:var(--gp-muted); text-align:center }
.gp-lb-rank.gold { color:#f59e0b } .gp-lb-rank.silver { color:#94a3b8 } .gp-lb-rank.bronze { color:#cd7f32 }
.gp-lb-cell { font-weight:700; color:var(--gp-text); text-align:center }
.gp-lb-prize { color:var(--gp-green); text-align:center; font-weight:700 }

/* ═══ MODALS (P2P design) ═══ */
.gp-overlay { position:fixed; inset:0; background:rgba(0,0,0,.6); z-index:99998; backdrop-filter:blur(6px); -webkit-backdrop-filter:blur(6px) }
.gp-overlay.hidden { display:none }
.gp-modal { position:fixed; inset:0; z-index:99999; display:flex; align-items:center; justify-content:center; animation:gpModalIn .3s ease }
.gp-modal.hidden { display:none }
@keyframes gpModalIn { from { opacity:0; transform:translateY(30px) scale(.98) } to { opacity:1; transform:translateY(0) scale(1) } }
.gp-modal-panel { background:var(--gp-card); width:100%; max-width:520px; max-height:92vh; border-radius:28px; overflow-y:auto; box-shadow:0 10px 40px rgba(0,0,0,.2); margin:16px; scrollbar-width:none; -ms-overflow-style:none }
.gp-modal-panel::-webkit-scrollbar { display:none }
.gp-modal-panel.lg { max-width:640px }
.gp-modal-panel.sm { max-width:440px }
.gp-modal-panel--crown { background:linear-gradient(180deg,var(--gp-card),rgba(255,255,255,.95)) }
.dark .gp-modal-panel--crown { background:linear-gradient(180deg,var(--gp-card),rgba(30,41,59,.98)) }
.gp-modal-head { display:flex; align-items:center; justify-content:space-between; padding:18px 22px; border-bottom:1px solid var(--gp-border); position:sticky; top:0; background:var(--gp-card); z-index:2 }
.gp-modal-head h3 { margin:0; font-size:1.05rem; font-weight:800; display:flex; align-items:center; gap:8px; color:var(--gp-text) }
.gp-modal-close { width:34px; height:34px; border-radius:50%; border:0; background:rgba(0,0,0,.05); cursor:pointer; display:flex; align-items:center; justify-content:center; color:var(--gp-muted); font-size:14px; transition:all .2s; backdrop-filter:blur(4px) }
.gp-modal-close:hover { background:rgba(239,68,68,.12); color:#dc2626; transform:rotate(90deg) }
.dark .gp-modal-close { background:rgba(255,255,255,.08); color:#9ca3af }
.dark .gp-modal-close:hover { background:rgba(239,68,68,.2); color:#fca5a5 }
.gp-modal-body { padding:20px 22px 24px }
.gp-modal-sub { font-size:.82rem; color:var(--gp-muted); margin:0 0 12px }

/* ═══ FORM ELEMENTS ═══ */
.gp-form-group { display:flex; flex-direction:column; gap:4px }
.gp-form-group label { font-size:.72rem; font-weight:700; color:var(--gp-text) }
.gp-form-hint { font-size:.65rem; color:var(--gp-muted); margin-top:2px }
.gp-input { width:100%; padding:10px 14px; border-radius:12px; border:2px solid var(--gp-border); font-size:.82rem; font-weight:700; outline:none; box-sizing:border-box; font-family:'Plus Jakarta Sans',sans-serif; background:var(--gp-card); color:var(--gp-text); transition:border-color .2s,box-shadow .2s; box-shadow:0 2px 4px rgba(0,0,0,.02) }
.gp-input:focus { border-color:var(--gp-accent); box-shadow:0 0 0 4px rgba(139,92,246,.1) }
.gp-input::placeholder { color:var(--gp-muted); font-weight:400 }
select.gp-input { cursor:pointer; appearance:none; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236b7280' d='M6 8L1 3h10z'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 14px center; padding:10px 40px 10px 14px }
select.gp-input:hover { border-color:var(--gp-accent); background-color:rgba(139,92,246,.02) }
.dark select.gp-input:hover { background-color:rgba(139,92,246,.04) }
select.gp-input option { padding:10px 12px; font-weight:500; background:var(--gp-card); color:var(--gp-text) }
.gp-input-group { display:flex; align-items:stretch; border-radius:12px; overflow:hidden; border:2px solid var(--gp-border); transition:border-color .2s,box-shadow .2s }
.gp-input-group:focus-within { border-color:var(--gp-accent); box-shadow:0 0 0 4px rgba(139,92,246,.1) }
.gp-input-group .gp-input { border:none; border-radius:0 }
.gp-input-group .gp-input:focus { box-shadow:none }
.gp-input-prefix { display:flex; align-items:center; padding:0 12px; font-size:.82rem; font-weight:700; background:var(--gp-bg); color:var(--gp-muted); border-right:1px solid var(--gp-border) }
.gp-form-grid { display:grid; gap:12px; margin-bottom:10px }
.gp-form-grid.two { grid-template-columns:1fr 1fr }
.gp-form-grid.three { grid-template-columns:1fr 1fr 1fr }

/* ═══ RADIO ═══ */
.gp-radio-group { display:flex; gap:8px; flex-wrap:wrap }
.gp-radio { display:flex; align-items:center; gap:8px; padding:10px 14px; border-radius:12px; border:2px solid var(--gp-border); cursor:pointer; transition:all .18s; flex:1; font-size:.78rem; font-weight:600; color:var(--gp-text); background:var(--gp-card) }
.gp-radio:has(input:checked) { border-color:var(--gp-accent); background:rgba(139,92,246,.06); color:var(--gp-accent) }
.gp-radio input { accent-color:var(--gp-accent) }

/* ═══ ICON & COLOR PICKERS ═══ */
.gp-icon-picker { display:flex; gap:6px; flex-wrap:wrap }
.gp-icon-opt { width:40px; height:40px; border-radius:12px; border:2px solid transparent; background:var(--gp-bg); cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:16px; color:var(--gp-muted); transition:all .15s }
.gp-icon-opt:hover { border-color:var(--gp-accent); color:var(--gp-accent) }
.gp-icon-opt.active { border-color:var(--gp-accent); background:rgba(139,92,246,.1); color:var(--gp-accent) }
.gp-color-picker { display:flex; gap:6px; flex-wrap:wrap }
.gp-color-opt { width:32px; height:32px; border-radius:50%; border:3px solid transparent; cursor:pointer; transition:all .15s; outline:none }
.gp-color-opt:hover { transform:scale(1.15) }
.gp-color-opt.active { border-color:var(--gp-accent); box-shadow:0 0 0 2px rgba(139,92,246,.3); transform:scale(1.1) }

/* ═══ AGENT BADGE GRID ═══ */
.gp-agent-badge-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px }
.gp-agent-badge--premium { position:relative; padding:16px; border-radius:18px; background:var(--gp-card); border:1px solid var(--gp-border); overflow:hidden; text-align:center; transition:all .2s }
.gp-agent-badge--premium:hover { border-color:rgba(139,92,246,.2); transform:translateY(-2px) }
.gp-agent-badge-glow { position:absolute; top:-30px; right:-30px; width:80px; height:80px; border-radius:50%; background:radial-gradient(circle,rgba(139,92,246,.08),transparent); pointer-events:none }
.gp-agent-badge-icon { display:block; font-size:1.8rem; margin-bottom:6px }
.gp-agent-badge-label { font-size:.78rem; font-weight:700; color:var(--gp-text); display:block }
.gp-agent-badge-desc { font-size:.65rem; color:var(--gp-muted); margin-top:2px }

/* ═══ AGENT FEE ═══ */
.gp-agent-fee { position:relative; text-align:center; padding:18px; border-radius:18px; background:linear-gradient(135deg,rgba(251,191,36,.08),rgba(245,158,11,.04)); border:1px solid rgba(251,191,36,.2); margin-bottom:16px; overflow:hidden }
.gp-agent-fee-glow { position:absolute; top:-50%; left:-50%; width:200%; height:200%; background:radial-gradient(circle,rgba(251,191,36,.05),transparent 60%); animation:gpGlow 4s ease-in-out infinite; pointer-events:none }
@keyframes gpGlow { 0%,100%{opacity:.5;transform:translate(0,0)} 50%{opacity:1;transform:translate(10%,-10%)} }
.gp-agent-fee-label { display:block; font-size:.72rem; color:var(--gp-muted); margin-bottom:4px; font-weight:600 }
.gp-agent-fee-amount { display:block; font-size:1.4rem; font-weight:900; color:#d97706 }

/* ═══ AGENT HERO ═══ */
.gp-agent-hero { text-align:center; padding:24px 16px 20px; position:relative; overflow:hidden; border-radius:18px; background:linear-gradient(135deg,rgba(139,92,246,.04),rgba(99,102,241,.02)); border:1px solid var(--gp-border); margin-bottom:16px }
.gp-agent-hero-glow { position:absolute; top:-30%; right:-20%; width:180px; height:180px; border-radius:50%; background:radial-gradient(circle,rgba(139,92,246,.08),transparent); pointer-events:none }
.gp-agent-hero-icon { font-size:2.8rem; display:block; margin-bottom:8px }
.gp-agent-hero-title { font-size:1.15rem; font-weight:800; color:var(--gp-text); margin-bottom:4px }
.gp-agent-hero-sub { font-size:.78rem; color:var(--gp-muted) }

/* ═══ PAYMENT (BECOME AGENT) ═══ */
.gp-pay-note { padding:14px 16px; border-radius:14px; background:rgba(251,191,36,.08); border:1px solid rgba(251,191,36,.15); font-size:.78rem; color:#92400e; margin-bottom:16px; display:flex; align-items:center; gap:8px }
.dark .gp-pay-note { color:#fbbf24; background:rgba(251,191,36,.06) }
.gp-pay-note i { font-size:1rem; color:#d97706; flex-shrink:0 }
.ba-method-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; margin-bottom:16px }
.ba-method-card { display:flex; align-items:center; justify-content:center; padding:12px; border-radius:16px; border:2px solid var(--gp-border); background:var(--gp-card); cursor:pointer; transition:all .2s; min-height:60px }
.ba-method-card:hover { border-color:var(--gp-accent); background:rgba(139,92,246,.04) }
.ba-method-card.active { border-color:#E2136E; background:#fdf2f8 }
.dark .ba-method-card.active { background:rgba(226,19,110,.1) }
.ba-method-card img { max-height:36px; max-width:100%; object-fit:contain }
.ba-merchant-box--premium { padding:16px; border-radius:16px; background:var(--gp-bg); border:1px solid var(--gp-border); margin-bottom:16px; display:none }
.ba-merchant-header { display:flex; align-items:center; gap:8px; font-size:.85rem; font-weight:800; margin-bottom:12px; color:var(--gp-text) }
.ba-merchant-header img { height:22px }
.ba-instr-step { font-size:.75rem; color:var(--gp-muted); padding:5px 0; line-height:1.5 }
.ba-instr-step strong { color:var(--gp-text) }
.ba-merchant-num { font-size:.9rem; font-weight:800; letter-spacing:.5px }
.ba-copy-btn { display:inline-flex; align-items:center; gap:4px; padding:2px 10px; border-radius:8px; border:0; background:rgba(139,92,246,.1); color:var(--gp-accent); font-size:.6rem; font-weight:700; cursor:pointer; transition:all .15s; vertical-align:middle }
.ba-copy-btn:hover { background:rgba(139,92,246,.2) }
.ba-instr-footer { margin-top:10px; padding-top:10px; border-top:1px solid var(--gp-border); font-size:.72rem; color:var(--gp-muted) }
.ba-inputs-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px }
.ba-input-wrap { position:relative; display:flex; align-items:center; border-radius:12px; border:2px solid var(--gp-border); transition:all .2s }
.ba-input-wrap:focus-within { border-color:var(--gp-accent); box-shadow:0 0 0 4px rgba(139,92,246,.1) }
.ba-input-icon { display:flex; align-items:center; justify-content:center; width:42px; flex-shrink:0; color:var(--gp-muted); font-size:1rem }
.ba-input { flex:1; padding:10px 14px 10px 0; border:0; font-size:.8rem; font-weight:600; outline:none; font-family:'Plus Jakarta Sans',sans-serif; background:transparent; color:var(--gp-text) }
.ba-input::placeholder { color:var(--gp-muted); font-weight:400 }
.ba-actions { margin-top:12px }
.gp-modal-step { display:none; animation:gpModalIn .3s ease }
.gp-modal-step.active { display:block }
.gp-balance-notice { font-size:.72rem; color:var(--gp-muted); padding:8px 0; text-align:right }

/* ═══ FEEDBACK & TOAST ═══ */
.gp-feedback { padding:10px 14px; border-radius:12px; font-size:.75rem; font-weight:600; margin-top:12px; display:none }
.gp-feedback.success { display:block; background:rgba(5,150,105,.1); color:var(--gp-green); border:1px solid rgba(5,150,105,.2) }
.gp-feedback.error { display:block; background:rgba(220,38,38,.1); color:var(--gp-red); border:1px solid rgba(220,38,38,.2) }
.dark .gp-feedback.success { background:rgba(5,150,105,.12); color:#34d399 }
.dark .gp-feedback.error { background:rgba(220,38,38,.12); color:#fca5a5 }
.gp-feedback.hidden { display:none }
.gp-toast { position:fixed; bottom:24px; right:24px; z-index:999999; padding:12px 20px; border-radius:16px; font-size:.82rem; font-weight:700; display:flex; align-items:center; gap:10px; box-shadow:0 8px 32px rgba(0,0,0,.12); animation:gpToastIn .35s ease; max-width:360px; color:#fff }
.gp-toast.success { background:linear-gradient(135deg,#059669,#10b981) }
.gp-toast.error { background:linear-gradient(135deg,#dc2626,#ef4444) }
.gp-toast.info { background:linear-gradient(135deg,#3b82f6,#6366f1) }
@keyframes gpToastIn { from { opacity:0; transform:translateY(20px) scale(.95) } to { opacity:1; transform:translateY(0) scale(1) } }
.gp-loading { text-align:center; padding:24px; font-size:.78rem; color:var(--gp-muted) }

/* ═══ SUCCESS OVERLAY ═══ */
.gp-success-overlay { position:fixed; inset:0; z-index:999999; display:none; align-items:center; justify-content:center; background:rgba(0,0,0,.45); backdrop-filter:blur(6px) }
.gp-success-box { text-align:center; padding:40px 48px; border-radius:28px; background:var(--gp-card); box-shadow:0 20px 60px rgba(0,0,0,.15); max-width:380px; animation:gpModalIn .3s ease }
.gp-success-icon { width:64px; height:64px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 16px; font-size:1.5rem }
.gp-success-icon { background:rgba(5,150,105,.1); color:var(--gp-green) }
.gp-fail-icon { width:64px; height:64px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 16px; font-size:1.5rem; background:rgba(220,38,38,.1); color:var(--gp-red) }
.gp-success-text { font-size:1.2rem; font-weight:800; color:var(--gp-text); margin-bottom:4px }
.gp-success-sub { font-size:.78rem; color:var(--gp-muted) }

/* ═══ PARTICIPANT ITEMS ═══ */
.gp-participants { display:flex; flex-direction:column; gap:8px }
.gp-participant { display:flex; align-items:center; gap:12px; padding:10px 14px; border-radius:14px; background:var(--gp-bg); transition:background .15s }
.gp-participant img { width:36px; height:36px; border-radius:50%; object-fit:cover; flex-shrink:0 }
.gp-participant-info { flex:1; min-width:0 }
.gp-participant-info strong { display:block; font-size:.8rem; font-weight:700; color:var(--gp-text) }
.gp-participant-info span { font-size:.68rem; color:var(--gp-muted) }
.gp-team-members { display:flex; flex-direction:column; gap:6px }
.gp-team-member { display:flex; align-items:center; gap:12px; padding:10px 14px; border-radius:14px; background:var(--gp-bg) }
.gp-team-member img { width:34px; height:34px; border-radius:50%; object-fit:cover; flex-shrink:0 }
.gp-team-member-info { flex:1; min-width:0 }
.gp-team-member-info strong { display:block; font-size:.78rem; font-weight:700; color:var(--gp-text) }
.gp-team-member-role { font-size:.65rem; color:var(--gp-muted); text-transform:capitalize }
.gp-team-add { display:flex; gap:8px; padding:12px 16px }
.gp-team-add .gp-input { flex:1 }

/* ═══ TXN ITEMS ═══ */
.gp-txns { display:flex; flex-direction:column; gap:4px }
.gp-txn-item { display:flex; align-items:center; gap:12px; padding:10px 14px; border-radius:14px; background:var(--gp-bg); transition:background .15s }
.gp-txn-icon { width:34px; height:34px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:13px; flex-shrink:0 }
.gp-txn-icon.credit { background:rgba(5,150,105,.1); color:var(--gp-green) }
.gp-txn-icon.debit { background:rgba(220,38,38,.1); color:var(--gp-red) }
.gp-txn-info { flex:1; min-width:0 }
.gp-txn-info strong { display:block; font-size:.78rem; font-weight:700; color:var(--gp-text) }
.gp-txn-info span { font-size:.65rem; color:var(--gp-muted) }
.gp-txn-amount { font-size:.85rem; font-weight:800; flex-shrink:0 }
.gp-txn-amount.credit { color:var(--gp-green) }
.gp-txn-amount.debit { color:var(--gp-red) }

/* ═══ MISC ═══ */
.gp-join-fee { font-size:.78rem; color:var(--gp-muted); padding:8px 14px; border-radius:12px; background:rgba(16,185,129,.06); border:1px solid rgba(16,185,129,.1); margin-bottom:12px }
.gp-modal-actions { display:flex; gap:8px; margin-top:14px; justify-content:flex-end }
.hidden { display:none !important }
/* ═══ PROFILE CARD ═══ */
.gp-profile-card { text-align:center; margin-bottom:20px; border-radius:20px; overflow:hidden; border:1px solid var(--gp-border); background:var(--gp-card) }
.gp-profile-card-cover { height:80px; background:linear-gradient(135deg,rgba(139,92,246,.3),rgba(99,102,241,.15)); position:relative; overflow:hidden }
.gp-profile-card-cover img { display:none }
.gp-profile-card-avatar { width:72px; height:72px; border-radius:50%; overflow:hidden; border:4px solid var(--gp-card); margin:-40px auto 8px; box-shadow:0 4px 16px rgba(0,0,0,.1); position:relative }
.gp-profile-card-avatar img { width:100%; height:100%; object-fit:cover }
.gp-profile-card-body h4 { font-size:1.05rem; font-weight:800; color:var(--gp-text); margin:0; letter-spacing:-.02em }
.gp-profile-card-tag { font-size:.75rem; color:var(--gp-accent); font-weight:600 }

/* ═══ PROFILE STATS ═══ */
.gp-profile-stats { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:16px }
.gp-profile-stat { padding:14px 12px; border-radius:14px; background:var(--gp-bg); border:1px solid var(--gp-border); text-align:center; transition:all .2s }
.gp-profile-stat:hover { border-color:rgba(139,92,246,.2) }
.gp-profile-stat-icon { display:block; font-size:1.2rem; margin-bottom:4px; color:var(--gp-accent) }
.gp-profile-stat-label { display:block; font-size:.6rem; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:var(--gp-muted); margin-bottom:2px }
.gp-profile-stat-value { display:block; font-size:.82rem; font-weight:800; color:var(--gp-text) }

/* ═══ PROFILE STATS TITLE ═══ */
.gp-profile-stats-title { font-size:.78rem; font-weight:700; color:var(--gp-text); display:flex; align-items:center; gap:6px; margin-bottom:8px; padding-top:4px }
.gp-profile-stats-title i { color:var(--gp-accent); font-size:.7rem }

/* ═══ GAME SKILLS ═══ */
.gp-profile-game-skills { display:flex; flex-direction:column; gap:6px; margin-bottom:14px }
.gp-profile-game-skill { display:flex; align-items:center; gap:8px; padding:8px 12px; border-radius:12px; background:var(--gp-bg); border:1px solid var(--gp-border); transition:all .15s }
.gp-profile-game-skill:hover { border-color:rgba(139,92,246,.15) }
.gp-game-skill-icon { width:28px; height:28px; border-radius:8px; display:flex; align-items:center; justify-content:center; background:rgba(139,92,246,.1); color:var(--gp-accent); font-size:.7rem; flex-shrink:0 }
.gp-game-skill-name { font-size:.75rem; font-weight:700; color:var(--gp-text); flex:1; min-width:0 }
.gp-game-skill-level { font-size:.65rem; font-weight:600; color:var(--gp-muted); flex-shrink:0; width:72px; text-align:right }
.gp-game-skill-bar { flex:0 0 80px; height:4px; border-radius:99px; background:var(--gp-border); overflow:hidden }
.gp-game-skill-bar span { display:block; height:100%; border-radius:99px; background:linear-gradient(90deg,var(--gp-accent),#a78bfa); transition:width .3s }
.gp-profile-no-skills { text-align:center; padding:16px; font-size:.72rem; color:var(--gp-muted); border:1px dashed var(--gp-border); border-radius:12px }
.gp-profile-no-skills a { color:var(--gp-accent); font-weight:600 }
.gp-profile-info-links { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:14px; font-size:.72rem; color:var(--gp-muted) }
.gp-profile-info-links i { width:14px; text-align:center }

/* ═══ GAME SKILLS EDITOR ═══ */
.gp-game-skills-editor { display:flex; flex-direction:column; gap:5px; margin-bottom:12px }
.gp-game-skill-row { display:flex; align-items:center; gap:8px; padding:6px 10px; border-radius:10px; background:var(--gp-bg); border:1px solid var(--gp-border) }
.gp-game-skill-icon-preview { width:26px; height:26px; border-radius:7px; display:flex; align-items:center; justify-content:center; background:rgba(139,92,246,.1); color:var(--gp-accent); font-size:.65rem; flex-shrink:0 }
.gp-game-skill-row-name { font-size:.72rem; font-weight:600; color:var(--gp-text); flex:1; min-width:0 }
.gp-game-skill-row-select { width:125px; font-size:.7rem; padding:6px 28px 6px 10px; border-radius:10px; flex-shrink:0 }
.gp-add-game-skill-btn { margin-top:2px; font-size:.7rem; padding:6px 12px; border-radius:10px; align-self:flex-start }

/* ═══ PROFILE BIO ═══ */
.gp-profile-bio { margin-bottom:16px; padding:14px 16px; border-radius:14px; background:var(--gp-bg); border:1px solid var(--gp-border) }
.gp-profile-bio label { font-size:.65rem; font-weight:700; color:var(--gp-muted); display:flex; align-items:center; gap:4px; margin-bottom:4px }
.gp-profile-bio label i { font-size:.55rem; opacity:.5 }
.gp-profile-bio p { font-size:.8rem; color:var(--gp-text); margin:0; line-height:1.5 }

/* ═══ PROFILE BAR (v2) ═══ */
.gp-profile-bar { display:flex; align-items:center; gap:12px; padding:10px 16px; margin:0 0 16px; border-radius:20px; background:linear-gradient(135deg,var(--gp-card),rgba(139,92,246,.03)); border:1px solid var(--gp-border); box-shadow:0 2px 12px rgba(0,0,0,.02); position:relative; overflow:hidden }
.gp-profile-bar::before { content:''; position:absolute; inset:0; border-radius:20px; padding:1px; background:linear-gradient(135deg,rgba(139,92,246,.08),transparent 50%,rgba(5,150,105,.04)); -webkit-mask:linear-gradient(#fff 0 0) content-box,linear-gradient(#fff 0 0); -webkit-mask-composite:xor; mask:linear-gradient(#fff 0 0) content-box,linear-gradient(#fff 0 0); mask-composite:exclude; pointer-events:none }
.gp-profile-avatar-wrap { position:relative; width:40px; height:40px; flex-shrink:0; cursor:pointer; transition:transform .2s }
.gp-profile-avatar-wrap:hover { transform:scale(1.08) }
.gp-profile-avatar-wrap img { width:100%; height:100%; border-radius:50%; object-fit:cover; position:relative; z-index:1 }
.gp-profile-avatar-ring { position:absolute; inset:-3px; border-radius:50%; background:linear-gradient(135deg,#8b5cf6,#6366f1,#8b5cf6); z-index:0; animation:gpRingSpin 3s linear infinite; opacity:.7 }
@keyframes gpRingSpin { 0%{transform:rotate(0deg)} 100%{transform:rotate(360deg)} }
.gp-profile-meta { flex:1; min-width:0; cursor:pointer }
.gp-profile-name { display:flex; align-items:baseline; gap:6px; font-size:.85rem; font-weight:700; color:var(--gp-text); white-space:nowrap; overflow:hidden }
.gp-profile-nick { font-size:.65rem; font-weight:600; color:var(--gp-accent); overflow:hidden; text-overflow:ellipsis }
.gp-profile-tags { display:flex; align-items:center; gap:4px; margin-top:1px }
.gp-profile-balance { display:flex; align-items:center; gap:6px; padding:5px 12px; border-radius:999px; background:linear-gradient(135deg,rgba(16,185,129,.08),rgba(5,150,105,.04)); border:1px solid rgba(16,185,129,.12); flex-shrink:0 }
.gp-balance-icon { font-size:.7rem; color:var(--gp-green) }
.gp-balance-amount { font-size:.82rem; font-weight:800; color:var(--gp-green); letter-spacing:-.3px }
.gp-balance-add { display:flex; align-items:center; justify-content:center; width:18px; height:18px; border-radius:50%; background:rgba(16,185,129,.12); color:var(--gp-green); font-size:8px; text-decoration:none; transition:all .15s }
.gp-balance-add:hover { background:var(--gp-green); color:#fff; transform:scale(1.15) }
.gp-profile-actions { display:flex; align-items:center; gap:5px; flex-shrink:0 }
.gp-profile-actions .gp-btn { width:34px; height:34px; padding:0; border-radius:10px; font-size:.75rem }

/* ═══ BECOME AGENT IN PROFILE ═══ */
.gp-profile-become-agent { display:flex; align-items:center; gap:12px; padding:14px 16px; border-radius:16px; background:linear-gradient(135deg,rgba(251,191,36,.06),rgba(245,158,11,.03)); border:1px solid rgba(251,191,36,.15); margin-bottom:14px }
.gp-profile-agent-icon { width:40px; height:40px; border-radius:12px; display:flex; align-items:center; justify-content:center; background:rgba(251,191,36,.12); color:#d97706; font-size:1.1rem; flex-shrink:0 }
.gp-profile-become-agent div { flex:1; min-width:0 }
.gp-profile-become-agent strong { display:block; font-size:.78rem; color:var(--gp-text) }
.gp-profile-become-agent p { font-size:.68rem; color:var(--gp-muted); margin:2px 0 0 }
.gp-profile-become-agent .gp-btn { flex-shrink:0 }

/* ═══ FULL-SCREEN MODALS ON MOBILE (P2P style) ═══ */
@media (max-width:480px) {
  .gp-modal .gp-modal-panel { max-width:none !important; max-height:none !important; border-radius:0 !important; width:100vw !important; height:100dvh !important; margin:0 !important; box-shadow:none !important }
  .gp-modal .gp-modal-head { border-radius:0 }
  body.gp-modal-open { overflow:hidden }
}

/* ═══ CUSTOM SELECT (P2P style) ═══ */
.gp-custom-select { position:relative; width:100% }
.gp-select-trigger { display:flex; align-items:center; gap:10px; padding:10px 14px; background:var(--gp-card); border:2px solid var(--gp-border); border-radius:12px; cursor:pointer; font-size:.78rem; font-weight:700; color:var(--gp-text); transition:all .2s; font-family:'Plus Jakarta Sans',sans-serif }
.gp-select-trigger:hover { border-color:var(--gp-accent); background:rgba(139,92,246,.04) }
.gp-select-trigger span { flex:1; text-align:left }
.gp-select-trigger::after { content:'\f078'; font-family:'Font Awesome 6 Free'; font-weight:900; font-size:10px; opacity:.4; transition:transform .2s; margin-left:4px }
.gp-custom-select.active .gp-select-trigger::after { transform:rotate(180deg); opacity:1; color:var(--gp-accent) }
.gp-select-options { position:absolute; top:calc(100% + 6px); left:0; right:0; background:var(--gp-card); border:1px solid var(--gp-border); border-radius:12px; box-shadow:0 12px 32px rgba(0,0,0,.12); z-index:200; display:none; padding:6px; animation:gpSelectIn .2s ease; max-height:220px; overflow-y:auto; scrollbar-width:none }
.gp-select-options::-webkit-scrollbar { display:none }
@keyframes gpSelectIn { from { opacity:0; transform:translateY(10px) } to { opacity:1; transform:translateY(0) } }
.gp-select-option { display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:8px; cursor:pointer; font-size:.78rem; font-weight:600; color:var(--gp-muted); transition:all .15s; margin-bottom:3px; font-family:'Plus Jakarta Sans',sans-serif }
.gp-select-option:hover { background:var(--gp-bg); color:var(--gp-accent) }
.gp-select-option.active { background:rgba(139,92,246,.1); color:var(--gp-accent) }
.gp-custom-select.active .gp-select-options { display:block }
.gp-custom-select.active .gp-select-trigger { border-color:var(--gp-accent); box-shadow:0 0 0 4px rgba(139,92,246,.1) }
.gp-filter-select { flex-shrink:0; width:200px }
.gp-modal-step-indicator { display:flex; align-items:center; justify-content:center; gap:8px; margin-bottom:18px }
.gp-step-dot { width:8px; height:8px; border-radius:50%; background:var(--gp-border); transition:all .2s }
.gp-step-dot.active { background:var(--gp-accent); width:24px; border-radius:99px }
.gp-step-line { width:32px; height:2px; background:var(--gp-border) }

/* ═══ OTP ═══ */
.gp-otp-wrap { display:flex; gap:8px; justify-content:center; margin:12px 0 }
.gp-otp-box { width:44px; height:52px; border-radius:14px; border:2px solid var(--gp-border); text-align:center; font-size:1.3rem; font-weight:800; outline:none; font-family:'Plus Jakarta Sans',sans-serif; background:var(--gp-card); color:var(--gp-text); transition:all .2s }
.gp-otp-box:focus { border-color:var(--gp-accent); box-shadow:0 0 0 4px rgba(139,92,246,.1) }

/* ═══ RESPONSIVE ═══ */
@media (max-width:768px) {
  .gp-page { padding:0 6px 1.5rem }
  .gp-hero { padding:16px 12px 0; border-radius:0 0 24px 24px }
  .gp-hero-bg { min-height:300px; border-radius:0 0 24px 24px }
  .gp-hero-content h1 { font-size:clamp(18px,5vw,28px) }
  .gp-hero-content p { font-size:.78rem }
  .gp-hero-stats { grid-template-columns:1fr 1fr; gap:10px; padding:14px 4px 18px }
  .gp-stat-card { padding:16px 10px; border-radius:18px }
  .gp-stat-card .gp-stat-icon { font-size:20px; width:46px; height:46px; border-radius:14px; margin-bottom:8px }
  .gp-stat-value { font-size:20px }
  .gp-stat-label { font-size:11px }
  .gp-stat-card:hover { transform:none }
  .gp-profile-bar { flex-wrap:wrap; gap:8px; padding:10px 14px; border-radius:18px }
  .gp-profile-avatar-wrap { width:36px; height:36px }
  .gp-profile-name { font-size:.78rem }
  .gp-profile-nick { font-size:.6rem }
  .gp-profile-balance { padding:4px 10px; gap:4px }
  .gp-balance-amount { font-size:.75rem }
  .gp-profile-actions { gap:4px }
  .gp-profile-actions .gp-btn { width:30px; height:30px; font-size:.65rem; border-radius:9px }
  .gp-section-switcher { overflow-x:auto; -webkit-overflow-scrolling:touch; gap:0; padding:3px; border-radius:14px }
  .gp-switcher-btn { white-space:nowrap; font-size:.75rem; padding:8px 12px; flex-shrink:0 }
  .gp-section { padding:10px 4px }
  .gp-section-header { flex-direction:column; gap:10px; padding:0 8px 12px }
  .gp-section-header h2 { font-size:1rem }
  .gp-filter-select { width:100% }
  .gp-search { width:100% }
  .gp-search-input { font-size:.75rem; padding:9px 38px 9px 36px }
  .gp-grid { grid-template-columns:1fr; gap:12px; padding:0 4px }
  .gp-card { border-radius:20px }
  .gp-card-body h3 { font-size:.9rem }
  .gp-card-body p { font-size:.72rem; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden }
  .gp-card-meta { font-size:.7rem; grid-template-columns:1fr 1fr; gap:4px 8px }
  .gp-card-actions .gp-btn { font-size:.65rem; padding:6px 10px; border-radius:10px }
  .gp-my-grid { grid-template-columns:1fr; gap:14px }
  .gp-my-panel { border-radius:18px }
  .gp-my-panel-header { font-size:.82rem; padding:12px 16px }
  .gp-my-item { gap:8px; padding:10px 14px; border-radius:12px }
  .gp-my-info strong { font-size:.75rem }
  .gp-my-info span { font-size:.65rem }
  .gp-agent-quick { flex-direction:column; gap:6px }
  .gp-agent-quick .gp-btn { width:100% }
  .gp-modal-panel { margin:12px; max-height:88vh; border-radius:22px }
  .gp-modal-body { padding:16px }
  .gp-form-grid.two { grid-template-columns:1fr }
  .gp-form-grid.three { grid-template-columns:1fr }
  .gp-icon-picker { gap:4px }
  .gp-icon-opt { width:36px; height:36px; font-size:14px; border-radius:10px }
  .gp-color-picker { gap:4px }
  .gp-color-opt { width:28px; height:28px }
  .ba-method-grid { gap:8px }
  .ba-method-card { padding:10px; border-radius:14px; min-height:50px }
  .gp-radio-group { flex-direction:column }
  .gp-radio { padding:10px 12px }
  .gp-participant { flex-wrap:wrap; gap:8px; padding:10px; border-radius:12px }
  .gp-team-member { flex-wrap:wrap; gap:8px; padding:10px; border-radius:12px }
  .gp-team-add { flex-direction:column; gap:8px }
  .gp-team-add .gp-btn { width:100%; justify-content:center }
  .gp-txn-item { flex-wrap:wrap; padding:10px; border-radius:12px }
  .gp-txn-amount { margin-left:auto }
  .gp-empty { padding:32px 16px; border-radius:16px }
  .gp-empty i { font-size:2rem }
  .gp-countdown { font-size:.65rem }
  .gp-agent-badge-grid { grid-template-columns:1fr 1fr; gap:8px }
  .gp-agent-hero-title { font-size:1rem }
  .ba-inputs-grid { grid-template-columns:1fr; gap:10px }
  .ba-merchant-box--premium { padding:12px; border-radius:14px }
  .ba-instr-step { font-size:.7rem }
  .gp-modal-actions { flex-wrap:wrap }
  .gp-modal-actions .gp-btn { flex:1; justify-content:center; font-size:.7rem; padding:9px 12px; border-radius:10px; min-width:0 }
  .gp-card-head .gp-card-icon { width:38px; height:38px; font-size:17px; border-radius:12px }
  .gp-card-head .gp-badge { font-size:9px; padding:3px 9px; border-radius:8px }
  .gp-card-actions .gp-agent-badge { font-size:9px; padding:2px 7px }
  .gp-card-meta i { font-size:.6rem }
  .gp-card-tag { font-size:.65rem }
  .gp-card-host { font-size:.65rem }
  .gp-my-panel .gp-my-icon { width:34px; height:34px; font-size:15px; border-radius:10px }
  .gp-badge.sm { font-size:8px; padding:2px 7px }
}
@media (max-width:480px) {
  .gp-hero-stats { gap:8px; padding:10px 2px 14px }
  .gp-stat-card { padding:12px 8px; border-radius:16px }
  .gp-stat-card .gp-stat-icon { font-size:18px; width:40px; height:40px; border-radius:12px; margin-bottom:6px }
  .gp-stat-value { font-size:17px }
  .gp-stat-label { font-size:10px }
  .gp-card { padding:0 }
  .gp-card-head { gap:8px; padding:12px 14px 0 }
  .gp-card-body { padding:8px 14px 4px }
  .gp-card-meta { padding:2px 14px 10px; grid-template-columns:1fr }
  .gp-card-actions { padding:8px 14px 14px; margin:0 14px 0; gap:5px }
  .gp-card-icon { width:34px; height:34px; font-size:15px; border-radius:10px }
  .gp-badge { font-size:9px; padding:3px 8px }
  .gp-card-actions .gp-btn { font-size:9px; padding:5px 8px; border-radius:8px }
  .gp-countdown { font-size:9px }
  .gp-section-header h2 { font-size:.9rem }
  .gp-section-header { padding:0 6px 10px; gap:8px }
  .gp-grid { padding:0 2px; gap:10px }
  .gp-profile-bar { padding:8px 10px; gap:6px; border-radius:16px }
  .gp-profile-avatar-wrap { width:32px; height:32px }
  .gp-profile-name { font-size:.72rem; gap:4px }
  .gp-profile-nick { font-size:.55rem }
  .gp-profile-balance { padding:3px 8px; gap:3px; border-radius:999px }
  .gp-balance-amount { font-size:.68rem }
  .gp-balance-add { width:16px; height:16px; font-size:7px }
  .gp-profile-actions .gp-btn { width:28px; height:28px; font-size:.6rem; border-radius:8px }
  .gp-modal-panel { border-radius:18px; margin:8px }
  .gp-modal-body { padding:14px }
  .gp-modal-head { padding:14px 16px }
  .gp-hero { padding:12px 10px 0; border-radius:0 0 20px 20px }
  .gp-hero-bg { min-height:260px; border-radius:0 0 20px 20px }
}
</style>
<div class="gp-page" id="tournamentsPage" data-csrf="<?php echo htmlspecialchars($csrfToken); ?>" data-user-id="<?php echo (int)($viewerId ?? 0); ?>" data-role="<?php echo htmlspecialchars($userRole); ?>" data-balance="<?php echo $userBalance; ?>">

    <!-- ═══ HERO ═══ -->
    <section class="gp-hero">
        <div class="gp-hero-bg"></div>
        <div class="gp-hero-content">
            <span class="gp-hero-badge"><i class="fas fa-trophy"></i> DreamBD Arena</span>
            <h1>Compete. Conquer. <span class="text-gradient">Rise Up.</span></h1>
            <p>Join tournaments, build your team, and battle for glory and prizes.</p>
            <!-- <div class="gp-hero-actions">
                <?php if ($viewerId): ?>
                    <a href="#browse" class="gp-btn gp-btn-primary" data-scroll-to="browse"><i class="fas fa-gamepad"></i> Browse tournaments</a>
                    <a href="#my-stuff" class="gp-btn gp-btn-ghost" data-scroll-to="my-stuff"><i class="fas fa-layer-group"></i> My tournaments</a>
                <?php else: ?>
                    <a href="index.php?page=register" class="gp-btn gp-btn-primary" data-page="register"><i class="fas fa-user-plus"></i> Join free</a>
                    <a href="index.php?page=login" class="gp-btn gp-btn-ghost" data-page="login"><i class="fas fa-right-to-bracket"></i> Sign in</a>
                <?php endif; ?>
            </div> -->
        </div>
        <div class="gp-hero-stats">
            <div class="gp-stat-card"><span class="gp-stat-icon"><i class="fas fa-trophy"></i></span><span class="gp-stat-value"><?php echo count($tournaments); ?></span><span class="gp-stat-label">Tournaments</span></div>
            <div class="gp-stat-card"><span class="gp-stat-icon"><i class="fas fa-coins"></i></span><span class="gp-stat-value">৳<?php echo number_format($totalPrize); ?></span><span class="gp-stat-label">Prize pool</span></div>
            <div class="gp-stat-card"><span class="gp-stat-icon"><i class="fas fa-fire"></i></span><span class="gp-stat-value"><?php echo $statusStats['live'] + $statusStats['ongoing']; ?></span><span class="gp-stat-label">Active</span></div>
            <div class="gp-stat-card"><span class="gp-stat-icon"><i class="fas fa-calendar-check"></i></span><span class="gp-stat-value"><?php echo $statusStats['upcoming']; ?></span><span class="gp-stat-label">Upcoming</span></div>
        </div>
    </section>

    <!-- ═══ PROFILE BAR (v2) ═══ -->
    <?php if ($viewerId):
        $gpNickname = $_SESSION['nickname'] ?? '';
        $gpAvatar = htmlspecialchars($_SESSION['avatar'] ?? 'default.png');
        $gpFullName = htmlspecialchars($_SESSION['full_name'] ?? ($_SESSION['username'] ?? 'User'));
    ?>
    <section class="gp-profile-bar">
        <div class="gp-profile-avatar-wrap" onclick="openGamerProfile()" title="View profile">
            <img src="assets/avatars/<?php echo $gpAvatar; ?>" alt="" onerror="this.src='assets/avatars/default.png'">
            <span class="gp-profile-avatar-ring"></span>
        </div>
        <div class="gp-profile-meta" onclick="openGamerProfile()">
            <span class="gp-profile-name"><?php echo $gpFullName; ?><?php if ($gpNickname): ?><span class="gp-profile-nick">@<?php echo htmlspecialchars($gpNickname); ?></span><?php endif; ?></span>
            <span class="gp-profile-tags">
                <span class="gp-role-badge <?php echo $userRole === 'agent' ? 'gp-role-agent' : 'gp-role-gamer'; ?>">
                    <i class="fas <?php echo $userRole === 'agent' ? 'fa-crown' : 'fa-gamepad'; ?>"></i>
                    <?php echo $userRole === 'agent' ? 'Agent' : 'Gamer'; ?>
                </span>
            </span>
        </div>
        <div class="gp-profile-balance">
            <span class="gp-balance-icon"><i class="fas fa-coins"></i></span>
            <span class="gp-balance-amount">৳<?php echo number_format($userBalance, 0); ?></span>
            <a href="index.php?page=balance" class="gp-balance-add" title="Add funds"><i class="fas fa-plus"></i></a>
        </div>
        <div class="gp-profile-actions">
            <?php if ($userRole === 'agent'): ?>
                <button type="button" class="gp-btn gp-btn-sm gp-btn-accent" data-open-modal="createTournamentModal"><i class="fas fa-trophy"></i></button>
            <?php elseif ($userRole === 'user'): ?>
                <button type="button" class="gp-btn gp-btn-sm gp-btn-gradient" data-open-modal="becomeAgentModal" title="Become an agent"><i class="fas fa-crown"></i></button>
            <?php endif; ?>
            <button type="button" class="gp-btn gp-btn-sm gp-btn-ghost" data-open-modal="createTeamModal" title="Create team"><i class="fas fa-users"></i></button>
        </div>
    </section>
    <?php endif; ?>

    <!-- ═══ SECTION SWITCHER ═══ -->
    <div class="gp-section-switcher">
        <a href="#browse" class="gp-switcher-btn active" data-scroll-to="browse">
            <i class="fas fa-trophy"></i> Tournaments
        </a>
        <?php if ($viewerId): ?>
        <a href="#my-stuff" class="gp-switcher-btn" data-scroll-to="my-stuff">
            <i class="fas fa-layer-group"></i> My stuff
        </a>
        <a href="index.php?page=clubs" class="gp-switcher-btn" data-no-ajax>
            <i class="fas fa-flag"></i> Clubs
        </a>
        <a href="index.php?page=player-market" class="gp-switcher-btn" data-no-ajax>
            <i class="fas fa-gavel"></i> Market
        </a>
        <?php endif; ?>
    </div>

    <!-- ═══ TOURNAMENT LIST ═══ -->
    <section id="browse" class="gp-section">
        <div class="gp-section-header">
            <h2><i class="fas fa-trophy"></i> Tournaments</h2>
            <div class="gp-filter-select">
                <div class="gp-custom-select" id="gpFilterSelect">
                    <div class="gp-select-trigger" onclick="toggleFilterSelect()">
                        <span>All Tournaments</span>
                    </div>
                    <div class="gp-select-options">
                        <div class="gp-select-option active" data-value="all" onclick="selectFilterOption(this)">All Tournaments</div>
                        <div class="gp-select-option" data-value="live" onclick="selectFilterOption(this)">Live</div>
                        <div class="gp-select-option" data-value="upcoming" onclick="selectFilterOption(this)">Upcoming</div>
                        <div class="gp-select-option" data-value="ongoing" onclick="selectFilterOption(this)">Ongoing</div>
                        <div class="gp-select-option" data-value="completed" onclick="selectFilterOption(this)">Completed</div>
                        <div class="gp-select-option" data-value="cancelled" onclick="selectFilterOption(this)">Cancelled</div>
                    </div>
                </div>
            </div>
            <div class="gp-search">
                <i class="fas fa-search gp-search-icon"></i>
                <input type="text" id="gpSearch" placeholder="Search tournaments..." class="gp-search-input">
                <button type="button" class="gp-search-clear" id="gpSearchClear" aria-label="Clear search"><i class="fas fa-times"></i></button>
            </div>
        </div>

        <div class="gp-grid" id="tournamentGrid">
            <?php if (empty($tournaments)): ?>
            <div class="gp-empty">
                <i class="fas fa-trophy"></i>
                <h3>No tournaments yet</h3>
                <p>Tournaments will appear here once created.</p>
            </div>
            <?php else: ?>
                <?php foreach ($tournaments as $t):
                    $tid = (int)($t['id'] ?? 0);
                    $title = htmlspecialchars($t['title'] ?? 'Tournament');
                    $desc = htmlspecialchars($t['description'] ?? '');
                    $status = $t['status'] ?? 'upcoming';
                    $prize = htmlspecialchars($t['prize_money'] ?? '');
                    $cat = htmlspecialchars($t['category'] ?? '');
                    $maxTeams = (int)($t['max_teams'] ?? 0);
                    $regd = (int)($t['registered_teams'] ?? 0);
                    $icon = htmlspecialchars($t['game_icon'] ?? 'fa-gamepad');
                    $accent = htmlspecialchars($t['accent_color'] ?? '#7c3aed');
                    $startsAt = $t['starts_at'] ?? null;
                    $startDate = $startsAt ? date('M j, Y', strtotime($startsAt)) : 'TBD';
                    $startTime = $startsAt ? date('g:i A', strtotime($startsAt)) : '';
                    $entryFee = (float)($t['entry_fee'] ?? 0);
                    $agentId = (int)($t['agent_id'] ?? 0);

                    // Get agent name
                    $agentName = '';
                    if ($agentId > 0) {
                        try { $aStmt = $db->prepare("SELECT full_name, username FROM users WHERE id = ?"); $aStmt->execute([$agentId]); $a = $aStmt->fetch(); $agentName = htmlspecialchars($a['full_name'] ?? $a['username'] ?? ''); } catch (Throwable $e) {}
                    }

                    $badgeClass = $statusBadgeClasses[$status] ?? '';
                    $isRegistered = false; $regTeamId = 0;
                    foreach ($myRegistrations as $reg) {
                        if ((int)$reg['tournament_id'] === $tid && $reg['status'] === 'confirmed') {
                            $isRegistered = true; $regTeamId = (int)($reg['team_id'] ?? 0); break;
                        }
                    }
                    $canRegister = in_array($status, ['upcoming', 'live']) && $viewerId;
                    $isFull = $maxTeams > 0 && $regd >= $maxTeams;
                    $isAgentOwner = $agentId > 0 && $agentId === (int)$viewerId;
                    $canOpenRoom = $viewerId ? userCanAccessTournamentRoom($db, $tid, (int)$viewerId) : false;
                ?>
                <article class="gp-card" data-id="<?php echo $tid; ?>" data-status="<?php echo $status; ?>">
                    <div class="gp-card-accent" style="background:<?php echo $accent; ?>"></div>
                    <div class="gp-card-head">
                        <span class="gp-card-icon" style="background:<?php echo $accent; ?>;color:#fff"><i class="fas <?php echo $icon; ?>"></i></span>
                        <span class="gp-badge <?php echo $badgeClass; ?>"><?php echo strtoupper($status); ?></span>
                    </div>
                    <div class="gp-card-body">
                        <h3><?php echo $title; ?></h3>
                        <?php if ($cat): ?><span class="gp-card-tag"><i class="fas fa-tag"></i> <?php echo $cat; ?></span><?php endif; ?>
                        <?php if ($desc): ?><p><?php echo $desc; ?></p><?php endif; ?>
                        <?php if ($agentName): ?><span class="gp-card-host"><i class="fas fa-crown" style="color:#f59e0b"></i> Host: <?php echo $agentName; ?></span><?php endif; ?>
                    </div>
                    <div class="gp-card-meta">
                        <div><i class="fas fa-calendar"></i> <span class="gp-date"><?php echo $startDate; ?></span> <?php if ($startTime): ?><span class="gp-time"><?php echo $startTime; ?></span><?php endif; ?>
                            <?php if ($startsAt && !in_array($status, ['completed','cancelled'])): ?>
                            <span class="gp-countdown" data-starts="<?php echo strtotime($startsAt); ?>"></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($prize): ?><div><i class="fas fa-trophy" style="color:#f59e0b"></i> ৳<?php echo $prize; ?></div><?php endif; ?>
                        <div><i class="fas fa-users"></i> <?php echo $regd; ?><?php echo $maxTeams > 0 ? "/$maxTeams" : ''; ?> teams</div>
                        <?php if ($entryFee > 0): ?><div><i class="fas fa-coins" style="color:#10b981"></i> ৳<?php echo number_format($entryFee, 0); ?> entry</div><?php endif; ?>
                        <?php if ($status === 'cancelled'): ?><div style="color:#dc2626"><i class="fas fa-ban"></i> Cancelled</div><?php endif; ?>
                        <?php
                        $bracketType = $t['bracket_type'] ?? 'single_elimination';
                        $bracketIcons = ['single_elimination'=>'fa-diagram-project','double_elimination'=>'fa-arrow-right-arrow-left','round_robin'=>'fa-arrows-rotate','swiss'=>'fa-sitemap'];
                        $bracketLabels = ['single_elimination'=>'Single Elim','double_elimination'=>'Double Elim','round_robin'=>'Round Robin','swiss'=>'Swiss'];
                        $restrictedClubId = $t['restricted_club_id'] ?? null;
                        $restrictedClubName = '';
                        $restrictedClubColour = '';
                        if ($restrictedClubId) {
                            $rcStmt = $db->prepare("SELECT name, colour FROM clubs WHERE id = ?");
                            $rcStmt->execute([$restrictedClubId]);
                            $rc = $rcStmt->fetch();
                            if ($rc) { $restrictedClubName = $rc['name']; $restrictedClubColour = $rc['colour']; }
                        }
                        ?>
                        <div><i class="fas <?php echo $bracketIcons[$bracketType] ?? 'fa-diagram-project'; ?>" style="color:#7c3aed"></i> <?php echo $bracketLabels[$bracketType] ?? 'Single Elim'; ?></div>
                        <?php if ($maxTeams > 0): ?>
                        <div style="grid-column:1/-1;margin-top:2px">
                            <div style="display:flex;align-items:center;gap:8px;font-size:.7rem;color:var(--gp-muted)">
                                <span style="flex:1;height:4px;border-radius:2px;background:var(--gp-border);overflow:hidden">
                                    <span style="display:block;height:100%;width:<?php echo min(100, round($regd/$maxTeams*100)); ?>%;border-radius:2px;background:linear-gradient(90deg,#7c3aed,#a78bfa);transition:width .3s"></span>
                                </span>
                                <span style="font-weight:700"><?php echo $regd; ?>/<?php echo $maxTeams; ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if ($restrictedClubName): ?>
                        <div style="grid-column:1/-1;margin-top:2px;display:flex;align-items:center;gap:6px"><span style="display:inline-flex;align-items:center;gap:4px;font-size:.65rem;font-weight:700;padding:3px 8px;border-radius:999px;background:rgba(124,58,237,.1);color:<?php echo $restrictedClubColour ?: '#7c3aed'; ?>"><i class="fas fa-lock"></i> <?php echo htmlspecialchars($restrictedClubName); ?> only</span></div>
                        <?php endif; ?>
                    </div>
                    <div class="gp-card-actions">
                        <?php if ($status === 'cancelled'): ?>
                            <span class="gp-btn gp-btn-sm gp-btn-disabled"><i class="fas fa-ban"></i> Cancelled</span>
                        <?php elseif ($isRegistered): ?>
                            <button class="gp-btn gp-btn-sm gp-btn-success" disabled><i class="fas fa-check-circle"></i> Registered</button>
                            <button class="gp-btn gp-btn-sm gp-btn-ghost gp-unregister" data-id="<?php echo $tid; ?>"><i class="fas fa-xmark"></i></button>
                        <?php elseif ($canRegister && !$isFull): ?>
                            <button class="gp-btn gp-btn-sm gp-btn-primary gp-join-btn" data-id="<?php echo $tid; ?>" data-title="<?php echo htmlspecialchars($title, ENT_QUOTES); ?>" data-fee="<?php echo $entryFee; ?>"><i class="fas fa-right-to-bracket"></i> <?php echo $entryFee > 0 ? 'Join (৳'.number_format($entryFee,0).')' : 'Join free'; ?></button>
                        <?php elseif ($isFull): ?>
                            <span class="gp-btn gp-btn-sm gp-btn-disabled"><i class="fas fa-lock"></i> Full</span>
                        <?php else: ?>
                            <span class="gp-btn gp-btn-sm gp-btn-disabled">Closed</span>
                        <?php endif; ?>
                        <button class="gp-btn gp-btn-sm gp-btn-ghost gp-view-btn" data-id="<?php echo $tid; ?>"><i class="fas fa-eye"></i> View</button>
                        <?php if ($canOpenRoom): ?>
                            <a class="gp-btn gp-btn-sm gp-btn-outline" href="index.php?page=tournament-room&id=<?php echo $tid; ?>" data-no-ajax><i class="fas fa-door-open"></i> Room</a>
                        <?php endif; ?>
                        <?php if ($isAgentOwner): ?>
                        <span class="gp-agent-badge"><i class="fas fa-crown"></i> Yours</span>
                        <?php endif; ?>
                    </div>
                </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <!-- ═══ MY STUFF ═══ -->
    <?php if ($viewerId): ?>
    <section id="my-stuff" class="gp-section">
        <div class="gp-section-header">
            <h2><i class="fas fa-layer-group"></i> My stuff</h2>
        </div>
        <div class="gp-my-grid">
            <div class="gp-my-panel">
                <div class="gp-my-panel-header"><i class="fas fa-trophy"></i> My registrations <span class="gp-count"><?php echo count($myRegistrations); ?></span></div>
                <div class="gp-my-list">
                    <?php if (empty($myRegistrations)): ?>
                    <div class="gp-my-empty">No registrations yet. Join a tournament above!</div>
                    <?php else: foreach ($myRegistrations as $reg):
                        $rTitle = htmlspecialchars($reg['title'] ?? '');
                        $rStatus = $reg['tournament_status'] ?? '';
                        $rBadge = $statusBadgeClasses[$rStatus] ?? '';
                    ?>
                    <div class="gp-my-item">
                        <span class="gp-my-icon" style="background:<?php echo htmlspecialchars($reg['accent_color'] ?? '#7c3aed'); ?>"><i class="fas <?php echo htmlspecialchars($reg['game_icon'] ?? 'fa-gamepad'); ?>"></i></span>
                        <div class="gp-my-info">
                            <strong><?php echo $rTitle; ?></strong>
                            <span><span class="gp-badge sm <?php echo $rBadge; ?>"><?php echo strtoupper($rStatus); ?></span></span>
                        </div>
                        <div style="display:flex;gap:8px;align-items:center">
                            <a class="gp-btn gp-btn-xs gp-btn-outline" href="index.php?page=tournament-room&id=<?php echo (int)$reg['tournament_id']; ?>" data-no-ajax><i class="fas fa-door-open"></i> Room</a>
                            <button class="gp-btn gp-btn-xs gp-btn-ghost gp-unregister" data-id="<?php echo (int)$reg['tournament_id']; ?>"><i class="fas fa-xmark"></i> Leave</button>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

            <div class="gp-my-panel">
                <div class="gp-my-panel-header"><i class="fas fa-users"></i> My teams <span class="gp-count"><?php echo count($myTeams); ?></span></div>
                <div class="gp-my-list">
                    <?php if (empty($myTeams)): ?>
                    <div class="gp-my-empty">No teams yet. <button class="gp-btn gp-btn-xs gp-btn-primary" data-open-modal="createTeamModal">Create one</button></div>
                    <?php else: foreach ($myTeams as $team):
                        $tId = (int)$team['id'];
                        $tName = htmlspecialchars($team['name'] ?? '');
                        $tGame = htmlspecialchars($team['game'] ?? '');
                        $tRole = $team['my_role'] ?? 'member';
                        $memberStmt = $db->prepare("SELECT COUNT(*) FROM team_members WHERE team_id = ?");
                        $memberStmt->execute([$tId]);
                        $memberCount = (int)$memberStmt->fetchColumn();
                    ?>
                    <div class="gp-my-item team-item" data-team-id="<?php echo $tId; ?>">
                        <span class="gp-my-icon team-icon" style="background:linear-gradient(135deg,#7c3aed,#2563eb)"><i class="fas fa-users"></i></span>
                        <div class="gp-my-info">
                            <strong><?php echo $tName; ?></strong>
                            <span><?php echo $memberCount; ?> members <?php if ($tGame): ?>&middot; <?php echo $tGame; ?><?php endif; ?> &middot; <?php echo ucfirst($tRole); ?></span>
                        </div>
                        <button class="gp-btn gp-btn-xs gp-btn-outline gp-manage-team" data-team-id="<?php echo $tId; ?>" data-team-name="<?php echo htmlspecialchars($tName, ENT_QUOTES); ?>"><i class="fas fa-users-gear"></i> Manage</button>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

            <?php if ($userRole === 'agent'): ?>
            <div class="gp-my-panel gp-agent-panel">
                <div class="gp-my-panel-header"><i class="fas fa-crown"></i> Agent dashboard <span class="gp-count">৳<?php echo number_format($userBalance, 0); ?></span></div>
                <div class="gp-agent-quick">
                    <button class="gp-btn gp-btn-sm gp-btn-accent" data-open-modal="createTournamentModal"><i class="fas fa-trophy"></i> Add tournament</button>
                    <button class="gp-btn gp-btn-sm gp-btn-outline" data-open-modal="agentHistoryModal"><i class="fas fa-clock-rotate-left"></i> History</button>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- ═══ LEADERBOARD ═══ -->
    <section id="leaderboard" class="gp-section" style="margin-top:32px">
        <div class="gp-section-header">
            <h2><i class="fas fa-ranking-star"></i> Leaderboard</h2>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <select id="lbFilter" class="gp-input" style="width:auto">
                    <option value="">Player Rankings</option>
                    <option value="club">Club Rankings</option>
                </select>
                <button class="gp-btn gp-btn-sm gp-btn-ghost" onclick="loadLeaderboard()"><i class="fas fa-rotate"></i> Refresh</button>
            </div>
        </div>
        <div style="background:var(--gp-card);border-radius:20px;border:1px solid var(--gp-border);overflow:hidden">
            <div style="display:grid;grid-template-columns:36px 1fr 80px 90px 60px;gap:8px;padding:12px 16px;font-size:.7rem;font-weight:700;color:var(--gp-muted);text-transform:uppercase;background:var(--gp-bg);border-bottom:1px solid var(--gp-border)" id="lbHeader">
                <span>#</span><span>Player</span><span>Points</span><span>Prize</span><span>Rank</span>
            </div>
            <div id="lbBody">
                <div style="text-align:center;padding:40px 20px;color:var(--gp-muted)"><i class="fas fa-spinner fa-spin" style="font-size:1.5rem;opacity:.4"></i><p style="font-size:.9rem;margin:8px 0 0">Loading leaderboard...</p></div>
            </div>
        </div>
    </section>
</div>



<script>
function loadLeaderboard() {
    var body = document.getElementById('lbBody');
    body.innerHTML = '<div style="text-align:center;padding:30px;color:var(--gp-muted)"><i class="fas fa-spinner fa-spin" style="font-size:1.5rem;opacity:.4"></i></div>';
    var type = document.getElementById('lbFilter').value;
    var data = type === 'club' 
        ? new URLSearchParams({ action:'get_club_standings' })
        : new URLSearchParams({ action:'get_leaderboard', limit:50 });
    fetch('handlers/tournament_handler.php', { method:'POST', body:data })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (type === 'club') {
            if (!res.success || !res.standings || !res.standings.length) {
                body.innerHTML = '<div style="text-align:center;padding:40px 20px;color:var(--gp-muted)"><i class="fas fa-flag" style="font-size:2.5rem;opacity:.4;margin-bottom:12px;display:block"></i><p style="font-size:.9rem;margin:0">No club rankings yet.</p></div>';
                document.getElementById('lbHeader').innerHTML = '<span>#</span><span>Club</span><span>Points</span><span>Trophies</span><span>Members</span>';
                return;
            }
            document.getElementById('lbHeader').innerHTML = '<span>#</span><span>Club</span><span>Points</span><span>Trophies</span><span>Members</span>';
            var html = '';
            res.standings.forEach(function(c, i) {
                var rankClass = i===0 ? 'gold' : (i===1 ? 'silver' : (i===2 ? 'bronze' : ''));
                html += '<a href="index.php?page=clubs&club_id=' + c.id + '" class="gp-lb-row">';
                html += '<div class="gp-lb-rank ' + rankClass + '">' + (i+1) + '</div>';
                html += '<div style="display:flex;align-items:center;gap:8px;font-weight:700;color:var(--gp-text)"><span style="width:10px;height:10px;border-radius:50%;background:' + (c.colour || '#7c3aed') + ';flex-shrink:0"></span>' + c.name + '</div>';
                html += '<div class="gp-lb-cell">' + (c.total_club_points || c.total_points || 0) + '</div>';
                html += '<div class="gp-lb-cell">' + (c.trophies || 0) + '</div>';
                html += '<div class="gp-lb-cell">' + (c.member_count || 0) + '</div></a>';
            });
            body.innerHTML = html;
        } else {
            if (!res.success || !res.leaderboard || !res.leaderboard.length) {
                body.innerHTML = '<div style="text-align:center;padding:40px 20px;color:var(--gp-muted)"><i class="fas fa-trophy" style="font-size:2.5rem;opacity:.4;margin-bottom:12px;display:block"></i><p style="font-size:.9rem;margin:0">No rankings yet. Complete a tournament to appear.</p></div>';
                return;
            }
            document.getElementById('lbHeader').innerHTML = '<span>#</span><span>Player</span><span>Points</span><span>Prize</span><span>Rank</span>';
            var html = '';
            res.leaderboard.forEach(function(p, i) {
                var rankClass = i===0 ? 'gold' : (i===1 ? 'silver' : (i===2 ? 'bronze' : ''));
                html += '<div class="gp-lb-row" onclick="window.location.href=\'index.php?page=player-market&user_id=' + p.user_id + '\'">';
                html += '<div class="gp-lb-rank ' + rankClass + '">' + (i+1) + '</div>';
                html += '<div style="display:flex;align-items:center;gap:8px;font-weight:600;color:var(--gp-text)">';
                if (p.avatar) html += '<img src="assets/images/avatars/' + p.avatar + '" style="width:28px;height:28px;border-radius:50%;object-fit:cover" onerror="this.style.display=\'none\'">';
                html += (p.nickname || p.full_name || p.username) + '</div>';
                html += '<div class="gp-lb-cell">' + (p.total_points || 0) + '</div>';
                html += '<div class="gp-lb-prize">৳' + (parseFloat(p.total_prize) || 0).toLocaleString() + '</div>';
                html += '<div class="gp-lb-cell">' + (p.best_rank || '--') + '</div></div>';
            });
            body.innerHTML = html;
        }
    }).catch(function() {
        body.innerHTML = '<div style="text-align:center;padding:40px;color:var(--gp-muted)"><i class="fas fa-exclamation-triangle" style="font-size:2rem;opacity:.4;margin-bottom:8px;display:block"></i><p style="font-size:.9rem;margin:0">Failed to load leaderboard.</p></div>';
    });
}

// Filter change
document.addEventListener('DOMContentLoaded', function() {
    var filter = document.getElementById('lbFilter');
    if (filter) {
        filter.addEventListener('change', loadLeaderboard);
        loadLeaderboard();
    }
});
</script>

<!-- ════════════════════════════════════════════════════════════ -->
<!-- MODALS -->
<!-- ════════════════════════════════════════════════════════════ -->

<div class="gp-overlay hidden" id="gpOverlay"></div>

<!-- Become Agent Modal (Info + Payment) — Premium Bangla Design -->
<div class="gp-modal hidden" id="becomeAgentModal">
    <div class="gp-modal-panel gp-modal-panel--crown">
        <div class="gp-modal-head">
            <h3><i class="fas fa-crown" style="color:#f59e0b"></i> এজেন্ট হোন</h3>
            <button class="gp-modal-close" data-close-modal><i class="fas fa-times"></i></button>
        </div>
        <div class="gp-modal-body">

            <!-- ═══ Step 1: Benefits Info (Bangla) ═══ -->
            <div class="gp-modal-step active" id="baInfoStep">
                <div class="gp-agent-hero gp-agent-hero--bn">
                    <div class="gp-agent-hero-glow"></div>
                    <span class="gp-agent-hero-icon">👑</span>
                    <div class="gp-agent-hero-title">এজেন্ট পাওয়ার্স আনলক করুন</div>
                    <div class="gp-agent-hero-sub">টুর্নামেন্ট তৈরি করুন, আয় করুন, নিজের ব্র্যান্ড গড়ুন</div>
                </div>

                <div class="gp-agent-badge-grid">
                    <div class="gp-agent-badge gp-agent-badge--premium">
                        <div class="gp-agent-badge-glow"></div>
                        <span class="gp-agent-badge-icon">🏆</span>
                        <div class="gp-agent-badge-label">টুর্নামেন্ট তৈরি</div>
                        <div class="gp-agent-badge-desc">নিজের ইভেন্ট হোস্ট করুন</div>
                    </div>
                    <div class="gp-agent-badge gp-agent-badge--premium">
                        <div class="gp-agent-badge-glow"></div>
                        <span class="gp-agent-badge-icon">💰</span>
                        <div class="gp-agent-badge-label">আয় উপার্জন</div>
                        <div class="gp-agent-badge-desc">এন্ট্রি ফি রাখুন নিজের</div>
                    </div>
                    <div class="gp-agent-badge gp-agent-badge--premium">
                        <div class="gp-agent-badge-glow"></div>
                        <span class="gp-agent-badge-icon">👑</span>
                        <div class="gp-agent-badge-label">এজেন্ট ব্যাজ</div>
                        <div class="gp-agent-badge-desc">স্পেশাল রিকগনিশন</div>
                    </div>
                    <div class="gp-agent-badge gp-agent-badge--premium">
                        <div class="gp-agent-badge-glow"></div>
                        <span class="gp-agent-badge-icon">🎯</span>
                        <div class="gp-agent-badge-label">প্রাইজ কন্ট্রোল</div>
                        <div class="gp-agent-badge-desc">নিজের প্রাইজ সেট করুন</div>
                    </div>
                </div>

                <div class="gp-agent-fee gp-agent-fee--bn">
                    <div class="gp-agent-fee-glow"></div>
                    <span class="gp-agent-fee-label">এককালীন অ্যাক্টিভেশন ফি</span>
                    <span class="gp-agent-fee-amount">মাত্র ৳৫০০</span>
                </div>

                <div class="gp-modal-actions">
                    <button type="button" class="gp-btn gp-btn-ghost" data-close-modal>পরে করব</button>
                    <button type="button" class="gp-btn gp-btn-gradient gp-btn-gradient--gold" id="baProceedBtn"><i class="fas fa-arrow-right"></i> পেমেন্টে যান</button>
                </div>
            </div>

            <!-- ═══ Step 2: Payment (Bangla instructions + copy) ═══ -->
            <div class="gp-modal-step" id="baPayStep">
                <div class="ba-pay-wrap">

                    <!-- Premium info note -->
                    <div class="gp-pay-note gp-pay-note--agent">
                        <i class="fas fa-crown"></i> নিচের যেকোনো একটি মেথডে <strong>৫০০ টাকা</strong> পাঠিয়ে এজেন্ট অ্যাক্টিভেট করুন
                    </div>

                    <!-- Method Cards (images only) -->
                    <div class="ba-method-grid">
                        <?php $methods = ['bkash'=>['label'=>'bKash','color'=>'#E2136E'],'nagad'=>['label'=>'Nagad','color'=>'#E8522E'],'rocket'=>['label'=>'Rocket','color'=>'#CC0000']]; ?>
                        <?php foreach ($methods as $mk => $mv): ?>
                        <div class="ba-method-card ba-method-card--premium active" data-method="<?php echo $mk; ?>" style="border-color:<?php echo $mk === 'bkash' ? $mv['color'] : '#d1d5db'; ?>;background:<?php echo $mk === 'bkash' ? '#fdf2f8' : ''; ?>">
                            <img src="assets/images/payment-icon/<?php echo $mk; ?>-logo-mobile-banking.png" alt="<?php echo $mv['label']; ?>" onerror="this.style.display='none'">
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Merchant Info + Instructions (JS-rendered, like balance.php) -->
                    <div id="baMerchantBox" class="ba-merchant-box--premium" style="display:none"></div>

                    <!-- Phone + TXID -->
                    <div class="ba-inputs-grid">
                        <div>
                            <label class="ba-input-label">📱 আপনার মোবাইল নাম্বার (Sent From)</label>
                            <div class="ba-input-wrap">
                                <span class="ba-input-icon"><i class="fas fa-mobile-screen"></i></span>
                                <input type="tel" id="baPhone" class="ba-input ba-input--premium" placeholder="01XXXXXXXXX" pattern="01[3-9]\d{8}">
                            </div>
                        </div>
                        <div>
                            <label class="ba-input-label">🔗 ট্রান্সজেকশন আইডি</label>
                            <div class="ba-input-wrap">
                                <span class="ba-input-icon"><i class="fas fa-hashtag"></i></span>
                                <input type="text" id="baTxid" class="ba-input ba-input--premium" placeholder="ট্রান্সজেকশন আইডি দিন" style="text-transform:uppercase">
                            </div>
                        </div>
                    </div>

                    <div class="gp-modal-actions ba-actions">
                        <button type="button" class="gp-btn gp-btn-ghost" id="baBackToInfo"><i class="fas fa-arrow-left"></i> পেছনে</button>
                        <button type="button" class="gp-btn gp-btn-gradient gp-btn-gradient--gold" id="baPayBtn"><i class="fas fa-paper-plane"></i> সাবমিট করুন</button>
                    </div>
                    <div class="gp-feedback hidden" id="baFeedback"></div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- ═══ View Gamer Profile Modal (exists) ═══ -->
<div class="gp-modal hidden" id="viewGamerProfileModal">
    <div class="gp-modal-panel gp-modal-full-mobile">
        <div class="gp-modal-head">
            <h3><i class="fas fa-id-card" style="color:#7c3aed"></i> Gamer Profile</h3>
            <button class="gp-modal-close" data-close-modal><i class="fas fa-times"></i></button>
        </div>
        <div class="gp-modal-body">
            <div class="gp-profile-card">
                <div class="gp-profile-card-cover">
                    <img src="assets/avatars/<?php echo htmlspecialchars($_SESSION['avatar'] ?? 'default.png'); ?>" alt="" onerror="this.src='assets/avatars/default.png'">
                </div>
                <div class="gp-profile-card-body">
                    <div class="gp-profile-card-avatar">
                        <img src="assets/avatars/<?php echo htmlspecialchars($_SESSION['avatar'] ?? 'default.png'); ?>" alt="" onerror="this.src='assets/avatars/default.png'">
                    </div>
                    <h4><?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User'); ?></h4>
                    <?php if (!empty($_SESSION['nickname'])): ?>
                    <span class="gp-profile-card-tag">@<?php echo htmlspecialchars($_SESSION['nickname']); ?></span>
                    <?php endif; ?>
                    <span class="gp-role-badge <?php echo $userRole === 'agent' ? 'gp-role-agent' : 'gp-role-gamer'; ?>" style="margin-top:6px">
                        <i class="fas <?php echo $userRole === 'agent' ? 'fa-crown' : 'fa-gamepad'; ?>"></i>
                        <?php echo $userRole === 'agent' ? 'Agent' : 'Gamer'; ?>
                    </span>
                </div>
            </div>

            <!-- ═══ STATS OVERVIEW ═══ -->
            <div class="gp-profile-stats-title"><i class="fas fa-chart-simple"></i> Career Stats</div>
            <div class="gp-profile-stats">
                <div class="gp-profile-stat">
                    <span class="gp-profile-stat-icon"><i class="fas fa-gamepad"></i></span>
                    <span class="gp-profile-stat-label">Matches</span>
                    <span class="gp-profile-stat-value"><?php echo (int)($playerStats['total_matches'] ?? 0); ?></span>
                </div>
                <div class="gp-profile-stat">
                    <span class="gp-profile-stat-icon"><i class="fas fa-trophy"></i></span>
                    <span class="gp-profile-stat-label">Wins</span>
                    <span class="gp-profile-stat-value"><?php echo (int)($playerStats['total_wins'] ?? 0); ?></span>
                </div>
                <div class="gp-profile-stat">
                    <span class="gp-profile-stat-icon"><i class="fas fa-crosshairs"></i></span>
                    <span class="gp-profile-stat-label">Kills</span>
                    <span class="gp-profile-stat-value"><?php echo (int)($playerStats['total_kills'] ?? 0); ?></span>
                </div>
                <div class="gp-profile-stat">
                    <span class="gp-profile-stat-icon"><i class="fas fa-star"></i></span>
                    <span class="gp-profile-stat-label">Score</span>
                    <span class="gp-profile-stat-value"><?php echo number_format((float)($playerStats['total_score'] ?? 0), 0); ?></span>
                </div>
            </div>

            <!-- ═══ GAME SKILLS ═══ -->
            <div class="gp-profile-stats-title"><i class="fas fa-signal"></i> Game Skills</div>
            <div class="gp-profile-game-skills" id="viewGameSkills">
                <?php if (empty($gameSkills)): ?>
                <div class="gp-profile-no-skills">No skills added yet. <a href="#" onclick="editGamerProfile();return false">Add your game skills</a></div>
                <?php else: foreach ($gameSkills as $gs): ?>
                <div class="gp-profile-game-skill">
                    <span class="gp-game-skill-icon"><i class="fas <?php echo htmlspecialchars($gs['game_icon'] ?? 'fa-gamepad'); ?>"></i></span>
                    <span class="gp-game-skill-name"><?php echo htmlspecialchars($gs['game']); ?></span>
                    <span class="gp-game-skill-level"><?php echo htmlspecialchars($gs['skill_level'] ?: '--'); ?></span>
                    <span class="gp-game-skill-bar"><span style="width:<?php echo $skillPct = max(10, min(100, ['Beginner'=>20,'Intermediate'=>40,'Advanced'=>60,'Pro'=>80,'Elite'=>100][$gs['skill_level']] ?? 30)); ?>%"></span></span>
                </div>
                <?php endforeach; endif; ?>
            </div>

            <?php if (!empty($_SESSION['bio'])): ?>
            <div class="gp-profile-bio">
                <label><i class="fas fa-quote-left"></i> Bio</label>
                <p><?php echo htmlspecialchars($_SESSION['bio']); ?></p>
            </div>
            <?php endif; ?>

            <div class="gp-profile-info-links">
                <?php if (!empty($_SESSION['discord'])): ?>
                <span><i class="fab fa-discord"></i> <?php echo htmlspecialchars($_SESSION['discord']); ?></span>
                <?php endif; ?>
                <span><i class="fas fa-coins"></i> ৳<?php echo number_format($userBalance, 0); ?></span>
            </div>

            <div class="gp-modal-actions">
                <button type="button" class="gp-btn gp-btn-ghost" data-close-modal>Close</button>
                <button type="button" class="gp-btn gp-btn-primary" onclick="editGamerProfile()"><i class="fas fa-pen"></i> Edit profile</button>
            </div>
        </div>
    </div>
</div>

<!-- ═══ Create/Edit Gamer Profile Modal ═══ -->
<div class="gp-modal hidden" id="createGamerProfileModal">
    <div class="gp-modal-panel gp-modal-full-mobile">
        <div class="gp-modal-head">
            <h3><i class="fas fa-id-card" style="color:#7c3aed"></i> <?php echo !empty($_SESSION['nickname']) ? 'Edit' : 'Create'; ?> Gamer Profile</h3>
            <button class="gp-modal-close" data-close-modal><i class="fas fa-times"></i></button>
        </div>
        <div class="gp-modal-body">
            <div class="gp-modal-step-indicator">
                <span class="gp-step-dot active"></span>
                <span class="gp-step-line"></span>
                <span class="gp-step-dot"></span>
            </div>

            <form id="gamerProfileForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="action" value="update_profile">

                <div class="gp-form-group">
                    <label><i class="fas fa-tag"></i> Gaming nickname</label>
                    <input type="text" name="nickname" class="gp-input" placeholder="e.g. ShadowStrike" maxlength="50" value="<?php echo htmlspecialchars($_SESSION['nickname'] ?? ''); ?>" required>
                    <span class="gp-form-hint">This will be displayed to other players</span>
                </div>
                <div class="gp-form-grid two">
                    <div class="gp-form-group">
                        <label><i class="fas fa-signal"></i> Skill level</label>
                        <select name="skill_level" class="gp-input">
                            <?php $levels = ['','Beginner','Intermediate','Advanced','Pro','Elite']; $current = $_SESSION['skill_level'] ?? ''; ?>
                            <?php foreach ($levels as $lv): ?>
                            <option value="<?php echo $lv; ?>" <?php echo $current === $lv ? 'selected' : ''; ?>><?php echo $lv ?: 'Select'; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="gp-form-group">
                        <label><i class="fas fa-gamepad"></i> Favorite game</label>
                        <select name="favorite_game" class="gp-input">
                            <option value="">Select</option>
                            <?php foreach ($categories as $gc): ?>
                            <option value="<?php echo $gc; ?>" <?php echo ($_SESSION['favorite_game'] ?? '') === $gc ? 'selected' : ''; ?>><?php echo $gc; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="gp-form-group">
                    <label><i class="fas fa-comment"></i> Bio</label>
                    <textarea name="bio" class="gp-input" rows="2" placeholder="Tell other players about yourself..." maxlength="200"><?php echo htmlspecialchars($_SESSION['bio'] ?? ''); ?></textarea>
                </div>
                <div class="gp-form-group">
                    <label><i class="fab fa-discord"></i> Discord (optional)</label>
                    <div class="gp-input-group">
                        <span class="gp-input-prefix"><i class="fab fa-discord"></i></span>
                        <input type="text" name="discord" class="gp-input" placeholder="your#0000" value="<?php echo htmlspecialchars($_SESSION['discord'] ?? ''); ?>">
                    </div>
                </div>

                <!-- ═══ PER-GAME SKILLS ═══ -->
                <div class="gp-profile-stats-title" style="margin-top:6px"><i class="fas fa-signal"></i> Per-Game Skills</div>
                <div id="gameSkillsContainer" class="gp-game-skills-editor">
                    <?php if (!empty($gameSkills)): foreach ($gameSkills as $gs): ?>
                    <div class="gp-game-skill-row">
                        <span class="gp-game-skill-icon-preview"><i class="fas <?php echo htmlspecialchars($gs['game_icon'] ?? 'fa-gamepad'); ?>"></i></span>
                        <span class="gp-game-skill-row-name"><?php echo htmlspecialchars($gs['game']); ?></span>
                        <select class="gp-input gp-game-skill-row-select" onchange="saveGameSkill(this, '<?php echo htmlspecialchars($gs['game']); ?>')">
                            <option value="">--</option>
                            <?php foreach (['Beginner','Intermediate','Advanced','Pro','Elite'] as $lv): ?>
                            <option value="<?php echo $lv; ?>" <?php echo $gs['skill_level'] === $lv ? 'selected' : ''; ?>><?php echo $lv; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endforeach; endif; ?>
                    <button type="button" class="gp-btn gp-btn-sm gp-btn-ghost gp-add-game-skill-btn" onclick="addGameSkillRow()"><i class="fas fa-plus"></i> Add game skill</button>
                </div>

                <?php if ($userRole !== 'agent'): ?>
                <div class="gp-profile-become-agent">
                    <span class="gp-profile-agent-icon"><i class="fas fa-crown"></i></span>
                    <div>
                        <strong>Want to host tournaments?</strong>
                        <p>Become an agent and create your own events.</p>
                    </div>
                    <button type="button" class="gp-btn gp-btn-sm gp-btn-gradient" data-open-modal="becomeAgentModal"><i class="fas fa-arrow-right"></i> Upgrade</button>
                </div>
                <?php endif; ?>

                <div class="gp-modal-actions">
                    <button type="button" class="gp-btn gp-btn-ghost" data-close-modal>Cancel</button>
                    <button type="submit" class="gp-btn gp-btn-accent"><i class="fas fa-check"></i> <?php echo !empty($_SESSION['nickname']) ? 'Save changes' : 'Create profile'; ?></button>
                </div>
                <div class="gp-feedback hidden"></div>
            </form>
        </div>
    </div>
</div>


<!-- Create Tournament Modal (Agent only) -->
<div class="gp-modal hidden" id="createTournamentModal">
    <div class="gp-modal-panel lg">
        <div class="gp-modal-head">
            <h3><i class="fas fa-trophy" style="color:#7c3aed"></i> Create tournament</h3>
            <button class="gp-modal-close" data-close-modal><i class="fas fa-times"></i></button>
        </div>
        <div class="gp-modal-body">
            <form id="createTournamentForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="action" value="agent_create_tournament">
                <div class="gp-form-grid">
                    <div class="gp-form-group">
                        <label>Title</label>
                        <input type="text" name="title" class="gp-input" required placeholder="e.g. Dream Cup 2026">
                    </div>
                    <div class="gp-form-group">
                        <label>Game / Category</label>
                        <select name="category" class="gp-input">
                            <option value="">Select game</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="gp-form-group">
                    <label>Description</label>
                    <textarea name="description" class="gp-input" rows="2" placeholder="Tournament details..."></textarea>
                </div>
                <div class="gp-form-grid three">
                    <div class="gp-form-group">
                        <label>Prize money (৳)</label>
                        <div class="gp-input-group"><span class="gp-input-prefix">৳</span>
                            <input type="text" name="prize_money" class="gp-input" placeholder="e.g. 500" required>
                        </div>
                        <span class="gp-form-hint">Will be deducted from your balance</span>
                    </div>
                    <div class="gp-form-group">
                        <label>Entry fee (৳)</label>
                        <div class="gp-input-group"><span class="gp-input-prefix">৳</span>
                            <input type="text" name="entry_fee" class="gp-input" placeholder="e.g. 2" value="0">
                        </div>
                        <span class="gp-form-hint">Per team</span>
                    </div>
                    <div class="gp-form-group">
                        <label>Max teams</label>
                        <input type="number" name="max_teams" class="gp-input" min="0" placeholder="e.g. 16" value="16">
                    </div>
                </div>
                <div class="gp-form-grid two">
                    <div class="gp-form-group">
                        <label>Start date</label>
                        <input type="datetime-local" name="starts_at" class="gp-input">
                    </div>
                    <div class="gp-form-group">
                        <label>Game icon</label>
                        <div class="gp-icon-picker">
                            <?php foreach ($gameIcons as $gi): ?>
                            <button type="button" class="gp-icon-opt <?php echo $gi === 'fa-gamepad' ? 'active' : ''; ?>" data-icon="<?php echo $gi; ?>"><i class="fas <?php echo $gi; ?>"></i></button>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="game_icon" value="fa-gamepad">
                    </div>
                </div>
                <div class="gp-form-group">
                    <label>Accent color</label>
                    <div class="gp-color-picker">
                        <?php foreach ($palette as $c): ?>
                        <button type="button" class="gp-color-opt <?php echo $c === '#7c3aed' ? 'active' : ''; ?>" data-color="<?php echo $c; ?>" style="background:<?php echo $c; ?>"></button>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="accent_color" value="#7c3aed">
                </div>
                <div class="gp-balance-notice" id="createTournamentBalance">
                    Balance: <strong>৳<?php echo number_format($userBalance, 0); ?></strong>
                </div>
                <div class="gp-modal-actions">
                    <button type="button" class="gp-btn gp-btn-ghost" data-close-modal>Cancel</button>
                    <button type="submit" class="gp-btn gp-btn-accent"><i class="fas fa-trophy"></i> Create tournament</button>
                </div>
                <div class="gp-feedback hidden"></div>
            </form>
        </div>
    </div>
</div>

<!-- Create Team Modal -->
<div class="gp-modal hidden" id="createTeamModal">
    <div class="gp-modal-panel sm">
        <div class="gp-modal-head">
            <h3><i class="fas fa-users" style="color:#2563eb"></i> Create team</h3>
            <button class="gp-modal-close" data-close-modal><i class="fas fa-times"></i></button>
        </div>
        <div class="gp-modal-body">
            <form id="createTeamForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="action" value="create_team">
                <div class="gp-form-group">
                    <label>Team name</label>
                    <input type="text" name="name" class="gp-input" required placeholder="e.g. Dream Crew" maxlength="100">
                </div>
                <div class="gp-form-group">
                    <label>Game</label>
                    <select name="game" class="gp-input">
                        <option value="">Select game</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="gp-form-group">
                    <label>Description</label>
                    <textarea name="description" class="gp-input" rows="2" placeholder="Team bio..."></textarea>
                </div>
                <div class="gp-modal-actions">
                    <button type="button" class="gp-btn gp-btn-ghost" data-close-modal>Cancel</button>
                    <button type="submit" class="gp-btn gp-btn-primary"><i class="fas fa-check"></i> Create team</button>
                </div>
                <div class="gp-feedback hidden"></div>
            </form>
        </div>
    </div>
</div>

<!-- Join Tournament Modal (choose team or solo) -->
<div class="gp-modal hidden" id="joinTournamentModal">
    <div class="gp-modal-panel sm">
        <div class="gp-modal-head">
            <h3><i class="fas fa-right-to-bracket" style="color:#10b981"></i> Join tournament</h3>
            <button class="gp-modal-close" data-close-modal><i class="fas fa-times"></i></button>
        </div>
        <div class="gp-modal-body">
            <p class="gp-modal-sub" id="joinTournamentTitle"></p>
            <div id="joinTournamentFee" class="gp-join-fee"></div>
            <form id="joinTournamentForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="action" value="register">
                <input type="hidden" name="tournament_id" id="joinTournamentId" value="">
                <div class="gp-form-group">
                    <label>Join as</label>
                    <div class="gp-radio-group">
                        <label class="gp-radio">
                            <input type="radio" name="join_type" value="solo" checked>
                            <span><i class="fas fa-user"></i> Solo player</span>
                        </label>
                        <?php if (!empty($myTeams)): ?>
                        <label class="gp-radio">
                            <input type="radio" name="join_type" value="team">
                            <span><i class="fas fa-users"></i> With team</span>
                        </label>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="gp-form-group" id="teamSelectGroup" style="display:none">
                    <label>Select team</label>
                    <select name="team_id" class="gp-input">
                        <?php foreach ($myTeams as $team): ?>
                        <option value="<?php echo (int)$team['id']; ?>"><?php echo htmlspecialchars($team['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="gp-form-group" id="soloNameGroup">
                    <label>Team name <span class="text-gray-400">(optional)</span></label>
                    <input type="text" name="team_name" class="gp-input" placeholder="Enter a name for your solo entry">
                </div>
                <div class="gp-modal-actions">
                    <button type="button" class="gp-btn gp-btn-ghost" data-close-modal>Cancel</button>
                    <button type="submit" class="gp-btn gp-btn-primary"><i class="fas fa-check"></i> Confirm join</button>
                </div>
                <div class="gp-feedback hidden"></div>
            </form>
        </div>
    </div>
</div>

<!-- View Participants Modal -->
<div class="gp-modal hidden" id="viewParticipantsModal">
    <div class="gp-modal-panel sm">
        <div class="gp-modal-head">
            <h3><i class="fas fa-users"></i> Participants</h3>
            <button class="gp-modal-close" data-close-modal><i class="fas fa-times"></i></button>
        </div>
        <div class="gp-modal-body" id="participantsList">
            <div class="gp-loading"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
        </div>
    </div>
</div>

<!-- Team Manage Modal -->
<div class="gp-modal hidden" id="teamManageModal">
    <div class="gp-modal-panel sm">
        <div class="gp-modal-head">
            <h3><i class="fas fa-users-gear"></i> <span id="teamManageTitle">Manage team</span></h3>
            <button class="gp-modal-close" data-close-modal><i class="fas fa-times"></i></button>
        </div>
        <div class="gp-modal-body" id="teamManageBody">
            <div class="gp-loading"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
        </div>
    </div>
</div>

<!-- Agent History Modal -->
<div class="gp-modal hidden" id="agentHistoryModal">
    <div class="gp-modal-panel sm">
        <div class="gp-modal-head">
            <h3><i class="fas fa-clock-rotate-left"></i> Transaction history</h3>
            <button class="gp-modal-close" data-close-modal><i class="fas fa-times"></i></button>
        </div>
        <div class="gp-modal-body" id="agentHistoryBody">
            <div class="gp-loading"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
        </div>
    </div>
</div>

<!-- ═══ SUCCESS / FAIL OVERLAY ═══ -->
<div class="gp-success-overlay" id="baResultOverlay">
    <div class="gp-success-box gp-success-box--crown">
        <div id="baResultIcon" class="gp-success-icon"><i class="fas fa-check"></i></div>
        <div id="baResultTitle" class="gp-success-text">Success!</div>
        <div id="baResultSub" class="gp-success-sub">Your request has been submitted</div>
    </div>
</div>

<script>
(function() {
    if (window._gpInitDone) return;
    window._gpInitDone = true;

    function escHtml(str) { var d = document.createElement('div'); d.textContent = str; return d.innerHTML; }
    function api(data) { return fetch('handlers/tournament_handler.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(data) }).then(function(r) { return r.json(); }); }
    function fb(form, r) { var el = form.querySelector('.gp-feedback'); if (!el) return; el.className = 'gp-feedback'; el.classList.add(r.success ? 'success' : 'error'); el.textContent = r.message || 'Done.'; }
    function toast(msg, type) { type = type || 'success'; var t = document.createElement('div'); t.className = 'gp-toast ' + type; t.innerHTML = '<i class="fas ' + (type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle') + '"></i><span>' + escHtml(msg) + '</span>'; document.body.appendChild(t); setTimeout(function() { t.style.opacity = '0'; t.style.transform = 'translateY(20px)'; t.style.transition = 'all .3s'; setTimeout(function() { t.remove(); }, 300); }, 3000); }

    function safe(name, fn) { try { fn(); } catch (e) {} }

    var csrfToken = (document.getElementById('tournamentsPage') || {}).getAttribute('data-csrf') || '';
    var availableGames = <?php echo json_encode($categories); ?>;
    var gameIconsMap = <?php echo json_encode(array_combine($categories, array_slice($gameIcons, 0, count($categories)))); ?>;

    window.saveGameSkill = function(sel, game) {
        var val = sel.value;
        fetch('handlers/tournament_handler.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
            body: JSON.stringify({ action: 'save_game_skill', game: game, skill_level: val, csrf_token: csrfToken })
        }).then(function(r){ return r.json(); }).then(function(res){
            if (res.success) toast('Skill updated for ' + game, 'success');
            else toast(res.message || 'Failed', 'error');
        }).catch(function(){ toast('Network error', 'error'); });
    };

    window.addGameSkillRow = function() {
        var container = document.getElementById('gameSkillsContainer');
        if (!container) return;
        var usedGames = {};
        container.querySelectorAll('.gp-game-skill-row-name').forEach(function(el){ usedGames[el.textContent.trim()] = true; });
        var avail = availableGames.filter(function(g){ return !usedGames[g]; });
        if (!avail.length) { toast('All games added!', 'info'); return; }
        var game = avail[0];
        var icon = gameIconsMap[game] || 'fa-gamepad';
        var row = document.createElement('div');
        row.className = 'gp-game-skill-row';
        row.innerHTML = '<span class="gp-game-skill-icon-preview"><i class="fas ' + icon + '"></i></span><span class="gp-game-skill-row-name">' + game + '</span><select class="gp-input gp-game-skill-row-select" onchange="saveGameSkill(this,\'' + game + '\')"><option value="">--</option><option value="Beginner">Beginner</option><option value="Intermediate">Intermediate</option><option value="Advanced">Advanced</option><option value="Pro">Pro</option><option value="Elite">Elite</option></select>';
        var addBtn = container.querySelector('.gp-add-game-skill-btn');
        container.insertBefore(row, addBtn);
        row.querySelector('select').focus();
    };

    safe('Cards', function() {
        document.querySelectorAll('.gp-card').forEach(function(el, i) {
            el.style.opacity='0'; el.style.transform='translateY(20px)'; el.style.transition='opacity .5s,transform .5s cubic-bezier(.34,1.56,.64,1)';
            setTimeout(function(){ el.style.opacity='1'; el.style.transform='translateY(0)'; },80+i*60);
        });
    });

    safe('Countdowns', function() {
        var els = document.querySelectorAll('.gp-countdown'); if (!els.length) return;
        var tick = function(el) {
            var t = parseInt(el.getAttribute('data-starts'),10); if (!t) return;
            var d = t - (Date.now()/1000|0); if (d<=0) { el.textContent='LIVE'; el.style.color='#16a34a'; return; }
            var dd = d/86400|0, hh = d%86400/3600|0, mm = d%3600/60|0, ss = d%60;
            el.textContent = dd ? dd+'d '+hh+'h' : hh ? hh+'h '+mm+'m '+ss+'s' : mm+'m '+ss+'s';
        };
        els.forEach(tick); clearInterval(window._gpIntv);
        window._gpIntv = setInterval(function(){ document.querySelectorAll('.gp-countdown').forEach(tick); },1000);
    });

    window.toggleFilterSelect = function() {
        var sel = document.getElementById('gpFilterSelect');
        if (sel) sel.classList.toggle('active');
    };

    window.selectFilterOption = function(el) {
        var container = document.getElementById('gpFilterSelect');
        if (!container) return;
        container.querySelectorAll('.gp-select-option').forEach(function(o){ o.classList.remove('active'); });
        el.classList.add('active');
        var trigger = container.querySelector('.gp-select-trigger span');
        if (trigger) trigger.textContent = el.textContent;
        container.classList.remove('active');
        var f = el.getAttribute('data-value');
        document.querySelectorAll('.gp-card').forEach(function(c) {
            c.classList.toggle('hidden', f !== 'all' && c.getAttribute('data-status') !== f);
        });
    };

    // Close custom select on outside click
    document.addEventListener('click', function(e) {
        document.querySelectorAll('.gp-custom-select').forEach(function(sel) {
            if (!sel.contains(e.target)) sel.classList.remove('active');
        });
    });

    window.openGamerProfile = function() {
        var hasProfile = <?php echo !empty($_SESSION['nickname']) ? 'true' : 'false'; ?>;
        var modalId = hasProfile ? 'viewGamerProfileModal' : 'createGamerProfileModal';
        var modal = document.getElementById(modalId);
        if (modal) {
            var ov = document.getElementById('gpOverlay');
            if (ov) ov.classList.remove('hidden');
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
    };

    window.editGamerProfile = function() {
        var viewModal = document.getElementById('viewGamerProfileModal');
        var editModal = document.getElementById('createGamerProfileModal');
        if (viewModal) viewModal.classList.add('hidden');
        if (editModal) {
            editModal.classList.remove('hidden');
        }
    };

    safe('Search', function() {
        var inp = document.getElementById('gpSearch'); if (!inp) return;
        var clearBtn = document.getElementById('gpSearchClear');
        function filterCards() {
            var q = inp.value.trim().toLowerCase();
            document.querySelectorAll('.gp-card').forEach(function(c){ c.classList.toggle('hidden', q && !((c.querySelector('h3')||{}).textContent||'').toLowerCase().includes(q)); });
            if (clearBtn) clearBtn.style.display = q ? 'flex' : 'none';
        }
        inp.addEventListener('input', filterCards);
        if (clearBtn) {
            clearBtn.addEventListener('click', function() {
                inp.value = '';
                filterCards();
                inp.focus();
            });
        }
    });

    safe('Modals', function() {
        var ov = document.getElementById('gpOverlay'); if (!ov) return;
        function gpModalBody(open) {
            document.body.classList.toggle('gp-modal-open', open);
            document.body.style.overflow = open ? 'hidden' : '';
        }
        var close = function() {
            document.querySelectorAll('.gp-modal').forEach(function(m){ m.classList.add('hidden'); });
            ov.classList.add('hidden');
            gpModalBody(false);
        };
        document.querySelectorAll('[data-open-modal]').forEach(function(b) {
            b.addEventListener('click', function() {
                var m = document.getElementById(b.getAttribute('data-open-modal')); if (!m) return;
                ov.classList.remove('hidden'); m.classList.remove('hidden');
                gpModalBody(true);
            });
        });
        document.querySelectorAll('[data-close-modal]').forEach(function(b){ b.addEventListener('click',close); });
        ov.addEventListener('click',close);
        document.addEventListener('keydown',function(e){ if (e.key==='Escape') close(); });
    });

    safe('Forms', function() {
        function otpInputs(container, hiddenId) {
            var ins = document.querySelectorAll('#'+container+' .gp-otp-box');
            ins.forEach(function(inp,i) {
                inp.addEventListener('input',function(){ if (inp.value&&i<ins.length-1) ins[i+1].focus(); document.getElementById(hiddenId).value=Array.from(ins).map(function(x){return x.value;}).join(''); });
                inp.addEventListener('keydown',function(e){ if (e.key==='Backspace'&&!inp.value&&i>0) ins[i-1].focus(); if (e.key==='Enter'){ document.getElementById(hiddenId).value=Array.from(ins).map(function(x){return x.value;}).join(''); var f=inp.closest('form'); if(f)f.requestSubmit(); } });
                inp.addEventListener('paste',function(e){ e.preventDefault(); var cd=e.clipboardData||window.clipboardData; if(!cd)return; var p=cd.getData('text').replace(/\D/g,'').slice(0,6); p.split('').forEach(function(ch,j){ if(ins[j])ins[j].value=ch; }); var nxt=ins[Math.min(p.length,ins.length-1)]; if(nxt)nxt.focus(); document.getElementById(hiddenId).value=Array.from(ins).map(function(x){return x.value;}).join(''); });
            });
        }
        otpInputs('bkashOtpInputs','bkashOtpHidden');

        // Step 1 handler helper
        function step1Handler(formId, step1Id, step2Id, phoneDisplayId, otpContainer, demoOtpId, btnText, apiAction) {
            var f = document.getElementById(formId); if (!f) return;
            f.addEventListener('submit', function(e) {
                e.preventDefault(); var b = f.querySelector('button[type="submit"]'); b.disabled=true; b.innerHTML='<i class="fas fa-spinner fa-spin"></i> Sending OTP...';
                var d = Object.fromEntries(new FormData(f)); d.csrf_token=csrfToken; d.action=apiAction;
                api(d).then(function(r) {
                    fb(f,r);
                    if (r.success) {
                        document.getElementById(step1Id).style.display='none'; document.getElementById(step2Id).style.display='block';
                        var pd = document.getElementById(phoneDisplayId); if (pd) pd.textContent=d.bkash_phone||'';
                        var dop = document.getElementById(demoOtpId); if (dop) dop.textContent=r.demo_otp||'------';
                        var fo = document.querySelector('#'+otpContainer+' .gp-otp-box'); if (fo) setTimeout(function(){fo.focus();},100);
                    } else { b.disabled=false; b.innerHTML=btnText; }
                });
            });
        }

        // Step 2 handler helper
        function step2Handler(formId, otpContainer, hiddenId, btnText, successMsg, apiAction) {
            var f = document.getElementById(formId); if (!f) return;
            f.addEventListener('submit', function(e) {
                e.preventDefault();
                document.getElementById(hiddenId).value = Array.from(document.querySelectorAll('#'+otpContainer+' .gp-otp-box')).map(function(x){return x.value;}).join('');
                var b = f.querySelector('button[type="submit"]'); b.disabled=true; b.innerHTML='<i class="fas fa-spinner fa-spin"></i> Processing...';
                var d = Object.fromEntries(new FormData(f)); d.csrf_token=csrfToken; d.action=apiAction;
                api(d).then(function(r) {
                    fb(f,r);
                    if (r.success) { b.innerHTML='<i class="fas fa-check"></i> Done!'; toast(successMsg); setTimeout(function(){location.reload();},1200); }
                    else { b.disabled=false; b.innerHTML=btnText; }
                });
            });
        }

        // Back button helper
        function backBtn(btnId, step2Id, step1Id, otpContainer, hiddenId, formId) {
            var b = document.getElementById(btnId); if (!b) return;
            b.addEventListener('click', function() {
                document.getElementById(step2Id).style.display='none'; document.getElementById(step1Id).style.display='block';
                document.querySelectorAll('#'+otpContainer+' .gp-otp-box').forEach(function(x){x.value='';});
                document.getElementById(hiddenId).value='';
                var f = document.getElementById(formId); if (f) f.querySelectorAll('.gp-feedback').forEach(function(el){el.classList.add('hidden');el.textContent='';});
            });
        }

        // Become agent flow — step transition
        var baInfoStep = document.getElementById('baInfoStep');
        var baPayStep = document.getElementById('baPayStep');
        var baProceedBtn = document.getElementById('baProceedBtn');
        var baBackToInfo = document.getElementById('baBackToInfo');
        var baPayBtn = document.getElementById('baPayBtn');
        var baPhone = document.getElementById('baPhone');
        var baTxid = document.getElementById('baTxid');
        var baFeedback = document.getElementById('baFeedback');
        var baMerchantBox = document.getElementById('baMerchantBox');

        var baCurrentMethod = 'bkash';
        var baPmData = <?php echo json_encode($pmData); ?>;

        if (baProceedBtn) {
            baProceedBtn.addEventListener('click', function() {
                baInfoStep.classList.remove('active');
                baPayStep.classList.add('active');
                baUpdateMerchant();
            });
        }
        if (baBackToInfo) {
            baBackToInfo.addEventListener('click', function() {
                baPayStep.classList.remove('active');
                baInfoStep.classList.add('active');
                if (baFeedback) { baFeedback.classList.add('hidden'); baFeedback.textContent=''; }
            });
        }
        // Reset step when modal opened
        document.querySelectorAll('[data-open-modal="becomeAgentModal"]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                setTimeout(function() {
                    if (baInfoStep && baPayStep) {
                        baPayStep.classList.remove('active');
                        baInfoStep.classList.add('active');
                        if (baFeedback) { baFeedback.classList.add('hidden'); baFeedback.textContent=''; }
                    }
                }, 10);
            });
        });

        // Method card selection
        document.querySelectorAll('.ba-method-card').forEach(function(card) {
            card.addEventListener('click', function() {
                document.querySelectorAll('.ba-method-card').forEach(function(c) {
                    c.classList.remove('active');
                    c.style.borderColor = '#d1d5db'; c.style.background = '';
                });
                card.classList.add('active');
                var m = card.getAttribute('data-method');
                var d = baPmData[m];
                if (d) { card.style.borderColor = '#E2136E'; card.style.background = '#fdf2f8'; }
                baCurrentMethod = m;
                baUpdateMerchant();
            });
        });

        function baUpdateMerchant() {
            var d = baPmData[baCurrentMethod];
            if (!d) { baMerchantBox.style.display = 'none'; return; }
            var label = d.instruction === 'cashout' ? 'ক্যাশ আউট' : 'সেন্ড মানি';
            var colors = {bkash:'#E2136E', nagad:'#E8522E', rocket:'#CC0000'};
            var names = {bkash:'bKash', nagad:'Nagad', rocket:'Rocket'};
            var dials = {bkash:'*247#', nagad:'*167#', rocket:'*322#'};
            var c = colors[baCurrentMethod] || '#6b7280';
            var n = names[baCurrentMethod] || baCurrentMethod.toUpperCase();
            var mNum = d.number || '01888780877';
            baMerchantBox.innerHTML =
                '<div class="ba-merchant-header"><img src="assets/images/payment-icon/'+baCurrentMethod+'-logo-mobile-banking.png" alt="" onerror="this.style.display=\'none\'"><span style="color:'+c+'">'+n+'</span></div>' +
                '<div class="ba-instr-step">১. <strong>'+dials[baCurrentMethod]+'</strong> ডায়াল করুন অথবা '+n+' অ্যাপ খুলুন।</div>' +
                '<div class="ba-instr-step">২. <strong>"'+label+'"</strong> অপশন সিলেক্ট করুন।</div>' +
                '<div class="ba-instr-step">৩. প্রাপক নম্বর লিখুনঃ <strong class="ba-merchant-num" style="color:'+c+'">'+mNum+'</strong> <button onclick="baCopyNumber()" class="ba-copy-btn"><i class="fas fa-copy"></i> কপি</button></div>' +
                '<div class="ba-instr-step">৪. টাকার পরিমাণ লিখুনঃ <strong>৳৫০০</strong></div>' +
                '<div class="ba-instr-step">৫. আপনার পিন দিন এবং কনফার্ম বাটনে ক্লিক করুন।</div>' +
                '<div class="ba-instr-step">৬. কনফার্মেশন মেসেজ থেকে <strong>Transaction ID</strong> কপি করে নিচে দিন।</div>' +
                '<div class="ba-instr-footer">এখন নিচের বক্সে TXID দিন এবং <strong style="color:#8b5cf6">সাবমিট</strong> বাটনে ক্লিক করুন। ✅</div>';
            baMerchantBox.style.display = 'block';
        }

        window.baCopyNumber = function() {
            var d = baPmData[baCurrentMethod];
            if (!d) return;
            navigator.clipboard.writeText(d.number).then(function(){
                var btn = baMerchantBox.querySelector('.ba-copy-btn');
                if (btn) { var t = btn.innerHTML; btn.innerHTML = '<i class="fas fa-check"></i> কপি হয়েছে!'; setTimeout(function(){btn.innerHTML = t;},1500); }
            });
        };

        // Success/fail overlay for agent modal
        var baResultOverlay = document.getElementById('baResultOverlay');
        var baResultIcon = document.getElementById('baResultIcon');
        var baResultTitle = document.getElementById('baResultTitle');
        var baResultSub = document.getElementById('baResultSub');

        function baShowResult(success, title, sub) {
            baResultOverlay.style.display = 'flex'; gpModalBody(true);
            if (success) {
                baResultIcon.className = 'gp-success-icon';
                baResultIcon.innerHTML = '<i class="fas fa-check"></i>';
            } else {
                baResultIcon.className = 'gp-fail-icon';
                baResultIcon.innerHTML = '<i class="fas fa-times"></i>';
            }
            baResultTitle.textContent = title;
            baResultSub.textContent = sub;
            if (success) {
                setTimeout(function() {
                    baResultOverlay.style.display = 'none'; gpModalBody(false);
                    location.reload();
                }, 2500);
            } else {
                setTimeout(function() { baResultOverlay.style.display = 'none'; gpModalBody(false); }, 3000);
            }
        }

        // Submit payment
        if (baPayBtn) {
            baPayBtn.addEventListener('click', function() {
                var phone = (baPhone ? baPhone.value : '').trim();
                var txid = (baTxid ? baTxid.value : '').trim().toUpperCase();

                if (!/^01[3-9]\d{8}$/.test(phone)) {
                    if (baFeedback) { baFeedback.classList.remove('hidden'); baFeedback.textContent = 'Please enter a valid Bangladeshi phone number (01XXXXXXXXX).'; baFeedback.style.color = '#dc2626'; }
                    return;
                }
                if (txid.length < 4) {
                    if (baFeedback) { baFeedback.classList.remove('hidden'); baFeedback.textContent = 'Please enter a valid Transaction ID.'; baFeedback.style.color = '#dc2626'; }
                    return;
                }

                baPayBtn.disabled = true;
                baPayBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
                if (baFeedback) { baFeedback.classList.add('hidden'); baFeedback.textContent = ''; }

                api({
                    action: 'submit_payment',
                    method: baCurrentMethod,
                    sender_phone: phone,
                    transaction_id: txid,
                    amount: 500,
                    purpose: 'agent_activation',
                    csrf_token: csrfToken
                }).then(function(res) {
                    if (res.success) {
                        baShowResult(true, '✅ পেমেন্ট সাবমিট!', 'পেমেন্ট সাবমিট হয়েছে! অ্যাডমিন ভেরিফাই করে আপনার একাউন্ট এক্টিভেট করবে।');
                    } else {
                        baPayBtn.disabled = false;
                        baPayBtn.innerHTML = '<i class="fas fa-paper-plane"></i> সাবমিট করুন';
                        baShowResult(false, '❌ সাবমিট ব্যর্থ', res.message || 'দয়া করে আবার চেষ্টা করুন।');
                    }
                }).catch(function() {
                    baPayBtn.disabled = false;
                    baPayBtn.innerHTML = '<i class="fas fa-paper-plane"></i> সাবমিট করুন';
                    baShowResult(false, '❌ নেটওয়ার্ক এরর', 'সার্ভারে সংযোগ করা যায়নি। দয়া করে আবার চেষ্টা করুন।');
                });
            });
        }

        // Create Tournament
        document.getElementById('createTournamentForm') && document.getElementById('createTournamentForm').addEventListener('submit', function(e) {
            e.preventDefault(); var b=this.querySelector('button[type="submit"]'); b.disabled=true; b.innerHTML='<i class="fas fa-spinner fa-spin"></i> Creating...';
            var d=Object.fromEntries(new FormData(this)); d.csrf_token=csrfToken; d.action='agent_create_tournament';
            api(d).then(function(r){ fb(e.target,r); if(r.success){b.innerHTML='<i class="fas fa-check"></i> Created!';setTimeout(function(){location.reload();},1200);}else{b.disabled=false;b.innerHTML='<i class="fas fa-trophy"></i> Create tournament';} });
        });

        // Create Team
        document.getElementById('createTeamForm') && document.getElementById('createTeamForm').addEventListener('submit', function(e) {
            e.preventDefault(); var b=this.querySelector('button[type="submit"]'); b.disabled=true; b.innerHTML='<i class="fas fa-spinner fa-spin"></i> Creating...';
            var d=Object.fromEntries(new FormData(this)); d.csrf_token=csrfToken; d.action='create_team';
            api(d).then(function(r){ fb(e.target,r); if(r.success){b.innerHTML='<i class="fas fa-check"></i> Created!';setTimeout(function(){location.reload();},1000);}else{b.disabled=false;b.innerHTML='<i class="fas fa-check"></i> Create team';} });
        });

        // Gamer Profile
        document.getElementById('gamerProfileForm') && document.getElementById('gamerProfileForm').addEventListener('submit', function(e) {
            e.preventDefault(); var b=this.querySelector('button[type="submit"]'); b.disabled=true; b.innerHTML='<i class="fas fa-spinner fa-spin"></i> Saving...';
            var d=Object.fromEntries(new FormData(this)); d.csrf_token=csrfToken; d.action='update_profile';
            api(d).then(function(r){ fb(e.target,r); if(r.success){b.innerHTML='<i class="fas fa-check"></i> Saved!';setTimeout(function(){location.reload();},1000);}else{b.disabled=false;b.innerHTML='<i class="fas fa-check"></i> Save profile';} });
        });

        // Join Tournament
        var jtForm = document.getElementById('joinTournamentForm');
        if (jtForm) {
            jtForm.querySelectorAll('input[name="join_type"]').forEach(function(r) {
                r.addEventListener('change', function() {
                    document.getElementById('teamSelectGroup').style.display = r.value === 'team' ? 'block' : 'none';
                    document.getElementById('soloNameGroup').style.display = r.value === 'solo' ? 'block' : 'none';
                });
            });
            jtForm.addEventListener('submit', function(e) {
                e.preventDefault(); var b=this.querySelector('button[type="submit"]'); b.disabled=true; b.innerHTML='<i class="fas fa-spinner fa-spin"></i> Joining...';
                var d=Object.fromEntries(new FormData(this)); d.csrf_token=csrfToken; d.action='register';
                if (d.join_type !== 'team') delete d.team_id;
                api(d).then(function(r){ fb(e.target,r); if(r.success){b.innerHTML='<i class="fas fa-check"></i> Joined!';setTimeout(function(){location.reload();},1000);}else{b.disabled=false;b.innerHTML='<i class="fas fa-check"></i> Confirm join';} });
            });
        }

        // Join buttons
        document.querySelectorAll('.gp-join-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.getElementById('joinTournamentId').value = btn.getAttribute('data-id');
                document.getElementById('joinTournamentTitle').textContent = btn.getAttribute('data-title');
                var fee = parseFloat(btn.getAttribute('data-fee')||'0'), feeEl=document.getElementById('joinTournamentFee');
                if (fee>0) { feeEl.textContent='Entry fee: ৳'+fee.toFixed(0)+' will be deducted from your balance.'; feeEl.style.display='block'; } else { feeEl.style.display='none'; }
                document.querySelectorAll('#joinTournamentForm .gp-feedback').forEach(function(el){el.classList.add('hidden');el.textContent='';});
                var solo = document.querySelector('#joinTournamentForm input[value="solo"]'); if (solo) solo.checked=true;
                document.getElementById('teamSelectGroup').style.display='none'; document.getElementById('soloNameGroup').style.display='block';
                var ov=document.getElementById('gpOverlay'), m=document.getElementById('joinTournamentModal');
                if (ov) ov.classList.remove('hidden'); if (m) m.classList.remove('hidden'); document.body.style.overflow='hidden';
            });
        });

        // Unregister
        document.querySelectorAll('.gp-unregister').forEach(function(btn) {
            btn.addEventListener('click', function() {
                if (!confirm('Leave this tournament?')) return; var tid=btn.getAttribute('data-id'); btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i>';
                api({action:'unregister',tournament_id:tid,csrf_token:csrfToken}).then(function(r){ if(r.success){toast(r.message);setTimeout(function(){location.reload();},800);}else{toast(r.message,'error');btn.disabled=false;btn.innerHTML='<i class="fas fa-xmark"></i>';} });
            });
        });

        // View participants
        document.querySelectorAll('.gp-view-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var tid=btn.getAttribute('data-id'), list=document.getElementById('participantsList'), ov=document.getElementById('gpOverlay'), mo=document.getElementById('viewParticipantsModal');
                if (!list||!mo) return; if (ov) ov.classList.remove('hidden'); mo.classList.remove('hidden'); document.body.style.overflow='hidden';
                list.innerHTML='<div class="gp-loading"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
                api({action:'get_participants',tournament_id:tid,csrf_token:csrfToken}).then(function(r) {
                    if (r.success&&r.participants&&r.participants.length) {
                        var uid=parseInt((document.getElementById('tournamentsPage')||{}).getAttribute('data-user-id')||'0',10);
                        list.innerHTML='<div class="gp-participants">'+r.participants.map(function(p,i){ var n=p.full_name||p.username||'Player', a=p.avatar||'default.png'; return '<div class="gp-participant"><img src="assets/avatars/'+escHtml(a)+'" alt="" onerror="this.src=\'assets/avatars/default.png\'"><div class="gp-participant-info"><strong>'+escHtml(n)+(parseInt(p.user_id,10)===uid?' <span class="text-purple-500">(you)</span>':'')+'</strong><span>'+(p.team_name?'Team: '+escHtml(p.team_name):'Solo')+'</span></div><span class="text-gray-400 text-xs">#'+(i+1)+'</span></div>'; }).join('')+'</div>';
                    } else { list.innerHTML='<div class="gp-loading" style="padding:3rem"><i class="fas fa-users text-2xl mb-2 block opacity-40"></i><p>No participants yet.</p></div>'; }
                });
            });
        });

        // Team Manage
        document.querySelectorAll('.gp-manage-team').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var tid=btn.getAttribute('data-team-id'), tname=btn.getAttribute('data-team-name'), ov=document.getElementById('gpOverlay'), mo=document.getElementById('teamManageModal'), body=document.getElementById('teamManageBody'), title=document.getElementById('teamManageTitle');
                if (!mo||!body) return; if (title) title.textContent=tname;
                if (ov) ov.classList.remove('hidden'); mo.classList.remove('hidden'); document.body.style.overflow='hidden';
                body.innerHTML='<div class="gp-loading"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
                api({action:'get_team_members',team_id:tid,csrf_token:csrfToken}).then(function(r) {
                    if (r.success&&r.members&&r.members.length) {
                        var uid=parseInt((document.getElementById('tournamentsPage')||{}).getAttribute('data-user-id')||'0',10), isCap=r.members.some(function(m){return parseInt(m.id,10)===uid&&m.role==='captain';});
                        var html='<div class="gp-team-members">';
                        r.members.forEach(function(m){ var n=m.full_name||m.username||'Player', a=m.avatar||'default.png', me=parseInt(m.id,10)===uid; html+='<div class="gp-team-member"><img src="assets/avatars/'+escHtml(a)+'" alt="" onerror="this.src=\'assets/avatars/default.png\'"><div class="gp-team-member-info"><strong>'+escHtml(n)+(me?' (you)':'')+'</strong><span class="gp-team-member-role">'+m.role+'</span></div>'+(isCap&&!me&&m.role!=='captain'?'<button class="gp-btn gp-btn-xs gp-btn-ghost gp-remove-member" data-team="'+tid+'" data-user="'+m.id+'" style="color:#dc2626"><i class="fas fa-user-minus"></i></button>':'')+'</div>'; });
                        html+='</div>';
                        if (isCap) html+='<div class="gp-team-add"><input type="text" class="gp-input gp-add-member-input" placeholder="Enter user ID or username" data-team="'+tid+'"><button class="gp-btn gp-btn-sm gp-btn-primary gp-add-member-btn" data-team="'+tid+'"><i class="fas fa-plus"></i> Add</button></div>';
                        body.innerHTML=html;
                        body.querySelectorAll('.gp-remove-member').forEach(function(rb){ rb.addEventListener('click',function(){ if(!confirm('Remove this member?'))return; api({action:'remove_member',team_id:rb.getAttribute('data-team'),member_id:rb.getAttribute('data-user'),csrf_token:csrfToken}).then(function(res){if(res.success){toast(res.message);setTimeout(function(){location.reload();},800);}else{toast(res.message,'error');}}); }); });
                        body.querySelectorAll('.gp-add-member-btn').forEach(function(ab){ ab.addEventListener('click',function(){ var inp=body.querySelector('.gp-add-member-input'), val=inp?inp.value.trim():''; if(!val)return; api({action:'add_member',team_id:ab.getAttribute('data-team'),member_id:parseInt(val,10)||val,csrf_token:csrfToken}).then(function(res){if(res.success){toast(res.message);setTimeout(function(){location.reload();},800);}else{toast(res.message,'error');}}); }); });
                    } else { body.innerHTML='<div class="gp-loading">No members.</div>'; }
                });
            });
        });

        // History
        document.querySelectorAll('[data-open-modal="agentHistoryModal"]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var body=document.getElementById('agentHistoryBody'); if (!body) return;
                body.innerHTML='<div class="gp-loading"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
                api({action:'agent_stats',csrf_token:csrfToken}).then(function(r) {
                    if (r.success&&r.transactions&&r.transactions.length) {
                        var html='<div class="gp-txns">';
                        r.transactions.forEach(function(t){ var cr=t.type==='credit'; html+='<div class="gp-txn-item"><span class="gp-txn-icon '+t.type+'"><i class="fas '+(cr?'fa-arrow-down':'fa-arrow-up')+'"></i></span><div class="gp-txn-info"><strong>'+escHtml(t.description||t.reference_type||'')+'</strong><span>'+(t.created_at?new Date(t.created_at).toLocaleDateString():'')+'</span></div><span class="gp-txn-amount '+t.type+'">'+(cr?'+':'-')+'৳'+parseFloat(t.amount).toFixed(0)+'</span></div>'; });
                        html+='</div>'; body.innerHTML=html;
                    } else { body.innerHTML='<div class="gp-loading">No transactions yet.</div>'; }
                });
            });
        });

        // Icon picker
        document.querySelectorAll('.gp-icon-opt').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var p = btn.closest('.gp-icon-picker'); if (p) p.querySelectorAll('.gp-icon-opt').forEach(function(b){b.classList.remove('active');});
                btn.classList.add('active');
                var h = btn.closest('.gp-form-group').querySelector('input[type="hidden"]'); if (h) h.value=btn.getAttribute('data-icon')||'';
            });
        });

        // Color picker
        document.querySelectorAll('.gp-color-opt').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var p = btn.closest('.gp-color-picker'); if (p) p.querySelectorAll('.gp-color-opt').forEach(function(b){b.classList.remove('active');});
                btn.classList.add('active');
                var h = btn.closest('.gp-form-group').querySelector('input[type="hidden"]'); if (h) h.value=btn.getAttribute('data-color')||'';
            });
        });

        // Section switcher
        document.querySelectorAll('.gp-switcher-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                var target = btn.getAttribute('data-scroll-to'), el = document.getElementById(target);
                if (el) {
                    document.querySelectorAll('.gp-switcher-btn').forEach(function(b){b.classList.remove('active');});
                    btn.classList.add('active');
                    el.scrollIntoView({behavior:'smooth',block:'start'});
                }
            });
        });
        // Update active section on scroll
        var sections = document.querySelectorAll('.gp-section[id]');
        if (sections.length) {
            var switcherUpdate = function() {
                var scrollY = window.scrollY + 120;
                var activeId = '';
                sections.forEach(function(s) {
                    if (s.offsetTop <= scrollY) activeId = s.getAttribute('id');
                });
                document.querySelectorAll('.gp-switcher-btn').forEach(function(b) {
                    b.classList.toggle('active', b.getAttribute('data-scroll-to') === activeId);
                });
            };
            window.addEventListener('scroll', switcherUpdate, {passive:true});
            setTimeout(switcherUpdate, 100);
        }

        // Legacy scroll-to
        document.querySelectorAll('[data-scroll-to]').forEach(function(link) {
            link.addEventListener('click', function(e) {
                var target = link.getAttribute('data-scroll-to'), el = document.getElementById(target);
                if (el) { e.preventDefault(); el.scrollIntoView({behavior:'smooth',block:'start'}); }
            });
        });
    });
})();
</script>
