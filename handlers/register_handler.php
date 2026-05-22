<?php
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
        $country_code = trim($_POST['country_code'] ?? '+1');
        $phone = trim($_POST['phone'] ?? '');
        
        // Combine country code with phone number
        if (!empty($phone)) {
            $phone = $country_code . ltrim($phone, '+');
        }
        
        $data = [
            'full_name' => trim($_POST['full_name'] ?? ''),
            'username' => trim($_POST['username'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'confirm_password' => $_POST['confirm_password'] ?? '',
            'phone' => $phone,
            'agree_terms' => isset($_POST['agree_terms']),
            'agree_privacy' => isset($_POST['agree_privacy'])
        ];
        
        $result = $auth->register($data);
        
        if ($result['success']) {
            $response['success'] = true;
            $response['message'] = $result['message'] ?? 'Registration successful! Please login.';
            $response['redirect'] = 'index.php?page=login&registered=true';
            $response['clearForm'] = true;
        } else {
            $response['message'] = 'Registration failed. Please check the form.';
            $response['errors'] = $result['errors'] ?? ['global' => 'Registration failed'];
            
            // Store form data and errors in session for non-AJAX fallback
            $_SESSION['form_data'] = $data;
            $_SESSION['form_errors'] = $response['errors'];
        }
    }
}

echo json_encode($response);
exit;