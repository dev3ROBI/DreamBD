<?php
// upload_avatar.php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth_functions.php';
require_once __DIR__ . '/../includes/security.php';

// Start session
dream_start_session();

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !($_SESSION['logged_in'] ?? false)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check CSRF token
$security = new Security();
if (!$security->validateCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit;
}

// Initialize auth
$auth = new Auth();
$user_id = $_SESSION['user_id'];

// Validate file upload
$validation = $security->validateFileUpload($_FILES['avatar']);
if (!$validation['valid']) {
    echo json_encode(['success' => false, 'message' => implode(', ', $validation['errors'])]);
    exit;
}

// Create uploads directory if it doesn't exist
$upload_dir = __DIR__ . '/../assets/avatars/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Generate unique filename (flat structure, same as profile_handlers.php)
$filename = 'avatar_' . $user_id . '_' . time() . '.' . $validation['extension'];
$filepath = $upload_dir . $filename;

// Create image resource based on type
switch ($validation['mime']) {
    case 'image/jpeg':
        $source = imagecreatefromjpeg($_FILES['avatar']['tmp_name']);
        break;
    case 'image/png':
        $source = imagecreatefrompng($_FILES['avatar']['tmp_name']);
        break;
    case 'image/gif':
        $source = imagecreatefromgif($_FILES['avatar']['tmp_name']);
        break;
    case 'image/webp':
        $source = imagecreatefromwebp($_FILES['avatar']['tmp_name']);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Unsupported image format']);
        exit;
}

// Get original dimensions
$width = imagesx($source);
$height = imagesy($source);

// Create square thumbnail (200x200)
$thumb_size = 200;
$thumb = imagecreatetruecolor($thumb_size, $thumb_size);

// Preserve transparency for PNG and GIF
if ($validation['extension'] === 'png' || $validation['extension'] === 'gif') {
    imagealphablending($thumb, false);
    imagesavealpha($thumb, true);
    $transparent = imagecolorallocatealpha($thumb, 0, 0, 0, 127);
    imagefill($thumb, 0, 0, $transparent);
}

// Calculate cropping
$src_x = 0;
$src_y = 0;
$src_w = $width;
$src_h = $height;

if ($width > $height) {
    $src_x = ($width - $height) / 2;
    $src_w = $height;
} else {
    $src_y = ($height - $width) / 2;
    $src_h = $width;
}

// Resize and crop
imagecopyresampled($thumb, $source, 0, 0, $src_x, $src_y, $thumb_size, $thumb_size, $src_w, $src_h);

// Save thumbnail
switch ($validation['extension']) {
    case 'jpeg':
    case 'jpg':
        imagejpeg($thumb, $filepath, 90);
        break;
    case 'png':
        imagepng($thumb, $filepath, 9);
        break;
    case 'gif':
        imagegif($thumb, $filepath);
        break;
    case 'webp':
        imagewebp($thumb, $filepath, 90);
        break;
}

// Clean up
imagedestroy($source);
imagedestroy($thumb);

// Get old avatar for deletion
$old_avatar = null;
try {
    $conn = Database::getInstance()->getConnection();
    $stmt = $conn->prepare("SELECT avatar FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    $old_avatar = $user['avatar'] ?? null;
} catch (Exception $e) {
    error_log("Failed to get old avatar: " . $e->getMessage());
}

// Update database
try {
    $avatar_url = 'assets/avatars/' . $filename;
    
    $stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
    $stmt->execute([$filename, $user_id]);
    
    // Update session
    $_SESSION['avatar'] = $filename;
    
    // Delete old avatar if not default
    if ($old_avatar && $old_avatar !== 'default.png' && $old_avatar !== 'default-avatar.svg') {
        $old_path = __DIR__ . '/../assets/avatars/' . $old_avatar;
        if (file_exists($old_path) && is_file($old_path)) {
            unlink($old_path);
        }
    }
    
    // Log the avatar change
    $auth->logSecurityEvent($user_id, 'avatar_updated', [
        'old_avatar' => $old_avatar,
        'new_avatar' => $avatar_url
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Avatar updated successfully',
        'avatar_url' => $avatar_url
    ]);
    
} catch (Exception $e) {
    error_log("Avatar update error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to update avatar']);
}
