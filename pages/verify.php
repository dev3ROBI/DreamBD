<?php
require_once __DIR__ . '/../includes/security.php';

$security = new Security();
$csrf_token = $security->generateCSRFToken();

$token = $_GET['token'] ?? '';
$message = '';
$is_valid = !empty($token);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify'])) {
    require_once __DIR__ . '/../handlers/verify_handler.php';
    exit;
}
?>
<div class="min-h-screen flex items-center justify-center p-4 sm:p-8 bg-gradient-to-br from-slate-50 via-white to-purple-50 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900">
    <div class="w-full max-w-md mx-auto">
        <div class="bg-white/95 dark:bg-gray-900/95 backdrop-blur-xl rounded-3xl border border-gray-200/50 dark:border-gray-700/50 shadow-2xl p-6 sm:p-10 relative overflow-hidden">
            <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-green-500 via-emerald-500 to-green-500"></div>

            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-20 h-20 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl shadow-lg mb-4">
                    <i class="fas fa-envelope text-3xl text-white"></i>
                </div>
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white mb-2">Email Verification</h1>
                <p class="text-gray-600 dark:text-gray-400">Verify your email address to activate your account</p>
            </div>

            <?php if (!$is_valid): ?>
            <div class="text-center py-8">
                <div class="flex items-start gap-3 p-4 mb-6 bg-red-50 dark:bg-red-900/20 border-l-4 border-red-500 rounded-xl">
                    <i class="fas fa-exclamation-circle text-red-500 text-xl flex-shrink-0 mt-0.5"></i>
                    <div>
                        <div class="font-semibold text-red-800 dark:text-red-200 mb-1">Invalid Link</div>
                        <div class="text-sm text-red-700 dark:text-red-300">This verification link is invalid or missing. Please check your email for the correct link.</div>
                    </div>
                </div>
                <a href="index.php?page=login" class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-br from-blue-500 to-purple-600 text-white font-semibold rounded-xl shadow-lg hover:-translate-y-1 transition-all">
                    <i class="fas fa-sign-in-alt"></i> Go to Login
                </a>
            </div>
            <?php else: ?>
            <form method="POST" action="handlers/verify_handler.php" class="space-y-5" data-ajax-form="true" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                <div class="text-center p-6 bg-blue-50 dark:bg-blue-900/10 rounded-xl border border-blue-100 dark:border-blue-800">
                    <i class="fas fa-paper-plane text-4xl text-blue-500 mb-3 block"></i>
                    <p class="text-gray-700 dark:text-gray-300 text-sm leading-relaxed">
                        Click the button below to verify your email address and activate your DreamBD account.
                    </p>
                </div>

                <button type="submit" name="verify" class="w-full relative overflow-hidden bg-gradient-to-br from-green-500 to-emerald-600 text-white font-semibold py-3 px-6 rounded-xl shadow-lg shadow-green-500/30 hover:shadow-xl hover:shadow-green-500/40 hover:-translate-y-1 disabled:opacity-60 transition-all duration-300 text-base group">
                    <span class="btn-text group-hover:tracking-wide transition-all"><i class="fas fa-check-circle mr-2"></i>Verify Email</span>
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
