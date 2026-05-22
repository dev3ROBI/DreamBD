<?php
$adminViewerId = $_SESSION['user_id'] ?? null;
if (!$adminViewerId) { echo '<p>Please log in.</p>'; return; }

$db = Database::getInstance()->getConnection();

// Check if user is admin (role = 'admin')
$stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$adminViewerId]);
$adminUser = $stmt->fetch();

$isAdmin = $adminUser && $adminUser['role'] === 'admin';

if (!$isAdmin) {
    echo '<div style="padding:40px;text-align:center;font-family:sans-serif"><h2>Access Denied</h2><p>You do not have permission to access this page.</p></div>';
    return;
}

$adminSecurity = new Security();
$adminCsrfToken = $adminSecurity->generateCSRFToken();

// Handle resolve/dismiss action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['resolve_report', 'dismiss_report'])) {
    $reportId = (int) ($_POST['report_id'] ?? 0);
    $adminNote = trim($_POST['admin_note'] ?? '');
    $action = $_POST['action'];
    $status = $action === 'resolve_report' ? 'resolved' : 'dismissed';

    $stmt = $db->prepare("SELECT pr.*, p.user_id AS post_author_id FROM post_reports pr JOIN posts p ON p.id = pr.post_id WHERE pr.id = ?");
    $stmt->execute([$reportId]);
    $report = $stmt->fetch();

    if ($report) {
        $db->prepare("UPDATE post_reports SET status = ?, admin_note = ?, resolved_by = ?, resolved_at = NOW() WHERE id = ?")
           ->execute([$status, $adminNote, $adminViewerId, $reportId]);

        // Notify post author
        $authorMsg = $status === 'resolved'
            ? 'A report on your post has been reviewed. Our team found it violated guidelines and action was taken.'
            : 'A report on your post has been reviewed. Our team determined it does not violate guidelines and your post remains visible.';
        createNotification($db, (int)$report['post_author_id'], $adminViewerId, 'report_resolved', $authorMsg, (int)$report['post_id']);

        // Notify reporter
        $reporterMsg = $status === 'resolved'
            ? 'Thank you for your report. Our team has reviewed it and taken appropriate action on the post.'
            : 'Thank you for your report. Our team has reviewed it and determined the post does not violate guidelines.';
        createNotification($db, (int)$report['user_id'], $adminViewerId, 'report_resolved', $reporterMsg, (int)$report['post_id']);

        echo '<div class="admin-alert success">Report ' . $status . ' successfully. Notifications sent.</div>';
    }
}

// Fetch reports
$reportTab = $_GET['tab'] ?? 'pending';
$validTabs = ['pending', 'resolved', 'dismissed', 'all'];
if (!in_array($reportTab, $validTabs)) $reportTab = 'pending';

$statusFilter = $reportTab === 'all' ? '' : "WHERE pr.status = '$reportTab'";
$sql = "SELECT pr.*, p.content AS post_content, p.image_path,
               u.username, u.full_name AS reporter_name,
               pu.full_name AS author_name, pu.username AS author_username,
               a.full_name AS admin_name
        FROM post_reports pr
        JOIN posts p ON p.id = pr.post_id
        JOIN users u ON u.id = pr.user_id
        JOIN users pu ON pu.id = p.user_id
        LEFT JOIN users a ON a.id = pr.resolved_by
        $statusFilter
        ORDER BY pr.created_at DESC
        LIMIT 100";
$reports = $db->query($sql)->fetchAll();

$counts = [
    'pending' => $db->query("SELECT COUNT(*) FROM post_reports WHERE status='pending'")->fetchColumn(),
    'resolved' => $db->query("SELECT COUNT(*) FROM post_reports WHERE status='resolved'")->fetchColumn(),
    'dismissed' => $db->query("SELECT COUNT(*) FROM post_reports WHERE status='dismissed'")->fetchColumn(),
];
$counts['all'] = $counts['pending'] + $counts['resolved'] + $counts['dismissed'];
?>
<link rel="stylesheet" href="assets/css/admin.css?v=<?= time() ?>">
<div class="admin-page" data-admin-page data-csrf-token="<?= htmlspecialchars($adminCsrfToken) ?>">
    <div class="admin-container">
        <!-- Header -->
        <div class="admin-header">
            <div>
                <h1><i class="fas fa-shield-alt" style="color:#1877f2"></i> Report Management</h1>
                <p class="admin-header-sub">Review and resolve post reports from the community</p>
            </div>
            <a href="index.php?page=community" class="admin-back-link"><i class="fas fa-arrow-left"></i> Back to Community</a>
        </div>

        <!-- Stats Cards -->
        <div class="admin-stats-row">
            <div class="admin-stat-card pending">
                <div class="admin-stat-icon"><i class="fas fa-clock"></i></div>
                <div class="admin-stat-info">
                    <span class="admin-stat-num"><?= (int)$counts['pending'] ?></span>
                    <span class="admin-stat-label">Pending</span>
                </div>
            </div>
            <div class="admin-stat-card resolved">
                <div class="admin-stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="admin-stat-info">
                    <span class="admin-stat-num"><?= (int)$counts['resolved'] ?></span>
                    <span class="admin-stat-label">Resolved</span>
                </div>
            </div>
            <div class="admin-stat-card dismissed">
                <div class="admin-stat-icon"><i class="fas fa-times-circle"></i></div>
                <div class="admin-stat-info">
                    <span class="admin-stat-num"><?= (int)$counts['dismissed'] ?></span>
                    <span class="admin-stat-label">Dismissed</span>
                </div>
            </div>
            <div class="admin-stat-card total">
                <div class="admin-stat-icon"><i class="fas fa-flag"></i></div>
                <div class="admin-stat-info">
                    <span class="admin-stat-num"><?= (int)$counts['all'] ?></span>
                    <span class="admin-stat-label">Total</span>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="admin-tabs">
            <?php foreach (['pending' => 'Pending', 'resolved' => 'Resolved', 'dismissed' => 'Dismissed', 'all' => 'All'] as $key => $label): ?>
            <a href="?page=admin&tab=<?= $key ?>" class="admin-tab <?= $reportTab === $key ? 'active' : '' ?>">
                <?= $label ?>
                <span class="admin-tab-count"><?= (int)$counts[$key] ?></span>
            </a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($reports)): ?>
        <div class="admin-empty">
            <i class="fas fa-inbox"></i>
            <h3>No reports found</h3>
            <p>All clear! There are no <?= $reportTab === 'all' ? '' : htmlspecialchars($reportTab) ?> reports to display.</p>
        </div>
        <?php else: ?>
        <div class="admin-report-list">
            <?php foreach ($reports as $report): ?>
            <div class="admin-report-card">
                <div class="admin-report-top">
                    <div class="admin-report-meta">
                        <span class="admin-status-badge status-<?= $report['status'] ?>">
                            <?php if ($report['status'] === 'pending'): ?><i class="fas fa-clock"></i>
                            <?php elseif ($report['status'] === 'resolved'): ?><i class="fas fa-check-circle"></i>
                            <?php else: ?><i class="fas fa-times-circle"></i>
                            <?php endif; ?>
                            <?= ucfirst($report['status']) ?>
                        </span>
                        <span class="admin-report-date"><i class="far fa-calendar-alt"></i> <?= date('M j, Y \a\t g:i A', strtotime($report['created_at'])) ?></span>
                    </div>
                    <div class="admin-report-id">#<?= (int)$report['id'] ?></div>
                </div>
                <div class="admin-report-body">
                    <div class="admin-report-post">
                        <div class="admin-report-post-label">Reported Post</div>
                        <div class="admin-post-preview">
                            <?php if ($report['image_path']): ?>
                            <img src="assets/posts/<?= htmlspecialchars($report['image_path']) ?>" alt="" class="admin-post-thumb">
                            <?php endif; ?>
                            <div class="admin-post-info">
                                <span class="admin-post-snippet"><?= htmlspecialchars(mb_substr(strip_tags($report['post_content'] ?? ''), 0, 120)) ?: '<em>No text content</em>' ?></span>
                                <a href="index.php?page=community&post=<?= (int)$report['post_id'] ?>" class="admin-post-link" target="_blank"><i class="fas fa-external-link-alt"></i> View Post</a>
                            </div>
                        </div>
                    </div>
                    <div class="admin-report-details">
                        <div class="admin-report-detail-item">
                            <span class="admin-detail-label">Author</span>
                            <span class="admin-detail-value"><?= htmlspecialchars($report['author_name'] ?: $report['author_username']) ?></span>
                        </div>
                        <div class="admin-report-detail-item">
                            <span class="admin-detail-label">Reported by</span>
                            <span class="admin-detail-value"><?= htmlspecialchars($report['reporter_name'] ?: $report['username']) ?></span>
                        </div>
                        <div class="admin-report-detail-item">
                            <span class="admin-detail-label">Reason</span>
                            <span class="admin-reason-badge"><?= htmlspecialchars($report['reason'] ?: 'No reason') ?></span>
                        </div>
                        <?php if ($report['status'] !== 'pending'): ?>
                        <div class="admin-report-detail-item full-width">
                            <span class="admin-detail-label">Handled by</span>
                            <span class="admin-detail-value"><?= htmlspecialchars($report['admin_name'] ?? 'Unknown') ?> <span style="color:#65676b;font-weight:400;font-size:12px">on <?= date('M j, Y \a\t g:i A', strtotime($report['resolved_at'])) ?></span></span>
                        </div>
                        <?php if (!empty($report['admin_note'])): ?>
                        <div class="admin-report-detail-item full-width">
                            <span class="admin-detail-label">Admin Note</span>
                            <span class="admin-detail-value admin-note-text"><?= htmlspecialchars($report['admin_note']) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($report['status'] === 'pending'): ?>
                <div class="admin-report-actions">
                    <button class="admin-action-btn resolve" onclick="openResolveModal(<?= $report['id'] ?>, 'resolve')">
                        <i class="fas fa-check-circle"></i> Resolve
                        <span class="admin-action-hint">Post violates guidelines</span>
                    </button>
                    <button class="admin-action-btn dismiss" onclick="openResolveModal(<?= $report['id'] ?>, 'dismiss')">
                        <i class="fas fa-times-circle"></i> Dismiss
                        <span class="admin-action-hint">Post is fine</span>
                    </button>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Resolve/Dismiss Modal -->
<div class="admin-modal-overlay" id="adminModalOverlay">
    <div class="admin-modal">
        <div class="admin-modal-header">
            <div class="admin-modal-header-icon" id="adminModalIcon"><i class="fas fa-check-circle"></i></div>
            <h2 id="adminModalTitle">Resolve Report</h2>
            <p class="admin-modal-sub" id="adminModalSub">This will mark the report as resolved and notify both the post author and reporter.</p>
            <button type="button" class="admin-modal-close" onclick="closeAdminModal()"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" class="admin-modal-body">
            <input type="hidden" name="report_id" id="adminReportId">
            <input type="hidden" name="action" id="adminReportAction">
            <div class="admin-modal-field">
                <label for="adminNote"><i class="fas fa-pen"></i> Admin Note</label>
                <textarea name="admin_note" id="adminNote" rows="3" placeholder="Add a brief note about this decision (shown to users)"></textarea>
            </div>
            <div class="admin-modal-actions">
                <button type="button" class="admin-modal-btn secondary" onclick="closeAdminModal()"><i class="fas fa-arrow-left"></i> Cancel</button>
                <button type="submit" class="admin-modal-btn primary" id="adminConfirmBtn"><i class="fas fa-check"></i> Confirm</button>
            </div>
        </form>
    </div>
</div>

<script>
function openResolveModal(reportId, action) {
    const isResolve = action === 'resolve';
    document.getElementById('adminReportId').value = reportId;
    document.getElementById('adminReportAction').value = action + '_report';
    document.getElementById('adminModalTitle').textContent = isResolve ? 'Resolve Report' : 'Dismiss Report';
    document.getElementById('adminModalSub').textContent = isResolve
        ? 'Confirm that this post violates guidelines. The author and reporter will be notified.'
        : 'Confirm that this post does not violate guidelines. The author and reporter will be notified.';
    document.getElementById('adminModalIcon').innerHTML = isResolve
        ? '<i class="fas fa-check-circle" style="color:#10b981"></i>'
        : '<i class="fas fa-times-circle" style="color:#ef4444"></i>';
    const confirmBtn = document.getElementById('adminConfirmBtn');
    confirmBtn.innerHTML = isResolve ? '<i class="fas fa-check-circle"></i> Resolve' : '<i class="fas fa-times-circle"></i> Dismiss';
    confirmBtn.className = 'admin-modal-btn ' + (isResolve ? 'resolve' : 'dismiss');
    document.getElementById('adminModalOverlay').classList.add('active');
    document.body.style.overflow = 'hidden';
    document.getElementById('adminNote').value = '';
}

function closeAdminModal() {
    document.getElementById('adminModalOverlay').classList.remove('active');
    document.body.style.overflow = '';
}

document.getElementById('adminModalOverlay')?.addEventListener('click', (e) => {
    if (e.target === e.currentTarget) closeAdminModal();
});
</script>
