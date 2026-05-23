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
?>

<canvas id="gpParticles"></canvas>

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

    <!-- ═══ PROFILE BAR ═══ -->
    <?php if ($viewerId): ?>
    <section class="gp-profile-bar">
        <div class="gp-profile-bar-avatar">
            <img src="assets/avatars/<?php echo htmlspecialchars($_SESSION['avatar'] ?? 'default.png'); ?>" alt="" onerror="this.src='assets/avatars/default.png'">
        </div>
        <div class="gp-profile-bar-info">
            <strong><?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User'); ?></strong>
            <span class="gp-role-badge <?php echo $userRole === 'agent' ? 'gp-role-agent' : 'gp-role-gamer'; ?>">
                <i class="fas <?php echo $userRole === 'agent' ? 'fa-crown' : 'fa-gamepad'; ?>"></i>
                <?php echo $userRole === 'agent' ? 'Agent' : 'Gamer'; ?>
            </span>
        </div>
        <div class="gp-profile-bar-balance">
            <span class="gp-balance-label">Balance</span>
            <span class="gp-balance-value">৳<?php echo number_format($userBalance, 0); ?></span>
            <a href="index.php?page=balance" class="gp-btn gp-btn-xs gp-btn-gradient" style="margin-left:8px;font-size:11px;padding:2px 8px"><i class="fas fa-plus"></i> Add</a>
        </div>
        <div class="gp-profile-bar-actions">
            <?php if ($userRole === 'agent'): ?>
                <button type="button" class="gp-btn gp-btn-sm gp-btn-primary" data-open-modal="bKashModal"><i class="fas fa-mobile-screen-button"></i> bKash</button>
                <button type="button" class="gp-btn gp-btn-sm gp-btn-accent" data-open-modal="createTournamentModal"><i class="fas fa-trophy"></i> Create tournament</button>
            <?php elseif ($userRole === 'user'): ?>
                <button type="button" class="gp-btn gp-btn-sm gp-btn-gradient" data-open-modal="becomeAgentModal"><i class="fas fa-crown"></i> Become an agent</button>
            <?php endif; ?>
            <button type="button" class="gp-btn gp-btn-sm gp-btn-ghost" data-open-modal="gamerProfileModal"><i class="fas fa-id-card"></i> Profile</button>
            <?php if ($userRole !== 'agent'): ?>
                <button type="button" class="gp-btn gp-btn-sm gp-btn-outline" data-open-modal="createTeamModal"><i class="fas fa-users"></i> Create team</button>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- ═══ SECTION SWITCHER ═══ -->
    <div class="gp-section-switcher">
        <a href="#browse" class="gp-switcher-btn active" data-scroll-to="browse">
            <i class="fas fa-trophy"></i> Browse tournaments
        </a>
        <?php if ($viewerId): ?>
        <a href="#my-stuff" class="gp-switcher-btn" data-scroll-to="my-stuff">
            <i class="fas fa-layer-group"></i> My tournaments
        </a>
        <?php endif; ?>
    </div>

    <!-- ═══ TOURNAMENT LIST ═══ -->
    <section id="browse" class="gp-section">
        <div class="gp-section-header">
            <h2><i class="fas fa-trophy"></i> Tournaments</h2>
            <div class="gp-tabs" role="tablist">
                <button class="gp-tab active" data-filter="all" role="tab">All</button>
                <button class="gp-tab" data-filter="live" role="tab">Live</button>
                <button class="gp-tab" data-filter="upcoming" role="tab">Upcoming</button>
                <button class="gp-tab" data-filter="ongoing" role="tab">Ongoing</button>
                <button class="gp-tab" data-filter="completed" role="tab">Completed</button>
            </div>
            <div class="gp-search">
                <i class="fas fa-search"></i>
                <input type="text" id="gpSearch" placeholder="Search tournaments..." class="gp-search-input">
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
                    </div>
                    <div class="gp-card-actions">
                        <?php if ($isRegistered): ?>
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
                        <button class="gp-btn gp-btn-xs gp-btn-ghost gp-unregister" data-id="<?php echo (int)$reg['tournament_id']; ?>"><i class="fas fa-xmark"></i> Leave</button>
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
                    <button class="gp-btn gp-btn-sm gp-btn-primary" data-open-modal="bKashModal"><i class="fas fa-mobile-screen-button"></i> bKash</button>
                    <button class="gp-btn gp-btn-sm gp-btn-accent" data-open-modal="createTournamentModal"><i class="fas fa-trophy"></i> Create tournament</button>
                    <button class="gp-btn gp-btn-sm gp-btn-outline" data-open-modal="agentHistoryModal"><i class="fas fa-clock-rotate-left"></i> History</button>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>
</div>

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

<!-- ═══ Gamer Profile Setup Modal ═══ -->
<div class="gp-modal hidden" id="gamerProfileModal">
    <div class="gp-modal-panel">
        <div class="gp-modal-head">
            <h3><i class="fas fa-id-card" style="color:#7c3aed"></i> My Profile</h3>
            <button class="gp-modal-close" data-close-modal><i class="fas fa-times"></i></button>
        </div>
        <div class="gp-modal-body">
            <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px">
                <div style="width:64px;height:64px;border-radius:50%;overflow:hidden;border:3px solid #e5e7eb;flex-shrink:0;background:#f3f4f6">
                    <img src="assets/avatars/<?php echo htmlspecialchars($_SESSION['avatar'] ?? 'default.png'); ?>" alt="" style="width:100%;height:100%;object-fit:cover" onerror="this.src='assets/avatars/default.png'">
                </div>
                <div>
                    <div style="font-size:18px;font-weight:800;color:#1f2937" class="dark:text-white"><?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User'); ?></div>
                    <div style="font-size:12px;color:#6b7280;margin-top:2px"><?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?></div>
                    <div style="display:inline-flex;align-items:center;gap:4px;margin-top:6px;padding:2px 10px;border-radius:999px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;background:<?php echo $userRole === 'agent' ? '#fef3c7' : '#ede9fe'; ?>;color:<?php echo $userRole === 'agent' ? '#92400e' : '#5b21b6'; ?>">
                        <i class="fas <?php echo $userRole === 'agent' ? 'fa-crown' : 'fa-gamepad'; ?>"></i>
                        <?php echo $userRole === 'agent' ? 'Agent' : 'Gamer'; ?>
                    </div>
                </div>
            </div>

            <form id="gamerProfileForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="action" value="update_profile">
                <div class="gp-form-group">
                    <label>Gaming nickname</label>
                    <input type="text" name="nickname" class="gp-input" placeholder="e.g. ShadowStrike" maxlength="50" value="<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>">
                    <span class="gp-form-hint">This will be displayed to other players</span>
                </div>
                <div class="gp-form-grid two" style="grid-template-columns:1fr 2fr">
                    <div class="gp-form-group">
                        <label>Skill level</label>
                        <select name="skill_level" class="gp-input">
                            <?php $levels = ['','Beginner','Intermediate','Advanced','Pro','Elite']; $current = $_SESSION['skill_level'] ?? ''; ?>
                            <?php foreach ($levels as $lv): ?>
                            <option value="<?php echo $lv; ?>" <?php echo $current === $lv ? 'selected' : ''; ?>><?php echo $lv ?: 'Select'; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="gp-form-group">
                        <label>Favorite game</label>
                        <select name="favorite_game" class="gp-input">
                            <option value="">Select</option>
                            <?php foreach ($categories as $gc): ?>
                            <option value="<?php echo $gc; ?>"><?php echo $gc; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="gp-form-group">
                    <label>Bio</label>
                    <textarea name="bio" class="gp-input" rows="2" placeholder="Tell other players about yourself..." maxlength="200"><?php echo htmlspecialchars($_SESSION['bio'] ?? ''); ?></textarea>
                </div>
                <div class="gp-form-group">
                    <label>Discord (optional)</label>
                    <div class="gp-input-group">
                        <span class="gp-input-prefix"><i class="fab fa-discord"></i></span>
                        <input type="text" name="discord" class="gp-input" placeholder="your#0000" value="<?php echo htmlspecialchars($_SESSION['discord'] ?? ''); ?>">
                    </div>
                </div>
                <div class="gp-modal-actions">
                    <button type="button" class="gp-btn gp-btn-ghost" data-close-modal>Close</button>
                    <button type="submit" class="gp-btn gp-btn-primary"><i class="fas fa-check"></i> Save profile</button>
                </div>
                <div class="gp-feedback hidden"></div>
            </form>
        </div>
    </div>
</div>

<!-- ═══ bKash Payment Modal ═══ -->
<div class="gp-modal hidden" id="bKashModal">
    <div class="gp-modal-panel sm">
        <div class="gp-modal-head">
            <h3><i class="fas fa-mobile-screen-button" style="color:#E2136E"></i> bKash Payment</h3>
            <button class="gp-modal-close" data-close-modal><i class="fas fa-times"></i></button>
        </div>
        <div class="gp-modal-body">
            <!-- Step 1: Amount + Phone -->
            <div id="bkashStep1">
                <div class="gp-bkash-merchant">
                    <i class="fas fa-building-columns"></i>
                    <div>
                        <strong>Merchant: DreamBD Arena</strong>
                        <span>bKash Merchant: <strong>01XXXXXXXXX</strong></span>
                    </div>
                </div>
                <form id="bkashStep1Form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <input type="hidden" name="action" value="bkash_send_otp">
                    <div class="gp-amount-grid">
                        <?php foreach ([50, 100, 200, 500, 1000, 5000] as $amt): ?>
                        <button type="button" class="gp-amount-btn" data-amount="<?php echo $amt; ?>">৳<?php echo $amt; ?></button>
                        <?php endforeach; ?>
                    </div>
                    <div class="gp-form-group">
                        <label>Amount (৳)</label>
                        <div class="gp-input-group">
                            <span class="gp-input-prefix">৳</span>
                            <input type="number" name="bkash_amount" id="bkashAmount" min="10" class="gp-input" placeholder="Enter amount" required>
                        </div>
                    </div>
                    <div class="gp-form-group">
                        <label>Your bKash account number</label>
                        <div class="gp-input-group">
                            <span class="gp-input-prefix"><i class="fas fa-mobile-screen"></i></span>
                            <input type="tel" name="bkash_phone" class="gp-input" placeholder="01XXXXXXXXX" pattern="01[3-9]\d{8}" required>
                        </div>
                        <span class="gp-form-hint">Enter the bKash number you're paying from</span>
                    </div>
                    <div class="gp-modal-actions">
                        <button type="button" class="gp-btn gp-btn-ghost" data-close-modal>Cancel</button>
                        <button type="submit" class="gp-btn gp-btn-bkash"><i class="fas fa-paper-plane"></i> Send OTP</button>
                    </div>
                    <div class="gp-feedback hidden"></div>
                </form>
            </div>

            <!-- Step 2: OTP Verify -->
            <div id="bkashStep2" style="display:none">
                <div class="gp-bkash-check">
                    <i class="fas fa-mobile-screen-button fa-3x" style="color:#E2136E"></i>
                    <p>An OTP has been sent to <strong id="bkashPhoneDisplay"></strong></p>
                    <p class="text-sm text-gray-500">Enter the 6-digit code below</p>
                </div>
                <form id="bkashStep2Form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <input type="hidden" name="action" value="bkash_verify_otp">
                    <div class="gp-form-group">
                        <label>OTP Code</label>
                        <div class="gp-otp-inputs" id="bkashOtpInputs">
                            <?php for ($i = 0; $i < 6; $i++): ?>
                            <input type="text" class="gp-otp-box" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off" required>
                            <?php endfor; ?>
                        </div>
                        <input type="hidden" name="bkash_otp" id="bkashOtpHidden">
                    </div>
                    <div class="gp-bkash-demo-note">
                        <i class="fas fa-flask"></i> Demo mode — use OTP: <strong id="bkashDemoOtp">------</strong>
                    </div>
                    <div class="gp-modal-actions">
                        <button type="button" class="gp-btn gp-btn-ghost" id="bkashBackBtn"><i class="fas fa-arrow-left"></i> Back</button>
                        <button type="submit" class="gp-btn gp-btn-bkash"><i class="fas fa-check-circle"></i> Confirm Payment</button>
                    </div>
                    <div class="gp-feedback hidden"></div>
                </form>
            </div>
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

    safe('Particles', function() {
        var c = document.getElementById('gpParticles'); if (!c) return;
        var ctx = c.getContext('2d'); if (!ctx) return;
        var W = c.width = innerWidth, H = c.height = innerHeight, P = [], N = Math.min(W*H/8000|0,80), C = ['124,58,237','37,99,235','236,72,153','16,185,129','245,158,11'];
        for (var i = 0; i < N; i++) P.push({x:Math.random()*W,y:Math.random()*H,vx:(Math.random()-.5)*.6,vy:(Math.random()-.5)*.6,r:Math.random()*2.5+1,c:C[i%5],a:Math.random()*.5+.2});
        !function loop() {
            ctx.clearRect(0,0,W,H); var dk = document.documentElement.classList.contains('dark');
            for (var i = 0; i < P.length; i++) {
                var p = P[i]; p.x+=p.vx; p.y+=p.vy; if (p.x<0||p.x>W) p.vx*=-1; if (p.y<0||p.y>H) p.vy*=-1;
                ctx.beginPath(); ctx.arc(p.x,p.y,p.r,0,Math.PI*2); ctx.fillStyle='rgba('+p.c+','+(dk?p.a+.2:p.a)+')'; ctx.fill();
                for (var j = i+1; j < P.length; j++) { var d = Math.hypot(p.x-P[j].x,p.y-P[j].y); if (d<120) { ctx.beginPath(); ctx.moveTo(p.x,p.y); ctx.lineTo(P[j].x,P[j].y); ctx.strokeStyle='rgba('+p.c+','+((1-d/120)*(dk?.2:.12))+')'; ctx.lineWidth=.6; ctx.stroke(); } }
            }
            requestAnimationFrame(loop);
        }();
        window.addEventListener('resize', function(){ W=c.width=innerWidth; H=c.height=innerHeight; });
    });

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

    safe('Tabs', function() {
        document.querySelectorAll('.gp-tab').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var parent = btn.closest('.gp-tabs');
                parent.querySelectorAll('.gp-tab').forEach(function(t){ t.classList.remove('active'); });
                btn.classList.add('active');
                var f = btn.getAttribute('data-filter');
                document.querySelectorAll('.gp-card').forEach(function(c){ c.classList.toggle('hidden', f!=='all' && c.getAttribute('data-status')!==f); });
            });
        });
    });

    safe('Search', function() {
        var inp = document.getElementById('gpSearch'); if (!inp) return;
        inp.addEventListener('input', function() {
            var q = inp.value.trim().toLowerCase();
            document.querySelectorAll('.gp-card').forEach(function(c){ c.classList.toggle('hidden', q && !((c.querySelector('h3')||{}).textContent||'').toLowerCase().includes(q)); });
        });
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

        // bKash flow
        step1Handler('bkashStep1Form','bkashStep1','bkashStep2','bkashPhoneDisplay','bkashOtpInputs','bkashDemoOtp','<i class="fas fa-paper-plane"></i> Send OTP','bkash_send_otp');
        step2Handler('bkashStep2Form','bkashOtpInputs','bkashOtpHidden','<i class="fas fa-check-circle"></i> Confirm Payment','Payment successful!','bkash_verify_otp');
        backBtn('bkashBackBtn','bkashStep2','bkashStep1','bkashOtpInputs','bkashOtpHidden','bkashStep2Form');

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

        // Fix: Set amount for bKash step1 (the bkash_send_otp action reads bkash_amount)
        var bkashStep1Form = document.getElementById('bkashStep1Form');
        if (bkashStep1Form) {
            bkashStep1Form.addEventListener('submit', function() {
                var amt = document.getElementById('bkashAmount'); if (amt) { var inp = document.createElement('input'); inp.type='hidden'; inp.name='bkash_amount'; inp.value=amt.value; this.appendChild(inp); }
                // Remove the extra submit listeners to avoid duplicate calls
            }, true); // use capture to run first
        }

        // bKash amount buttons
        document.querySelectorAll('#bKashModal .gp-amount-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.querySelectorAll('#bKashModal .gp-amount-btn').forEach(function(b){b.classList.remove('active');});
                btn.classList.add('active');
                var inp = document.getElementById('bkashAmount'); if (inp) inp.value = btn.getAttribute('data-amount');
            });
        });

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
