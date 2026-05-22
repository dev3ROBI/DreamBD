if (typeof window.SocialFeed === 'undefined') {
    window.SocialFeed = class SocialFeed {
        constructor() {
            if (window.__socialFeedBound) {
                return;
            }

            this.handlerUrl = 'handlers/profile_handlers.php';
            this.reactionHideTimeout = null;
            this.bind();
            this.refreshRelativeTimes();
            window.clearInterval(window.__socialFeedRelativeTimeTimer);
            window.__socialFeedRelativeTimeTimer = window.setInterval(() => this.refreshRelativeTimes(), 60000);
            this.handleFeedDeepLink();
            window.__socialFeedBound = true;
        }

        handleFeedDeepLink() {
            const hash = window.location.hash;
            if (hash && hash.startsWith('#post-')) {
                const postId = hash.replace('#post-', '');
                const card = document.querySelector(`[data-post-id="${postId}"]`);
                if (card) {
                    card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    card.classList.add('post-highlight');
                    setTimeout(() => card.classList.remove('post-highlight'), 3000);
                }
            }
        }

    bind() {
        document.addEventListener('click', (event) => {
            const likeButton = event.target.closest('.like-btn');
            const shareButton = event.target.closest('.share-btn');
            const commentToggle = event.target.closest('.comment-toggle-btn');
            const postMenuToggle = event.target.closest('.post-menu-toggle');
            const editPostButton = event.target.closest('.edit-post-btn');
            const deletePostButton = event.target.closest('.delete-post-btn');
            const morePostButton = event.target.closest('.more-post-btn');
            const deleteCommentButton = event.target.closest('.delete-comment-btn');
            const editCommentButton = event.target.closest('.edit-comment-btn');
            const reportCommentButton = event.target.closest('.report-comment-btn');
            const commentMenuToggle = event.target.closest('.comment-menu-toggle');
            const commentReactButton = event.target.closest('.social-comment-react-btn');
            const commentReactionOption = event.target.closest('.comment-reaction-option');
            const commentReplyButton = event.target.closest('.social-comment-reply-btn');
            const commentReplyCancelButton = event.target.closest('.comment-reply-cancel');

            if (!event.target.closest('.post-menu-wrap, .home-post-menu-wrap')) {
                this.closePostMenus();
            }
            if (!event.target.closest('.comment-menu-wrap')) {
                this.closeCommentMenus();
            }

            if (!event.target.closest('.comment-reaction-picker, .social-comment-react-btn')) {
                this.hideCommentReactionPicker();
            }

            if (postMenuToggle) {
                event.preventDefault();
                event.stopPropagation();
                this.togglePostMenu(postMenuToggle);
            } else if (commentMenuToggle) {
                event.preventDefault();
                event.stopPropagation();
                this.toggleCommentMenu(commentMenuToggle);
            } else if (editCommentButton) {
                event.preventDefault();
                this.editComment(editCommentButton);
            } else if (reportCommentButton) {
                event.preventDefault();
                this.reportComment(reportCommentButton);
            } else if (commentReactionOption) {
                event.preventDefault();
                this.applyCommentReaction(commentReactionOption);
            } else if (commentReactButton) {
                event.preventDefault();
                this.showCommentReactionPicker(commentReactButton);
            } else if (commentReplyButton) {
                event.preventDefault();
                this.setCommentReplyTarget(commentReplyButton);
            } else if (commentReplyCancelButton) {
                event.preventDefault();
                this.clearCommentReplyTarget(commentReplyCancelButton.closest('.comment-form'));
            } else if (editPostButton) {
                event.preventDefault();
                this.editPost(editPostButton);
            } else if (likeButton) {
                event.preventDefault();
                this.toggleLike(likeButton);
            } else if (shareButton) {
                event.preventDefault();
                this.sharePost(shareButton);
            } else if (commentToggle) {
                event.preventDefault();
                this.toggleComments(commentToggle);
            } else if (deletePostButton) {
                event.preventDefault();
                this.deletePost(deletePostButton);
            } else if (morePostButton) {
                event.preventDefault();
                this.showMoreOptions(morePostButton);
            } else if (deleteCommentButton) {
                event.preventDefault();
                this.deleteComment(deleteCommentButton);
            }
        });

        document.addEventListener('submit', (event) => {
            const commentForm = event.target.closest('.comment-form');
            if (!commentForm) {
                return;
            }

            event.preventDefault();
            this.submitComment(commentForm);
        });

        document.addEventListener('mouseover', (event) => {
            const likeButton = event.target.closest('.like-btn');
            if (!likeButton) {
                return;
            }
            if (likeButton.closest('.home-post-actions--facebook')) {
                return;
            }

            clearTimeout(this.reactionHideTimeout);
            this.showReactionPicker(likeButton);
        });

        document.addEventListener('mouseout', (event) => {
            const leftLikeButton = event.target.closest('.like-btn');
            if (!leftLikeButton) {
                return;
            }
            if (leftLikeButton.closest('.home-post-actions--facebook')) {
                return;
            }

            if (event.relatedTarget?.closest('.social-reaction-picker')) {
                return;
            }

            this.scheduleReactionPickerHide();
        });

        document.addEventListener('click', (event) => {
            const reactionOption = event.target.closest('.reaction-option');
            if (reactionOption) {
                event.preventDefault();
                this.applyReaction(reactionOption);
            }
        });
    }

    async toggleLike(button, reaction = null) {
        const postId = button.dataset.postId;
        const csrfToken = this.findCsrfToken(button);
        const nextReaction = reaction || button.dataset.reaction || 'like';
        const formData = new FormData();
        formData.append('action', 'toggle_like');
        formData.append('post_id', postId);
        formData.append('reaction', nextReaction);
        formData.append('csrf_token', csrfToken);

        try {
            const data = await this.post(formData);
            if (!data.success) {
                throw new Error(data.message || 'Unable to like post');
            }

            button.classList.toggle('liked', data.liked);
            button.classList.toggle('active', data.liked);
            button.dataset.reaction = data.viewer_reaction || '';
            const nextMeta = data.viewer_reaction ? this.getReactionDisplayMeta(data.viewer_reaction) : this.getReactionDisplayMeta('like');
            button.innerHTML = `<i class="fas fa-${data.viewer_reaction ? nextMeta.icon : 'thumbs-up'}"></i> ${data.viewer_reaction ? nextMeta.label : 'Like'}`;
            this.updateCount(button, '.like-count', data.like_count, '<i class="fas fa-thumbs-up"></i> {value}');
            this.updateReactionSummary(button, data.reaction_summary || [], data.like_count);
            this.hideReactionPicker();
        } catch (error) {
            this.notify(error.message, 'error');
        }
    }

    applyReaction(option) {
        const button = document.querySelector(`.like-btn[data-post-id="${option.dataset.postId}"]`);
        if (!button) {
            return;
        }

        this.toggleLike(button, option.dataset.reaction);
    }

    async sharePost(button) {
        const postId = button.dataset.postId;
        const csrfToken = this.findCsrfToken(button);
        const formData = new FormData();
        formData.append('action', 'share_post');
        formData.append('post_id', postId);
        formData.append('csrf_token', csrfToken);

        try {
            const data = await this.post(formData);
            if (!data.success) {
                throw new Error(data.message || 'Unable to share post');
            }

            this.updateCount(button, '.share-count', data.share_count, '<i class="fas fa-share"></i> {value}');
            const shareUrl = `${window.location.origin}${window.location.pathname}?page=community#post-${postId}`;
            if (navigator.share) {
                navigator.share({ title: 'DreamBD Post', url: shareUrl }).catch(() => {});
            } else if (navigator.clipboard?.writeText) {
                navigator.clipboard.writeText(shareUrl).catch(() => {});
            }
            this.notify(data.message || 'Post shared', 'success');
        } catch (error) {
            this.notify(error.message, 'error');
        }
    }

    async submitComment(form) {
        const button = form.querySelector('[type="submit"]');
        const original = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '...';

        try {
            const formData = new FormData(form);
            formData.append('action', 'add_comment');
            const data = await this.post(formData);
            if (!data.success) {
                throw new Error(data.message || 'Unable to add comment');
            }

            const card = this.getPostCard(form);
            const container = card?.querySelector('.home-feed-comments, .comment-list');
            this.insertComment(container, data.comment, form);
            this.setCommentsOpen(card, true);
            this.updateCount(form, '.comment-count', data.comment_count, '<i class="fas fa-comment"></i> {value} comments');
            this.refreshRelativeTimes(card);
            form.reset();
            this.clearCommentReplyTarget(form);
        } catch (error) {
            this.notify(error.message, 'error');
        } finally {
            button.disabled = false;
            button.innerHTML = original;
        }
    }

    renderComment(comment, container) {
        const name = this.escape(comment.full_name || comment.username || 'User');
        const text = this.escape(comment.comment_text || '');
        const avatar = this.escape(comment.avatar || 'default.png');
        const reactionLabel = comment.viewer_reaction ? this.getReactionDisplayMeta(comment.viewer_reaction).label : 'Like';
        const reactionSummary = this.renderCommentReactionSummary(comment.reaction_summary || [], comment.reaction_count || 0);
        const isReply = Number(comment.parent_comment_id || 0) > 0;
        const replyLabel = isReply ? 'Reply again' : 'Reply';
        const author = this.escape(comment.full_name || comment.username || 'User');
        const menuItems = [
            comment.can_delete ? `<button class="comment-menu-item edit-comment-btn" type="button" data-comment-id="${comment.id}"><i class="fas fa-pen"></i> Edit</button>` : '',
            comment.can_delete ? `<button class="comment-menu-item delete-comment-btn" type="button" data-comment-id="${comment.id}"><i class="fas fa-trash"></i> Delete</button>` : '',
            `<button class="comment-menu-item report-comment-btn" type="button" data-comment-id="${comment.id}"><i class="fas fa-flag"></i> Report</button>`
        ].join('');

        return `
            <div class="social-comment-card ${isReply ? 'is-reply' : ''}" data-comment-id="${comment.id}">
                <img src="assets/avatars/${avatar}" alt="" class="social-comment-avatar" onerror="this.src='assets/avatars/default.png'">
                <div class="social-comment-main">
                    <div class="social-comment-bubble">
                        <div class="social-comment-topline">
                            <strong>${name}</strong>
                            <div class="comment-menu-wrap">
                                <button class="comment-menu-toggle comment-ghost-btn" type="button" data-comment-id="${comment.id}" aria-label="Comment options"><i class="fas fa-ellipsis-h"></i></button>
                                <div class="comment-menu-dropdown">${menuItems}</div>
                            </div>
                        </div>
                        <p>${text.replace(/\n/g, '<br>')}</p>
                        <div class="social-comment-reactions is-attached" data-comment-reaction-summary>${reactionSummary}</div>
                    </div>
                    <div class="social-comment-meta">
                        <span class="social-comment-time js-social-relative-time" data-time="${this.escape(comment.created_at || '')}" title="${this.escape(comment.created_at_exact || '')}">${this.escape(comment.created_at_formatted || 'Just now')}</span>
                        <button class="social-comment-action social-comment-react-btn" type="button" data-comment-id="${comment.id}" data-reaction="${this.escape(comment.viewer_reaction || '')}">
                            <i class="fas fa-${comment.viewer_reaction ? this.getReactionDisplayMeta(comment.viewer_reaction).icon : 'thumbs-up'}"></i> ${this.escape(reactionLabel)}
                        </button>
                        <button class="social-comment-action social-comment-reply-btn" type="button" data-comment-id="${comment.id}" data-comment-author="${author}" data-comment-preview="${text}">
                            ${replyLabel}
                        </button>
                    </div>
                    <div class="social-comment-replies"></div>
                </div>
            </div>
        `;
    }

    toggleComments(button) {
        const card = this.getPostCard(button);
        const section = card?.querySelector('.comment-section');
        if (!section) return;

        const shouldOpen = !section.classList.contains('open');
        this.setCommentsOpen(card, shouldOpen);
    }

    togglePostMenu(button) {
        const menu = button.parentElement?.querySelector('.post-menu-dropdown');
        if (!menu) return;

        const isOpen = menu.classList.contains('open');
        this.closePostMenus();
        menu.classList.toggle('open', !isOpen);
        button.classList.toggle('active', !isOpen);
    }

    closePostMenus() {
        document.querySelectorAll('.post-menu-dropdown.open').forEach((menu) => {
            menu.classList.remove('open');
        });

        document.querySelectorAll('.post-menu-toggle.active').forEach((button) => {
            button.classList.remove('active');
        });
    }

    toggleCommentMenu(button) {
        const menu = button.parentElement?.querySelector('.comment-menu-dropdown');
        if (!menu) return;

        const isOpen = menu.classList.contains('open');
        this.closeCommentMenus();
        menu.classList.toggle('open', !isOpen);
        button.classList.toggle('active', !isOpen);
    }

    closeCommentMenus() {
        document.querySelectorAll('.comment-menu-dropdown.open').forEach((menu) => menu.classList.remove('open'));
        document.querySelectorAll('.comment-menu-toggle.active').forEach((button) => button.classList.remove('active'));
    }

    showReactionPicker(button) {
        let picker = document.querySelector('.social-reaction-picker');
        if (!picker) {
            picker = document.createElement('div');
            picker.className = 'social-reaction-picker';
            picker.innerHTML = ['like', 'love', 'haha', 'wow', 'sad'].map((reaction) => {
                const meta = this.getReactionDisplayMeta(reaction);
                return `<button class="reaction-option ${meta.class}" type="button" data-reaction="${reaction}" title="${meta.label}">${meta.emoji}</button>`;
            }).join('');
            picker.addEventListener('mouseenter', () => clearTimeout(this.reactionHideTimeout));
            picker.addEventListener('mouseleave', () => this.scheduleReactionPickerHide());
            document.body.appendChild(picker);
        }

        const rect = button.getBoundingClientRect();
        picker.querySelectorAll('.reaction-option').forEach((option) => {
            option.dataset.postId = button.dataset.postId;
        });
        picker.style.top = `${window.scrollY + rect.top - 58}px`;
        picker.style.left = `${window.scrollX + rect.left}px`;
        picker.classList.add('open');
    }

    scheduleReactionPickerHide() {
        clearTimeout(this.reactionHideTimeout);
        this.reactionHideTimeout = setTimeout(() => this.hideReactionPicker(), 120);
    }

    hideReactionPicker() {
        document.querySelector('.social-reaction-picker')?.classList.remove('open');
    }

    async editPost(button) {
        const card = this.getPostCard(button);
        const textElement = card?.querySelector('.home-post-content p, .post-text');
        const currentContent = textElement?.innerText?.trim() || '';

        this.closePostMenus();

        // Use the HomePage edit post dialog if available
        if (window.HomePage && typeof window.HomePage.openEditPostDialog === 'function') {
            window.HomePage.openEditPostDialog(button.dataset.postId, currentContent);
            return;
        }

        // Fallback to prompt
        const nextContent = window.prompt('Edit your post', currentContent);
        if (nextContent === null) return;

        const trimmedContent = nextContent.trim();
        if (!trimmedContent) {
            this.notify('Post content cannot be empty', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'update_post');
        formData.append('post_id', button.dataset.postId);
        formData.append('content', trimmedContent);
        formData.append('csrf_token', this.findCsrfToken(button));

        try {
            const data = await this.post(formData);
            if (!data.success) {
                throw new Error(data.message || 'Unable to edit post');
            }

            if (textElement) {
                textElement.innerHTML = this.formatPostText(trimmedContent);
            }
            this.notify(data.message || 'Post updated', 'success');
        } catch (error) {
            this.notify(error.message, 'error');
        }
    }

    async deletePost(button) {
        this.closePostMenus();
        if (!confirm('Delete this post?')) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'delete_post');
        formData.append('post_id', button.dataset.postId);
        formData.append('csrf_token', this.findCsrfToken(button));

        try {
            const data = await this.post(formData);
            if (!data.success) {
                throw new Error(data.message || 'Unable to delete post');
            }

            this.getPostCard(button)?.remove();
            this.notify(data.message || 'Post deleted', 'success');
        } catch (error) {
            this.notify(error.message, 'error');
        }
    }

    showMoreOptions(button) {
        this.closePostMenus();
        const postId = button.dataset.postId;
        const postUrl = `${window.location.origin}${window.location.pathname}?page=community#post-${postId}`;

        if (navigator.clipboard?.writeText) {
            navigator.clipboard.writeText(postUrl).catch(() => {});
            this.notify('Post link copied', 'success');
            return;
        }

        this.notify('More options coming soon', 'info');
    }

    async deleteComment(button) {
        this.closeCommentMenus();
        if (!confirm('Delete this comment?')) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'delete_comment');
        formData.append('comment_id', button.dataset.commentId);
        formData.append('csrf_token', this.findCsrfToken(button));

        try {
            const data = await this.post(formData);
            if (!data.success) {
                throw new Error(data.message || 'Unable to delete comment');
            }

            const card = this.getPostCard(button);
            button.closest('.social-comment-card, .comment-item, .home-feed-comment')?.remove();
            this.updateCount(card || button, '.comment-count', data.comment_count, '<i class="fas fa-comment"></i> {value} comments');
            this.notify(data.message || 'Comment deleted', 'success');
        } catch (error) {
            this.notify(error.message, 'error');
        }
    }

    async editComment(button) {
        this.closeCommentMenus();
        const card = button.closest('.social-comment-card');
        const textNode = card?.querySelector('.social-comment-bubble p');
        const current = textNode?.innerText?.trim() || '';
        const next = window.prompt('Edit your comment', current);
        if (next === null) {
            return;
        }

        const trimmed = next.trim();
        if (!trimmed) {
            this.notify('Comment cannot be empty', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'update_comment');
        formData.append('comment_id', button.dataset.commentId);
        formData.append('comment', trimmed);
        formData.append('csrf_token', this.findCsrfToken(button));

        try {
            const data = await this.post(formData);
            if (!data.success) {
                throw new Error(data.message || 'Unable to edit comment');
            }

            if (textNode) {
                textNode.innerHTML = this.escape(trimmed).replace(/\n/g, '<br>');
            }
            this.notify(data.message || 'Comment updated', 'success');
        } catch (error) {
            this.notify(error.message, 'error');
        }
    }

    async reportComment(button) {
        this.closeCommentMenus();
        const formData = new FormData();
        formData.append('action', 'report_comment');
        formData.append('comment_id', button.dataset.commentId);
        formData.append('csrf_token', this.findCsrfToken(button));

        try {
            const data = await this.post(formData);
            if (!data.success) {
                throw new Error(data.message || 'Unable to report comment');
            }
            this.notify(data.message || 'Comment reported', 'success');
        } catch (error) {
            this.notify(error.message, 'error');
        }
    }

    updateCount(origin, selector, value, fallbackTemplate = '{value}') {
        const card = this.getPostCard(origin);
        const target = card?.querySelector(selector);
        if (target) {
            target.textContent = value;
            return;
        }

        if (selector === '.like-count') {
            const wrapper = card?.querySelector('.home-post-likes');
            if (wrapper) wrapper.innerHTML = fallbackTemplate.replace('{value}', value);
        } else if (selector === '.comment-count') {
            const wrapper = card?.querySelector('.home-post-comments');
            if (wrapper) wrapper.innerHTML = fallbackTemplate.replace('{value}', value);
        } else if (selector === '.share-count') {
            const wrapper = card?.querySelector('.home-post-shares');
            if (wrapper) wrapper.innerHTML = fallbackTemplate.replace('{value}', value);
        }
    }

    updateReactionSummary(origin, summary, totalCount) {
        const card = this.getPostCard(origin);
        const wrappers = card?.querySelectorAll('.home-post-likes, .post-reaction-summary');
        if (!wrappers?.length) {
            return;
        }

        const top = summary.slice(0, 3).map((reaction) => {
            const meta = this.getReactionDisplayMeta(reaction.type);
            return `<span class="reaction-chip ${meta.class}" title="${meta.label}"><i class="fas fa-${meta.icon}"></i></span>`;
        }).join('');

        const markup = `${top || '<span class="reaction-chip reaction-like"><i class="fas fa-thumbs-up"></i></span>'}<span class="like-count">${totalCount}</span>`;
        wrappers.forEach((wrapper) => {
            wrapper.innerHTML = wrapper.classList.contains('home-post-likes')
                ? `<span class="reaction-stack">${markup.replace(`<span class="like-count">${totalCount}</span>`, '')}</span><span class="like-count">${totalCount}</span>`
                : `<span class="reaction-stack">${markup.replace(`<span class="like-count">${totalCount}</span>`, '')}</span><span class="like-count">${totalCount}</span>`;
        });
    }

    insertComment(container, comment, form = null) {
        if (!container) return;

        const markup = this.renderComment(comment, container);
        if (Number(comment.parent_comment_id || 0) > 0) {
            const parent = container.querySelector(`.social-comment-card[data-comment-id="${comment.parent_comment_id}"] .social-comment-replies`);
            if (parent) {
                parent.insertAdjacentHTML('beforeend', markup);
                return;
            }
        }

        const formWrap = container.querySelector('.home-feed-comment-form');
        if (formWrap) {
            formWrap.insertAdjacentHTML('beforebegin', markup);
        } else if (form) {
            form.insertAdjacentHTML('beforebegin', markup);
        } else {
            container.insertAdjacentHTML('beforeend', markup);
        }
    }

    setCommentsOpen(card, isOpen) {
        const section = card?.querySelector('.comment-section');
        if (!section) return;

        section.classList.toggle('open', isOpen);
        if (section.classList.contains('comment-panel')) {
            section.style.display = isOpen ? 'block' : 'none';
        } else {
            section.style.display = isOpen ? 'grid' : 'none';
        }

        if (isOpen) {
            section.querySelector('.comment-input')?.focus();
        }
    }

    renderCommentReactionSummary(summary, totalCount = 0) {
        if (!summary.length || !totalCount) {
            return '';
        }

        const top = summary.slice(0, 3).map((reaction) => {
            const meta = this.getReactionDisplayMeta(reaction.type);
            return `<span class="reaction-chip ${meta.class}" title="${meta.label}"><i class="fas fa-${meta.icon}"></i></span>`;
        }).join('');

        return `<span class="reaction-stack">${top}</span><span class="like-count">${totalCount}</span>`;
    }

    showCommentReactionPicker(button) {
        this.hideCommentReactionPicker();
        const picker = document.createElement('div');
        picker.className = 'comment-reaction-picker';
        picker.innerHTML = ['like', 'love', 'haha', 'wow', 'sad'].map((reaction) => {
            const meta = this.getReactionDisplayMeta(reaction);
            return `<button class="comment-reaction-option ${meta.class}" type="button" data-comment-id="${button.dataset.commentId}" data-reaction="${reaction}" title="${meta.label}">${meta.emoji}</button>`;
        }).join('');

        button.parentElement?.appendChild(picker);
        this.commentReactionPicker = picker;
    }

    hideCommentReactionPicker() {
        this.commentReactionPicker?.remove();
        this.commentReactionPicker = null;
    }

    async applyCommentReaction(option) {
        const formData = new FormData();
        formData.append('action', 'toggle_comment_reaction');
        formData.append('comment_id', option.dataset.commentId);
        formData.append('reaction', option.dataset.reaction);
        formData.append('csrf_token', this.findCsrfToken(option));

        try {
            const data = await this.post(formData);
            if (!data.success) {
                throw new Error(data.message || 'Unable to react to comment');
            }

            const commentCard = option.closest('.social-comment-card') || document.querySelector(`.social-comment-card[data-comment-id="${option.dataset.commentId}"]`);
            const reactButton = commentCard?.querySelector('.social-comment-react-btn');
            const summary = commentCard?.querySelector('[data-comment-reaction-summary]');
            const meta = data.viewer_reaction ? this.getReactionDisplayMeta(data.viewer_reaction) : null;

            if (reactButton) {
                reactButton.dataset.reaction = data.viewer_reaction || '';
                reactButton.innerHTML = `<i class="fas fa-${meta ? meta.icon : 'thumbs-up'}"></i> ${meta ? meta.label : 'Like'}`;
            }

            if (summary) {
                summary.innerHTML = this.renderCommentReactionSummary(data.reaction_summary || [], data.reaction_count || 0);
            }

            this.hideCommentReactionPicker();
        } catch (error) {
            this.notify(error.message, 'error');
        }
    }

    setCommentReplyTarget(button) {
        const form = button.closest('.comment-section')?.querySelector('.comment-form');
        if (!form) {
            return;
        }

        const replyInput = form.querySelector('[name="parent_comment_id"]');
        const replyBox = form.querySelector('.comment-replying-box');
        if (replyInput) {
            replyInput.value = button.dataset.commentId || '';
        }
        if (replyBox) {
            replyBox.hidden = false;
            replyBox.innerHTML = `
                <div class="comment-replying-copy">
                    <strong>Replying to ${this.escape(button.dataset.commentAuthor || 'User')}</strong>
                    <span>${this.escape(button.dataset.commentPreview || 'Comment')}</span>
                </div>
                <button class="comment-reply-cancel" type="button" aria-label="Cancel reply"><i class="fas fa-times"></i></button>
            `;
        }

        form.querySelector('.comment-input')?.focus();
    }

    clearCommentReplyTarget(form) {
        if (!form) {
            return;
        }

        const replyInput = form.querySelector('[name="parent_comment_id"]');
        const replyBox = form.querySelector('.comment-replying-box');
        if (replyInput) {
            replyInput.value = '';
        }
        if (replyBox) {
            replyBox.hidden = true;
            replyBox.innerHTML = '';
        }
    }

    getPostCard(origin) {
        if (!origin) return null;

        return origin.closest('.fb-post-card')
            || origin.closest('.post-card')
            || origin.closest('.community-post-card')
            || origin.closest('.home-post-card')
            || origin.parentElement?.closest('[data-post-id]')
            || null;
    }

    findCsrfToken(origin) {
        return origin.closest('form')?.querySelector('[name="csrf_token"]')?.value
            || document.querySelector('[data-profile-page]')?.dataset.csrfToken
            || document.querySelector('[data-community-page]')?.dataset.csrfToken
            || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            || document.querySelector('#loginForm [name="csrf_token"]')?.value
            || '';
    }

    async post(formData) {
        const response = await fetch(this.handlerUrl, {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            throw new Error(`Request failed with status ${response.status}`);
        }

        return response.json();
    }

    notify(message, type = 'success') {
        if (window.profileManager?.showNotification) {
            window.profileManager.showNotification(message, type);
            return;
        }

        if (window.HomePage?.showNotification) {
            window.HomePage.showNotification(message, type);
        }
    }

    escape(text) {
        return String(text).replace(/[&<>"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[char]));
    }

    formatPostText(text) {
        return this.escape(text).replace(/\n/g, '<br>');
    }

    getReactionDisplayMeta(reaction) {
        const map = {
            like: { label: 'Like', icon: 'thumbs-up', emoji: '&#128077;', class: 'reaction-like' },
            love: { label: 'Love', icon: 'heart', emoji: '&#10084;&#65039;', class: 'reaction-love' },
            care: { label: 'Care', icon: 'face-smile', emoji: '&#129392;', class: 'reaction-care' },
            haha: { label: 'Haha', icon: 'face-laugh-squint', emoji: '&#128518;', class: 'reaction-haha' },
            wow: { label: 'Wow', icon: 'face-surprise', emoji: '&#128558;', class: 'reaction-wow' },
            sad: { label: 'Sad', icon: 'face-sad-tear', emoji: '&#128546;', class: 'reaction-sad' },
            angry: { label: 'Angry', icon: 'face-angry', emoji: '&#128545;', class: 'reaction-angry' }
        };

        return map[reaction] || map.like;
    }

    getReactionMeta(reaction) {
        const map = {
            like: { label: 'Like', emoji: '👍', class: 'reaction-like' },
            love: { label: 'Love', emoji: '❤️', class: 'reaction-love' },
            haha: { label: 'Haha', emoji: '😆', class: 'reaction-haha' },
            wow: { label: 'Wow', emoji: '😮', class: 'reaction-wow' },
            sad: { label: 'Sad', emoji: '😢', class: 'reaction-sad' }
        };

        return map[reaction] || map.like;
    }
    refreshRelativeTimes(scope = document) {
        scope.querySelectorAll?.('.js-social-relative-time[data-time]').forEach((node) => {
            node.textContent = this.formatRelativeTime(node.dataset.time || '');
        });
    }

    formatRelativeTime(value) {
        if (!value) {
            return 'just now';
        }

        const date = /^\d+$/.test(String(value))
            ? new Date(Number(value) * 1000)
            : new Date(String(value).replace(' ', 'T'));

        if (Number.isNaN(date.getTime())) {
            return String(value);
        }

        const diffSeconds = Math.max(0, Math.floor((Date.now() - date.getTime()) / 1000));
        if (diffSeconds < 15) {
            return 'just now';
        }
        if (diffSeconds < 60) {
            return `${diffSeconds}s ago`;
        }
        if (diffSeconds < 3600) {
            return `${Math.floor(diffSeconds / 60)}m ago`;
        }
        if (diffSeconds < 86400) {
            return `${Math.floor(diffSeconds / 3600)}h ago`;
        }

        const days = Math.floor(diffSeconds / 86400);
        if (days <= 3) {
            return `${days}d ago`;
        }

        return date.toLocaleDateString(undefined, {
            month: 'short',
            day: 'numeric',
            year: date.getFullYear() !== new Date().getFullYear() ? 'numeric' : undefined
        });
    }
}
}

document.addEventListener('DOMContentLoaded', () => {
    window.socialFeed = new window.SocialFeed();
});
