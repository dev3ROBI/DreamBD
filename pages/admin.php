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

// Handle admin actions
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (in_array($action, ['resolve_report', 'dismiss_report'])) {
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

    if ($action === 'change_role') {
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        $newRole = $_POST['new_role'] ?? 'user';
        $validRoles = ['user', 'merchant', 'agent', 'admin'];
        if (in_array($newRole, $validRoles) && $targetUserId > 0) {
            // Prevent changing own role or super admin
            if ($targetUserId !== (int)$adminViewerId) {
                $db->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$newRole, $targetUserId]);
                echo '<div class="admin-alert success">User role updated to ' . ucfirst($newRole) . ' successfully.</div>';
            } else {
                echo '<div class="admin-alert error">Cannot change your own role here.</div>';
            }
        }
    }
}

// Determine view
$adminView = $_GET['view'] ?? 'reports';

// Fetch users if in users view
$searchQuery = trim($_GET['search'] ?? '');
$usersList = [];
if ($adminView === 'users') {
    $searchFilter = '';
    if ($searchQuery) {
        $searchFilter = "WHERE username LIKE :q OR full_name LIKE :q OR email LIKE :q OR id = :id_q";
    }
    $uSql = "SELECT id, username, full_name, email, role, registered_at FROM users $searchFilter ORDER BY id DESC LIMIT 50";
    $uStmt = $db->prepare($uSql);
    if ($searchQuery) {
        $uStmt->execute(['q' => "%$searchQuery%", 'id_q' => (int)$searchQuery]);
    } else {
        $uStmt->execute();
    }
    $usersList = $uStmt->fetchAll();
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
<link rel="stylesheet" href="<?php echo dream_asset('assets/css/admin.css'); ?>">
<div class="admin-page" data-admin-page data-csrf-token="<?= htmlspecialchars($adminCsrfToken) ?>">
    <div class="admin-container">
        <!-- Header -->
        <div class="admin-header">
            <div>
                <h1><i class="fas fa-shield-alt" style="color:#1877f2"></i> Admin Control Panel</h1>
                <p class="admin-header-sub">Manage reports, users, and platform settings</p>
            </div>
            <div style="display:flex; gap:10px;">
                <a href="?page=admin&view=reports" class="admin-back-link <?= $adminView === 'reports' ? 'active' : '' ?>" style="<?= $adminView==='reports'?'background:#e0e7ff;color:#4f46e5':'' ?>"><i class="fas fa-flag"></i> Reports</a>
                <a href="?page=admin&view=users" class="admin-back-link <?= $adminView === 'users' ? 'active' : '' ?>" style="<?= $adminView==='users'?'background:#e0e7ff;color:#4f46e5':'' ?>"><i class="fas fa-users"></i> Users</a>
                <a href="index.php?page=community" class="admin-back-link"><i class="fas fa-arrow-left"></i> Exit</a>
            </div>
        </div>

        <?php if ($adminView === 'reports'): ?>

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
        
        <?php elseif ($adminView === 'users'): ?>
        <!-- USER MANAGEMENT VIEW -->
        <div class="admin-search-bar" style="margin-bottom:20px;">
            <form method="GET" style="display:flex; gap:10px;">
                <input type="hidden" name="page" value="admin">
                <input type="hidden" name="view" value="users">
                <input type="text" name="search" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Search by username, email, or ID..." style="flex:1; padding:12px; border:1px solid #e2e8f0; border-radius:10px;">
                <button type="submit" style="padding:12px 20px; background:#4f46e5; color:#fff; border:none; border-radius:10px; cursor:pointer;"><i class="fas fa-search"></i> Search</button>
            </form>
        </div>
        
        <div class="admin-report-list">
            <table style="width:100%; border-collapse:collapse; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 4px 6px -1px rgba(0,0,0,.1);">
                <thead style="background:#f8fafc; border-bottom:2px solid #e2e8f0;">
                    <tr>
                        <th style="padding:12px; text-align:left;">ID</th>
                        <th style="padding:12px; text-align:left;">User</th>
                        <th style="padding:12px; text-align:left;">Email</th>
                        <th style="padding:12px; text-align:left;">Role</th>
                        <th style="padding:12px; text-align:center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($usersList)): ?>
                    <tr><td colspan="5" style="padding:20px; text-align:center;">No users found.</td></tr>
                    <?php else: ?>
                    <?php foreach ($usersList as $u): ?>
                    <tr style="border-bottom:1px solid #e2e8f0;">
                        <td style="padding:12px;">#<?= $u['id'] ?></td>
                        <td style="padding:12px;"><strong><?= htmlspecialchars($u['full_name'] ?: $u['username']) ?></strong><br><small style="color:#64748b">@<?= htmlspecialchars($u['username']) ?></small></td>
                        <td style="padding:12px;"><?= htmlspecialchars($u['email']) ?></td>
                        <td style="padding:12px;">
                            <span style="padding:4px 8px; border-radius:99px; font-size:.75rem; font-weight:700; background:<?= $u['role']==='admin'?'#fee2e2':($u['role']==='merchant'?'#fef3c7':'#e0e7ff') ?>; color:<?= $u['role']==='admin'?'#ef4444':($u['role']==='merchant'?'#d97706':'#4f46e5') ?>;">
                                <?= ucfirst($u['role']) ?>
                            </span>
                        </td>
                        <td style="padding:12px; text-align:center;">
                            <form method="POST" style="display:flex; gap:6px; justify-content:center;">
                                <input type="hidden" name="action" value="change_role">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <select name="new_role" style="padding:6px; border:1px solid #cbd5e1; border-radius:6px;">
                                    <option value="user" <?= $u['role']==='user'?'selected':'' ?>>User</option>
                                    <option value="agent" <?= $u['role']==='agent'?'selected':'' ?>>Agent</option>
                                    <option value="merchant" <?= $u['role']==='merchant'?'selected':'' ?>>Merchant</option>
                                    <option value="admin" <?= $u['role']==='admin'?'selected':'' ?>>Admin</option>
                                </select>
                                <button type="submit" style="padding:6px 12px; background:#10b981; color:#fff; border:none; border-radius:6px; cursor:pointer;" <?= $u['id']==$adminViewerId?'disabled':'' ?>><i class="fas fa-save"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
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
