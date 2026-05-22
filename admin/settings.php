<?php
$pageTitle = 'Settings';
$pageHeading = 'Admin Settings';
$currentPage = 'settings';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'index.php'],
    ['label' => 'Settings']
];

require_once __DIR__ . '/layout/header.php';

$db = Database::getInstance()->getConnection();
$csrfToken = $security->generateCSRFToken();
$messages = [];
$errors = [];

// Get current admin profile
$adminId = $_SESSION['user_id'] ?? 0;
$adminProfile = [];
try {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$adminId]);
    $adminProfile = $stmt->fetch();
} catch (PDOException $e) {
    $errors[] = 'Failed to load profile: ' . $e->getMessage();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$security->validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token';
    } else {
        $action = $_POST['action'] ?? '';

        try {
            if ($action === 'update_profile') {
                $fullName = trim($_POST['full_name'] ?? '');
                $email = trim($_POST['email'] ?? '');

                $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
                $stmt->execute([$fullName, $email, $adminId]);

                $_SESSION['full_name'] = $fullName;
                $_SESSION['email'] = $email;

                $messages[] = '[SUCCESS] Profile updated successfully';
            }

            if ($action === 'change_password') {
                $currentPassword = $_POST['current_password'] ?? '';
                $newPassword = $_POST['new_password'] ?? '';
                $confirmPassword = $_POST['confirm_password'] ?? '';

                if ($newPassword !== $confirmPassword) {
                    $errors[] = 'New passwords do not match';
                } elseif (strlen($newPassword) < 8) {
                    $errors[] = 'New password must be at least 8 characters';
                } else {
                    // Verify current password
                    $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
                    $stmt->execute([$adminId]);
                    $user = $stmt->fetch();

                    if (password_verify($currentPassword, $user['password_hash'])) {
                        $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
                        $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                        $stmt->execute([$newHash, $adminId]);
                        $messages[] = '[SUCCESS] Password changed successfully';
                    } else {
                        $errors[] = 'Current password is incorrect';
                    }
                }
            }
        } catch (PDOException $e) {
            $errors[] = '[ERROR] ' . $e->getMessage();
        }
    }
}
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">

    <!-- Profile Settings -->
    <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 slide-in">
        <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-6">
            <i class="fas fa-user-cog text-blue-500 mr-2"></i>Profile Settings
        </h2>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <input type="hidden" name="action" value="update_profile">

            <div class="flex items-center space-x-6 mb-6">
                <div class="w-20 h-20 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white text-3xl font-bold">
                    <?php echo strtoupper(substr($adminProfile['full_name'] ?? 'A', 0, 1)); ?>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white"><?php echo htmlspecialchars($adminProfile['full_name'] ?? ''); ?></h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">@<?php echo htmlspecialchars($adminProfile['username'] ?? ''); ?></p>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $userRole === 'super_admin' ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' : 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400'; ?> mt-2">
                        <i class="fas fa-shield-alt mr-1"></i>
                        <?php echo strtoupper($userRole); ?>
                    </span>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Full Name</label>
                    <input type="text" name="full_name" required
                           value="<?php echo htmlspecialchars($adminProfile['full_name'] ?? ''); ?>"
                           class="w-full px-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-gray-800 dark:text-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email</label>
                    <input type="email" name="email" required
                           value="<?php echo htmlspecialchars($adminProfile['email'] ?? ''); ?>"
                           class="w-full px-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-gray-800 dark:text-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Username</label>
                    <input type="text" disabled
                           value="<?php echo htmlspecialchars($adminProfile['username'] ?? ''); ?>"
                           class="w-full px-4 py-2 bg-gray-100 dark:bg-gray-600 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-500 dark:text-gray-400 cursor-not-allowed">
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Username cannot be changed</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Phone</label>
                    <input type="text" disabled
                           value="<?php echo htmlspecialchars($adminProfile['phone'] ?? 'Not set'); ?>"
                           class="w-full px-4 py-2 bg-gray-100 dark:bg-gray-600 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-500 dark:text-gray-400 cursor-not-allowed">
                </div>
            </div>

            <button type="submit" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                <i class="fas fa-save mr-2"></i>Save Changes
            </button>
        </form>
    </div>

    <!-- Change Password -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 slide-in">
        <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-6">
            <i class="fas fa-key text-yellow-500 mr-2"></i>Change Password
        </h2>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <input type="hidden" name="action" value="change_password">

            <div class="space-y-4 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Current Password</label>
                    <input type="password" name="current_password" required
                           class="w-full px-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-gray-800 dark:text-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">New Password</label>
                    <input type="password" name="new_password" required minlength="8"
                           class="w-full px-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-gray-800 dark:text-white">
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Minimum 8 characters</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Confirm New Password</label>
                    <input type="password" name="confirm_password" required minlength="8"
                           class="w-full px-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-gray-800 dark:text-white">
                </div>
            </div>

            <button type="submit" class="w-full px-6 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded-lg transition-colors">
                <i class="fas fa-key mr-2"></i>Change Password
            </button>
        </form>
    </div>
</div>

<!-- Account Info -->
<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
    <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-6">
        <i class="fas fa-info-circle text-green-500 mr-2"></i>Account Information
    </h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="flex justify-between items-center py-3 border-b border-gray-100 dark:border-gray-700">
            <span class="text-gray-600 dark:text-gray-400">Account Created</span>
            <span class="text-sm font-semibold text-gray-800 dark:text-white"><?php echo date('F j, Y', strtotime($adminProfile['registered_at'] ?? 'now')); ?></span>
        </div>
        <div class="flex justify-between items-center py-3 border-b border-gray-100 dark:border-gray-700">
            <span class="text-gray-600 dark:text-gray-400">Last Login</span>
            <span class="text-sm font-semibold text-gray-800 dark:text-white"><?php echo $adminProfile['last_login'] ? date('F j, Y H:i', strtotime($adminProfile['last_login'])) : 'Never'; ?></span>
        </div>
        <div class="flex justify-between items-center py-3 border-b border-gray-100 dark:border-gray-700">
            <span class="text-gray-600 dark:text-gray-400">Account Status</span>
            <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo ($adminProfile['status'] ?? 'active') === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'; ?>">
                <?php echo strtoupper($adminProfile['status'] ?? 'active'); ?>
            </span>
        </div>
        <div class="flex justify-between items-center py-3">
            <span class="text-gray-600 dark:text-gray-400">User ID</span>
            <span class="text-sm font-mono font-semibold text-gray-800 dark:text-white">#<?php echo $adminId; ?></span>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
