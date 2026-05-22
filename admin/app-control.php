<?php
$pageTitle = 'App Control';
$pageHeading = 'App Control';
$currentPage = 'app-control';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'index.php'],
    ['label' => 'App Control']
];

require_once __DIR__ . '/layout/header.php';

$db = Database::getInstance()->getConnection();
$messages = [];
$errors = [];

// Create app_settings table if not exists
try {
    $db->exec("CREATE TABLE IF NOT EXISTS app_settings (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(50) UNIQUE NOT NULL,
        setting_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_key (setting_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (PDOException $e) {
    // Ignore
}

// Get current settings
$settings = [];
try {
    $stmt = $db->query("SELECT * FROM app_settings");
    foreach ($stmt->fetchAll() as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    // Table doesn't exist
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$security->validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token';
    } else {
        try {
            $maintenanceMode = $_POST['maintenance_mode'] ?? 'off';
            $forceUpdate = $_POST['force_update'] ?? '0';
            $latestVersion = $_POST['latest_version'] ?? '1.0.0';
            $appNotice = $_POST['app_notice'] ?? '';

            $settingsToUpdate = [
                'maintenance_mode' => $maintenanceMode,
                'force_update' => $forceUpdate,
                'latest_version' => $latestVersion,
                'app_notice' => $appNotice
            ];

            foreach ($settingsToUpdate as $key => $value) {
                $stmt = $db->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?)
                                       ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                $stmt->execute([$key, $value]);
            }

            $messages[] = '[SUCCESS] Settings updated successfully';

            // Refresh settings
            $settings = [];
            $stmt = $db->query("SELECT * FROM app_settings");
            foreach ($stmt->fetchAll() as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (PDOException $e) {
            $errors[] = '[ERROR] ' . $e->getMessage();
        }
    }
}
?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">

    <!-- Maintenance Mode -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 slide-in">
        <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-6">
            <i class="fas fa-tools text-yellow-500 mr-2"></i>Maintenance Mode
        </h2>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Maintenance Status</label>
                <div class="flex items-center space-x-4">
                    <label class="flex items-center">
                        <input type="radio" name="maintenance_mode" value="off" <?php echo ($settings['maintenance_mode'] ?? 'off') === 'off' ? 'checked' : ''; ?>
                               class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                        <span class="ml-2 text-gray-700 dark:text-gray-300">Off (App is live)</span>
                    </label>
                    <label class="flex items-center">
                        <input type="radio" name="maintenance_mode" value="on" <?php echo ($settings['maintenance_mode'] ?? 'off') === 'on' ? 'checked' : ''; ?>
                               class="w-4 h-4 text-yellow-600 border-gray-300 focus:ring-yellow-500">
                        <span class="ml-2 text-gray-700 dark:text-gray-300">On (Maintenance mode)</span>
                    </label>
                </div>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">App Notice / Maintenance Message</label>
                <textarea name="app_notice" rows="3"
                          class="w-full px-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-gray-800 dark:text-white"><?php echo htmlspecialchars($settings['app_notice'] ?? ''); ?></textarea>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">This message will be shown to users when maintenance mode is ON</p>
            </div>

            <button type="submit" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                <i class="fas fa-save mr-2"></i>Save Settings
            </button>
        </form>
    </div>

    <!-- Force Update -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 slide-in">
        <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-6">
            <i class="fas fa-download text-blue-500 mr-2"></i>Force Update
        </h2>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

            <div class="mb-6">
                <label class="flex items-center">
                    <input type="checkbox" name="force_update" value="1" <?php echo ($settings['force_update'] ?? '0') === '1' ? 'checked' : ''; ?>
                           class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                    <span class="ml-2 text-gray-700 dark:text-gray-300">Force users to update app</span>
                </label>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Latest Version</label>
                <input type="text" name="latest_version" value="<?php echo htmlspecialchars($settings['latest_version'] ?? '1.0.0'); ?>"
                       class="w-full px-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-gray-800 dark:text-white font-mono"
                       placeholder="e.g. 1.0.0">
            </div>

            <button type="submit" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                <i class="fas fa-save mr-2"></i>Save Settings
            </button>
        </form>
    </div>
</div>

<!-- Current Status -->
<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-8">
    <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-6">
        <i class="fas fa-info-circle text-green-500 mr-2"></i>Current Status
    </h2>
    <div class="space-y-4">
        <div class="flex justify-between items-center py-3 border-b border-gray-100 dark:border-gray-700">
            <span class="text-gray-600 dark:text-gray-400">Maintenance Mode</span>
            <span class="px-3 py-1 text-xs font-semibold rounded-full <?php echo ($settings['maintenance_mode'] ?? 'off') === 'on' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400' : 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'; ?>">
                <?php echo ($settings['maintenance_mode'] ?? 'off') === 'on' ? '<i class="fas fa-tools mr-1"></i>ON' : '<i class="fas fa-check-circle mr-1"></i>OFF'; ?>
            </span>
        </div>
        <div class="flex justify-between items-center py-3 border-b border-gray-100 dark:border-gray-700">
            <span class="text-gray-600 dark:text-gray-400">Force Update</span>
            <span class="px-3 py-1 text-xs font-semibold rounded-full <?php echo ($settings['force_update'] ?? '0') === '1' ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'; ?>">
                <?php echo ($settings['force_update'] ?? '0') === '1' ? 'YES' : 'NO'; ?>
            </span>
        </div>
        <div class="flex justify-between items-center py-3 border-b border-gray-100 dark:border-gray-700">
            <span class="text-gray-600 dark:text-gray-400">Latest Version</span>
            <span class="font-mono font-semibold text-gray-800 dark:text-white"><?php echo htmlspecialchars($settings['latest_version'] ?? '1.0.0'); ?></span>
        </div>
        <div class="flex justify-between items-center py-3">
            <span class="text-gray-600 dark:text-gray-400">App Notice</span>
            <span class="text-sm text-gray-800 dark:text-white max-w-md text-right"><?php echo htmlspecialchars(substr($settings['app_notice'] ?? 'No notice set', 0, 50)); ?>...</span>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
