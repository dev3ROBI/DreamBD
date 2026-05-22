<?php
$pageTitle = 'Post Reports';
$pageHeading = 'Post Reports';
$currentPage = 'post-reports';
$breadcrumbs = [
    ['label' => 'Post Reports']
];

require_once __DIR__ . '/layout/header.php';

$db = Database::getInstance()->getConnection();
$messages = $messages ?? [];
$errors = $errors ?? [];

// Handle resolve/dismiss ALL pending reports for a post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['resolve_report', 'dismiss_report'])) {
    $postId = (int) ($_POST['post_id'] ?? 0);
    $adminNote = trim($_POST['admin_note'] ?? '');
    $action = $_POST['action'];
    $status = $action === 'resolve_report' ? 'resolved' : 'dismissed';

    $stmt = $db->prepare("SELECT id, user_id FROM posts WHERE id = ?");
    $stmt->execute([$postId]);
    $post = $stmt->fetch();

    if ($post) {
        $upStmt = $db->prepare("UPDATE post_reports SET status = ?, admin_note = ?, resolved_by = ?, resolved_at = NOW() WHERE post_id = ? AND status = 'pending'");
        $upStmt->execute([$status, $adminNote, $userId, $postId]);
        $affected = $upStmt->rowCount();

        $authorMsg = $status === 'resolved'
            ? 'A report on your post has been reviewed. Our team found it violated guidelines and action was taken.'
            : 'A report on your post has been reviewed. Our team determined it does not violate guidelines and your post remains visible.';
        createNotification($db, (int)$post['user_id'], $userId, 'report_resolved', $authorMsg . (!empty($adminNote) ? " Admin note: $adminNote" : ''), (int)$postId);

        $reporterStmt = $db->prepare("SELECT DISTINCT user_id FROM post_reports WHERE post_id = ?");
        $reporterStmt->execute([$postId]);
        $reporterIds = $reporterStmt->fetchAll(PDO::FETCH_COLUMN);

        $reporterMsg = $status === 'resolved'
            ? '✅ Report Resolved: Thank you for your report. Our team reviewed it and took appropriate action on the post.'
            : 'ℹ️ Report Dismissed: Thank you for your report. Our team reviewed it and determined the post does not violate guidelines.';
        if (!empty($adminNote)) {
            $reporterMsg .= "\n\nAdmin note: $adminNote";
        }

        foreach ($reporterIds as $rid) {
            createNotification($db, (int)$rid, $userId, 'report_resolved', $reporterMsg, (int)$postId);
        }

        $messages[] = "Post resolved: $affected report(s) updated, " . count($reporterIds) . " reporter(s) notified.";
    } else {
        $errors[] = 'Post not found.';
    }
}

// Stats — distinct posts
$counts = [
    'pending'   => (int) $db->query("SELECT COUNT(DISTINCT post_id) FROM post_reports WHERE status='pending'")->fetchColumn(),
    'resolved'  => (int) $db->query("SELECT COUNT(DISTINCT post_id) FROM post_reports WHERE status='resolved'")->fetchColumn(),
    'dismissed' => (int) $db->query("SELECT COUNT(DISTINCT post_id) FROM post_reports WHERE status='dismissed'")->fetchColumn(),
];
$counts['all'] = (int) $db->query("SELECT COUNT(DISTINCT post_id) FROM post_reports")->fetchColumn();

// Tab
$reportTab = $_GET['tab'] ?? 'pending';
$validTabs = ['pending', 'resolved', 'dismissed', 'all'];
if (!in_array($reportTab, $validTabs)) $reportTab = 'pending';
$statusFilter = $reportTab === 'all' ? '' : "WHERE pr.status = '$reportTab'";

// Grouped query — posts with report counts, reporter list, reasons
$sql = "SELECT pr.post_id,
               p.content, p.image_path, p.user_id AS post_author_id,
               pu.full_name AS author_name, pu.username AS author_username,
               COUNT(pr.id) AS report_count,
               GROUP_CONCAT(DISTINCT u.full_name ORDER BY u.full_name SEPARATOR '||') AS reporter_name_list,
               GROUP_CONCAT(DISTINCT u.id ORDER BY u.full_name SEPARATOR '||') AS reporter_id_list,
               GROUP_CONCAT(DISTINCT u.username ORDER BY u.full_name SEPARATOR '||') AS reporter_username_list,
               GROUP_CONCAT(DISTINCT pr.reason ORDER BY pr.id SEPARATOR '||') AS reason_list,
               MAX(pr.created_at) AS latest_report_date
        FROM post_reports pr
        JOIN posts p ON p.id = pr.post_id
        JOIN users pu ON pu.id = p.user_id
        JOIN users u ON u.id = pr.user_id
        $statusFilter
        GROUP BY pr.post_id
        ORDER BY report_count DESC, MAX(pr.created_at) DESC
        LIMIT 50";
$reports = $db->query($sql)->fetchAll();

// Parse reasons and reporter details
foreach ($reports as &$r) {
    $reasons = array_filter(explode('||', $r['reason_list'] ?? ''));
    $reasonCounts = array_count_values($reasons);
    arsort($reasonCounts);
    $r['top_reason'] = key($reasonCounts) ?: 'N/A';
    $r['top_reason_count'] = current($reasonCounts) ?: 0;

    $names = explode('||', $r['reporter_name_list'] ?? '');
    $ids   = explode('||', $r['reporter_id_list'] ?? '');
    $unames = explode('||', $r['reporter_username_list'] ?? '');
    $r['reporters'] = [];
    foreach ($names as $i => $n) {
        if (!empty($n)) {
            $r['reporters'][] = ['name' => $n, 'id' => $ids[$i] ?? 0, 'username' => $unames[$i] ?? ''];
        }
    }
}
unset($r);

// Admin info for non-pending posts
$adminInfo = [];
if ($reportTab !== 'pending' && !empty($reports)) {
    $postIds = array_column($reports, 'post_id');
    $placeholders = implode(',', array_fill(0, count($postIds), '?'));
    $statusClause = $reportTab === 'all' ? "pr.status IN ('resolved','dismissed')" : "pr.status = '$reportTab'";
    $adminSql = "SELECT pr.post_id, u.full_name AS admin_name, pr.admin_note, pr.resolved_at, pr.status
                 FROM post_reports pr
                 JOIN users u ON u.id = pr.resolved_by
                 WHERE pr.post_id IN ($placeholders) AND $statusClause AND pr.resolved_by IS NOT NULL
                 GROUP BY pr.post_id
                 ORDER BY pr.resolved_at DESC";
    $adminStmt = $db->prepare($adminSql);
    $adminStmt->execute($postIds);
    while ($row = $adminStmt->fetch()) {
        $adminInfo[$row['post_id']] = $row;
    }
}
?>
<!-- ===== STATS ROW ===== -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-yellow-50 to-yellow-100 dark:from-yellow-900/20 dark:to-yellow-800/20 p-5 border border-yellow-200 dark:border-yellow-700/40 shadow-sm group hover:shadow-md transition-all duration-300">
        <div class="absolute top-0 right-0 w-24 h-24 -mr-6 -mt-6 rounded-full bg-yellow-200/30 dark:bg-yellow-500/10 group-hover:scale-110 transition-transform duration-500"></div>
        <div class="flex items-center gap-4 relative z-10">
            <div class="w-12 h-12 rounded-xl bg-yellow-500/20 flex items-center justify-center text-yellow-600 dark:text-yellow-400 text-lg"><i class="fas fa-clock"></i></div>
            <div>
                <div class="text-2xl font-extrabold text-gray-900 dark:text-white tracking-tight"><?= $counts['pending'] ?></div>
                <div class="text-xs font-semibold text-yellow-600 dark:text-yellow-400 uppercase tracking-wide">Pending</div>
            </div>
        </div>
    </div>
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-emerald-50 to-emerald-100 dark:from-emerald-900/20 dark:to-emerald-800/20 p-5 border border-emerald-200 dark:border-emerald-700/40 shadow-sm group hover:shadow-md transition-all duration-300">
        <div class="absolute top-0 right-0 w-24 h-24 -mr-6 -mt-6 rounded-full bg-emerald-200/30 dark:bg-emerald-500/10 group-hover:scale-110 transition-transform duration-500"></div>
        <div class="flex items-center gap-4 relative z-10">
            <div class="w-12 h-12 rounded-xl bg-emerald-500/20 flex items-center justify-center text-emerald-600 dark:text-emerald-400 text-lg"><i class="fas fa-check-circle"></i></div>
            <div>
                <div class="text-2xl font-extrabold text-gray-900 dark:text-white tracking-tight"><?= $counts['resolved'] ?></div>
                <div class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 uppercase tracking-wide">Resolved</div>
            </div>
        </div>
    </div>
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-700/50 p-5 border border-gray-200 dark:border-gray-600/40 shadow-sm group hover:shadow-md transition-all duration-300">
        <div class="absolute top-0 right-0 w-24 h-24 -mr-6 -mt-6 rounded-full bg-gray-300/30 dark:bg-gray-500/10 group-hover:scale-110 transition-transform duration-500"></div>
        <div class="flex items-center gap-4 relative z-10">
            <div class="w-12 h-12 rounded-xl bg-gray-500/20 flex items-center justify-center text-gray-600 dark:text-gray-400 text-lg"><i class="fas fa-times-circle"></i></div>
            <div>
                <div class="text-2xl font-extrabold text-gray-900 dark:text-white tracking-tight"><?= $counts['dismissed'] ?></div>
                <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Dismissed</div>
            </div>
        </div>
    </div>
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-800/20 p-5 border border-blue-200 dark:border-blue-700/40 shadow-sm group hover:shadow-md transition-all duration-300">
        <div class="absolute top-0 right-0 w-24 h-24 -mr-6 -mt-6 rounded-full bg-blue-200/30 dark:bg-blue-500/10 group-hover:scale-110 transition-transform duration-500"></div>
        <div class="flex items-center gap-4 relative z-10">
            <div class="w-12 h-12 rounded-xl bg-blue-500/20 flex items-center justify-center text-blue-600 dark:text-blue-400 text-lg"><i class="fas fa-flag"></i></div>
            <div>
                <div class="text-2xl font-extrabold text-gray-900 dark:text-white tracking-tight"><?= $counts['all'] ?></div>
                <div class="text-xs font-semibold text-blue-600 dark:text-blue-400 uppercase tracking-wide">Total Posts</div>
            </div>
        </div>
    </div>
</div>

<!-- ===== TABS ===== -->
<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-1.5 mb-6 flex gap-1 overflow-x-auto">
    <?php $tabLabels = ['pending' => 'Pending', 'resolved' => 'Resolved', 'dismissed' => 'Dismissed', 'all' => 'All Reports']; ?>
    <?php foreach ($tabLabels as $key => $label): ?>
    <a href="?tab=<?= $key ?>"
       class="flex-1 text-center px-4 py-2.5 rounded-xl text-sm font-bold transition-all duration-200 whitespace-nowrap <?= $reportTab === $key ? 'bg-blue-600 text-white shadow-lg shadow-blue-600/20' : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700/50' ?>">
        <?= $label ?>
        <span class="ml-1.5 px-2 py-0.5 rounded-full text-xs <?= $reportTab === $key ? 'bg-white/20 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-500 dark:text-gray-400' ?>"><?= (int)$counts[$key] ?></span>
    </a>
    <?php endforeach; ?>
</div>

<!-- ===== POST CARDS ===== -->
<?php if (empty($reports)): ?>
<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-16 text-center">
    <div class="w-20 h-20 rounded-full bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-700 dark:to-gray-600 flex items-center justify-center mx-auto mb-5">
        <i class="fas fa-shield-alt text-3xl text-gray-400"></i>
    </div>
    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">All clear!</h3>
    <p class="text-sm text-gray-500 dark:text-gray-400 max-w-sm mx-auto">
        <?= $reportTab === 'all' ? 'No reports have been submitted yet.' : 'No ' . htmlspecialchars($reportTab) . ' reports to review.' ?>
    </p>
</div>
<?php else: ?>
<div class="space-y-5">
    <?php foreach ($reports as $report): ?>
    <?php
        $isPending = $reportTab === 'pending';
        $adminRow = $adminInfo[$report['post_id']] ?? null;
        $postImage = $report['image_path'] ? '../assets/posts/' . htmlspecialchars($report['image_path']) : null;
    ?>
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden transition-all duration-300 hover:shadow-lg hover:border-gray-200 dark:hover:border-gray-600 group/report">
        <!-- HEADER -->
        <div class="flex items-center justify-between px-6 py-3.5 bg-gradient-to-r from-gray-50 to-transparent dark:from-gray-800/80 border-b border-gray-100 dark:border-gray-700">
            <div class="flex items-center gap-3">
                <?php if ($isPending): ?>
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-gradient-to-r from-yellow-100 to-yellow-200 text-yellow-700 dark:from-yellow-900/40 dark:to-yellow-800/40 dark:text-yellow-300 shadow-sm">
                    <i class="fas fa-exclamation-triangle text-yellow-500"></i> <?= (int)$report['report_count'] ?> pending
                </span>
                <?php else: ?>
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold shadow-sm
                    <?= $reportTab === 'resolved' ? 'bg-gradient-to-r from-emerald-100 to-emerald-200 text-emerald-700 dark:from-emerald-900/40 dark:to-emerald-800/40 dark:text-emerald-300' : '' ?>
                    <?= $reportTab === 'dismissed' ? 'bg-gradient-to-r from-gray-200 to-gray-300 text-gray-600 dark:from-gray-700 dark:to-gray-600 dark:text-gray-300' : '' ?>
                    <?= $reportTab === 'all' ? ($adminRow && $adminRow['status'] === 'resolved' ? 'bg-gradient-to-r from-emerald-100 to-emerald-200 text-emerald-700 dark:from-emerald-900/40 dark:to-emerald-800/40 dark:text-emerald-300' : 'bg-gradient-to-r from-gray-200 to-gray-300 text-gray-600 dark:from-gray-700 dark:to-gray-600 dark:text-gray-300') : '' ?>">
                    <?php if (($reportTab === 'resolved') || ($reportTab === 'all' && $adminRow && $adminRow['status'] === 'resolved')): ?><i class="fas fa-check-circle"></i> Resolved
                    <?php else: ?><i class="fas fa-times-circle"></i> Dismissed
                    <?php endif; ?>
                </span>
                <span class="text-xs font-semibold bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 px-2.5 py-1 rounded-lg">
                    <i class="fas fa-flag mr-1"></i><?= (int)$report['report_count'] ?> report<?= $report['report_count'] != 1 ? 's' : '' ?>
                </span>
                <?php endif; ?>
                <span class="text-xs text-gray-400 dark:text-gray-500"><i class="far fa-calendar-alt mr-1"></i><?= date('M j, Y', strtotime($report['latest_report_date'])) ?></span>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-xs font-mono text-gray-400 bg-gray-100 dark:bg-gray-700 px-2.5 py-1 rounded-lg">#<?= (int)$report['post_id'] ?></span>
            </div>
        </div>

        <!-- BODY -->
        <div class="p-6">
            <div class="flex flex-col lg:flex-row gap-6">
                <!-- Post preview -->
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-2">
                        <i class="fas fa-file-alt text-xs text-gray-400"></i>
                        <span class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Reported Post</span>
                    </div>
                    <div class="flex gap-4 p-4 rounded-xl bg-gray-50 dark:bg-gray-750 border border-gray-100 dark:border-gray-700">
                        <?php if ($postImage): ?>
                        <div class="w-20 h-20 rounded-xl overflow-hidden flex-shrink-0 bg-gray-100 border border-gray-200 dark:border-gray-600 shadow-sm">
                            <img src="<?= $postImage ?>" alt="" class="w-full h-full object-cover">
                        </div>
                        <?php endif; ?>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-800 dark:text-gray-200 leading-relaxed line-clamp-2"><?= htmlspecialchars(mb_substr(strip_tags($report['content'] ?? ''), 0, 200)) ?: '<span class="text-gray-400 italic">No text content</span>' ?></p>
                            <div class="flex items-center gap-3 mt-2">
                                <span class="text-xs text-gray-500 dark:text-gray-400"><i class="fas fa-user mr-1"></i><?= htmlspecialchars($report['author_name'] ?: $report['author_username']) ?></span>
                                <a href="../index.php?page=community&post=<?= (int)$report['post_id'] ?>" target="_blank" class="inline-flex items-center gap-1 text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline">
                                    <i class="fas fa-external-link-alt"></i> View
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Details -->
                <div class="lg:w-72 space-y-3">
                    <div>
                        <div class="flex items-center gap-1.5 mb-1.5">
                            <i class="fas fa-users text-xs text-gray-400"></i>
                            <span class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Reporters (<?= count($report['reporters']) ?>)</span>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            <?php foreach (array_slice($report['reporters'], 0, 5) as $reporter): ?>
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-red-50 dark:bg-red-900/15 text-red-600 dark:text-red-400 rounded-lg text-xs font-medium border border-red-100 dark:border-red-800/30">
                                <i class="fas fa-flag text-[10px]"></i> <?= htmlspecialchars(explode(' ', $reporter['name'])[0]) ?>
                            </span>
                            <?php endforeach; ?>
                            <?php if (count($report['reporters']) > 5): ?>
                            <span class="inline-flex items-center px-2.5 py-1 bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 rounded-lg text-xs font-medium">+<?= count($report['reporters']) - 5 ?> more</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 pt-1">
                        <div>
                            <span class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 block mb-1">Top reason</span>
                            <span class="inline-flex items-center gap-1 px-3 py-1.5 bg-orange-50 dark:bg-orange-900/20 text-orange-700 dark:text-orange-400 rounded-lg text-xs font-bold border border-orange-200 dark:border-orange-800/30">
                                <i class="fas fa-tag text-[10px]"></i> <?= htmlspecialchars($report['top_reason']) ?>
                                <?php if ((int)$report['top_reason_count'] > 1): ?><span class="ml-1 px-1.5 py-0.5 rounded-full bg-orange-200/60 dark:bg-orange-800/40 text-[10px]">x<?= (int)$report['top_reason_count'] ?></span><?php endif; ?>
                            </span>
                        </div>
                    </div>

                    <?php if (!$isPending && $adminRow): ?>
                    <div class="pt-2 border-t border-gray-100 dark:border-gray-700">
                        <span class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 block mb-1.5">Handled by</span>
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-full bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center text-white text-[10px] font-bold shadow-sm">
                                <?= strtoupper(substr($adminRow['admin_name'] ?? 'A', 0, 1)) ?>
                            </div>
                            <div>
                                <span class="text-sm font-semibold text-gray-800 dark:text-gray-200"><?= htmlspecialchars($adminRow['admin_name'] ?? 'Unknown') ?></span>
                                <span class="text-xs text-gray-400 ml-1"><?= $adminRow['resolved_at'] ? date('M j, g:i A', strtotime($adminRow['resolved_at'])) : '' ?></span>
                            </div>
                        </div>
                        <?php if (!empty($adminRow['admin_note'])): ?>
                        <div class="mt-2 text-xs text-gray-500 dark:text-gray-400 italic bg-gray-50 dark:bg-gray-700/30 p-2.5 rounded-lg border-l-3 border-blue-400 pl-3">
                            <i class="fas fa-quote-left text-blue-400 mr-1 text-[10px]"></i><?= htmlspecialchars($adminRow['admin_note']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ACTIONS -->
        <?php if ($isPending): ?>
        <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-transparent dark:from-gray-800/80 border-t border-gray-100 dark:border-gray-700 flex flex-col sm:flex-row gap-3">
            <button onclick="openResolveModal(<?= $report['post_id'] ?>, 'resolve', <?= $report['report_count'] ?>, '<?= htmlspecialchars($report['top_reason'], ENT_QUOTES) ?>')"
                    class="flex-1 flex items-center justify-center gap-2.5 px-5 py-3 rounded-xl bg-gradient-to-r from-emerald-50 to-green-50 dark:from-emerald-900/20 dark:to-green-900/20 border-2 border-emerald-200 dark:border-emerald-700/50 text-emerald-700 dark:text-emerald-300 font-bold text-sm transition-all duration-200 hover:from-emerald-100 hover:to-green-100 dark:hover:from-emerald-800/40 dark:hover:to-green-800/40 hover:border-emerald-400 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-emerald-200/30 active:translate-y-0">
                <i class="fas fa-check-circle text-lg"></i>
                <span>Resolve All</span>
                <span class="text-xs font-normal opacity-70 bg-white/50 dark:bg-black/20 px-2 py-0.5 rounded-full"><?= (int)$report['report_count'] ?> report<?= $report['report_count'] != 1 ? 's' : '' ?></span>
            </button>
            <button onclick="openResolveModal(<?= $report['post_id'] ?>, 'dismiss', <?= $report['report_count'] ?>, '<?= htmlspecialchars($report['top_reason'], ENT_QUOTES) ?>')"
                    class="flex-1 flex items-center justify-center gap-2.5 px-5 py-3 rounded-xl bg-gradient-to-r from-red-50 to-rose-50 dark:from-red-900/20 dark:to-rose-900/20 border-2 border-red-200 dark:border-red-700/50 text-red-700 dark:text-red-300 font-bold text-sm transition-all duration-200 hover:from-red-100 hover:to-rose-100 dark:hover:from-red-800/40 dark:hover:to-rose-800/40 hover:border-red-400 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-red-200/30 active:translate-y-0">
                <i class="fas fa-times-circle text-lg"></i>
                <span>Dismiss All</span>
                <span class="text-xs font-normal opacity-70 bg-white/50 dark:bg-black/20 px-2 py-0.5 rounded-full"><?= (int)$report['report_count'] ?> report<?= $report['report_count'] != 1 ? 's' : '' ?></span>
            </button>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ===== RESOLVE/DISMISS MODAL ===== -->
<div class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden items-center justify-center" id="adminModalOverlay">
    <div class="bg-white dark:bg-gray-800 rounded-2xl w-full max-w-lg mx-4 shadow-2xl" style="animation:modalPop 0.3s cubic-bezier(0.34,1.56,0.64,1)">
        <div class="relative p-6 text-center border-b border-gray-100 dark:border-gray-700">
            <div id="adminModalIcon" class="text-5xl mb-3"><i class="fas fa-check-circle" style="color:#10b981"></i></div>
            <h3 class="text-xl font-bold text-gray-900 dark:text-white" id="adminModalTitle">Resolve All Reports</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-2 max-w-sm mx-auto" id="adminModalSub">This will mark all pending reports for this post as resolved and notify all reporters.</p>
            <div class="mt-3 inline-flex items-center gap-2 px-4 py-2 bg-blue-50 dark:bg-blue-900/20 rounded-xl border border-blue-100 dark:border-blue-800/30">
                <i class="fas fa-info-circle text-blue-500 text-sm"></i>
                <span class="text-xs font-semibold text-blue-600 dark:text-blue-400" id="adminModalBadge">3 reporters will be notified</span>
            </div>
            <button type="button" onclick="closeAdminModal()" class="absolute top-4 right-4 w-8 h-8 rounded-full bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 flex items-center justify-center text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 transition-all">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" class="p-6">
            <input type="hidden" name="post_id" id="adminPostId">
            <input type="hidden" name="action" id="adminReportAction">
            <div class="mb-5">
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2.5">
                    <i class="fas fa-pen mr-1.5 text-gray-400"></i> Admin Note <span class="text-gray-400 font-normal">(optional)</span>
                </label>
                <textarea name="admin_note" id="adminNote" rows="3" class="w-full px-4 py-3.5 rounded-xl border-2 border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50 text-gray-900 dark:text-gray-100 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none resize-none transition-all placeholder-gray-400" placeholder="Write a brief note explaining your decision..."></textarea>
                <p class="text-xs text-gray-400 mt-1.5 ml-1"><i class="far fa-lightbulb mr-1"></i> This note will be sent to all reporters and the post author.</p>
            </div>
            <div class="flex gap-3">
                <button type="button" onclick="closeAdminModal()" class="flex-1 px-4 py-3.5 rounded-xl bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 font-bold text-sm transition-all hover:-translate-y-0.5">
                    <i class="fas fa-chevron-left mr-1"></i> Cancel
                </button>
                <button type="submit" class="flex-1 px-4 py-3.5 rounded-xl text-white font-bold text-sm transition-all hover:-translate-y-0.5 shadow-lg flex items-center justify-center gap-2" id="adminConfirmBtn">
                    Confirm
                </button>
            </div>
        </form>
    </div>
</div>

<style>
@keyframes modalPop {
    0% { opacity: 0; transform: scale(0.92) translateY(12px); }
    100% { opacity: 1; transform: scale(1) translateY(0); }
}
</style>

<script>
function openResolveModal(postId, action, reportCount, topReason) {
    const isResolve = action === 'resolve';
    document.getElementById('adminPostId').value = postId;
    document.getElementById('adminReportAction').value = action + '_report';
    document.getElementById('adminModalTitle').textContent = isResolve ? 'Resolve All Reports' : 'Dismiss All Reports';
    document.getElementById('adminModalSub').textContent = isResolve
        ? 'This post has been reported ' + reportCount + ' time(s) for "' + topReason + '". Mark all reports as resolved and notify everyone involved.'
        : 'This post has been reported ' + reportCount + ' time(s) for "' + topReason + '". Dismiss all reports and notify everyone involved.';
    document.getElementById('adminModalBadge').innerHTML = '<i class="fas fa-users mr-1"></i> Sends notification to post author + ' + reportCount + ' reporter(s)';
    document.getElementById('adminModalIcon').innerHTML = isResolve
        ? '<i class="fas fa-shield-alt" style="color:#10b981"></i>'
        : '<i class="fas fa-shield-alt" style="color:#ef4444"></i>';
    const btn = document.getElementById('adminConfirmBtn');
    btn.innerHTML = isResolve
        ? '<i class="fas fa-check-circle"></i> Resolve All (' + reportCount + ')'
        : '<i class="fas fa-times-circle"></i> Dismiss All (' + reportCount + ')';
    btn.className = 'flex-1 px-4 py-3.5 rounded-xl text-white font-bold text-sm transition-all hover:-translate-y-0.5 shadow-lg flex items-center justify-center gap-2 ' +
        (isResolve ? 'bg-gradient-to-r from-emerald-500 to-green-600 hover:from-emerald-600 hover:to-green-700 shadow-emerald-500/30' : 'bg-gradient-to-r from-red-500 to-rose-600 hover:from-red-600 hover:to-rose-700 shadow-red-500/30');
    document.getElementById('adminModalOverlay').classList.remove('hidden');
    document.getElementById('adminModalOverlay').classList.add('flex');
    document.getElementById('adminNote').value = '';
    document.body.style.overflow = 'hidden';
}

function closeAdminModal() {
    document.getElementById('adminModalOverlay').classList.add('hidden');
    document.getElementById('adminModalOverlay').classList.remove('flex');
    document.body.style.overflow = '';
}

document.getElementById('adminModalOverlay')?.addEventListener('click', (e) => {
    if (e.target === e.currentTarget) closeAdminModal();
});
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
