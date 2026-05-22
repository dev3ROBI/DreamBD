<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';

$viewerId = $_SESSION['user_id'] ?? null;
$db = Database::getInstance()->getConnection();
ensureSocialTables($db);

$communityPosts = getVisibleFeedPosts($db, $viewerId, 50, 'community');
$communityOverview = getCommunityOverview($db, $viewerId);
$communitySecurity = new Security();
$communityCsrfToken = $communitySecurity->generateCSRFToken();
$communityUserDisplayName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Guest';
$communityReactionOptions = [
    ['type' => 'like', 'label' => 'Like', 'emoji' => '👍'],
    ['type' => 'love', 'label' => 'Love', 'emoji' => '❤️'],
    ['type' => 'care', 'label' => 'Care', 'emoji' => '🥰'],
    ['type' => 'haha', 'label' => 'Haha', 'emoji' => '😆'],
    ['type' => 'wow', 'label' => 'Wow', 'emoji' => '😮'],
    ['type' => 'sad', 'label' => 'Sad', 'emoji' => '😢'],
    ['type' => 'angry', 'label' => 'Angry', 'emoji' => '😡'],
];
$communityLeftLinks = [
    ['icon' => 'fa-house', 'label' => 'Home feed', 'href' => 'index.php?page=home', 'page' => 'home', 'tone' => 'blue'],
    ['icon' => 'fa-user-group', 'label' => 'Friends', 'href' => 'index.php?page=profile#friends', 'page' => 'profile', 'tone' => 'cyan'],
    ['icon' => 'fa-clock-rotate-left', 'label' => 'Memories', 'href' => 'index.php?page=profile', 'page' => 'profile', 'tone' => 'sky'],
    ['icon' => 'fa-bookmark', 'label' => 'Saved', 'href' => 'index.php?page=profile', 'page' => 'profile', 'tone' => 'purple'],
    ['icon' => 'fa-store', 'label' => 'Marketplace', 'href' => 'index.php?page=products', 'page' => 'products', 'tone' => 'teal'],
    ['icon' => 'fa-trophy', 'label' => 'Tournaments', 'href' => 'index.php?page=tournaments', 'page' => 'tournaments', 'tone' => 'orange'],
];

$communityUserRole = $_SESSION['role'] ?? 'user';
if (in_array($communityUserRole, ['admin', 'moderator', 'super_admin'], true)) {
    $communityLeftLinks[] = ['icon' => 'fa-shield-alt', 'label' => 'Admin Panel', 'href' => 'admin/post-reports.php', 'page' => 'admin', 'tone' => 'red'];
}
$communityShortcuts = [
    ['title' => 'Clash of Fans BD', 'image' => 'assets/images/apps/app1.jpg'],
    ['title' => 'Neonix Music', 'image' => 'assets/images/apps/app2.jpg'],
    ['title' => 'HTML, CSS, JavaScript, PHP', 'image' => 'assets/images/apps/app3.jpg'],
];
$communityStories = [
    ['name' => 'Create story', 'image' => 'assets/avatars/' . ($_SESSION['avatar'] ?? 'default.png'), 'create' => true],
    ['name' => 'Mahbub Hasan', 'image' => 'assets/images/slider/slide1.jpg', 'avatar' => 'default.png'],
    ['name' => 'MD Tauhid Islam', 'image' => 'assets/images/slider/slide2.jpg', 'avatar' => 'default.png'],
    ['name' => 'Rejoan Kobir', 'image' => 'assets/images/slider/slide3.jpg', 'avatar' => 'default.png'],
    ['name' => 'Cell Tech BD', 'image' => 'assets/images/apps/app1.jpg', 'avatar' => 'default.png'],
];
$friendRequests = [];
if ($viewerId) {
    try {
        $friendRequests = getFriendRequests($db, $viewerId, 5);
    } catch (Throwable $e) { $friendRequests = []; }
}
?>

<script src="assets/js/community.js?v=<?php echo time(); ?>" defer></script>
<div class="community-page" data-community-page data-csrf-token="<?php echo htmlspecialchars($communityCsrfToken); ?>" data-viewer-id="<?= (int)($viewerId ?? 0) ?>">
    <section class="community-layout">
        <!-- Sidebar Left -->
        <aside class="community-sidebar">
            <div class="community-panel community-profile-panel">
                <div class="community-left-user">
                    <img src="assets/avatars/<?php echo htmlspecialchars($_SESSION['avatar'] ?? 'default.png'); ?>" alt="" onerror="this.src='assets/avatars/default.png'">
                    <div>
                        <strong><?php echo htmlspecialchars($communityUserDisplayName); ?></strong>
                        <span>View profile</span>
                    </div>
                </div>
                <div class="community-left-menu">
                    <?php foreach ($communityLeftLinks as $leftLink): ?>
                        <a href="<?php echo htmlspecialchars($leftLink['href']); ?>" data-page="<?php echo htmlspecialchars($leftLink['page']); ?>" class="community-left-link" data-tone="<?php echo htmlspecialchars($leftLink['tone'] ?? 'blue'); ?>">
                            <i class="fas <?php echo htmlspecialchars($leftLink['icon']); ?>"></i>
                            <span><?php echo htmlspecialchars($leftLink['label']); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="community-panel">
                <div class="community-panel-header"><h3>Your shortcuts</h3></div>
                <div class="community-shortcut-list">
                    <?php foreach ($communityShortcuts as $shortcut): ?>
                        <a href="index.php?page=community" data-page="community" class="community-left-link">
                            <img src="<?php echo htmlspecialchars($shortcut['image']); ?>" alt="" style="width:24px;height:24px;border-radius:6px;object-fit:cover">
                            <span><?php echo htmlspecialchars($shortcut['title']); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </aside>

        <!-- Feed Center -->
        <div class="community-feed">
            <!-- Stories Strip -->
            <div class="community-stories-strip">
                <?php foreach ($communityStories as $story): ?>
                <div class="community-story-card <?= isset($story['create']) ? 'create-story' : '' ?>">
                    <?php if (isset($story['create'])): ?>
                        <div class="community-story-bg-wrap">
                            <img src="<?= htmlspecialchars($story['image']) ?>" alt="" class="community-story-bg" onerror="this.src='assets/avatars/default.png'">
                        </div>
                        <div class="community-story-avatar"><i class="fas fa-plus"></i></div>
                        <div class="community-story-name">Create story</div>
                    <?php else: ?>
                        <img src="<?= htmlspecialchars($story['image']) ?>" alt="" class="community-story-bg" onerror="this.src='assets/avatars/default.png'">
                        <div class="community-story-overlay"></div>
                        <img src="assets/avatars/<?= htmlspecialchars($story['avatar'] ?? 'default.png') ?>" alt="" class="community-story-avatar" onerror="this.src='assets/avatars/default.png'">
                        <div class="community-story-name"><?= htmlspecialchars($story['name']) ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Composer Card (trigger) -->
            <section class="community-composer-card" id="composerCard">
                <div class="community-composer-top">
                    <img src="assets/avatars/<?= htmlspecialchars($_SESSION['avatar'] ?? 'default.png') ?>" alt="" onerror="this.src='assets/avatars/default.png'">
                    <button type="button" class="composer-trigger-btn" id="composerTriggerBtn">What's on your mind, <?= htmlspecialchars(explode(' ', trim($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Friend'))[0]) ?>?</button>
                </div>
                <div class="community-composer-actions">
                    <button type="button" class="composer-action-btn" id="composerTriggerPhoto"><i class="fas fa-image" style="color:#45bd62"></i> Photo</button>
                    <button type="button" class="composer-action-btn" id="composerTriggerFeeling"><i class="fas fa-face-smile" style="color:#f7b928"></i> Feeling</button>
                </div>
            </section>
            
            <!-- Create Post Modal (Facebook-style) -->
            <div class="create-post-overlay" id="createPostOverlay">
                <div class="create-post-modal">
                    <div class="create-post-header">
                        <h2>Create Post</h2>
                        <button type="button" class="create-post-close" id="createPostClose"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="create-post-body">
                        <div class="create-post-user">
                            <img src="assets/avatars/<?= htmlspecialchars($_SESSION['avatar'] ?? 'default.png') ?>" alt="" onerror="this.src='assets/avatars/default.png'">
                            <div>
                                <strong><?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User') ?></strong>
                                <span class="create-post-privacy">
                                    <i class="fas fa-globe-americas"></i> Public
                                </span>
                            </div>
                        </div>
                        <textarea class="create-post-textarea" id="createPostTextarea" placeholder="What's on your mind, <?= htmlspecialchars(explode(' ', trim($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Friend'))[0]) ?>?" maxlength="5000"></textarea>
                        <div class="create-post-photo-preview" id="createPostPhotoPreview" style="display:none">
                            <img id="createPostPhotoImg" src="" alt="">
                            <button type="button" class="create-post-photo-remove" id="createPostPhotoRemove"><i class="fas fa-times"></i></button>
                        </div>
                        <div class="create-post-feeling-bar" id="createPostFeelingBar" style="display:none">
                            <i class="fas fa-face-smile"></i> Feeling <strong id="createPostFeelingText"></strong>
                            <button type="button" class="create-post-feeling-clear" id="createPostFeelingClear"><i class="fas fa-times"></i></button>
                        </div>
                    </div>
                    <div class="create-post-footer">
                        <div class="create-post-footer-left">
                            <span class="create-post-footer-label">Add to your post</span>
                            <div class="create-post-footer-icons">
                                <button type="button" class="create-post-icon-btn" id="createPostPhotoBtn" title="Photo"><i class="fas fa-image" style="color:#45bd62"></i></button>
                                <button type="button" class="create-post-icon-btn" id="createPostFeelingBtn" title="Feeling"><i class="fas fa-face-smile" style="color:#f7b928"></i></button>
                            </div>
                        </div>
                        <button type="button" class="create-post-submit" id="createPostSubmit" disabled>Post</button>
                    </div>
                    <!-- Feeling picker (in modal) -->
                    <div class="create-post-feeling-picker" id="createPostFeelingPicker">
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
                    <input type="file" id="createPostPhotoInput" accept="image/png,image/jpeg,image/gif,image/webp" hidden>
                </div>
            </div>

            <?php include __DIR__ . '/../includes/post-modals.php'; ?>
            
            <!-- Feed Posts -->
            <?php foreach ($communityPosts as $post): ?>
            <article class="community-post-card" data-post-id="<?= (int) $post['id'] ?>">
                <div class="community-post-header">
                    <div class="community-post-author">
                        <img src="assets/avatars/<?= htmlspecialchars($post['avatar'] ?? 'default.png') ?>" alt="" onerror="this.src='assets/avatars/default.png'">
                        <div class="community-author-info">
                            <strong><?= htmlspecialchars($post['full_name'] ?: $post['username']) ?></strong>
                            <span class="community-post-time"><?= formatTimeAgo($post['created_at']) ?></span>
                            <?php if (!empty($post['feeling'])): ?>
                            <span style="font-size:12px;color:var(--comm-text-secondary);display:block;margin-top:2px">feeling <strong><?= htmlspecialchars($post['feeling']) ?></strong></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="post-menu-container">
                        <button type="button" class="post-menu-trigger" title="More options"><i class="fas fa-ellipsis-h"></i></button>
                        <div class="post-dropdown">
                            <?php if ((int)$post['user_id'] === (int)$viewerId): ?>
                            <button class="post-dropdown-item" data-action="edit" data-post-id="<?= (int)$post['id'] ?>"><i class="fas fa-pen"></i> Edit post</button>
                            <button class="post-dropdown-item danger" data-action="delete" data-post-id="<?= (int)$post['id'] ?>"><i class="fas fa-trash-alt"></i> Delete post</button>
                            <?php else: ?>
                            <button class="post-dropdown-item" data-action="save" data-post-id="<?= (int)$post['id'] ?>"><i class="fas fa-bookmark"></i> Save post</button>
                            <div class="post-dropdown-divider"></div>
                            <button class="post-dropdown-item danger" data-action="report" data-post-id="<?= (int)$post['id'] ?>"><i class="fas fa-flag"></i> Report post</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="community-post-content">
                    <p><?= nl2br(htmlspecialchars($post['content'])) ?></p>
                </div>
                <?php if (!empty($post['image_path'])): ?>
                    <div class="community-post-image">
                        <img src="assets/posts/<?= htmlspecialchars($post['image_path']) ?>" alt="" loading="lazy">
                    </div>
                <?php endif; ?>
                
                <div class="community-post-stats">
                    <div class="community-stat-left" title="View reactions">
                        <div class="community-reaction-icons">
                            <span class="rxn-like"><i class="fas fa-thumbs-up"></i></span>
                            <span class="rxn-love"><i class="fas fa-heart"></i></span>
                        </div>
                        <span class="community-stat-count"><?= (int) $post['like_count'] ?></span>
                    </div>
                    <div class="community-stat-right">
                        <span class="community-comment-trigger" data-post-id="<?= (int) $post['id'] ?>"><?= (int) $post['comment_count'] ?> comments</span> • 
                        <span><?= (int) $post['share_count'] ?> shares</span>
                    </div>
                </div>

                <div class="community-post-actions">
                    <div class="community-btn-action-wrap">
                        <button class="community-btn-action <?= ($post['viewer_reaction'] ?? null) ? 'active' : '' ?>" data-action="like" data-reaction="<?= htmlspecialchars($post['viewer_reaction'] ?? '') ?>">
                            <?php if ($post['viewer_reaction']): ?>
                                <span style="margin-right:8px"><?= getEmoji($post['viewer_reaction']) ?></span> <?= ucfirst($post['viewer_reaction']) ?>
                            <?php else: ?>
                                <i class="far fa-thumbs-up"></i> Like
                            <?php endif; ?>
                        </button>
                        <div class="community-reaction-strip">
                            <?php foreach ($communityReactionOptions as $rxn): ?>
                                <button class="community-reaction-btn" data-reaction="<?= htmlspecialchars($rxn['type']) ?>" title="<?= htmlspecialchars($rxn['label']) ?>"><?= $rxn['emoji'] ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="community-btn-action-wrap">
                        <button class="community-btn-action community-comment-trigger" data-action="comment" data-post-id="<?= (int) $post['id'] ?>"><i class="far fa-comment"></i> Comment</button>
                    </div>
                    <div class="community-btn-action-wrap">
                        <button class="community-btn-action" data-action="share"><i class="far fa-share-square"></i> Share</button>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>

        <!-- Right Sidebar -->
        <aside class="community-right-sidebar">
            <div class="community-panel">
                <div class="community-side-heading">
                    <h3>Friend requests</h3>
                    <a href="#" style="font-size:14px; text-decoration:none; color:var(--comm-primary)">See all</a>
                </div>
                <div class="community-friend-list">
                    <?php if (empty($friendRequests)): ?>
                        <p style="font-size:14px; color:var(--comm-text-secondary); text-align:center; padding:10px">No new requests</p>
                    <?php endif; ?>
                    <?php foreach ($friendRequests as $request): ?>
                    <article class="community-request-card">
                        <img src="assets/avatars/<?php echo htmlspecialchars($request['avatar'] ?? 'default.png'); ?>" alt="" onerror="this.src='assets/avatars/default.png'">
                        <div class="community-request-info">
                            <strong><?php echo htmlspecialchars($request['full_name'] ?: $request['username']); ?></strong>
                            <div class="community-request-actions">
                                <button class="btn-sm btn-primary">Confirm</button>
                                <button class="btn-sm btn-outline">Delete</button>
                            </div>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="community-panel">
                <div class="community-side-heading"><h3>Contacts</h3></div>
                <div class="community-friend-list" id="community-contacts-list">
                    <!-- Loaded via JS -->
                    <div style="padding:10px; color:var(--comm-text-secondary); font-size:14px">Loading contacts...</div>
                </div>
            </div>
        </aside>

    </section>
</div>


