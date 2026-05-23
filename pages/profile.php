<?php
require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

$viewerId = $_SESSION['user_id'] ?? null;

try {
    $db = Database::getInstance()->getConnection();
    ensureSocialTables($db);

    $profileId = isset($_GET['user']) ? (int) $_GET['user'] : $viewerId;
    $stmt = $db->prepare("
        SELECT u.*,
               EXISTS(SELECT 1 FROM user_sessions us_online WHERE us_online.user_id = u.id AND us_online.expires_at > NOW() AND us_online.last_activity >= (UNIX_TIMESTAMP() - 300)) AS is_online,
               (SELECT MAX(us_last.last_activity) FROM user_sessions us_last WHERE us_last.user_id = u.id) AS last_active_at,
               (SELECT COUNT(*) FROM orders WHERE user_id = u.id) AS order_count,
               (SELECT COUNT(*) FROM tournament_participants WHERE user_id = u.id) AS tournament_count,
               (SELECT COUNT(*) FROM user_sessions WHERE user_id = u.id AND expires_at > NOW()) AS session_count
        FROM users u WHERE u.id = ? LIMIT 1
    ");
    $stmt->execute([$profileId]);
    $profileUser = $stmt->fetch();
    if (!$profileUser) throw new Exception('User not found');

    $stats = getProfileStats($db, $profileId);
    $posts = getProfilePosts($db, $profileId, $viewerId, 10);
    $friends = getFriendsList($db, $profileId, 24);
    $friendRequests = $profileId === $viewerId ? getFriendRequests($db, $viewerId, 12) : [];
    // Get user photos
    $userPhotos = [];
    try {
        $stmt = $db->prepare("SELECT image_path, created_at FROM posts WHERE user_id = ? AND image_path IS NOT NULL AND image_path != '' ORDER BY created_at DESC LIMIT 20");
        $stmt->execute([$profileId]);
        $userPhotos = $stmt->fetchAll();
    } catch (Throwable $e) {}

    $suggestedFriends = $profileId === $viewerId ? getSuggestedFriends($db, $viewerId, 12) : [];
    $preferences = !empty($profileUser['preferences']) ? json_decode($profileUser['preferences'], true) : [];
    $friendshipStatus = getFriendshipStatus($db, $viewerId, $profileId);
    $isOwnProfile = $profileId === $viewerId;

    try {
        $db->prepare("DELETE FROM user_sessions WHERE user_id = ? AND expires_at <= NOW()")->execute([$viewerId]);
        $sessionsStmt = $db->prepare("SELECT * FROM user_sessions WHERE user_id = ? AND expires_at > NOW() ORDER BY last_activity DESC LIMIT 12");
        $sessionsStmt->execute([$viewerId]);
        $activeSessions = $sessionsStmt->fetchAll();
    } catch (Exception $e) { $activeSessions = []; }
} catch (Exception $e) {
    echo '<div class="p-8 text-center text-red-500">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    return;
}

$homeReactionOptions = [
    ['type' => 'like', 'label' => 'Like', 'emoji' => '👍'],
    ['type' => 'love', 'label' => 'Love', 'emoji' => '❤️'],
    ['type' => 'care', 'label' => 'Care', 'emoji' => '🥰'],
    ['type' => 'haha', 'label' => 'Haha', 'emoji' => '😆'],
    ['type' => 'wow', 'label' => 'Wow', 'emoji' => '😮'],
    ['type' => 'sad', 'label' => 'Sad', 'emoji' => '😢'],
    ['type' => 'angry', 'label' => 'Angry', 'emoji' => '😡'],
];

$security = new Security();
$csrfToken = $security->generateCSRFToken();
$displayName = $profileUser['full_name'] ?: $profileUser['username'];
$isOnline = !empty($profileUser['is_online']);
$presenceLabel = $isOnline ? 'Active' : 'Offline';
$joinedDate = $profileUser['registered_at'] ?? $profileUser['created_at'] ?? 'now';
$aboutHighlights = [
    [
        'icon' => 'fa-location-dot',
        'label' => 'Location',
        'value' => $profileUser['location'] ?: 'Location not added',
        'accent' => 'emerald'
    ],
    [
        'icon' => 'fa-briefcase',
        'label' => 'Role',
        'value' => ucfirst($profileUser['role'] ?? 'Member'),
        'accent' => 'blue'
    ],
    [
        'icon' => 'fa-link',
        'label' => 'Website',
        'value' => $profileUser['website'] ?: 'Website not added',
        'accent' => 'violet',
        'url' => $profileUser['website'] ?? ''
    ],
    [
        'icon' => 'fa-calendar-days',
        'label' => 'Joined',
        'value' => date('F j, Y', strtotime($joinedDate)),
        'accent' => 'amber'
    ],
    [
        'icon' => 'fa-shield-heart',
        'label' => 'Account',
        'value' => !empty($profileUser['email_verified']) ? 'Verified member' : 'Email not verified',
        'accent' => 'rose'
    ],
    [
        'icon' => 'fa-phone',
        'label' => 'Phone',
        'value' => $profileUser['phone'] ?: 'Phone not added',
        'accent' => 'cyan'
    ],
];

function renderProfileActionButtons($isOwnProfile, $friendshipStatus, $profileId) {
    if ($isOwnProfile) {
        echo '<button class="btn btn-primary profile-story-btn" type="button"><i class="fas fa-circle-plus"></i> Add to story</button>';
        echo '<button class="btn btn-outline" data-open-profile-settings><i class="fas fa-pen"></i> Edit profile</button>';
        return;
    }
    if ($friendshipStatus === 'friends') {
        echo '<button class="btn btn-primary friend-toggle-btn" type="button" data-action="remove_friend" data-target-user-id="' . $profileId . '"><i class="fas fa-user-check"></i> Friends</button>';
        echo '<a class="btn btn-outline" href="index.php?page=messages&user=' . $profileId . '" data-no-ajax><i class="fas fa-comment-dots"></i> Message</a>';
        return;
    } elseif ($friendshipStatus === 'request_sent') {
        echo '<button class="btn btn-outline" type="button" disabled><i class="fas fa-paper-plane"></i> Request Sent</button>';
        echo '<a class="btn btn-outline" href="index.php?page=messages&user=' . $profileId . '" data-no-ajax><i class="fas fa-comment-dots"></i> Message</a>';
        return;
    } elseif ($friendshipStatus === 'request_received') {
        echo '<button class="btn btn-primary friend-response-btn" type="button" data-request-user-id="' . $profileId . '" data-decision="accept"><i class="fas fa-user-check"></i> Friends</button>';
        echo '<a class="btn btn-outline" href="index.php?page=messages&user=' . $profileId . '" data-no-ajax><i class="fas fa-comment-dots"></i> Message</a>';
        return;
    }
    echo '<button class="btn btn-primary friend-toggle-btn" type="button" data-action="send_friend_request" data-target-user-id="' . $profileId . '"><i class="fas fa-user-plus"></i> Add Friend</button>';
    echo '<a class="btn btn-outline" href="index.php?page=messages&user=' . $profileId . '" data-no-ajax><i class="fas fa-comment-dots"></i> Message</a>';
}
?>
<div class="profile-shell profile-shell--fb" data-profile-page data-csrf-token="<?php echo htmlspecialchars($csrfToken); ?>" data-viewer-id="<?= (int)($viewerId ?? 0) ?>">

    <!-- ===== HERO ===== -->
    <section class="profile-hero">
        <div class="profile-hero-shapes" aria-hidden="true">
            <div class="profile-shape profile-shape--1"></div>
            <div class="profile-shape profile-shape--2"></div>
            <div class="profile-shape profile-shape--3"></div>
        </div>
        <div class="profile-cover">
            <img src="assets/covers/<?php echo htmlspecialchars($profileUser['cover_image'] ?? 'default.jpg'); ?>" alt="" class="profile-cover-img" onerror="this.src='assets/covers/default.jpg'">
            <?php if ($isOwnProfile): ?>
            <div class="cover-actions"><button class="btn btn-outline cover-btn" type="button" data-open-cover-upload><i class="fas fa-camera"></i> Edit cover</button><input type="file" id="coverFile" accept="image/*" hidden></div>
            <?php endif; ?>
            <div class="profile-cover-overlay"></div>
        </div>
        <div class="profile-header">
            <div class="profile-avatar-container">
                <div class="profile-avatar-ring">
                    <img src="assets/avatars/<?php echo htmlspecialchars($profileUser['avatar'] ?? 'default.png'); ?>" alt="" class="profile-avatar" id="profileAvatar" onerror="this.src='assets/avatars/default.png'">
                </div>
                <?php if ($isOwnProfile): ?>
                <button class="avatar-edit-btn" id="editAvatarBtn" type="button" aria-label="Change profile picture"><i class="fas fa-camera"></i></button>
                <?php endif; ?>
            </div>
            <div class="profile-intro">
                <div class="profile-name-row">
                    <div class="profile-identity">
                        <h1 class="profile-name"><span class="profile-name-text"><?php echo htmlspecialchars($displayName); ?></span><span class="profile-presence-pill <?php echo $isOnline ? 'is-online' : 'is-offline'; ?>"><?php echo renderPresenceDot($isOnline); ?><?php echo htmlspecialchars($presenceLabel); ?></span></h1>
                        <div class="profile-social-stats">
                            <span><strong class="stat-counter" data-count="<?php echo $stats['friends']; ?>">0</strong> friends</span>
                            <span><strong class="stat-counter" data-count="<?php echo $stats['posts']; ?>">0</strong> posts</span>
                        </div>
                    </div>
                </div>
                <p class="profile-bio" id="profileBioText"><?php echo htmlspecialchars($profileUser['bio'] ?? 'Share a short intro so people know what you are into.'); ?></p>
                <div class="profile-about-lines" aria-label="Profile details">
                    <?php foreach (array_slice($aboutHighlights, 0, 4) as $item): ?>
                    <div class="profile-about-line profile-about-line--<?php echo htmlspecialchars($item['accent']); ?>">
                        <i class="fas <?php echo htmlspecialchars($item['icon']); ?>"></i>
                        <span><?php echo htmlspecialchars($item['value']); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="profile-actions"><?php renderProfileActionButtons($isOwnProfile, $friendshipStatus, $profileId); ?></div>
            </div>
        </div>
    </section>

    <!-- ===== TABS ===== -->
    <nav class="profile-nav" aria-label="Profile sections">
        <button class="nav-item active" data-profile-tab data-tab="timeline"><i class="fas fa-rss"></i> Posts</button>
        <button class="nav-item" data-profile-tab data-tab="about"><i class="fas fa-circle-info"></i> About</button>
        <button class="nav-item" data-profile-tab data-tab="friends"><i class="fas fa-user-group"></i> Friends <span class="nav-badge"><?php echo $stats['friends']; ?></span></button>
        <button class="nav-item" data-profile-tab data-tab="photos"><i class="fas fa-images"></i> Photos</button>
    </nav>

    <!-- ===== CONTENT ===== -->
    <div class="profile-content">
        <!-- Sidebar Left -->
        <aside class="profile-sidebar">
            <div class="profile-card sidebar-intro">
                <div class="card-heading"><h3>Intro</h3><?php if ($isOwnProfile): ?><button class="btn btn-sm btn-outline" data-open-profile-settings><i class="fas fa-pen"></i></button><?php endif; ?></div>
                <?php if (!empty($profileUser['bio'])): ?><div class="intro-item"><i class="fas fa-quote-left" style="color:var(--primary)"></i><span><?php echo htmlspecialchars($profileUser['bio']); ?></span></div><?php endif; ?>
                <?php if (!empty($profileUser['location'])): ?><div class="intro-item"><i class="fas fa-house" style="color:#10b981"></i><span>Lives in <?php echo htmlspecialchars($profileUser['location']); ?></span></div><?php endif; ?>
                <?php if (!empty($profileUser['website'])): ?><div class="intro-item"><i class="fas fa-link" style="color:#3b82f6"></i><a href="<?php echo htmlspecialchars($profileUser['website']); ?>" target="_blank"><?php echo htmlspecialchars($profileUser['website']); ?></a></div><?php endif; ?>
                <div class="intro-item"><i class="fas fa-calendar" style="color:#f59e0b"></i><span>Joined <?php echo date('F Y', strtotime($profileUser['registered_at'] ?? 'now')); ?></span></div>
                <div class="intro-item"><i class="fas fa-shield-heart" style="color:#ec4899"></i><span><?php echo !empty($profileUser['email_verified']) ? 'Verified member' : 'Email not verified'; ?></span></div>
            </div>

            <?php if ($friendRequests): ?>
            <div class="profile-card sidebar-requests" id="friendRequestsPanel">
                <div class="card-heading"><h3>Requests</h3><span class="card-count"><?php echo count($friendRequests); ?></span></div>
                <div class="stack-list">
                    <?php foreach ($friendRequests as $request): ?>
                    <div class="person-row" data-request-card="<?php echo (int) $request['user_id']; ?>">
                        <img src="assets/avatars/<?php echo htmlspecialchars($request['avatar'] ?? 'default.png'); ?>" alt="" class="person-avatar" onerror="this.src='assets/avatars/default.png'">
                        <div class="person-copy"><strong><?php echo htmlspecialchars($request['full_name'] ?: $request['username']); ?></strong></div>
                        <div class="person-actions">
                            <button class="btn btn-sm btn-primary friend-response-btn" data-request-user-id="<?php echo (int) $request['user_id']; ?>" data-decision="accept"><i class="fas fa-check"></i></button>
                            <button class="btn btn-sm btn-outline friend-response-btn" data-request-user-id="<?php echo (int) $request['user_id']; ?>" data-decision="reject"><i class="fas fa-times"></i></button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($suggestedFriends): ?>
            <div class="profile-card sidebar-suggestions">
                <div class="card-heading"><h3>Suggestions</h3><span class="card-count"><?php echo count($suggestedFriends); ?></span></div>
                <div class="stack-list">
                    <?php foreach ($suggestedFriends as $s): ?>
                    <div class="person-row" data-suggestion-card="<?php echo (int) $s['id']; ?>">
                        <img src="assets/avatars/<?php echo htmlspecialchars($s['avatar'] ?? 'default.png'); ?>" alt="" class="person-avatar" onerror="this.src='assets/avatars/default.png'">
                        <div class="person-copy"><strong><?php echo htmlspecialchars($s['full_name'] ?: $s['username']); ?></strong></div>
                        <button class="btn btn-sm btn-primary friend-toggle-btn" data-action="send_friend_request" data-target-user-id="<?php echo (int) $s['id']; ?>"><i class="fas fa-plus"></i></button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </aside>

        <!-- Main -->
        <section class="profile-main">

            <!-- Timeline Tab -->
            <div class="tab-content active" id="timelineTab">
                <?php if ($isOwnProfile): ?>
                <!-- Composer Card (trigger) -->
                <section class="community-composer-card" id="composerCard">
                    <div class="community-composer-top">
                        <img src="assets/avatars/<?= htmlspecialchars($profileUser['avatar'] ?? 'default.png') ?>" alt="" onerror="this.src='assets/avatars/default.png'">
                        <button type="button" class="composer-trigger-btn" id="composerTriggerBtn">What's on your mind, <?= htmlspecialchars(explode(' ', trim($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Friend'))[0]) ?>?</button>
                    </div>
                    <div class="community-composer-actions">
                        <button type="button" class="composer-action-btn" id="composerTriggerPhoto"><i class="fas fa-image" style="color:#45bd62"></i> Photo</button>
                        <button type="button" class="composer-action-btn" id="composerTriggerFeeling"><i class="fas fa-face-smile" style="color:#f7b928"></i> Feeling</button>
                    </div>
                </section>
                
                <!-- Create Post Modal -->
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
                <?php endif; ?>

                <div id="postFeed" class="timeline-post-feed">
                    <?php if ($posts): ?>
                        <?php foreach ($posts as $post): ?>
                        <article class="community-post-card" data-post-id="<?= (int) $post['id'] ?>">
                            <div class="community-post-header">
                                <div class="community-post-author">
                                    <img src="assets/avatars/<?= htmlspecialchars($post['avatar'] ?? 'default.png') ?>" alt="" onerror="this.src='assets/avatars/default.png'">
                                    <div class="community-author-info">
                                        <strong><?= htmlspecialchars($post['full_name'] ?: $post['username']) ?></strong>
                                        <span class="community-post-time"><?= formatTimeAgo($post['created_at']) ?>
                                            <span style="font-size:11px;color:var(--comm-text-secondary);margin-left:6px"><i class="fas fa-globe-asia"></i></span>
                                        </span>
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
                                    <span><?= (int) ($post['share_count'] ?? 0) ?> shares</span>
                                </div>
                            </div>
                            <div class="community-post-actions">
                                <div class="community-btn-action-wrap">
                                    <button class="community-btn-action <?= ($post['viewer_reaction'] ?? null) ? 'active' : '' ?>" data-action="like" data-reaction="<?= htmlspecialchars($post['viewer_reaction'] ?? '') ?>">
                                        <?php if ($post['viewer_reaction'] ?? null): ?>
                                            <span style="margin-right:8px"><?= getEmoji($post['viewer_reaction']) ?></span> <?= ucfirst($post['viewer_reaction']) ?>
                                        <?php else: ?>
                                            <i class="far fa-thumbs-up"></i> Like
                                        <?php endif; ?>
                                    </button>
                                    <div class="community-reaction-strip">
                                        <?php foreach ($homeReactionOptions as $rxn): ?>
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
                    <?php else: ?>
                    <div class="profile-card empty-state">
                        <i class="fas fa-comments"></i>
                        <h3>No posts yet</h3>
                        <p><?php echo $isOwnProfile ? 'Your first update will appear here.' : 'This profile has no public posts yet.'; ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- About Tab -->
            <div class="tab-content" id="aboutTab">
                <div class="mobile-profile-sections">
                    <div class="profile-card sidebar-intro mobile-only-profile-card">
                        <div class="card-heading"><h3>Intro</h3><?php if ($isOwnProfile): ?><button class="btn btn-sm btn-outline" data-open-profile-settings><i class="fas fa-pen"></i></button><?php endif; ?></div>
                        <?php if (!empty($profileUser['bio'])): ?><div class="intro-item"><i class="fas fa-quote-left" style="color:var(--primary)"></i><span><?php echo htmlspecialchars($profileUser['bio']); ?></span></div><?php endif; ?>
                        <?php if (!empty($profileUser['location'])): ?><div class="intro-item"><i class="fas fa-house" style="color:#10b981"></i><span>Lives in <?php echo htmlspecialchars($profileUser['location']); ?></span></div><?php endif; ?>
                        <?php if (!empty($profileUser['website'])): ?><div class="intro-item"><i class="fas fa-link" style="color:#3b82f6"></i><a href="<?php echo htmlspecialchars($profileUser['website']); ?>" target="_blank"><?php echo htmlspecialchars($profileUser['website']); ?></a></div><?php endif; ?>
                        <div class="intro-item"><i class="fas fa-calendar" style="color:#f59e0b"></i><span>Joined <?php echo date('F Y', strtotime($profileUser['registered_at'] ?? 'now')); ?></span></div>
                        <div class="intro-item"><i class="fas fa-shield-heart" style="color:#ec4899"></i><span><?php echo !empty($profileUser['email_verified']) ? 'Verified member' : 'Email not verified'; ?></span></div>
                    </div>
                </div>
                <div class="profile-card about-showcase-card">
                    <div class="card-heading"><h3>About <?php echo htmlspecialchars($displayName); ?></h3><?php if ($isOwnProfile): ?><button class="btn btn-sm btn-outline" data-open-profile-settings><i class="fas fa-pen"></i></button><?php endif; ?></div>
                    <div class="about-showcase-grid">
                        <div class="about-feature-card">
                            <span class="about-feature-icon"><i class="fas fa-user"></i></span>
                            <span class="about-label">Username</span>
                            <strong>@<?php echo htmlspecialchars($profileUser['username']); ?></strong>
                        </div>
                        <?php foreach ($aboutHighlights as $item): ?>
                        <div class="about-feature-card about-feature-card--<?php echo htmlspecialchars($item['accent']); ?>">
                            <span class="about-feature-icon"><i class="fas <?php echo htmlspecialchars($item['icon']); ?>"></i></span>
                            <span class="about-label"><?php echo htmlspecialchars($item['label']); ?></span>
                            <?php if (!empty($item['url'])): ?>
                            <a href="<?php echo htmlspecialchars($item['url']); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($item['value']); ?></a>
                            <?php else: ?>
                            <strong><?php echo htmlspecialchars($item['value']); ?></strong>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        <div class="about-feature-card about-feature-card--slate">
                            <span class="about-feature-icon"><i class="fas fa-envelope"></i></span>
                            <span class="about-label">Email</span>
                            <strong><?php echo htmlspecialchars($profileUser['email']); ?></strong>
                        </div>
                    </div>
                </div>
                <div class="mobile-profile-sections">
                    <div class="profile-card mobile-only-profile-card">
                        <div class="card-heading"><h3>Snapshot</h3></div>
                        <div class="snapshot-list">
                            <div class="snapshot-item"><span>Posts</span><strong><?php echo $stats['posts']; ?></strong></div>
                            <div class="snapshot-item"><span>Friends</span><strong><?php echo $stats['friends']; ?></strong></div>
                            <div class="snapshot-item"><span>Photos</span><strong><?php echo $stats['photos']; ?></strong></div>
                            <div class="snapshot-item"><span>Sessions</span><strong><?php echo (int) $profileUser['session_count']; ?></strong></div>
                        </div>
                    </div>
                    <?php if ($friends): ?>
                    <div class="profile-card mobile-only-profile-card">
                        <div class="card-heading"><h3>Friend Circle</h3><span class="card-count"><?php echo count($friends); ?></span></div>
                        <div class="friends-mini-grid">
                            <?php foreach (array_slice($friends, 0, 8) as $f): ?>
                            <a href="index.php?page=profile&user=<?php echo (int) $f['id']; ?>" class="friend-mini" data-no-ajax>
                                <img src="assets/avatars/<?php echo htmlspecialchars($f['avatar'] ?? 'default.png'); ?>" alt="" onerror="this.src='assets/avatars/default.png'">
                                <span><?php echo htmlspecialchars($f['full_name'] ?: $f['username']); ?></span>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Friends Tab -->
            <div class="tab-content" id="friendsTab">
                <div class="profile-card">
                    <div class="card-heading"><h3><i class="fas fa-user-group" style="color:#1877f2"></i> Friends</h3><span class="card-count" style="background:rgba(24,119,242,0.1);color:#1877f2"><?php echo $stats['friends']; ?></span></div>
                    <div class="fb-tabs">
                        <button class="fb-tab active" data-friends-view="all"><strong><?php echo $stats['friends']; ?></strong> Friends</button>
                        <?php if ($isOwnProfile): ?>
                        <button class="fb-tab" data-friends-view="requests"><strong><?php echo count($friendRequests); ?></strong> Requests</button>
                        <button class="fb-tab" data-friends-view="suggestions"><strong><?php echo count($suggestedFriends); ?></strong> Suggestions</button>
                        <?php endif; ?>
                    </div>
                    <div class="friends-view-panel active" data-friends-panel="all">
                        <div class="fb-friends-grid" id="friendsGrid">
                            <?php if ($friends): ?>
                                <?php $fi = 0; foreach ($friends as $friend): ?>
                                <div class="fb-friend-card" style="animation:fbFadeIn 0.3s ease both;animation-delay:<?php echo min($fi, 10) * 0.05; ?>s">
                                    <a href="index.php?page=profile&user=<?php echo (int) $friend['id']; ?>" data-no-ajax>
                                        <div class="fb-friend-avatar-wrap">
                                            <img src="assets/avatars/<?php echo htmlspecialchars($friend['avatar'] ?? 'default.png'); ?>" alt="" class="fb-friend-avatar" onerror="this.src='assets/avatars/default.png'">
                                        </div>
                                    </a>
                                    <a href="index.php?page=profile&user=<?php echo (int) $friend['id']; ?>" data-no-ajax class="fb-friend-name"><?php echo htmlspecialchars($friend['full_name'] ?: $friend['username']); ?></a>
                                    <span class="fb-friend-mutual">Friends</span>
                                    <div class="fb-friend-actions">
                                        <button class="fb-btn fb-btn-danger friend-toggle-btn" data-action="remove_friend" data-target-user-id="<?php echo (int) $friend['id']; ?>">Unfriend</button>
                                    </div>
                                </div>
                                <?php $fi++; endforeach; ?>
                            <?php else: ?>
                                <div class="fb-empty"><i class="fas fa-user-friends text-2xl mb-3 block opacity-30"></i><p>No friends yet</p></div>
                            <?php endif; ?>
                        </div>
                        <?php if (count($friends) >= 12): ?>
                        <div class="fb-load-more"><button class="fb-btn fb-btn-secondary" id="loadMoreFriends" data-offset="12">Load More</button></div>
                        <?php endif; ?>
                    </div>
                    <?php if ($isOwnProfile): ?>
                    <div class="friends-view-panel" data-friends-panel="requests">
                        <?php if ($friendRequests): ?>
                            <?php $ri = 0; foreach ($friendRequests as $request): ?>
                            <div class="fb-request-card" data-request-card="<?php echo (int) $request['user_id']; ?>" style="animation:fbFadeIn 0.3s ease both;animation-delay:<?php echo min($ri, 10) * 0.08; ?>s">
                                <img src="assets/avatars/<?php echo htmlspecialchars($request['avatar'] ?? 'default.png'); ?>" alt="" class="fb-request-avatar" onerror="this.src='assets/avatars/default.png'">
                                <div class="fb-request-info">
                                    <strong><a href="index.php?page=profile&user=<?php echo (int) $request['user_id']; ?>" data-no-ajax class="fb-friend-name"><?php echo htmlspecialchars($request['full_name'] ?: $request['username']); ?></a></strong>
                                    <span><?php echo (int) ($request['mutual_count'] ?? 0); ?> mutual friends</span>
                                </div>
                                <div class="fb-request-actions">
                                    <button class="fb-btn fb-btn-primary friend-response-btn" data-request-user-id="<?php echo (int) $request['user_id']; ?>" data-decision="accept">Confirm</button>
                                    <button class="fb-btn fb-btn-secondary friend-response-btn" data-request-user-id="<?php echo (int) $request['user_id']; ?>" data-decision="reject">Delete</button>
                                </div>
                            </div>
                            <?php $ri++; endforeach; ?>
                        <?php else: ?>
                        <div class="fb-empty"><i class="fas fa-user-clock text-2xl mb-3 block opacity-30"></i><p>No pending requests</p></div>
                        <?php endif; ?>
                    </div>
                    <div class="friends-view-panel" data-friends-panel="suggestions">
                        <?php if ($suggestedFriends): ?>
                        <div class="fb-friends-grid">
                            <?php $si = 0; foreach ($suggestedFriends as $s): ?>
                            <div class="fb-friend-card" style="animation:fbFadeIn 0.3s ease both;animation-delay:<?php echo min($si, 10) * 0.05; ?>s">
                                <a href="index.php?page=profile&user=<?php echo (int) $s['id']; ?>" data-no-ajax>
                                    <div class="fb-friend-avatar-wrap">
                                        <img src="assets/avatars/<?php echo htmlspecialchars($s['avatar'] ?? 'default.png'); ?>" alt="" class="fb-friend-avatar" onerror="this.src='assets/avatars/default.png'">
                                    </div>
                                </a>
                                <a href="index.php?page=profile&user=<?php echo (int) $s['id']; ?>" data-no-ajax class="fb-friend-name"><?php echo htmlspecialchars($s['full_name'] ?: $s['username']); ?></a>
                                <span class="fb-friend-mutual"><?php echo htmlspecialchars($s['suggestion_reason'] ?? ''); ?></span>
                                <div class="fb-friend-actions">
                                    <button class="fb-btn fb-btn-primary friend-toggle-btn" data-action="send_friend_request" data-target-user-id="<?php echo (int) $s['id']; ?>"><i class="fas fa-user-plus"></i> Add Friend</button>
                                </div>
                            </div>
                            <?php $si++; endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="fb-empty"><i class="fas fa-user-plus text-2xl mb-3 block opacity-30"></i><p>No suggestions</p></div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Photos Tab -->
            <div class="tab-content" id="photosTab">
                <div class="profile-card">
                    <div class="card-heading"><h3>Photos</h3><span class="card-count"><?php echo count($userPhotos); ?></span></div>
                    <?php if ($userPhotos): ?>
                    <div class="profile-photos-grid">
                        <?php foreach ($userPhotos as $photo): ?>
                        <a href="index.php?page=profile&post=<?php echo htmlspecialchars($photo['image_path']); ?>" class="profile-photo-item" target="_blank">
                            <img src="assets/posts/<?php echo htmlspecialchars($photo['image_path']); ?>" alt="" loading="lazy">
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="empty-panel py-8"><i class="fas fa-images text-4xl mb-3 block opacity-30"></i><p>No photos yet. Photos from posts will appear here.</p></div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Sidebar Right -->
        <aside class="profile-sidebar">
            <div class="profile-card">
                <div class="card-heading"><h3>Snapshot</h3></div>
                <div class="snapshot-list">
                    <div class="snapshot-item"><span>Posts</span><strong><?php echo $stats['posts']; ?></strong></div>
                    <div class="snapshot-item"><span>Friends</span><strong><?php echo $stats['friends']; ?></strong></div>
                    <div class="snapshot-item"><span>Photos</span><strong><?php echo $stats['photos']; ?></strong></div>
                    <div class="snapshot-item"><span>Sessions</span><strong><?php echo (int) $profileUser['session_count']; ?></strong></div>
                </div>
            </div>
            <?php if ($friends): ?>
            <div class="profile-card">
                <div class="card-heading"><h3>Friend Circle</h3><span class="card-count"><?php echo count($friends); ?></span></div>
                <div class="friends-mini-grid">
                    <?php foreach (array_slice($friends, 0, 8) as $f): ?>
                    <a href="index.php?page=profile&user=<?php echo (int) $f['id']; ?>" class="friend-mini" data-no-ajax>
                        <img src="assets/avatars/<?php echo htmlspecialchars($f['avatar'] ?? 'default.png'); ?>" alt="" onerror="this.src='assets/avatars/default.png'">
                        <span><?php echo htmlspecialchars($f['full_name'] ?: $f['username']); ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </aside>
    </div>

    <nav class="profile-mobile-bottom-nav" aria-label="Quick profile navigation">
        <button class="profile-mobile-bottom-link active" data-profile-tab data-tab="timeline" type="button">
            <i class="fas fa-newspaper"></i>
            <span>Posts</span>
        </button>
        <button class="profile-mobile-bottom-link" data-profile-tab data-tab="about" type="button">
            <i class="fas fa-circle-info"></i>
            <span>About</span>
        </button>
        <button class="profile-mobile-bottom-link" data-profile-tab data-tab="friends" type="button">
            <i class="fas fa-user-group"></i>
            <span>Friends</span>
        </button>
        <button class="profile-mobile-bottom-link" data-profile-tab data-tab="photos" type="button">
            <i class="fas fa-images"></i>
            <span>Photos</span>
        </button>
    </nav>
</div>

<!-- ===== EDIT PROFILE DIALOG (site-dialog style) ===== -->
<div class="site-dialog-backdrop" id="profileDialogBackdrop" hidden></div>
<div class="site-dialog" id="profileSettingsDialog" role="dialog" aria-modal="true" aria-labelledby="profileSettingsTitle" hidden>
    <div class="site-dialog-panel settings-dialog-panel">
        <!-- Fixed Header -->
        <div class="site-dialog-header settings-dialog-header">
            <div>
                <span class="site-dialog-kicker">Profile studio</span>
                <h2 id="profileSettingsTitle"><i class="fas fa-user-gear"></i> Edit Profile</h2>
            </div>
            <button type="button" class="site-dialog-close" data-close-profile-settings><i class="fas fa-times"></i></button>
        </div>

        <!-- Fixed Tabs -->
        <div class="profile-settings-nav">
            <button class="nav-item active" data-stab="profile" type="button"><i class="fas fa-user"></i> Profile</button>
            <button class="nav-item" data-stab="security" type="button"><i class="fas fa-lock"></i> Security</button>
            <button class="nav-item" data-stab="sessions" type="button"><i class="fas fa-desktop"></i> Sessions</button>
            <button class="nav-item" data-stab="preferences" type="button"><i class="fas fa-sliders"></i> Preferences</button>
        </div>

        <!-- Scrollable Content -->
        <div class="settings-scroll-content">

            <div class="stab-content active" id="sProfileTab">
                <form id="profileForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <div class="settings-panel-grid">
                        <div class="settings-surface-card">
                            <div class="settings-surface-header"><h3><i class="fas fa-id-card text-blue-500 mr-1"></i> Identity</h3><p>Name, contact & location</p></div>
                            <div class="form-row">
                                <div class="form-group"><label class="form-label">Full Name</label><input type="text" name="full_name" class="form-input" value="<?php echo htmlspecialchars($profileUser['full_name'] ?? ''); ?>" required></div>
                                <div class="form-group"><label class="form-label">Username</label><input type="text" class="form-input" value="<?php echo htmlspecialchars($profileUser['username']); ?>" disabled></div>
                            </div>
                            <div class="form-row">
                                <div class="form-group"><label class="form-label"><i class="fas fa-phone text-xs mr-1"></i> Phone</label><input type="tel" name="phone" class="form-input" value="<?php echo htmlspecialchars($profileUser['phone'] ?? ''); ?>"></div>
                                <div class="form-group"><label class="form-label"><i class="fas fa-location-dot text-xs mr-1"></i> Location</label><input type="text" name="location" class="form-input" value="<?php echo htmlspecialchars($profileUser['location'] ?? ''); ?>"></div>
                            </div>
                        </div>
                        <div class="settings-surface-card">
                            <div class="settings-surface-header"><h3><i class="fas fa-globe text-emerald-500 mr-1"></i> Public profile</h3><p>Bio & website</p></div>
                            <div class="form-group"><label class="form-label"><i class="fas fa-link text-xs mr-1"></i> Website</label><input type="url" name="website" class="form-input" value="<?php echo htmlspecialchars($profileUser['website'] ?? ''); ?>" placeholder="https://"></div>
                            <div class="form-group"><label class="form-label"><i class="fas fa-quote-left text-xs mr-1"></i> Bio</label><textarea name="bio" class="form-input" rows="4" placeholder="Tell people about yourself..."><?php echo htmlspecialchars($profileUser['bio'] ?? ''); ?></textarea></div>
                        </div>
                        <div class="settings-surface-card">
                            <div class="settings-surface-header"><h3><i class="fas fa-share-nodes text-purple-500 mr-1"></i> Social Links</h3><p>Connect your social profiles</p></div>
                            <?php $socialLinks = ['facebook' => 'fab fa-facebook', 'twitter' => 'fab fa-twitter', 'instagram' => 'fab fa-instagram', 'github' => 'fab fa-github', 'discord' => 'fab fa-discord', 'youtube' => 'fab fa-youtube']; ?>
                            <?php $savedSocial = !empty($profileUser['preferences']) ? (json_decode($profileUser['preferences'], true)['social'] ?? []) : []; ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <?php foreach ($socialLinks as $platform => $icon): ?>
                                <div class="form-group mb-0">
                                    <label class="form-label flex items-center gap-1.5"><i class="<?php echo $icon; ?> text-xs" style="color:<?php echo match($platform) { 'facebook'=>'#1877f2', 'twitter'=>'#1da1f2', 'instagram'=>'#e4405f', 'github'=>'#333', 'discord'=>'#5865f2', 'youtube'=>'#ff0000', default=>'#666' }; ?>"></i> <?php echo ucfirst($platform); ?></label>
                                    <input type="url" name="social[<?php echo $platform; ?>]" class="form-input" value="<?php echo htmlspecialchars($savedSocial[$platform] ?? ''); ?>" placeholder="https://<?php echo $platform; ?>.com/">
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="settings-submit-row"><button type="submit" class="btn btn-primary"><i class="fas fa-check mr-1"></i> Save Changes</button></div>
                </form>
            </div>

            <div class="stab-content" id="sSecurityTab">
                <form id="passwordForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <div class="settings-surface-card">
                        <div class="settings-surface-header"><h3><i class="fas fa-lock text-red-500 mr-1"></i> Password</h3><p>Keep your account protected</p></div>
                        <div class="form-group"><label class="form-label">Current Password</label><input type="password" name="current_password" class="form-input" required placeholder="Enter current password"></div>
                        <div class="form-row">
                            <div class="form-group"><label class="form-label">New Password</label><input type="password" name="new_password" class="form-input" required placeholder="Min 8 characters"></div>
                            <div class="form-group"><label class="form-label">Confirm New Password</label><input type="password" name="confirm_password" class="form-input" required placeholder="Repeat new password"></div>
                        </div>
                        <div class="settings-submit-row"><button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Update Password</button></div>
                    </div>
                </form>
            </div>

            <div class="stab-content" id="sSessionsTab">
                <div class="settings-surface-card">
                    <div class="settings-surface-header"><h3><i class="fas fa-desktop text-cyan-500 mr-1"></i> Active sessions</h3><p>Where your account is signed in</p></div>
                    <?php if ($activeSessions): ?>
                        <?php foreach ($activeSessions as $session): ?>
                        <?php $isCurrentSession = hash_equals(session_id(), (string) ($session['session_token'] ?? '')); ?>
                        <div class="session-item" data-session-item="<?php echo htmlspecialchars($session['id'] ?? ''); ?>">
                            <i class="fas fa-<?php echo strpos($session['user_agent'] ?? '', 'Mobile') !== false ? 'mobile' : 'desktop'; ?>"></i>
                            <div class="session-info">
                                <h4><?php echo strpos($session['user_agent'] ?? '', 'Mobile') !== false ? 'Mobile' : 'Desktop'; ?> session <?php if ($isCurrentSession): ?><span class="session-current-badge">Current</span><?php endif; ?></h4>
                                <div class="session-meta"><span><i class="fas fa-globe mr-1"></i><?php echo htmlspecialchars($session['ip_address'] ?? ''); ?></span><span><i class="fas fa-clock mr-1"></i>Last active: <?php echo date('M d, H:i', (int) ($session['last_activity'] ?? 0)); ?></span></div>
                            </div>
                            <?php if (!$isCurrentSession): ?>
                            <button type="button" class="session-delete-btn" data-delete-session data-session-id="<?php echo htmlspecialchars($session['id'] ?? ''); ?>"><i class="fas fa-trash"></i><span>Remove</span></button>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <div class="text-center py-8 text-gray-400"><i class="fas fa-desktop text-3xl mb-3 block opacity-30"></i><p class="text-sm">No active sessions found</p></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="stab-content" id="sPreferencesTab">
                <form id="preferencesForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <div class="settings-panel-grid">
                        <div class="settings-surface-card">
                            <div class="settings-surface-header"><h3><i class="fas fa-palette text-amber-500 mr-1"></i> Appearance</h3><p>Choose your theme</p></div>
                            <div class="form-group">
                                <label class="form-label">Theme</label>
                                <div class="theme-options">
                                    <label class="theme-option"><input type="radio" name="theme" value="light" <?php echo ($preferences['theme'] ?? 'light') === 'light' ? 'checked' : ''; ?>><div class="theme-preview light"></div><span>Light</span></label>
                                    <label class="theme-option"><input type="radio" name="theme" value="dark" <?php echo ($preferences['theme'] ?? 'light') === 'dark' ? 'checked' : ''; ?>><div class="theme-preview dark"></div><span>Dark</span></label>
                                    <label class="theme-option"><input type="radio" name="theme" value="auto" <?php echo ($preferences['theme'] ?? 'light') === 'auto' ? 'checked' : ''; ?>><div class="theme-preview auto"></div><span>Auto</span></label>
                                </div>
                            </div>
                        </div>
                        <div class="settings-surface-card">
                            <div class="settings-surface-header"><h3><i class="fas fa-bell text-purple-500 mr-1"></i> Notifications</h3><p>Control what you get notified about</p></div>
                            <div class="space-y-3">
                                <?php $notifPrefs = $preferences['notifications'] ?? []; ?>
                                <label class="flex items-center gap-3 cursor-pointer p-2 -mx-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50"><input type="checkbox" name="notifications[friend_requests]" value="1" <?php echo !empty($notifPrefs['friend_requests']) ? 'checked' : ''; ?> class="w-4 h-4 rounded accent-indigo-500"><span class="text-sm font-medium">Friend requests</span></label>
                                <label class="flex items-center gap-3 cursor-pointer p-2 -mx-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50"><input type="checkbox" name="notifications[messages]" value="1" <?php echo !empty($notifPrefs['messages']) ? 'checked' : ''; ?> class="w-4 h-4 rounded accent-indigo-500"><span class="text-sm font-medium">Messages</span></label>
                                <label class="flex items-center gap-3 cursor-pointer p-2 -mx-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50"><input type="checkbox" name="notifications[post_likes]" value="1" <?php echo !empty($notifPrefs['post_likes']) ? 'checked' : ''; ?> class="w-4 h-4 rounded accent-indigo-500"><span class="text-sm font-medium">Post likes & reactions</span></label>
                                <label class="flex items-center gap-3 cursor-pointer p-2 -mx-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50"><input type="checkbox" name="notifications[comments]" value="1" <?php echo !empty($notifPrefs['comments']) ? 'checked' : ''; ?> class="w-4 h-4 rounded accent-indigo-500"><span class="text-sm font-medium">Comments & replies</span></label>
                                <label class="flex items-center gap-3 cursor-pointer p-2 -mx-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50"><input type="checkbox" name="notifications[tournaments]" value="1" <?php echo !empty($notifPrefs['tournaments']) ? 'checked' : ''; ?> class="w-4 h-4 rounded accent-indigo-500"><span class="text-sm font-medium">Tournament updates</span></label>
                            </div>
                        </div>
                    </div>
                    <div class="settings-submit-row"><button type="submit" class="btn btn-primary"><i class="fas fa-check mr-1"></i> Save Preferences</button></div>
                </form>
            </div>

        </div>
    </div>
</div>

<!-- ===== AVATAR UPLOAD DIALOG (site-dialog style) ===== -->
<div class="site-dialog-backdrop" id="avatarDialogBackdrop" hidden></div>
<div class="site-dialog" id="avatarUploadDialog" role="dialog" aria-modal="true" aria-labelledby="avatarUploadTitle" hidden>
    <div class="site-dialog-panel avatar-dialog-panel">
        <div class="site-dialog-header avatar-modal-header">
            <div class="avatar-modal-title-group">
                <span class="avatar-modal-eyebrow">Profile studio</span>
                <h2 id="avatarUploadTitle"><i class="fas fa-camera"></i> Profile Photo</h2>
            </div>
            <button type="button" class="site-dialog-close" data-close-avatar-upload><i class="fas fa-times"></i></button>
        </div>

        <div class="avatar-modal-body">
        <div class="avatar-studio-grid avatar-studio-grid--single" id="avatarStudioGrid">
            <div class="settings-surface-card avatar-choice-card" id="avatarChooseCard">
                <div class="settings-surface-header avatar-choice-copy"><h3>Select image</h3><p>PNG, JPG, GIF, or WebP up to 5MB.</p></div>
                <div class="file-upload avatar-upload-dropzone" id="avatarUploadArea">
                    <div class="file-upload-icon"><i class="fas fa-cloud-arrow-up"></i></div>
                    <div class="file-upload-text"><h4>Choose profile photo</h4><p>Tap to browse or drop an image</p></div>
                    <div><span class="btn btn-outline">Browse files</span></div>
                    <input type="file" id="avatarFile" accept="image/*" hidden>
                </div>
                <div class="avatar-upload-hints">
                    <span><i class="fas fa-circle-check"></i> Square works best</span>
                    <span><i class="fas fa-eye"></i> Preview before uploading</span>
                </div>
            </div>
            <div class="settings-surface-card avatar-crop-card" id="avatarCropCard" hidden>
                <div class="settings-surface-header avatar-crop-heading">
                    <div>
                        <h3>Edit image</h3>
                        <p>Move and zoom until the circle fits perfectly.</p>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline" id="avatarChooseAnother"><i class="fas fa-arrow-left"></i> Change</button>
                </div>
                <div id="avatarPreview" hidden>
                    <div class="avatar-crop-stage" id="avatarCropStage">
                        <div class="avatar-crop-frame"><img id="avatarPreviewImg" src="" alt="Preview"></div>
                    </div>
                    <div class="avatar-crop-controls">
                        <label class="avatar-crop-control avatar-crop-control--zoom">
                            <span>Zoom</span>
                            <div class="avatar-zoom-control">
                                <button type="button" class="avatar-zoom-btn" data-avatar-zoom="out" aria-label="Zoom out"><i class="fas fa-minus"></i></button>
                                <input type="range" id="avatarCropZoom" min="1" max="3" step="0.01" value="1">
                                <button type="button" class="avatar-zoom-btn" data-avatar-zoom="in" aria-label="Zoom in"><i class="fas fa-plus"></i></button>
                            </div>
                        </label>
                        <label><span>Horizontal</span><input type="range" id="avatarCropX" min="-100" max="100" step="1" value="0"></label>
                        <label><span>Vertical</span><input type="range" id="avatarCropY" min="-100" max="100" step="1" value="0"></label>
                    </div>
                    <div class="avatar-preview-copy"><strong>Preview and crop</strong><span>Adjust the photo so it fits perfectly in the profile circle.</span></div>
                </div>
                <div id="avatarPreviewEmpty">
                    <div class="avatar-preview-frame"><img src="assets/avatars/<?php echo htmlspecialchars($profileUser['avatar'] ?? 'default.png'); ?>" alt="" onerror="this.src='assets/avatars/default.png'"></div>
                    <strong>Current picture</strong>
                    <p class="text-sm text-gray-500">Select a new image to see the preview.</p>
                </div>
            </div>
        </div>
        </div>
        <div class="settings-submit-row avatar-modal-footer"><button id="uploadAvatarBtn" class="btn btn-primary" type="button" disabled><i class="fas fa-check"></i> Update profile</button></div>
    </div>
</div>

<!-- ===== COVER UPLOAD DIALOG ===== -->
<div class="site-dialog-backdrop" id="coverDialogBackdrop" hidden></div>
<div class="site-dialog" id="coverUploadDialog" role="dialog" aria-modal="true" hidden>
    <div class="site-dialog-panel avatar-dialog-panel">
        <div class="site-dialog-header">
            <div>
                <span class="site-dialog-kicker">Cover studio</span>
                <h2><i class="fas fa-image"></i> Cover Photo</h2>
            </div>
            <button type="button" class="site-dialog-close" data-close-cover-upload><i class="fas fa-times"></i></button>
        </div>
        <div class="avatar-studio-grid cover-studio-grid" id="coverStudioGrid">
            <div class="settings-surface-card cover-choice-card" id="coverChooseCard">
                <div class="settings-surface-header"><h3>Choose a cover</h3><p>PNG, JPG, WebP up to 10MB. Landscape works best.</p></div>
                <div class="file-upload avatar-upload-dropzone" id="coverUploadArea">
                    <div class="file-upload-icon"><i class="fas fa-cloud-arrow-up"></i></div>
                    <div class="file-upload-text"><h4>Drop cover image here</h4><p>or tap to browse</p></div>
                    <div><span class="btn btn-outline">Browse files</span></div>
                    <input type="file" id="coverFileInput" accept="image/*" hidden>
                </div>
            </div>
            <div class="settings-surface-card cover-preview-card" id="coverPreviewCard" hidden>
                <div class="settings-surface-header avatar-crop-heading">
                    <div><h3>Preview cover</h3><p>Make sure the image looks good as a wide banner.</p></div>
                    <button type="button" class="btn btn-sm btn-outline" id="coverChooseAnother"><i class="fas fa-arrow-left"></i> Change</button>
                </div>
                <div id="coverPreview" hidden>
                    <div class="cover-preview-frame"><img id="coverPreviewImg" src="" alt="Cover preview"></div>
                    <div class="settings-submit-row mt-4"><button id="uploadCoverBtn" class="btn btn-primary" type="button" disabled><i class="fas fa-check"></i> Update cover</button></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/post-modals.php'; ?>

<script src="<?php echo dream_asset('assets/js/community.js'); ?>" defer></script>
