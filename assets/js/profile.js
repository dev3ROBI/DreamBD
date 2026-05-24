class ProfileManager {
    constructor() {
        this.root = document.querySelector('[data-profile-page]') || document.querySelector('[data-home-page]');
        this.selectedAvatarFile = null;
        this.avatarCrop = { zoom: 1, x: 0, y: 0 };
        this.avatarCropBounds = { minZoom: 1, maxZoom: 3, maxX: 0, maxY: 0, circleSize: 0, baseScale: 1 };
        this.handlerUrl = 'handlers/profile_handlers.php';
        this.csrfToken = this.root?.dataset.csrfToken || '';

        if (!this.root) {
            return;
        }

        this.init();
    }

    init() {
        this.destroy(); // Clean up any existing listeners if this instance is re-initialized

        this._handlers = {
            resize: () => this.refreshAvatarCropEditor(),
            hashchange: () => {
                const hash = window.location.hash.replace('#', '');
                if (hash && ['timeline','about','friends','photos'].includes(hash)) {
                    this.switchMainTab(hash);
                }
            },
            socialClick: (event) => {
                const friendButton = event.target.closest('.friend-toggle-btn');
                const friendResponse = event.target.closest('.friend-response-btn');
                const dismissButton = event.target.closest('[data-dismiss-user-id]');

                if (friendButton) {
                    this.handleFriendAction(friendButton);
                } else if (friendResponse) {
                    this.respondToFriendRequest(friendResponse);
                } else if (dismissButton) {
                    this.dismissSuggestion(dismissButton);
                }
            },
            globalFriendClick: (event) => {
                const fb = event.target.closest('.friend-toggle-btn, .friend-response-btn');
                if (!fb) return;
                event.preventDefault();
                if (fb.classList.contains('friend-toggle-btn')) {
                    this.handleFriendAction(fb);
                } else if (fb.classList.contains('friend-response-btn')) {
                    this.respondToFriendRequest(fb);
                }
            }
        };

        this.setupNavigation();
        this.setupModal();
        this.setupAvatarUpload();
        this.setupCoverUpload();
        this.setupForms();
        this.setupSessionDeletion();

        this.setupFriendsView();
        this.setupTheme();
        this.applySavedTheme();
        this.animateCounters();
        
        window.addEventListener('resize', this._handlers.resize);
        window.addEventListener('hashchange', this._handlers.hashchange);
        document.addEventListener('click', this._handlers.socialClick);
        document.addEventListener('click', this._handlers.globalFriendClick);
    }

    destroy() {
        if (this._handlers) {
            window.removeEventListener('resize', this._handlers.resize);
            window.removeEventListener('hashchange', this._handlers.hashchange);
            document.removeEventListener('click', this._handlers.socialClick);
            document.removeEventListener('click', this._handlers.globalFriendClick);
        }
        this._handlers = null;
    }

    setupNavigation() {
        this.bindSettingsTabs();
        const hash = window.location.hash.replace('#', '');
        if (['timeline', 'about', 'friends', 'photos'].includes(hash)) {
            this.switchMainTab(hash);
        } else {
            this.switchMainTab('timeline');
        }
        document.querySelectorAll('[data-scroll-target]').forEach((button) => {
            button.addEventListener('click', () => {
                const target = document.querySelector(button.dataset.scrollTarget);
                target?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });
    }

    bindSettingsTabs() {
        document.querySelectorAll('#profileSettingsDialog .profile-settings-nav .nav-item').forEach((item) => {
            const stab = item.dataset.stab;
            item.onclick = (e) => { e.preventDefault(); this.switchSettingsTab(stab); };
        });
        this.root.querySelectorAll('[data-profile-tab]').forEach((item) => {
            const tab = item.dataset.tab;
            item.onclick = (e) => {
                e.preventDefault();
                const shouldScroll = item.classList.contains('profile-mobile-bottom-link') || window.matchMedia('(max-width: 767px)').matches;
                this.switchMainTab(tab, { scroll: shouldScroll });
                history.pushState(null, '', `#${tab}`);
            };
        });
        // Listen for hash changes (browser back/forward, external links)
        window.addEventListener('hashchange', () => {
            const hash = window.location.hash.replace('#', '');
            if (hash && ['timeline','about','friends','photos'].includes(hash)) {
                this.switchMainTab(hash);
            }
        });
    }

    switchMainTab(tab, options = {}) {
        let targetContent = null;
        this.root.querySelectorAll('[data-profile-tab]').forEach((item) => {
            item.classList.toggle('active', item.dataset.tab === tab);
        });
        this.root.querySelectorAll('.profile-main > .tab-content').forEach((content) => {
            const isTarget = content.id === `${tab}Tab`;
            if (isTarget) {
                targetContent = content;
                content.classList.add('active');
                content.style.opacity = '0';
                requestAnimationFrame(() => { content.style.opacity = '1'; });
            } else {
                content.classList.remove('active');
                content.style.opacity = '';
            }
        });
        history.replaceState(null, '', `#${tab}`);
        if (options.scroll && targetContent) {
            const scrollToTarget = () => {
                const navOffset = document.querySelector('.profile-nav')?.getBoundingClientRect().height || 0;
                const top = targetContent.getBoundingClientRect().top + window.scrollY - navOffset - 12;
                window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
            };
            requestAnimationFrame(() => {
                scrollToTarget();
                window.setTimeout(scrollToTarget, 180);
            });
        }
    }

    switchSettingsTab(tab) {
        document.querySelectorAll('.profile-settings-nav .nav-item').forEach((item) => {
            item.classList.toggle('active', item.dataset.stab === tab);
        });
        document.querySelectorAll('#profileSettingsDialog .stab-content').forEach((content) => {
            const isTarget = content.id === `s${tab.charAt(0).toUpperCase() + tab.slice(1)}Tab`;
            if (isTarget) {
                content.classList.add('active');
                content.style.opacity = '0';
                requestAnimationFrame(() => { content.style.opacity = '1'; });
            } else {
                content.classList.remove('active');
                content.style.opacity = '';
            }
        });
    }

    setupModal() {
        // Open buttons
        document.querySelectorAll('[data-open-profile-settings]').forEach((btn) => {
            btn.addEventListener('click', (e) => { e.preventDefault(); this.openSiteDialog('profileSettingsDialog'); });
        });
        document.querySelectorAll('[data-open-avatar-upload]').forEach((btn) => {
            btn.addEventListener('click', (e) => { e.preventDefault(); this.resetAvatarDialog(); this.openSiteDialog('avatarUploadDialog'); });
        });
        document.querySelectorAll('[data-open-cover-upload]').forEach((btn) => {
            btn.addEventListener('click', (e) => { e.preventDefault(); this.resetCoverDialog(); this.openSiteDialog('coverUploadDialog'); });
        });
        // Close buttons
        document.querySelectorAll('[data-close-profile-settings]').forEach((btn) => {
            btn.addEventListener('click', () => this.closeSiteDialog('profileSettingsDialog'));
        });
        document.querySelectorAll('[data-close-avatar-upload]').forEach((btn) => {
            btn.addEventListener('click', () => this.closeSiteDialog('avatarUploadDialog'));
        });
        document.querySelectorAll('[data-close-cover-upload]').forEach((btn) => {
            btn.addEventListener('click', () => this.closeSiteDialog('coverUploadDialog'));
        });
        // Backdrop clicks
        ['profileDialogBackdrop', 'avatarDialogBackdrop', 'coverDialogBackdrop'].forEach((id) => {
            document.getElementById(id)?.addEventListener('click', () => {
                const map = { profileDialogBackdrop: 'profileSettingsDialog', avatarDialogBackdrop: 'avatarUploadDialog', coverDialogBackdrop: 'coverUploadDialog' };
                this.closeSiteDialog(map[id]);
            });
        });
    }

    openSiteDialog(id) {
        const dialog = document.getElementById(id);
        // Create backdrop dynamically if not already in DOM
        let backdrop = document.getElementById('profile-blur-backdrop');
        if (!backdrop) {
            backdrop = document.createElement('div');
            backdrop.id = 'profile-blur-backdrop';
            backdrop.style.cssText = 'position:fixed;inset:0;z-index:1199;background:rgba(15,23,42,0.55);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);';
            document.body.appendChild(backdrop);
        }
        backdrop.style.display = 'block';
        if (dialog) { dialog.hidden = false; dialog.classList.remove('hidden'); dialog.classList.add('grid'); }
        this.siteDialogScrollY = window.scrollY || document.documentElement.scrollTop || 0;
        document.documentElement.classList.add('dialog-open');
        document.body.classList.add('dialog-open');
        document.body.style.position = 'fixed';
        document.body.style.width = '100%';
        document.body.style.top = `-${this.siteDialogScrollY}px`;
    }

    closeSiteDialog(id) {
        const dialog = document.getElementById(id);
        const backdrop = document.getElementById('profile-blur-backdrop');
        if (backdrop) backdrop.style.display = 'none';
        if (dialog) { dialog.hidden = true; dialog.classList.add('hidden'); dialog.classList.remove('grid', 'flex'); }
        document.documentElement.classList.remove('dialog-open');
        document.body.classList.remove('dialog-open');
        document.body.style.position = '';
        document.body.style.width = '';
        document.body.style.top = '';
        window.scrollTo(0, this.siteDialogScrollY || 0);
    }

    setupAvatarUpload() {
        const editBtn = document.getElementById('editAvatarBtn');
        const uploadArea = document.getElementById('avatarUploadArea');
        const fileInput = document.getElementById('avatarFile');
        const uploadBtn = document.getElementById('uploadAvatarBtn');
        const cropInputs = ['avatarCropZoom', 'avatarCropX', 'avatarCropY'];

        editBtn?.addEventListener('click', () => {
            this.resetAvatarDialog();
            this.openSiteDialog('avatarUploadDialog');
        });

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach((eventName) => {
            uploadArea?.addEventListener(eventName, (event) => {
                event.preventDefault();
                event.stopPropagation();
            });
        });

        ['dragenter', 'dragover'].forEach((eventName) => {
            uploadArea?.addEventListener(eventName, () => uploadArea.classList.add('dragover'));
        });

        ['dragleave', 'drop'].forEach((eventName) => {
            uploadArea?.addEventListener(eventName, () => uploadArea.classList.remove('dragover'));
        });

        uploadArea?.addEventListener('click', () => fileInput?.click());
        uploadArea?.addEventListener('drop', (event) => this.previewAvatar(event.dataTransfer.files[0]));
        fileInput?.addEventListener('change', (event) => this.previewAvatar(event.target.files[0]));
        document.getElementById('avatarChooseAnother')?.addEventListener('click', () => {
            this.resetAvatarDialog();
            fileInput?.click();
        });
        uploadBtn?.addEventListener('click', () => {
            if (this.selectedAvatarFile) {
                this.uploadAvatar(this.selectedAvatarFile);
            }
        });

        cropInputs.forEach((id) => {
            document.getElementById(id)?.addEventListener('input', () => this.updateAvatarCropPreview());
        });
        document.querySelectorAll('[data-avatar-zoom]').forEach((button) => {
            button.addEventListener('click', () => this.stepAvatarZoom(button.dataset.avatarZoom === 'in' ? 0.12 : -0.12));
        });
    }

    setupCoverUpload() {
        const coverArea = document.getElementById('coverUploadArea');
        const coverInput = document.getElementById('coverFileInput');
        const uploadBtn = document.getElementById('uploadCoverBtn');

        coverArea?.addEventListener('click', () => coverInput?.click());
        document.getElementById('coverChooseAnother')?.addEventListener('click', () => {
            this.resetCoverDialog();
            coverInput?.click();
        });
        coverInput?.addEventListener('change', (event) => {
            const [file] = event.target.files || [];
            if (file) this.previewCover(file);
        });
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach((e) => {
            coverArea?.addEventListener(e, (ev) => { ev.preventDefault(); ev.stopPropagation(); });
        });
        ['dragenter', 'dragover'].forEach((e) => {
            coverArea?.addEventListener(e, () => coverArea.classList.add('dragover'));
        });
        ['dragleave', 'drop'].forEach((e) => {
            coverArea?.addEventListener(e, () => coverArea.classList.remove('dragover'));
        });
        coverArea?.addEventListener('drop', (event) => this.previewCover(event.dataTransfer.files[0]));
        uploadBtn?.addEventListener('click', () => {
            if (this.selectedCoverFile) this.uploadCover(this.selectedCoverFile);
        });
    }

    previewCover(file) {
        if (!file || !file.type.match('image.*')) return;
        if (file.size > 10 * 1024 * 1024) { this.showNotification('Cover must be under 10MB', 'error'); return; }
        this.selectedCoverFile = file;
        const chooseCard = document.getElementById('coverChooseCard');
        const previewCard = document.getElementById('coverPreviewCard');
        const studioGrid = document.getElementById('coverStudioGrid');
        if (chooseCard) chooseCard.hidden = true;
        if (previewCard) previewCard.hidden = false;
        studioGrid?.classList.add('is-previewing');
        const reader = new FileReader();
        reader.onload = ({ target }) => {
            const preview = document.getElementById('coverPreview');
            const img = document.getElementById('coverPreviewImg');
            const btn = document.getElementById('uploadCoverBtn');
            if (preview) preview.hidden = false;
            if (img) img.src = target.result;
            if (btn) btn.disabled = false;
        };
        reader.readAsDataURL(file);
    }

    resetCoverDialog() {
        this.selectedCoverFile = null;
        const chooseCard = document.getElementById('coverChooseCard');
        const previewCard = document.getElementById('coverPreviewCard');
        const preview = document.getElementById('coverPreview');
        const img = document.getElementById('coverPreviewImg');
        const input = document.getElementById('coverFileInput');
        const button = document.getElementById('uploadCoverBtn');
        const studioGrid = document.getElementById('coverStudioGrid');

        if (chooseCard) chooseCard.hidden = false;
        if (previewCard) previewCard.hidden = true;
        if (preview) preview.hidden = true;
        if (img) img.removeAttribute('src');
        if (input) input.value = '';
        if (button) button.disabled = true;
        studioGrid?.classList.remove('is-previewing');
    }

    previewAvatar(file) {
        if (!file || !file.type.match('image.*')) {
            this.showNotification('Please choose an image file', 'error');
            return;
        }

        if (file.size > 5 * 1024 * 1024) {
            this.showNotification('Avatar must be smaller than 5MB', 'error');
            return;
        }

        this.selectedAvatarFile = file;
        const chooseCard = document.getElementById('avatarChooseCard');
        const cropCard = document.getElementById('avatarCropCard');
        const studioGrid = document.getElementById('avatarStudioGrid');
        if (chooseCard) chooseCard.hidden = true;
        if (cropCard) cropCard.hidden = false;
        studioGrid?.classList.add('is-cropping');
        const reader = new FileReader();
        reader.onload = ({ target }) => {
            const previewPanel = document.getElementById('avatarPreview');
            const previewEmpty = document.getElementById('avatarPreviewEmpty');
            const previewImage = document.getElementById('avatarPreviewImg');
            const uploadButton = document.getElementById('uploadAvatarBtn');

            if (previewPanel) {
                previewPanel.hidden = false;
            }
            if (previewEmpty) {
                previewEmpty.hidden = true;
            }
            if (previewImage) {
                previewImage.src = target.result;
                previewImage.onload = () => {
                    this.resetAvatarCropInputs();
                    this.refreshAvatarCropEditor(true);
                };
            }
            if (uploadButton) {
                uploadButton.disabled = false;
            }
        };
        reader.readAsDataURL(file);
    }

    resetAvatarDialog() {
        this.selectedAvatarFile = null;
        this.avatarCrop = { zoom: 1, x: 0, y: 0 };
        this.avatarCropBounds = { minZoom: 1, maxZoom: 3, maxX: 0, maxY: 0, circleSize: 0, baseScale: 1 };
        const chooseCard = document.getElementById('avatarChooseCard');
        const cropCard = document.getElementById('avatarCropCard');
        const previewPanel = document.getElementById('avatarPreview');
        const previewImage = document.getElementById('avatarPreviewImg');
        const fileInput = document.getElementById('avatarFile');
        const uploadButton = document.getElementById('uploadAvatarBtn');
        const studioGrid = document.getElementById('avatarStudioGrid');

        if (chooseCard) chooseCard.hidden = false;
        if (cropCard) cropCard.hidden = true;
        if (previewPanel) previewPanel.hidden = true;
        if (previewImage) {
            previewImage.removeAttribute('src');
            previewImage.style.transform = '';
            previewImage.style.removeProperty('--crop-zoom');
            previewImage.style.removeProperty('--crop-translate-x');
            previewImage.style.removeProperty('--crop-translate-y');
        }
        if (fileInput) fileInput.value = '';
        if (uploadButton) uploadButton.disabled = true;
        studioGrid?.classList.remove('is-cropping');
        this.resetAvatarCropInputs();
    }

    async uploadAvatar(file) {
        const uploadFile = await this.getCroppedAvatarFile(file);
        const formData = new FormData();
        formData.append('avatar', uploadFile);
        formData.append('action', 'upload_avatar');
        formData.append('csrf_token', this.csrfToken);

        const button = document.getElementById('uploadAvatarBtn');
        const original = button.innerHTML;
        button.innerHTML = '<div class="loading"></div>';
        button.disabled = true;

        try {
            const data = await this.postForm(formData);
            if (!data.success) {
                throw new Error(data.message || 'Upload failed');
            }

            ['profileAvatar', 'timelineAvatar'].forEach((id) => {
                const image = document.getElementById(id);
                if (image) {
                    image.src = `${data.avatar_url}?t=${Date.now()}`;
                }
            });

            const previewEmpty = document.getElementById('avatarPreviewEmpty');
            const previewPanel = document.getElementById('avatarPreview');
            if (previewEmpty) {
                const emptyImage = previewEmpty.querySelector('img');
                if (emptyImage) {
                    emptyImage.src = `${data.avatar_url}?t=${Date.now()}`;
                }
                previewEmpty.hidden = false;
            }
            if (previewPanel) {
                previewPanel.hidden = true;
            }

            this.closeSiteDialog('avatarUploadDialog');
            this.showNotification('Profile picture updated', 'success');
        } catch (error) {
            this.showNotification(error.message, 'error');
        } finally {
            button.innerHTML = original;
            button.disabled = false;
        }
    }

    updateAvatarCropPreview() {
        const img = document.getElementById('avatarPreviewImg');
        if (!img || !img.naturalWidth || !img.naturalHeight) return;

        const metrics = this.getAvatarCropMetrics();
        if (!metrics) return;

        this.avatarCrop = {
            zoom: parseFloat(document.getElementById('avatarCropZoom')?.value || String(metrics.minZoom || 1)),
            x: parseFloat(document.getElementById('avatarCropX')?.value || '0'),
            y: parseFloat(document.getElementById('avatarCropY')?.value || '0')
        };

        this.avatarCrop.zoom = Math.max(metrics.minZoom, Math.min(metrics.maxZoom, this.avatarCrop.zoom));
        this.avatarCrop.x = Math.max(-100, Math.min(100, this.avatarCrop.x));
        this.avatarCrop.y = Math.max(-100, Math.min(100, this.avatarCrop.y));

        const zoomInput = document.getElementById('avatarCropZoom');
        if (zoomInput) {
            zoomInput.min = metrics.minZoom.toFixed(2);
            zoomInput.max = metrics.maxZoom.toFixed(2);
            zoomInput.value = this.avatarCrop.zoom.toFixed(2);
        }

        const translateX = (this.avatarCrop.x / 100) * metrics.maxX;
        const translateY = (this.avatarCrop.y / 100) * metrics.maxY;

        img.style.setProperty('--crop-zoom', this.avatarCrop.zoom);
        img.style.setProperty('--crop-translate-x', `${translateX}px`);
        img.style.setProperty('--crop-translate-y', `${translateY}px`);
    }

    stepAvatarZoom(delta) {
        const input = document.getElementById('avatarCropZoom');
        if (!input) return;

        const min = parseFloat(input.min || String(this.avatarCropBounds.minZoom || 1));
        const max = parseFloat(input.max || String(this.avatarCropBounds.maxZoom || 3));
        const next = Math.max(min, Math.min(max, (parseFloat(input.value || '1') + delta)));
        input.value = next.toFixed(2);
        this.updateAvatarCropPreview();
    }

    resetAvatarCropInputs() {
        this.avatarCrop = { zoom: 1, x: 0, y: 0 };
        ['avatarCropZoom', 'avatarCropX', 'avatarCropY'].forEach((id) => {
            const input = document.getElementById(id);
            if (!input) return;
            input.value = id === 'avatarCropZoom' ? '1' : '0';
        });
    }

    refreshAvatarCropEditor(resetPosition = false) {
        const img = document.getElementById('avatarPreviewImg');
        if (!img || !img.src || !img.naturalWidth || !img.naturalHeight) return;

        const metrics = this.getAvatarCropMetrics();
        if (!metrics) return;

        const zoomInput = document.getElementById('avatarCropZoom');
        if (zoomInput) {
            zoomInput.min = metrics.minZoom.toFixed(2);
            zoomInput.max = metrics.maxZoom.toFixed(2);
            if (resetPosition || parseFloat(zoomInput.value || '0') < metrics.minZoom) {
                zoomInput.value = metrics.minZoom.toFixed(2);
            }
        }

        if (resetPosition) {
            const xInput = document.getElementById('avatarCropX');
            const yInput = document.getElementById('avatarCropY');
            if (xInput) xInput.value = '0';
            if (yInput) yInput.value = '0';
        }

        this.updateAvatarCropPreview();
    }

    getAvatarCropMetrics() {
        const frame = document.querySelector('#avatarUploadDialog .avatar-crop-frame');
        const img = document.getElementById('avatarPreviewImg');
        if (!frame || !img || !img.naturalWidth || !img.naturalHeight) return null;

        const frameWidth = frame.clientWidth;
        const frameHeight = frame.clientHeight;
        if (!frameWidth || !frameHeight) return null;

        const circleRatio = this.getAvatarCircleRatio();
        const circleSize = Math.min(frameWidth, frameHeight) * circleRatio;
        const baseScale = Math.min(frameWidth / img.naturalWidth, frameHeight / img.naturalHeight);
        const baseWidth = img.naturalWidth * baseScale;
        const baseHeight = img.naturalHeight * baseScale;
        const minZoom = Math.max(circleSize / Math.max(baseWidth, 1), circleSize / Math.max(baseHeight, 1), 0.35);
        const maxZoom = Math.max(4, minZoom);
        const currentZoom = parseFloat(document.getElementById('avatarCropZoom')?.value || String(minZoom));
        const effectiveZoom = Math.max(minZoom, Math.min(maxZoom, currentZoom));
        const scaledWidth = baseWidth * effectiveZoom;
        const scaledHeight = baseHeight * effectiveZoom;
        const maxX = Math.max(0, (scaledWidth - circleSize) / 2);
        const maxY = Math.max(0, (scaledHeight - circleSize) / 2);

        this.avatarCropBounds = { minZoom, maxZoom, maxX, maxY, circleSize, baseScale };
        return this.avatarCropBounds;
    }

    getAvatarCircleRatio() {
        return window.matchMedia('(max-width: 767px)').matches ? 0.78 : 0.72;
    }

    async getCroppedAvatarFile(originalFile) {
        const img = document.getElementById('avatarPreviewImg');
        if (!img || !img.naturalWidth || !img.naturalHeight) {
            return originalFile;
        }

        const size = 512;
        const canvas = document.createElement('canvas');
        canvas.width = size;
        canvas.height = size;
        const ctx = canvas.getContext('2d');
        const metrics = this.getAvatarCropMetrics();
        if (!metrics) return originalFile;

        const zoom = Math.max(metrics.minZoom, this.avatarCrop.zoom || metrics.minZoom);
        const translateX = (this.avatarCrop.x / 100) * metrics.maxX;
        const translateY = (this.avatarCrop.y / 100) * metrics.maxY;
        const cropSize = metrics.circleSize / (metrics.baseScale * zoom);
        const centerX = (img.naturalWidth / 2) - (translateX / (metrics.baseScale * zoom));
        const centerY = (img.naturalHeight / 2) - (translateY / (metrics.baseScale * zoom));
        const sx = centerX - (cropSize / 2);
        const sy = centerY - (cropSize / 2);

        ctx.drawImage(
            img,
            Math.max(0, Math.min(img.naturalWidth - cropSize, sx)),
            Math.max(0, Math.min(img.naturalHeight - cropSize, sy)),
            cropSize,
            cropSize,
            0,
            0,
            size,
            size
        );

        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.92));
        if (!blob) return originalFile;
        return new File([blob], `cropped_${Date.now()}.jpg`, { type: 'image/jpeg' });
    }

    async uploadCover(file) {
        if (!file || !file.type.match('image.*')) {
            this.showNotification('Please choose an image file', 'error');
            return;
        }

        if (file.size > 10 * 1024 * 1024) {
            this.showNotification('Cover must be smaller than 10MB', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('cover', file);
        formData.append('action', 'update_cover');
        formData.append('csrf_token', this.csrfToken);

        try {
            const data = await this.postForm(formData);
            if (!data.success) {
                throw new Error(data.message || 'Cover upload failed');
            }

            const coverImage = document.querySelector('.profile-cover-img');
            if (coverImage) {
                coverImage.src = `${data.cover_url}?t=${Date.now()}`;
            }
            this.closeSiteDialog('coverUploadDialog');
            this.showNotification('Cover photo updated', 'success');
        } catch (error) {
            this.showNotification(error.message, 'error');
        }
    }

    setupForms() {
        document.getElementById('profileForm')?.addEventListener('submit', (event) => {
            event.preventDefault();
            this.submitProfileForm();
        });

        document.getElementById('passwordForm')?.addEventListener('submit', (event) => {
            event.preventDefault();
            this.submitSimpleForm(event.currentTarget, 'change_password', 'Password updated');
        });

        document.getElementById('preferencesForm')?.addEventListener('submit', (event) => {
            event.preventDefault();
            this.submitSimpleForm(event.currentTarget, 'update_preferences', 'Preferences updated', () => {
                const selectedTheme = document.querySelector('input[name="theme"]:checked')?.value || 'light';
                this.persistThemePreference(selectedTheme);
                this.applyThemePreference(selectedTheme);
            });
        });
    }

    setupSessionDeletion() {
        document.querySelectorAll('[data-delete-session]').forEach((button) => {
            button.addEventListener('click', () => this.deleteSession(button));
        });
    }

    async deleteSession(button) {
        if (!button?.dataset.sessionId) return;
        if (!window.confirm('Remove this saved session?')) return;

        const original = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<div class="loading"></div>';

        const formData = new FormData();
        formData.append('csrf_token', this.csrfToken);
        formData.append('action', 'delete_session');
        formData.append('session_id', button.dataset.sessionId);

        try {
            const data = await this.postForm(formData);
            if (!data.success) {
                throw new Error(data.message || 'Unable to remove session');
            }
            button.closest('[data-session-item]')?.remove();
            this.showNotification('Session removed', 'success');
        } catch (error) {
            button.innerHTML = original;
            button.disabled = false;
            this.showNotification(error.message, 'error');
        }
    }

    async submitProfileForm() {
        const form = document.getElementById('profileForm');
        const submitButton = form.querySelector('[type="submit"]');
        const original = submitButton.innerHTML;
        submitButton.innerHTML = '<div class="loading"></div>';
        submitButton.disabled = true;

        try {
            const formData = new FormData(form);
            formData.append('action', 'update_profile');
            const data = await this.postForm(formData);
            if (!data.success) {
                throw new Error(data.message || 'Update failed');
            }

            const fullName = data.user?.full_name || formData.get('full_name');
            const nameEl = document.querySelector('.profile-name-text') || document.querySelector('.profile-name');
            if (nameEl) nameEl.textContent = fullName;
            const bioEl = document.getElementById('profileBioText');
            if (bioEl) bioEl.textContent = data.user?.bio || '';
            this.showNotification('Profile updated successfully', 'success');
        } catch (error) {
            this.showNotification(error.message, 'error');
        } finally {
            submitButton.innerHTML = original;
            submitButton.disabled = false;
        }
    }

    async submitSimpleForm(form, action, successMessage, onSuccess) {
        const button = form.querySelector('[type="submit"]');
        const original = button.innerHTML;
        button.innerHTML = '<div class="loading"></div>';
        button.disabled = true;

        try {
            const formData = new FormData(form);
            formData.append('action', action);
            const data = await this.postForm(formData);
            if (!data.success) {
                throw new Error(data.message || 'Request failed');
            }

            if (typeof onSuccess === 'function') {
                onSuccess(data);
            }

            if (form.id !== 'preferencesForm') {
                form.reset();
            }
            this.showNotification(successMessage, 'success');
        } catch (error) {
            this.showNotification(error.message, 'error');
        } finally {
            button.innerHTML = original;
            button.disabled = false;
        }
    }

    previewPostImage(file) {
        const preview = document.getElementById('composerPreview');
        if (!preview) {
            return;
        }

        if (!file) {
            preview.innerHTML = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = ({ target }) => {
            preview.innerHTML = `<img src="${target.result}" alt="Preview">`;
        };
        reader.readAsDataURL(file);
    }

    setupFriendsView() {
        this.root.querySelectorAll('[data-friends-view]').forEach((button) => {
            button.addEventListener('click', () => {
                this.switchFriendsView(button.dataset.friendsView || 'all');
            });
        });
    }

    switchFriendsView(view) {
        this.root.querySelectorAll('[data-friends-view]').forEach((button) => {
            button.classList.toggle('active', button.dataset.friendsView === view);
        });

        this.root.querySelectorAll('[data-friends-panel]').forEach((panel) => {
            panel.classList.toggle('active', panel.dataset.friendsPanel === view);
        });
    }

    async handleFriendAction(button) {
        const isRemoval = button.dataset.action === 'remove_friend';
        if (isRemoval && !window.confirm('Remove this friend from your list?')) {
            return;
        }

        const formData = new FormData();
        formData.append('csrf_token', this.csrfToken);
        formData.append('action', button.dataset.action);
        formData.append('target_user_id', button.dataset.targetUserId);

        try {
            const data = await this.postForm(formData);
            if (!data.success) {
                throw new Error(data.message || 'Unable to update friendship');
            }

            const actionButtons = document.querySelectorAll(`.friend-toggle-btn[data-target-user-id="${button.dataset.targetUserId}"]`);
            actionButtons.forEach((item) => {
                const isUnfriendOnly = item.classList.contains('unfriend-only');
                item.disabled = data.friendship_status === 'request_sent';

                if (data.friendship_status === 'request_sent') {
                    if (isUnfriendOnly) {
                        item.style.display = 'none';
                        return;
                    }
                    item.innerHTML = '<i class="fas fa-paper-plane"></i> Request sent';
                } else if (data.friendship_status === 'not_friends') {
                    if (isUnfriendOnly) {
                        item.style.display = 'none';
                        return;
                    }
                    item.dataset.action = 'send_friend_request';
                    item.classList.remove('friend-status-btn');
                    item.innerHTML = '<i class="fas fa-user-plus"></i> Add friend';
                } else {
                    item.style.display = '';
                    item.dataset.action = 'remove_friend';
                    item.classList.add('friend-status-btn');
                    item.innerHTML = isUnfriendOnly
                        ? '<i class="fas fa-user-minus"></i> Unfriend'
                        : '<i class="fas fa-user-check"></i> Friends';
                }
            });

            if (isRemoval) {
                button.closest('.friend-card-managed')?.remove();
            }

            if (data.friendship_status === 'request_sent') {
                const suggestionCard = button.closest('[data-suggestion-card]');
                if (suggestionCard) {
                    suggestionCard.remove();
                }
            }

            this.showNotification(data.message, 'success');
        } catch (error) {
            this.showNotification(error.message, 'error');
        }
    }

    async respondToFriendRequest(button) {
        const formData = new FormData();
        formData.append('csrf_token', this.csrfToken);
        formData.append('action', 'respond_friend_request');
        formData.append('request_user_id', button.dataset.requestUserId);
        formData.append('decision', button.dataset.decision);

        try {
            const data = await this.postForm(formData);
            if (!data.success) {
                throw new Error(data.message || 'Unable to update request');
            }

            button.closest('[data-request-card]')?.remove();
            document.querySelectorAll(`.friend-response-btn[data-request-user-id="${button.dataset.requestUserId}"]`).forEach((item) => {
                item.disabled = true;
            });
            this.showNotification(data.message, 'success');
        } catch (error) {
            this.showNotification(error.message, 'error');
        }
    }

    async dismissSuggestion(button) {
        const userId = button.dataset.dismissUserId;
        const formData = new FormData();
        formData.append('csrf_token', this.csrfToken);
        formData.append('action', 'dismiss_suggestion');
        formData.append('target_user_id', userId);

        try {
            const data = await this.postForm(formData);
            if (!data.success) {
                throw new Error(data.message || 'Unable to dismiss');
            }
            const card = button.closest('[data-suggestion-card]');
            card?.remove();
            this.showNotification(data.message, 'success');
        } catch (error) {
            this.showNotification(error.message, 'error');
        }
    }

    setupTheme() {
        document.querySelectorAll('input[name="theme"]').forEach((radio) => {
            radio.addEventListener('change', () => {
                this.persistThemePreference(radio.value);
                this.applyThemePreference(radio.value);
            });
        });
    }

    persistThemePreference(theme) {
        localStorage.setItem('dreambd-theme', theme);
    }

    resolveTheme(theme) {
        if (theme === 'auto') {
            return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        return theme === 'dark' ? 'dark' : 'light';
    }

    syncThemeToggle(theme) {
        const toggle = document.getElementById('themeToggle');
        if (!toggle) {
            return;
        }

        const icons = toggle.querySelectorAll('i');
        const resolvedTheme = this.resolveTheme(theme);
        if (icons[0]) {
            icons[0].style.display = resolvedTheme === 'dark' ? 'none' : 'block';
        }
        if (icons[1]) {
            icons[1].style.display = resolvedTheme === 'dark' ? 'block' : 'none';
        }
    }

    applyThemePreference(theme) {
        const resolvedTheme = this.resolveTheme(theme);
        document.documentElement.setAttribute('data-theme', resolvedTheme);
        this.syncThemeToggle(theme);
        document.dispatchEvent(new CustomEvent('themeChange', {
            detail: {
                theme,
                resolvedTheme
            }
        }));
    }

    applySavedTheme() {
        const savedTheme = localStorage.getItem('dreambd-theme') || document.querySelector('input[name="theme"]:checked')?.value || 'light';
        const radio = document.querySelector(`input[name="theme"][value="${savedTheme}"]`) || document.querySelector('input[name="theme"][value="light"]');
        if (radio) {
            radio.checked = true;
        }
        this.applyThemePreference(savedTheme);
    }

    openModal(modalId) {
        document.getElementById(modalId)?.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    closeModal(modalId) {
        document.getElementById(modalId)?.classList.remove('active');
        document.body.style.overflow = '';
    }

    async postForm(formData) {
        const response = await fetch(this.handlerUrl, {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            throw new Error(`Request failed with status ${response.status}`);
        }

        return response.json();
    }

    renderPost(post) {
        const viewerId = window.__communityViewerId || this.viewerId;
        const isOwner = post.user_id && viewerId && parseInt(post.user_id) === parseInt(viewerId);
        const avatar = post.avatar || 'default.png';
        const name = this.escapeHtml(post.full_name || post.username);
        const content = post.content ? this.escapeHtml(post.content).replace(/\n/g, '<br>') : '';
        const imageHtml = post.image_path
            ? `<div class="community-post-image"><img src="assets/posts/${this.escapeHtml(post.image_path)}" alt="" loading="lazy"></div>`
            : '';
        const feelingHtml = post.feeling
            ? `<span style="font-size:12px;color:var(--comm-text-secondary);display:block;margin-top:2px">feeling <strong>${this.escapeHtml(post.feeling)}</strong></span>`
            : '';
        return `
            <article class="community-post-card" data-post-id="${post.id}">
                <div class="community-post-header">
                    <div class="community-post-author">
                        <img src="assets/avatars/${avatar}" alt="" onerror="this.src='assets/avatars/default.png'">
                        <div class="community-author-info">
                            <strong>${name}</strong>
                            <span class="community-post-time">Just now</span>
                            ${feelingHtml}
                        </div>
                    </div>
                    <div class="post-menu-container">
                        <button type="button" class="post-menu-trigger" title="More options"><i class="fas fa-ellipsis-h"></i></button>
                        <div class="post-dropdown">
                            ${isOwner ? `
                                <button class="post-dropdown-item" data-action="edit" data-post-id="${post.id}"><i class="fas fa-pen"></i> Edit post</button>
                                <button class="post-dropdown-item danger" data-action="delete" data-post-id="${post.id}"><i class="fas fa-trash-alt"></i> Delete post</button>
                            ` : `
                                <button class="post-dropdown-item" data-action="save" data-post-id="${post.id}"><i class="fas fa-bookmark"></i> Save post</button>
                                <div class="post-dropdown-divider"></div>
                                <button class="post-dropdown-item danger" data-action="report" data-post-id="${post.id}"><i class="fas fa-flag"></i> Report post</button>
                            `}
                        </div>
                    </div>
                </div>
                ${content ? `<div class="community-post-content"><p>${content}</p></div>` : ''}
                ${imageHtml}
                <div class="community-post-stats">
                    <div class="community-stat-left" title="View reactions">
                        <div class="community-reaction-icons">
                            <span class="rxn-like"><i class="fas fa-thumbs-up"></i></span>
                            <span class="rxn-love"><i class="fas fa-heart"></i></span>
                        </div>
                        <span class="community-stat-count">${post.like_count || 0}</span>
                    </div>
                    <div class="community-stat-right">
                        <span class="community-comment-trigger" data-post-id="${post.id}">${post.comment_count || 0} comments</span> •
                        <span>${post.share_count || 0} shares</span>
                    </div>
                </div>
                <div class="community-post-actions">
                    <div class="community-btn-action-wrap">
                        <button class="community-btn-action" data-action="like" data-reaction="">
                            <i class="far fa-thumbs-up"></i> Like
                        </button>
                        <div class="community-reaction-strip">
                            <button class="community-reaction-btn" data-reaction="like" title="Like">👍</button>
                            <button class="community-reaction-btn" data-reaction="love" title="Love">❤️</button>
                            <button class="community-reaction-btn" data-reaction="care" title="Care">🥰</button>
                            <button class="community-reaction-btn" data-reaction="haha" title="Haha">😆</button>
                            <button class="community-reaction-btn" data-reaction="wow" title="Wow">😮</button>
                            <button class="community-reaction-btn" data-reaction="sad" title="Sad">😢</button>
                            <button class="community-reaction-btn" data-reaction="angry" title="Angry">😡</button>
                        </div>
                    </div>
                    <div class="community-btn-action-wrap">
                        <button class="community-btn-action community-comment-trigger" data-action="comment" data-post-id="${post.id}"><i class="far fa-comment"></i> Comment</button>
                    </div>
                    <div class="community-btn-action-wrap">
                        <button class="community-btn-action" data-action="share"><i class="far fa-share-square"></i> Share</button>
                    </div>
                </div>
            </article>
        `;
    }

    renderComment(comment) {
        return `
            <div class="comment-item">
                <img src="assets/avatars/${comment.avatar || 'default.png'}" alt="" class="comment-avatar">
                <div class="comment-bubble">
                    <strong>${this.escapeHtml(comment.full_name || comment.username)}</strong>
                    <p>${this.formatMultiline(comment.comment_text || '')}</p>
                    <span>Just now</span>
                </div>
            </div>
        `;
    }

    formatMultiline(text) {
        return this.escapeHtml(text).replace(/\n/g, '<br>');
    }

    capitalize(text) {
        return text ? text.charAt(0).toUpperCase() + text.slice(1) : '';
    }

    escapeHtml(text) {
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, (m) => map[m]);
    }

    animateCounters() {
        document.querySelectorAll('.stat-counter').forEach((el) => {
            const target = parseInt(el.dataset.count) || 0;
            const duration = 1000;
            const steps = 25;
            const increment = target / steps;
            let current = 0;
            const update = () => {
                current += increment;
                if (current >= target) { el.textContent = target.toLocaleString(); return; }
                el.textContent = Math.floor(current).toLocaleString();
                requestAnimationFrame(update);
            };
            update();
        });
    }

    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            <div class="notification-content">
                <div class="notification-title">${this.capitalize(type)}</div>
                <div class="notification-message">${this.escapeHtml(message)}</div>
            </div>
            <button class="notification-close" type="button"><i class="fas fa-times"></i></button>
        `;

        notification.querySelector('.notification-close').addEventListener('click', () => notification.remove());
        document.body.appendChild(notification);
        requestAnimationFrame(() => notification.classList.add('show'));

        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 4000);
    }
}

window.ProfileManager = ProfileManager;
let profileInitAttempts = 0;

function initProfileManager() {
    if (!document.querySelector('[data-profile-page]')) {
        if (window.profileManager) {
            window.profileManager.destroy();
            window.profileManager = null;
        }
        return;
    }
    
    // Clean up existing instance before creating a new one
    if (window.profileManager) {
        window.profileManager.destroy();
    }
    window.profileManager = new ProfileManager();
}

// Multiple init attempts to handle AJAX timing
function tryInitProfile() {
    if (profileInitAttempts > 10) return;
    profileInitAttempts++;
    initProfileManager();
    if (!window.profileManager && document.querySelector('[data-profile-page]')) {
        setTimeout(tryInitProfile, 200);
    }
}

// Init strategies
if (document.readyState === 'complete' || document.readyState === 'interactive') {
    setTimeout(tryInitProfile, 100);
}
document.addEventListener('DOMContentLoaded', tryInitProfile);
document.addEventListener('pageChanged', (e) => {
    if (e.detail?.page === 'profile') {
        profileInitAttempts = 0;
        setTimeout(tryInitProfile, 150);
    }
});
document.addEventListener('pageContentLoaded', (e) => {
    if (e.detail?.page === 'profile') {
        profileInitAttempts = 0;
        setTimeout(tryInitProfile, 100);
    }
});
(function() {
    const p = new URLSearchParams(window.location.search);
    if (p.get('page') === 'profile') setTimeout(tryInitProfile, 300);
})();

function openSettings() {
    window.profileManager?.openSiteDialog('profileSettingsDialog');
    window.profileManager?.switchSettingsTab('profile');
}

function updateCoverPhoto() {
    window.profileManager?.openSiteDialog('coverUploadDialog');
}
