<!-- Edit Post Modal -->
<div class="edit-post-overlay" id="editPostOverlay">
    <div class="edit-post-modal">
        <div class="edit-post-header">
            <h2>Edit Post</h2>
            <button type="button" class="edit-post-close" id="editPostClose"><i class="fas fa-times"></i></button>
        </div>
        <div class="edit-post-body">
            <div class="edit-post-user">
                <img src="assets/avatars/<?= htmlspecialchars($_SESSION['avatar'] ?? 'default.png') ?>" alt="" onerror="this.src='assets/avatars/default.png'">
                <div>
                    <strong><?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User') ?></strong>
                    <span class="edit-post-privacy"><i class="fas fa-globe-americas"></i> Public</span>
                </div>
            </div>
            <textarea class="edit-post-textarea" id="editPostTextarea" placeholder="What's on your mind?" maxlength="5000"></textarea>
            <div class="edit-post-photo-preview" id="editPostPhotoPreview" style="display:none">
                <img id="editPostPhotoImg" src="" alt="">
                <div class="edit-post-photo-actions">
                    <button type="button" class="edit-post-photo-change" id="editPostPhotoChange"><i class="fas fa-sync-alt"></i> Change</button>
                    <button type="button" class="edit-post-photo-remove" id="editPostPhotoRemove"><i class="fas fa-trash-alt"></i> Remove</button>
                </div>
            </div>
            <div class="edit-post-feeling-bar" id="editPostFeelingBar" style="display:none">
                <i class="fas fa-face-smile"></i> Feeling <strong id="editPostFeelingText"></strong>
                <button type="button" class="edit-post-feeling-clear" id="editPostFeelingClear"><i class="fas fa-times"></i></button>
            </div>
        </div>
        <div class="edit-post-footer">
            <div class="edit-post-footer-left">
                <span class="edit-post-footer-label">Add to your post</span>
                <div class="edit-post-footer-icons">
                    <button type="button" class="edit-post-icon-btn" id="editPostPhotoBtn" title="Photo"><i class="fas fa-image" style="color:#45bd62"></i></button>
                    <button type="button" class="edit-post-icon-btn" id="editPostFeelingBtn" title="Feeling"><i class="fas fa-face-smile" style="color:#f7b928"></i></button>
                </div>
            </div>
            <button type="button" class="edit-post-submit" id="editPostSubmit">Save</button>
        </div>
        <div class="edit-post-feeling-picker" id="editPostFeelingPicker">
            <div class="feeling-picker-header">How are you feeling?</div>
            <div class="feeling-picker-grid">
                <button class="feeling-option" data-feeling="Happy"><span class="feeling-emoji">😊</span><span class="feeling-label">Happy</span></button>
                <button class="feeling-option" data-feeling="Sad"><span class="feeling-emoji">😢</span><span class="feeling-label">Sad</span></button>
                <button class="feeling-option" data-feeling="Excited"><span class="feeling-emoji">🎉</span><span class="feeling-label">Excited</span></button>
                <button class="feeling-option" data-feeling="Loved"><span class="feeling-emoji">❤️</span><span class="feeling-label">Loved</span></button>
                <button class="feeling-option" data-feeling="Grateful"><span class="feeling-emoji">🙏</span><span class="feeling-label">Grateful</span></button>
                <button class="feeling-option" data-feeling="Blessed"><span class="feeling-emoji">✨</span><span class="feeling-label">Blessed</span></button>
                <button class="feeling-option" data-feeling="Angry"><span class="feeling-emoji">😡</span><span class="feeling-label">Angry</span></button>
                <button class="feeling-option" data-feeling="Silly"><span class="feeling-emoji">🤪</span><span class="feeling-label">Silly</span></button>
                <button class="feeling-option" data-feeling="Tired"><span class="feeling-emoji">😴</span><span class="feeling-label">Tired</span></button>
                <button class="feeling-option" data-feeling="Cool"><span class="feeling-emoji">😎</span><span class="feeling-label">Cool</span></button>
            </div>
        </div>
        <input type="file" id="editPostPhotoInput" accept="image/png,image/jpeg,image/gif,image/webp" hidden>
    </div>
</div>

<!-- Report Post Modal -->
<div class="report-post-overlay" id="reportPostOverlay">
    <div class="report-post-modal">
        <div class="report-post-header">
            <h2>Report Post</h2>
            <button type="button" class="report-post-close" id="reportPostClose"><i class="fas fa-times"></i></button>
        </div>
        <div class="report-post-body">
            <p class="report-post-desc">Why are you reporting this post? Your report is anonymous.</p>
            <div class="report-reason-list" id="reportReasonList">
                <label class="report-reason-option">
                    <input type="radio" name="report_reason" value="Spam">
                    <span class="report-reason-radio"></span>
                    <div class="report-reason-text">
                        <strong>Spam</strong>
                        <span>Misleading or repetitive content</span>
                    </div>
                </label>
                <label class="report-reason-option">
                    <input type="radio" name="report_reason" value="Nudity or sexual activity">
                    <span class="report-reason-radio"></span>
                    <div class="report-reason-text">
                        <strong>Nudity or sexual activity</strong>
                        <span>Contains explicit sexual content</span>
                    </div>
                </label>
                <label class="report-reason-option">
                    <input type="radio" name="report_reason" value="Hate speech or harassment">
                    <span class="report-reason-radio"></span>
                    <div class="report-reason-text">
                        <strong>Hate speech or harassment</strong>
                        <span>Promotes violence or targets individuals</span>
                    </div>
                </label>
                <label class="report-reason-option">
                    <input type="radio" name="report_reason" value="False information">
                    <span class="report-reason-radio"></span>
                    <div class="report-reason-text">
                        <strong>False information</strong>
                        <span>Contains misleading or false claims</span>
                    </div>
                </label>
                <label class="report-reason-option">
                    <input type="radio" name="report_reason" value="Violence or dangerous organizations">
                    <span class="report-reason-radio"></span>
                    <div class="report-reason-text">
                        <strong>Violence or dangerous organizations</strong>
                        <span>Threatens others or promotes terrorism</span>
                    </div>
                </label>
                <label class="report-reason-option">
                    <input type="radio" name="report_reason" value="Intellectual property violation">
                    <span class="report-reason-radio"></span>
                    <div class="report-reason-text">
                        <strong>Intellectual property violation</strong>
                        <span>Violates copyright or trademark</span>
                    </div>
                </label>
            </div>
            <div class="report-additional">
                <textarea class="report-detail-input" id="reportDetailInput" rows="3" placeholder="Provide additional details (optional)"></textarea>
            </div>
        </div>
        <div class="report-post-footer">
            <button type="button" class="report-cancel-btn" id="reportCancelBtn">Cancel</button>
            <button type="button" class="report-submit-btn" id="reportSubmitBtn" disabled>Submit Report</button>
        </div>
    </div>
</div>

<!-- Reusable Status Modal -->
<div class="gp-success-overlay" id="communityStatusOverlay">
    <div class="gp-success-box" id="communityStatusBox">
        <div class="gp-success-icon" id="communityStatusIcon"><i class="fas fa-check"></i></div>
        <div class="gp-success-text" id="communityStatusTitle">Success!</div>
        <div class="gp-success-sub" id="communityStatusSub">Action completed successfully.</div>
        <button class="gp-result-dismiss" id="communityStatusDismiss">Got it</button>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="gp-success-overlay" id="communityDeleteOverlay">
    <div class="gp-success-box" style="text-align:center;padding:2rem 1.8rem 1.8rem">
        <div class="community-delete-icon"><i class="fas fa-trash-alt"></i></div>
        <div class="gp-success-text" style="font-size:20px">Delete Post?</div>
        <div class="gp-success-sub" style="margin-bottom:24px;font-size:14px">This action cannot be undone. Are you sure you want to delete this post permanently?</div>
        <div class="community-delete-actions">
            <button class="community-delete-btn community-delete-cancel" id="communityDeleteCancel">Cancel</button>
            <button class="community-delete-btn community-delete-confirm" id="communityDeleteConfirm"><i class="fas fa-trash-alt"></i> Delete</button>
        </div>
    </div>
</div>
