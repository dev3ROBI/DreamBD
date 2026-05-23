<?php
require_once __DIR__ . '/../includes/session.php';
dream_start_session();
require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

$viewerId = (int) ($_SESSION['user_id'] ?? 0);
$role = $_SESSION['role'] ?? '';
if ($viewerId <= 0 || $role !== 'agent') {
    echo '<div class="p-8 text-center text-red-500">Only agents can access this page.</div>';
    return;
}

$db = Database::getInstance()->getConnection();
$security = new Security();
$csrfToken = $security->generateCSRFToken();
$feedback = ['type' => '', 'message' => ''];

// Handle POST submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_results') {
    if (!$security->validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $feedback = ['type' => 'error', 'message' => 'Invalid security token.'];
    } else {
        $tournamentId = (int) ($_POST['tournament_id'] ?? 0);
        $teamResults = [];
        $playerResults = [];

        $teamPlacements = $_POST['team_placement'] ?? [];
        $teamPoints = $_POST['team_points'] ?? [];
        $teamScores = $_POST['team_score'] ?? [];
        $teamLabels = $_POST['team_label'] ?? [];
        $teamPrizes = $_POST['team_prize'] ?? [];
        $teamNotes = $_POST['team_notes'] ?? [];

        foreach ($teamPlacements as $teamId => $placement) {
            if (!empty($placement)) {
                $teamResults[] = [
                    'team_id' => (int) $teamId,
                    'placement' => (int) $placement,
                    'points_earned' => (int) ($teamPoints[$teamId] ?? 0),
                    'score' => trim($teamScores[$teamId] ?? ''),
                    'result_label' => trim($teamLabels[$teamId] ?? ''),
                    'prize_amount' => (float) ($teamPrizes[$teamId] ?? 0),
                    'notes' => trim($teamNotes[$teamId] ?? ''),
                ];
            }
        }

        $playerPlacements = $_POST['player_placement'] ?? [];
        $playerPoints = $_POST['player_points'] ?? [];
        $playerScores = $_POST['player_score'] ?? [];
        $playerLabels = $_POST['player_label'] ?? [];
        $playerPrizes = $_POST['player_prize'] ?? [];
        $playerNotes = $_POST['player_notes'] ?? [];

        foreach ($playerPlacements as $userId => $placement) {
            if (!empty($placement)) {
                $playerResults[] = [
                    'user_id' => (int) $userId,
                    'team_id' => (int) ($_POST['player_team_id'][$userId] ?? 0),
                    'placement' => (int) $placement,
                    'points_earned' => (int) ($playerPoints[$userId] ?? 0),
                    'score' => trim($playerScores[$userId] ?? ''),
                    'result_label' => trim($playerLabels[$userId] ?? ''),
                    'prize_amount' => (float) ($playerPrizes[$userId] ?? 0),
                    'notes' => trim($playerNotes[$userId] ?? ''),
                ];
            }
        }

        $result = saveTournamentResults($db, $tournamentId, $viewerId, $teamResults, $playerResults);
        if ($result['success']) {
            $feedback = ['type' => 'success', 'message' => 'Results published successfully!'];
        } else {
            $feedback = ['type' => 'error', 'message' => $result['message']];
        }
    }
}

$tournamentId = (int) ($_GET['id'] ?? 0);
$tournament = null;
$teams = [];
$playerPool = [];

if ($tournamentId > 0) {
    $tournament = getTournamentByIdWithCounts($db, $tournamentId);
    if ($tournament && (int) ($tournament['agent_id'] ?? 0) === $viewerId) {
        $teams = getTournamentTeams($db, $tournamentId);
        $playerPool = getTournamentPlayerPool($db, $tournamentId);
        $existingResults = getTournamentResultsBundle($db, $tournamentId);
    } else {
        $tournament = null;
    }
}

$agentTournaments = [];
$stmt = $db->prepare("
    SELECT t.*,
           (SELECT COUNT(*) FROM tournament_results WHERE tournament_id = t.id) AS has_results
    FROM tournaments t
    WHERE t.agent_id = ?
    ORDER BY COALESCE(t.starts_at, t.created_at) DESC
");
$stmt->execute([$viewerId]);
$agentTournaments = $stmt->fetchAll();
?>
<div class="tr-shell" style="max-width:1280px;margin:0 auto;padding:24px 16px 48px">
    <a class="tr-back" href="index.php?page=tournaments" data-no-ajax style="display:inline-flex;align-items:center;gap:8px;margin-bottom:14px;color:inherit;text-decoration:none">
        <i class="fas fa-arrow-left"></i><span>Back to tournaments</span>
    </a>

    <div style="background:rgba(255,255,255,0.92);border:1px solid rgba(148,163,184,0.22);border-radius:18px;padding:24px;margin-bottom:20px">
        <span style="display:inline-flex;align-items:center;gap:8px;padding:6px 12px;border-radius:999px;background:rgba(16,185,129,0.12);color:#047857;font-size:12px;font-weight:700;text-transform:uppercase">
            <i class="fas fa-medal"></i> Agent result submission
        </span>
        <h1 style="margin:16px 0 8px;font-size:clamp(28px,4vw,40px);line-height:1.05">Submit Tournament Results</h1>
        <p style="color:#475569;line-height:1.6">Select a tournament you own and submit final ranks, points, and prizes for teams and players.</p>
    </div>

    <?php if ($feedback['message']): ?>
    <div style="padding:14px 18px;border-radius:14px;margin-bottom:16px;font-weight:600;background:<?php echo $feedback['type'] === 'success' ? 'rgba(16,185,129,0.12)' : 'rgba(239,68,68,0.12)'; ?>;color:<?php echo $feedback['type'] === 'success' ? '#047857' : '#b91c1c'; ?>">
        <i class="fas fa-<?php echo $feedback['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
        <?php echo htmlspecialchars($feedback['message']); ?>
    </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr;gap:20px">
        <!-- Tournament Selector -->
        <div style="background:rgba(255,255,255,0.92);border:1px solid rgba(148,163,184,0.22);border-radius:18px;padding:18px">
            <h2 style="margin:0 0 14px;font-size:18px"><i class="fas fa-trophy"></i> Your Tournaments</h2>
            <?php if ($agentTournaments): ?>
            <div style="display:grid;gap:10px">
                <?php foreach ($agentTournaments as $t): ?>
                <?php
                $isSelected = (int) $t['id'] === $tournamentId;
                $hasResults = (int) ($t['has_results'] ?? 0) > 0;
                ?>
                <a href="index.php?page=agent_submit_results&id=<?php echo (int) $t['id']; ?>"
                   style="display:flex;align-items:center;gap:14px;padding:12px 14px;border-radius:14px;text-decoration:none;color:inherit;background:<?php echo $isSelected ? 'rgba(124,58,237,0.1)' : 'rgba(148,163,184,0.08)'; ?>;border:<?php echo $isSelected ? '2px solid #7c3aed' : '2px solid transparent'; ?>"
                   data-no-ajax>
                    <span style="width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;background:<?php echo htmlspecialchars($t['accent_color'] ?? '#7c3aed'); ?>">
                        <i class="fas <?php echo htmlspecialchars($t['game_icon'] ?? 'fa-gamepad'); ?>"></i>
                    </span>
                    <div style="flex:1">
                        <strong><?php echo htmlspecialchars($t['title'] ?? 'Tournament'); ?></strong>
                        <div style="display:flex;gap:8px;font-size:13px;color:#64748b">
                            <span><i class="fas fa-signal"></i> <?php echo strtoupper($t['status'] ?? 'upcoming'); ?></span>
                            <?php if ($hasResults): ?>
                            <span style="color:#047857"><i class="fas fa-check-circle"></i> Results submitted</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right" style="color:#94a3b8"></i>
                </a>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p style="color:#64748b">You haven't created any tournaments yet.</p>
            <?php endif; ?>
        </div>

        <?php if ($tournament): ?>
        <!-- Result Form -->
        <form method="POST" style="background:rgba(255,255,255,0.92);border:1px solid rgba(148,163,184,0.22);border-radius:18px;padding:18px">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <input type="hidden" name="action" value="submit_results">
            <input type="hidden" name="tournament_id" value="<?php echo $tournamentId; ?>">

            <h2 style="margin:0 0 14px;font-size:18px"><i class="fas fa-medal"></i> Results for: <?php echo htmlspecialchars($tournament['title'] ?? ''); ?></h2>

            <?php if ($teams): ?>
            <div style="margin-bottom:20px">
                <h3 style="margin:0 0 12px;font-size:15px;color:#475569"><i class="fas fa-users"></i> Team Results</h3>
                <div style="display:grid;gap:10px">
                    <?php foreach ($teams as $team): ?>
                    <div style="display:grid;grid-template-columns:1.5fr 60px 70px 100px 110px 100px 1fr;gap:8px;align-items:center;padding:10px 12px;border-radius:12px;background:rgba(148,163,184,0.06)">
                        <div style="font-size:13px"><strong><?php echo htmlspecialchars($team['linked_team_name'] ?: $team['team_name'] ?: 'Team'); ?></strong></div>
                        <input type="number" name="team_placement[<?php echo (int) ($team['team_id'] ?? 0); ?>]" min="1" placeholder="Rank" style="width:100%;padding:6px 8px;border-radius:8px;border:1px solid rgba(148,163,184,0.3);font-size:13px">
                        <input type="number" name="team_points[<?php echo (int) ($team['team_id'] ?? 0); ?>]" min="0" placeholder="Pts" style="width:100%;padding:6px 8px;border-radius:8px;border:1px solid rgba(148,163,184,0.3);font-size:13px">
                        <input type="text" name="team_score[<?php echo (int) ($team['team_id'] ?? 0); ?>]" placeholder="Score" style="width:100%;padding:6px 8px;border-radius:8px;border:1px solid rgba(148,163,184,0.3);font-size:13px">
                        <input type="text" name="team_label[<?php echo (int) ($team['team_id'] ?? 0); ?>]" placeholder="Label" style="width:100%;padding:6px 8px;border-radius:8px;border:1px solid rgba(148,163,184,0.3);font-size:13px">
                        <input type="number" name="team_prize[<?php echo (int) ($team['team_id'] ?? 0); ?>]" min="0" step="0.01" placeholder="Prize ৳" style="width:100%;padding:6px 8px;border-radius:8px;border:1px solid rgba(148,163,184,0.3);font-size:13px">
                        <input type="text" name="team_notes[<?php echo (int) ($team['team_id'] ?? 0); ?>]" placeholder="Notes" style="width:100%;padding:6px 8px;border-radius:8px;border:1px solid rgba(148,163,184,0.3);font-size:13px">
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div style="margin-bottom:20px">
                <h3 style="margin:0 0 12px;font-size:15px;color:#475569"><i class="fas fa-user"></i> Player Results</h3>
                <div style="display:grid;gap:10px">
                    <?php if ($playerPool): ?>
                    <?php foreach ($playerPool as $player): ?>
                    <?php $uid = (int) ($player['user_id'] ?? 0); ?>
                    <div style="display:grid;grid-template-columns:1.5fr 60px 70px 100px 110px 100px 1fr;gap:8px;align-items:center;padding:10px 12px;border-radius:12px;background:rgba(148,163,184,0.06)">
                        <div style="font-size:13px">
                            <strong><?php echo htmlspecialchars($player['full_name'] ?: $player['username'] ?: 'Player'); ?></strong>
                            <small style="color:#64748b;display:block"><?php echo htmlspecialchars($player['team_name'] ?? ''); ?></small>
                        </div>
                        <input type="number" name="player_placement[<?php echo $uid; ?>]" min="1" placeholder="Rank" style="width:100%;padding:6px 8px;border-radius:8px;border:1px solid rgba(148,163,184,0.3);font-size:13px">
                        <input type="number" name="player_points[<?php echo $uid; ?>]" min="0" placeholder="Pts" style="width:100%;padding:6px 8px;border-radius:8px;border:1px solid rgba(148,163,184,0.3);font-size:13px">
                        <input type="text" name="player_score[<?php echo $uid; ?>]" placeholder="Score" style="width:100%;padding:6px 8px;border-radius:8px;border:1px solid rgba(148,163,184,0.3);font-size:13px">
                        <input type="text" name="player_label[<?php echo $uid; ?>]" placeholder="Label" style="width:100%;padding:6px 8px;border-radius:8px;border:1px solid rgba(148,163,184,0.3);font-size:13px">
                        <input type="number" name="player_prize[<?php echo $uid; ?>]" min="0" step="0.01" placeholder="Prize ৳" style="width:100%;padding:6px 8px;border-radius:8px;border:1px solid rgba(148,163,184,0.3);font-size:13px">
                        <input type="text" name="player_notes[<?php echo $uid; ?>]" placeholder="Notes" style="width:100%;padding:6px 8px;border-radius:8px;border:1px solid rgba(148,163,184,0.3);font-size:13px">
                        <input type="hidden" name="player_team_id[<?php echo $uid; ?>]" value="<?php echo (int) ($player['team_id'] ?? 0); ?>">
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <p style="color:#64748b;font-size:13px">No players registered for this tournament.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div style="display:flex;gap:10px;align-items:center">
                <button type="submit" style="border:0;border-radius:12px;padding:10px 20px;font:inherit;font-weight:700;cursor:pointer;background:#16a34a;color:#fff">
                    <i class="fas fa-check-circle"></i> Publish Results
                </button>
                <span style="font-size:13px;color:#64748b">This will mark the tournament as completed and notify all players.</span>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php
// Register this page in the router
if (!in_array('agent_submit_results', $allowed_pages ?? [])) {
    // Page not in allowed list yet, but the index.php will handle it
}
?>
