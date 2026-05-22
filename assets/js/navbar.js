(() => {
    const MOBILE_BREAKPOINT = 769;

    if (window.__dreambdNavbarReady) return;
    window.__dreambdNavbarReady = true;

    const menuToggle = document.getElementById('mobileMenuToggle');
    const mobileMenu = document.getElementById('mobileMenu');
    const mobileClose = document.getElementById('mobileMenuClose');
    const mobileOverlay = document.getElementById('mobileBlurOverlay');
    const siteDialogBackdrop = document.getElementById('siteDialogBackdrop');
    const settingsDialog = document.getElementById('globalSettingsDialog');
    const logoutDialog = document.getElementById('logoutConfirmDialog');

    if (!menuToggle || !mobileMenu) return;

    const isOpen = () => mobileMenu.classList.contains('is-open');
    const isAnimating = () => mobileMenu.classList.contains('is-closing');

    const hide = () => {
        mobileMenu.classList.remove('is-closing');
        mobileMenu.classList.remove('hidden');
        mobileMenu.style.display = '';
        if (mobileOverlay) {
            mobileOverlay.classList.remove('is-closing');
            mobileOverlay.classList.remove('hidden');
            mobileOverlay.style.display = '';
        }
        document.body.classList.remove('dream-mobile-menu-open');
    };

    const finishClosing = () => {
        hide();
        mobileMenu.removeEventListener('animationend', finishClosing);
    };

    const closeMobileMenu = () => {
        if (!isOpen() || isAnimating()) return;
        mobileMenu.classList.remove('is-open');
        mobileMenu.classList.add('is-closing');
        if (mobileOverlay) {
            mobileOverlay.classList.remove('is-visible');
            mobileOverlay.classList.add('is-closing');
        }
        menuToggle.setAttribute('aria-expanded', 'false');
        menuToggle.setAttribute('aria-label', 'Open menu');
        menuToggle.innerHTML = '<i class="fas fa-bars" aria-hidden="true"></i>';
        document.body.style.overflow = '';
        mobileMenu.addEventListener('animationend', finishClosing);
        document.dispatchEvent(new CustomEvent('mobileMenuClose'));
    };

    const openMobileMenu = () => {
        if (isOpen() || isAnimating()) return;
        mobileMenu.classList.remove('is-closing');
        if (mobileOverlay) mobileOverlay.classList.remove('is-closing');
        mobileMenu.classList.remove('hidden');
        if (mobileOverlay) mobileOverlay.classList.remove('hidden');
        mobileMenu.style.display = 'block';
        if (mobileOverlay) mobileOverlay.style.display = 'block';
        void mobileMenu.offsetHeight;
        mobileMenu.classList.add('is-open');
        if (mobileOverlay) mobileOverlay.classList.add('is-visible');
        mobileMenu.classList.remove('hidden');
        if (mobileOverlay) mobileOverlay.classList.remove('hidden');
        document.body.classList.add('dream-mobile-menu-open');
        menuToggle.setAttribute('aria-expanded', 'true');
        menuToggle.setAttribute('aria-label', 'Close menu');
        menuToggle.innerHTML = '<i class="fas fa-times" aria-hidden="true"></i>';
        document.body.style.overflow = 'hidden';
        document.dispatchEvent(new CustomEvent('mobileMenuOpen'));
        requestAnimationFrame(() => {
            mobileMenu.classList.remove('hidden');
            if (mobileOverlay) mobileOverlay.classList.remove('hidden');
        });
    };

    const toggleMobileMenu = () => {
        if (isOpen()) {
            closeMobileMenu();
        } else {
            openMobileMenu();
        }
    };

    const hideAllDialogs = () => {
        [settingsDialog, logoutDialog].forEach((d) => {
            if (d) d.hidden = true;
        });
        if (siteDialogBackdrop) siteDialogBackdrop.hidden = true;
        document.body.classList.remove('dialog-open');
        if (!isOpen()) {
            document.body.style.overflow = '';
        }
    };

    const showDialog = (dialog) => {
        if (!dialog || !siteDialogBackdrop) return;
        closeMobileMenu();
        hideAllDialogs();
        siteDialogBackdrop.hidden = false;
        dialog.hidden = false;
        document.body.classList.add('dialog-open');
        document.body.style.overflow = 'hidden';
        dialog.querySelector('[data-close-site-dialog], .site-dialog-close, a, button')?.focus();
    };

    const updateThemeButtons = (theme) => {
        const saved = theme || localStorage.getItem('dreambd-theme') || 'light';
        document.querySelectorAll('[data-theme-choice]').forEach((btn) => {
            const active = btn.dataset.themeChoice === saved;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-pressed', String(active));
        });
    };

    menuToggle.addEventListener('click', toggleMobileMenu);

    if (mobileClose) {
        mobileClose.addEventListener('click', closeMobileMenu);
    }

    document.addEventListener('click', (event) => {
        const target = event.target;

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

        const openSettings = target.closest('[data-open-global-settings]');
        const openLogout = target.closest('[data-open-logout-dialog]');
        const closeDialog = target.closest('[data-close-site-dialog]');
        const themeChoice = target.closest('[data-theme-choice]');

        if (openSettings) {
            event.preventDefault();
            showDialog(settingsDialog);
            return;
        }
        if (openLogout) {
            event.preventDefault();
            showDialog(logoutDialog);
            return;
        }
        if (closeDialog) {
            event.preventDefault();
            hideAllDialogs();
            return;
        }
        if (themeChoice) {
            const theme = themeChoice.dataset.themeChoice || 'light';
            localStorage.setItem('dreambd-theme', theme);
            document.dispatchEvent(new CustomEvent('themeChange', { detail: { theme } }));
            updateThemeButtons(theme);
        }
    });

    if (mobileOverlay) {
        mobileOverlay.addEventListener('click', closeMobileMenu);
    }

    siteDialogBackdrop?.addEventListener('click', hideAllDialogs);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            if (!settingsDialog?.hidden || !logoutDialog?.hidden) {
                hideAllDialogs();
                return;
            }
            if (isOpen()) {
                closeMobileMenu();
            }
        }
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth >= MOBILE_BREAKPOINT) {
            if (isOpen()) {
                closeMobileMenu();
            }
        }
    });

    document.addEventListener('themeChange', (event) => {
        updateThemeButtons(event.detail?.theme);
    });

    document.addEventListener('pageContentLoaded', () => {
        if (isOpen()) {
            closeMobileMenu();
        }
    });

    updateThemeButtons();
})();
