<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';

$viewerId = (int) ($_SESSION['user_id'] ?? 0);
if ($viewerId <= 0) {
    header('Location: index.php?page=login');
    exit;
}

$db = Database::getInstance()->getConnection();
$security = new Security();
$csrfToken = $security->generateCSRFToken();
$tournamentId = (int) ($_GET['id'] ?? 0);
$tournament = $tournamentId > 0 ? getTournamentByIdWithCounts($db, $tournamentId) : null;

if (!$tournament || !userCanAccessTournamentRoom($db, $tournamentId, $viewerId)) {
    echo '<div class="p-8 text-center text-red-500">You do not have access to this tournament room.</div>';
    return;
}

$participants = getTournamentParticipants($db, $tournamentId);
$teams = getTournamentTeams($db, $tournamentId);
$playerPool = getTournamentPlayerPool($db, $tournamentId);
$messages = getTournamentRoomMessages($db, $tournamentId);
$results = getTournamentResultsBundle($db, $tournamentId);
$isAgentOwner = (int) ($tournament['agent_id'] ?? 0) === $viewerId && (($_SESSION['role'] ?? '') === 'agent');
$status = (string) ($tournament['status'] ?? 'upcoming');
$accent = htmlspecialchars($tournament['accent_color'] ?? '#7c3aed');
$title = htmlspecialchars($tournament['title'] ?? 'Tournament');
?>

<style>
.tr-shell { max-width: 1280px; margin: 0 auto; padding: 24px 16px 48px; color: var(--text-primary, #0f172a); }
.tr-hero, .tr-panel { background: rgba(255,255,255,0.92); border: 1px solid rgba(148,163,184,0.22); border-radius: 18px; box-shadow: 0 18px 40px rgba(15,23,42,0.08); }
.dark .tr-hero, .dark .tr-panel, [data-theme="dark"] .tr-hero, [data-theme="dark"] .tr-panel { background: rgba(15,23,42,0.92); border-color: rgba(71,85,105,0.45); box-shadow: none; color: #e2e8f0; }
.tr-hero { padding: 24px; margin-bottom: 20px; position: relative; overflow: hidden; }
.tr-hero::before { content: ""; position: absolute; inset: 0 0 auto; height: 5px; background: <?php echo $accent; ?>; }
.tr-kicker { display: inline-flex; align-items: center; gap: 8px; padding: 6px 12px; border-radius: 999px; background: rgba(124,58,237,0.1); color: #6d28d9; font-size: 12px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
.tr-title-row { display: flex; align-items: start; justify-content: space-between; gap: 16px; margin-top: 16px; flex-wrap: wrap; }
.tr-title-row h1 { margin: 0; font-size: clamp(28px, 4vw, 40px); line-height: 1.05; }
.tr-meta { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 12px; color: #475569; font-size: 14px; }
.dark .tr-meta, [data-theme="dark"] .tr-meta { color: #94a3b8; }
.tr-badge { display: inline-flex; align-items: center; gap: 8px; padding: 10px 14px; border-radius: 999px; font-weight: 700; background: rgba(15,23,42,0.06); }
.tr-badge.live { background: rgba(239,68,68,0.12); color: #b91c1c; }
.tr-badge.upcoming { background: rgba(37,99,235,0.12); color: #1d4ed8; }
.tr-badge.completed { background: rgba(16,185,129,0.14); color: #047857; }
.tr-grid { display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 20px; }
.tr-stack { display: grid; gap: 20px; }
.tr-panel { padding: 18px; }
.tr-panel h2, .tr-panel h3 { margin: 0 0 14px; font-size: 18px; }
.tr-copy { color: #475569; line-height: 1.6; }
.dark .tr-copy, [data-theme="dark"] .tr-copy { color: #94a3b8; }
.tr-actions { display: flex; flex-wrap: wrap; gap: 10px; }
.tr-btn { border: 0; border-radius: 12px; padding: 10px 14px; font: inherit; font-weight: 600; cursor: pointer; background: #e2e8f0; color: #0f172a; }
.tr-btn.primary { background: <?php echo $accent; ?>; color: #fff; }
.tr-btn.ghost { background: transparent; border: 1px solid rgba(148,163,184,0.4); }
.tr-btn.success { background: #16a34a; color: #fff; }
.tr-btn.warn { background: #f59e0b; color: #111827; }
.tr-btn:disabled { opacity: .6; cursor: not-allowed; }
.tr-list { display: grid; gap: 10px; }
.tr-item { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 14px; border-radius: 14px; background: rgba(148,163,184,0.08); }
.dark .tr-item, [data-theme="dark"] .tr-item { background: rgba(30,41,59,0.7); }
.tr-item strong, .tr-chat-author { display: block; }
.tr-item span, .tr-chat-time { color: #64748b; font-size: 13px; }
.dark .tr-item span, .dark .tr-chat-time, [data-theme="dark"] .tr-item span, [data-theme="dark"] .tr-chat-time { color: #94a3b8; }
.tr-chat-feed { display: grid; gap: 12px; max-height: 460px; overflow-y: auto; padding-right: 4px; }
.tr-chat-card { padding: 14px; border-radius: 14px; background: rgba(148,163,184,0.08); }
.tr-chat-card.room-card { border-left: 4px solid <?php echo $accent; ?>; }
.dark .tr-chat-card, [data-theme="dark"] .tr-chat-card { background: rgba(30,41,59,0.72); }
.tr-room-card-meta { display: grid; gap: 8px; margin-top: 10px; padding-top: 10px; border-top: 1px solid rgba(148,163,184,0.2); }
.tr-form { display: grid; gap: 12px; }
.tr-field-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.tr-form input, .tr-form textarea, .tr-form select { width: 100%; border-radius: 12px; border: 1px solid rgba(148,163,184,0.35); padding: 11px 12px; font: inherit; background: #fff; color: #0f172a; }
.dark .tr-form input, .dark .tr-form textarea, .dark .tr-form select, [data-theme="dark"] .tr-form input, [data-theme="dark"] .tr-form textarea, [data-theme="dark"] .tr-form select { background: #0f172a; color: #e2e8f0; border-color: rgba(71,85,105,0.7); }
.tr-form textarea { min-height: 100px; resize: vertical; }
.tr-results-grid { display: grid; gap: 12px; }
.tr-result-row { display: grid; grid-template-columns: 1.2fr 90px 120px 140px 1fr; gap: 10px; align-items: center; padding: 12px; border-radius: 14px; background: rgba(148,163,184,0.08); }
.dark .tr-result-row, [data-theme="dark"] .tr-result-row { background: rgba(30,41,59,0.72); }
.tr-result-name small { display: block; color: #64748b; }
.tr-note { font-size: 13px; color: #64748b; }
.dark .tr-note, [data-theme="dark"] .tr-note { color: #94a3b8; }
.tr-results-board { display: grid; gap: 10px; }
.tr-result-card { padding: 12px 14px; border-radius: 14px; background: rgba(148,163,184,0.08); }
.tr-result-card strong { display: block; margin-bottom: 4px; }
.tr-back { display: inline-flex; align-items: center; gap: 8px; margin-bottom: 14px; color: inherit; text-decoration: none; }
.tr-feedback { min-height: 20px; font-size: 14px; }
.tr-feedback.success { color: #15803d; }
.tr-feedback.error { color: #dc2626; }
@media (max-width: 980px) {
  .tr-grid { grid-template-columns: 1fr; }
  .tr-field-grid, .tr-result-row { grid-template-columns: 1fr; }
}
</style>

<div class="tr-shell" data-tournament-room data-tournament-id="<?php echo $tournamentId; ?>" data-csrf-token="<?php echo htmlspecialchars($csrfToken); ?>">
    <a class="tr-back" href="index.php?page=tournaments" data-no-ajax><i class="fas fa-arrow-left"></i><span>Back to tournaments</span></a>

    <section class="tr-hero">
        <span class="tr-kicker"><i class="fas fa-door-open"></i> Tournament room</span>
        <div class="tr-title-row">
            <div>
                <h1><?php echo $title; ?></h1>
                <div class="tr-meta">
                    <span><i class="fas fa-calendar-days"></i> <?php echo !empty($tournament['starts_at']) ? date('M j, Y g:i A', strtotime($tournament['starts_at'])) : 'Start time TBD'; ?></span>
                    <span><i class="fas fa-users"></i> <?php echo (int) ($tournament['registered_teams'] ?? 0); ?> joined</span>
                    <span><i class="fas fa-tag"></i> <?php echo htmlspecialchars($tournament['category'] ?? 'General'); ?></span>
                    <span><i class="fas fa-trophy"></i> Prize <?php echo htmlspecialchars((string) ($tournament['prize_money'] ?? '0')); ?></span>
                </div>
                <?php if (!empty($tournament['description'])): ?>
                    <p class="tr-copy" style="margin-top:14px"><?php echo htmlspecialchars($tournament['description']); ?></p>
                <?php endif; ?>
            </div>
            <span class="tr-badge <?php echo htmlspecialchars($status); ?>"><i class="fas fa-signal"></i> <?php echo strtoupper($status); ?></span>
        </div>
        <?php if ($isAgentOwner): ?>
            <div class="tr-actions" style="margin-top:18px">
                <button class="tr-btn ghost" data-status-action="upcoming">Set upcoming</button>
                <button class="tr-btn warn" data-status-action="live">Make live</button>
                <button class="tr-btn success" data-status-action="completed">Complete</button>
            </div>
        <?php endif; ?>
    </section>

    <div class="tr-grid">
        <div class="tr-stack">
            <section class="tr-panel">
                <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap">
                    <h2><i class="fas fa-comments"></i> Room chat</h2>
                    <button class="tr-btn ghost" type="button" data-refresh-chat>Refresh</button>
                </div>
                <div class="tr-chat-feed" id="trChatFeed">
                    <?php if (!$messages): ?>
                        <div class="tr-chat-card"><span class="tr-chat-time">No messages yet. This is where invites and room updates will appear.</span></div>
                    <?php endif; ?>
                    <?php foreach ($messages as $message): ?>
                        <?php $meta = !empty($message['metadata_json']) ? json_decode($message['metadata_json'], true) : []; ?>
                        <article class="tr-chat-card <?php echo ($message['message_type'] ?? '') === 'room_card' ? 'room-card' : ''; ?>">
                            <strong class="tr-chat-author"><?php echo htmlspecialchars($message['full_name'] ?: $message['username']); ?></strong>
                            <span class="tr-chat-time"><?php echo date('M j, g:i A', strtotime($message['created_at'] ?? 'now')); ?></span>
                            <p class="tr-copy" style="margin:10px 0 0"><?php echo nl2br(htmlspecialchars($message['message'] ?? '')); ?></p>
                            <?php if (($message['message_type'] ?? '') === 'room_card' && is_array($meta)): ?>
                                <div class="tr-room-card-meta">
                                    <?php if (!empty($meta['room_title'])): ?><div><strong><?php echo htmlspecialchars($meta['room_title']); ?></strong></div><?php endif; ?>
                                    <?php if (!empty($meta['room_code'])): ?><div>Room code: <?php echo htmlspecialchars($meta['room_code']); ?></div><?php endif; ?>
                                    <?php if (!empty($meta['room_link'])): ?><div><a href="<?php echo htmlspecialchars($meta['room_link']); ?>" target="_blank" rel="noopener">Open invite</a></div><?php endif; ?>
                                    <?php if (!empty($meta['starts_at'])): ?><div>Room opens: <?php echo htmlspecialchars($meta['starts_at']); ?></div><?php endif; ?>
                                    <?php if (!empty($meta['note'])): ?><div><?php echo htmlspecialchars($meta['note']); ?></div><?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
                <form class="tr-form" id="trTextChatForm" style="margin-top:14px">
                    <input type="hidden" name="message_type" value="text">
                    <textarea name="message" placeholder="Share a tournament update or message the joined teams..." required></textarea>
                    <div class="tr-actions">
                        <button class="tr-btn primary" type="submit">Send message</button>
                        <div class="tr-feedback" id="trChatFeedback"></div>
                    </div>
                </form>
            </section>

            <?php if ($isAgentOwner): ?>
            <section class="tr-panel">
                <h2><i class="fas fa-id-card-clip"></i> Share room card</h2>
                <form class="tr-form" id="trRoomCardForm">
                    <input type="hidden" name="message_type" value="room_card">
                    <div class="tr-field-grid">
                        <input type="text" name="room_title" placeholder="Room title" required>
                        <input type="text" name="room_code" placeholder="Room code / ID">
                    </div>
                    <div class="tr-field-grid">
                        <input type="url" name="room_link" placeholder="Invite link">
                        <input type="text" name="starts_at" placeholder="Open time or round note">
                    </div>
                    <textarea name="note" placeholder="Quick note for players"></textarea>
                    <textarea name="message" placeholder="Optional message shown above the room card"></textarea>
                    <div class="tr-actions">
                        <button class="tr-btn primary" type="submit">Share room card</button>
                        <div class="tr-feedback" id="trRoomCardFeedback"></div>
                    </div>
                </form>
            </section>

            <section class="tr-panel">
                <h2><i class="fas fa-medal"></i> Submit final results</h2>
                <p class="tr-note">When you publish results, the tournament moves to completed and each listed player gets the result on their profile.</p>

                <form class="tr-form" id="trResultsForm">
                    <div class="tr-results-grid">
                        <h3>Team results</h3>
                        <?php if ($teams): ?>
                            <?php foreach ($teams as $team): ?>
                                <div class="tr-result-row team-row">
                                    <div class="tr-result-name">
                                        <strong><?php echo htmlspecialchars($team['linked_team_name'] ?: $team['team_name'] ?: 'Team'); ?></strong>
                                        <small>Captain: <?php echo htmlspecialchars($team['captain_name'] ?: $team['captain_username'] ?: 'Unknown'); ?></small>
                                    </div>
                                    <input type="hidden" name="team_id" value="<?php echo (int) ($team['team_id'] ?? 0); ?>">
                                    <input type="number" name="placement" min="1" placeholder="Place">
                                    <input type="number" name="points_earned" min="0" placeholder="Points" title="Points earned">
                                    <input type="text" name="score" placeholder="Score">
                                    <input type="text" name="result_label" placeholder="Winner / Runner-up">
                                    <input type="number" name="prize_amount" min="0" step="0.01" placeholder="Prize ৳" title="Prize amount">
                                    <input type="text" name="notes" placeholder="Notes">
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="tr-note">No registered teams yet.</p>
                        <?php endif; ?>
                    </div>

                    <div class="tr-results-grid">
                        <h3>Player results</h3>
                        <?php if ($playerPool): ?>
                            <?php foreach ($playerPool as $participant): ?>
                                <div class="tr-result-row player-row">
                                    <div class="tr-result-name">
                                        <strong><?php echo htmlspecialchars($participant['full_name'] ?: $participant['username'] ?: 'Player'); ?></strong>
                                        <small><?php echo htmlspecialchars($participant['team_name'] ?: 'Solo player'); ?></small>
                                    </div>
                                    <input type="hidden" name="user_id" value="<?php echo (int) ($participant['user_id'] ?? 0); ?>">
                                    <input type="hidden" name="team_id" value="<?php echo (int) ($participant['team_id'] ?? 0); ?>">
                                    <input type="number" name="placement" min="1" placeholder="Place">
                                    <input type="number" name="points_earned" min="0" placeholder="Points" title="Points earned">
                                    <input type="text" name="score" placeholder="Score / kills">
                                    <input type="text" name="result_label" placeholder="MVP / Finalist">
                                    <input type="number" name="prize_amount" min="0" step="0.01" placeholder="Prize ৳" title="Prize amount">
                                    <input type="text" name="notes" placeholder="Notes">
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="tr-note">No participants yet.</p>
                        <?php endif; ?>
                    </div>
                    <div class="tr-actions">
                        <button class="tr-btn success" type="submit">Publish results</button>
                        <div class="tr-feedback" id="trResultsFeedback"></div>
                    </div>
                </form>
            </section>
            <?php endif; ?>
        </div>

        <div class="tr-stack">
            <section class="tr-panel">
                <h2><i class="fas fa-users"></i> Joined teams</h2>
                <div class="tr-list">
                    <?php if (!$teams): ?>
                        <div class="tr-item"><div><strong>No teams yet</strong><span>Registrations will appear here.</span></div></div>
                    <?php endif; ?>
                    <?php foreach ($teams as $team): ?>
                        <div class="tr-item">
                            <div>
                                <strong><?php echo htmlspecialchars($team['linked_team_name'] ?: $team['team_name'] ?: 'Team'); ?></strong>
                                <span><?php echo (int) ($team['member_count'] ?? 0); ?> members<?php if (!empty($team['captain_name']) || !empty($team['captain_username'])): ?>, captain <?php echo htmlspecialchars($team['captain_name'] ?: $team['captain_username']); ?><?php endif; ?></span>
                            </div>
                            <span class="tr-badge"><?php echo htmlspecialchars(strtoupper($team['status'] ?? 'confirmed')); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="tr-panel">
                <h2><i class="fas fa-ranking-star"></i> Published results</h2>
                <div class="tr-results-board" id="trResultsBoard">
                    <?php if (!$results['teams'] && !$results['players']): ?>
                        <div class="tr-result-card"><strong>No results yet</strong><span class="tr-note">Final standings will appear here once the agent publishes them.</span></div>
                    <?php endif; ?>
                    <?php foreach ($results['teams'] as $result): ?>
                        <div class="tr-result-card">
                            <strong>#<?php echo (int) ($result['placement'] ?? 0); ?> <?php echo htmlspecialchars($result['linked_team_name'] ?: 'Team'); ?></strong>
                            <span class="tr-note"><?php echo htmlspecialchars($result['result_label'] ?: 'Team result'); ?><?php if (!empty($result['score'])): ?>, score <?php echo htmlspecialchars($result['score']); ?><?php endif; ?><?php if (!empty($result['points_earned'])): ?>, <?php echo (int) $result['points_earned']; ?> pts<?php endif; ?><?php if (!empty($result['prize_amount'])): ?>, ৳<?php echo htmlspecialchars($result['prize_amount']); ?><?php endif; ?></span>
                        </div>
                    <?php endforeach; ?>
                    <?php foreach ($results['players'] as $result): ?>
                        <div class="tr-result-card">
                            <strong>#<?php echo (int) ($result['placement'] ?? 0); ?> <?php echo htmlspecialchars($result['full_name'] ?: $result['username'] ?: 'Player'); ?></strong>
                            <span class="tr-note"><?php echo htmlspecialchars($result['result_label'] ?: 'Player result'); ?><?php if (!empty($result['score'])): ?>, score <?php echo htmlspecialchars($result['score']); ?><?php endif; ?><?php if (!empty($result['points_earned'])): ?>, <?php echo (int) $result['points_earned']; ?> pts<?php endif; ?><?php if (!empty($result['prize_amount'])): ?>, ৳<?php echo htmlspecialchars($result['prize_amount']); ?><?php endif; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
    </div>
</div>

<script>
(function () {
    var root = document.querySelector('[data-tournament-room]');
    if (!root) { return; }

    var tournamentId = root.getAttribute('data-tournament-id');
    var csrfToken = root.getAttribute('data-csrf-token');

    function setFeedback(id, message, ok) {
        var node = document.getElementById(id);
        if (!node) { return; }
        node.textContent = message || '';
        node.className = 'tr-feedback' + (message ? (ok ? ' success' : ' error') : '');
    }

    function api(payload) {
        payload.csrf_token = csrfToken;
        return fetch('handlers/tournament_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(payload)
        }).then(function (res) { return res.json(); });
    }

    function renderMessages(messages) {
        var feed = document.getElementById('trChatFeed');
        if (!feed) { return; }
        if (!Array.isArray(messages) || !messages.length) {
            feed.innerHTML = '<div class="tr-chat-card"><span class="tr-chat-time">No messages yet. This is where invites and room updates will appear.</span></div>';
            return;
        }
        feed.innerHTML = messages.map(function (message) {
            var meta = {};
            if (message.metadata_json) {
                try { meta = JSON.parse(message.metadata_json); } catch (e) { meta = {}; }
            }
            var roomMeta = '';
            if (message.message_type === 'room_card') {
                roomMeta += '<div class="tr-room-card-meta">';
                if (meta.room_title) { roomMeta += '<div><strong>' + escapeHtml(meta.room_title) + '</strong></div>'; }
                if (meta.room_code) { roomMeta += '<div>Room code: ' + escapeHtml(meta.room_code) + '</div>'; }
                if (meta.room_link) { roomMeta += '<div><a href="' + escapeAttr(meta.room_link) + '" target="_blank" rel="noopener">Open invite</a></div>'; }
                if (meta.starts_at) { roomMeta += '<div>Room opens: ' + escapeHtml(meta.starts_at) + '</div>'; }
                if (meta.note) { roomMeta += '<div>' + escapeHtml(meta.note) + '</div>'; }
                roomMeta += '</div>';
            }
            return '<article class="tr-chat-card ' + (message.message_type === 'room_card' ? 'room-card' : '') + '">' +
                '<strong class="tr-chat-author">' + escapeHtml(message.full_name || message.username || 'User') + '</strong>' +
                '<span class="tr-chat-time">' + escapeHtml(formatDate(message.created_at)) + '</span>' +
                '<p class="tr-copy" style="margin:10px 0 0">' + nl2br(escapeHtml(message.message || '')) + '</p>' +
                roomMeta +
                '</article>';
        }).join('');
        feed.scrollTop = feed.scrollHeight;
    }

    function renderResults(results) {
        var board = document.getElementById('trResultsBoard');
        if (!board) { return; }
        var cards = [];
        (results.teams || []).forEach(function (result) {
            var pts = result.points_earned ? ', ' + escapeHtml(String(result.points_earned)) + ' pts' : '';
            var prize = result.prize_amount && parseFloat(result.prize_amount) > 0 ? ', ৳' + escapeHtml(String(result.prize_amount)) : '';
            cards.push('<div class="tr-result-card"><strong>#' + escapeHtml(String(result.placement || '0')) + ' ' + escapeHtml(result.linked_team_name || 'Team') + '</strong><span class="tr-note">' + escapeHtml(result.result_label || 'Team result') + (result.score ? ', score ' + escapeHtml(result.score) : '') + pts + prize + '</span></div>');
        });
        (results.players || []).forEach(function (result) {
            var pts = result.points_earned ? ', ' + escapeHtml(String(result.points_earned)) + ' pts' : '';
            var prize = result.prize_amount && parseFloat(result.prize_amount) > 0 ? ', ৳' + escapeHtml(String(result.prize_amount)) : '';
            cards.push('<div class="tr-result-card"><strong>#' + escapeHtml(String(result.placement || '0')) + ' ' + escapeHtml(result.full_name || result.username || 'Player') + '</strong><span class="tr-note">' + escapeHtml(result.result_label || 'Player result') + (result.score ? ', score ' + escapeHtml(result.score) : '') + pts + prize + '</span></div>');
        });
        board.innerHTML = cards.length ? cards.join('') : '<div class="tr-result-card"><strong>No results yet</strong><span class="tr-note">Final standings will appear here once the agent publishes them.</span></div>';
    }

    function collectRows(selector, includeUser) {
        return Array.prototype.map.call(document.querySelectorAll(selector), function (row) {
            var payload = {
                team_id: row.querySelector('input[name="team_id"]') ? row.querySelector('input[name="team_id"]').value : '',
                placement: row.querySelector('input[name="placement"]').value,
                points_earned: row.querySelector('input[name="points_earned"]') ? row.querySelector('input[name="points_earned"]').value : '0',
                score: row.querySelector('input[name="score"]').value,
                result_label: row.querySelector('input[name="result_label"]').value,
                prize_amount: row.querySelector('input[name="prize_amount"]') ? row.querySelector('input[name="prize_amount"]').value : '0',
                notes: row.querySelector('input[name="notes"]').value
            };
            if (includeUser && row.querySelector('input[name="user_id"]')) {
                payload.user_id = row.querySelector('input[name="user_id"]').value;
            }
            return payload;
        }).filter(function (row) {
            return row.placement || row.score || row.result_label || row.notes;
        });
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, function (char) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char];
        });
    }

    function escapeAttr(value) {
        return escapeHtml(value).replace(/"/g, '&quot;');
    }

    function nl2br(value) {
        return value.replace(/\n/g, '<br>');
    }

    function formatDate(value) {
        if (!value) { return 'Just now'; }
        var date = new Date(value.replace(' ', 'T'));
        if (Number.isNaN(date.getTime())) { return value; }
        return date.toLocaleString([], { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
    }

    Array.prototype.forEach.call(document.querySelectorAll('[data-status-action]'), function (button) {
        button.addEventListener('click', function () {
            button.disabled = true;
            api({ action: 'update_tournament_status', tournament_id: tournamentId, status: button.getAttribute('data-status-action') })
                .then(function (result) {
                    if (result.success) { window.location.reload(); return; }
                    setFeedback('trChatFeedback', result.message || 'Could not update status.', false);
                    button.disabled = false;
                })
                .catch(function () {
                    setFeedback('trChatFeedback', 'Could not update status.', false);
                    button.disabled = false;
                });
        });
    });

    var textChatForm = document.getElementById('trTextChatForm');
    if (textChatForm) {
        textChatForm.addEventListener('submit', function (event) {
            event.preventDefault();
            var formData = Object.fromEntries(new FormData(textChatForm));
            formData.action = 'send_tournament_chat';
            formData.tournament_id = tournamentId;
            api(formData).then(function (result) {
                setFeedback('trChatFeedback', result.message || '', !!result.success);
                if (result.success) {
                    textChatForm.reset();
                    renderMessages(result.messages || []);
                }
            }).catch(function () {
                setFeedback('trChatFeedback', 'Could not send message.', false);
            });
        });
    }

    var roomCardForm = document.getElementById('trRoomCardForm');
    if (roomCardForm) {
        roomCardForm.addEventListener('submit', function (event) {
            event.preventDefault();
            var formData = Object.fromEntries(new FormData(roomCardForm));
            formData.action = 'send_tournament_chat';
            formData.tournament_id = tournamentId;
            api(formData).then(function (result) {
                setFeedback('trRoomCardFeedback', result.message || '', !!result.success);
                if (result.success) {
                    roomCardForm.reset();
                    renderMessages(result.messages || []);
                }
            }).catch(function () {
                setFeedback('trRoomCardFeedback', 'Could not share room card.', false);
            });
        });
    }

    var resultsForm = document.getElementById('trResultsForm');
    if (resultsForm) {
        resultsForm.addEventListener('submit', function (event) {
            event.preventDefault();
            api({
                action: 'submit_tournament_results',
                tournament_id: tournamentId,
                team_results: collectRows('.team-row', false),
                player_results: collectRows('.player-row', true)
            }).then(function (result) {
                setFeedback('trResultsFeedback', result.message || '', !!result.success);
                if (result.success) {
                    renderResults(result.results || { teams: [], players: [] });
                }
            }).catch(function () {
                setFeedback('trResultsFeedback', 'Could not submit results.', false);
            });
        });
    }

    var refreshButton = document.querySelector('[data-refresh-chat]');
    if (refreshButton) {
        refreshButton.addEventListener('click', function () {
            api({ action: 'get_tournament_chat', tournament_id: tournamentId }).then(function (result) {
                if (result.success) {
                    renderMessages(result.messages || []);
                }
            });
        });
    }
})();
</script>
