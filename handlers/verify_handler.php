<?php
require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth_functions.php';

dream_start_session();

$auth = new Auth();
$security = new Security();

header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'redirect' => null];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$security->validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $response['message'] = 'Invalid security token.';
    } else {
        $token = trim($_POST['token'] ?? '');
        if (empty($token)) {
            $response['message'] = 'Missing verification token.';
        } else {
            $result = $auth->verifyEmail($token);
            $response['success'] = $result['success'];
            $response['message'] = $result['message'];
            if ($result['success']) {
                $response['redirect'] = 'index.php?page=login&verified=true';
            }
        }
    }
}

echo json_encode($response);
exit;
