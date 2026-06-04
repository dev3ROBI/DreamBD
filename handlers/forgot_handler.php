<?php
require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth_functions.php';

dream_start_session();

$auth = new Auth();
$security = new Security();

header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'errors' => []];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$security->validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $response['message'] = 'Invalid security token. Please try again.';
        $response['errors']['global'] = 'Invalid security token.';
    } else {
        $email = trim($_POST['email'] ?? '');
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response['message'] = 'Please enter a valid email address';
            $response['errors']['email'] = 'Please enter a valid email address';
        } else {
            $result = $auth->sendPasswordResetEmail($email);
            $response['success'] = true;
            $response['message'] = 'If that email is registered, we\'ve sent a password reset link.';
        }
    }
}

echo json_encode($response);
exit;
