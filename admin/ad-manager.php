<?php
$pageTitle = 'Ad Manager';
$pageHeading = 'Ad Manager';
$currentPage = 'ads';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'index.php'],
    ['label' => 'Ad Manager']
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
                    trim($_POST['title'] ?? ''),
                    trim($_POST['description'] ?? ''),
                    trim($_POST['image_path'] ?? ''),
                    trim($_POST['link_url'] ?? ''),
                    trim($_POST['link_text'] ?? 'Learn More'),
                    trim($_POST['bg_color'] ?? '#1e293b'),
                    trim($_POST['badge_text'] ?? 'Sponsored'),
                    (int) ($_POST['is_active'] ?? 1),
                    (int) ($_POST['sort_order'] ?? 0),
                ];
                if ($action === 'add') {
                    $stmt = $db->prepare("INSERT INTO slider_ads (title, description, image_path, link_url, link_text, bg_color, badge_text, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute($data);
                    $messages[] = 'Ad created!';
                } else {
                    $data[] = $id;
                    $stmt = $db->prepare("UPDATE slider_ads SET title=?, description=?, image_path=?, link_url=?, link_text=?, bg_color=?, badge_text=?, is_active=?, sort_order=? WHERE id=?");
                    $stmt->execute($data);
                    $messages[] = 'Ad updated!';
                }
            }
            if ($action === 'delete') {
                $stmt = $db->prepare("DELETE FROM slider_ads WHERE id=?");
                $stmt->execute([(int)($_POST['id'] ?? 0)]);
                $messages[] = 'Ad deleted';
            }
            if ($action === 'edit') {
                $stmt = $db->prepare("SELECT * FROM slider_ads WHERE id=?");
                $stmt->execute([(int)($_POST['id'] ?? 0)]);
                $editItem = $stmt->fetch();
            }
        } catch (PDOException $e) { $errors[] = $e->getMessage(); }
    }
}
$items = [];
try {
    $stmt = $db->query("SELECT * FROM slider_ads ORDER BY sort_order ASC");
    $items = $stmt->fetchAll();
} catch (PDOException $e) { $errors[] = 'Table not found.'; }
?>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 slide-in">
        <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-6">
            <i class="fas fa-<?php echo $editItem ? 'edit' : 'plus'; ?> text-green-500 mr-2"></i>
            <?php echo $editItem ? 'Edit Ad' : 'Add New Ad'; ?>
        </h2>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <input type="hidden" name="action" value="<?php echo $editItem ? 'update' : 'add'; ?>">
            <?php if ($editItem): ?><input type="hidden" name="id" value="<?php echo $editItem['id']; ?>"><?php endif; ?>

            <!-- Preview -->
            <div class="mb-6 rounded-xl overflow-hidden shadow-lg">
                <div id="adPreview" class="h-40 flex items-center justify-center p-6 transition-all duration-300"
                     style="background: <?php echo htmlspecialchars($editItem['bg_color'] ?? '#1e293b'); ?>;">
                    <div class="text-center text-white">
                        <div class="inline-block bg-white/20 px-3 py-1 rounded-full text-xs mb-2" id="previewBadge"><?php echo htmlspecialchars($editItem['badge_text'] ?? 'Sponsored'); ?></div>
                        <h3 class="text-xl font-bold" id="previewTitle"><?php echo htmlspecialchars(substr($editItem['title'] ?? 'Ad Title', 0, 40)); ?></h3>
                        <p class="text-sm opacity-80 mt-1" id="previewDesc"><?php echo htmlspecialchars(substr($editItem['description'] ?? 'Description...', 0, 80)); ?></p>
                        <span class="inline-block mt-3 px-4 py-1.5 bg-white/20 rounded-lg text-sm" id="previewBtn"><?php echo htmlspecialchars($editItem['link_text'] ?? 'Learn More'); ?></span>
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Title</label>
                        <input type="text" name="title" required value="<?php echo htmlspecialchars($editItem['title'] ?? ''); ?>"
                               class="w-full px-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-800 dark:text-white" oninput="updateAdPreview()">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Badge Text</label>
                        <input type="text" name="badge_text" value="<?php echo htmlspecialchars($editItem['badge_text'] ?? 'Sponsored'); ?>"
                               class="w-full px-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-800 dark:text-white" oninput="updateAdPreview()">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Description</label>
                    <textarea name="description" rows="2" class="w-full px-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-800 dark:text-white" oninput="updateAdPreview()"><?php echo htmlspecialchars($editItem['description'] ?? ''); ?></textarea>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Image Path</label>
                        <input type="text" name="image_path" value="<?php echo htmlspecialchars($editItem['image_path'] ?? ''); ?>"
                               class="w-full px-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-800 dark:text-white" placeholder="assets/images/slider/ad1.jpg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Link URL</label>
                        <input type="text" name="link_url" value="<?php echo htmlspecialchars($editItem['link_url'] ?? ''); ?>"
                               class="w-full px-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-800 dark:text-white" placeholder="https://...">
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Link Text</label>
                        <input type="text" name="link_text" value="<?php echo htmlspecialchars($editItem['link_text'] ?? 'Learn More'); ?>"
                               class="w-full px-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-800 dark:text-white" oninput="updateAdPreview()">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Background Color</label>
                        <div class="flex gap-2 mb-2">
                            <div class="relative">
                                <input type="color" name="bg_color" value="<?php echo htmlspecialchars($editItem['bg_color'] ?? '#1e293b'); ?>"
                                       class="w-10 h-9 rounded-lg cursor-pointer block border border-gray-300 dark:border-gray-500" oninput="updateAdPreview(); document.querySelector('input[name=\'bg_color_text\']').value=this.value">
                                <div class="absolute -bottom-1 -right-1 w-3 h-3 bg-white dark:bg-gray-800 rounded-full border border-gray-300 flex items-center justify-center" style="pointer-events:none;">
                                    <i class="fas fa-pipette text-[6px]"></i>
                                </div>
                            </div>
                            <input type="text" name="bg_color_text" value="<?php echo htmlspecialchars($editItem['bg_color'] ?? '#1e293b'); ?>"
                                   class="flex-1 px-3 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-500 rounded-lg text-gray-800 dark:text-white font-mono text-sm" id="bgColorText">
                        </div>
                        <div>
                            <?php
                            $adGroups = [
                                'Dark' => ['#0f172a','#1e293b','#334155','#1e1b4b'],
                                'Red' => ['#991b1b','#b91c1c','#7f1d1d'],
                                'Green' => ['#166534','#15803d','#14532d'],
                                'Blue' => ['#1e3a5f','#075985','#1e40af'],
                                'Purple' => ['#4c1d95','#3730a3','#581c87'],
                            ];
                            foreach ($adGroups as $gName => $gColors):
                            ?>
                            <div class="flex items-center gap-2 mb-1.5 last:mb-0">
                                <span class="text-[9px] font-semibold text-gray-400 uppercase tracking-wider w-10 shrink-0"><?php echo $gName; ?></span>
                                <div class="flex gap-1">
                                    <?php foreach ($gColors as $c): ?>
                                    <button type="button" onclick="document.querySelector('input[name=\'bg_color\']').value='<?php echo $c; ?>'; document.querySelector('input[name=\'bg_color_text\']').value='<?php echo $c; ?>'; updateAdPreview();"
                                            class="w-6 h-6 rounded-lg border-2 border-transparent hover:scale-110 hover:shadow-md transition-all shadow-sm"
                                            style="background: <?php echo $c; ?>;" title="<?php echo $c; ?>"></button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Sort Order</label>
                        <input type="number" name="sort_order" min="0" value="<?php echo $editItem['sort_order'] ?? 0; ?>"
                               class="w-full px-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-800 dark:text-white">
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" <?php echo ($editItem['is_active'] ?? 1) ? 'checked' : ''; ?>>
                    <span class="text-sm text-gray-700 dark:text-gray-300">Active</span>
                </div>
                <button type="submit" class="px-6 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors">
                    <i class="fas fa-save mr-2"></i><?php echo $editItem ? 'Update Ad' : 'Create Ad'; ?>
                </button>
                <?php if ($editItem): ?>
                <a href="ad-manager.php" class="px-6 py-2 bg-gray-300 dark:bg-gray-600 text-gray-800 dark:text-white rounded-lg ml-2">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-6"><i class="fas fa-ad text-green-500 mr-2"></i>Ads (<?php echo count($items); ?>)</h2>
        <?php if ($items): ?>
        <div class="space-y-3">
            <?php foreach ($items as $item): ?>
            <div class="border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                <div class="h-20 flex items-center px-6" style="background: <?php echo htmlspecialchars($item['bg_color']); ?>;">
                    <div class="text-white">
                        <div class="text-xs opacity-70"><?php echo htmlspecialchars($item['badge_text']); ?></div>
                        <div class="font-bold text-sm"><?php echo htmlspecialchars(substr($item['title'], 0, 30)); ?></div>
                    </div>
                </div>
                <div class="p-4 flex justify-between items-center">
                    <span class="text-sm text-gray-500"><?php echo htmlspecialchars($item['link_text']); ?> &rarr;</span>
                    <div class="flex gap-2">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                            <button class="p-2 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg"><i class="fas fa-edit"></i></button>
                        </form>
                        <form method="POST" onsubmit="return confirm('Delete ad?')">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                            <button class="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="text-center text-gray-500 py-8"><i class="fas fa-ad text-4xl mb-4"></i><p>No ads yet.</p></div>
        <?php endif; ?>
    </div>
</div>
<script>
function updateAdPreview() {
    const preview = document.getElementById('adPreview');
    const title = document.querySelector('input[name="title"]');
    const badge = document.querySelector('input[name="badge_text"]');
    const desc = document.querySelector('textarea[name="description"]');
    const linkText = document.querySelector('input[name="link_text"]');
    const bgColor = document.querySelector('input[name="bg_color"]');
    const bgColorText = document.getElementById('bgColorText');
    if (!preview) return;
    preview.style.background = bgColor.value;
    if (bgColorText) bgColorText.value = bgColor.value;
    document.getElementById('previewTitle').textContent = (title?.value || 'Ad Title').substring(0, 40);
    document.getElementById('previewBadge').textContent = badge?.value || 'Sponsored';
    document.getElementById('previewDesc').textContent = (desc?.value || 'Description...').substring(0, 80);
    document.getElementById('previewBtn').textContent = linkText?.value || 'Learn More';
}
updateAdPreview();
</script>
<?php require_once __DIR__ . '/layout/footer.php'; ?>
