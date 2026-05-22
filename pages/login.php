<?php
require_once __DIR__ . '/../includes/security.php';

$security = new Security();
$csrf_token = $security->generateCSRFToken();

$saved_identifier = '';
if (isset($_COOKIE['remember_identifier'])) {
    $saved_identifier = htmlspecialchars($_COOKIE['remember_identifier']);
}

$errors = [];
if (isset($_SESSION['form_errors'])) {
    $errors = $_SESSION['form_errors'];
    unset($_SESSION['form_errors']);
}

$success_msg = '';
if (isset($_GET['registered']) && $_GET['registered'] === 'true') {
    $success_msg = 'Registration successful! You can now log in.';
}
?>

<div class="min-h-screen flex items-center justify-center p-4 sm:p-8 bg-gradient-to-br from-slate-50 via-white to-purple-50 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900 transition-colors duration-300" id="authPage">
    <div class="w-full max-w-6xl grid grid-cols-1 lg:grid-cols-2 gap-8 items-center">
        
        <!-- Left Side - Branding -->
        <div class="hidden lg:flex items-center justify-center p-8">
            <div class="max-w-md text-center">
                <div class="inline-flex items-center justify-center w-24 h-24 bg-gradient-to-br from-blue-500 to-purple-600 rounded-3xl shadow-2xl mb-6 transform hover:scale-105 transition-all duration-300 group">
                    <i class="fas fa-comments text-5xl text-white group-hover:animate-pulse"></i>
                </div>
                <h2 class="text-3xl sm:text-4xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">
                    Welcome to DreamBD
                </h2>
                <p class="text-gray-600 dark:text-gray-300 text-lg leading-relaxed mb-8">
                    Connect with friends, share moments, and discover amazing content from around the world.
                </p>
                
                <div class="flex flex-col gap-4 text-left mx-auto max-w-xs">
                    <div class="flex items-center gap-3 text-gray-600 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 transition-colors group">
                        <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900/30 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform">
                            <i class="fas fa-shield-alt text-blue-500 group-hover:text-blue-600"></i>
                        </div>
                        <span class="text-sm font-medium">Secure & Private</span>
                    </div>
                    <div class="flex items-center gap-3 text-gray-600 dark:text-gray-300 hover:text-yellow-600 dark:hover:text-yellow-400 transition-colors group">
                        <div class="w-12 h-12 bg-yellow-100 dark:bg-yellow-900/30 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform">
                            <i class="fas fa-bolt text-yellow-500 group-hover:text-yellow-600"></i>
                        </div>
                        <span class="text-sm font-medium">Lightning Fast</span>
                    </div>
                    <div class="flex items-center gap-3 text-gray-600 dark:text-gray-300 hover:text-green-600 dark:hover:text-green-400 transition-colors group">
                        <div class="w-12 h-12 bg-green-100 dark:bg-green-900/30 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform">
                            <i class="fas fa-users text-green-500 group-hover:text-green-600"></i>
                        </div>
                        <span class="text-sm font-medium">Community Driven</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side - Login Form -->
        <div class="w-full max-w-md mx-auto">
            <div class="bg-white/95 dark:bg-gray-900/95 backdrop-blur-xl rounded-3xl border border-gray-200/50 dark:border-gray-700/50 shadow-2xl p-6 sm:p-10 relative overflow-hidden transform hover:shadow-3xl transition-all duration-300">
                <!-- Top gradient bar -->
                <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-blue-500 via-purple-500 to-blue-500"></div>
                
                <!-- Header -->
                <div class="text-center mb-8">
                    <div class="inline-flex items-center justify-center w-20 h-20 bg-gradient-to-br from-blue-500 to-purple-600 rounded-2xl shadow-lg mb-4 animate-bounce">
                        <i class="fas fa-sign-in-alt text-3xl text-white"></i>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white mb-2">Sign In</h1>
                    <p class="text-gray-600 dark:text-gray-400">Access your DreamBD account</p>
                </div>

                <!-- Success Message -->
                <?php if ($success_msg): ?>
                <div class="flex items-start gap-3 p-4 mb-6 bg-green-50 dark:bg-green-900/20 border-l-4 border-green-500 rounded-xl animate-[slideIn_0.3s_ease-out] group">
                    <i class="fas fa-check-circle text-green-500 text-xl flex-shrink-0 mt-0.5 group-hover:rotate-12 transition-transform"></i>
                    <div class="flex-1">
                        <div class="font-semibold text-green-800 dark:text-green-200 mb-1">Success!</div>
                        <div class="text-sm text-green-700 dark:text-green-300"><?php echo htmlspecialchars($success_msg); ?></div>
                    </div>
                    <button type="button" class="ml-auto text-green-500 hover:text-green-700 dark:hover:text-green-300 opacity-70 hover:opacity-100 transition-all p-1 rounded-lg" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <?php endif; ?>

                <!-- Error Message -->
                <?php if (isset($errors['global'])): ?>
                <div class="flex items-start gap-3 p-4 mb-6 bg-red-50 dark:bg-red-900/20 border-l-4 border-red-500 rounded-xl animate-[slideIn_0.3s_ease-out] group">
                    <i class="fas fa-exclamation-circle text-red-500 text-xl flex-shrink-0 mt-0.5 group-hover:rotate-12 transition-transform"></i>
                    <div class="flex-1">
                        <div class="font-semibold text-red-800 dark:text-red-200 mb-1">Error</div>
                        <div class="text-sm text-red-700 dark:text-red-300"><?php echo htmlspecialchars($errors['global']); ?></div>
                    </div>
                    <button type="button" class="ml-auto text-red-500 hover:text-red-700 dark:hover:text-red-300 opacity-70 hover:opacity-100 transition-all p-1 rounded-lg" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <?php endif; ?>

                <form method="POST" action="handlers/login_handler.php" class="space-y-5" id="loginForm" data-ajax-form="true" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <!-- Identifier Input -->
                    <div class="<?php echo isset($errors['identifier']) ? 'has-error' : ''; ?>">
                        <div class="relative group">
                            <i class="fas fa-user absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 group-focus-within:text-blue-500 transition-colors text-lg z-10"></i>
                            <input type="text" 
                                   id="identifier" 
                                   name="identifier" 
                                   class="w-full min-h-[52px] pl-12 pr-4 py-3 text-base bg-gray-50 dark:bg-gray-800 border-2 <?php echo isset($errors['identifier']) ? 'border-red-500 bg-red-50 dark:bg-red-900/10' : 'border-gray-200 dark:border-gray-700'; ?> rounded-xl text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:border-blue-500  transition-all"
                                   placeholder="Email, username, or phone"
                                   value="<?php echo htmlspecialchars($saved_identifier); ?>"
                                   autocomplete="username"
                                   required>
                        </div>
                        <?php if (isset($errors['identifier'])): ?>
                            <div class="flex items-center gap-1.5 text-red-500 text-xs mt-2 pl-1 animate-[slideIn_0.3s_ease-out]">
                                <i class="fas fa-exclamation-circle text-[10px] animate-pulse"></i>
                                <?php echo htmlspecialchars($errors['identifier']); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Password Input -->
                    <div class="<?php echo isset($errors['password']) ? 'has-error' : ''; ?>">
                        <div class="relative group">
                            <i class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 group-focus-within:text-blue-500 transition-colors text-lg z-10"></i>
                            <input type="password" 
                                   id="password" 
                                   name="password" 
                                   class="w-full min-h-[52px] pl-12 pr-12 py-3 text-base bg-gray-50 dark:bg-gray-800 border-2 <?php echo isset($errors['password']) ? 'border-red-500 bg-red-50 dark:bg-red-900/10' : 'border-gray-200 dark:border-gray-700'; ?> rounded-xl text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:border-blue-500 transition-all"
                                   placeholder="Enter your password"
                                   autocomplete="current-password"
                                   required>
                            <button type="button" class="password-toggle absolute right-3 top-1/2 -translate-y-1/2 bg-transparent border-none text-gray-400 hover:text-blue-500 cursor-pointer p-2 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/30 transition-all flex items-center" data-target="password" aria-label="Show password">
                                <i class="fas fa-eye text-lg"></i>
                            </button>
                        </div>
                        <?php if (isset($errors['password'])): ?>
                            <div class="flex items-center gap-1.5 text-red-500 text-xs mt-2 pl-1 animate-[slideIn_0.3s_ease-out]">
                                <i class="fas fa-exclamation-circle text-[10px] animate-pulse"></i>
                                <?php echo htmlspecialchars($errors['password']); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Options -->
                    <div class="flex justify-between items-center flex-wrap gap-4">
                        <label class="flex items-start gap-3 cursor-pointer select-none relative pl-8 min-h-[1.5rem] leading-normal group">
                            <input type="checkbox" name="remember_me" id="remember_me" class="absolute opacity-0 cursor-pointer h-0 w-0">
                            <span class="checkbox-custom absolute left-0 top-0 h-5 w-5 bg-gray-100 dark:bg-gray-800 border-2 border-gray-300 dark:border-gray-600 rounded transition-all group-hover:border-blue-500"></span>
                            <span class="checkbox-label text-sm text-gray-600 dark:text-gray-300">Remember me</span>
                        </label>
                        <a href="#" class="text-blue-500 no-underline text-sm font-medium hover:text-blue-600 hover:underline transition-all" data-modal="forgotPassword">
                            Forgot password?
                        </a>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="w-full relative overflow-hidden bg-gradient-to-br from-blue-500 to-purple-600 text-white font-semibold py-3 px-6 rounded-xl shadow-lg shadow-blue-500/30 hover:shadow-xl hover:shadow-blue-500/40 hover:-translate-y-1 disabled:opacity-60 disabled:cursor-not-allowed disabled:hover:translate-y-0 transition-all duration-300 text-base group" id="loginBtn">
                        <span class="btn-text group-hover:tracking-wide transition-all">Sign In</span>
                        <div class="btn-loader hidden absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2">
                            <div class="w-6 h-6 border-2 border-white/30 rounded-full border-t-white animate-spin"></div>
                        </div>
                    </button>

                    <!-- Divider -->
                    <div class="flex items-center gap-4 my-6">
                        <div class="flex-1 h-px bg-gradient-to-r from-transparent via-gray-300 dark:via-gray-600 to-transparent"></div>
                        <span class="text-xs text-gray-500 dark:text-gray-400 font-medium">or</span>
                        <div class="flex-1 h-px bg-gradient-to-r from-transparent via-gray-300 dark:via-gray-600 to-transparent"></div>
                    </div>

                    <!-- Register Link -->
                    <a href="index.php?page=register" class="w-full inline-flex items-center justify-center gap-2 px-6 py-3 text-base font-semibold border-2 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white rounded-xl hover:border-blue-500 hover:text-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/10 hover:-translate-y-1 transition-all text-center group">
                        <i class="fas fa-user-plus group-hover:scale-110 transition-transform"></i>
                        Create New Account
                    </a>
                </form>
            </div>
        </div>
    </div>
</div>
