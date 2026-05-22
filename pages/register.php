<?php
require_once __DIR__ . '/../includes/security.php';

$security = new Security();
$csrf_token = $security->generateCSRFToken();

$errors = [];
if (isset($_SESSION['form_errors'])) {
    $errors = $_SESSION['form_errors'];
    unset($_SESSION['form_errors']);
}

$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);
?>

<div class="min-h-screen flex items-center justify-center p-4 sm:p-8 bg-gradient-to-br from-slate-50 via-white to-purple-50 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900 transition-colors duration-300" id="authPage">
    <div class="w-full max-w-6xl grid grid-cols-1 lg:grid-cols-2 gap-8 items-start">
        
        <!-- Left Side - Branding -->
        <div class="hidden lg:flex items-center justify-center p-8">
            <div class="max-w-md text-center">
                <div class="inline-flex items-center justify-center w-24 h-24 bg-gradient-to-br from-blue-500 to-purple-600 rounded-3xl shadow-2xl mb-6 transform hover:scale-105 transition-transform duration-300 group">
                    <i class="fas fa-user-plus text-5xl text-white group-hover:animate-pulse"></i>
                </div>
                <h2 class="text-3xl sm:text-4xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">
                    Join DreamBD
                </h2>
                <p class="text-gray-600 dark:text-gray-300 text-lg leading-relaxed mb-8">
                    Create your account and start connecting with thousands of users worldwide.
                </p>
                
                <div class="flex flex-col gap-4 text-left mx-auto max-w-xs">
                    <div class="flex items-center gap-3 text-gray-600 dark:text-gray-300 hover:text-green-600 dark:hover:text-green-400 transition-colors group">
                        <div class="w-12 h-12 bg-green-100 dark:bg-green-900/30 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform">
                            <i class="fas fa-check-circle text-green-600 dark:text-green-400"></i>
                        </div>
                        <span class="text-sm font-medium">Free to join</span>
                    </div>
                    <div class="flex items-center gap-3 text-gray-600 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 transition-colors group">
                        <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900/30 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform">
                            <i class="fas fa-shield-alt text-blue-600 dark:text-blue-400"></i>
                        </div>
                        <span class="text-sm font-medium">Secure registration</span>
                    </div>
                    <div class="flex items-center gap-3 text-gray-600 dark:text-gray-300 hover:text-yellow-600 dark:hover:text-yellow-400 transition-colors group">
                        <div class="w-12 h-12 bg-yellow-100 dark:bg-yellow-900/30 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform">
                            <i class="fas fa-clock text-yellow-600 dark:text-yellow-400"></i>
                        </div>
                        <span class="text-sm font-medium">Quick setup</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side - Register Form -->
        <div class="w-full max-w-lg mx-auto lg:max-w-none">
            <div class="bg-white/90 dark:bg-gray-900/90 backdrop-blur-xl rounded-3xl border border-gray-200/50 dark:border-gray-700/50 shadow-2xl p-6 sm:p-10 relative overflow-hidden transform hover:shadow-3xl transition-all duration-300">
                <!-- Top gradient bar -->
                <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-pink-500 via-purple-500 to-blue-500"></div>
                
                <!-- Header -->
                <div class="text-center mb-8">
                    <div class="inline-flex items-center justify-center w-20 h-20 bg-gradient-to-br from-blue-500 to-purple-600 rounded-2xl shadow-lg mb-4 animate-bounce">
                        <i class="fas fa-user-plus text-3xl text-white"></i>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white mb-2">Create Account</h1>
                    <p class="text-gray-600 dark:text-gray-400">Fill in your details to get started</p>
                </div>

                <!-- Global Error -->
                <?php if (isset($errors['global'])): ?>
                <div class="flex items-start gap-3 p-4 mb-6 bg-red-50 dark:bg-red-900/20 border-l-4 border-red-500 rounded-xl animate-[slideIn_0.3s_ease-out] group">
                    <i class="fas fa-exclamation-circle text-red-500 text-xl flex-shrink-0 mt-0.5 group-hover:rotate-12 transition-transform"></i>
                    <div class="flex-1">
                        <div class="font-semibold text-red-800 dark:text-red-200 mb-1">Registration Failed</div>
                        <div class="text-sm text-red-700 dark:text-red-300"><?php echo htmlspecialchars($errors['global']); ?></div>
                    </div>
                    <button type="button" class="ml-auto text-red-500 hover:text-red-700 dark:hover:text-red-300 opacity-70 hover:opacity-100 transition-all p-1 rounded-lg" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <?php endif; ?>

                <form method="POST" action="handlers/register_handler.php" class="space-y-5" id="registerForm" data-ajax-form="true" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <!-- Full Name -->
                    <div class="<?php echo isset($errors['full_name']) ? 'has-error' : ''; ?>">
                        <div class="relative group">
                            <i class="fas fa-user absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 group-focus-within:text-blue-500 transition-colors text-lg z-10"></i>
                            <input type="text" id="full_name" name="full_name" 
                                   class="w-full min-h-[52px] pl-12 pr-4 py-3 text-base bg-gray-50 dark:bg-gray-800 border-2 <?php echo isset($errors['full_name']) ? 'border-red-500 bg-red-50 dark:bg-red-900/10' : 'border-gray-200 dark:border-gray-700'; ?> rounded-xl text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:border-blue-500  transition-all" 
                                   placeholder="Full name" 
                                   value="<?php echo htmlspecialchars($formData['full_name'] ?? ''); ?>" 
                                   autocomplete="name" 
                                   required>
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Enter your full name as you'd like it to appear</div>
                        <?php if (isset($errors['full_name'])): ?>
                            <div class="flex items-center gap-1.5 text-red-500 text-xs mt-2 pl-1 animate-[slideIn_0.3s_ease-out]">
                                <i class="fas fa-exclamation-circle text-[10px] animate-pulse"></i>
                                <?php echo htmlspecialchars($errors['full_name']); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Username & Email Row -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Username -->
                        <div class="<?php echo isset($errors['username']) ? 'has-error' : ''; ?>">
                            <div class="relative group">
                                <i class="fas fa-at absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 group-focus-within:text-blue-500 transition-colors text-lg z-10"></i>
                                <input type="text" id="username" name="username" 
                                       class="w-full min-h-[52px] pl-12 pr-4 py-3 text-base bg-gray-50 dark:bg-gray-800 border-2 <?php echo isset($errors['username']) ? 'border-red-500 bg-red-50 dark:bg-red-900/10' : 'border-gray-200 dark:border-gray-700'; ?> rounded-xl text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:border-blue-500  transition-all" 
                                       placeholder="Username" 
                                       value="<?php echo htmlspecialchars($formData['username'] ?? ''); ?>" 
                                       autocomplete="username" 
                                       required>
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">3-20 characters, letters and numbers only</div>
                            <?php if (isset($errors['username'])): ?>
                                <div class="flex items-center gap-1.5 text-red-500 text-xs mt-2 pl-1 animate-[slideIn_0.3s_ease-out]">
                                    <i class="fas fa-exclamation-circle text-[10px] animate-pulse"></i>
                                    <?php echo htmlspecialchars($errors['username']); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Email -->
                        <div class="<?php echo isset($errors['email']) ? 'has-error' : ''; ?>">
                            <div class="relative group">
                                <i class="fas fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 group-focus-within:text-blue-500 transition-colors text-lg z-10"></i>
                                <input type="email" id="email" name="email" 
                                       class="w-full min-h-[52px] pl-12 pr-4 py-3 text-base bg-gray-50 dark:bg-gray-800 border-2 <?php echo isset($errors['email']) ? 'border-red-500 bg-red-50 dark:bg-red-900/10' : 'border-gray-200 dark:border-gray-700'; ?> rounded-xl text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:border-blue-500  transition-all" 
                                       placeholder="Email address" 
                                       value="<?php echo htmlspecialchars($formData['email'] ?? ''); ?>" 
                                       autocomplete="email" 
                                       required>
                            </div>
                            <?php if (isset($errors['email'])): ?>
                                <div class="flex items-center gap-1.5 text-red-500 text-xs mt-2 pl-1 animate-[slideIn_0.3s_ease-out]">
                                    <i class="fas fa-exclamation-circle text-[10px] animate-pulse"></i>
                                    <?php echo htmlspecialchars($errors['email']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Password & Confirm Row -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Password -->
                        <div class="<?php echo isset($errors['password']) ? 'has-error' : ''; ?>" id="passwordStrengthContainer">
                            <div class="relative group">
                                <i class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 group-focus-within:text-blue-500 transition-colors text-lg z-10"></i>
                                <input type="password" id="password" name="password" 
                                       class="w-full min-h-[52px] pl-12 pr-12 py-3 text-base bg-gray-50 dark:bg-gray-800 border-2 <?php echo isset($errors['password']) ? 'border-red-500 bg-red-50 dark:bg-red-900/10' : 'border-gray-200 dark:border-gray-700'; ?> rounded-xl text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:border-blue-500  transition-all" 
                                       placeholder="Create password" 
                                       autocomplete="new-password" 
                                       required>
                                <button type="button" class="password-toggle absolute right-3 top-1/2 -translate-y-1/2 bg-transparent border-none text-gray-400 hover:text-blue-500 cursor-pointer p-2 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/30 transition-all flex items-center" data-target="password" aria-label="Show password">
                                    <i class="fas fa-eye text-lg"></i>
                                </button>
                            </div>
                            <!-- Password Strength Meter -->
                            <div id="passwordStrength" class="mt-2 p-3 rounded-xl border transition-all duration-300 <?php echo isset($errors['password']) ? 'bg-red-50 dark:bg-red-900/10 border-red-100 dark:border-red-800' : 'bg-blue-50 dark:bg-blue-900/10 border-blue-100 dark:border-blue-800'; ?>">
                                <div class="h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden mb-2">
                                    <div class="h-full rounded-full transition-all duration-300" id="strengthFill" style="width: 0%"></div>
                                </div>
                                <div class="flex justify-between text-xs">
                                    <span class="text-gray-500 dark:text-gray-400">Strength:</span>
                                    <span class="font-semibold transition-colors duration-300" id="strengthText">None</span>
                                </div>
                            </div>
                            <?php if (isset($errors['password'])): ?>
                                <div class="flex items-center gap-1.5 text-red-500 text-xs mt-2 pl-1 animate-[slideIn_0.3s_ease-out]">
                                    <i class="fas fa-exclamation-circle text-[10px] animate-pulse"></i>
                                    <?php echo htmlspecialchars($errors['password']); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Confirm Password -->
                        <div class="<?php echo isset($errors['confirm_password']) ? 'has-error' : ''; ?>">
                            <div class="relative group">
                                <i class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 group-focus-within:text-blue-500 transition-colors text-lg z-10"></i>
                                <input type="password" id="confirm_password" name="confirm_password" 
                                       class="w-full min-h-[52px] pl-12 pr-12 py-3 text-base bg-gray-50 dark:bg-gray-800 border-2 <?php echo isset($errors['confirm_password']) ? 'border-red-500 bg-red-50 dark:bg-red-900/10' : 'border-gray-200 dark:border-gray-700'; ?> rounded-xl text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:border-blue-500  transition-all" 
                                       placeholder="Confirm password" 
                                       autocomplete="new-password" 
                                       required>
                                <button type="button" class="password-toggle absolute right-3 top-1/2 -translate-y-1/2 bg-transparent border-none text-gray-400 hover:text-blue-500 cursor-pointer p-2 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/30 transition-all flex items-center" data-target="confirm_password" aria-label="Show password">
                                    <i class="fas fa-eye text-lg"></i>
                                </button>
                            </div>
                            <!-- Password Match Indicator -->
                            <div id="passwordMatch" class="mt-2 hidden">
                                <div class="flex items-center gap-2 text-xs font-medium" id="matchIndicator">
                                    <i class="fas" id="matchIcon"></i>
                                    <span id="matchText"></span>
                                </div>
                            </div>
                            <?php if (isset($errors['confirm_password'])): ?>
                                <div class="flex items-center gap-1.5 text-red-500 text-xs mt-2 pl-1 animate-[slideIn_0.3s_ease-out]">
                                    <i class="fas fa-exclamation-circle text-[10px] animate-pulse"></i>
                                    <?php echo htmlspecialchars($errors['confirm_password']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Phone with Country Code -->
                    <div class="<?php echo isset($errors['phone']) ? 'has-error' : ''; ?>">
                        <div class="flex gap-2">
                            <!-- Country Code Selector -->
                            <div class="relative w-32 flex-shrink-0" id="countryCodeContainer">
                                <button type="button" id="countryCodeBtn" class="w-full h-full min-h-[52px] px-3 py-3 bg-gray-50 dark:bg-gray-800 border-2 <?php echo isset($errors['phone']) ? 'border-red-500 bg-red-50 dark:bg-red-900/10' : 'border-gray-200 dark:border-gray-700'; ?> rounded-xl text-gray-900 dark:text-white focus:outline-none focus:border-blue-500  transition-all flex items-center justify-between gap-1 text-sm">
                                    <span id="selectedCode">+1</span>
                                    <i class="fas fa-chevron-down text-gray-400 text-xs"></i>
                                </button>
                                <input type="hidden" id="country_code" name="country_code" value="+1">
                                <!-- Dropdown -->
                                <div id="countryDropdown" class="hidden absolute z-50 w-64 max-h-60 overflow-y-auto bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-2xl mt-1 left-0">
                                    <input type="text" id="countrySearch" placeholder="Search country..." class="w-full px-3 py-2 text-sm border-b border-gray-200 dark:border-gray-700 focus:outline-none bg-transparent">
                                    <div id="countryList" class="py-1"></div>
                                </div>
                            </div>
                            <!-- Phone Input -->
                            <div class="relative group flex-1">
                                <i class="fas fa-phone absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 group-focus-within:text-blue-500 transition-colors text-lg z-10"></i>
                                <input type="tel" id="phone" name="phone" 
                                       class="w-full min-h-[52px] pl-12 pr-4 py-3 text-base bg-gray-50 dark:bg-gray-800 border-2 <?php echo isset($errors['phone']) ? 'border-red-500 bg-red-50 dark:bg-red-900/10' : 'border-gray-200 dark:border-gray-700'; ?> rounded-xl text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:border-blue-500  transition-all" 
                                       placeholder="Phone number" 
                                       value="<?php echo htmlspecialchars($formData['phone'] ?? ''); ?>" 
                                       autocomplete="tel">
                            </div>
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">For tournament notifications</div>
                        <?php if (isset($errors['phone'])): ?>
                            <div class="flex items-center gap-1.5 text-red-500 text-xs mt-2 pl-1 animate-[slideIn_0.3s_ease-out]">
                                <i class="fas fa-exclamation-circle text-[10px] animate-pulse"></i>
                                <?php echo htmlspecialchars($errors['phone']); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Terms & Privacy -->
                    <div class="space-y-3">
                        <label class="flex items-start gap-3 cursor-pointer select-none relative pl-8 min-h-[1.5rem] leading-normal group">
                            <input type="checkbox" name="agree_terms" id="agree_terms" class="absolute opacity-0 cursor-pointer h-0 w-0">
                            <span class="checkbox-custom absolute left-0 top-0 h-5 w-5 bg-gray-100 dark:bg-gray-800 border-2 border-gray-300 dark:border-gray-600 rounded transition-all group-hover:border-blue-500"></span>
                            <span class="checkbox-label text-sm text-gray-600 dark:text-gray-300">I agree to the <a href="index.php?page=terms" target="_blank" class="text-blue-500 hover:underline">Terms of Service</a></span>
                        </label>
                        <?php if (isset($errors['agree_terms'])): ?>
                            <div class="flex items-center gap-1.5 text-red-500 text-xs pl-1">
                                <i class="fas fa-exclamation-circle text-[10px]"></i>
                                <?php echo htmlspecialchars($errors['agree_terms']); ?>
                            </div>
                        <?php endif; ?>

                        <label class="flex items-start gap-3 cursor-pointer select-none relative pl-8 min-h-[1.5rem] leading-normal group">
                            <input type="checkbox" name="agree_privacy" id="agree_privacy" class="absolute opacity-0 cursor-pointer h-0 w-0">
                            <span class="checkbox-custom absolute left-0 top-0 h-5 w-5 bg-gray-100 dark:bg-gray-800 border-2 border-gray-300 dark:border-gray-600 rounded transition-all group-hover:border-blue-500"></span>
                            <span class="checkbox-label text-sm text-gray-600 dark:text-gray-300">I agree to the <a href="index.php?page=privacy" target="_blank" class="text-blue-500 hover:underline">Privacy Policy</a></span>
                        </label>
                        <?php if (isset($errors['agree_privacy'])): ?>
                            <div class="flex items-center gap-1.5 text-red-500 text-xs pl-1">
                                <i class="fas fa-exclamation-circle text-[10px]"></i>
                                <?php echo htmlspecialchars($errors['agree_privacy']); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="w-full relative overflow-hidden bg-gradient-to-br from-blue-500 to-purple-600 text-white font-semibold py-3 px-6 rounded-xl shadow-lg shadow-blue-500/30 hover:shadow-xl hover:shadow-blue-500/40 hover:-translate-y-1 disabled:opacity-60 disabled:cursor-not-allowed disabled:hover:translate-y-0 transition-all duration-300 text-base group" id="registerBtn">
                        <span class="btn-text group-hover:tracking-wide transition-all">Create Account</span>
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

                    <!-- Login Link -->
                    <a href="index.php?page=login" class="w-full inline-flex items-center justify-center gap-2 px-6 py-3 text-base font-semibold border-2 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white rounded-xl hover:border-blue-500 hover:text-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/10 hover:-translate-y-1 transition-all text-center group">
                        <i class="fas fa-sign-in-alt group-hover:scale-110 transition-transform"></i>
                        Already have an account? Sign In
                    </a>
                </form>
            </div>
        </div>
    </div>
</div>
