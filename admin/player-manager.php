<?php
$pageTitle = 'Player Manager';
$pageHeading = 'Top Players';
$currentPage = 'players';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'index.php'],
    ['label' => 'Top Players']
];
require_once __DIR__ . '/layout/header.php';
$db = Database::getInstance()->getConnection();
$messages = []; $errors = [];
$editItem = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$security->validateCSRFToken($_POST['csrf_token'] ?? '')) { $errors[] = 'Invalid token'; }
    else {
        $action = $_POST['action'] ?? '';
        try {
            if ($action === 'add' || $action === 'update') {
                $id = (int) ($_POST['id'] ?? 0);
                $data = [
                    trim($_POST['name'] ?? ''),
                    (int) ($_POST['rank'] ?? 0),
                    (int) ($_POST['score'] ?? 0),
                    trim($_POST['avatar'] ?? 'default.png'),
                    trim($_POST['highlight'] ?? ''),
                    (int) ($_POST['is_active'] ?? 1),
                    (int) ($_POST['sort_order'] ?? 0),
                ];
                if ($action === 'add') {
                    $stmt = $db->prepare("INSERT INTO slider_players (name, `rank`, score, avatar, highlight, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute($data);
                    $messages[] = 'Player added!';
                } else {
                    $data[] = $id;
                    $stmt = $db->prepare("UPDATE slider_players SET name=?, `rank`=?, score=?, avatar=?, highlight=?, is_active=?, sort_order=? WHERE id=?");
                    $stmt->execute($data);
                    $messages[] = 'Player updated!';
                }
            }
            if ($action === 'delete') {
                $stmt = $db->prepare("DELETE FROM slider_players WHERE id=?");
                $stmt->execute([(int)($_POST['id'] ?? 0)]);
                $messages[] = 'Player deleted';
            }
            if ($action === 'edit') {
                $stmt = $db->prepare("SELECT * FROM slider_players WHERE id=?");
                $stmt->execute([(int)($_POST['id'] ?? 0)]);
                $editItem = $stmt->fetch();
            }
        } catch (PDOException $e) { $errors[] = $e->getMessage(); }
    }
}
$items = [];
try {
    $stmt = $db->query("SELECT * FROM slider_players ORDER BY sort_order ASC, `rank` ASC");
    $items = $stmt->fetchAll();
} catch (PDOException $e) { $errors[] = 'Table not found. Create tables first.'; }
?>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 slide-in">
        <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-6">
            <i class="fas fa-<?php echo $editItem ? 'edit' : 'plus'; ?> text-amber-500 mr-2"></i>
            <?php echo $editItem ? 'Edit Player' : 'Add Player'; ?>
        </h2>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <input type="hidden" name="action" value="<?php echo $editItem ? 'update' : 'add'; ?>">
            <?php if ($editItem): ?><input type="hidden" name="id" value="<?php echo $editItem['id']; ?>"><?php endif; ?>
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Player Name</label>
                        <input type="text" name="name" required value="<?php echo htmlspecialchars($editItem['name'] ?? ''); ?>"
                               class="w-full px-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-800 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Avatar filename</label>
                        <input type="text" name="avatar" value="<?php echo htmlspecialchars($editItem['avatar'] ?? 'default.png'); ?>"
                               class="w-full px-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-800 dark:text-white"
                               placeholder="default.png">
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Rank</label>
                        <input type="number" name="rank" min="1" value="<?php echo $editItem['rank'] ?? 0; ?>"
                               class="w-full px-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-800 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Score (pts)</label>
                        <input type="number" name="score" min="0" value="<?php echo $editItem['score'] ?? 0; ?>"
                               class="w-full px-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-800 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Sort Order</label>
                        <input type="number" name="sort_order" min="0" value="<?php echo $editItem['sort_order'] ?? 0; ?>"
                               class="w-full px-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-800 dark:text-white">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Highlight (e.g. "1324 pts, 48 wins")</label>
                    <input type="text" name="highlight" value="<?php echo htmlspecialchars($editItem['highlight'] ?? ''); ?>"
                           class="w-full px-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-800 dark:text-white"
                           placeholder="1324 pts, 48 wins">
                </div>
                <div class="flex items-center gap-4">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="is_active" value="1" <?php echo ($editItem['is_active'] ?? 1) ? 'checked' : ''; ?>>
                        <span class="text-sm text-gray-700 dark:text-gray-300">Active</span>
                    </label>
                    <?php if (!$editItem): ?>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="link_to_user" value="1" id="linkToUser">
                        <span class="text-sm text-gray-700 dark:text-gray-300">Link to existing user</span>
                    </label>
                    <?php endif; ?>
                </div>
                <button type="submit" class="px-6 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg transition-colors">
                    <i class="fas fa-save mr-2"></i><?php echo $editItem ? 'Update' : 'Add Player'; ?>
                </button>
                <?php if ($editItem): ?>
                <a href="player-manager.php" class="px-6 py-2 bg-gray-300 dark:bg-gray-600 text-gray-800 dark:text-white rounded-lg ml-2">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-6"><i class="fas fa-ranking-star text-amber-500 mr-2"></i>Players (<?php echo count($items); ?>)</h2>
        <?php if ($items): ?>
        <div class="space-y-3">
            <?php foreach ($items as $item): ?>
            <div class="border border-gray-200 dark:border-gray-700 rounded-xl p-4 flex items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <span class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center text-white font-bold">#<?php echo (int) $item['rank']; ?></span>
                    <div>
                        <strong class="text-gray-900 dark:text-white"><?php echo htmlspecialchars($item['name']); ?></strong>
                        <div class="text-sm text-gray-500"><?php echo number_format((int) $item['score']); ?> pts</div>
                    </div>
                </div>
                <div class="flex gap-2 shrink-0">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                        <button class="p-2 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg"><i class="fas fa-edit"></i></button>
                    </form>
                    <form method="POST" onsubmit="return confirm('Delete?')">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                        <button class="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg"><i class="fas fa-trash"></i></button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="text-center text-gray-500 py-8"><i class="fas fa-ranking-star text-4xl mb-4"></i><p>No players added yet.</p></div>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/layout/footer.php'; ?>
