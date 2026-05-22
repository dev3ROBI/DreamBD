<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';

$db = Database::getInstance()->getConnection();
$viewerId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['role'] ?? 'user';

if (!$viewerId || $userRole !== 'agent') {
    echo '<div class="p-8 text-center"><h2 class="text-xl font-bold mb-2">Access denied</h2><p class="text-gray-500">Only agents can access this page.</p><a href="index.php?page=tournaments" class="btn btn-primary mt-4">Go to tournaments</a></div>';
    exit;
}

$security = new Security();
$csrfToken = $security->generateCSRFToken();

$stmt = $db->prepare("SELECT balance, agent_verified_at FROM users WHERE id = ?");
$stmt->execute([$viewerId]);
$user = $stmt->fetch();
$balance = (float)($user['balance'] ?? 0);

$stats = getAgentStats($db, $viewerId);
$transactions = getAgentTransactions($db, $viewerId, 30);

$stmt = $db->prepare("SELECT * FROM tournaments WHERE agent_id = ? ORDER BY COALESCE(starts_at, created_at) DESC");
$stmt->execute([$viewerId]);
$myTournaments = $stmt->fetchAll();
?>

<div class="gp-page">
    <section class="gp-hero" style="margin-top:1.5rem">
        <div class="gp-hero-bg"></div>
        <div class="gp-hero-content">
            <span class="gp-hero-badge"><i class="fas fa-crown" style="color:#f59e0b"></i> Agent Dashboard</span>
            <h1>Welcome, <span class="text-gradient"><?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Agent'); ?></span></h1>
            <p>Manage your tournaments, track earnings, and grow your presence.</p>
        </div>
        <div class="gp-hero-stats">
            <div class="gp-stat-card"><span class="gp-stat-value">৳<?php echo number_format($balance, 0); ?></span><span class="gp-stat-label">Balance</span></div>
            <div class="gp-stat-card"><span class="gp-stat-value"><?php echo $stats['total_tournaments']; ?></span><span class="gp-stat-label">Created</span></div>
            <div class="gp-stat-card"><span class="gp-stat-value"><?php echo $stats['total_participants']; ?></span><span class="gp-stat-label">Players</span></div>
            <div class="gp-stat-card"><span class="gp-stat-value">৳<?php echo number_format($stats['total_prize_spent'], 0); ?></span><span class="gp-stat-label">Prize spent</span></div>
        </div>
    </section>

    <div class="gp-agent-quick" style="display:flex;gap:.75rem;margin-bottom:1.5rem;flex-wrap:wrap">
        <a href="index.php?page=tournaments" class="gp-btn gp-btn-primary" data-page="tournaments"><i class="fas fa-arrow-left"></i> Back to tournaments</a>
        <button type="button" class="gp-btn gp-btn-accent" onclick="document.querySelector('[data-open-modal=\'bKashModal\']')?.click()"><i class="fas fa-plus"></i> Add funds</button>
        <button type="button" class="gp-btn gp-btn-gradient" onclick="document.querySelector('[data-open-modal=\'createTournamentModal\']')?.click()"><i class="fas fa-trophy"></i> Create tournament</button>
    </div>

    <div class="gp-my-grid" style="margin-bottom:2rem">
        <div class="gp-my-panel gp-agent-panel">
            <div class="gp-my-panel-header"><i class="fas fa-trophy"></i> My tournaments <span class="gp-count"><?php echo count($myTournaments); ?></span></div>
            <div class="gp-my-list">
                <?php if (empty($myTournaments)): ?>
                <div class="gp-my-empty">No tournaments created yet.</div>
                <?php else: foreach ($myTournaments as $t):
                    $tStatus = $t['status'] ?? '';
                    $badgeMap = ['live'=>'badge-live','upcoming'=>'badge-upcoming','ongoing'=>'badge-ongoing','completed'=>'badge-completed','cancelled'=>'badge-cancelled'];
                    $bClass = $badgeMap[$tStatus] ?? '';
                ?>
                <div class="gp-my-item">
                    <span class="gp-my-icon" style="background:<?php echo htmlspecialchars($t['accent_color'] ?? '#7c3aed'); ?>"><i class="fas <?php echo htmlspecialchars($t['game_icon'] ?? 'fa-gamepad'); ?>"></i></span>
                    <div class="gp-my-info">
                        <strong><?php echo htmlspecialchars($t['title']); ?></strong>
                        <span><span class="gp-badge sm <?php echo $bClass; ?>"><?php echo strtoupper($tStatus); ?></span> &middot; $<?php echo htmlspecialchars($t['prize_money'] ?: '0'); ?> prize</span>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <div class="gp-my-panel">
            <div class="gp-my-panel-header"><i class="fas fa-clock-rotate-left"></i> Recent transactions</div>
            <div class="gp-my-list">
                <?php if (empty($transactions)): ?>
                <div class="gp-my-empty">No transactions yet.</div>
                <?php else: ?>
                <div class="gp-txns">
                    <?php foreach ($transactions as $tx):
                        $isCredit = $tx['type'] === 'credit';
                        $icon = $isCredit ? 'fa-arrow-down' : 'fa-arrow-up';
                    ?>
                    <div class="gp-txn-item">
                        <span class="gp-txn-icon <?php echo $tx['type']; ?>"><i class="fas <?php echo $icon; ?>"></i></span>
                        <div class="gp-txn-info">
                            <strong><?php echo htmlspecialchars($tx['description'] ?? $tx['reference_type'] ?? ''); ?></strong>
                            <span><?php echo date('M j, Y g:i A', strtotime($tx['created_at'])); ?></span>
                        </div>
                        <span class="gp-txn-amount <?php echo $tx['type']; ?>"><?php echo $isCredit ? '+' : '-'; ?>৳<?php echo number_format((float)$tx['amount'], 0); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
