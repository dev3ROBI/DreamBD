<?php
require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth_functions.php';

dream_start_session();

$auth = new Auth();
$security = new Security();

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$security->validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $response['message'] = 'Invalid security token.';
    } elseif (!isset($_SESSION['user_id'])) {
        $response['message'] = 'You must be logged in.';
    } else {
        $result = $auth->resendVerification((int) $_SESSION['user_id']);
        $response['success'] = $result['success'];
        $response['message'] = $result['success']
            ? 'Verification email sent! Check your inbox.'
            : ($result['message'] ?? 'Failed to send verification email.');
    }
}

echo json_encode($response);
exit;
