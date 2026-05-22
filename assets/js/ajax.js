// ===== ENHANCED AJAX NAVIGATION SYSTEM =====

class AjaxNavigation {
    constructor() {
        this.isNavigating = false;
        this.currentPage = null;
        this.navigationTimeout = null;
        this.lastNavigationTime = 0;
        this.navigationQueue = [];
        this.init();
    }

    init() {
        // Get current page from URL
        const urlParams = new URLSearchParams(window.location.search);
        this.currentPage = urlParams.get('page') || 'home';
        
        // Set up event listeners
        this.setupNavigation();
        this.setupHistory();
        this.setupErrorHandling();
        
        // Move modals to modalRoot on initial load
        this.relocateModals();
        
        // Initial page load
        this.markActiveNav(this.currentPage);
        
        // Add performance monitoring
        this.setupPerformanceMonitoring();
    }

    setupNavigation() {
        // Handle link clicks with debounce
        document.addEventListener('click', (e) => {
            const link = e.target.closest('a[data-page]');
            if (!link || link.getAttribute('href') === '#') return;
            
            e.preventDefault();
            
            const page = link.getAttribute('data-page');
            const href = link.getAttribute('href');
            
            // If moving to the messages page from a thread, always force navigation to show the inbox list
            const isToMessagesList = (page === 'messages' && !href.includes('user='));
            const isToThread = (page === 'messages' && href.includes('user='));
            
            // Allow navigation if it's a different page, or same page but different query params
            if (page === this.currentPage && href === window.location.href) {
                // If on messages page and trying to go to list, force refresh
                if (isToMessagesList) {
                    this.addToNavigationQueue(page, href, true);
                }
                return;
            }
            
            // Debounce rapid clicks (300ms)
            const now = Date.now();
            if (now - this.lastNavigationTime < 300) {
                return;
            }
            this.lastNavigationTime = now;
            
            // Add to navigation queue
            this.addToNavigationQueue(page, href, true);
        });
        
        // Handle form submissions that might trigger navigation
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
        
        // Add to queue
        this.navigationQueue.push(navigationTask);
        
        // Process queue
        this.processNavigationQueue();
        
        return navigationId;
    }

    async processNavigationQueue() {
        // If already processing or empty queue, return
        if (this.isNavigating || this.navigationQueue.length === 0) {
            return;
        }
        
        // Get next navigation task
        const task = this.navigationQueue.shift();
        
        try {
            this.isNavigating = true;
            await this.navigateTo(task.page, task.href, task.pushState, task.id);
        } catch (error) {
            console.error('Navigation error:', error);
            // Remove failed task from queue
            this.navigationQueue = this.navigationQueue.filter(t => t.id !== task.id);
        } finally {
            this.isNavigating = false;
            
            // Process next task if available
            if (this.navigationQueue.length > 0) {
                setTimeout(() => this.processNavigationQueue(), 100);
            }
        }
    }

    setupHistory() {
        // Handle browser back/forward
        window.addEventListener('popstate', (e) => {
            const urlParams = new URLSearchParams(window.location.search);
            const page = urlParams.get('page') || 'home';
            
            if (page !== this.currentPage) {
                this.addToNavigationQueue(page, window.location.href, false);
            }
        });
        
        // Handle page load
        window.addEventListener('load', () => {
            this.updateBrowserPerformance();
        });
    }

    setupErrorHandling() {
        // Network error handling
        window.addEventListener('offline', () => {
            console.warn('You are offline. Please check your connection.');
        });

        window.addEventListener('online', () => {
            console.log('Back online!');
        });

        // Error boundary for unhandled errors
        window.addEventListener('error', (e) => {
            console.error('Global error:', e.error);
        });
    }

    async navigateTo(page, href, pushState = true, navigationId = null) {
        try {
            // Check network status
            if (!navigator.onLine) {
                throw new Error('No internet connection');
            }
            
            // Create abort controller for timeout
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 30000); // 30 second timeout
            
            // Fetch page content with enhanced headers
            const response = await fetch(`index.php?page=${page}&ajax=1&_=${Date.now()}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Ajax-Navigation': 'true',
                    'X-Current-Page': this.currentPage,
                    'X-Request-ID': navigationId || Date.now(),
                    'Cache-Control': 'no-cache, no-store, must-revalidate',
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
            
            // Check for redirect
            if (data.redirect) {
                window.location.href = data.redirect;
                return;
            }
            
            // Validate response
            if (!data.content || !data.title) {
                throw new Error('Invalid response format');
            }
            
            // Update content with smooth transition
            await this.updateContent(data, page, href, pushState, navigationId);
            
            // Update user session data if available
            this.updateSessionData(data);
            
            // Track successful navigation
            this.trackNavigation(page, 'success');
            
        } catch (error) {
            console.error('Navigation error:', error);
            
            // Track failed navigation
            this.trackNavigation(page, 'error', error.message);
            
            // Fallback to normal navigation for critical errors
            if (error.name === 'AbortError' || error.message.includes('timeout') || 
                error.message.includes('HTTP 5')) {
                setTimeout(() => {
                    window.location.href = href;
                }, 1500);
            }
            
            throw error;
        }
    }

    async updateContent(data, page, href, pushState = true, navigationId = null) {
        const mainContent = document.querySelector('.main-content');
        if (!mainContent) {
            throw new Error('Main content element not found');
        }
        
        // Add exit animation to current content
        mainContent.classList.add('content-exiting');
        
        // Wait for exit animation (non-blocking)
        await new Promise(resolve => {
            setTimeout(resolve, 300);
        });
        
        // Update content
        document.title = `${data.title} - DreamBD`;
        
        mainContent.innerHTML = data.content;
        
        // Move modals to #modalRoot (outside mainContent) to fix z-index stacking
        this.relocateModals();
        
        mainContent.classList.remove('content-exiting');
        mainContent.classList.add('content-entering');
        
        // Re-execute inline scripts (innerHTML does NOT execute them)
        setTimeout(function() {
            mainContent.querySelectorAll('script').forEach(function(oldScr) {
                try {
                    var newScr = document.createElement('script');
                    newScr.textContent = oldScr.textContent || '';
                    if (oldScr.src) newScr.src = oldScr.src;
                    oldScr.parentNode.replaceChild(newScr, oldScr);
                } catch(e) { console.warn('Script exec:', e); }
            });
        }, 0);
        
        // Update URL and history
        if (pushState) {
            history.pushState({ 
                page: page, 
                timestamp: Date.now(),
                navigationId: navigationId 
            }, data.title, href);
        }
        
        // Update current page
        this.currentPage = page;
        
        // Update navigation
        this.markActiveNav(page);
        
        // Update meta tags
        this.updateMetaTags(data, page);
        
        // Add entrance animation
        setTimeout(() => {
            mainContent.classList.remove('content-entering');
            mainContent.classList.add('content-entered');
            
            // Smooth scroll to top
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
            
            // Focus management for accessibility
            this.focusMainContent();
            
            // Dispatch page loaded event
            this.dispatchPageEvent('pageChanged', { 
                page: page, 
                data: data,
                timestamp: Date.now(),
                navigationId: navigationId
            });
            
            // Initialize any scripts in the new content
            this.initializePageScripts();
            
            // Update browser performance metrics
            this.updateBrowserPerformance();
            
        }, 50);
    }

    markActiveNav(page) {
        // Update all navigation elements with data-page attribute
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
        
        // Update mobile menu if exists
        const mobileMenu = document.querySelector('.mobile-menu');
        if (mobileMenu && mobileMenu.classList.contains('active')) {
            // You might want to update mobile menu state here
        }
    }

    updateMetaTags(data, page) {
        // Update meta description if available
        if (data.description) {
            let metaDesc = document.querySelector('meta[name="description"]');
            if (!metaDesc) {
                metaDesc = document.createElement('meta');
                metaDesc.name = 'description';
                document.head.appendChild(metaDesc);
            }
            metaDesc.content = data.description;
        }
        
        // Update canonical URL
        let canonical = document.querySelector('link[rel="canonical"]');
        if (!canonical) {
            canonical = document.createElement('link');
            canonical.rel = 'canonical';
            document.head.appendChild(canonical);
        }
        canonical.href = `${window.location.origin}${window.location.pathname}?page=${page}`;
        
        // Update Open Graph tags if available
        if (data.og_title) {
            this.updateMetaProperty('og:title', data.og_title);
        }
        if (data.og_description) {
            this.updateMetaProperty('og:description', data.og_description);
        }
        if (data.og_image) {
            this.updateMetaProperty('og:image', data.og_image);
        }
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
        // Focus main content for screen readers
        const mainContent = document.querySelector('.main-content');
        if (mainContent) {
            mainContent.setAttribute('tabindex', '-1');
            mainContent.focus();
            
            // Remove tabindex after focus
            setTimeout(() => {
                mainContent.removeAttribute('tabindex');
            }, 100);
        }
    }

    initializePageScripts() {
        // Initialize any page-specific scripts
        const pageScripts = document.querySelectorAll('[data-init-script]');
        pageScripts.forEach(script => {
            try {
                const scriptName = script.getAttribute('data-init-script');
                if (window[scriptName] && typeof window[scriptName] === 'function') {
                    window[scriptName]();
                }
            } catch (error) {
                console.warn('Failed to initialize page script:', error);
            }
        });
        
        // Initialize forms with AJAX support
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
                    // Handle redirect if needed
                    if (result.redirect) {
                        setTimeout(() => {
                            this.addToNavigationQueue(
                                result.redirect.replace('?page=', ''),
                                result.redirect,
                                true
                            );
                        }, 1000);
                    }
                    
                    // Reset form if needed
                    if (result.resetForm) {
                        form.reset();
                    }
                }
            } catch (error) {
                console.error('Form submission error:', error);
            }
        });
    }

    async handleFormSubmit(form) {
        return this.setupAjaxForm(form);
    }

    updateSessionData(data) {
        // Update user session info if available
        if (data.user_logged_in !== undefined) {
            // Store in session storage for persistence
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
        // Track navigation for analytics
        const navigationData = {
            page: page,
            status: status,
            timestamp: Date.now(),
            error: error,
            previousPage: this.currentPage,
            loadTime: performance.now()
        };
        
        // Store in session storage for debugging
        const navHistory = JSON.parse(sessionStorage.getItem('nav_history') || '[]');
        navHistory.push(navigationData);
        if (navHistory.length > 50) navHistory.shift(); // Keep last 50 navigations
        sessionStorage.setItem('nav_history', JSON.stringify(navHistory));
        
        // Log to console in development
        if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
            console.log(`Navigation: ${page} - ${status}`, navigationData);
        }
        
        // Send to analytics if available
        if (window.gtag && status === 'success') {
            gtag('event', 'page_view', {
                page_title: this.formatPageName(page),
                page_location: window.location.href,
                page_path: `/?page=${page}`
            });
        }
    }

    setupPerformanceMonitoring() {
        // Monitor navigation performance
        if (window.performance && performance.mark) {
            // Mark initial page load
            performance.mark('ajax-navigation-init');
        }
        
        // Monitor memory usage
        if (performance.memory) {
            setInterval(() => {
                const memory = performance.memory;
                if (memory.usedJSHeapSize > 500000000) { // 500MB
                    console.warn('High memory usage detected:', memory);
                }
            }, 30000); // Check every 30 seconds
        }
    }

    updateBrowserPerformance() {
        // Update browser performance metrics
        if (window.performance && performance.getEntriesByType) {
            const navEntries = performance.getEntriesByType('navigation');
            if (navEntries.length > 0) {
                const nav = navEntries[0];
                console.log(`Page load performance: ${Math.round(nav.domContentLoadedEventEnd - nav.startTime)}ms`);
            }
        }
    }

    dispatchPageEvent(eventName, detail) {
        // Dispatch custom event for page changes
        const event = new CustomEvent(eventName, {
            detail: detail,
            bubbles: true,
            cancelable: true
        });
        
        document.dispatchEvent(event);
    }

    // Public API methods
    navigate(page, href = `?page=${page}`) {
        return this.addToNavigationQueue(page, href, true);
    }
    
    reload() {
        this.addToNavigationQueue(this.currentPage, window.location.href, false);
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
        modalRoot.innerHTML = ''; // Clear orphaned modals from previous page
        var selectors = '.gp-modal, .gp-overlay, .gp-modal-overlay, .gp-success-overlay, .p2p-detail-overlay, .pinned-modal-overlay, .forward-modal-overlay, .edit-dialog-overlay, .confirm-dialog-overlay';
        var mainContent = document.querySelector('.main-content');
        if (mainContent) {
            [].slice.call(mainContent.querySelectorAll(selectors)).forEach(function(el) {
                modalRoot.appendChild(el);
            });
        }
        // Also catch any modals already at body level
        [].slice.call(document.querySelectorAll('body > ' + selectors)).forEach(function(el) {
            modalRoot.appendChild(el);
        });
    }
}

// Initialize AJAX navigation when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    // Check if AjaxNavigation should be enabled
    const disableAjax = document.documentElement.hasAttribute('data-no-ajax');
    
    if (!disableAjax && !window.AjaxNavigation) {
        window.AjaxNavigation = new AjaxNavigation();
        
        // Add to global error handler
        window.addEventListener('error', (e) => {
            if (window.AjaxNavigation && window.AjaxNavigation.isPageLoading()) {
                console.error('Navigation error caught:', e.error);
            }
        });
        
        // Handle page visibility changes
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible' && window.AjaxNavigation) {
                // Page became visible - check if we need to refresh
                const lastActive = parseInt(sessionStorage.getItem('last_active') || '0');
                const now = Date.now();
                
                if (now - lastActive > 300000) { // 5 minutes
                    console.log('Page was inactive for 5+ minutes');
                    // Optionally refresh data or update content
                }
                
                sessionStorage.setItem('last_active', now.toString());
            }
        });
        
        // Store initial active time
        sessionStorage.setItem('last_active', Date.now().toString());
        
        console.log('AjaxNavigation initialized');
    }
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AjaxNavigation;
}