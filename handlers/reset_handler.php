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
        $email = trim($_POST['email'] ?? $_SESSION['reset_email'] ?? '');
        $otp = trim($_POST['otp'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (empty($otp) && empty($password)) {
            // Step 1: Send OTP
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $response['message'] = 'Please enter a valid email';
                $response['errors']['email'] = 'Please enter a valid email';
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
        } elseif (!empty($otp) && empty($password)) {
            // Step 2: Verify OTP only
            if (empty($email)) {
                $response['message'] = 'Session expired. Please start again.';
                $response['errors']['global'] = 'Session expired.';
            } elseif (!preg_match('/^\d{6}$/', $otp)) {
                $response['message'] = 'Please enter a valid 6-digit OTP';
                $response['errors']['otp'] = 'Enter a valid 6-digit OTP';
            } else {
                $result = $auth->validateResetOtp($email, $otp);
                $response['success'] = $result['success'];
                $response['message'] = $result['message'];
                if ($result['success']) {
                    $_SESSION['reset_otp_verified'] = true;
                } else {
                    $response['errors']['otp'] = $result['message'];
                }
            }
        } else {
            // Step 3: Reset password (session-verified, skip OTP re-check)
            if (empty($email)) {
                $response['message'] = 'Session expired. Please start again.';
                $response['errors']['global'] = 'Session expired.';
            } else {
                // Check if password was already reset (race-condition guard)
                try {
                    $db = Database::getInstance()->getConnection();
                    $stmt = $db->prepare("SELECT reset_token FROM users WHERE email = ? LIMIT 1");
                    $stmt->execute([$email]);
                    $pwUser = $stmt->fetch();
                } catch (PDOException $e) {
                    $pwUser = null;
                }
                if ($pwUser && $pwUser['reset_token'] === null) {
                    $response['success'] = true;
                    $response['message'] = 'Password reset successfully!';
                    unset($_SESSION['reset_email'], $_SESSION['reset_otp_verified']);
                } elseif (empty($_SESSION['reset_otp_verified'])) {
                    $response['message'] = 'OTP not verified. Please start again.';
                    $response['errors']['global'] = 'OTP not verified. Please start again.';
                    unset($_SESSION['reset_email']);
                } elseif (strlen($password) < 8) {
                    $response['message'] = 'Password must be at least 8 characters';
                    $response['errors']['password'] = 'Password must be at least 8 characters';
                } elseif ($password !== $confirm) {
                    $response['message'] = 'Passwords do not match';
                    $response['errors']['confirm_password'] = 'Passwords do not match';
                } else {
                    $result = $auth->resetPassword($email, $password);
                    $response['success'] = $result['success'];
                    $response['message'] = $result['message'];
                    if ($result['success']) {
                        unset($_SESSION['reset_email'], $_SESSION['reset_otp_verified']);
                    }
                }
            }
        }
    }
}

echo json_encode($response);
exit;
