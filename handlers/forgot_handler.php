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
            $result = $auth->sendPasswordResetOtp($email);
            if ($result['success']) {
                $_SESSION['reset_email'] = $email;
                $response['success'] = true;
                $response['message'] = 'OTP sent to your email.';
            } else {
                $response['message'] = $result['message'] ?? 'Failed to send OTP.';
                $response['errors']['global'] = $response['message'];
            }
        }
    }
}

echo json_encode($response);
exit;
