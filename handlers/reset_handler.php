<?php
require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth_functions.php';

dream_start_session();

$auth = new Auth();
$security = new Security();

header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'errors' => [], 'redirect' => null];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$security->validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $response['message'] = 'Invalid security token.';
        $response['errors']['global'] = 'Invalid security token.';
    } else {
        $token = trim($_POST['token'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (empty($token)) {
            $response['message'] = 'Missing reset token.';
            $response['errors']['global'] = 'Missing reset token.';
        } elseif (strlen($password) < 8) {
            $response['message'] = 'Password must be at least 8 characters';
            $response['errors']['password'] = 'Password must be at least 8 characters';
        } elseif ($password !== $confirm) {
            $response['message'] = 'Passwords do not match';
            $response['errors']['confirm_password'] = 'Passwords do not match';
        } else {
            $result = $auth->resetPassword($token, $password);
            $response['success'] = $result['success'];
            $response['message'] = $result['message'];
            if ($result['success']) {
                $response['redirect'] = 'index.php?page=login&reset=true';
            }
        }
    }
}

echo json_encode($response);
exit;
