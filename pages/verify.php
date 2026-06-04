<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';

dream_start_session();

$token = $_GET['token'] ?? '';
$verified = false;

if (!empty($token)) {
    $auth = new Auth();
    $result = $auth->verifyEmail($token);
    $verified = $result['success'];
}
?>
<div class="min-h-screen flex items-center justify-center p-4 bg-gray-100 dark:bg-gray-900">
    <div class="w-full max-w-sm">

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 p-6 text-center">
            
            <?php if ($verified): ?>
            <div class="inline-flex items-center justify-center w-16 h-16 bg-green-100 dark:bg-green-900/30 rounded-full mb-4">
                <i class="fas fa-check-circle text-3xl text-green-500"></i>
            </div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Email Verified!</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Your email has been verified successfully. You can now log in to your account.</p>
            <a href="index.php?page=login" class="inline-block w-full py-2.5 px-4 bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm rounded-lg transition-colors">
                <i class="fas fa-sign-in-alt mr-2"></i>Go to Login
            </a>
            <?php else: ?>
            <div class="inline-flex items-center justify-center w-16 h-16 bg-red-100 dark:bg-red-900/30 rounded-full mb-4">
                <i class="fas fa-exclamation-circle text-3xl text-red-500"></i>
            </div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Verification Failed</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">This link is invalid or has expired. Please request a new verification email.</p>
            <a href="index.php?page=login" class="inline-block w-full py-2.5 px-4 bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm rounded-lg transition-colors">
                <i class="fas fa-sign-in-alt mr-2"></i>Go to Login
            </a>
            <?php endif; ?>

        </div>
    </div>
</div>
