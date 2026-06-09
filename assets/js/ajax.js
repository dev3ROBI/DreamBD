// ===== ENHANCED AJAX NAVIGATION SYSTEM WITH SKELETON LOADER =====

class AjaxNavigation {
    constructor() {
        this.isNavigating = false;
        this.currentPage = null;
        this.navigationTimeout = null;
        this.lastNavigationTime = 0;
        this.navigationQueue = [];
        this.skeletonEl = null;
        this.init();
    }

    init() {
        const urlParams = new URLSearchParams(window.location.search);
        this.currentPage = urlParams.get('page') || 'home';
        this.fullReloadPages = true;
        
        this.setupNavigation();
        this.setupHistory();
        this.setupErrorHandling();
        
        this.relocateModals();
        this.markActiveNav(this.currentPage);
        this.setupPerformanceMonitoring();

        this.skeletonEl = document.getElementById('pageSkeleton');
    }

    shouldSkipLink(link) {
        const href = link.getAttribute('href');
        if (!href || href === '#' || href.startsWith('#') || href.startsWith('javascript:')) return true;
        if (href.startsWith('mailto:') || href.startsWith('tel:')) return true;
        if (link.hasAttribute('download')) return true;
        if (link.getAttribute('target') === '_blank') return true;
        if (link.hostname && link.hostname !== window.location.hostname) return true;
        if (link.hasAttribute('data-no-ajax')) return true;
        return false;
    }

    extractPageFromHref(href) {
        try {
            const url = new URL(href, window.location.origin);
            if (url.hostname !== window.location.hostname) return null;
            const pageParam = url.searchParams.get('page');
            if (pageParam) return pageParam;
            if (url.pathname.endsWith('index.php') || url.pathname === '/') return 'home';
            return null;
        } catch {
            return null;
        }
    }

    requiresFullReload(page) {
        return true;
    }

    setupNavigation() {
        document.addEventListener('click', (e) => {
            if (e.defaultPrevented) return;
            const link = e.target.closest('a');
            if (!link) return;
            if (this.shouldSkipLink(link)) return;
            const href = link.getAttribute('href');
            const explicitPage = link.getAttribute('data-page');
            const page = explicitPage || this.extractPageFromHref(href);
            if (!page) return;
            e.preventDefault();
            this.showSkeleton(page || 'default');
            setTimeout(() => {
                window.location.href = href || `index.php?page=${page}`;
            }, 120);
        });

        document.addEventListener('submit', (e) => {
            const form = e.target;
            if (form.hasAttribute('data-ajax-form')) {
                e.preventDefault();
                this.handleFormSubmit(form);
            }
        });
    }

    addToNavigationQueue(page, href, pushState = true) {
        const navigationId = Date.now();
        const navigationTask = {
            id: navigationId,
            page,
            href,
            pushState,
            timestamp: Date.now()
        };
        this.navigationQueue.push(navigationTask);
        this.processNavigationQueue();
        return navigationId;
    }

    async processNavigationQueue() {
        if (this.isNavigating || this.navigationQueue.length === 0) return;

        const task = this.navigationQueue.shift();

        try {
            this.isNavigating = true;
            await this.navigateTo(task.page, task.href, task.pushState, task.id);
        } catch (error) {
            this.navigationQueue = this.navigationQueue.filter(t => t.id !== task.id);
        } finally {
            this.isNavigating = false;
            if (this.navigationQueue.length > 0) {
                setTimeout(() => this.processNavigationQueue(), 100);
            }
        }
    }

    setupHistory() {
        window.addEventListener('popstate', (e) => {
            const urlParams = new URLSearchParams(window.location.search);
            const page = urlParams.get('page') || 'home';
            if (page !== this.currentPage) {
                this.showSkeleton(page);
                setTimeout(() => {
                    window.location.reload();
                }, 80);
            }
        });

        window.addEventListener('load', () => {
            this.hideSkeleton();
            this.updateBrowserPerformance();
        });
    }

    setupErrorHandling() {
        window.addEventListener('offline', () => {});
        window.addEventListener('online', () => {});
        window.addEventListener('error', (e) => {});
    }

    showSkeleton(page) {
        if (!this.skeletonEl) return;
        const type = page || 'default';
        this.skeletonEl.setAttribute('data-skeleton-type', type);
        this.skeletonEl.classList.remove('hidden');
        this.skeletonEl.hidden = false;
        document.body.style.overflow = 'hidden';
        document.documentElement.style.overflow = 'hidden';
    }

    hideSkeleton() {
        if (!this.skeletonEl) return;
        this.skeletonEl.classList.add('hidden');
        this.skeletonEl.hidden = true;
        document.body.style.overflow = '';
        document.documentElement.style.overflow = '';
    }

    async executeScripts(container) {
        if (!container) return;
        
        const scripts = container.querySelectorAll('script');
        for (const oldScript of scripts) {
            await new Promise((resolve) => {
            const newScript = document.createElement('script');
            
            // Copy attributes
            Array.from(oldScript.attributes).forEach(attr => {
                newScript.setAttribute(attr.name, attr.value);
            });
            
            if (oldScript.src) {
                // For external scripts
                newScript.src = oldScript.src;
                newScript.async = false; // Maintain execution order
                newScript.onload = resolve;
                newScript.onerror = resolve;
            } else {
                // For inline scripts, wrap in a way that handles DOMContentLoaded
                let content = oldScript.textContent;
                
                // If the script is waiting for DOMContentLoaded, we should execute it immediately
                // since DOMContentLoaded has already fired for the initial page.
                if (content.includes('DOMContentLoaded') || content.includes('jQuery(function') || content.includes('$(function')) {
                    content = `(function() { 
                        const DOMContentLoaded = 'DOMContentLoaded';
                        const originalAddEventListener = document.addEventListener;
                        document.addEventListener = function(event, cb, opts) {
                            if (event === DOMContentLoaded) {
                                setTimeout(() => {
                                    try { cb({ type: DOMContentLoaded }); } catch(e) { console.error(e); }
                                }, 1);
                            } else {
                                originalAddEventListener.call(document, event, cb, opts);
                            }
                        };
                        try {
                            ${content}
                        } catch(e) {
                            console.error('Error executing inline script:', e);
                        } finally {
                            document.addEventListener = originalAddEventListener;
                        }
                    })();`;
                }
                newScript.textContent = content;
                resolve();
            }
            
            oldScript.parentNode.replaceChild(newScript, oldScript);
            });
        }
    }

    runPageInitializers(page) {
        const initTasks = [
            () => window.DreamBD?.refreshPage?.(page),
            () => window.DreamBDNavbar?.init?.(),
            () => window.initCommunity?.(),
            () => window.AuthHandler?.initPageEnhancements?.()
        ];

        if (page === 'home') {
            initTasks.push(() => window.maybeInitHomePage?.(true));
        }

        if (page === 'profile') {
            initTasks.push(() => {
                if (typeof window.tryInitProfile === 'function') {
                    window.resetProfileInitAttempts?.();
                    window.tryInitProfile();
                } else if (typeof window.initProfileManager === 'function') {
                    window.initProfileManager();
                }
            });
        }

        initTasks.forEach((task) => {
            try {
                task();
            } catch (error) {
                console.error('Page initializer failed:', error);
            }
        });
    }

    async navigateTo(page, href, pushState = true, navigationId = null) {
        const mainContent = document.querySelector('.main-content');
        const contentArea = document.getElementById('pageContent');

        try {
            if (this.requiresFullReload(page)) {
                window.location.href = href || `index.php?page=${page}`;
                return;
            }

            if (!navigator.onLine) {
                throw new Error('No internet connection');
            }

            this.showSkeleton(page);

            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 30000);

            const response = await fetch(`index.php?page=${page}&ajax=1&_=${Date.now()}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Ajax-Navigation': 'true',
                    'X-Current-Page': this.currentPage,
                    'X-Request-ID': navigationId || Date.now(),
                    'Cache-Control': 'no-cache',
                    'Pragma': 'no-cache'
                },
                credentials: 'same-origin',
                signal: controller.signal
            });

            clearTimeout(timeoutId);

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();

            if (data.redirect) {
                window.location.href = data.redirect;
                return;
            }

            if (!data.content || !data.title) {
                throw new Error('Invalid response format');
            }

            if (mainContent && contentArea) {
                contentArea.classList.add('content-exiting');
                await new Promise(resolve => setTimeout(resolve, 200));

                contentArea.innerHTML = data.content;
                contentArea.classList.remove('content-exiting');
                contentArea.classList.add('content-entering');

                contentArea.querySelectorAll('link[rel="stylesheet"]').forEach(function(oldLink) {
                    try {
                        var newLink = document.createElement('link');
                        newLink.rel = 'stylesheet';
                        newLink.href = oldLink.href;
                        oldLink.parentNode.replaceChild(newLink, oldLink);
                    } catch (e) {}
                });
                
                // Execute scripts in the new content
                await this.executeScripts(contentArea);

                // Dispatch events AFTER scripts are executed
                setTimeout(() => {
                    this.dispatchPageEvent('pageContentLoaded', {
                        page: page,
                        navigationId: navigationId,
                        data: data
                    });
                }, 50);
            }

            document.title = `${data.title} - DreamBD`;

            if (pushState) {
                history.pushState({
                    page: page,
                    timestamp: Date.now(),
                    navigationId: navigationId
                }, data.title, href);
            }

            this.currentPage = page;
            this.markActiveNav(page);
            this.relocateModals();
            this.updateMetaTags(data, page);
            this.hideSkeleton();

            if (contentArea) {
                this.dispatchPageEvent('pageChanged', {
                    page: page,
                    data: data,
                    timestamp: Date.now(),
                    navigationId: navigationId
                });
                this.initializePageScripts();
                this.runPageInitializers(page);
                this.updateBrowserPerformance();
                contentArea.classList.remove('content-entering');
                contentArea.classList.add('content-entered');
                window.scrollTo({ top: 0, behavior: 'smooth' });
                this.focusMainContent();
            }

            this.trackNavigation(page, 'success');

        } catch (error) {
            this.hideSkeleton();
            this.trackNavigation(page, 'error', error.message);

            if (error.name === 'AbortError' || error.message.includes('timeout') ||
                error.message.includes('HTTP 5')) {
                setTimeout(() => {
                    window.location.href = href;
                }, 1500);
            }

            throw error;
        }
    }

    markActiveNav(page) {
        document.querySelectorAll('[data-page]').forEach(element => {
            const isActive = element.getAttribute('data-page') === page;
            if (isActive) {
                element.classList.add('active');
                element.setAttribute('aria-current', 'page');
            } else {
                element.classList.remove('active');
                element.removeAttribute('aria-current');
            }
        });
    }

    updateMetaTags(data, page) {
        if (data.description) {
            let metaDesc = document.querySelector('meta[name="description"]');
            if (!metaDesc) {
                metaDesc = document.createElement('meta');
                metaDesc.name = 'description';
                document.head.appendChild(metaDesc);
            }
            metaDesc.content = data.description;
        }

        let canonical = document.querySelector('link[rel="canonical"]');
        if (!canonical) {
            canonical = document.createElement('link');
            canonical.rel = 'canonical';
            document.head.appendChild(canonical);
        }
        canonical.href = `${window.location.origin}${window.location.pathname}?page=${page}`;

        if (data.og_title) this.updateMetaProperty('og:title', data.og_title);
        if (data.og_description) this.updateMetaProperty('og:description', data.og_description);
        if (data.og_image) this.updateMetaProperty('og:image', data.og_image);
    }

    updateMetaProperty(property, content) {
        let meta = document.querySelector(`meta[property="${property}"]`);
        if (!meta) {
            meta = document.createElement('meta');
            meta.setAttribute('property', property);
            document.head.appendChild(meta);
        }
        meta.content = content;
    }

    focusMainContent() {
        const mainContent = document.querySelector('.main-content');
        if (mainContent) {
            mainContent.setAttribute('tabindex', '-1');
            mainContent.focus();
            setTimeout(() => {
                mainContent.removeAttribute('tabindex');
            }, 100);
        }
    }

    initializePageScripts() {
        const pageScripts = document.querySelectorAll('[data-init-script]');
        pageScripts.forEach(script => {
            try {
                const scriptName = script.getAttribute('data-init-script');
                if (window[scriptName] && typeof window[scriptName] === 'function') {
                    window[scriptName]();
                }
            } catch (error) {}
        });

        const ajaxForms = document.querySelectorAll('form[data-ajax-form]');
        ajaxForms.forEach(form => {
            this.setupAjaxForm(form);
        });
    }

    setupAjaxForm(form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitButton = form.querySelector('[type="submit"]');

            try {
                const formData = new FormData(form);
                const response = await fetch(form.action, {
                    method: form.method,
                    body: formData,
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const result = await response.json();

                if (result.success) {
                    if (result.redirect) {
                        setTimeout(() => {
                            this.addToNavigationQueue(
                                result.redirect.replace('?page=', ''),
                                result.redirect,
                                true
                            );
                        }, 1000);
                    }
                    if (result.resetForm) {
                        form.reset();
                    }
                }
            } catch (error) {}
        });
    }

    async handleFormSubmit(form) {
        return this.setupAjaxForm(form);
    }

    updateSessionData(data) {
        if (data.user_logged_in !== undefined) {
            sessionStorage.setItem('user_logged_in', data.user_logged_in);
            if (data.user_name) {
                sessionStorage.setItem('user_name', data.user_name);
            }
        }
    }

    formatPageName(page) {
        return page.replace(/-/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    }

    trackNavigation(page, status, error = null) {
        const navigationData = {
            page: page,
            status: status,
            timestamp: Date.now(),
            error: error,
            previousPage: this.currentPage,
            loadTime: performance.now()
        };

        const navHistory = JSON.parse(sessionStorage.getItem('nav_history') || '[]');
        navHistory.push(navigationData);
        if (navHistory.length > 50) navHistory.shift();
        sessionStorage.setItem('nav_history', JSON.stringify(navHistory));
    }

    setupPerformanceMonitoring() {
        if (window.performance && performance.mark) {
            performance.mark('ajax-navigation-init');
        }

        if (performance.memory) {
            setInterval(() => {
                const memory = performance.memory;
                if (memory.usedJSHeapSize > 500000000) {}
            }, 30000);
        }
    }

    updateBrowserPerformance() {
        if (window.performance && performance.getEntriesByType) {
            const navEntries = performance.getEntriesByType('navigation');
            if (navEntries.length > 0) {}
        }
    }

    dispatchPageEvent(eventName, detail) {
        const event = new CustomEvent(eventName, {
            detail: detail,
            bubbles: true,
            cancelable: true
        });
        document.dispatchEvent(event);
    }

    navigate(page, href = `?page=${page}`) {
        this.showSkeleton(page);
        window.location.href = href || `index.php?page=${page}`;
    }

    reload() {
        this.showSkeleton(this.currentPage);
        window.location.reload();
    }

    getCurrentPage() {
        return this.currentPage;
    }

    getNavigationHistory() {
        return JSON.parse(sessionStorage.getItem('nav_history') || '[]');
    }

    clearNavigationQueue() {
        this.navigationQueue = [];
    }

    isPageLoading() {
        return this.isNavigating || this.navigationQueue.length > 0;
    }

    relocateModals() {
        var modalRoot = document.getElementById('modalRoot');
        if (!modalRoot) return;
        modalRoot.innerHTML = '';
        var selectors = '.gp-modal, .gp-overlay, .gp-modal-overlay, .gp-success-overlay, .p2p-detail-overlay, .pinned-modal-overlay, .forward-modal-overlay, .edit-dialog-overlay, .confirm-dialog-overlay';
        var mainContent = document.querySelector('.main-content');
        if (mainContent) {
            [].slice.call(mainContent.querySelectorAll(selectors)).forEach(function(el) {
                modalRoot.appendChild(el);
            });
        }
        [].slice.call(document.querySelectorAll('body > ' + selectors)).forEach(function(el) {
            modalRoot.appendChild(el);
        });
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const disableAjax = document.documentElement.hasAttribute('data-no-ajax');

    if (!disableAjax && !window.AjaxNavigation) {
        window.AjaxNavigation = new AjaxNavigation();

        window.addEventListener('error', (e) => {
            if (window.AjaxNavigation && window.AjaxNavigation.isPageLoading()) {}
        });

        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible' && window.AjaxNavigation) {
                const lastActive = parseInt(sessionStorage.getItem('last_active') || '0');
                const now = Date.now();
                if (now - lastActive > 300000) {}
                sessionStorage.setItem('last_active', now.toString());
            }
        });

        sessionStorage.setItem('last_active', Date.now().toString());
    }

    function hideInitialSkeleton() {
        var skeleton = document.getElementById('pageSkeleton');
        if (skeleton) {
            skeleton.classList.add('hidden');
            skeleton.hidden = true;
        }
    }

    if (document.readyState === 'complete') {
        hideInitialSkeleton();
    } else {
        window.addEventListener('load', hideInitialSkeleton);
    }
});

if (typeof module !== 'undefined' && module.exports) {
    module.exports = AjaxNavigation;
}
