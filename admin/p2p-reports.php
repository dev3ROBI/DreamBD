<?php
$pageTitle = 'P2P Reports';
$pageHeading = 'P2P Trade Reports';
$currentPage = 'p2p-reports';
$breadcrumbs = [
    ['label' => 'P2P Reports']
];

require_once __DIR__ . '/layout/header.php';

$db = Database::getInstance()->getConnection();
$messages = $messages ?? [];
$errors = $errors ?? [];

// Handle resolve/dismiss actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $security->validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $reportId = (int)($_POST['report_id'] ?? 0);
    if ($reportId > 0) {
        if ($action === 'resolve') {
            $db->prepare("UPDATE p2p_reports SET status = 'resolved', resolved_at = NOW(), resolved_by = ? WHERE id = ? AND status = 'open'")->execute([$userId, $reportId]);
            $messages[] = 'Report #' . $reportId . ' resolved.';
        } elseif ($action === 'dismiss') {
            $db->prepare("UPDATE p2p_reports SET status = 'dismissed', resolved_at = NOW(), resolved_by = ? WHERE id = ? AND status = 'open'")->execute([$userId, $reportId]);
            $messages[] = 'Report #' . $reportId . ' dismissed.';
        }
    }
}

// Fetch reports
$reports = [];
try {
    $stmt = $db->query("SELECT r.*, ru.username AS reporter_name, ru2.username AS reported_name, t.id AS trade_ref, t.status AS trade_status FROM p2p_reports r LEFT JOIN users ru ON ru.id = r.reporter_id LEFT JOIN users ru2 ON ru2.id = r.reported_id LEFT JOIN p2p_trades t ON t.id = r.trade_id ORDER BY r.created_at DESC LIMIT 50");
    $reports = $stmt->fetchAll();
} catch (Throwable $e) {
    $errors[] = 'Could not load reports.';
}
?>

<div class="space-y-6">
    <?php if (!empty($messages)): foreach ($messages as $m): ?>
        <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300 px-4 py-3 rounded-lg text-sm font-medium"><?php echo htmlspecialchars($m); ?></div>
    <?php endforeach; endif; ?>
    <?php if (!empty($errors)): foreach ($errors as $e): ?>
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 px-4 py-3 rounded-lg text-sm font-medium"><?php echo htmlspecialchars($e); ?></div>
    <?php endforeach; endif; ?>

    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-white"><i class="fas fa-flag text-red-500 mr-2"></i> P2P Trade Reports</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Review and resolve user-reported P2P trade issues</p>
            </div>
            <span class="text-xs font-semibold bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 px-3 py-1.5 rounded-full"><?php echo count($reports); ?> total</span>
        </div>

        <?php if (empty($reports)): ?>
        <div class="text-center py-16 px-6">
            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-700 dark:to-gray-600 flex items-center justify-center shadow-inner">
                <i class="fas fa-flag text-2xl text-gray-400 dark:text-gray-500"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">No Reports</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">All P2P trades running smoothly!</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 dark:bg-gray-800/50 text-gray-500 dark:text-gray-400 text-xs font-semibold uppercase tracking-wider">
                        <th class="text-left px-6 py-3">ID</th>
                        <th class="text-left px-6 py-3">Trade</th>
                        <th class="text-left px-6 py-3">Reporter</th>
                        <th class="text-left px-6 py-3">Reported</th>
                        <th class="text-left px-6 py-3">Reason</th>
                        <th class="text-left px-6 py-3">Details</th>
                        <th class="text-left px-6 py-3">Status</th>
                        <th class="text-left px-6 py-3">Date</th>
                        <th class="text-center px-6 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
                    <?php foreach ($reports as $r): 
                        $st = $r['status'];
                        $stClass = $st === 'open' ? 'bg-red-100 text-red-700' : ($st === 'resolved' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600');
                    ?>
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/30 transition-colors">
                        <td class="px-6 py-4 font-bold text-gray-900 dark:text-white">#<?php echo (int)$r['id']; ?></td>
                        <td class="px-6 py-4">
                            <a href="../index.php?page=p2p" class="text-blue-600 dark:text-blue-400 font-medium hover:underline">#<?php echo (int)$r['trade_ref']; ?></a>
                            <div class="text-xs text-gray-400 mt-0.5"><?php echo htmlspecialchars($r['trade_status'] ?? 'N/A'); ?></div>
                        </td>
                        <td class="px-6 py-4 font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($r['reporter_name'] ?? 'Unknown'); ?></td>
                        <td class="px-6 py-4 font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($r['reported_name'] ?? 'Unknown'); ?></td>
                        <td class="px-6 py-4"><span class="inline-block px-2 py-0.5 rounded text-xs font-semibold bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300"><?php echo htmlspecialchars($r['reason']); ?></span></td>
                        <td class="px-6 py-4 max-w-[200px] truncate text-gray-600 dark:text-gray-400" title="<?php echo htmlspecialchars($r['details'] ?? ''); ?>"><?php echo htmlspecialchars($r['details'] ?? '-'); ?></td>
                        <td class="px-6 py-4"><span class="inline-block px-2.5 py-1 rounded-full text-xs font-bold <?php echo $stClass; ?>"><?php echo ucfirst($st); ?></span></td>
                        <td class="px-6 py-4 text-gray-500 dark:text-gray-400 whitespace-nowrap"><?php echo date('M j, g:ia', strtotime($r['created_at'])); ?></td>
                        <td class="px-6 py-4 text-center">
                            <?php if ($st === 'open'): ?>
                            <form method="post" class="inline-flex gap-1">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="report_id" value="<?php echo (int)$r['id']; ?>">
                                <button type="submit" name="action" value="resolve" class="px-3 py-1.5 rounded-lg bg-emerald-500 text-white text-xs font-bold hover:bg-emerald-600 transition-colors" onclick="return confirm('Resolve this report?')"><i class="fas fa-check"></i> Resolve</button>
                                <button type="submit" name="action" value="dismiss" class="px-3 py-1.5 rounded-lg bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300 text-xs font-bold hover:bg-gray-400 dark:hover:bg-gray-500 transition-colors" onclick="return confirm('Dismiss this report?')"><i class="fas fa-times"></i> Dismiss</button>
                            </form>
                            <?php else: ?>
                            <span class="text-xs text-gray-400">by <?php echo htmlspecialchars($r['resolved_by'] ? 'Admin' : 'System'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
