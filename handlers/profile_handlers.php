<?php
// profile_handlers.php
error_reporting(0);
ini_set('display_errors', 0);
require_once __DIR__ . '/../includes/session.php';
dream_start_session();
if (ob_get_level() === 0) ob_start();

require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];
$security = new Security();
$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true);
$requestData = is_array($jsonInput) ? $jsonInput : $_POST;

// Verify CSRF token for POST requests
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && !$security->validateCSRFToken($requestData['csrf_token'] ?? '')
) {
    $response['message'] = 'Invalid security token';
    echo json_encode($response);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$action = $requestData['action'] ?? ($_GET['action'] ?? '');
$publicReadActions = ['get_post_details'];

if (!$user_id && !in_array($action, $publicReadActions, true)) {
    $response['message'] = 'Not authenticated';
    error_log("profile_handlers.php - Not authenticated. SESSION: " . print_r($_SESSION, true));
    echo json_encode($response);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    ensureSocialTables($db);
} catch (Exception $e) {
    $response['message'] = 'Database connection failed';
    echo json_encode($response);
    exit;
}

switch ($action) {
    case 'upload_avatar':
        handleAvatarUpload($db, $user_id);
        break;
    
    case 'update_profile':
        handleProfileUpdate($db, $user_id);
        break;
    
    case 'add_experience':
        handleAddExperience($db, $user_id);
        break;
    
    case 'delete_experience':
        handleDeleteExperience($db, $user_id);
        break;
    
    case 'update_privacy':
        handlePrivacyUpdate($db, $user_id);
        break;
    
    case 'update_cover':
        handleCoverUpload($db, $user_id);
        break;
    
    case 'update_preferences':
        handlePreferencesUpdate($db, $user_id);
        break;

    case 'delete_session':
        handleDeleteSession($db, $user_id, $requestData);
        break;
    
    case 'get_post_details':
        handleGetPostDetails($db, (int) ($user_id ?? 0));
        break;

    case 'react_post':
        handleReactPost($db, $user_id, $requestData);
        break;

    case 'create_post':
        handleCreatePost($db, $user_id);
        break;
    
    case 'get_post_data':
        handleGetPostData($db);
        break;
    
    case 'update_post':
        handleUpdatePost($db, $user_id);
        break;
    
    case 'mark_notification_read':
        handleMarkNotificationRead($db, $user_id);
        break;
    
    case 'mark_all_notifications_read':
        handleMarkAllNotificationsRead($db, $user_id);
        break;
    
    case 'load_notifications':
        handleLoadNotifications($db, $user_id);
        break;
    
    case 'send_message':
        handleSendMessage($db, $user_id);
        break;
    
    case 'edit_message':
        handleEditMessage($db, $user_id, $requestData);
        break;
    
    case 'pin_message':
        handlePinMessage($db, $user_id, $requestData);
        break;
    
    case 'send_friend_request':
        handleSendFriendRequest($db, $user_id, $requestData);
        break;
    
    case 'remove_friend':
        handleRemoveFriend($db, $user_id, $requestData);
        break;
    
    case 'respond_friend_request':
        handleRespondFriendRequest($db, $user_id, $requestData);
        break;
    
    case 'dismiss_suggestion':
        handleDismissSuggestion($db, $user_id, $requestData);
        break;
    
    case 'delete_message':
        handleDeleteMessage($db, $user_id, $requestData);
        break;
    
    case 'toggle_pin_message':
        handleTogglePinMessage($db, $user_id, $requestData);
        break;
    
    case 'get_recent_threads':
        handleGetRecentThreads($db, $user_id);
        break;

    case 'load_more_messages':
        handleLoadMoreMessages($db, $user_id, $requestData);
        break;
    
    case 'get_pinned_messages':
        handleGetPinnedMessages($db, $user_id, $requestData);
        break;
    
    case 'mark_thread_read':
        handleMarkThreadRead($db, $user_id, $requestData);
        break;
    
    case 'react_message':
        handleReactMessage($db, $user_id, $requestData);
        break;
    
    case 'report_message':
        handleReportMessage($db, $user_id, $requestData);
        break;
    
    case 'get_friends':
        handleGetFriends($db, $user_id);
        break;
    
    case 'search_users':
        handleSearchUsers($db, $user_id, $requestData);
        break;
    
    case 'forward_message':
        handleForwardMessage($db, $user_id, $requestData);
        break;
    
    case 'heartbeat':
        handleHeartbeat($db, $user_id);
        break;
    
    case 'pin_conversation':
        handlePinConversation($db, $user_id, $requestData);
        break;

    case 'toggle_like':
        handleToggleLike($db, $user_id);
        break;

    case 'add_comment':
        handleAddComment($db, $user_id);
        break;

    case 'share_post':
        handleSharePost($db, $user_id);
        break;

    case 'delete_post':
        handleDeletePost($db, $user_id);
        break;

    case 'delete_comment':
        handleDeleteComment($db, $user_id);
        break;

    case 'get_more_comments':
        handleGetMoreComments($db, $user_id);
        break;

    case 'update_comment':
        handleUpdateComment($db, $user_id);
        break;

    case 'toggle_comment_reaction':
        handleToggleCommentReaction($db, $user_id);
        break;

    case 'report_comment':
        handleReportComment($db, $user_id);
        break;

    case 'save_post':
        handleSavePost($db, $user_id);
        break;

    case 'report_post':
        handleReportPost($db, $user_id);
        break;

    default:
        $response['message'] = 'Invalid action';
        echo json_encode($response);
        exit;
}

function handleAvatarUpload($db, $user_id) {
    $response = ['success' => false, 'message' => ''];
    
    if (!isset($_FILES['avatar'])) {
        $response['message'] = 'No file uploaded';
        echo json_encode($response);
        exit;
    }
    
    $file = $_FILES['avatar'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detectedType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($detectedType, $allowedTypes)) {
        $response['message'] = 'Invalid file type. Only JPG, PNG, GIF, WebP allowed.';
        echo json_encode($response);
        exit;
    }
    
    if ($file['size'] > $maxSize) {
        $response['message'] = 'File too large. Maximum 5MB.';
        echo json_encode($response);
        exit;
    }
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
    $uploadPath = __DIR__ . '/../assets/avatars/' . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        $stmt = $db->prepare("UPDATE users SET avatar = ? WHERE id = ?");
        $stmt->execute([$filename, $user_id]);
        
        $_SESSION['avatar'] = $filename;
        
        $response['success'] = true;
        $response['message'] = 'Avatar updated successfully';
        $response['avatar_url'] = 'assets/avatars/' . $filename;
    } else {
        $response['message'] = 'Failed to upload file';
    }
    
    echo json_encode($response);
    exit;
}

function handleProfileUpdate($db, $user_id) {
    $response = ['success' => false, 'message' => ''];
    
    $full_name = trim($_POST['full_name'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $social = $_POST['social'] ?? [];
    
    if (empty($full_name)) {
        $response['message'] = 'Full name is required';
        echo json_encode($response);
        exit;
    }
    
    // Get existing preferences to preserve non-profile fields
    $stmt = $db->prepare("SELECT preferences FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $existingPrefs = $stmt->fetchColumn();
    $prefs = $existingPrefs ? json_decode($existingPrefs, true) : [];
    $prefs['social'] = $social;
    $prefsJson = json_encode($prefs);
    
    $stmt = $db->prepare("
        UPDATE users 
        SET full_name = ?, bio = ?, phone = ?, website = ?, location = ?, preferences = ?
        WHERE id = ?
    ");
    $stmt->execute([$full_name, $bio, $phone, $website, $location, $prefsJson, $user_id]);
    
    $_SESSION['full_name'] = $full_name;
    
    $response['success'] = true;
    $response['message'] = 'Profile updated successfully';
    echo json_encode($response);
    exit;
}

function handleAddExperience($db, $user_id) {
    $response = ['success' => false, 'message' => ''];
    
    $title = trim($_POST['title'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    if (empty($title) || empty($company)) {
        $response['message'] = 'Title and company are required';
        echo json_encode($response);
        exit;
    }
    
    $stmt = $db->prepare("
        INSERT INTO user_experience (user_id, title, company, description)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$user_id, $title, $company, $description]);
    
    $response['success'] = true;
    $response['message'] = 'Experience added successfully';
    echo json_encode($response);
    exit;
}

function handleDeleteExperience($db, $user_id) {
    $response = ['success' => false, 'message' => ''];
    
    $exp_id = (int) ($_POST['exp_id'] ?? 0);
    
    $stmt = $db->prepare("DELETE FROM user_experience WHERE id = ? AND user_id = ?");
    $stmt->execute([$exp_id, $user_id]);
    
    $response['success'] = true;
    $response['message'] = 'Experience deleted successfully';
    echo json_encode($response);
    exit;
}

function handleDeleteSession($db, $user_id, $data) {
    $sessionId = trim((string) ($data['session_id'] ?? ''));
    if ($sessionId === '') {
        echo json_encode(['success' => false, 'message' => 'Invalid session']);
        exit;
    }

    $currentStmt = $db->prepare("SELECT id FROM user_sessions WHERE user_id = ? AND session_token = ? LIMIT 1");
    $currentStmt->execute([$user_id, session_id()]);
    $currentSessionId = (string) ($currentStmt->fetchColumn() ?: '');

    if ($currentSessionId !== '' && hash_equals($currentSessionId, $sessionId)) {
        echo json_encode(['success' => false, 'message' => 'Use logout to end your current session']);
        exit;
    }

    $stmt = $db->prepare("DELETE FROM user_sessions WHERE id = ? AND user_id = ?");
    $stmt->execute([$sessionId, $user_id]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Session not found']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Session removed']);
    exit;
}

function handleSendMessage($db, $userId) {
    $receiverId = (int) ($_POST['receiver_id'] ?? 0);
    $body = trim($_POST['body'] ?? '');
    $replyToId = (int) ($_POST['reply_to_message_id'] ?? 0);
    
    if ($receiverId <= 0 || $receiverId === $userId) {
        echo json_encode(['success' => false, 'message' => 'Invalid recipient']);
        exit;
    }
    
    $imagePath = null;
    if (isset($_FILES['message_image']) && $_FILES['message_image']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['message_image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            $dir = __DIR__ . '/../assets/messages/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $filename = 'msg_' . $userId . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['message_image']['tmp_name'], $dir . $filename)) {
                $imagePath = $filename;
            }
        }
    }
    
    if (empty($body) && !$imagePath) {
        echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
        exit;
    }
    
    $stmt = $db->prepare("INSERT INTO messages (sender_id, receiver_id, body, image_path, reply_to_message_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $receiverId, $body, $imagePath, $replyToId ?: null]);
    $msgId = (int) $db->lastInsertId();
    
    // Fetch full message with user data
    $stmt = $db->prepare("
        SELECT m.*, u.username, u.full_name, u.avatar,
               r.body AS reply_body, ru.full_name AS reply_full_name, ru.username AS reply_username
        FROM messages m
        INNER JOIN users u ON u.id = m.sender_id
        LEFT JOIN messages r ON r.id = m.reply_to_message_id
        LEFT JOIN users ru ON ru.id = r.sender_id
        WHERE m.id = ?
    ");
    $stmt->execute([$msgId]);
    $msg = $stmt->fetch();
    
    // Create notification
    $stmt = $db->prepare("SELECT full_name, username FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $sender = $stmt->fetch();
    $name = $sender['full_name'] ?: $sender['username'];
    $preview = function_exists('mb_strimwidth') ? mb_strimwidth($body ?: 'Sent a photo', 0, 80, '...') : substr($body ?: 'Sent a photo', 0, 80);
    try {
        $stmt = $db->prepare("INSERT INTO notifications (user_id, actor_id, type, message, entity_id) VALUES (?, ?, 'message', ?, ?)");
        $stmt->execute([$receiverId, $userId, $name . ' sent: ' . $preview, $userId]);
    } catch (PDOException $e) {}
    
    echo json_encode(['success' => true, 'message' => 'Sent', 'message_item' => $msg]);
    exit;
}

function handleEditMessage($db, $userId, $data) {
    $msgId = (int) ($data['message_id'] ?? 0);
    $newBody = trim($data['body'] ?? '');
    if (!$msgId || !$newBody) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }
    $stmt = $db->prepare("UPDATE messages SET body = ?, edited_at = NOW() WHERE id = ? AND sender_id = ?");
    $stmt->execute([$newBody, $msgId, $userId]);
    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Message not found']);
        exit;
    }
    // Fetch updated message
    $stmt = $db->prepare("SELECT m.*, u.username, u.full_name, u.avatar FROM messages m INNER JOIN users u ON u.id = m.sender_id WHERE m.id = ?");
    $stmt->execute([$msgId]);
    $msg = $stmt->fetch();
    echo json_encode(['success' => true, 'message' => 'Message updated', 'message_item' => $msg]);
    exit;
}

function handlePinMessage($db, $userId, $data) {
    $msgId = (int) ($data['message_id'] ?? 0);
    $pin = $data['pin'] ?? '1';
    if (!$msgId) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }
    if ($pin === '1') {
        $stmt = $db->prepare("INSERT IGNORE INTO message_pins (message_id, user_id) VALUES (?, ?)");
        $stmt->execute([$msgId, $userId]);
        echo json_encode(['success' => true, 'message' => 'Message pinned']);
    } else {
        $stmt = $db->prepare("DELETE FROM message_pins WHERE message_id = ? AND user_id = ?");
        $stmt->execute([$msgId, $userId]);
        echo json_encode(['success' => true, 'message' => 'Message unpinned']);
    }
    exit;
}

function handleSendFriendRequest($db, $userId, $data) {
    $targetId = (int) ($data['target_user_id'] ?? 0);
    if ($targetId <= 0 || $targetId === $userId) {
        echo json_encode(['success' => false, 'message' => 'Invalid user']);
        exit;
    }
    // Check existing friendship
    $stmt = $db->prepare("SELECT status, requester_id FROM friendships WHERE (requester_id = ? AND addressee_id = ?) OR (requester_id = ? AND addressee_id = ?) LIMIT 1");
    $stmt->execute([$userId, $targetId, $targetId, $userId]);
    $existing = $stmt->fetch();
    if ($existing) {
        $status = $existing['status'];
        if ($status === 'accepted') {
            echo json_encode(['success' => false, 'message' => 'Already friends']);
            exit;
        }
        if ($status === 'pending') {
            echo json_encode(['success' => false, 'message' => 'Friend request already sent']);
            exit;
        }
        if ($status === 'rejected') {
            // Re-send: update the existing row back to pending
            $stmt = $db->prepare("UPDATE friendships SET status = 'pending', updated_at = NOW() WHERE (requester_id = ? AND addressee_id = ?) OR (requester_id = ? AND addressee_id = ?)");
            $stmt->execute([$userId, $targetId, $targetId, $userId]);
            createNotification($db, $targetId, $userId, 'friend_request', 'sent you a friend request');
            echo json_encode(['success' => true, 'message' => 'Friend request sent', 'friendship_status' => 'request_sent']);
            exit;
        }
    }
    $stmt = $db->prepare("INSERT INTO friendships (requester_id, addressee_id, status) VALUES (?, ?, 'pending')");
    $stmt->execute([$userId, $targetId]);
    // Get sender name for notification
    $stmt = $db->prepare("SELECT full_name, username FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $sender = $stmt->fetch();
    $name = $sender['full_name'] ?: $sender['username'];
    createNotification($db, $targetId, $userId, 'friend_request', 'sent you a friend request');
    echo json_encode(['success' => true, 'message' => 'Friend request sent', 'friendship_status' => 'request_sent']);
    exit;
}

function handleRemoveFriend($db, $userId, $data) {
    $targetId = (int) ($data['target_user_id'] ?? 0);
    if ($targetId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid user']);
        exit;
    }
    $stmt = $db->prepare("DELETE FROM friendships WHERE (requester_id = ? AND addressee_id = ?) OR (requester_id = ? AND addressee_id = ?)");
    $stmt->execute([$userId, $targetId, $targetId, $userId]);
    echo json_encode(['success' => true, 'message' => 'Removed', 'friendship_status' => 'not_friends']);
    exit;
}

function handleRespondFriendRequest($db, $userId, $data) {
    $requesterId = (int) ($data['request_user_id'] ?? 0);
    $decision = $data['decision'] ?? '';
    if ($requesterId <= 0 || !in_array($decision, ['accept', 'reject'], true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }
    $status = $decision === 'accept' ? 'accepted' : 'rejected';
    $stmt = $db->prepare("UPDATE friendships SET status = ?, accepted_at = IF(? = 'accepted', NOW(), NULL), updated_at = NOW(), action_user_id = ? WHERE requester_id = ? AND addressee_id = ? AND status = 'pending'");
    $stmt->execute([$status, $status, $userId, $requesterId, $userId]);
    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'No pending request found']);
        exit;
    }
    if ($decision === 'accept') {
        $stmt = $db->prepare("SELECT full_name, username FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $acceptor = $stmt->fetch();
        $name = $acceptor['full_name'] ?: $acceptor['username'];
        createNotification($db, $requesterId, $userId, 'friend_accept', 'accepted your friend request');
    }
    echo json_encode(['success' => true, 'message' => $decision === 'accept' ? 'Friend request accepted' : 'Friend request declined', 'friendship_status' => $decision === 'accept' ? 'friends' : 'not_friends']);
    exit;
}

function handleDismissSuggestion($db, $userId, $data) {
    $dismissedUserId = (int) ($data['target_user_id'] ?? 0);
    if ($dismissedUserId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid user']);
        exit;
    }
    try {
        $stmt = $db->prepare("INSERT IGNORE INTO dismissed_suggestions (user_id, dismissed_user_id) VALUES (?, ?)");
        $stmt->execute([$userId, $dismissedUserId]);
        echo json_encode(['success' => true, 'message' => 'Suggestion dismissed']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit;
}

function handleMarkNotificationRead($db, $userId) {
    try {
        $id = (int) ($_POST['notification_id'] ?? 0);
        if ($id) {
            $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $userId]);
        }
        $stmt = $db->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        $msgUnread = (int)$stmt->fetchColumn();
        $counts = getNotificationCounts($db, $userId);
        if (ob_get_length()) ob_clean();
        echo json_encode(['success' => true, 'counts' => ['messages' => $msgUnread, 'notifications' => $counts['unread']], 'tab_counts' => $counts]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
function handleMarkAllNotificationsRead($db, $userId) {
    try {
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        $counts = getNotificationCounts($db, $userId);
        $stmt = $db->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        $msgUnread = (int)$stmt->fetchColumn();
        if (ob_get_length()) ob_clean();
        echo json_encode(['success' => true, 'counts' => ['messages' => $msgUnread, 'notifications' => $counts['unread']], 'tab_counts' => $counts]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
function handleLoadNotifications($db, $userId) {
    try {
        $filter = $_POST['filter'] ?? 'all';
        $beforeId = (int) ($_POST['before_id'] ?? 0);
        $notifications = getNotificationsList($db, $userId, 10, $filter, $beforeId ?: null);
        $hasMore = count($notifications) >= 10;
        $tabCounts = getNotificationCounts($db, $userId);
        if (ob_get_length()) ob_clean();
        echo json_encode(['success' => true, 'notifications' => $notifications, 'has_more' => $hasMore, 'tab_counts' => $tabCounts]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

function handleGetPostData($db) {
    $response = ['success' => false, 'message' => ''];
    $postId = (int) ($_GET['post_id'] ?? $_POST['post_id'] ?? 0);
    if (!$postId) { $response['message'] = 'No post ID'; echo json_encode($response); exit; }
    
    $userId = $_SESSION['user_id'] ?? 0;
    
    $stmt = $db->prepare("SELECT p.*, u.username, u.full_name, u.avatar FROM posts p JOIN users u ON u.id = p.user_id WHERE p.id = ?");
    $stmt->execute([$postId]);
    $post = $stmt->fetch();
    if (!$post) { $response['message'] = 'Post not found'; echo json_encode($response); exit; }
    
    $likes = $db->prepare("SELECT COUNT(*) FROM post_likes WHERE post_id = ?");
    $likes->execute([$postId]);
    $likeCount = (int) $likes->fetchColumn();
    
    $shares = $db->prepare("SELECT COUNT(*) FROM post_shares WHERE post_id = ?");
    $shares->execute([$postId]);
    $shareCount = (int) $shares->fetchColumn();
    
    $comments = getPostComments($db, $postId, 50, (int) $userId, (int) $post['user_id']);
    
    $response['success'] = true;
    $response['data'] = [
        'id' => $post['id'],
        'author' => $post['full_name'] ?: $post['username'],
        'avatar' => 'assets/avatars/' . ($post['avatar'] ?: 'default.png'),
        'content' => $post['content'],
        'image' => $post['image_path'] ? 'assets/posts/' . $post['image_path'] : '',
        'time' => date('M j, Y g:i A', strtotime($post['created_at'])),
        'privacy' => ucfirst($post['privacy']),
        'likes' => $likeCount,
        'comments_count' => count($comments),
        'shares' => $shareCount,
        'comments' => array_map(function($c) {
            $mapComment = function($item) use (&$mapComment) {
                $replies = [];
                if (!empty($item['replies'])) {
                    foreach ($item['replies'] as $r) {
                        $replies[] = $mapComment($r);
                    }
                }
                return [
                    'commentId' => $item['id'],
                    'author' => $item['full_name'] ?: $item['username'],
                    'avatar' => $item['avatar'] ? 'assets/avatars/' . $item['avatar'] : 'assets/avatars/default.png',
                    'text' => $item['comment_text'] ?? '',
                    'time' => $item['created_at_formatted'] ?? 'Just now',
                    'viewer_reaction' => $item['viewer_reaction'] ?? null,
                    'reaction_count' => $item['reaction_count'] ?? 0,
                    'can_delete' => !empty($item['can_delete']),
                    'replies' => $replies,
                ];
            };
            return $mapComment($c);
        }, $comments),
    ];
    echo json_encode($response);
    exit;
}

function handleUpdatePost($db, $user_id) {
    $response = ['success' => false, 'message' => ''];
    $postId = (int) ($_POST['post_id'] ?? 0);
    $content = trim($_POST['content'] ?? '');
    $feeling = trim($_POST['feeling'] ?? '');
    $removePhoto = ($_POST['remove_photo'] ?? '') === '1';
    
    $stmt = $db->prepare("SELECT id, image_path FROM posts WHERE id = ? AND user_id = ?");
    $stmt->execute([$postId, $user_id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        $response['message'] = 'Post not found';
        echo json_encode($response);
        exit;
    }
    
    // Handle photo upload or removal
    $newImagePath = $existing['image_path'];
    if ($removePhoto) {
        if ($existing['image_path']) {
            $oldFile = __DIR__ . '/../assets/posts/' . $existing['image_path'];
            if (file_exists($oldFile)) unlink($oldFile);
        }
        $newImagePath = null;
    } elseif (isset($_FILES['post_image']) && $_FILES['post_image']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['post_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($ext, $allowed)) {
            $dir = __DIR__ . '/../assets/posts/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $filename = 'post_' . $user_id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['post_image']['tmp_name'], $dir . $filename)) {
                // Delete old image
                if ($existing['image_path']) {
                    $oldFile = __DIR__ . '/../assets/posts/' . $existing['image_path'];
                    if (file_exists($oldFile)) unlink($oldFile);
                }
                $newImagePath = $filename;
            }
        }
    }
    
    $stmt = $db->prepare("UPDATE posts SET content = ?, image_path = ?, feeling = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$content, $newImagePath, $feeling ?: null, $postId]);
    
    $response['success'] = true;
    $response['message'] = 'Post updated';
    $response['post'] = [
        'id' => $postId,
        'content' => $content,
        'image_path' => $newImagePath,
        'feeling' => $feeling ?: null,
    ];
    echo json_encode($response);
    exit;
}

function handleCreatePost($db, $user_id) {
    $response = ['success' => false, 'message' => ''];
    $content = trim($_POST['content'] ?? '');
    $privacy = in_array($_POST['privacy'] ?? '', ['public', 'friends', 'private']) ? $_POST['privacy'] : 'public';
    
    $feeling = trim($_POST['feeling'] ?? '');
    
    if (empty($content) && empty($_FILES['post_image']['name']) && empty($feeling)) {
        $response['message'] = 'Write something or add a photo';
        echo json_encode($response);
        exit;
    }
    
    $imagePath = null;
    if (isset($_FILES['post_image']) && $_FILES['post_image']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['post_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($ext, $allowed)) {
            $dir = __DIR__ . '/../assets/posts/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $filename = 'post_' . $user_id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['post_image']['tmp_name'], $dir . $filename)) {
                $imagePath = $filename;
            }
        }
    }
    
    $stmt = $db->prepare("INSERT INTO posts (user_id, content, image_path, feeling, privacy) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $content, $imagePath, $feeling ?: null, $privacy]);
    $postId = (int) $db->lastInsertId();
    
    $stmt = $db->prepare("SELECT u.username, u.full_name, u.avatar FROM users u WHERE u.id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    $response['success'] = true;
    $response['message'] = 'Post created';
    $response['post'] = [
        'id' => $postId,
        'user_id' => $user_id,
        'content' => $content,
        'image_path' => $imagePath,
        'feeling' => $feeling ?: null,
        'privacy' => $privacy,
        'full_name' => $user['full_name'] ?? '',
        'username' => $user['username'] ?? '',
        'avatar' => $user['avatar'] ?? 'default.png',
        'like_count' => 0,
        'comment_count' => 0,
        'share_count' => 0,
        'can_delete' => true,
    ];
    echo json_encode($response);
    exit;
}

function handlePreferencesUpdate($db, $user_id) {
    $response = ['success' => false, 'message' => ''];
    $theme = trim($_POST['theme'] ?? 'light');
    $notifications = $_POST['notifications'] ?? [];
    
    $stmt = $db->prepare("SELECT preferences FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $existingPrefs = $stmt->fetchColumn();
    $prefs = $existingPrefs ? json_decode($existingPrefs, true) : [];
    $prefs['theme'] = $theme;
    $prefs['notifications'] = $notifications;
    
    $stmt = $db->prepare("UPDATE users SET preferences = ? WHERE id = ?");
    $stmt->execute([json_encode($prefs), $user_id]);
    
    $_SESSION['theme'] = $theme;
    $response['success'] = true;
    $response['message'] = 'Preferences updated';
    echo json_encode($response);
    exit;
}

function handleCoverUpload($db, $user_id) {
    $response = ['success' => false, 'message' => ''];
    if (!isset($_FILES['cover'])) {
        $response['message'] = 'No file uploaded';
        echo json_encode($response);
        exit;
    }
    $file = $_FILES['cover'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($ext, $allowed)) {
        $response['message'] = 'Invalid file type';
        echo json_encode($response);
        exit;
    }
    $dir = __DIR__ . '/../assets/covers/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $filename = 'cover_' . $user_id . '_' . time() . '.' . $ext;
    $path = $dir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $path)) {
        $response['message'] = 'Upload failed';
        echo json_encode($response);
        exit;
    }
    $stmt = $db->prepare("UPDATE users SET cover_image = ? WHERE id = ?");
    $stmt->execute([$filename, $user_id]);
    $response['success'] = true;
    $response['cover_url'] = 'assets/covers/' . $filename;
    $response['message'] = 'Cover updated';
    echo json_encode($response);
    exit;
}

function handlePrivacyUpdate($db, $user_id) {
    $response = ['success' => false, 'message' => ''];
    
    $profile_privacy = $_POST['profile_privacy'] ?? 'public';
    $post_default_privacy = $_POST['post_default_privacy'] ?? 'public';
    
    $stmt = $db->prepare("
        UPDATE users 
        SET profile_privacy = ?, post_default_privacy = ?
        WHERE id = ?
    ");
    $stmt->execute([$profile_privacy, $post_default_privacy, $user_id]);
    
    $response['success'] = true;
    $response['message'] = 'Privacy settings updated';
    echo json_encode($response);
    exit;
}

function handleDeleteMessage($db, $userId, $data) {
    $msgId = (int) ($data['message_id'] ?? 0);
    if (!$msgId) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }
    $stmt = $db->prepare("DELETE FROM messages WHERE id = ? AND sender_id = ?");
    $stmt->execute([$msgId, $userId]);
    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Message not found']);
        exit;
    }
    $unreadStmt = $db->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
    $unreadStmt->execute([$userId]);
    $notifStmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $notifStmt->execute([$userId]);
    echo json_encode(['success' => true, 'message' => 'Message deleted', 'counts' => ['messages' => (int) $unreadStmt->fetchColumn(), 'notifications' => (int) $notifStmt->fetchColumn()]]);
    exit;
}

function handleTogglePinMessage($db, $userId, $data) {
    $msgId = (int) ($data['message_id'] ?? 0);
    if (!$msgId) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }
    $stmt = $db->prepare("SELECT COUNT(*) FROM message_pins WHERE message_id = ? AND user_id = ?");
    $stmt->execute([$msgId, $userId]);
    $isPinned = (int) $stmt->fetchColumn() > 0;
    if ($isPinned) {
        $db->prepare("DELETE FROM message_pins WHERE message_id = ? AND user_id = ?")->execute([$msgId, $userId]);
        $isPinned = false;
    } else {
        $db->prepare("INSERT IGNORE INTO message_pins (message_id, user_id) VALUES (?, ?)")->execute([$msgId, $userId]);
        $isPinned = true;
    }
    echo json_encode(['success' => true, 'message' => $isPinned ? 'Message pinned' : 'Message unpinned', 'is_pinned' => $isPinned]);
    exit;
}

function handleGetRecentThreads($db, $userId) {
    $threads = getMessageThreads($db, $userId);
    echo json_encode(['success' => true, 'threads' => $threads]);
    exit;
}

function handleLoadMoreMessages($db, $userId, $data) {
    $otherUserId = (int) ($data['other_user_id'] ?? 0);
    $beforeId = (int) ($data['before_message_id'] ?? 0);
    $afterId = (int) ($data['after_message_id'] ?? 0);
    if ($otherUserId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }
    $messages = getConversationMessages($db, $userId, $otherUserId, 50, $beforeId ?: null, $afterId ?: null);
    $hasMore = count($messages) >= 50;
    foreach ($messages as &$msg) {
        $msg['is_pinned'] = (bool) $msg['is_pinned'];
        $msg['is_read'] = (bool) $msg['is_read'];
        $msg['created_at_formatted'] = date('g:i A', strtotime((string) $msg['created_at']));
        $msg['created_at_exact'] = date('M j, Y g:i A', strtotime((string) $msg['created_at']));
    }
    
    // Get latest counts
    $unreadStmt = $db->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
    $unreadStmt->execute([$userId]);
    $mCount = (int) $unreadStmt->fetchColumn();
    
    $notifStmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $notifStmt->execute([$userId]);
    $nCount = (int) $notifStmt->fetchColumn();
    
    echo json_encode([
        'success' => true, 
        'messages' => $messages, 
        'has_more' => $hasMore,
        'counts' => ['messages' => $mCount, 'notifications' => $nCount]
    ]);
    exit;
}

function handleGetPinnedMessages($db, $userId, $data) {
    $otherUserId = (int) ($data['other_user_id'] ?? 0);
    if ($otherUserId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }
    $stmt = $db->prepare("
        SELECT m.*, u.username, u.full_name, u.avatar,
               EXISTS(SELECT 1 FROM message_pins mp WHERE mp.message_id = m.id AND mp.user_id = ?) AS is_pinned,
               (SELECT reaction_type FROM message_reactions WHERE message_id = m.id AND user_id = ?) AS viewer_reaction
        FROM messages m
        INNER JOIN users u ON u.id = m.sender_id
        INNER JOIN message_pins mp2 ON mp2.message_id = m.id AND mp2.user_id = ?
        WHERE ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
        ORDER BY m.created_at DESC
    ");
    $stmt->execute([$userId, $userId, $userId, $userId, $otherUserId, $otherUserId, $userId]);
    $messages = $stmt->fetchAll();
    
    $messageIds = array_column($messages, 'id');
    $reactionsByMsg = [];
    if (!empty($messageIds)) {
        $ids = array_map('intval', $messageIds);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        try {
            $stmtR = $db->prepare("SELECT message_id, reaction_type, COUNT(*) AS cnt FROM message_reactions WHERE message_id IN ($placeholders) GROUP BY message_id, reaction_type");
            $stmtR->execute($ids);
            while ($row = $stmtR->fetch()) {
                $reactionsByMsg[(int) $row['message_id']][] = ['reaction_type' => $row['reaction_type'], 'count' => (int) $row['cnt']];
            }
        } catch (Throwable $e) {}
    }
    
    foreach ($messages as &$msg) {
        $msg['is_pinned'] = true;
        $msg['is_read'] = (bool) $msg['is_read'];
        $msg['reaction_summary'] = $reactionsByMsg[(int) $msg['id']] ?? [];
        $msg['created_at_formatted'] = date('g:i A', strtotime((string) $msg['created_at']));
        $msg['created_at_exact'] = date('M j, Y g:i A', strtotime((string) $msg['created_at']));
    }
    echo json_encode(['success' => true, 'messages' => $messages]);
    exit;
}

function handleMarkThreadRead($db, $userId, $data) {
    $otherUserId = (int) ($data['other_user_id'] ?? 0);
    if ($otherUserId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }
    $stmt = $db->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
    $stmt->execute([$otherUserId, $userId]);
    $unreadStmt = $db->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
    $unreadStmt->execute([$userId]);
    $notifStmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $notifStmt->execute([$userId]);
    echo json_encode(['success' => true, 'counts' => ['messages' => (int) $unreadStmt->fetchColumn(), 'notifications' => (int) $notifStmt->fetchColumn()]]);
    exit;
}

function handleReactMessage($db, $userId, $data) {
    $msgId = (int) ($data['message_id'] ?? 0);
    $reaction = $data['reaction'] ?? '';
    if (!$msgId || !$reaction) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }
    $allowed = ['👍', '❤️', '😂', '😮', '😢'];
    if (!in_array($reaction, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Invalid reaction']);
        exit;
    }
    $stmt = $db->prepare("SELECT id, reaction_type FROM message_reactions WHERE message_id = ? AND user_id = ?");
    $stmt->execute([$msgId, $userId]);
    $existing = $stmt->fetch();
    if ($existing) {
        if ($existing['reaction_type'] === $reaction) {
            $db->prepare("DELETE FROM message_reactions WHERE id = ?")->execute([$existing['id']]);
            $reaction = null;
        } else {
            $db->prepare("UPDATE message_reactions SET reaction_type = ? WHERE id = ?")->execute([$reaction, $existing['id']]);
        }
    } else {
        $db->prepare("INSERT INTO message_reactions (message_id, user_id, reaction_type) VALUES (?, ?, ?)")->execute([$msgId, $userId, $reaction]);
    }
    $stmt = $db->prepare("SELECT reaction_type FROM message_reactions WHERE message_id = ? AND user_id = ?");
    $stmt->execute([$msgId, $userId]);
    $viewerReaction = $stmt->fetchColumn();
    $stmt = $db->prepare("SELECT mr.reaction_type, COUNT(*) AS count FROM message_reactions mr WHERE mr.message_id = ? GROUP BY mr.reaction_type");
    $stmt->execute([$msgId]);
    $summary = $stmt->fetchAll();
    echo json_encode(['success' => true, 'viewer_reaction' => $viewerReaction ?: null, 'reaction_summary' => $summary]);
    exit;
}

function handleReportMessage($db, $userId, $data) {
    $msgId = (int) ($data['message_id'] ?? 0);
    $reason = trim($data['reason'] ?? '');
    if (!$msgId) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }
    try {
        $stmt = $db->prepare("INSERT INTO report_messages (reporter_id, message_id, reason) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $msgId, $reason ?: null]);
        echo json_encode(['success' => true, 'message' => 'Message reported']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to submit report']);
    }
    exit;
}

function handleHeartbeat($db, $userId) {
    try {
        $token = session_id();
        $db->prepare("DELETE FROM user_sessions WHERE user_id = ? AND expires_at <= NOW()")->execute([$userId]);
        $stmt = $db->prepare("UPDATE user_sessions SET last_activity = UNIX_TIMESTAMP(), expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR) WHERE user_id = ? AND session_token = ?");
        $stmt->execute([$userId, $token]);
        if ($stmt->rowCount() === 0) {
            $stmt = $db->prepare("INSERT IGNORE INTO user_sessions (session_token, user_id, last_activity, expires_at) VALUES (?, ?, UNIX_TIMESTAMP(), DATE_ADD(NOW(), INTERVAL 24 HOUR))");
            $stmt->execute([$token, $userId]);
        }
        
        // Get counts
        $unreadStmt = $db->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
        $unreadStmt->execute([$userId]);
        $mCount = (int) $unreadStmt->fetchColumn();
        
        $notifStmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $notifStmt->execute([$userId]);
        $nCount = (int) $notifStmt->fetchColumn();
        
        echo json_encode(['success' => true, 'counts' => ['messages' => $mCount, 'notifications' => $nCount]]);
    } catch (Exception $e) {
        echo json_encode(['success' => false]);
    }
    exit;
}

function handleGetFriends($db, $userId) {
    $friends = getAllFriends($db, $userId);
    echo json_encode(['success' => true, 'friends' => $friends]);
    exit;
}

function handlePinConversation($db, $userId, $data) {
    $otherUserId = (int) ($data['other_user_id'] ?? 0);
    if ($otherUserId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }
    $stmt = $db->prepare("SELECT id FROM pinned_conversations WHERE user_id = ? AND other_user_id = ?");
    $stmt->execute([$userId, $otherUserId]);
    if ($stmt->fetch()) {
        $db->prepare("DELETE FROM pinned_conversations WHERE user_id = ? AND other_user_id = ?")->execute([$userId, $otherUserId]);
        echo json_encode(['success' => true, 'pinned' => false, 'message' => 'Conversation unpinned']);
    } else {
        $cnt = $db->prepare("SELECT COUNT(*) FROM pinned_conversations WHERE user_id = ?");
        $cnt->execute([$userId]);
        if ((int) $cnt->fetchColumn() >= 10) {
            echo json_encode(['success' => false, 'message' => 'Maximum 10 pinned conversations allowed']);
            exit;
        }
        $db->prepare("INSERT INTO pinned_conversations (user_id, other_user_id) VALUES (?, ?)")->execute([$userId, $otherUserId]);
        echo json_encode(['success' => true, 'pinned' => true, 'message' => 'Conversation pinned']);
    }
    exit;
}

function handleSearchUsers($db, $userId, $data) {
    $query = trim($data['query'] ?? '');
    if (strlen($query) < 1) {
        echo json_encode(['success' => false, 'message' => 'Query too short', 'users' => []]);
        exit;
    }
    $users = searchUsers($db, $userId, $query);
    echo json_encode(['success' => true, 'users' => $users]);
    exit;
}

function handleForwardMessage($db, $userId, $data) {
    $msgId = (int) ($data['message_id'] ?? 0);
    $receiverId = (int) ($data['receiver_id'] ?? 0);
    if (!$msgId || !$receiverId || $receiverId === $userId) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }
    $stmt = $db->prepare("SELECT body, image_path FROM messages WHERE id = ? AND (sender_id = ? OR receiver_id = ?)");
    $stmt->execute([$msgId, $userId, $userId]);
    $original = $stmt->fetch();
    if (!$original) {
        echo json_encode(['success' => false, 'message' => 'Message not found']);
        exit;
    }
    $forwardBody = '';
    if (!empty($original['body'])) {
        $forwardBody = trim($original['body']);
    }
    $forwardImage = null;
    if (!empty($original['image_path'])) {
        $forwardImage = $original['image_path'];
    }
    $stmt = $db->prepare("INSERT INTO messages (sender_id, receiver_id, body, image_path) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $receiverId, $forwardBody, $forwardImage]);
    $newId = (int) $db->lastInsertId();
    $stmt = $db->prepare("
        SELECT m.*, u.username, u.full_name, u.avatar
        FROM messages m INNER JOIN users u ON u.id = m.sender_id WHERE m.id = ?
    ");
    $stmt->execute([$newId]);
    $msg = $stmt->fetch();
    echo json_encode(['success' => true, 'message' => 'Message forwarded', 'message_item' => $msg]);
    exit;
}

function handleToggleLike($db, $userId) {
    $data = ['post_id' => $_POST['post_id'] ?? 0, 'reaction' => $_POST['reaction'] ?? 'like'];
    handleReactPost($db, $userId, $data);
}

function handleAddComment($db, $userId) {
    $postId = (int) ($_POST['post_id'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');
    $parentId = (int) ($_POST['parent_comment_id'] ?? 0);
    if (!$postId || !$comment) { echo json_encode(['success' => false, 'message' => 'Missing fields']); exit; }

    // Max 2 levels deep: if replying to a depth-2+ comment, flatten to the depth-1 ancestor
    if ($parentId > 0) {
        $chain = [];
        $currentId = $parentId;
        $stmt = $db->prepare("SELECT id, parent_comment_id FROM post_comments WHERE id = ?");
        for ($i = 0; $i < 20; $i++) {
            $stmt->execute([$currentId]);
            $target = $stmt->fetch();
            if (!$target) break;
            $chain[] = (int) $target['id'];
            if (empty($target['parent_comment_id'])) break;
            $currentId = (int) $target['parent_comment_id'];
        }
        // chain = [target, depth-1, root]; if >= 3 entries, flatten to depth-1 ancestor
        if (count($chain) >= 3) {
            $parentId = $chain[count($chain) - 2];
        }
    }

    $stmt = $db->prepare("INSERT INTO post_comments (post_id, user_id, comment_text, parent_comment_id) VALUES (?, ?, ?, ?)");
    $stmt->execute([$postId, $userId, $comment, $parentId ?: null]);
    $commentId = (int) $db->lastInsertId();

    $stmt = $db->prepare("SELECT u.username, u.full_name, u.avatar FROM users u WHERE u.id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    $stmt = $db->prepare("SELECT COUNT(*) FROM post_comments WHERE post_id = ?");
    $stmt->execute([$postId]);
    $commentCount = (int) $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT user_id FROM posts WHERE id = ?");
    $stmt->execute([$postId]);
    $authorId = (int) $stmt->fetchColumn();

    $commentData = [
        'id' => $commentId,
        'post_id' => $postId,
        'user_id' => $userId,
        'comment_text' => $comment,
        'parent_comment_id' => $parentId ?: null,
        'full_name' => $user['full_name'] ?? '',
        'username' => $user['username'] ?? '',
        'avatar' => $user['avatar'] ?? 'default.png',
        'created_at' => date('Y-m-d H:i:s'),
        'created_at_formatted' => 'Just now',
        'can_delete' => true,
        'viewer_reaction' => null,
        'reaction_summary' => [],
        'reaction_count' => 0,
        'replies' => [],
    ];

    // Notify post author (store commentId as entity_id for comment-scroll support)
    if ($authorId && $authorId !== $userId) {
        createNotification($db, $authorId, $userId, 'comment', 'commented on your post', $commentId);
    }

    // Notify parent comment author if it's a reply
    if ($parentId) {
        $parentStmt = $db->prepare("SELECT user_id FROM post_comments WHERE id = ?");
        $parentStmt->execute([$parentId]);
        $parentAuthorId = (int) $parentStmt->fetchColumn();
        if ($parentAuthorId && $parentAuthorId !== $userId && $parentAuthorId !== $authorId) {
            createNotification($db, $parentAuthorId, $userId, 'reply', 'replied to your comment', $commentId);
        }
    }

    // Handle mentions: @username
    preg_match_all('/@([a-zA-Z0-9_]+)/', $comment, $mentions);
    if (!empty($mentions[1])) {
        $mentionedUsernames = array_unique($mentions[1]);
        $placeholders = implode(',', array_fill(0, count($mentionedUsernames), '?'));
        $mentionStmt = $db->prepare("SELECT id FROM users WHERE username IN ($placeholders)");
        $mentionStmt->execute($mentionedUsernames);
        $mentionedUserIds = $mentionStmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($mentionedUserIds as $mUid) {
            $mUid = (int) $mUid;
            if ($mUid !== $userId && $mUid !== $authorId) {
                createNotification($db, $mUid, $userId, 'mention', 'mentioned you in a comment', $commentId);
            }
        }
    }

    echo json_encode(['success' => true, 'comment' => $commentData, 'comment_count' => $commentCount]);
    exit;
}

function handleSharePost($db, $userId) {
    $postId = (int) ($_POST['post_id'] ?? 0);
    if (!$postId) { echo json_encode(['success' => false, 'message' => 'No post ID']); exit; }

    $stmt = $db->prepare("SELECT id FROM post_shares WHERE post_id = ? AND user_id = ?");
    $stmt->execute([$postId, $userId]);
    if (!$stmt->fetch()) {
        $db->prepare("INSERT INTO post_shares (post_id, user_id) VALUES (?, ?)")->execute([$postId, $userId]);
    }

    $stmt = $db->prepare("SELECT COUNT(*) FROM post_shares WHERE post_id = ?");
    $stmt->execute([$postId]);
    $shareCount = (int) $stmt->fetchColumn();

    echo json_encode(['success' => true, 'share_count' => $shareCount, 'message' => 'Post shared']);
    exit;
}

function handleDeletePost($db, $userId) {
    $postId = (int) ($_POST['post_id'] ?? 0);
    if (!$postId) { echo json_encode(['success' => false, 'message' => 'No post ID']); exit; }

    $stmt = $db->prepare("SELECT user_id FROM posts WHERE id = ?");
    $stmt->execute([$postId]);
    $authorId = (int) $stmt->fetchColumn();
    if ($authorId !== $userId) { echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit; }

    $db->prepare("DELETE FROM post_likes WHERE post_id = ?")->execute([$postId]);
    $db->prepare("DELETE FROM post_shares WHERE post_id = ?")->execute([$postId]);
    $commentIds = $db->prepare("SELECT id FROM post_comments WHERE post_id = ?");
    $commentIds->execute([$postId]);
    foreach ($commentIds->fetchAll(PDO::FETCH_COLUMN) as $cid) {
        $db->prepare("DELETE FROM comment_reactions WHERE comment_id = ?")->execute([$cid]);
    }
    $db->prepare("DELETE FROM post_comments WHERE post_id = ?")->execute([$postId]);
    $db->prepare("DELETE FROM posts WHERE id = ?")->execute([$postId]);

    echo json_encode(['success' => true, 'message' => 'Post deleted']);
    exit;
}

function handleDeleteComment($db, $userId) {
    $commentId = (int) ($_POST['comment_id'] ?? 0);
    if (!$commentId) { echo json_encode(['success' => false, 'message' => 'No comment ID']); exit; }

    $stmt = $db->prepare("SELECT pc.id, pc.user_id, pc.post_id FROM post_comments pc WHERE pc.id = ?");
    $stmt->execute([$commentId]);
    $comment = $stmt->fetch();
    if (!$comment) { echo json_encode(['success' => false, 'message' => 'Comment not found']); exit; }

    $stmt = $db->prepare("SELECT user_id FROM posts WHERE id = ?");
    $stmt->execute([$comment['post_id']]);
    $postAuthorId = (int) $stmt->fetchColumn();

    $isAuthor = ((int) $comment['user_id'] === $userId);
    $isPostOwner = ($postAuthorId === $userId);
    if (!$isAuthor && !$isPostOwner) { echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit; }

    // Delete replies first
    $db->prepare("DELETE FROM comment_reactions WHERE comment_id IN (SELECT id FROM post_comments WHERE parent_comment_id = ?)")->execute([$commentId]);
    $db->prepare("DELETE FROM post_comments WHERE parent_comment_id = ?")->execute([$commentId]);
    $db->prepare("DELETE FROM comment_reactions WHERE comment_id = ?")->execute([$commentId]);
    $db->prepare("DELETE FROM post_comments WHERE id = ?")->execute([$commentId]);

    $stmt = $db->prepare("SELECT COUNT(*) FROM post_comments WHERE post_id = ?");
    $stmt->execute([$comment['post_id']]);
    $commentCount = (int) $stmt->fetchColumn();

    echo json_encode(['success' => true, 'comment_count' => $commentCount, 'message' => 'Comment deleted']);
    exit;
}

function handleUpdateComment($db, $userId) {
    $commentId = (int) ($_POST['comment_id'] ?? 0);
    $text = trim($_POST['comment'] ?? '');
    if (!$commentId || !$text) { echo json_encode(['success' => false, 'message' => 'Missing fields']); exit; }

    $stmt = $db->prepare("SELECT user_id FROM post_comments WHERE id = ?");
    $stmt->execute([$commentId]);
    $authorId = (int) $stmt->fetchColumn();
    if ($authorId !== $userId) { echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit; }

    $db->prepare("UPDATE post_comments SET comment_text = ? WHERE id = ?")->execute([$text, $commentId]);

    echo json_encode(['success' => true, 'message' => 'Comment updated']);
    exit;
}

function handleToggleCommentReaction($db, $userId) {
    $commentId = (int) ($_POST['comment_id'] ?? 0);
    $reaction = trim($_POST['reaction'] ?? 'like');
    if (!$commentId) { echo json_encode(['success' => false, 'message' => 'No comment ID']); exit; }
    if (!in_array($reaction, ['like','love','haha','wow','sad'], true)) $reaction = 'like';

    $stmt = $db->prepare("SELECT id, reaction_type FROM comment_reactions WHERE comment_id = ? AND user_id = ?");
    $stmt->execute([$commentId, $userId]);
    $existing = $stmt->fetch();

    if ($existing) {
        if ($existing['reaction_type'] === $reaction) {
            $db->prepare("DELETE FROM comment_reactions WHERE id = ?")->execute([$existing['id']]);
            $viewerReaction = null;
        } else {
            $db->prepare("UPDATE comment_reactions SET reaction_type = ? WHERE id = ?")->execute([$reaction, $existing['id']]);
            $viewerReaction = $reaction;
        }
    } else {
        $db->prepare("INSERT INTO comment_reactions (comment_id, user_id, reaction_type) VALUES (?, ?, ?)")->execute([$commentId, $userId, $reaction]);
        $viewerReaction = $reaction;
    }

    $summary = getCommentReactionSummary($db, $commentId);
    $summaryArr = [];
    foreach ($summary as $type => $count) {
        $summaryArr[] = ['type' => $type, 'count' => $count, 'meta' => getReactionMeta($type)];
    }
    $reactionCount = array_sum($summary);

    // Notify comment author (store commentId as entity_id for comment-scroll support)
    if ($viewerReaction) {
        $stmt = $db->prepare("SELECT user_id, post_id FROM post_comments WHERE id = ?");
        $stmt->execute([$commentId]);
        $comment = $stmt->fetch();
        if ($comment && (int)$comment['user_id'] !== $userId) {
            createNotification($db, (int)$comment['user_id'], $userId, 'comment_reaction', 'liked your comment', $commentId);
        }
    }

    echo json_encode(['success' => true, 'viewer_reaction' => $viewerReaction, 'reaction_summary' => $summaryArr, 'reaction_count' => $reactionCount]);
    exit;
}

function handleReportComment($db, $userId) {
    $commentId = (int) ($_POST['comment_id'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    if (!$commentId) { echo json_encode(['success' => false, 'message' => 'No comment ID']); exit; }

    try {
        $stmt = $db->prepare("INSERT INTO report_comments (reporter_id, comment_id, reason) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $commentId, $reason ?: null]);
        echo json_encode(['success' => true, 'message' => 'Comment reported']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to submit report']);
    }
    exit;
}

function handleSavePost($db, $userId) {
    $postId = (int) ($_POST['post_id'] ?? 0);
    if (!$postId) { echo json_encode(['success' => false, 'message' => 'No post ID']); exit; }

    $stmt = $db->prepare("SELECT id FROM saved_posts WHERE post_id = ? AND user_id = ?");
    $stmt->execute([$postId, $userId]);
    if ($stmt->fetch()) {
        $db->prepare("DELETE FROM saved_posts WHERE post_id = ? AND user_id = ?")->execute([$postId, $userId]);
        echo json_encode(['success' => true, 'saved' => false, 'message' => 'Post unsaved']);
    } else {
        $db->prepare("INSERT INTO saved_posts (post_id, user_id) VALUES (?, ?)")->execute([$postId, $userId]);
        echo json_encode(['success' => true, 'saved' => true, 'message' => 'Post saved']);
    }
    exit;
}

function handleReportPost($db, $userId) {
    $postId = (int) ($_POST['post_id'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    if (!$postId) { echo json_encode(['success' => false, 'message' => 'No post ID']); exit; }

    $stmt = $db->prepare("SELECT id FROM post_reports WHERE post_id = ? AND user_id = ?");
    $stmt->execute([$postId, $userId]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'already_reported' => true, 'message' => 'Already reported']);
        exit;
    }

    $db->prepare("INSERT INTO post_reports (post_id, user_id, reason) VALUES (?, ?, ?)")->execute([$postId, $userId, $reason]);

    // Notify user that their report was received
    createNotification($db, $userId, null, 'report_received', 'Your report has been received. Our team will review it shortly.');

    echo json_encode(['success' => true, 'message' => 'Post reported']);
    exit;
}

function handleGetContacts($db, $userId) {
    $contacts = getMessageContacts($db, $userId, 15);
    echo json_encode(['success' => true, 'contacts' => $contacts]);
    exit;
}

function handleGetPostDetails($db, $userId) {
    $postId = (int) ($_GET['post_id'] ?? 0);
    if ($postId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid post ID']);
        exit;
    }

    $post = getPostDetails($db, $postId, $userId);
    if (!$post) {
        echo json_encode(['success' => false, 'message' => 'Post not found']);
        exit;
    }

    echo json_encode(['success' => true, 'post' => $post]);
    exit;
}

function handleReactPost($db, $userId, $data) {
    $postId = (int) ($data['post_id'] ?? 0);
    $reaction = $data['reaction'] ?? 'like';
    if ($postId <= 0) { echo json_encode(['success' => false]); exit; }

    // Check if the user already has a reaction on this post
    $stmt = $db->prepare("SELECT reaction_type FROM post_likes WHERE post_id = ? AND user_id = ?");
    $stmt->execute([$postId, $userId]);
    $existing = $stmt->fetchColumn();

    if ($existing === $reaction) {
        // If same reaction, remove it (toggle off)
        $db->prepare("DELETE FROM post_likes WHERE post_id = ? AND user_id = ?")->execute([$postId, $userId]);
        $newReaction = null;
    } else {
        // If different reaction or no reaction, update/insert (toggle on or switch)
        if ($existing) {
            $db->prepare("UPDATE post_likes SET reaction_type = ? WHERE post_id = ? AND user_id = ?")
               ->execute([$reaction, $postId, $userId]);
        } else {
            $db->prepare("INSERT INTO post_likes (post_id, user_id, reaction_type) VALUES (?, ?, ?)")
               ->execute([$postId, $userId, $reaction]);
        }
        $newReaction = $reaction;
    }

    // Get updated total count for this post
    $totalStmt = $db->prepare("SELECT COUNT(*) FROM post_likes WHERE post_id = ?");
    $totalStmt->execute([$postId]);
    $totalCount = (int) $totalStmt->fetchColumn();

    // Notify post author if adding a reaction
    if ($newReaction) {
        $authorStmt = $db->prepare("SELECT user_id FROM posts WHERE id = ?");
        $authorStmt->execute([$postId]);
        $authorId = (int) $authorStmt->fetchColumn();
        if ($authorId && $authorId !== $userId) {
            createNotification($db, $authorId, $userId, 'reaction', 'reacted to your post', $postId);
        }
    }

    // Build reaction summary for live icon update
    $rawSummary = getReactionSummary($db, $postId);
    $summary = [];
    foreach ($rawSummary as $type => $count) {
        $summary[] = ['type' => $type, 'count' => $count, 'meta' => getReactionMeta($type)];
    }

    echo json_encode([
        'success' => true,
        'count' => $totalCount,
        'like_count' => $totalCount,
        'liked' => !empty($newReaction),
        'viewer_reaction' => $newReaction,
        'reaction_summary' => $summary
    ]);
    exit;
}

function handleGetMoreComments($db, $userId) {
    $postId = (int) ($_GET['post_id'] ?? 0);
    $offset = (int) ($_GET['offset'] ?? 0);
    if ($postId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid post ID']);
        exit;
    }

    $stmt = $db->prepare("SELECT user_id FROM posts WHERE id = ?");
    $stmt->execute([$postId]);
    $post = $stmt->fetch();
    if (!$post) {
        echo json_encode(['success' => false, 'message' => 'Post not found']);
        exit;
    }

    $comments = getPostComments($db, $postId, 10, (int) $userId, (int) $post['user_id'], $offset);

    echo json_encode(['success' => true, 'comments' => $comments]);
    exit;
}
