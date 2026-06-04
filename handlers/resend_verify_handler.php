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
    } else {
        $email = trim($_POST['email'] ?? '');
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response['message'] = 'Valid email is required.';
        } else {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            if (!$user) {
                $response['message'] = 'Email not found.';
            } else {
                $result = $auth->resendVerification((int) $user['id']);
                $response['success'] = $result['success'];
                $response['message'] = $result['success']
                    ? 'Verification email sent! Check your inbox.'
                    : ($result['message'] ?? 'Failed to send verification email.');
            }
        }
    }
}

echo json_encode($response);
exit;
