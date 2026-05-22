if (typeof window.SocialPages === 'undefined') {
    window.SocialPages = class SocialPages {
        constructor() {
            this.handlerUrl = 'handlers/profile_handlers.php';
            this.replyTarget = null;
            this.friendCache = [];
            this.selectMode = false;
            this.selectedIds = new Set();
            this.forwardMsgIds = [];
            this.forwards = {};
            
            // Clean up existing intervals if any
            if (window.__messengerRelativeTimeInterval) clearInterval(window.__messengerRelativeTimeInterval);
            if (window.__messengerReadPollInterval) clearInterval(window.__messengerReadPollInterval);
            if (window.__messengerHeartbeatInterval) clearInterval(window.__messengerHeartbeatInterval);
            if (window.__messengerThreadPollInterval) clearInterval(window.__messengerThreadPollInterval);
            
            // Reference current instance globally so setupGlobalDelegation
            // always uses the latest SocialPages, not the one that first bound it
            window.__currentSocialPages = this;
            
            this.bindMessages();
            this.bindNotifications();
            this.setupGlobalDelegation();
            this.startGlobalHeartbeat();
        }

    async pollThreadList() {
        if (!this.root || this.activeTab !== 'inbox') return;
        try {
            const fd = new FormData();
            fd.append('action', 'get_recent_threads');
            fd.append('csrf_token', document.body.dataset.csrfToken);
            const d = await this.post(fd);
            if (!d.success || !d.threads) return;

            const activeId = this.root.dataset.activeUserId;
            const list = document.getElementById('sidebarList');
            if (!list) return;

            // Generate HTML for threads
            let html = '';
            d.threads.forEach(t => {
                const isActive = Number(t.other_user_id) === Number(activeId);
                const unread = Number(t.unread_count) > 0;
                const online = Number(t.is_online) === 1;
                const pinned = Number(t.is_pinned) === 1;
                
                html += `<a href="index.php?page=messages&user=${t.other_user_id}" 
                            class="list-item ${isActive ? 'active' : ''} ${pinned ? 'pinned' : ''}" 
                            data-no-ajax data-thread-user-id="${t.other_user_id}" ${pinned ? 'data-is-pinned="1"' : ''}>
                    <div class="list-item-avatar">
                        <img src="assets/avatars/${this.escape(t.avatar || 'default.png')}" alt="" onerror="this.src='assets/avatars/default.png'">
                        ${online ? '<span class="online-dot"></span>' : ''}
                    </div>
                    <div class="list-item-info">
                        <div class="list-item-top">
                            <strong>${this.escape(t.full_name || t.username)}</strong>
                            ${pinned ? '<span class="pin-badge"><i class="fas fa-thumbtack"></i> Pinned</span>' : ''}
                            <span class="list-item-time js-relative-time" data-time="${t.last_message_at}">${this.formatRelativeTime(t.last_message_at)}</span>
                        </div>
                        <div class="list-item-bottom">
                            <span class="list-item-preview ${unread ? 'unread' : ''}">${this.escape(t.last_message || 'Start a conversation')}</span>
                            ${unread ? `<span class="list-item-badge">${t.unread_count}</span>` : ''}
                        </div>
                    </div>
                    <div class="list-item-menu-wrap">
                        <button class="list-item-menu-btn" title="Options"><i class="fas fa-ellipsis-v"></i></button>
                        <div class="list-item-dropdown">
                            <button class="dropdown-item" data-action="pin" data-thread-id="${t.other_user_id}">
                                <i class="fas fa-thumbtack"></i> <span class="pin-text">${pinned ? 'Unpin' : 'Pin'}</span>
                            </button>
                            <button class="dropdown-item" data-action="delete" data-thread-id="${t.other_user_id}">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                </a>`;
            });
            
            if (html) {
                list.innerHTML = html;
                this.threadListHTML = html;
            }
        } catch (e) {}
    }

    startGlobalHeartbeat() {
        if (window.__messengerHeartbeatInterval) clearInterval(window.__messengerHeartbeatInterval);
        window.__messengerHeartbeatInterval = window.setInterval(async () => {
            const csrfToken = document.body.dataset.csrfToken;
            if (!csrfToken) return;
            try {
                const fd = new FormData();
                fd.append('action', 'heartbeat');
                fd.append('csrf_token', csrfToken);
                const d = await this.post(fd);
                if (d.success && d.counts) this.updateHeaderCounts(d.counts);
            } catch (e) {}
        }, 30000);
    }

    bindMessages() {
        const root = document.querySelector('[data-messages-page]');
        if (!root) return;
        this.root = root;

        // Handle Mobile Responsiveness
        const activeUserId = root.dataset.activeUserId;
        const container = root.closest('.messenger-app');
        
        // If there's an active user and the URL actually has 'user=' parameter, show chat
        const urlParams = new URLSearchParams(window.location.search);
        const hasUserParam = urlParams.has('user');
        
        if (window.innerWidth <= 900) {
            if (activeUserId && hasUserParam) {
                container?.classList.add('show-chat');
            } else {
                container?.classList.remove('show-chat');
            }
        }

        // Reset state
        this.isLoadingOlderMessages = false;
        this.selectMode = false;
        if (this.selectedIds) this.selectedIds.clear();
        
        this.initSidebar();
        this.initActiveNow();
        this.initThemePicker(); 
        this.initModals();
        this.refreshRelativeTimes(root);
        
        window.__messengerRelativeTimeInterval = window.setInterval(() => this.refreshRelativeTimes(this.root), 30000);
        
        // Poll for thread list updates (sidebar)
        if (window.__messengerThreadPollInterval) clearInterval(window.__messengerThreadPollInterval);
        window.__messengerThreadPollInterval = window.setInterval(() => this.pollThreadList(), 15000);

        const form = document.getElementById('messageForm');
        if (form) {
            this.form = form;
            this.stream = document.getElementById('messagesStream');
            this.replyInput = document.getElementById('replyToMessageId');
            this.replyBox = document.getElementById('messageReplyingBox');
            this.imageInput = document.getElementById('messageImageInput');
            this.imagePreview = document.getElementById('messageImagePreview');
            this.viewerId = Number(root.dataset.viewerId || 0);

            if (activeUserId) this.markThreadRead(activeUserId, root.dataset.csrfToken || document.body.dataset.csrfToken);

            this.imageInput?.addEventListener('change', () => this.updateImagePreview(this.imageInput, this.imagePreview));
            this.stream?.addEventListener('scroll', () => this.handleMessagesScroll());
            this.scrollToBottom();
            this.startReadStatusPolling();
            this.initConvSearch();

            const textarea = form.querySelector('textarea[name="body"]');
            const sendBtn = form.querySelector('.compose-send');
            if (textarea) {
                textarea.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendBtn?.click(); }
                    if (e.key === 'Escape') { if (this.replyTarget) this.clearReplyTarget(); }
                });
                textarea.addEventListener('input', () => {
                    textarea.style.height = '36px';
                    textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
                    if (sendBtn) sendBtn.disabled = !textarea.value.trim() && (!this.imageInput?.files?.length);
                });
            }

            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                const submit = form.querySelector('[type="submit"]');
                if (submit.disabled) return;
                submit.disabled = true;
                const orig = submit.innerHTML;
                try {
                    const formData = new FormData(form);
                    formData.append('action', 'send_message');
                    const data = await this.post(formData);
                    if (!data.success) throw new Error(data.message || 'Unable to send');
                    if (this.replyTarget) {
                        data.message_item.reply_full_name = this.replyTarget.author;
                        data.message_item.reply_username = this.replyTarget.author;
                        data.message_item.reply_body = this.replyTarget.preview;
                    }
                    this.appendMessage(data.message_item, true);
                    this.updateRecentThreadPreview(data.message_item);
                    form.reset();
                    this.clearReplyTarget();
                    if (this.imagePreview) { this.imagePreview.innerHTML = ''; this.imagePreview.style.display = 'none'; }
                    if (textarea) textarea.style.height = '36px';
                    if (sendBtn) sendBtn.disabled = true;
                    this.updateHeaderCounts(data.counts);
                    this.initActiveNow(); // Refresh online status and friends list immediately
                } catch (err) { this.toast(err.message, 'error'); }
                finally { submit.innerHTML = orig; submit.disabled = false; }
            });
        }
    }


    setupGlobalDelegation() {
        if (window.__messengerGlobalDelegationBound) return;
        window.__messengerGlobalDelegationBound = true;

        document.addEventListener('click', (event) => {
            const sp = window.__currentSocialPages;
            if (!sp) return;
            const target = event.target;
            const root = document.querySelector('[data-messages-page]');
            if (!root) return;

            // 1. Sidebar Tabs
            const tabBtn = target.closest('.sidebar-tab');
            if (tabBtn) { sp.switchTab(tabBtn.dataset.tab); return; }

            // 2. Message Actions
            const delBtn = target.closest('.message-delete-btn');
            const replyBtn = target.closest('.message-reply-btn');
            const pinBtn = target.closest('.message-pin-btn');
            const editBtn = target.closest('.message-edit-btn');
            const fwdBtn = target.closest('.message-forward-btn');
            const cancelReply = target.closest('.reply-preview-cancel');
            const reactionBtn = target.closest('.msg-reaction-btn');

            if (cancelReply) { event.preventDefault(); sp.clearReplyTarget(); return; }
            if (editBtn) { event.preventDefault(); sp.openEditMessageDialog(editBtn, root.dataset.csrfToken); return; }
            if (replyBtn) { event.preventDefault(); sp.setReplyTarget(replyBtn); return; }
            if (delBtn) { event.preventDefault(); sp.deleteMessage(delBtn, root.dataset.csrfToken); return; }
            if (pinBtn) { event.preventDefault(); sp.togglePinMessage(pinBtn, root.dataset.csrfToken); return; }
            if (fwdBtn) { event.preventDefault(); sp.openForwardModal([fwdBtn.dataset.messageId]); return; }
            if (reactionBtn) {
                event.preventDefault(); event.stopPropagation();
                sp.handleReact(reactionBtn.dataset.messageId, reactionBtn.dataset.reaction, root.dataset.csrfToken);
                return;
            }

            // 3. Modals Close (Handles relocated modals in modalRoot)
            const closePinned = target.closest('[data-close-pinned-modal]');
            if (closePinned) { 
                event.preventDefault(); event.stopPropagation();
                sp.closePinnedMessages(); 
                return; 
            }
            const closeForward = target.closest('[data-close-forward-modal]');
            if (closeForward) { 
                event.preventDefault(); event.stopPropagation();
                sp.closeForwardModal(); 
                return; 
            }
            const openPinned = target.closest('#openPinnedMessagesBtn');
            if (openPinned) { event.preventDefault(); sp.openPinnedMessages(); return; }

            // 4. Thread List Menu (3-dot)
            const menuBtn = target.closest('.list-item-menu-btn');
            if (menuBtn) {
                event.preventDefault(); event.stopPropagation();
                const wrap = menuBtn.closest('.list-item-menu-wrap');
                
                // Close others
                document.querySelectorAll('.list-item-menu-wrap.active').forEach(a => { 
                    if (a !== wrap) a.classList.remove('active'); 
                });
                
                const dd = wrap?.querySelector('.list-item-dropdown');
                if (dd) {
                    const rect = menuBtn.getBoundingClientRect();
                    const ddWidth = 160;
                    let left = rect.right - ddWidth;
                    const maxLeft = window.innerWidth - ddWidth - 10;
                    if (left > maxLeft) left = maxLeft;
                    if (left < 10) left = 10;
                    
                    dd.style.top = (rect.bottom + 8) + 'px';
                    dd.style.left = left + 'px';
                }
                wrap?.classList.toggle('active');
                return;
            }

            const dropItem = target.closest('.dropdown-item');
            if (dropItem) {
                event.preventDefault(); event.stopPropagation();
                const action = dropItem.dataset.action;
                const tid = dropItem.dataset.threadId;
                dropItem.closest('.list-item-menu-wrap')?.classList.remove('active');
                if (action === 'delete') {
                    sp.showConfirmDialog('Delete conversation?', 'This will remove the entire conversation. Are you sure?', () => {
                        sp.handleDeleteConversation(tid, root.dataset.csrfToken);
                    });
                } else if (action === 'pin') {
                    sp.handlePinConversation(tid, dropItem, root.dataset.csrfToken);
                }
                return;
            }

            // 5. Selection Mode
            const selectCheck = target.closest('.select-checkbox');
            if (selectCheck) {
                event.preventDefault();
                const row = selectCheck.closest('.msg-row');
                if (row?.dataset.messageId) sp.initSelectMode(row.dataset.messageId);
                return;
            }
            const selectBarBtn = target.closest('.select-bar-btn');
            if (selectBarBtn) {
                event.preventDefault();
                const id = selectBarBtn.id;
                if (id === 'selectCancelBtn') sp.exitSelectMode();
                else if (id === 'selectDeleteBtn') sp.deleteSelected(root.dataset.csrfToken);
                else if (id === 'selectForwardBtn') sp.openForwardModal([...sp.selectedIds]);
                return;
            }

            // 6. Theme Picker & Conv Search Toggles (if missed by direct listeners)
            const themeToggle = target.closest('#themePickerToggle');
            if (themeToggle) {
                event.preventDefault(); event.stopPropagation();
                const dropdown = document.getElementById('themePickerDropdown');
                dropdown?.classList.toggle('visible');
                return;
            }
            const convSearchToggle = target.closest('#convSearchToggle');
            if (convSearchToggle) {
                event.preventDefault(); event.stopPropagation();
                const bar = document.getElementById('convSearchBar');
                bar?.classList.toggle('visible');
                if (bar?.classList.contains('visible')) document.getElementById('convSearchInput')?.focus();
                return;
            }

            // Cleanup: Close menus if clicking outside
            if (!target.closest('.list-item-menu-wrap, .list-item-dropdown')) {
                document.querySelectorAll('.list-item-menu-wrap.active').forEach(a => a.classList.remove('active'));
            }
            if (!target.closest('#themePickerToggle, #themePickerDropdown')) {
                document.getElementById('themePickerDropdown')?.classList.remove('visible');
            }
        });

        document.addEventListener('scroll', () => {
            document.querySelectorAll('.list-item-menu-wrap.active').forEach(a => a.classList.remove('active'));
        }, { passive: true, capture: true });

        document.addEventListener('contextmenu', (event) => {
            const sp = window.__currentSocialPages;
            if (!sp) return;
            const menuBtn = event.target.closest('.list-item-menu-btn');
            if (menuBtn) {
                event.preventDefault();
                const wrap = menuBtn.closest('.list-item-menu-wrap');
                document.querySelectorAll('.list-item-menu-wrap.active').forEach(a => { if (a !== wrap) a.classList.remove('active'); });
                wrap?.classList.toggle('active');
                return;
            }
            const msgRow = event.target.closest('.msg-row');
            if (msgRow) {
                event.preventDefault();
                document.querySelectorAll('.msg-row.show-reactions').forEach(r => { if (r !== msgRow) r.classList.remove('show-reactions'); });
                msgRow.classList.toggle('show-reactions');
            }
        });
    }

    initModals() {
        this.pinnedModal = document.getElementById('pinnedMessagesModal');
        this.pinnedBody = document.getElementById('pinnedMessagesBody');
        this.forwardModal = document.getElementById('forwardModal');
        this.forwardList = document.getElementById('forwardList');
    }

    async handleDeleteConversation(threadId, csrfToken) {
        try {
            const fd = new FormData();
            fd.append('action', 'delete_conversation');
            fd.append('other_user_id', threadId);
            fd.append('csrf_token', csrfToken);
            const data = await this.post(fd);
            if (data.success) {
                const item = document.querySelector(`.list-item[data-thread-user-id="${threadId}"]`);
                if (item) item.remove();
                this.toast('Conversation deleted', 'success');
                if (Number(this.root?.dataset.activeUserId) === Number(threadId)) {
                    window.location.href = 'index.php?page=messages';
                }
            } else {
                const item = document.querySelector(`.list-item[data-thread-user-id="${threadId}"]`);
                if (item) item.remove();
                this.toast('Conversation removed', 'success');
            }
        } catch (e) {
            this.toast('Conversation removed', 'success');
            const item = document.querySelector(`.list-item[data-thread-user-id="${threadId}"]`);
            if (item) item.remove();
        }
    }

    initActiveNow() {
        try {
            const friendData = this.root?.dataset.friends;
            if (friendData) this.friendCache = JSON.parse(friendData) || [];
        } catch(e) {}
        this.populateActiveNow();
        this.loadFriends().then(() => {
            this.populateActiveNow();
            if (this.activeTab !== 'inbox') this.renderFriendsTab(this.activeTab);
        });
    }

    /* ============ SIDEBAR ============ */
    initSidebar() {
        this.searchInput = document.getElementById('messengerSearch');
        this.searchResults = document.getElementById('searchResults');
        this.searchClear = document.getElementById('searchClear');
        this.sidebarList = document.getElementById('sidebarList');
        this.threadListHTML = this.sidebarList?.innerHTML || '';
        this.activeTab = 'inbox';
        this.searchTimer = null;

        if (this.searchInput) {
            this.searchInput.oninput = () => {
                clearTimeout(this.searchTimer);
                const val = this.searchInput.value.trim();
                this.searchClear?.classList.toggle('visible', val.length > 0);
                if (val.length < 1) { this.searchResults?.classList.remove('visible'); return; }
                this.searchTimer = setTimeout(() => this.handleSearch(val), 250);
            };
            this.searchInput.onfocus = () => {
                if (this.searchInput.value.trim()) this.searchResults?.classList.add('visible');
            };
        }
        if (this.searchClear) {
            this.searchClear.onclick = () => {
                if (this.searchInput) { this.searchInput.value = ''; this.searchInput.focus(); }
                this.searchClear.classList.remove('visible');
                this.searchResults?.classList.remove('visible');
            };
        }
    }

    switchTab(tab) {
        this.activeTab = tab;
        document.querySelectorAll('.sidebar-tab').forEach(t => t.classList.toggle('active', t.dataset.tab === tab));
        this.populateTab(tab);
    }

    syncThreadList() {
        const list = document.getElementById('sidebarList');
        if (list) this.threadListHTML = list.innerHTML;
    }

    populateTab(tab) {
        const list = document.getElementById('sidebarList');
        if (!list) return;
        if (tab === 'inbox') {
            list.innerHTML = this.threadListHTML;
            const activeId = this.root?.dataset.activeUserId;
            if (activeId) {
                list.querySelectorAll('.list-item').forEach(el => {
                    el.classList.toggle('active', el.dataset.threadUserId === activeId);
                });
            }
            return;
        }
        if (tab === 'friends' || tab === 'active') {
            if (this.friendCache.length === 0) { this.loadFriends().then(() => this.renderFriendsTab(tab)); return; }
            this.renderFriendsTab(tab);
        }
    }

    renderFriendsTab(tab) {
        const listEl = document.getElementById('sidebarList');
        if (!listEl) return;
        const list = tab === 'active'
            ? this.friendCache.filter(f => f.is_online)
            : this.friendCache;
        if (list.length === 0) {
            listEl.innerHTML = `<div class="list-empty">
                <i class="fas fa-user-slash"></i>
                <p>${tab === 'active' ? 'No friends online' : 'No friends yet'}</p>
            </div>`;
            return;
        }
        listEl.innerHTML = list.map(f => this.renderFriendItem(f)).join('');
    }

    renderFriendItem(f) {
        const name = this.escape(f.full_name || f.username || 'User');
        const avatar = this.escape(f.avatar || 'default.png');
        const isOnline = f.is_online;
        const status = isOnline ? 'Active' : 'Offline';
        const statusCls = isOnline ? ' online' : '';
        return `<a href="index.php?page=messages&user=${f.id}" data-page="messages" class="list-item">
            <div class="list-item-avatar">
                <img src="assets/avatars/${avatar}" alt="" onerror="this.src='assets/avatars/default.png'" loading="lazy">
                ${isOnline ? '<span class="online-dot"></span>' : ''}
            </div>
            <div class="list-item-info">
                <div class="list-item-top">
                    <strong>${name}</strong>
                    <span class="list-item-status${statusCls}">${status}</span>
                </div>
                <div class="list-item-bottom">
                    <span class="list-item-preview" style="opacity:0.5;font-size:0.7rem">${status === 'Active' ? 'Tap to chat' : 'Last seen recently'}</span>
                </div>
            </div>
        </a>`;
    }

    /* ============ SEARCH ============ */
    async handleSearch(query) {
        const results = document.getElementById('searchResults');
        if (!results) return;
        try {
            const formData = new FormData();
            formData.append('action', 'search_users');
            formData.append('query', query);
            formData.append('csrf_token', this.root.dataset.csrfToken);
            const data = await this.post(formData);
            if (!data.success || !data.users?.length) {
                results.innerHTML = '<div class="search-result-empty">No results found</div>';
                results.classList.add('visible');
                return;
            }
            results.innerHTML = data.users.map(u => {
                const name = this.escape(u.full_name || u.username);
                const avatar = this.escape(u.avatar || 'default.png');
                const friendLabel = u.is_friend ? '<span style="opacity:0.5">Friend</span>' : '<span style="opacity:0.4">Not friend</span>';
                const onlineDot = u.is_online ? '<span style="width:8px;height:8px;border-radius:50%;background:var(--msg-online);flex-shrink:0;border:1.5px solid var(--msg-surface)"></span>' : '';
                return `<a href="index.php?page=messages&user=${u.id}" data-page="messages" class="search-result-item">
                    <img src="assets/avatars/${avatar}" alt="" onerror="this.src='assets/avatars/default.png'">
                    <div class="result-info"><strong>${name}</strong><span>${friendLabel}</span></div>
                    ${onlineDot}
                </a>`;
            }).join('');
            results.classList.add('visible');
        } catch (e) {}
    }

    async loadFriends() {
        try {
            const formData = new FormData();
            formData.append('action', 'get_friends');
            formData.append('csrf_token', this.root.dataset.csrfToken);
            const data = await this.post(formData);
            if (data.success) this.friendCache = data.friends || [];
        } catch (e) {}
    }

    populateActiveNow() {
        const section = document.getElementById('activeNowSection');
        const list = document.getElementById('activeNowList');
        if (!section || !list) return;
        const active = this.friendCache.filter(f => f.is_online);
        if (active.length === 0) { section.classList.remove('has-active'); return; }
        section.classList.add('has-active');
        list.innerHTML = active.slice(0, 8).map(f => {
            const name = this.escape(f.full_name || f.username || '?');
            const av = this.escape(f.avatar || 'default.png');
            return `<a href="index.php?page=messages&user=${f.id}" data-page="messages" class="active-now-item">
                <div class="avatar-wrap">
                    <img src="assets/avatars/${av}" alt="" onerror="this.src='assets/avatars/default.png'">
                    <span class="online-ring"></span>
                </div>
                <span>${name.split(' ')[0]}</span>
            </a>`;
        }).join('');
    }

    /* ============ THEME ============ */
    initThemePicker() {
        const dropdown = document.getElementById('themePickerDropdown');
        if (!dropdown) return;

        dropdown.querySelectorAll('.theme-mode-btn').forEach(btn => {
            btn.onclick = (e) => {
                e.stopPropagation();
                dropdown.querySelectorAll('.theme-mode-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                const mode = btn.dataset.mode;
                document.documentElement.classList.toggle('dark', mode === 'dark');
                document.cookie = `dreambd-theme=${mode}; path=/; max-age=31536000`;
            };
        });

        dropdown.querySelectorAll('.theme-color-btn').forEach(btn => {
            btn.onclick = (e) => {
                e.stopPropagation();
                dropdown.querySelectorAll('.theme-color-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                const app = this.root || document.querySelector('[data-messages-page]');
                if (app) {
                    app.classList.remove('msg-theme-purple', 'msg-theme-green', 'msg-theme-orange', 'msg-theme-pink');
                    const color = btn.dataset.color;
                    if (color !== 'blue') app.classList.add('msg-theme-' + color);
                    try { localStorage.setItem('dreambd-msg-accent', color); } catch(err) {}
                }
            };
        });

        try {
            const saved = localStorage.getItem('dreambd-msg-accent');
            if (saved && saved !== 'blue') {
                const app = this.root || document.querySelector('[data-messages-page]');
                app?.classList.add('msg-theme-' + saved);
                dropdown.querySelector(`.theme-color-btn[data-color="${saved}"]`)?.classList.add('active');
                dropdown.querySelector('.theme-color-blue')?.classList.remove('active');
            }
        } catch(e) {}
    }

    /* ============ CONVERSATION SEARCH ============ */
    initConvSearch() {
        const input = document.getElementById('convSearchInput');
        const clear = document.getElementById('convSearchClear');
        if (!input) return;

        clear?.addEventListener('click', () => { input.value = ''; this.clearConvSearch(); input.focus(); });
        input.addEventListener('input', () => this.filterConvMessages(input.value));
    }

    clearConvSearch() {
        this.stream?.querySelectorAll('.msg-row').forEach(el => el.style.display = '');
        const res = document.getElementById('convSearchResults');
        if (res) res.textContent = '';
    }

    filterConvMessages(query) {
        const q = query.trim().toLowerCase();
        const res = document.getElementById('convSearchResults');
        if (!this.stream || !res) return;
        if (!q) { this.clearConvSearch(); return; }
        let count = 0;
        this.stream.querySelectorAll('.msg-row').forEach(el => {
            const text = el.querySelector('.msg-bubble p')?.textContent?.toLowerCase() || '';
            const match = text.includes(q);
            el.style.display = match ? '' : 'none';
            if (match) count++;
        });
        res.textContent = count + ' result' + (count !== 1 ? 's' : '');
    }

    /* ============ SELECT MODE ============ */
    initSelectMode(msgId) {
        if (!this.selectMode) {
            this.selectMode = true;
            this.selectedIds.clear();
            this.stream?.querySelectorAll('.msg-row').forEach(el => el.classList.add('selectable'));
            this.root?.classList.add('select-mode');
            document.getElementById('selectBar')?.classList.add('visible');
        }
        const row = document.querySelector(`[data-message-id="${msgId}"]`);
        if (!row) return;
        if (this.selectedIds.has(msgId)) {
            this.selectedIds.delete(msgId);
            row.classList.remove('selected');
        } else {
            this.selectedIds.add(msgId);
            row.classList.add('selected');
        }
        this.updateSelectUI();
    }

    updateSelectUI() {
        const bar = document.getElementById('selectBar');
        const count = document.getElementById('selectCount');
        if (!bar || !count) return;
        count.textContent = this.selectedIds.size + ' selected';
        if (this.selectedIds.size === 0) this.exitSelectMode();
    }

    exitSelectMode() {
        this.selectMode = false;
        this.selectedIds.clear();
        this.root?.classList.remove('select-mode');
        document.getElementById('selectBar')?.classList.remove('visible');
        this.stream?.querySelectorAll('.msg-row').forEach(el => el.classList.remove('selectable', 'selected'));
    }

    /* ============ FORWARD ============ */
    openForwardModal(msgIds) {
        const modal = document.getElementById('forwardModal');
        const list = document.getElementById('forwardList');
        if (!modal || !list) return;
        this.forwardMsgIds = msgIds;
        modal.classList.remove('hidden');
        list.innerHTML = '';
        this.renderForwardList(this.friendCache);

        const searchInput = document.getElementById('forwardSearchInput');
        if (searchInput) {
            searchInput.value = '';
            searchInput.focus();
            searchInput.oninput = () => {
                const q = searchInput.value.toLowerCase();
                const filtered = this.friendCache.filter(f => (f.full_name || f.username || '').toLowerCase().includes(q));
                this.renderForwardList(filtered);
            };
        }
    }

    renderForwardList(friends) {
        const list = document.getElementById('forwardList');
        if (!list) return;
        if (!friends.length) { list.innerHTML = '<div style="padding:20px;text-align:center;color:var(--msg-text-secondary);font-size:0.82rem">No friends found</div>'; return; }
        list.innerHTML = friends.map(f => `<button class="forward-friend-item" data-forward-id="${f.id}"><img src="assets/avatars/${this.escape(f.avatar || 'default.png')}" alt="" onerror="this.src='assets/avatars/default.png'"><span>${this.escape(f.full_name || f.username || 'User')}</span></button>`).join('');
        list.querySelectorAll('.forward-friend-item').forEach(btn => {
            btn.onclick = () => this.handleForward(btn.dataset.forwardId);
        });
    }

    async handleForward(receiverId) {
        if (!this.forwardMsgIds.length) return;
        this.toast('Forwarding...', 'success');
        for (const msgId of this.forwardMsgIds) {
            try {
                const fd = new FormData();
                fd.append('action', 'forward_message');
                fd.append('message_id', msgId);
                fd.append('receiver_id', receiverId);
                fd.append('csrf_token', this.root.dataset.csrfToken);
                await this.post(fd);
            } catch (e) {}
        }
        this.closeForwardModal();
        this.exitSelectMode();
        this.toast('Message forwarded', 'success');
    }

    closeForwardModal() { document.getElementById('forwardModal')?.classList.add('hidden'); this.forwardMsgIds = []; }

    /* ============ MESSAGES ============ */
    markThreadRead(otherUserId, csrfToken) {
        const fd = new FormData();
        fd.append('action', 'mark_thread_read');
        fd.append('other_user_id', otherUserId);
        fd.append('csrf_token', csrfToken);
        this.post(fd).then(d => { if (d.success) this.updateHeaderCounts(d.counts); }).catch(() => {});
    }

    scrollToBottom() { if (this.stream) this.stream.scrollTop = this.stream.scrollHeight; }

    startReadStatusPolling() {
        if (window.__messengerReadPollInterval) clearInterval(window.__messengerReadPollInterval);
        window.__messengerReadPollInterval = window.setInterval(() => this.pollReadStatus(), 8000);
    }

    async pollReadStatus() {
        if (!this.stream?.dataset.otherUserId || !this.root) return;
        try {
            const lastMsg = this.stream.querySelector('.msg-row:last-child');
            const afterId = lastMsg?.dataset.messageId || 0;
            
            const fd = new FormData();
            fd.append('action', 'load_more_messages');
            fd.append('other_user_id', this.stream.dataset.otherUserId);
            if (afterId > 0) fd.append('after_message_id', afterId);
            fd.append('csrf_token', this.root.dataset.csrfToken);
            
            const d = await this.post(fd);
            if (!d.success || !d.messages) return;
            
            const map = {};
            d.messages.forEach(m => {
                map[m.id] = m.is_read;
                if (afterId > 0 && Number(m.id) > Number(afterId)) {
                    this.appendMessage(m, Number(m.sender_id) === this.viewerId);
                    // Update thread preview in sidebar
                    this.updateRecentThreadPreview(m);
                    if (Number(m.sender_id) !== this.viewerId) {
                        this.markThreadRead(this.stream.dataset.otherUserId, this.root.dataset.csrfToken);
                    }
                }
            });
            
            this.stream.querySelectorAll('.msg-row.mine').forEach(el => {
                const id = el.dataset.messageId;
                if (map[id] !== undefined) {
                    const icon = el.querySelector('.msg-sent, .msg-read');
                    if (icon) icon.className = map[id] ? 'fas fa-check-double msg-read' : 'fas fa-check msg-sent';
                }
            });
            
            if (d.counts) this.updateHeaderCounts(d.counts);
        } catch (e) {}
    }

    async handleMessagesScroll() {
        if (!this.stream || this.isLoadingOlderMessages || this.stream.dataset.hasMore !== '1') return;
        if (this.stream.scrollTop > 48) return;
        const firstMsg = this.stream.querySelector('[data-message-id]');
        const beforeId = firstMsg?.dataset.messageId;
        if (!beforeId) return;
        this.isLoadingOlderMessages = true;
        const prevHeight = this.stream.scrollHeight;
        const indicator = document.getElementById('messagesLoadIndicator');
        if (indicator) indicator.hidden = false;
        try {
            const fd = new FormData();
            fd.append('action', 'load_more_messages');
            fd.append('other_user_id', this.stream.dataset.otherUserId);
            fd.append('before_message_id', beforeId);
            fd.append('csrf_token', this.root.dataset.csrfToken);
            const d = await this.post(fd);
            if (!d.success) throw new Error(d.message || 'Unable to load');
            const items = (d.messages || []).map(msg => {
                const mine = Number(msg.sender_id) === this.viewerId;
                return `<div class="msg-row ${mine ? 'mine' : 'theirs'}" data-message-id="${msg.id}">${this.renderBubbleRow(msg, mine)}</div>`;
            }).join('');
            if (items) {
                indicator?.insertAdjacentHTML('afterend', items);
                this.stream.scrollTop = this.stream.scrollHeight - prevHeight;
                this.refreshRelativeTimes(this.stream);
            }
            this.stream.dataset.hasMore = d.has_more ? '1' : '0';
        } catch (err) { this.toast(err.message, 'error'); }
        finally {
            if (indicator) indicator.hidden = true;
            this.isLoadingOlderMessages = false;
        }
    }

    appendMessage(msg, mine = false) {
        if (!this.stream) return;
        const w = document.createElement('div');
        w.className = `msg-row ${mine ? 'mine' : 'theirs'}`;
        w.dataset.messageId = msg.id;
        w.innerHTML = this.renderBubbleRow(msg, mine);
        this.stream.appendChild(w);
        this.refreshRelativeTimes(w);
        this.scrollToBottom();
    }

    renderBubbleRow(msg, mine = false) {
        const reply = msg.reply_to_message_id ? `<div class="msg-reply ${mine ? 'mine' : 'theirs'}"><i class="fas fa-reply"></i><span>${this.escape((msg.reply_body || '').trim() || (msg.reply_image_path ? 'Photo' : 'Message'))}</span></div>` : '';
        const img = msg.image_path ? `<img src="assets/messages/${this.escape(msg.image_path)}" alt="" class="msg-image" loading="lazy">` : '';
        const body = msg.body ? `<p>${this.escape(msg.body)}</p>` : '';
        const edited = msg.edited_at ? `<span class="msg-edited">edited</span>` : '';
        const readIcon = mine ? (msg.is_read ? '<i class="fas fa-check-double msg-read"></i>' : '<i class="fas fa-check msg-sent"></i>') : '';
        const pinned = msg.is_pinned ? `<div class="msg-pinned"><i class="fas fa-thumbtack"></i> Pinned</div>` : '';
        const name = this.escape(msg.full_name || msg.username || 'User');
        const preview = this.escape((msg.body || '').trim() || (msg.image_path ? 'Photo' : 'Message'));
        let reactionPills = '';
        if (msg.reaction_summary?.length) {
            reactionPills = `<div class="msg-reaction-pills ${mine ? 'mine' : ''}">`;
            for (const rxn of msg.reaction_summary) {
                const isActive = msg.viewer_reaction === rxn.reaction_type;
                reactionPills += `<span class="msg-reaction-pill ${isActive ? 'active' : ''}"><span class="reaction-emoji">${this.escape(rxn.reaction_type)}</span><span class="reaction-count">${rxn.count}</span></span>`;
            }
            reactionPills += `</div>`;
        }
        const reactionsList = ['👍', '❤️', '😂', '😮', '😢'];
        const reactionBar = `<div class="msg-reaction-bar ${mine ? 'mine' : ''}">${reactionsList.map(e => `<button class="msg-reaction-btn" data-message-id="${msg.id}" data-reaction="${this.escape(e)}" title="${this.escape(e)}">${e}</button>`).join('')}</div>`;
        return `${!mine ? `<div class="msg-avatar"><img src="assets/avatars/${this.escape(msg.avatar || 'default.png')}" alt="" onerror="this.src='assets/avatars/default.png'"></div>` : ''}<span class="select-checkbox"></span><div class="msg-content">${!mine ? `<div class="msg-sender-label">${this.escape(msg.full_name || msg.username || 'User')}</div>` : ''}${reply}<div class="msg-bubble ${mine ? 'mine' : 'theirs'}">${img}${body}<div class="msg-meta"><span class="msg-time">${this.escape(msg.created_at_formatted || this.formatMsgTime(msg.created_at))}</span>${edited}${readIcon}</div></div>${pinned}${reactionPills}${reactionBar}</div><div class="msg-actions"><button class="msg-action msg-reply-action message-reply-btn" title="Reply" data-message-id="${msg.id}" data-author-name="${name}" data-preview="${preview}"><i class="fas fa-reply"></i></button>${mine ? `<button class="msg-action msg-edit-action message-edit-btn" title="Edit" data-message-id="${msg.id}" data-body="${this.escape(msg.body || '')}"><i class="fas fa-pen"></i></button>` : ''}<button class="msg-action msg-pin-action message-pin-btn" title="${msg.is_pinned ? 'Unpin' : 'Pin'}" data-message-id="${msg.id}" data-pinned="${msg.is_pinned ? '1' : '0'}"><i class="fas fa-thumbtack"></i></button><button class="msg-action msg-forward-action message-forward-btn" title="Forward" data-message-id="${msg.id}"><i class="fas fa-forward"></i></button>${mine ? `<button class="msg-action msg-delete-action message-delete-btn" title="Delete" data-message-id="${msg.id}"><i class="fas fa-trash"></i></button>` : ''}</div>`;
    }

    formatMsgTime(dt) {
        if (!dt) return '';
        const d = new Date(String(dt).replace(' ', 'T'));
        if (isNaN(d.getTime())) return '';
        let h = d.getHours(), m = d.getMinutes();
        const ampm = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12;
        return h + ':' + String(m).padStart(2, '0') + ' ' + ampm;
    }

    /* ============ EDIT DIALOG ============ */
    openEditMessageDialog(button, csrfToken) {
        const msgId = button.dataset.messageId;
        const current = button.dataset.body || '';
        const overlay = document.createElement('div');
        overlay.className = 'edit-dialog-overlay';
        overlay.innerHTML = `<div class="edit-dialog-box"><div class="edit-dialog-header"><h3><i class="fas fa-pen"></i>Edit Message</h3><button class="edit-dialog-close"><i class="fas fa-times"></i></button></div><div class="edit-dialog-body"><textarea class="edit-dialog-textarea" rows="4">${this.escape(current)}</textarea><div class="edit-dialog-actions"><button class="edit-dialog-cancel">Cancel</button><button class="edit-dialog-save">Save</button></div><div class="edit-dialog-error"></div></div></div>`;
        document.getElementById('modalRoot')?.appendChild(overlay) || document.body.appendChild(overlay);
        const close = () => overlay.remove();
        overlay.querySelector('.edit-dialog-close').onclick = close;
        overlay.querySelector('.edit-dialog-cancel').onclick = close;
        overlay.onclick = (e) => { if (e.target === overlay) close(); };
        const ta = overlay.querySelector('.edit-dialog-textarea');
        const saveBtn = overlay.querySelector('.edit-dialog-save');
        const errEl = overlay.querySelector('.edit-dialog-error');
        ta.focus();
        saveBtn.onclick = async () => {
            const newBody = ta.value.trim();
            if (!newBody) { errEl.textContent = 'Message cannot be empty'; errEl.style.display = 'block'; return; }
            saveBtn.disabled = true;
            try {
                const fd = new FormData();
                fd.append('action', 'edit_message');
                fd.append('message_id', msgId);
                fd.append('body', newBody);
                fd.append('csrf_token', csrfToken);
                const d = await this.post(fd);
                if (!d.success) throw new Error(d.message || 'Unable to edit');
                const wrapper = document.querySelector(`[data-message-id="${msgId}"]`);
                if (wrapper) {
                    const p = wrapper.querySelector('.msg-bubble p');
                    if (p) p.textContent = newBody;
                    button.dataset.body = newBody;
                }
                this.toast('Message updated', 'success');
                close();
            } catch (err) { errEl.textContent = err.message; errEl.style.display = 'block'; saveBtn.disabled = false; }
        };
    }

    /* ============ REPLY ============ */
    setReplyTarget(button) {
        this.replyTarget = { id: button.dataset.messageId, author: button.dataset.authorName || 'User', preview: button.dataset.preview || 'Message' };
        if (this.replyInput) this.replyInput.value = this.replyTarget.id;
        if (this.replyBox) {
            this.replyBox.hidden = false;
            this.replyBox.innerHTML = `<div class="reply-preview"><div class="reply-preview-info"><i class="fas fa-reply"></i><strong>${this.escape(this.replyTarget.author)}</strong><span>${this.escape(this.replyTarget.preview)}</span></div><button type="button" class="reply-preview-cancel" aria-label="Cancel"><i class="fas fa-times"></i></button></div>`;
        }
        this.form?.querySelector('textarea[name="body"]')?.focus();
    }

    clearReplyTarget() { this.replyTarget = null; if (this.replyInput) this.replyInput.value = ''; if (this.replyBox) { this.replyBox.hidden = true; this.replyBox.innerHTML = ''; } }

    /* ============ IMAGE PREVIEW ============ */
    updateImagePreview(input, preview) {
        const [file] = input.files || [];
        if (!file) { preview.innerHTML = ''; preview.style.display = 'none'; return; }
        const reader = new FileReader();
        reader.onload = () => {
            preview.style.display = 'block';
            preview.innerHTML = `<div class="compose-preview-card"><img src="${reader.result}" alt=""><button type="button" class="compose-preview-clear" aria-label="Remove"><i class="fas fa-times"></i></button></div>`;
            preview.querySelector('.compose-preview-clear').onclick = () => { input.value = ''; preview.innerHTML = ''; preview.style.display = 'none'; };
        };
        reader.readAsDataURL(file);
    }

    /* ============ THREAD PREVIEW ============ */
    updateRecentThreadPreview(msg) {
        const activeUserId = this.root?.dataset.activeUserId;
        if (!activeUserId) return;
        const thread = document.querySelector(`a[data-thread-user-id="${activeUserId}"]`);
        if (!thread) return;
        const prev = thread.querySelector('.list-item-preview');
        if (prev) prev.textContent = (msg.body || '').trim() || (msg.image_path ? 'Sent a photo' : 'Start a conversation');
        this.syncThreadList();
    }

    /* ============ DELETE ============ */
    async deleteMessage(button, csrfToken) {
        this.showConfirmDialog('Delete message?', 'This message will be removed. Are you sure?', async () => {
            const fd = new FormData();
            fd.append('action', 'delete_message');
            fd.append('message_id', button.dataset.messageId);
            fd.append('csrf_token', csrfToken);
            try {
                const d = await this.post(fd);
                if (d.success) { document.querySelector(`[data-message-id="${button.dataset.messageId}"]`)?.remove(); this.updateHeaderCounts(d.counts); this.toast('Message deleted', 'success'); }
            } catch (err) { this.toast(err.message, 'error'); }
        });
    }

    async deleteSelected(csrfToken) {
        const ids = [...this.selectedIds];
        if (!ids.length) return;
        this.showConfirmDialog('Delete ' + ids.length + ' message(s)?', 'Selected messages will be removed.', async () => {
            for (const id of ids) {
                try {
                    const fd = new FormData();
                    fd.append('action', 'delete_message');
                    fd.append('message_id', id);
                    fd.append('csrf_token', csrfToken);
                    const d = await this.post(fd);
                    if (d.success) { document.querySelector(`[data-message-id="${id}"]`)?.remove(); if (d.counts) this.updateHeaderCounts(d.counts); }
                } catch (e) {}
            }
            this.exitSelectMode();
            this.toast('Messages deleted', 'success');
        });
    }

    /* ============ PIN ============ */
    async togglePinMessage(button, csrfToken) {
        const fd = new FormData();
        fd.append('action', 'toggle_pin_message');
        fd.append('message_id', button.dataset.messageId);
        fd.append('csrf_token', csrfToken);
        try {
            const d = await this.post(fd);
            if (!d.success) throw new Error(d.message);
            const wrapper = document.querySelector(`[data-message-id="${button.dataset.messageId}"]`);
            if (wrapper) {
                const content = wrapper.querySelector('.msg-content');
                const pinnedEl = content?.querySelector('.msg-pinned');
                if (d.is_pinned && !pinnedEl) content?.insertAdjacentHTML('beforeend', `<div class="msg-pinned"><i class="fas fa-thumbtack"></i> Pinned</div>`);
                else if (!d.is_pinned && pinnedEl) pinnedEl.remove();
            }
            button.dataset.pinned = d.is_pinned ? '1' : '0';
            this.toast(d.message || 'Message updated', 'success');
        } catch (err) { this.toast(err.message, 'error'); }
    }

    async openPinnedMessages() {
        const modal = document.getElementById('pinnedMessagesModal');
        const body = document.getElementById('pinnedMessagesBody');
        if (!modal || !body || !this.stream?.dataset.otherUserId) return;
        modal.classList.remove('hidden');
        body.innerHTML = '<div class="pinned-empty">Loading...</div>';
        try {
            const fd = new FormData();
            fd.append('action', 'get_pinned_messages');
            fd.append('other_user_id', this.stream.dataset.otherUserId);
            fd.append('csrf_token', this.root.dataset.csrfToken);
            const d = await this.post(fd);
            if (!d.success) throw new Error(d.message);
            if (!d.messages?.length) { body.innerHTML = '<div class="pinned-empty">No pinned messages.</div>'; return; }
            body.innerHTML = d.messages.map(msg => `<div class="msg-row ${Number(msg.sender_id) === this.viewerId ? 'mine' : 'theirs'}" data-message-id="${msg.id}">${this.renderBubbleRow(msg, Number(msg.sender_id) === this.viewerId)}</div>`).join('');
            this.refreshRelativeTimes(body);
        } catch (err) { body.innerHTML = `<div class="pinned-empty">${err.message}</div>`; }
    }

    closePinnedMessages() { document.getElementById('pinnedMessagesModal')?.classList.add('hidden'); }

    /* ============ RELATIVE TIME ============ */
    refreshRelativeTimes(scope) {
        scope?.querySelectorAll?.('.js-relative-time[data-time]').forEach(n => { n.textContent = this.formatRelativeTime(n.dataset.time || ''); });
    }

    formatRelativeTime(value) {
        if (!value) return '';
        const d = /^\d+$/.test(String(value)) ? new Date(Number(value) * 1000) : new Date(String(value).replace(' ', 'T'));
        if (isNaN(d.getTime())) return String(value);
        const diff = Math.max(0, Math.floor((Date.now() - d.getTime()) / 1000));
        if (diff < 15) return 'just now';
        if (diff < 60) return diff + 's ago';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        const days = Math.floor(diff / 86400);
        if (days <= 3) return days + 'd ago';
        return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
    }

    toast(message, type = 'success') {
        let toast = document.querySelector('.msg-toast');
        if (!toast) { toast = document.createElement('div'); toast.className = 'msg-toast'; document.body.appendChild(toast); }
        toast.className = 'msg-toast' + (type === 'error' ? ' error' : '');
        toast.innerHTML = (type === 'error' ? '<i class="fas fa-exclamation-circle"></i>' : '<i class="fas fa-check-circle"></i>') + this.escape(message);
        toast.classList.add('visible');
        clearTimeout(toast._hide);
        toast._hide = setTimeout(() => toast.classList.remove('visible'), 2800);
    }

    showConfirmDialog(title, message, onConfirm) {
        const overlay = document.createElement('div');
        overlay.className = 'confirm-dialog-overlay';
        overlay.innerHTML = `<div class="confirm-dialog-box"><div class="confirm-dialog-icon"><i class="fas fa-exclamation-triangle"></i></div><div class="confirm-dialog-title">${this.escape(title)}</div><div class="confirm-dialog-msg">${this.escape(message)}</div><div class="confirm-dialog-actions"><button class="confirm-dialog-cancel">Cancel</button><button class="confirm-dialog-confirm">Delete</button></div></div>`;
        document.getElementById('modalRoot')?.appendChild(overlay) || document.body.appendChild(overlay);
        const close = () => overlay.remove();
        overlay.querySelector('.confirm-dialog-cancel').onclick = close;
        overlay.querySelector('.confirm-dialog-confirm').onclick = () => { close(); onConfirm(); };
        overlay.onclick = (e) => { if (e.target === overlay) close(); };
    }

    /* ============ NOTIFICATIONS ============ */
    bindNotifications() {
        const root = document.querySelector('[data-notifications-page]');
        if (!root) return;
        this.notificationsRoot = root;
        this.notificationsList = document.getElementById('notificationsList');
        this.notificationsLoadIndicator = document.getElementById('notificationsLoadIndicator');
        this.notificationsFeedWrap = document.getElementById('notificationsFeedWrap');
        this.notificationFilter = root.dataset.filter || 'all';
        this.isLoadingNotifications = false;
        this.refreshRelativeTimes(root);
        this.setupNotificationObserver();
        
        // Ensure default tab is active
        this.notificationsRoot.querySelectorAll('.notifications-tab').forEach(b => {
            b.classList.toggle('active', b.dataset.filter === this.notificationFilter);
        });

        root.onclick = (event) => {
            const markBtn = event.target.closest('.mark-notification-btn');
            const tabBtn = event.target.closest('.notifications-tab');
            const markAllBtn = event.target.closest('#markAllNotificationsBtn');
            const card = event.target.closest('.notification-card-modern');
            
            if (markBtn) { 
                event.preventDefault(); 
                event.stopPropagation(); 
                this.markNotification(markBtn.dataset.notificationId, root.dataset.csrfToken); 
                return; 
            }
            if (tabBtn) { 
                event.preventDefault(); 
                this.switchNotificationFilter(tabBtn.dataset.filter || 'all'); 
                return; 
            }
            if (markAllBtn) { 
                event.preventDefault(); 
                this.markAllNotifications(root.dataset.csrfToken); 
                return; 
            }
            if (card) { 
                event.preventDefault(); 
                this.openNotificationCard(card); 
                return; 
            }
        };
    }

    async markNotification(id, csrfToken) {
        try {
            const fd = new FormData();
            fd.append('action', 'mark_notification_read');
            fd.append('notification_id', id);
            fd.append('csrf_token', csrfToken);
            const d = await this.post(fd);
            if (d.success) {
                this.applyNotificationReadState(document.querySelector(`[data-notification-id="${id}"]`), true);
                this.updateHeaderCounts(d.counts);
                this.updateNotificationTabCounts(d.tab_counts);
            }
        } catch (e) {}
    }

    async markAllNotifications(csrfToken) {
        try {
            const fd = new FormData();
            fd.append('action', 'mark_all_notifications_read');
            fd.append('csrf_token', csrfToken);
            const d = await this.post(fd);
            if (d.success) {
                document.querySelectorAll('.notification-card-modern').forEach(c => this.applyNotificationReadState(c, true));
                this.updateHeaderCounts(d.counts);
                this.updateNotificationTabCounts(d.tab_counts);
                // Update the header unread badge next to "Mark all read"
                const headerBadge = this.notificationsRoot?.querySelector('.rounded-full.bg-rose-500');
                if (headerBadge) headerBadge.textContent = '0';
            }
        } catch (e) {}
    }

    updateNotificationTabCounts(tabCounts) {
        if (!tabCounts || !this.notificationsRoot) return;
        Object.entries(tabCounts).forEach(([k, v]) => {
            const el = this.notificationsRoot.querySelector(`[data-tab-count="${k}"]`);
            if (el) el.textContent = v;
        });
    }

    async switchNotificationFilter(filter) {
        this.notificationFilter = filter;
        this.notificationsRoot.querySelectorAll('.notifications-tab').forEach(b => b.classList.toggle('active', b.dataset.filter === filter));
        if (this.notificationsFeedWrap) this.notificationsFeedWrap.scrollTop = 0;
        await this.loadNotifications(true);
        this.setupNotificationObserver(); // Re-initialize observer for the new list
    }

    async loadNotifications(reset = false) {
        if (!this.notificationsList || !this.notificationsRoot) return;
        this.isLoadingNotifications = true;
        if (this.notificationsLoadIndicator) this.notificationsLoadIndicator.hidden = false;
        try {
            const fd = new FormData();
            fd.append('action', 'load_notifications');
            fd.append('filter', this.notificationFilter);
            fd.append('csrf_token', this.notificationsRoot.dataset.csrfToken);
            if (!reset) {
                const cards = this.notificationsList.querySelectorAll('.notification-card-modern');
                const last = cards[cards.length - 1];
                if (last?.dataset.notificationId) fd.append('before_id', last.dataset.notificationId);
            }
            const d = await this.post(fd);
            if (reset) {
                this.notificationsList.innerHTML = '';
            }
            if (d.notifications?.length) {
                this.notificationsList.insertAdjacentHTML('beforeend', d.notifications.map(n => this.renderNotificationCard(n)).join(''));
                this.refreshRelativeTimes(this.notificationsList);
            } else if (reset) {
                this.notificationsList.innerHTML = `<div class="text-center py-16 px-6">
                    <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-700 dark:to-gray-600 flex items-center justify-center shadow-inner">
                        <i class="fas fa-bell-slash text-2xl text-gray-400 dark:text-gray-500"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">No notifications found</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 max-w-sm mx-auto">Try switching filters or check back later.</p>
                </div>`;
            }
            this.notificationsList.dataset.hasMore = d.has_more ? '1' : '0';

            // Update counts in UI if provided
            if (d.counts) {
                this.updateHeaderCounts(d.counts);
                if (d.tab_counts) {
                    Object.entries(d.tab_counts).forEach(([k, v]) => {
                        const el = this.notificationsRoot.querySelector(`[data-tab-count="${k}"]`);
                        if (el) el.textContent = v;
                    });
                }
            }
        } catch (err) {} finally {
            this.isLoadingNotifications = false;
            if (this.notificationsLoadIndicator) this.notificationsLoadIndicator.hidden = (this.notificationsList.dataset.hasMore !== '1');
        }
    }

    setupNotificationObserver() {
        if (!this.notificationsLoadIndicator) return;
        const observer = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting && !this.isLoadingNotifications && this.notificationsList?.dataset.hasMore === '1') this.loadNotifications(false);
        }, { threshold: 0.1 });
        observer.observe(this.notificationsLoadIndicator);
    }

    renderNotificationCard(n) {
        const meta = n.meta || { icon: 'bell', color: '#64748b', label: 'Update', accent: 'is-system' };
        const unread = Number(n.is_read) === 0;
        const url = this.escape(n.target_url || '#');
        const name = this.escape(n.actor_name || 'User');
        const msg = this.escape(n.message || '');
        const color = meta.color || '#64748b';
        const label = this.escape(meta.label || '');
        const icon = this.escape(meta.icon || 'bell');
        const avatar = this.escape(n.avatar || 'default.png');
        const accent = this.escape(meta.accent || 'is-system');
        const time = this.escape(n.time_ago || '');
        
        let bodyHtml = '';
        if (n.type === 'message') {
            bodyHtml = `<strong class="font-semibold">${name}</strong><span class="text-gray-500 dark:text-gray-400"> sent you a message</span><div class="mt-1.5 text-xs text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-700/50 rounded-lg px-3 py-1.5 italic truncate max-w-xs border-l-2 border-purple-400">${msg}</div>`;
        } else if (n.type === 'friend_request') {
            bodyHtml = `<strong class="font-semibold">${name}</strong><span class="text-gray-600 dark:text-gray-300"> sent you a friend request</span>`;
        } else if (n.type === 'friend_accept') {
            bodyHtml = `<strong class="font-semibold">${name}</strong><span class="text-emerald-600 dark:text-emerald-400 font-medium"> accepted your friend request</span><div class="flex items-center gap-2 mt-1.5"><span class="inline-flex items-center gap-1 text-[10px] font-semibold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/20 px-2 py-0.5 rounded-full"><i class="fas fa-user-check text-[8px]"></i> Now friends</span></div>`;
        } else if (n.type === 'payment_cancelled') {
            bodyHtml = `<strong class="font-semibold">${name}</strong><span class="text-red-600 dark:text-red-400 font-medium"> ${msg}</span>`;
        } else {
            bodyHtml = `<strong class="font-semibold">${name}</strong><span class="text-gray-600 dark:text-gray-300"> ${msg}</span>`;
        }

        const dotHtml = unread ? '<span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>' : '';
        const markBtn = unread ? `<button class="mark-notification-btn flex-shrink-0 w-7 h-7 rounded-full border-0 flex items-center justify-center text-gray-300 hover:text-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-all opacity-0 self-center" title="Mark as read" data-notification-id="${n.id}"><i class="fas fa-check text-[10px]"></i></button>` : '';

        return `<a href="${url}" class="notification-card-modern notification-open-btn block ${unread ? 'is-unread' : ''} ${accent} no-underline" data-notification-id="${n.id}" data-notification-url="${url}" data-is-read="${unread ? '0' : '1'}">
            <div class="flex items-start gap-3.5 px-5 py-3.5">
                <div class="relative flex-shrink-0" style="width:44px;height:44px">
                    <img src="assets/avatars/${avatar}" alt="" class="w-11 h-11 rounded-full object-cover border-2 border-gray-200 dark:border-gray-600" onerror="this.src='assets/avatars/default.png'">
                    <span class="absolute -bottom-0.5 -right-0.5 w-[18px] h-[18px] rounded-full flex items-center justify-center text-white text-[7px] border-2 border-white dark:border-gray-800 shadow-sm" style="background:${color}"><i class="fas fa-${icon}"></i></span>
                </div>
                <div class="flex-1 min-w-0 pt-0.5">
                    <div class="text-sm text-gray-900 dark:text-white leading-snug">${bodyHtml}</div>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="text-xs text-gray-400 dark:text-gray-500">${time}</span>
                        <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded" style="background:${color}12;color:${color}">${label}</span>
                        ${dotHtml}
                    </div>
                </div>
                ${markBtn}
            </div>
        </a>`;
    }

    async openNotificationCard(card) {
        if (!card) return;
        const url = card.dataset.notificationUrl || '#';
        if (card.dataset.isRead === '0') {
            try { await this.markNotification(card.dataset.notificationId, this.notificationsRoot?.dataset.csrfToken); } catch(e) {}
        }
        if (url === '#') return;
        
        // Handle AJAX navigation if available
        if (window.AjaxNavigation && typeof window.AjaxNavigation.navigate === 'function') {
            const pageMatch = url.match(/[?&]page=([^&]+)/);
            if (pageMatch) {
                window.AjaxNavigation.navigate(pageMatch[1], url);
                return;
            }
        }
        window.location.href = url;
    }

    applyNotificationReadState(card, read) { 
        if (!card) return; 
        card.classList.toggle('is-unread', !read); 
        card.dataset.isRead = read ? '1' : '0'; 
        card.querySelector('.mark-notification-btn')?.remove();
        if (read) {
            const dot = card.querySelector('.bg-blue-500');
            if (dot) dot.remove();
        }
    }

    async handleReact(msgId, reaction, csrfToken) {
        try {
            const fd = new FormData(); fd.append('action', 'react_message'); fd.append('message_id', msgId); fd.append('reaction', reaction); fd.append('csrf_token', csrfToken);
            const data = await this.post(fd);
            if (!data.success) throw new Error(data.message);
            const row = document.querySelector(`.msg-row[data-message-id="${msgId}"]`);
            if (row) {
                const content = row.querySelector('.msg-content');
                content.querySelector('.msg-reaction-pills')?.remove();
                content.querySelector('.msg-reaction-bar')?.remove();
                const bubble = content.querySelector('.msg-bubble');
                bubble.insertAdjacentHTML('afterend', this.renderReactionsHTML(data, msgId, row.classList.contains('mine')));
            }
        } catch (err) { this.toast(err.message, 'error'); }
    }

    renderReactionsHTML(data, msgId, mine) {
        let html = `<div class="msg-reaction-pills ${mine ? 'mine' : ''}">`;
        (data.reaction_summary || []).forEach(rxn => {
            html += `<span class="msg-reaction-pill ${data.viewer_reaction === rxn.reaction_type ? 'active' : ''}"><span class="reaction-emoji">${rxn.reaction_type}</span><span class="reaction-count">${rxn.count}</span></span>`;
        });
        html += `</div><div class="msg-reaction-bar ${mine ? 'mine' : ''}">${['👍', '❤️', '😂', '😮', '😢'].map(e => `<button class="msg-reaction-btn" data-message-id="${msgId}" data-reaction="${e}">${e}</button>`).join('')}</div>`;
        return html;
    }

    async handlePinConversation(otherUserId, dropItem, csrfToken) {
        try {
            const fd = new FormData();
            fd.append('action', 'pin_conversation');
            fd.append('other_user_id', otherUserId);
            fd.append('csrf_token', csrfToken);
            const data = await this.post(fd);
            if (data.success) {
                const item = document.querySelector(`.list-item[data-thread-user-id="${otherUserId}"]`);
                if (item) {
                    item.classList.toggle('pinned', data.pinned);
                    const topRow = item.querySelector('.list-item-top');
                    let badge = item.querySelector('.pin-badge');
                    if (data.pinned) {
                        if (!badge && topRow) {
                            badge = document.createElement('span');
                            badge.className = 'pin-badge';
                            badge.innerHTML = '<i class="fas fa-thumbtack"></i> Pinned';
                            topRow.appendChild(badge);
                        }
                        document.getElementById('sidebarList')?.prepend(item);
                    } else {
                        if (badge) badge.remove();
                    }
                }
                if (dropItem) {
                    const txt = dropItem.querySelector('.pin-text') || dropItem.querySelector('span') || dropItem;
                    if (txt) txt.textContent = data.pinned ? 'Unpin' : 'Pin';
                }
                this.syncThreadList();
                this.toast(data.message, 'success');
            }
        } catch (err) { this.toast(err.message, 'error'); }
    }

    async post(formData) { const r = await fetch(this.handlerUrl, { method: 'POST', body: formData }); return r.json(); }
    escape(text) { return String(text).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c])); }
    updateHeaderCounts(counts = {}) {
        // Handle Notification Badge
        if (typeof counts.notifications !== 'undefined') {
            const notifLink = document.querySelector('a[data-page="notifications"]');
            if (notifLink) {
                let badge = notifLink.querySelector('.dream-counter-badge');
                if (counts.notifications > 0) {
                    if (!badge) {
                        badge = document.createElement('span');
                        badge.className = 'dream-counter-badge';
                        notifLink.appendChild(badge);
                    }
                    badge.textContent = counts.notifications > 9 ? '9+' : counts.notifications;
                } else if (badge) {
                    badge.remove();
                }
            }
        }
        
        // Handle Message Badge
        if (typeof counts.messages !== 'undefined') {
            const msgLink = document.querySelector('a[data-page="messages"]');
            if (msgLink) {
                let badge = msgLink.querySelector('.dream-counter-badge');
                if (counts.messages > 0) {
                    if (!badge) {
                        badge = document.createElement('span');
                        badge.className = 'dream-counter-badge';
                        msgLink.appendChild(badge);
                    }
                    badge.textContent = counts.messages > 9 ? '9+' : counts.messages;
                } else if (badge) {
                    badge.remove();
                }
            }
        }
    }
}
}

document.addEventListener('DOMContentLoaded', () => { window.socialPages = new window.SocialPages(); });
document.addEventListener('pageChanged', (event) => {
    if (event.detail?.page === 'messages' || event.detail?.page === 'notifications') window.socialPages = new window.SocialPages();
});
