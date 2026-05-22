<?php
// logout.php - With AJAX support
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if it's an AJAX request
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Perform logout
$user_id = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? 'Unknown';

// Log logout event
if ($user_id) {
    try {
        require_once __DIR__ . '/database/config.php';
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("
            INSERT INTO security_logs (user_id, event_type, ip_address, user_agent, details) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id,
            'logout',
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            json_encode(['logout_time' => date('Y-m-d H:i:s')])
        ]);
    } catch (Exception $e) {
        error_log("Logout logging error: " . $e->getMessage());
    }
}

// Clear session
$_SESSION = [];

// Destroy session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

session_destroy();

// Clear all auth cookies
$cookies = ['remember_token', 'remember_me', 'remember_identifier'];
foreach ($cookies as $cookie) {
    if (isset($_COOKIE[$cookie])) {
        setcookie($cookie, '', time() - 3600, '/');
    }
}

ob_end_clean();

// Respond based on request type
if ($is_ajax) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Logout successful',
        'redirect' => 'index.php?page=home'
    ]);
    exit();
} else {
    header('Location: index.php?page=home&logout=success');
    exit();
}