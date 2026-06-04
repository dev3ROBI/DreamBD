// ===== AUTHENTICATION FORM HANDLER =====

class AuthHandler {
    constructor() {
        this.isInitialized = false;
    }
    
    init() {
        if (this.isInitialized) return;
        this.isInitialized = true;
        
        // Handle all auth form submissions
        document.addEventListener('submit', (e) => {
            const form = e.target;
            if (!form || !form.hasAttribute('data-ajax-form')) return;
            e.preventDefault();
            this.handleAuthFormSubmit(form);
        });
        
        this.initPasswordToggles();
        this.initModals();
        this.initPageEnhancements();
        this.initForgotPasswordBackBtn();
    }
    
    initPageEnhancements() {
        // Initialize password strength meter if on register page
        this.initPasswordStrengthMeter();
        
        // Initialize password match indicator
        this.initPasswordMatchIndicator();
        
        // Initialize country code selector
        this.initCountryCodeSelector();
        
        // Initialize custom checkboxes
        this.initCustomCheckboxes();
    }
    
    initPasswordToggles() {
        document.addEventListener('click', (e) => {
            const toggleBtn = e.target.closest('.password-toggle');
            if (!toggleBtn) return;
            
            e.preventDefault();
            e.stopPropagation();
            
            const targetId = toggleBtn.getAttribute('data-target');
            const input = document.getElementById(targetId);
            if (!input) return;
            
            const icon = toggleBtn.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                if (icon) {
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                }
                toggleBtn.setAttribute('aria-label', 'Hide password');
            } else {
                input.type = 'password';
                if (icon) {
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
                toggleBtn.setAttribute('aria-label', 'Show password');
            }
            
            // Keep focus on input
            input.focus();
        });
    }
    
    initModals() {
        // Open modal triggers
        document.addEventListener('click', (e) => {
            const trigger = e.target.closest('[data-modal]');
            if (!trigger) return;
            
            e.preventDefault();
            e.stopPropagation();
            
            const modalId = trigger.getAttribute('data-modal') + 'Modal';
            this.openModal(modalId);
        });
        
        // Close modal triggers
        document.addEventListener('click', (e) => {
            const closeBtn = e.target.closest('[data-modal-close]');
            if (closeBtn) {
                e.preventDefault();
                e.stopPropagation();
                const modalId = closeBtn.getAttribute('data-modal-close') + 'Modal';
                this.closeModal(modalId);
                return;
            }
            
            // Close when clicking outside
            const modal = e.target.closest('.auth-modal');
            if (modal && e.target === modal) {
                this.closeModal(modal.id);
            }
        });
        
        // Close with Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const activeModal = document.querySelector('.auth-modal.active');
                if (activeModal) {
                    this.closeModal(activeModal.id);
                }
            }
        });
    }

    initForgotPasswordBackBtn() {
        document.addEventListener('click', (e) => {
            const btnBack = e.target.closest('#forgotBackBtn');
            const btnBackOtp = e.target.closest('#forgotBackToOtpBtn');
            if (!btnBack && !btnBackOtp) return;
            e.preventDefault();
            const stepEmail = document.getElementById('forgotStepEmail');
            const stepOtp = document.getElementById('forgotStepOtp');
            const stepPassword = document.getElementById('forgotStepPassword');
            const desc = document.getElementById('forgotDesc');
            if (btnBack && stepEmail && stepOtp) {
                stepOtp.style.display = 'none';
                stepEmail.style.display = 'block';
                if (desc) desc.textContent = 'Enter your email to receive an OTP';
            }
            if (btnBackOtp && stepOtp && stepPassword) {
                stepPassword.style.display = 'none';
                stepOtp.style.display = 'block';
                if (desc) desc.textContent = 'Enter the OTP sent to your email';
            }
        });
    }
    
    initPasswordStrengthMeter() {
        const passwordInput = document.getElementById('password');
        if (!passwordInput) return;
        
        const strengthFill = document.getElementById('strengthFill');
        const strengthText = document.getElementById('strengthText');
        if (!strengthFill || !strengthText) return;
        
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            const strength = checkPasswordStrength(password);
            
            strengthFill.style.width = strength.percent + '%';
            strengthFill.className = 'h-full rounded-full transition-all duration-300 ' + strength.fillClass;
            strengthText.textContent = strength.label;
            strengthText.style.color = strength.color;
        });
    }
    
    initPasswordMatchIndicator() {
        const passwordInput = document.getElementById('password');
        const confirmInput = document.getElementById('confirm_password');
        const matchDiv = document.getElementById('passwordMatch');
        const matchText = document.getElementById('matchText');
        
        if (!confirmInput || !matchDiv) return;
        
        const checkMatch = () => {
            const pass = passwordInput?.value || '';
            const confirm = confirmInput.value;
            
            if (!confirm) {
                matchDiv.classList.add('hidden');
                return;
            }
            
            matchDiv.classList.remove('hidden');
            
            if (pass === confirm && pass.length > 0) {
                matchText.innerHTML = '<span class="text-green-600 dark:text-green-400"><i class="fas fa-check-circle"></i> Passwords match</span>';
            } else {
                matchText.innerHTML = '<span class="text-red-500"><i class="fas fa-times-circle"></i> Passwords do not match</span>';
            }
        };
        
        confirmInput.addEventListener('input', checkMatch);
        if (passwordInput) {
            passwordInput.addEventListener('input', checkMatch);
        }
    }
    
    initCountryCodeSelector() {
        const btn = document.getElementById('countryCodeBtn');
        const dropdown = document.getElementById('countryDropdown');
        const countryList = document.getElementById('countryList');
        const countrySearch = document.getElementById('countrySearch');
        const selectedCode = document.getElementById('selectedCode');
        const countryCodeInput = document.getElementById('country_code');
        
        if (!btn || !dropdown || !countryList) return;
        
        // All countries with codes
        const countries = [
            { name: 'United States', code: '+1', flag: '🇺🇸' },
            { name: 'Canada', code: '+1', flag: '🇨🇦' },
            { name: 'United Kingdom', code: '+44', flag: '🇬🇧' },
            { name: 'Germany', code: '+49', flag: '🇩🇪' },
            { name: 'France', code: '+33', flag: '🇫🇷' },
            { name: 'Italy', code: '+39', flag: '🇮🇹' },
            { name: 'Spain', code: '+34', flag: '🇪🇸' },
            { name: 'Australia', code: '+61', flag: '🇦🇺' },
            { name: 'Japan', code: '+81', flag: '🇯🇵' },
            { name: 'China', code: '+86', flag: '🇨🇳' },
            { name: 'India', code: '+91', flag: '🇮🇳' },
            { name: 'Brazil', code: '+55', flag: '🇧🇷' },
            { name: 'Mexico', code: '+52', flag: '🇲🇽' },
            { name: 'South Korea', code: '+82', flag: '🇰🇷' },
            { name: 'Russia', code: '+7', flag: '🇷🇺' },
            { name: 'Netherlands', code: '+31', flag: '🇳🇱' },
            { name: 'Sweden', code: '+46', flag: '🇸🇪' },
            { name: 'Norway', code: '+47', flag: '🇳🇴' },
            { name: 'Denmark', code: '+45', flag: '🇩🇰' },
            { name: 'Finland', code: '+358', flag: '🇫🇮' },
            { name: 'Poland', code: '+48', flag: '🇵🇱' },
            { name: 'Turkey', code: '+90', flag: '🇹🇷' },
            { name: 'Saudi Arabia', code: '+966', flag: '🇸🇦' },
            { name: 'UAE', code: '+971', flag: '🇦🇪' },
            { name: 'Israel', code: '+972', flag: '🇮🇱' },
            { name: 'Singapore', code: '+65', flag: '🇸🇬' },
            { name: 'Thailand', code: '+66', flag: '🇹🇭' },
            { name: 'Vietnam', code: '+84', flag: '🇻🇳' },
            { name: 'Philippines', code: '+63', flag: '🇵🇭' },
            { name: 'Malaysia', code: '+60', flag: '🇲🇾' },
            { name: 'Indonesia', code: '+62', flag: '🇮🇩' },
            { name: 'Pakistan', code: '+92', flag: '🇵🇰' },
            { name: 'Bangladesh', code: '+880', flag: '🇧🇩' },
            { name: 'Sri Lanka', code: '+94', flag: '🇱🇰' },
            { name: 'Nepal', code: '+977', flag: '🇳🇵' },
            { name: 'Egypt', code: '+20', flag: '🇪🇬' },
            { name: 'South Africa', code: '+27', flag: '🇿🇦' },
            { name: 'Nigeria', code: '+234', flag: '🇳🇬' },
            { name: 'Kenya', code: '+254', flag: '🇰🇪' },
            { name: 'Ghana', code: '+233', flag: '🇬🇭' },
            { name: 'Morocco', code: '+212', flag: '🇲🇦' },
            { name: 'Argentina', code: '+54', flag: '🇦🇷' },
            { name: 'Chile', code: '+56', flag: '🇨🇱' },
            { name: 'Colombia', code: '+57', flag: '🇨🇴' },
            { name: 'Peru', code: '+51', flag: '🇵🇪' },
            { name: 'New Zealand', code: '+64', flag: '🇳🇿' }
        ];
        
        // Populate list
        const renderList = (filter = '') => {
            const filtered = countries.filter(country => 
                country.name.toLowerCase().includes(filter.toLowerCase()) ||
                country.code.includes(filter)
            );
            
            countryList.innerHTML = filtered.map(country => `
                <div class="country-item px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer flex items-center gap-2 text-sm transition-colors" data-code="${country.code}">
                    <span class="text-base">${country.flag}</span>
                    <span class="flex-1 text-gray-900 dark:text-white">${country.name}</span>
                    <span class="text-gray-500 dark:text-gray-400">${country.code}</span>
                </div>
            `).join('');
            
            // Add click handlers
            countryList.querySelectorAll('.country-item').forEach(item => {
                item.addEventListener('click', () => {
                    const code = item.dataset.code;
                    selectedCode.textContent = code;
                    countryCodeInput.value = code;
                    dropdown.classList.add('hidden');
                    countrySearch.value = '';
                    renderList();
                });
            });
        };
        
        renderList();
        
        // Toggle dropdown
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            dropdown.classList.toggle('hidden');
            if (!dropdown.classList.contains('hidden')) {
                countrySearch.focus();
            }
        });
        
        // Search
        countrySearch.addEventListener('input', (e) => {
            renderList(e.target.value);
        });
        
        // Close on outside click
        document.addEventListener('click', (e) => {
            if (!btn.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.classList.add('hidden');
            }
        });
    }
    
    initCustomCheckboxes() {
        document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
            // Skip if already initialized
            if (checkbox.dataset.enhanced) return;
            checkbox.dataset.enhanced = 'true';
            
            checkbox.addEventListener('change', function() {
                const customBox = this.parentElement.querySelector('.checkbox-custom');
                if (!customBox) return;
                
                if (this.checked) {
                    customBox.classList.add('bg-blue-500', 'border-blue-500');
                    customBox.innerHTML = '<i class="fas fa-check text-white text-xs absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2"></i>';
                } else {
                    customBox.classList.remove('bg-blue-500', 'border-blue-500');
                    customBox.innerHTML = '';
                }
            });
            
            // Trigger on load if checked
            if (checkbox.checked) {
                checkbox.dispatchEvent(new Event('change'));
            }
        });
    }
    
    initFocusEffects() {
        document.querySelectorAll('input[type="text"], input[type="email"], input[type="password"], input[type="tel"]').forEach(input => {
            // Skip if already initialized
            if (input.dataset.focusEnhanced) return;
            input.dataset.focusEnhanced = 'true';
            
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('ring-4', 'ring-blue-500/20');
            });
            input.addEventListener('blur', function() {
                this.parentElement.classList.remove('ring-4', 'ring-blue-500/20');
            });
        });
    }
    
    openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        
        document.body.style.overflow = 'hidden';
        
        const overlay = document.getElementById('modalOverlay');
        if (overlay) {
            overlay.classList.remove('hidden');
            overlay.classList.add('active');
        }
        
        modal.classList.remove('hidden');
        modal.style.display = 'flex';
        setTimeout(() => {
            modal.classList.add('active');
        }, 10);
        
        const firstInput = modal.querySelector('input');
        if (firstInput) {
            setTimeout(() => firstInput.focus(), 50);
        }
    }
    
    closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;

        // Reset forgot password steps
        if (modalId === 'forgotPasswordModal') {
            const stepEmail = document.getElementById('forgotStepEmail');
            const stepOtp = document.getElementById('forgotStepOtp');
            const stepPassword = document.getElementById('forgotStepPassword');
            const desc = document.getElementById('forgotDesc');
            if (stepOtp) stepOtp.style.display = 'none';
            if (stepPassword) stepPassword.style.display = 'none';
            if (stepEmail) stepEmail.style.display = 'block';
            if (desc) desc.textContent = 'Enter your email to receive an OTP';
        }
        
        modal.classList.remove('active');
        document.body.style.overflow = '';
        
        const activeModals = document.querySelectorAll('.auth-modal.active');
        if (activeModals.length === 0) {
            const overlay = document.getElementById('modalOverlay');
            if (overlay) {
                overlay.classList.add('hidden');
                overlay.classList.remove('active');
            }
        }
        
        setTimeout(() => {
            modal.classList.add('hidden');
            modal.style.display = '';
        }, 300);
    }
    
    async handleAuthFormSubmit(form) {
        if (!this.validateForm(form)) return;

        const recaptchaError = form.querySelector('#recaptchaError');
        if (recaptchaError) {
            recaptchaError.textContent = '';
            recaptchaError.classList.add('hidden');
        }
        
        const formData = new FormData(form);
        const submitBtn = form.querySelector('[type="submit"]');
        const formAction = form.getAttribute('action') || form.action;
        
        // Loading state
        if (submitBtn) {
            submitBtn.disabled = true;
            const text = submitBtn.querySelector('.btn-text');
            const loader = submitBtn.querySelector('.btn-loader');
            if (text) text.classList.add('hidden');
            if (loader) loader.classList.remove('hidden');
        }
        
        // Copy forgot password email to hidden field before submit
        if (form.id === 'forgotPasswordForm') {
            const visibleEmail = form.querySelector('[name="email_visible"]');
            const hiddenEmail = form.querySelector('#forgotEmailHidden');
            if (visibleEmail && hiddenEmail) {
                hiddenEmail.value = visibleEmail.value;
            }
        }

        try {
            const response = await fetch(formAction, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Auth-Submission': 'true'
                }
            });
            
            let result;
            try {
                result = await response.json();
            } catch (e) {
                
                
                this.showAlert(form, 'Server error. Please try again.', 'error');
                return;
            }
            
            if (result.success) {
                const stepEmail = form.querySelector('#forgotStepEmail');
                const stepOtp = form.querySelector('#forgotStepOtp');
                const stepPassword = form.querySelector('#forgotStepPassword');
                const forgotDesc = form.closest('.auth-modal')?.querySelector('#forgotDesc');

                // Step 1 → 2: OTP sent successfully
                if (stepEmail && stepOtp && stepEmail.style.display !== 'none') {
                    stepEmail.style.display = 'none';
                    stepOtp.style.display = 'block';
                    if (forgotDesc) forgotDesc.textContent = 'Enter the OTP sent to your email';
                    this.clearAlerts(form);
                }
                // Step 2 → 3: OTP verified
                else if (stepOtp && stepPassword && !stepEmail || stepOtp && stepPassword && stepOtp.style.display !== 'none') {
                    stepOtp.style.display = 'none';
                    stepPassword.style.display = 'block';
                    if (forgotDesc) forgotDesc.textContent = 'Choose a new password';
                    this.clearAlerts(form);
                }
                // Step 3 or other forms: success
                else {
                    this.showAlert(form, result.message || 'Success!', 'success');

                    if (typeof grecaptcha !== 'undefined') {
                        grecaptcha.reset();
                    }

                    if (result.redirect) {
                        setTimeout(function() {
                            window.location.href = result.redirect;
                        }, 800);
                    }

                    if (result.clearForm) {
                        form.reset();
                    }
                }
                
            } else {
                // Check for email verification error
                if (result.email_verified === false) {
                    // Show special alert with resend button
                    this.showVerificationAlert(form, result.message || 'Please verify your email');
                } else {
                    this.showAlert(form, result.message || 'An error occurred', 'error');
                }
                
                // Show field errors
                if (result.errors) {
                    this.showFieldErrors(form, result.errors);
                }
            }
            
        } catch (error) {
            this.showAlert(form, 'Network error. Please try again.', 'error');
        }
        
        // Restore button
        if (submitBtn) {
            submitBtn.disabled = false;
            const text = submitBtn.querySelector('.btn-text');
            const loader = submitBtn.querySelector('.btn-loader');
            if (text) text.classList.remove('hidden');
            if (loader) loader.classList.add('hidden');
        }
    }
    
    validateForm(form) {
        let isValid = true;
        const requiredInputs = form.querySelectorAll('[required]');
        
        const errorElements = form.querySelectorAll('.dynamic-error');
        errorElements.forEach(el => el.remove());
        
        // Check each required field (skip hidden containers)
        requiredInputs.forEach(input => {
            if (input.closest('[style*="display:none"], [style*="display: none"]')) return;
            const value = input.value.trim();
            
            if (!value) {
                this.showFieldError(input, 'This field is required');
                isValid = false;
            }
            
            // Email validation
            if (input.type === 'email' && value) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(value)) {
                    this.showFieldError(input, 'Please enter a valid email address');
                    isValid = false;
                }
            }

            if (input.name === 'username' && value) {
                const usernameRegex = /^[a-zA-Z0-9_]{3,20}$/;
                if (!usernameRegex.test(value)) {
                    this.showFieldError(input, 'Username must be 3-20 characters and use only letters, numbers, and underscores');
                    isValid = false;
                }
            }

            if (input.name === 'password' && value && value.length < 8) {
                this.showFieldError(input, 'Password must be at least 8 characters');
                isValid = false;
            }
            
            // Password confirmation validation
            if (input.name === 'confirm_password' && value) {
                const password = form.querySelector('[name="password"]');
                if (password && password.value !== value) {
                    this.showFieldError(input, 'Passwords do not match');
                    isValid = false;
                }
            }
        });
        
        // Check terms agreement for register form
        const agreeTerms = form.querySelector('#agree_terms');
        const agreePrivacy = form.querySelector('#agree_privacy');
        
        if (agreeTerms && !agreeTerms.checked) {
            this.showFieldError(agreeTerms, 'You must agree to the Terms of Service');
            isValid = false;
        }
        
        if (agreePrivacy && !agreePrivacy.checked) {
            this.showFieldError(agreePrivacy, 'You must agree to the Privacy Policy');
            isValid = false;
        }

        const phoneInput = form.querySelector('[name="phone"]');
        if (phoneInput && phoneInput.value.trim()) {
            const cleanPhone = phoneInput.value.trim().replace(/[^0-9+]/g, '');
            const phoneRegex = /^\+?[1-9][0-9]{7,14}$/;
            if (!phoneRegex.test(cleanPhone)) {
                this.showFieldError(phoneInput, 'Please enter a valid phone number');
                isValid = false;
            }
        }
        
        return isValid;
    }
    
    showFieldError(input, message) {
        const wrapper = input.closest('div')?.parentElement || input.parentElement;

        const existingError = wrapper.querySelector('.dynamic-error');
        if (existingError) existingError.remove();

        input.classList.add('border-red-500', 'bg-red-50', 'dark:bg-red-900/10');
        input.classList.remove('border-gray-300', 'dark:border-gray-600', 'border-blue-500', 'focus:border-transparent');

        const errorP = document.createElement('p');
        errorP.className = 'mt-1 text-xs text-red-500 dynamic-error';
        errorP.textContent = message;

        const hint = wrapper.querySelector('.text-xs.text-gray-400');
        if (hint && hint.parentElement === wrapper) {
            hint.insertAdjacentElement('afterend', errorP);
        } else {
            input.insertAdjacentElement('afterend', errorP);
        }
    }
    
    showFieldErrors(form, errors) {
        Object.entries(errors).forEach(([fieldName, errorMessage]) => {
            if (fieldName === 'recaptcha') {
                const errorDiv = form.querySelector('#recaptchaError');
                if (errorDiv) {
                    errorDiv.textContent = errorMessage;
                    errorDiv.classList.remove('hidden');
                }
            } else {
                const field = form.querySelector(`[name="${fieldName}"]`);
                if (field) {
                    this.showFieldError(field, errorMessage);
                }
            }
        });
    }
    
    showVerificationAlert(form, message) {
        const existingAlerts = form.querySelectorAll('.auth-alert');
        existingAlerts.forEach(alert => alert.remove());

        const div = document.createElement('div');
        div.className = 'auth-alert flex items-start gap-2 p-3 mb-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg text-sm';
        div.innerHTML = `
            <i class="fas fa-envelope text-amber-500 mt-0.5 flex-shrink-0"></i>
            <div class="flex-1">
                <span class="text-amber-700 dark:text-amber-300">${message}</span>
                <button type="button" class="mt-1.5 text-sm font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 underline" onclick="resendVerificationEmail(event)">Resend verification email</button>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-amber-400 hover:text-amber-600 flex-shrink-0"><i class="fas fa-times"></i></button>
        `;
        form.insertBefore(div, form.firstChild);
    }

    showAlert(form, message, type = 'success') {
        const existingAlerts = form.querySelectorAll('.auth-alert');
        existingAlerts.forEach(alert => alert.remove());

        const isSuccess = type === 'success';
        const div = document.createElement('div');
        div.className = `auth-alert flex items-start gap-2 p-3 mb-4 border rounded-lg text-sm ${isSuccess ? 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800' : 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800'}`;
        div.innerHTML = `
            <i class="fas fa-${isSuccess ? 'check-circle' : 'exclamation-circle'} text-${isSuccess ? 'green' : 'red'}-500 mt-0.5 flex-shrink-0"></i>
            <span class="text-${isSuccess ? 'green' : 'red'}-700 dark:text-${isSuccess ? 'green' : 'red'}-300 flex-1">${message}</span>
            <button type="button" onclick="this.parentElement.remove()" class="text-${isSuccess ? 'green' : 'red'}-400 hover:text-${isSuccess ? 'green' : 'red'}-600 flex-shrink-0"><i class="fas fa-times"></i></button>
        `;
        form.insertBefore(div, form.firstChild);

        if (isSuccess) {
            setTimeout(() => { div.style.opacity = '0'; div.style.transition = 'opacity 0.3s'; setTimeout(() => div.remove(), 300); }, 5000);
        }
    }

    showAlert(form, message, type = 'success') {
        // Remove existing alerts
        const existingAlerts = form.querySelectorAll('.auth-alert');
        existingAlerts.forEach(alert => alert.remove());
        
        // Create alert element with Tailwind classes
        const alertDiv = document.createElement('div');
        const isSuccess = type === 'success';
        alertDiv.className = `auth-alert flex items-start gap-3 p-4 mb-6 rounded-xl animate-[slideIn_0.3s_ease-out] group ${isSuccess ? 'bg-green-50 dark:bg-green-900/20 border-l-4 border-green-500' : 'bg-red-50 dark:bg-red-900/20 border-l-4 border-red-500'}`;
        alertDiv.innerHTML = `
            <i class="fas fa-${isSuccess ? 'check-circle' : 'exclamation-circle'} text-${isSuccess ? 'green' : 'red'}-500 text-xl flex-shrink-0 mt-0.5 group-hover:rotate-12 transition-transform"></i>
            <div class="flex-1">
                <div class="font-semibold text-${isSuccess ? 'green' : 'red'}-800 dark:text-${isSuccess ? 'green' : 'red'}-200 mb-1">${isSuccess ? 'Success' : 'Error'}</div>
                <div class="text-sm text-${isSuccess ? 'green' : 'red'}-700 dark:text-${isSuccess ? 'green' : 'red'}-300">${message}</div>
            </div>
            <button type="button" class="ml-auto text-${isSuccess ? 'green' : 'red'}-500 hover:text-${isSuccess ? 'green' : 'red'}-700 dark:hover:text-${isSuccess ? 'green' : 'red'}-300 opacity-70 hover:opacity-100 transition-all p-1 rounded-lg" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        // Insert at the beginning of the form
        form.insertBefore(alertDiv, form.firstChild);
        
        // Auto-remove success alerts after 5 seconds
        if (isSuccess) {
            setTimeout(() => {
                alertDiv.style.opacity = '0';
                alertDiv.style.transform = 'translateY(-10px)';
                alertDiv.style.transition = 'all 0.3s ease';
                setTimeout(() => alertDiv.remove(), 300);
            }, 5000);
        }
    }

    clearAlerts(form) {
        const alerts = form.querySelectorAll('.auth-alert');
        alerts.forEach(a => a.remove());
    }
}

// Password strength checker function
function checkPasswordStrength(password) {
    if (password.length === 0) {
        return { 
            level: 'none', 
            percent: 0, 
            label: 'None',
            fillClass: 'bg-gray-300 dark:bg-gray-600',
            color: '#9ca3af'
        };
    }

    let score = 0;
    if (password.length >= 8) score++;
    if (password.length >= 12) score++;
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score++;
    if (/\d/.test(password)) score++;
    if (/[^a-zA-Z0-9]/.test(password)) score++;

    if (score <= 1) return { 
        level: 'weak', 
        percent: 25, 
        label: 'Weak',
        fillClass: 'bg-red-500',
        color: '#ef4444'
    };
    if (score <= 2) return { 
        level: 'fair', 
        percent: 50, 
        label: 'Fair',
        fillClass: 'bg-yellow-500',
        color: '#eab308'
    };
    if (score <= 3) return { 
        level: 'good', 
        percent: 75, 
        label: 'Good',
        fillClass: 'bg-blue-500',
        color: '#3b82f6'
    };
    return { 
        level: 'strong', 
        percent: 100, 
        label: 'Strong',
        fillClass: 'bg-green-500',
        color: '#22c55e'
    };
}

// Global function to resend verification email
window.resendVerificationEmail = async function(e) {
    if (e) e.preventDefault();
    const btn = e ? e.currentTarget : null;
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Sending...';
    }
    try {
        const csrfToken = document.getElementById('body')?.getAttribute('data-csrf-token') || '';
        const form = document.getElementById('loginForm');
        const emailInput = form ? form.querySelector('[name="identifier"]') : null;
        const email = emailInput ? emailInput.value.trim() : '';

        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        if (email) formData.append('email', email);

        const response = await fetch('handlers/resend_verify_handler.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const result = await response.json();
        if (btn) {
            btn.textContent = result.success ? 'Verification sent!' : 'Failed - Try Again';
            btn.disabled = false;
            if (result.success) {
                btn.style.color = '#10b981';
                setTimeout(() => { if (btn.parentElement) btn.parentElement.remove(); }, 3000);
            }
        }
    } catch (err) {
        if (btn) { btn.textContent = 'Try Again'; btn.disabled = false; }
    }
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.AuthHandler = new AuthHandler();
    window.AuthHandler.init();
});

// Handle page changes - re-initialize for AJAX navigation
document.addEventListener('pageChanged', () => {
    setTimeout(() => {
        if (window.AuthHandler) {
            window.AuthHandler.initPageEnhancements();
        }
    }, 150);
});
