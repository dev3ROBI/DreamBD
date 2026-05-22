<?php
// Prevent any output before JSON
ob_start();

require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$auth = new Auth();
$security = new Security();

// Set response headers
header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => '',
    'errors' => [],
    'redirect' => null,
    'clearForm' => false
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!$security->validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $response['message'] = 'Invalid security token. Please try again.';
        $response['errors']['global'] = 'Invalid security token. Please try again.';
    } else {
        $identifier = trim($_POST['identifier'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember_me = isset($_POST['remember_me']);
        
        if (empty($identifier) || empty($password)) {
            $response['message'] = 'Please fill in all fields';
            $response['errors']['global'] = 'Please fill in all fields';
        } else {
            $result = $auth->login($identifier, $password, $remember_me);
            
            if ($result['success']) {
                $response['success'] = true;
                $response['message'] = 'Login successful! Redirecting...';
                $response['redirect'] = 'index.php?page=profile';
                $response['clearForm'] = true;
                
                // Set cookie for remembering identifier
                if (isset($_POST['remember_me'])) {
                    setcookie('remember_identifier', $identifier, time() + (86400 * 30), "/");
                }
                
                // Set flag to prevent redirect loop
                $_SESSION['just_logged_in'] = true;
                // Give session time to write
                session_write_close();
            } else {
                $response['message'] = $result['errors']['global'] ?? 'Login failed. Please check your credentials.';
                $response['errors'] = $result['errors'] ?? ['global' => 'Login failed'];
                
                // Store errors in session for non-AJAX fallback
                $_SESSION['form_errors'] = $response['errors'];
            }
        }
    }
}

// Clear any output before JSON
while (ob_get_level() > 0) {
    ob_end_clean();
}

echo json_encode($response);
exit;
