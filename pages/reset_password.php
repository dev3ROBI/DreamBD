<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../database/config.php';

$security = new Security();
$csrf_token = $security->generateCSRFToken();

$token = $_GET['token'] ?? '';
$is_valid = false;

if (!empty($token)) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT id, reset_token_expires FROM users WHERE reset_token = ? LIMIT 1");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        if ($user && strtotime($user['reset_token_expires']) > time()) {
            $is_valid = true;
        }
    } catch (PDOException $e) {}
}

$errors = [];
if (isset($_SESSION['form_errors'])) {
    $errors = $_SESSION['form_errors'];
    unset($_SESSION['form_errors']);
}
?>
<div class="min-h-screen flex items-center justify-center p-4 sm:p-8 bg-gradient-to-br from-slate-50 via-white to-purple-50 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900">
    <div class="w-full max-w-md mx-auto">
        <div class="bg-white/95 dark:bg-gray-900/95 backdrop-blur-xl rounded-3xl border border-gray-200/50 dark:border-gray-700/50 shadow-2xl p-6 sm:p-10 relative overflow-hidden">
            <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-amber-500 via-orange-500 to-amber-500"></div>

            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-20 h-20 bg-gradient-to-br from-amber-500 to-orange-600 rounded-2xl shadow-lg mb-4">
                    <i class="fas fa-key text-3xl text-white"></i>
                </div>
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white mb-2">Set New Password</h1>
                <p class="text-gray-600 dark:text-gray-400">Create a strong password for your account</p>
            </div>

            <?php if (!$is_valid): ?>
            <div class="text-center py-8">
                <div class="flex items-start gap-3 p-4 mb-6 bg-red-50 dark:bg-red-900/20 border-l-4 border-red-500 rounded-xl">
                    <i class="fas fa-exclamation-circle text-red-500 text-xl flex-shrink-0 mt-0.5"></i>
                    <div>
                        <div class="font-semibold text-red-800 dark:text-red-200 mb-1">Invalid or Expired Link</div>
                        <div class="text-sm text-red-700 dark:text-red-300">This password reset link is invalid or has expired. Please request a new one.</div>
                    </div>
                </div>
                <a href="index.php?page=login" class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-br from-blue-500 to-purple-600 text-white font-semibold rounded-xl shadow-lg hover:-translate-y-1 transition-all">
                    <i class="fas fa-sign-in-alt"></i> Go to Login
                </a>
            </div>
            <?php else: ?>
            <form method="POST" action="handlers/reset_handler.php" class="space-y-5" data-ajax-form="true" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                <div>
                    <div class="relative group">
                        <i class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 group-focus-within:text-blue-500 transition-colors text-lg z-10"></i>
                        <input type="password" id="password" name="password"
                               class="w-full min-h-[52px] pl-12 pr-12 py-3 text-base bg-gray-50 dark:bg-gray-800 border-2 border-gray-200 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:border-blue-500 transition-all"
                               placeholder="New password" autocomplete="new-password" required>
                        <button type="button" class="password-toggle absolute right-3 top-1/2 -translate-y-1/2 bg-transparent border-none text-gray-400 hover:text-blue-500 cursor-pointer p-2 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/30 transition-all flex items-center" data-target="password" aria-label="Show password">
                            <i class="fas fa-eye text-lg"></i>
                        </button>
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">At least 8 characters</div>
                </div>

                <div>
                    <div class="relative group">
                        <i class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 group-focus-within:text-blue-500 transition-colors text-lg z-10"></i>
                        <input type="password" id="confirm_password" name="confirm_password"
                               class="w-full min-h-[52px] pl-12 pr-12 py-3 text-base bg-gray-50 dark:bg-gray-800 border-2 border-gray-200 dark:border-gray-700 rounded-xl text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:border-blue-500 transition-all"
                               placeholder="Confirm new password" autocomplete="new-password" required>
                        <button type="button" class="password-toggle absolute right-3 top-1/2 -translate-y-1/2 bg-transparent border-none text-gray-400 hover:text-blue-500 cursor-pointer p-2 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/30 transition-all flex items-center" data-target="confirm_password" aria-label="Show password">
                            <i class="fas fa-eye text-lg"></i>
                        </button>
                    </div>
                    <div id="passwordMatch" class="mt-2 hidden">
                        <div class="flex items-center gap-2 text-xs font-medium" id="matchIndicator">
                            <i class="fas" id="matchIcon"></i>
                            <span id="matchText"></span>
                        </div>
                    </div>
                </div>

                <button type="submit" class="w-full relative overflow-hidden bg-gradient-to-br from-amber-500 to-orange-600 text-white font-semibold py-3 px-6 rounded-xl shadow-lg shadow-amber-500/30 hover:shadow-xl hover:shadow-amber-500/40 hover:-translate-y-1 disabled:opacity-60 transition-all duration-300 text-base group">
                    <span class="btn-text group-hover:tracking-wide transition-all"><i class="fas fa-save mr-2"></i>Reset Password</span>
                    <div class="btn-loader hidden absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2">
                        <div class="w-6 h-6 border-2 border-white/30 rounded-full border-t-white animate-spin"></div>
                    </div>
                </button>
            </form>
            <?php endif; ?>

            <div class="mt-6 text-center">
                <a href="index.php?page=login" class="text-blue-500 hover:underline text-sm font-medium">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Login
                </a>
            </div>
        </div>
    </div>
</div>
