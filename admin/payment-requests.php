<?php
$pageTitle = 'Payment Requests';
$pageHeading = 'Payment Requests';
$currentPage = 'payments';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'index.php'],
    ['label' => 'Payment Requests']
];
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/layout/header.php';
$db = Database::getInstance()->getConnection();
$messages = []; $errors = [];

// ─── Handle approve/reject ───
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$security->validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token';
    } else {
        $action = $_POST['action'] ?? '';

        // Save payment settings
        if ($action === 'save_settings') {
            $keys = ['bkash','nagad','rocket'];
            try {
                foreach ($keys as $k) {
                    $num = trim($_POST[$k . '_number'] ?? '01888780877');
                    $inst = in_array($_POST[$k . '_instruction'] ?? '', ['send_money','cashout']) ? $_POST[$k . '_instruction'] : 'send_money';
                    $db->exec("INSERT INTO site_settings (`key`, `value`) VALUES ('payment_{$k}_number', " . $db->quote($num) . ") ON DUPLICATE KEY UPDATE `value` = " . $db->quote($num));
                    $db->exec("INSERT INTO site_settings (`key`, `value`) VALUES ('payment_{$k}_instruction', " . $db->quote($inst) . ") ON DUPLICATE KEY UPDATE `value` = " . $db->quote($inst));
                }
                $messages[] = 'Payment settings saved!';
            } catch (Throwable $e) {
                $errors[] = 'Could not save settings.';
            }
        }

        // Approve / reject
        $reqId = (int)($_POST['id'] ?? 0);
        $adminNote = trim($_POST['admin_note'] ?? '');
        try {
            if ($action === 'approve' && $reqId > 0) {
                $db->beginTransaction();
                $stmt = $db->prepare("SELECT * FROM payment_requests WHERE id = ? AND status = 'pending' FOR UPDATE");
                $stmt->execute([$reqId]);
                $req = $stmt->fetch();
                if (!$req) {
                    $db->rollBack();
                    $errors[] = 'Request not found or already processed.';
                } else {
                    $uid = (int)$req['user_id'];
                    $amt = (float)$req['amount'];
                    $stmt = $db->prepare("UPDATE payment_requests SET status = 'completed', admin_id = ?, admin_note = ? WHERE id = ?");
                    $stmt->execute([$userId, $adminNote, $reqId]);
                    $purpose = $req['purpose'] ?? 'add_money';
                    if ($purpose === 'agent_activation') {
                        // Agent activation: NO balance credit, just activate role + debit transaction
                        $stmt = $db->prepare("UPDATE users SET role = 'agent', agent_verified_at = NOW() WHERE id = ? AND role = 'user'");
                        $stmt->execute([$uid]);
                        // Get current balance for transaction history
                        $stmt = $db->prepare("SELECT balance FROM users WHERE id = ?");
                        $stmt->execute([$uid]);
                        $uBal = (float)$stmt->fetchColumn();
                        $stmt = $db->prepare("INSERT INTO agent_transactions (agent_id, type, amount, balance_before, balance_after, reference_type, reference_id, description) VALUES (?, 'debit', ?, ?, ?, 'agent_fee', ?, 'Agent activation fee')");
                        $stmt->execute([$uid, $amt, $uBal, $uBal, $reqId]);
                        try { createNotification($db, $uid, $userId, 'agent_activation', 'Congratulations! Your agent account has been activated.'); } catch (Throwable $e) {}
                        $messages[] = 'User #' . $uid . ' activated as agent!';
                    } else {
                        // Regular deposit: credit balance + credit transaction
                        $stmt = $db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                        $stmt->execute([$amt, $uid]);
                        $desc = 'Payment request #' . $reqId . ' approved: ' . $req['method'] . ' ' . $req['transaction_id'];
                        $stmt = $db->prepare("INSERT INTO agent_transactions (agent_id, type, amount, reference_type, reference_id, description) VALUES (?, 'credit', ?, 'payment_request', ?, ?)");
                        $stmt->execute([$uid, $amt, $reqId, $desc]);
                        try { createNotification($db, $uid, $userId, 'payment_completed', 'Your ৳' . number_format($amt, 0) . ' payment has been approved and added to your balance.'); } catch (Throwable $e) {}
                        $messages[] = 'Payment #' . $reqId . ' approved! ৳' . number_format($amt, 0) . ' credited to user #' . $uid;
                    }
                    $db->commit();
                }
            } elseif ($action === 'reject' && $reqId > 0) {
                $stmt = $db->prepare("SELECT user_id, amount FROM payment_requests WHERE id = ? AND status = 'pending'");
                $stmt->execute([$reqId]);
                $reqData = $stmt->fetch();
                $stmt = $db->prepare("UPDATE payment_requests SET status = 'cancelled', admin_id = ?, admin_note = ? WHERE id = ? AND status = 'pending'");
                $stmt->execute([$userId, $adminNote, $reqId]);
                if ($stmt->rowCount() > 0) {
                    if ($reqData) {
                        try { createNotification($db, (int)$reqData['user_id'], $userId, 'payment_cancelled', 'Your ৳' . number_format((float)$reqData['amount'], 0) . ' payment request has been cancelled by admin.'); } catch (Throwable $e) {}
                    }
                    $messages[] = 'Payment #' . $reqId . ' rejected.';
                } else {
                    $errors[] = 'Request not found or already processed.';
                }
            }
        } catch (Throwable $e) {
            $db->rollBack();
            $errors[] = 'Server error: ' . $e->getMessage();
        }
    }
}

// ─── Load current payment settings (per method) ───
$pmSettings = ['bkash' => ['number' => '01888780877', 'instruction' => 'send_money'],
               'nagad' => ['number' => '01888780877', 'instruction' => 'send_money'],
               'rocket' => ['number' => '01888780877', 'instruction' => 'send_money']];
try {
    $stmt = $db->query("SELECT `key`, `value` FROM site_settings WHERE `key` LIKE 'payment_%'");
    while ($row = $stmt->fetch()) {
        foreach (['bkash','nagad','rocket'] as $m) {
            if ($row['key'] === "payment_{$m}_number") $pmSettings[$m]['number'] = $row['value'] ?: '01888780877';
            if ($row['key'] === "payment_{$m}_instruction") $pmSettings[$m]['instruction'] = $row['value'] ?: 'send_money';
        }
    }
} catch (Throwable $e) {}

// ─── Build query with filters + search ───
$statusFilter = $_GET['status'] ?? 'pending';
$searchQuery = trim($_GET['search'] ?? '');
$allowedStatuses = ['pending', 'completed', 'cancelled', 'all'];
if (!in_array($statusFilter, $allowedStatuses)) $statusFilter = 'pending';

$params = [];
$conditions = [];
if ($statusFilter !== 'all') {
    $conditions[] = 'pr.status = ?';
    $params[] = $statusFilter;
}
if ($searchQuery !== '') {
    $conditions[] = '(pr.transaction_id LIKE ? OR pr.user_id LIKE ? OR CAST(pr.id AS CHAR) LIKE ?)';
    $like = '%' . $searchQuery . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
}
$where = '';
if (!empty($conditions)) $where = 'WHERE ' . implode(' AND ', $conditions);

$stmt = $db->prepare("
    SELECT pr.*, u.username, u.full_name
    FROM payment_requests pr
    JOIN users u ON pr.user_id = u.id
    $where
    ORDER BY pr.created_at DESC
");
$stmt->execute($params);
$requests = $stmt->fetchAll();
?>
<!-- ═══ PAYMENT SETTINGS PANEL (per method) ═══ -->
<div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-5 mb-6">
    <div class="flex items-center justify-between mb-4">
        <h3 class="font-bold text-gray-900 dark:text-white"><i class="fas fa-gear text-gray-400 mr-2"></i> Payment Settings</h3>
        <span class="text-xs text-gray-400">Configure each payment method separately</span>
    </div>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
        <input type="hidden" name="action" value="save_settings">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <?php $methods = ['bkash' => ['label'=>'bKash','color'=>'#E2136E'],'nagad'=>['label'=>'Nagad','color'=>'#E8522E'],'rocket'=>['label'=>'Rocket','color'=>'#CC0000']]; ?>
            <?php foreach ($methods as $mk => $mv): $s = $pmSettings[$mk]; ?>
            <div class="p-4 rounded-xl border" style="border-color:<?php echo $mv['color']; ?>33">
                <div style="font-weight:700;font-size:14px;margin-bottom:10px;color:<?php echo $mv['color']; ?>"><?php echo $mv['label']; ?></div>
                <div class="mb-2">
                    <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-0.5">Merchant Number</label>
                    <input type="text" name="<?php echo $mk; ?>_number" value="<?php echo htmlspecialchars($s['number']); ?>" required class="w-full px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm font-mono">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-0.5">Instruction Type</label>
                    <select name="<?php echo $mk; ?>_instruction" class="w-full px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm">
                        <option value="send_money" <?php echo $s['instruction'] === 'send_money' ? 'selected' : ''; ?>>Send Money</option>
                        <option value="cashout" <?php echo $s['instruction'] === 'cashout' ? 'selected' : ''; ?>>Cashout</option>
                    </select>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg transition-colors">
            <i class="fas fa-save mr-1"></i> Save All Settings
        </button>
    </form>
</div>

<!-- ═══ FILTERS + SEARCH ═══ -->
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <div class="flex flex-wrap items-center gap-1.5">
        <a href="?status=pending<?php echo $searchQuery ? '&search=' . urlencode($searchQuery) : ''; ?>" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-colors <?php echo $statusFilter === 'pending' ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400 ring-2 ring-yellow-300 dark:ring-yellow-700' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600'; ?>">⏳ Pending</a>
        <a href="?status=completed<?php echo $searchQuery ? '&search=' . urlencode($searchQuery) : ''; ?>" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-colors <?php echo $statusFilter === 'completed' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 ring-2 ring-green-300 dark:ring-green-700' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600'; ?>">✅ Completed</a>
        <a href="?status=cancelled<?php echo $searchQuery ? '&search=' . urlencode($searchQuery) : ''; ?>" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-colors <?php echo $statusFilter === 'cancelled' ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400 ring-2 ring-red-300 dark:ring-red-700' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600'; ?>">❌ Cancelled</a>
        <a href="?status=all<?php echo $searchQuery ? '&search=' . urlencode($searchQuery) : ''; ?>" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-colors <?php echo $statusFilter === 'all' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 ring-2 ring-blue-300 dark:ring-blue-700' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600'; ?>">📋 All</a>
    </div>
    <form method="GET" class="flex items-center gap-2">
        <?php if ($statusFilter !== 'all'): ?>
        <input type="hidden" name="status" value="<?php echo $statusFilter; ?>">
        <?php endif; ?>
        <input type="text" name="search" placeholder="Search TXID or User ID..." value="<?php echo htmlspecialchars($searchQuery); ?>" class="px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm w-56 font-mono">
        <button type="submit" class="px-3 py-1.5 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 rounded-lg text-sm transition-colors"><i class="fas fa-search"></i></button>
        <?php if ($searchQuery): ?>
        <a href="?status=<?php echo $statusFilter; ?>" class="px-3 py-1.5 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 rounded-lg text-sm transition-colors"><i class="fas fa-times"></i></a>
        <?php endif; ?>
    </form>
    <span class="text-xs text-gray-400 whitespace-nowrap"><?php echo count($requests); ?> request(s)</span>
</div>

<!-- ═══ TABLE ═══ -->
<div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700">
                    <th class="text-left px-3 py-2.5 font-semibold text-gray-500 dark:text-gray-400 text-xs">ID</th>
                    <th class="text-left px-3 py-2.5 font-semibold text-gray-500 dark:text-gray-400 text-xs">User</th>
                    <th class="text-left px-3 py-2.5 font-semibold text-gray-500 dark:text-gray-400 text-xs">Method</th>
                    <th class="text-left px-3 py-2.5 font-semibold text-gray-500 dark:text-gray-400 text-xs">Sender Phone</th>
                    <th class="text-left px-3 py-2.5 font-semibold text-gray-500 dark:text-gray-400 text-xs">TXID</th>
                    <th class="text-center px-3 py-2.5 font-semibold text-gray-500 dark:text-gray-400 text-xs">Purpose</th>
                    <th class="text-right px-3 py-2.5 font-semibold text-gray-500 dark:text-gray-400 text-xs">Amount</th>
                    <th class="text-center px-3 py-2.5 font-semibold text-gray-500 dark:text-gray-400 text-xs">Status</th>
                    <th class="text-left px-3 py-2.5 font-semibold text-gray-500 dark:text-gray-400 text-xs">Date</th>
                    <th class="text-left px-3 py-2.5 font-semibold text-gray-500 dark:text-gray-400 text-xs">Admin Note</th>
                    <th class="text-center px-3 py-2.5 font-semibold text-gray-500 dark:text-gray-400 text-xs">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($requests)): ?>
                <tr><td colspan="10" class="px-4 py-10 text-center text-gray-400 text-sm">No payment requests found.</td></tr>
                <?php endif; ?>
                <?php foreach ($requests as $r): ?>
                <tr class="border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                    <td class="px-3 py-2.5 font-mono text-xs text-gray-500">#<?php echo $r['id']; ?></td>
                    <td class="px-3 py-2.5">
                        <div class="font-medium text-sm"><?php echo htmlspecialchars($r['full_name'] ?: $r['username']); ?></div>
                        <div class="text-xs text-gray-400">ID: <?php echo $r['user_id']; ?></div>
                    </td>
                    <td class="px-3 py-2.5">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                            <?php echo strtoupper(htmlspecialchars($r['method'])); ?>
                        </span>
                    </td>
                    <td class="px-3 py-2.5 font-mono text-xs text-gray-600 dark:text-gray-300"><?php echo htmlspecialchars($r['sender_phone']); ?></td>
                    <td class="px-3 py-2.5 font-mono text-xs max-w-[130px] truncate text-gray-600 dark:text-gray-300" title="<?php echo htmlspecialchars($r['transaction_id']); ?>"><?php echo htmlspecialchars($r['transaction_id']); ?></td>
                    <td class="px-3 py-2.5 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold
                            <?php echo ($r['purpose'] ?? 'add_money') === 'agent_activation' ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400' : 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'; ?>">
                            <?php echo ($r['purpose'] ?? 'add_money') === 'agent_activation' ? 'Agent' : 'Deposit'; ?>
                        </span>
                    </td>
                    <td class="px-3 py-2.5 text-right font-bold">৳<?php echo number_format((float)$r['amount'], 0); ?></td>
                    <td class="px-3 py-2.5 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold
                            <?php echo $r['status'] === 'completed' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : ($r['status'] === 'cancelled' ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' : 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400'); ?>">
                            <?php echo $r['status'] === 'completed' ? 'Paid' : ucfirst($r['status']); ?>
                        </span>
                    </td>
                    <td class="px-3 py-2.5 text-xs text-gray-500 whitespace-nowrap"><?php echo date('M j, g:ia', strtotime($r['created_at'])); ?></td>
                    <td class="px-3 py-2.5 text-xs text-gray-400 max-w-[110px] truncate"><?php echo htmlspecialchars($r['admin_note'] ?? ''); ?></td>
                    <td class="px-3 py-2.5 text-center">
                        <?php if ($r['status'] === 'pending'): ?>
                        <div class="flex items-center justify-center gap-1.5" style="position:relative">
                            <form method="POST" class="inline" onsubmit="return confirm('Approve payment #<?php echo $r['id']; ?>?')">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                                <input type="hidden" name="admin_note" value="">
                                <button type="submit" class="px-2.5 py-1.5 text-xs font-semibold rounded-lg bg-green-500 hover:bg-green-600 text-white transition-colors" title="Approve">
                                    <i class="fas fa-check"></i>
                                </button>
                            </form>
                            <form method="POST" class="inline" onsubmit="return confirm('Reject payment #<?php echo $r['id']; ?>?')">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                                <input type="hidden" name="admin_note" value="">
                                <button type="submit" class="px-2.5 py-1.5 text-xs font-semibold rounded-lg bg-red-500 hover:bg-red-600 text-white transition-colors" title="Reject">
                                    <i class="fas fa-times"></i>
                                </button>
                            </form>
                            <button onclick="noteToggle(this)" class="px-2 py-1.5 text-xs rounded-lg bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-600 dark:text-gray-400 transition-colors" title="Add note">✏️</button>
                            <div class="note-box" style="display:none;position:absolute;top:100%;right:0;z-index:10;background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:8px;box-shadow:0 8px 24px rgba(0,0,0,.12);min-width:200px;margin-top:4px" class="dark:bg-gray-700 dark:border-gray-600">
                                <form method="POST" class="flex flex-col gap-1.5">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                    <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                                    <textarea name="admin_note" rows="2" placeholder="Admin note..." class="w-full px-2 py-1.5 text-xs border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white resize-none"></textarea>
                                    <div class="flex gap-1.5">
                                        <button type="submit" name="action" value="approve" class="flex-1 px-2 py-1 text-xs font-semibold rounded-lg bg-green-500 hover:bg-green-600 text-white">Approve</button>
                                        <button type="submit" name="action" value="reject" class="flex-1 px-2 py-1 text-xs font-semibold rounded-lg bg-red-500 hover:bg-red-600 text-white">Reject</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <?php else: ?>
                        <span class="text-xs text-gray-400">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function noteToggle(btn) {
    var box = btn.parentElement.querySelector('.note-box');
    if (box) {
        var all = document.querySelectorAll('.note-box');
        all.forEach(function(n) { if (n !== box) n.style.display = 'none'; });
        box.style.display = box.style.display === 'none' ? 'block' : 'none';
    }
}
document.addEventListener('click', function(e) {
    if (!e.target.closest('.note-box') && !e.target.closest('[onclick*="noteToggle"]')) {
        document.querySelectorAll('.note-box').forEach(function(n) { n.style.display = 'none'; });
    }
});
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
