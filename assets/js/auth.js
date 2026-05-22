// Authentication System JavaScript
class AuthSystem {
    constructor() {
        this.initialized = false;
        this.init();
    }
    
    init() {
        if (this.initialized) return;

        const boot = () => {
            if (this.initialized) return;

            this.initFormValidation();
            this.initPasswordStrength();
            this.initToast();
            this.initPhoneFormatting();

            setTimeout(() => {
                const formGroups = document.querySelectorAll('.form-group');
                formGroups.forEach((group, index) => {
                    setTimeout(() => {
                        group.style.opacity = '0';
                        group.style.transform = 'translateY(10px)';
                        group.style.transition = 'all 0.5s ease';

                        setTimeout(() => {
                            group.style.opacity = '1';
                            group.style.transform = 'translateY(0)';
                        }, 50);
                    }, index * 100);
                });
            }, 300);

            this.initialized = true;
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', boot, { once: true });
        } else {
            boot();
        }
    }
    
    // Form validation
    initFormValidation() {
        const forms = document.querySelectorAll('.auth-form');
        
        forms.forEach(form => {
            // Add submit event listener
            form.addEventListener('submit', (e) => {
                if (!this.validateForm(form)) {
                    e.preventDefault();
                    e.stopPropagation();
                }
            });
            
            // Real-time validation
            const inputs = form.querySelectorAll('input[required]');
            inputs.forEach(input => {
                input.addEventListener('blur', () => this.validateField(input));
                input.addEventListener('input', () => this.clearFieldError(input));
            });
            
            // Password confirmation validation
            const password = form.querySelector('#password');
            const confirmPassword = form.querySelector('#confirm_password');
            if (password && confirmPassword) {
                confirmPassword.addEventListener('input', () => {
                    this.validatePasswordMatch(password, confirmPassword);
                });
            }
        });
    }
    
    validateForm(form) {
        let isValid = true;
        const inputs = form.querySelectorAll('input[required]');
        
        inputs.forEach(input => {
            if (!this.validateField(input)) {
                isValid = false;
            }
        });
        
        // Validate password match for register form
        const password = form.querySelector('#password');
        const confirmPassword = form.querySelector('#confirm_password');
        if (password && confirmPassword) {
            if (!this.validatePasswordMatch(password, confirmPassword)) {
                isValid = false;
            }
        }
        
        // Validate terms agreement
        const agreeTerms = form.querySelector('#agree_terms');
        const agreePrivacy = form.querySelector('#agree_privacy');
        
        if (agreeTerms && agreePrivacy) {
            if (!agreeTerms.checked) {
                this.showFieldError(agreeTerms, 'You must agree to the Terms of Service');
                isValid = false;
            }
            
            if (!agreePrivacy.checked) {
                this.showFieldError(agreePrivacy, 'You must agree to the Privacy Policy');
                isValid = false;
            }
        }
        
        return isValid;
    }
    
    validateField(input) {
        const value = input.value.trim();
        const type = input.type;
        const name = input.name;
        
        // Clear previous validation states
        this.clearFieldError(input);
        
        // Required field validation
        if (input.required && !value) {
            this.showFieldError(input, 'This field is required');
            return false;
        }
        
        // Email validation
        if (type === 'email' && value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                this.showFieldError(input, 'Please enter a valid email address');
                return false;
            }
        }
        
        // Username validation
        if (name === 'username' && value) {
            const usernameRegex = /^[a-zA-Z0-9_]{3,20}$/;
            if (!usernameRegex.test(value)) {
                this.showFieldError(input, 'Username must be 3-20 characters and contain only letters, numbers, and underscores');
                return false;
            }
        }
        
        // Password validation
        if (name === 'password' && value) {
            if (value.length < 8) {
                this.showFieldError(input, 'Password must be at least 8 characters');
                return false;
            }
        }
        
        // Phone validation
        if (name === 'phone' && value) {
            const cleanPhone = value.replace(/[^0-9+]/g, '');
            const phoneRegex = /^\+?[1-9][0-9]{7,14}$/;
            if (!phoneRegex.test(cleanPhone)) {
                this.showFieldError(input, 'Please enter a valid phone number');
                return false;
            }
        }
        
        // Mark as valid
        input.classList.add('valid');
        input.classList.remove('invalid');
        return true;
    }
    
    validatePasswordMatch(password, confirmPassword) {
        if (password.value && confirmPassword.value && password.value !== confirmPassword.value) {
            this.showFieldError(confirmPassword, 'Passwords do not match');
            return false;
        }
        
        this.clearFieldError(confirmPassword);
        return true;
    }
    
    showFieldError(input, message) {
        input.classList.add('invalid');
        input.classList.remove('valid');

        const fieldGroup = input.closest('.form-group') || input.parentElement;
        let errorDiv = fieldGroup.querySelector('.form-error.dynamic-error');
        if (!errorDiv) {
            errorDiv = document.createElement('div');
            errorDiv.className = 'form-error dynamic-error';
            const strengthBlock = fieldGroup.querySelector('.password-strength');
            const hintBlock = fieldGroup.querySelector('.form-hint');
            if (strengthBlock) {
                strengthBlock.insertAdjacentElement('afterend', errorDiv);
            } else if (hintBlock) {
                hintBlock.insertAdjacentElement('afterend', errorDiv);
            } else {
                fieldGroup.appendChild(errorDiv);
            }
        }
        errorDiv.textContent = message;
        errorDiv.style.display = 'block';
    }
    
    clearFieldError(input) {
        input.classList.remove('invalid');

        const fieldGroup = input.closest('.form-group') || input.parentElement;
        const errorDiv = fieldGroup.querySelector('.form-error.dynamic-error');
        if (errorDiv) {
            errorDiv.textContent = '';
            errorDiv.style.display = 'none';
        }
    }
    
    // Password strength meter
    initPasswordStrength() {
        const passwordInput = document.getElementById('password');
        if (!passwordInput) return;
        
        const meterFill = document.querySelector('.strength-meter-fill');
        const strengthText = document.getElementById('passwordStrengthText');
        
        const updateStrength = () => {
            const password = passwordInput.value;
            let score = 0;
            
            if (!password) {
                meterFill.style.width = '0%';
                strengthText.textContent = 'None';
                strengthText.style.color = '#718096';
                return;
            }
            
            // Length
            if (password.length >= 8) score++;
            if (password.length >= 12) score++;
            
            // Character types
            if (/[a-z]/.test(password)) score++;
            if (/[A-Z]/.test(password)) score++;
            if (/[0-9]/.test(password)) score++;
            if (/[^A-Za-z0-9]/.test(password)) score++;
            
            // Cap at 5
            score = Math.min(score, 5);
            
            // Update meter
            const width = score * 20;
            meterFill.style.width = width + '%';
            
            // Update text and color
            if (score <= 1) {
                meterFill.style.background = '#e53e3e';
                strengthText.textContent = 'Weak';
                strengthText.style.color = '#e53e3e';
            } else if (score === 2) {
                meterFill.style.background = '#ed8936';
                strengthText.textContent = 'Fair';
                strengthText.style.color = '#ed8936';
            } else if (score === 3) {
                meterFill.style.background = '#ecc94b';
                strengthText.textContent = 'Good';
                strengthText.style.color = '#ecc94b';
            } else if (score === 4) {
                meterFill.style.background = '#48bb78';
                strengthText.textContent = 'Strong';
                strengthText.style.color = '#48bb78';
            } else {
                meterFill.style.background = '#349600';
                strengthText.textContent = 'Very Strong';
                strengthText.style.color = '#349600';
            }
        };
        
        passwordInput.addEventListener('input', updateStrength);
        updateStrength();
    }
    
    // Toast notifications
    initToast() {
        window.showToast = (message, type = 'success', duration = 3000) => {
            let container = document.querySelector('.toast-container');
            if (!container) {
                container = document.createElement('div');
                container.className = 'toast-container';
                document.body.appendChild(container);
            }
            
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                <div class="toast-content">
                    <div class="toast-title">${type === 'success' ? 'Success' : 'Error'}</div>
                    <div class="toast-message">${message}</div>
                </div>
            `;
            
            container.appendChild(toast);
            
            // Remove toast after duration
            setTimeout(() => {
                toast.style.animation = 'fadeOut 0.3s ease-out forwards';
                setTimeout(() => toast.remove(), 300);
            }, duration);
        };
    }
    
    // Phone number formatting
    initPhoneFormatting() {
        const phoneInput = document.getElementById('phone');
        if (!phoneInput) return;
        
        phoneInput.addEventListener('input', (e) => {
            let value = e.target.value.replace(/\D/g, '');
            
            if (value.length > 0) {
                if (!value.startsWith('+')) {
                    value = '+' + value;
                }
            }
            
            e.target.value = value;
        });
    }
    
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.authSystem = new AuthSystem();
});
