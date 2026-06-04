const DreamBDNavbar = {
    init: function() {
        this.destroy();

        this.BREAKPOINT = 769;
        this._el = {
            menuToggle: document.getElementById('mobileMenuToggle'),
            mobileMenu: document.getElementById('mobileMenu'),
            mobileClose: document.getElementById('mobileMenuClose') || document.getElementById('closeMenu'),
            mobileOverlay: document.getElementById('mobileBlurOverlay') || document.getElementById('mobileOverlay'),
            siteDialogBackdrop: document.getElementById('siteDialogBackdrop'),
            settingsDialog: document.getElementById('globalSettingsDialog'),
            logoutDialog: document.getElementById('logoutConfirmDialog'),
            searchBar: document.getElementById('searchBar'),
            searchInput: document.getElementById('searchInput'),
            searchClearBtn: document.getElementById('searchClearBtn'),
            searchSuggestions: document.getElementById('navSearchSuggestions'),
            searchSuggestionsBody: document.getElementById('navSearchSuggestionsBody'),
            searchSuggestionsMeta: document.getElementById('navSearchSuggestionsMeta'),
            mobileSearchToggle: document.getElementById('mobileSearchToggle'),
            searchBackBtn: document.getElementById('searchBackBtn'),
            mainNav: document.getElementById('mainNav'),
            themeToggle: document.getElementById('themeToggle'),
            userDropdownToggle: document.getElementById('userDropdownToggle'),
            userDropdownMenu: document.getElementById('userDropdownMenu')
        };

        var el = this._el;
        if (!el.menuToggle || !el.mobileMenu) return;

        var isOpen = function() {
            return el.mobileMenu.classList.contains('is-open') || el.mobileMenu.classList.contains('active');
        };

        var closeMobileMenu = function() {
            el.mobileMenu.classList.remove('is-open', 'active');
            el.mobileMenu.classList.add('is-closing');
            el.mobileOverlay?.classList.remove('is-visible', 'active');
            el.mobileOverlay?.classList.add('is-closing');
            el.menuToggle.setAttribute('aria-expanded', 'false');
            document.body.classList.remove('dream-mobile-menu-open');
            document.body.style.overflow = '';

            window.setTimeout(function() {
                el.mobileMenu.classList.remove('is-closing');
                el.mobileOverlay?.classList.remove('is-closing');
            }, 220);
        };

        var openMobileMenu = function() {
            el.mobileMenu.classList.remove('is-closing');
            el.mobileMenu.classList.add('is-open', 'active');
            el.mobileOverlay?.classList.remove('is-closing');
            el.mobileOverlay?.classList.add('is-visible', 'active');
            el.menuToggle.setAttribute('aria-expanded', 'true');
            document.body.classList.add('dream-mobile-menu-open');
            document.body.style.overflow = 'hidden';
        };

        var toggleMobileMenu = function() {
            if (isOpen()) closeMobileMenu();
            else openMobileMenu();
        };

        var hideAllDialogs = function() {
            [el.settingsDialog, el.logoutDialog].forEach(function(dialog) {
                if (!dialog) return;
                dialog.hidden = true;
                dialog.classList.add('hidden');
                dialog.classList.remove('grid', 'flex');
            });
            if (el.siteDialogBackdrop) {
                el.siteDialogBackdrop.hidden = true;
                el.siteDialogBackdrop.classList.add('hidden');
            }
            document.documentElement.classList.remove('dialog-open');
            document.body.classList.remove('dialog-open');
            document.body.style.position = '';
            document.body.style.width = '';
            document.body.style.top = '';
        };

        var showDialog = function(dialog) {
            hideAllDialogs();
            if (!dialog) return;
            dialog.hidden = false;
            dialog.classList.remove('hidden', 'flex');
            dialog.classList.add('grid');
            if (el.siteDialogBackdrop) {
                el.siteDialogBackdrop.hidden = false;
                el.siteDialogBackdrop.classList.remove('hidden');
            }
            document.documentElement.classList.add('dialog-open');
            document.body.classList.add('dialog-open');
        };

        // --- Search System ---
        this._search = {
            abortController: null,
            debounceTimer: null
        };

        var renderSearchSuggestions = function(results, queryText) {
            if (!el.searchSuggestions || !el.searchSuggestionsBody || !el.searchSuggestionsMeta) return;

            const users = results.users || [];
            const posts = results.posts || [];
            const total = results.counts?.all || 0;

            el.searchSuggestionsMeta.textContent = total > 0
                ? `${total} match${total !== 1 ? 'es' : ''} for "${queryText}"`
                : `No results for "${queryText}"`;

            let html = '';

            if (users.length) {
                html += '<div class="dream-search-section"><span class="dream-search-section-title"><i class="fas fa-user"></i> People</span>';
                users.forEach((user) => {
                    const name = (user.full_name || user.username || 'User').replace(/"/g, '&quot;');
                    const note = (user.location || user.bio || '').replace(/"/g, '&quot;');
                    const avatar = (user.avatar || 'default.png').replace(/"/g, '&quot;');
                    const role = user.role || '';
                    const roleBadge = role === 'agent' ? '<span class="sr-badge"><i class="fas fa-crown"></i></span>' : '';
                    const onlineDot = '<span class="sr-online"></span>';
                    html += `
                        <a href="index.php?page=profile&user=${Number(user.id)}" class="dream-search-suggestion" data-no-ajax>
                            <span class="sr-avatar-wrap">${onlineDot}<img src="assets/avatars/${avatar}" alt="${name}" onerror="this.src='assets/avatars/default.png'"></span>
                            <span class="sr-body"><strong>${name} ${roleBadge}</strong><span class="sr-note">${note}</span></span>
                        </a>
                    `;
                });
                html += '</div>';
            }

            if (posts.length) {
                html += '<div class="dream-search-section"><span class="dream-search-section-title"><i class="fas fa-file-lines"></i> Posts</span>';
                posts.forEach((post) => {
                    const name = (post.full_name || post.username || 'Member').replace(/"/g, '&quot;');
                    const excerpt = (post.content_excerpt || '').replace(/"/g, '&quot;');
                    html += `
                        <a href="index.php?page=community&post=${Number(post.id)}#post-${Number(post.id)}" class="dream-search-suggestion" data-no-ajax>
                            <span class="dream-search-suggestion-icon"><i class="fas fa-file-lines"></i></span>
                            <span class="sr-body"><strong>${name}</strong><span class="sr-note">${excerpt}</span></span>
                        </a>
                    `;
                });
                html += '</div>';
            }

            if (!html) {
                html = `
                    <div class="dream-search-empty">
                        <i class="fas fa-magnifying-glass"></i>
                        <span>Try a different name, keyword, or topic.</span>
                    </div>
                `;
            }

            el.searchSuggestionsBody.innerHTML = html;

            const foot = document.getElementById('navSearchSuggestionsFoot');
            if (foot) {
                foot.classList.remove('hidden');
                const link = foot.querySelector('.dream-search-all-results');
                if (link) link.href = `index.php?page=search&q=${encodeURIComponent(queryText)}`;
            }

            el.searchSuggestions.classList.remove('hidden');
        };

        var hideSearchSuggestions = function() {
            if (el.searchSuggestions) {
                el.searchSuggestions.classList.add('hidden');
            }
        };

        var fetchSearchSuggestions = function(queryText) {
            if (!el.searchSuggestionsBody || !el.searchSuggestionsMeta) return;

            const foot = document.getElementById('navSearchSuggestionsFoot');
            if (foot) foot.classList.add('hidden');

            if (this._search.abortController) {
                this._search.abortController.abort();
            }

            this._search.abortController = new AbortController();
            el.searchSuggestionsMeta.textContent = 'Searching...';
            el.searchSuggestionsBody.innerHTML = '<div class="dream-search-empty"><i class="fas fa-spinner fa-spin"></i><span>Loading suggestions...</span></div>';
            el.searchSuggestions?.classList.remove('hidden');

            fetch(`index.php?ajax=nav_search&q=${encodeURIComponent(queryText)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                signal: this._search.abortController.signal
            })
                .then((response) => response.json())
                .then((results) => {
                    renderSearchSuggestions(results, queryText);
                })
                .catch((error) => {
                    if (error.name === 'AbortError') return;
                    el.searchSuggestionsMeta.textContent = 'Search is unavailable right now';
                    el.searchSuggestionsBody.innerHTML = '<div class="dream-search-empty"><i class="fas fa-circle-exclamation"></i><span>Please try again.</span></div>';
                    el.searchSuggestions?.classList.remove('hidden');
                });
        }.bind(this);

        var enterMobileSearch = function() {
            if (!el.mainNav) return;
            el.mainNav.classList.add('is-mobile-searching');
            if (el.searchInput) {
                el.searchInput.value = '';
                el.searchInput.focus();
            }
            if (el.searchSuggestions) el.searchSuggestions.classList.add('hidden');
            document.documentElement.style.setProperty('--navbar-actual-height', el.mainNav.offsetHeight + 'px');
        };

        var exitMobileSearch = function() {
            if (!el.mainNav) return;
            el.mainNav.classList.remove('is-mobile-searching');
            if (el.searchInput) el.searchInput.value = '';
            if (el.searchSuggestions) el.searchSuggestions.classList.add('hidden');
            document.documentElement.style.removeProperty('--navbar-actual-height');
        };

        this._handlers = {};

        this._handlers.searchInput = function() {
            const queryText = el.searchInput.value.trim();
            el.searchClearBtn?.classList.toggle('hidden', !queryText);
            clearTimeout(this._search.debounceTimer);
            if (queryText.length < 2) {
                hideSearchSuggestions();
                return;
            }
            this._search.debounceTimer = setTimeout(() => {
                fetchSearchSuggestions(queryText);
            }, 220);
        }.bind(this);

        this._handlers.searchFocus = function() {
            el.searchClearBtn?.classList.toggle('hidden', !el.searchInput.value.trim());
            if (el.searchInput.value.trim().length >= 2 && el.searchSuggestionsBody?.innerHTML) {
                el.searchSuggestions?.classList.remove('hidden');
            }
        };

        this._handlers.searchClear = function() {
            el.searchInput.value = '';
            el.searchClearBtn?.classList.add('hidden');
            hideSearchSuggestions();
            el.searchInput.focus();
        };

        this._handlers.mobileSearchOpen = enterMobileSearch;
        this._handlers.mobileSearchClose = exitMobileSearch;

        this._handlers.userDropdownToggle = function(e) {
            if (!el.userDropdownMenu) return;
            e.stopPropagation();
            const isHidden = el.userDropdownMenu.classList.contains('hidden');
            if (isHidden) {
                el.userDropdownMenu.classList.remove('hidden', 'opacity-0', 'scale-95');
                el.userDropdownMenu.classList.add('opacity-100', 'scale-100');
            } else {
                this.hideUserDropdown();
            }
        }.bind(this);

        this._handlers.themeToggle = function() {
            const html = document.documentElement;
            const isDark = html.classList.contains('dark');
            const newTheme = isDark ? 'light' : 'dark';
            
            html.classList.toggle('dark', !isDark);
            html.setAttribute('data-theme', newTheme);
            document.cookie = `dreambd-theme=${newTheme}; path=/; max-age=31536000`;
            
            this.updateThemeIcons(newTheme);
            document.dispatchEvent(new CustomEvent('themeChange', { detail: { theme: newTheme } }));
        }.bind(this);

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

            // Close mobile menu if clicking outside
            if (!target.closest('#mobileMenu') && !target.closest('#mobileMenuToggle') && !target.closest('#mobileBlurOverlay')) {
                if (isOpen()) {
                    closeMobileMenu();
                }
            }

            // Close user dropdown if clicking outside
            if (el.userDropdownMenu && !el.userDropdownMenu.classList.contains('hidden')) {
                if (!target.closest('#userDropdownMenu') && !target.closest('#userDropdownToggle')) {
                    this.hideUserDropdown();
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
                this.hideUserDropdown();
                showDialog(el.settingsDialog);
                return;
            }
            if (openLogout) {
                event.preventDefault();
                this.hideUserDropdown();
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
        
        // Search listeners
        if (el.searchInput) {
            el.searchInput.addEventListener('input', this._handlers.searchInput);
            el.searchInput.addEventListener('focus', this._handlers.searchFocus);
        }
        if (el.searchClearBtn) {
            el.searchClearBtn.addEventListener('click', this._handlers.searchClear);
        }
        if (el.mobileSearchToggle) {
            el.mobileSearchToggle.addEventListener('click', this._handlers.mobileSearchOpen);
        }
        if (el.searchBackBtn) {
            el.searchBackBtn.addEventListener('click', this._handlers.mobileSearchClose);
        }
        if (el.userDropdownToggle) {
            el.userDropdownToggle.addEventListener('click', this._handlers.userDropdownToggle);
        }
        if (el.themeToggle) {
            el.themeToggle.addEventListener('click', this._handlers.themeToggle);
        }

        document.addEventListener('keydown', this._handlers.keydown);
        window.addEventListener('resize', this._handlers.resize);
        document.addEventListener('themeChange', this._handlers.themeChange);
        document.addEventListener('pageContentLoaded', this._handlers.pageContentLoaded);

        this.updateThemeButtons();
        this.updateThemeIcons();
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

        // Search listeners cleanup
        if (this._el.searchInput) {
            this._el.searchInput.removeEventListener('input', this._handlers.searchInput);
            this._el.searchInput.removeEventListener('focus', this._handlers.searchFocus);
        }
        if (this._el.searchClearBtn) {
            this._el.searchClearBtn.removeEventListener('click', this._handlers.searchClear);
        }
        if (this._el.mobileSearchToggle) {
            this._el.mobileSearchToggle.removeEventListener('click', this._handlers.mobileSearchOpen);
        }
        if (this._el.searchBackBtn) {
            this._el.searchBackBtn.removeEventListener('click', this._handlers.mobileSearchClose);
        }
        if (this._el.userDropdownToggle) {
            this._el.userDropdownToggle.removeEventListener('click', this._handlers.userDropdownToggle);
        }
        if (this._el.themeToggle) {
            this._el.themeToggle.removeEventListener('click', this._handlers.themeToggle);
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

    hideUserDropdown: function() {
        if (!this._el?.userDropdownMenu) return;
        this._el.userDropdownMenu.classList.add('opacity-0', 'scale-95');
        setTimeout(() => {
            if (this._el?.userDropdownMenu) {
                this._el.userDropdownMenu.classList.add('hidden');
            }
        }, 200);
    },

    updateThemeIcons: function(theme) {
        if (!this._el?.themeToggle) return;
        var isDark = theme ? (theme === 'dark') : document.documentElement.classList.contains('dark');
        var moon = this._el.themeToggle.querySelector('.fa-moon');
        var sun = this._el.themeToggle.querySelector('.fa-sun');
        if (moon) moon.classList.toggle('hidden', isDark);
        if (sun) sun.classList.toggle('hidden', !isDark);
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

window.DreamBDNavbar = DreamBDNavbar;
