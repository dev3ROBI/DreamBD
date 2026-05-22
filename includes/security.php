<?php
// includes/security.php

class Security {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function generateCSRFToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public function validateCSRFToken($token) {
        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    public function checkRateLimit($action, $ip_address, $max_attempts = 5, $period = 3600) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM rate_limits 
                WHERE ip_address = ? AND action = ? AND expires_at > NOW()
            ");
            $stmt->execute([$ip_address, $action]);
            $record = $stmt->fetch();
            
            if ($record) {
                if ($record['attempts'] >= $max_attempts) {
                    return false;
                }
            }
            
            return true;
        } catch (PDOException $e) {
            error_log("Rate limit check error: " . $e->getMessage());
            return true;
        }
    }
    
    public function recordRateLimit($action, $ip_address) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO rate_limits (ip_address, action, attempts, last_attempt, expires_at) 
                VALUES (?, ?, 1, NOW(), DATE_ADD(NOW(), INTERVAL 1 HOUR))
                ON DUPLICATE KEY UPDATE 
                attempts = attempts + 1, 
                last_attempt = NOW(),
                expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR)
            ");
            $stmt->execute([$ip_address, $action]);
        } catch (PDOException $e) {
            error_log("Rate limit record error: " . $e->getMessage());
        }
    }
    
    public function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map([$this, 'sanitizeInput'], $input);
        }
        
        $input = trim($input);
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        
        return $input;
    }
    
    public function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
    
    public function validateUsername($username) {
        return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username);
    }
    
    public function validatePassword($password) {
        if (strlen($password) < DatabaseConfig::MIN_PASSWORD_LENGTH) {
            return false;
        }
        
        if (DatabaseConfig::REQUIRE_UPPERCASE && !preg_match('/[A-Z]/', $password)) {
            return false;
        }
        
        if (DatabaseConfig::REQUIRE_LOWERCASE && !preg_match('/[a-z]/', $password)) {
            return false;
        }
        
        if (DatabaseConfig::REQUIRE_NUMBERS && !preg_match('/[0-9]/', $password)) {
            return false;
        }
        
        if (DatabaseConfig::REQUIRE_SYMBOLS && !preg_match('/[^A-Za-z0-9]/', $password)) {
            return false;
        }
        
        return true;
    }
    
    public function encryptData($data) {
        $iv = random_bytes(openssl_cipher_iv_length(DatabaseConfig::ENCRYPTION_CIPHER));
        $encrypted = openssl_encrypt(
            $data,
            DatabaseConfig::ENCRYPTION_CIPHER,
            base64_decode(DatabaseConfig::getEncryptionKey()),
            0,
            $iv
        );
        return base64_encode($iv . $encrypted);
    }
    
    public function decryptData($data) {
        $data = base64_decode($data);
        $iv_length = openssl_cipher_iv_length(DatabaseConfig::ENCRYPTION_CIPHER);
        $iv = substr($data, 0, $iv_length);
        $encrypted = substr($data, $iv_length);
        
        return openssl_decrypt(
            $encrypted,
            DatabaseConfig::ENCRYPTION_CIPHER,
            base64_decode(DatabaseConfig::getEncryptionKey()),
            0,
            $iv
        );
    }
    
    public function validateFileUpload(array $file): array {
        $result = ['valid' => false, 'errors' => [], 'extension' => '', 'mime' => ''];

        if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            $result['errors'][] = 'No file uploaded or upload error.';
            return $result;
        }

        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $mime = $file['type'];

        if (!in_array($ext, $allowedExts)) {
            $result['errors'][] = 'Invalid file extension. Allowed: jpg, jpeg, png, gif, webp.';
            return $result;
        }

        if (!in_array($mime, $allowedTypes)) {
            $result['errors'][] = 'Invalid file type.';
            return $result;
        }

        if ($file['size'] > DatabaseConfig::MAX_FILE_SIZE) {
            $result['errors'][] = 'File too large. Maximum ' . (DatabaseConfig::MAX_FILE_SIZE / 1024 / 1024) . 'MB.';
            return $result;
        }

        // Verify image validity and get real MIME type
        $imgInfo = @getimagesize($file['tmp_name']);
        if (!$imgInfo) {
            $result['errors'][] = 'File is not a valid image.';
            return $result;
        }

        // Use real MIME from getimagesize, not user-supplied type
        $realMime = $imgInfo['mime'] ?? $mime;
        if (!in_array($realMime, $allowedTypes)) {
            $result['errors'][] = 'File content does not match allowed image types.';
            return $result;
        }

        $result['valid'] = true;
        $result['extension'] = $ext;
        $result['mime'] = $realMime;
        return $result;
    }

    public function generateJWT($payload) {
        $header = json_encode(['typ' => 'JWT', 'alg' => DatabaseConfig::JWT_ALGORITHM]);
        $payload['exp'] = time() + DatabaseConfig::JWT_EXPIRE;
        $payload['iat'] = time();
        
        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($payload)));
        
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, DatabaseConfig::getJwtSecret(), true);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }
    
    public function validateJWT($jwt) {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return false;
        }
        
        $signature = hash_hmac('sha256', $parts[0] . "." . $parts[1], DatabaseConfig::getJwtSecret(), true);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        if (!hash_equals($base64UrlSignature, $parts[2])) {
            return false;
        }
        
        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);
        
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return false;
        }
        
        return $payload;
    }
}
?>