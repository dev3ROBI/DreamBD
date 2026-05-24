const DreamBDNavbar = {
    init: function() {
        this.destroy();

        this.BREAKPOINT = 769;
        this._el = {
            menuToggle: document.getElementById('mobileMenuToggle'),
            mobileMenu: document.getElementById('mobileMenu'),
            mobileClose: document.getElementById('mobileMenuClose'),
            mobileOverlay: document.getElementById('mobileBlurOverlay'),
            siteDialogBackdrop: document.getElementById('siteDialogBackdrop'),
            settingsDialog: document.getElementById('globalSettingsDialog'),
            logoutDialog: document.getElementById('logoutConfirmDialog')
        };

        var el = this._el;
        if (!el.menuToggle || !el.mobileMenu) return;

        var isOpen = function() { return el.mobileMenu.classList.contains('is-open'); };
        var isAnimating = function() { return el.mobileMenu.classList.contains('is-closing'); };

        var hide = function() {
            el.mobileMenu.classList.remove('is-closing');
            el.mobileMenu.classList.remove('hidden');
            el.mobileMenu.style.display = '';
            if (el.mobileOverlay) {
                el.mobileOverlay.classList.remove('is-closing');
                el.mobileOverlay.classList.remove('hidden');
                el.mobileOverlay.style.display = '';
            }
            document.body.classList.remove('dream-mobile-menu-open');
        };

        var finishClosing = function() {
            hide();
            el.mobileMenu.removeEventListener('animationend', finishClosing);
        };

        var closeMobileMenu = function() {
            if (!isOpen() || isAnimating()) return;
            el.mobileMenu.classList.remove('is-open');
            el.mobileMenu.classList.add('is-closing');
            if (el.mobileOverlay) {
                el.mobileOverlay.classList.remove('is-visible');
                el.mobileOverlay.classList.add('is-closing');
            }
            el.menuToggle.setAttribute('aria-expanded', 'false');
            el.menuToggle.setAttribute('aria-label', 'Open menu');
            el.menuToggle.innerHTML = '<i class="fas fa-bars" aria-hidden="true"></i>';
            document.body.style.overflow = '';
            el.mobileMenu.addEventListener('animationend', finishClosing);
            document.dispatchEvent(new CustomEvent('mobileMenuClose'));
        };

        var openMobileMenu = function() {
            if (isOpen() || isAnimating()) return;
            el.mobileMenu.classList.remove('is-closing');
            if (el.mobileOverlay) el.mobileOverlay.classList.remove('is-closing');
            el.mobileMenu.classList.remove('hidden');
            if (el.mobileOverlay) el.mobileOverlay.classList.remove('hidden');
            el.mobileMenu.style.display = 'block';
            if (el.mobileOverlay) el.mobileOverlay.style.display = 'block';
            void el.mobileMenu.offsetHeight;
            el.mobileMenu.classList.add('is-open');
            if (el.mobileOverlay) el.mobileOverlay.classList.add('is-visible');
            el.mobileMenu.classList.remove('hidden');
            if (el.mobileOverlay) el.mobileOverlay.classList.remove('hidden');
            document.body.classList.add('dream-mobile-menu-open');
            el.menuToggle.setAttribute('aria-expanded', 'true');
            el.menuToggle.setAttribute('aria-label', 'Close menu');
            el.menuToggle.innerHTML = '<i class="fas fa-times" aria-hidden="true"></i>';
            document.body.style.overflow = 'hidden';
            document.dispatchEvent(new CustomEvent('mobileMenuOpen'));
            requestAnimationFrame(function() {
                el.mobileMenu.classList.remove('hidden');
                if (el.mobileOverlay) el.mobileOverlay.classList.remove('hidden');
            });
        };

        var toggleMobileMenu = function() {
            if (isOpen()) {
                closeMobileMenu();
            } else {
                openMobileMenu();
            }
        };

        var hideAllDialogs = function() {
            [el.settingsDialog, el.logoutDialog].forEach(function(d) {
                if (d) d.hidden = true;
            });
            if (el.siteDialogBackdrop) el.siteDialogBackdrop.hidden = true;
            document.body.classList.remove('dialog-open');
            if (!isOpen()) {
                document.body.style.overflow = '';
            }
        };

        var showDialog = function(dialog) {
            if (!dialog || !el.siteDialogBackdrop) return;
            closeMobileMenu();
            hideAllDialogs();
            el.siteDialogBackdrop.hidden = false;
            dialog.hidden = false;
            document.body.classList.add('dialog-open');
            document.body.style.overflow = 'hidden';
            var focusTarget = dialog.querySelector('[data-close-site-dialog], .site-dialog-close, a, button');
            if (focusTarget) focusTarget.focus();
        };

        this._handlers = {};

        this._handlers.toggle = function(e) {
            e.stopPropagation();
            toggleMobileMenu();
        };

        this._handlers.close = function() {
            closeMobileMenu();
        };

        this._handlers.hideDialogs = hideAllDialogs;

        this._handlers.documentClick = function(event) {
            var target = event.target;

            if (!target.closest('#mobileMenu') && !target.closest('#mobileMenuToggle') && !target.closest('#mobileBlurOverlay')) {
                if (isOpen()) {
                    closeMobileMenu();
                }
            }

            if (target.closest('#mobileMenu a') || target.closest('#mobileMenu button')) {
                if (isOpen() && !target.closest('[data-open-global-settings]') && !target.closest('[data-open-logout-dialog]')) {
                    closeMobileMenu();
                }
            }

            var openSettings = target.closest('[data-open-global-settings]');
            var openLogout = target.closest('[data-open-logout-dialog]');
            var closeDialog = target.closest('[data-close-site-dialog]');
            var themeChoice = target.closest('[data-theme-choice]');

            if (openSettings) {
                event.preventDefault();
                showDialog(el.settingsDialog);
                return;
            }
            if (openLogout) {
                event.preventDefault();
                showDialog(el.logoutDialog);
                return;
            }
            if (closeDialog) {
                event.preventDefault();
                hideAllDialogs();
                return;
            }
            if (themeChoice) {
                var theme = themeChoice.dataset.themeChoice || 'light';
                localStorage.setItem('dreambd-theme', theme);
                document.dispatchEvent(new CustomEvent('themeChange', { detail: { theme: theme } }));
                this.updateThemeButtons(theme);
            }
        }.bind(this);

        this._handlers.keydown = function(event) {
            if (event.key === 'Escape') {
                if (!el.settingsDialog?.hidden || !el.logoutDialog?.hidden) {
                    hideAllDialogs();
                    return;
                }
                if (isOpen()) {
                    closeMobileMenu();
                }
            }
        };

        this._handlers.resize = function() {
            if (window.innerWidth >= this.BREAKPOINT) {
                if (isOpen()) {
                    closeMobileMenu();
                }
            }
        }.bind(this);

        this._handlers.themeChange = function(event) {
            this.updateThemeButtons(event.detail?.theme);
        }.bind(this);

        this._handlers.pageContentLoaded = function() {
            if (isOpen()) {
                closeMobileMenu();
            }
        };

        el.menuToggle.addEventListener('click', this._handlers.toggle);
        if (el.mobileClose) {
            el.mobileClose.addEventListener('click', this._handlers.close);
        }
        document.addEventListener('click', this._handlers.documentClick);
        if (el.mobileOverlay) {
            el.mobileOverlay.addEventListener('click', this._handlers.close);
        }
        if (el.siteDialogBackdrop) {
            el.siteDialogBackdrop.addEventListener('click', this._handlers.hideDialogs);
        }
        document.addEventListener('keydown', this._handlers.keydown);
        window.addEventListener('resize', this._handlers.resize);
        document.addEventListener('themeChange', this._handlers.themeChange);
        document.addEventListener('pageContentLoaded', this._handlers.pageContentLoaded);

        this.updateThemeButtons();
    },

    destroy: function() {
        if (!this._handlers || !this._el) return;

        if (this._el.menuToggle) {
            this._el.menuToggle.removeEventListener('click', this._handlers.toggle);
        }
        if (this._el.mobileClose) {
            this._el.mobileClose.removeEventListener('click', this._handlers.close);
        }
        document.removeEventListener('click', this._handlers.documentClick);
        if (this._el.mobileOverlay) {
            this._el.mobileOverlay.removeEventListener('click', this._handlers.close);
        }
        if (this._el.siteDialogBackdrop) {
            this._el.siteDialogBackdrop.removeEventListener('click', this._handlers.hideDialogs);
        }
        document.removeEventListener('keydown', this._handlers.keydown);
        window.removeEventListener('resize', this._handlers.resize);
        document.removeEventListener('themeChange', this._handlers.themeChange);
        document.removeEventListener('pageContentLoaded', this._handlers.pageContentLoaded);

        this._handlers = null;
        this._el = null;

        document.body.style.overflow = '';
        document.body.classList.remove('dream-mobile-menu-open', 'dialog-open');
    },

    updateThemeButtons: function(theme) {
        var saved = theme || localStorage.getItem('dreambd-theme') || 'light';
        document.querySelectorAll('[data-theme-choice]').forEach(function(btn) {
            var active = btn.dataset.themeChoice === saved;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-pressed', String(active));
        });
    }
};

document.addEventListener('DOMContentLoaded', function() {
    DreamBDNavbar.init();
});

document.addEventListener('pageContentLoaded', function() {
    DreamBDNavbar.init();
});
