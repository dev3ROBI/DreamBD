<?php
// includes/auth_functions.php
include_once __DIR__ . '/security.php';
include_once __DIR__ . '/session.php';

class Auth {
    private $db;
    private $security;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->security = new Security();
    }
    
    public function register($data) {
        $errors = [];
        $normalizedPhone = $this->normalizePhone($data['phone'] ?? null);
        
        // Validate required fields
        $required = ['full_name', 'username', 'email', 'password', 'confirm_password'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
            }
        }
        
        // Validate terms agreement
        if (!$data['agree_terms'] || !$data['agree_privacy']) {
            if (!$data['agree_terms']) $errors['agree_terms'] = 'You must agree to the Terms of Service';
            if (!$data['agree_privacy']) $errors['agree_privacy'] = 'You must agree to the Privacy Policy';
        }
        
        // Validate email format
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        }
        
        // Validate username format
        if (!empty($data['username']) && !preg_match('/^[a-zA-Z0-9_]{3,20}$/', $data['username'])) {
            $errors['username'] = 'Username must be 3-20 characters and contain only letters, numbers, and underscores';
        }
        
        // Validate password - ONLY LENGTH (no complexity errors)
        if (!empty($data['password'])) {
            if (strlen($data['password']) < 8) {
                $errors['password'] = 'Password must be at least 8 characters';
            }
            // NO COMPLEXITY ERROR MESSAGES - only strength meter shows
        }
        
        // Validate password match
        if ($data['password'] !== $data['confirm_password']) {
            $errors['confirm_password'] = 'Passwords do not match';
        }
        
        // Validate phone if provided
        if (!empty($data['phone'])) {
            if (!$normalizedPhone) {
                $errors['phone'] = 'Please enter a valid phone number';
            }
        }
        
        // Check if username, email or phone already exists
        if (empty($errors)) {
            try {
                $stmt = $this->db->prepare("SELECT id, username, email, phone FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$data['username'], $data['email']]);
                $existingUsers = $stmt->fetchAll();
                
                foreach ($existingUsers as $existing) {
                    if ($existing['username'] === $data['username']) {
                        $errors['username'] = 'Username already taken';
                    }
                    
                    if ($existing['email'] === $data['email']) {
                        $errors['email'] = 'Email already registered';
                    }
                }
                
                // Check phone separately if provided
                if ($normalizedPhone) {
                    $stmt = $this->db->prepare("SELECT id FROM users WHERE phone = ? AND phone IS NOT NULL AND phone != ''");
                    $stmt->execute([$normalizedPhone]);
                    if ($stmt->rowCount() > 0) {
                        $errors['phone'] = 'Phone number already registered';
                    }
                }
            } catch (PDOException $e) {
                error_log("Registration check error: " . $e->getMessage());
                $errors['global'] = 'Registration failed. Please try again.';
            }
        }
        
        // If validation passed, create user
        if (empty($errors)) {
            try {
                $this->db->beginTransaction();
                
                // Hash password
                $password_hash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
                
                // Generate UUID
                $uuid = $this->generateUUID();
                
                // Insert user
                $stmt = $this->db->prepare("
                    INSERT INTO users (
                        uuid, username, email, password_hash, full_name, phone
                    ) VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $uuid,
                    $data['username'],
                    $data['email'],
                    $password_hash,
                    $data['full_name'],
                    $normalizedPhone
                ]);
                
                $user_id = $this->db->lastInsertId();
                
                // Log registration
                $this->logSecurityEvent($user_id, 'registration', [
                    'ip' => $_SERVER['REMOTE_ADDR'],
                    'user_agent' => $_SERVER['HTTP_USER_AGENT']
                ]);
                
                $this->db->commit();
                
                return [
                    'success' => true,
                    'message' => 'Registration successful! You can now log in.'
                ];
                
            } catch (PDOException $e) {
                $this->db->rollBack();
                error_log("Registration error: " . $e->getMessage());
                $errors['global'] = 'Registration failed. Please try again.';
            }
        }
        
        return [
            'success' => false,
            'errors' => $errors
        ];
    }
    
    public function login($identifier, $password, $remember_me = false) {
        $errors = [];
        $identifier = trim($identifier);
        $normalizedPhone = $this->normalizePhone($identifier);
        
        // Check rate limiting
        if (!$this->security->checkRateLimit('login', $_SERVER['REMOTE_ADDR'])) {
            $errors['global'] = 'Too many login attempts. Please try again later.';
            return ['success' => false, 'errors' => $errors];
        }
        
        // Find user by email, username, or phone
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM users 
                WHERE (email = ? OR username = ? OR phone = ?)
                LIMIT 1
            ");
            $stmt->execute([$identifier, $identifier, $normalizedPhone]);
            $user = $stmt->fetch();
            
            if (!$user) {
                $this->security->recordRateLimit('login', $_SERVER['REMOTE_ADDR']);
                $this->logFailedLogin($identifier, $_SERVER['REMOTE_ADDR']);
                $errors['identifier'] = 'No active account found with that email, username, or phone number';
                $errors['global'] = 'No active account found with that email, username, or phone number';
                return ['success' => false, 'errors' => $errors];
            }
            
            // Check if account is locked
            if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                $lock_time = ceil((strtotime($user['locked_until']) - time()) / 60);
                $errors['global'] = "Account is locked. Try again in {$lock_time} minutes.";
                return ['success' => false, 'errors' => $errors];
            }
            
            // Verify password
            if (!password_verify($password, $user['password_hash'])) {
                $this->handleFailedLogin($user);
                $this->security->recordRateLimit('login', $_SERVER['REMOTE_ADDR']);
                $this->logFailedLogin($identifier, $_SERVER['REMOTE_ADDR'], $user['id']);
                
                $errors['password'] = 'Password is incorrect';
                $attempts_left = max(0, DatabaseConfig::MAX_LOGIN_ATTEMPTS - ($user['login_attempts'] + 1));
                if ($attempts_left > 0) {
                    $errors['global'] = "Invalid credentials. {$attempts_left} attempts remaining.";
                } else {
                    $errors['global'] = "Account locked. Too many failed attempts.";
                }
                return ['success' => false, 'errors' => $errors];
            }
            
            // Reset login attempts on successful login
            $this->resetLoginAttempts($user['id']);
            
            // Update last login
            $this->updateLastLogin($user['id']);
            
            // Log successful login
            $this->logSuccessfulLogin($user['id'], $_SERVER['REMOTE_ADDR']);
            
            // Set session
            $this->setSession($user, $remember_me);
            
            // Set remember me cookie if requested
            if ($remember_me) {
                $this->setRememberMeCookie($user['id']);
            }
            
            return [
                'success' => true,
                'redirect' => 'index.php?page=profile'
            ];
            
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            $errors['global'] = 'Login failed. Please try again.';
            return ['success' => false, 'errors' => $errors];
        }
    }
    
    private function handleFailedLogin($user) {
        try {
            $new_attempts = $user['login_attempts'] + 1;
            
            if ($new_attempts >= DatabaseConfig::MAX_LOGIN_ATTEMPTS) {
                $locked_until = date('Y-m-d H:i:s', time() + DatabaseConfig::LOCKOUT_TIME);
                $stmt = $this->db->prepare("
                    UPDATE users 
                    SET login_attempts = ?, locked_until = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$new_attempts, $locked_until, $user['id']]);
            } else {
                $stmt = $this->db->prepare("
                    UPDATE users 
                    SET login_attempts = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$new_attempts, $user['id']]);
            }
        } catch (PDOException $e) {
            error_log("Failed login update error: " . $e->getMessage());
        }
    }
    
    private function resetLoginAttempts($user_id) {
        try {
            $stmt = $this->db->prepare("
                UPDATE users 
                SET login_attempts = 0, locked_until = NULL 
                WHERE id = ?
            ");
            $stmt->execute([$user_id]);
        } catch (PDOException $e) {
            error_log("Reset login attempts error: " . $e->getMessage());
        }
    }
    
    private function updateLastLogin($user_id) {
        try {
            $stmt = $this->db->prepare("
                UPDATE users 
                SET last_login = NOW(), last_ip = ? 
                WHERE id = ?
            ");
            $stmt->execute([$_SERVER['REMOTE_ADDR'], $user_id]);
        } catch (PDOException $e) {
            error_log("Update last login error: " . $e->getMessage());
        }
    }
    
    private function setSession($user, $remember_me = false) {
        // Clear existing session
        session_unset();
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_uuid'] = $user['uuid'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'] ?? 'user';
        $_SESSION['avatar'] = $user['avatar'] ?? null;
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['skill_level'] = $user['skill_level'] ?? '';
        $_SESSION['favorite_game'] = $user['favorite_game'] ?? '';
        $_SESSION['bio'] = $user['bio'] ?? '';
        $_SESSION['discord'] = $user['discord'] ?? '';
        $_SESSION['nickname'] = $user['nickname'] ?? '';
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        
        if ($remember_me) {
            $_SESSION['remember_me'] = true;
            // Set cookie for 30 days
            setcookie('remember_me', '1', time() + (86400 * 30), "/");
        }
        
        session_regenerate_id(true);
        $this->persistCurrentSession((int) $user['id']);
    }

    private function persistCurrentSession(int $user_id): void {
        if ($user_id <= 0) {
            return;
        }

        try {
            $this->db->prepare("DELETE FROM user_sessions WHERE user_id = ? AND expires_at <= NOW()")->execute([$user_id]);
            $token = session_id();
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $now = time();

            $stmt = $this->db->prepare("
                SELECT id FROM user_sessions WHERE session_token = ? AND user_id = ? LIMIT 1
            ");
            $stmt->execute([$token, $user_id]);
            $existing = $stmt->fetch();

            if ($existing) {
                $stmt = $this->db->prepare("
                    UPDATE user_sessions SET payload = ?, user_agent = ?, ip_address = ?, last_activity = ?, expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR)
                    WHERE id = ?
                ");
                $stmt->execute([json_encode(['type' => 'session']), $ua, $ip, $now, $existing['id']]);
            } else {
                $stmt = $this->db->prepare("
                    INSERT INTO user_sessions (session_token, user_id, payload, user_agent, ip_address, last_activity, expires_at)
                    VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))
                ");
                $stmt->execute([$token, $user_id, json_encode(['type' => 'session']), $ua, $ip, $now]);
            }
        } catch (PDOException $e) {
            error_log("Persist session error: " . $e->getMessage());
        }
    }
    
    private function setRememberMeCookie($user_id) {
        $token = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $token);
        $expire = time() + (86400 * 30); // 30 days
        
        setcookie('remember_token', $token, $expire, '/', '', true, true);
        
        // Store hashed token in database (plaintext would be a security risk)
        try {
            $stmt = $this->db->prepare("
                INSERT INTO user_sessions (session_token, user_id, payload, last_activity, expires_at, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $hashedToken,
                $user_id,
                json_encode(['type' => 'remember_me']),
                time(),
                date('Y-m-d H:i:s', $expire),
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        } catch (PDOException $e) {
            error_log("Remember me token error: " . $e->getMessage());
        }
    }
    
    public function isLoggedIn() {
        if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
            // Check session timeout (fixed timeout from initial login)
            if (isset($_SESSION['login_time']) && 
                (time() - $_SESSION['login_time']) > DatabaseConfig::SESSION_TIMEOUT && 
                DatabaseConfig::SESSION_TIMEOUT > 0) {
                $this->logout();
                return false;
            }
            $this->persistCurrentSession((int) ($_SESSION['user_id'] ?? 0));
            return true;
        }
        
        // Check remember me cookie
        if (isset($_COOKIE['remember_token'])) {
            return $this->validateRememberMeToken($_COOKIE['remember_token']);
        }
        
        return false;
    }
    
    private function validateRememberMeToken($token) {
        try {
            $hashedToken = hash('sha256', $token);
            $stmt = $this->db->prepare("
                 SELECT u.* FROM user_sessions s 
                 JOIN users u ON s.user_id = u.id 
                 WHERE s.session_token = ? AND s.expires_at > NOW()
            ");
            $stmt->execute([$hashedToken]);
            $user = $stmt->fetch();
            
            if ($user) {
                $this->setSession($user, true);
                return true;
            }
        } catch (PDOException $e) {
            error_log("Remember me validation error: " . $e->getMessage());
        }
        
        // Clear invalid token
        setcookie('remember_token', '', time() - 3600, '/');
        return false;
    }
    
    public function logout() {
        $sessionToken = session_id();
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        if ($sessionToken !== '' && $userId > 0) {
            try {
                $tokens = [$sessionToken];
                if (!empty($_COOKIE['remember_token'])) {
                    $tokens[] = hash('sha256', $_COOKIE['remember_token']);
                }
                $placeholders = implode(',', array_fill(0, count($tokens), '?'));
                $stmt = $this->db->prepare("DELETE FROM user_sessions WHERE user_id = ? AND session_token IN ({$placeholders})");
                $stmt->execute(array_merge([$userId], $tokens));
            } catch (PDOException $e) {
                error_log("Logout session cleanup error: " . $e->getMessage());
            }
        }

        // Clear session
        session_unset();
        session_destroy();
        
        // Clear remember me cookies
        setcookie('remember_token', '', time() - 3600, '/');
        setcookie('remember_me', '', time() - 3600, '/');
        
        // Clear identifier cookie
        if (isset($_COOKIE['remember_identifier'])) {
            setcookie('remember_identifier', '', time() - 3600, '/');
        }
        
        // Start fresh session
        dream_start_session();
    }
    
    private function generateUUID() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    public function logSecurityEvent($user_id, $event_type, $details = []) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO security_logs (user_id, event_type, ip_address, user_agent, details) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $user_id,
                $event_type,
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                json_encode($details)
            ]);
        } catch (PDOException $e) {
            error_log("Security log error: " . $e->getMessage());
        }
    }
    
    private function logSuccessfulLogin($user_id, $ip_address) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO login_history (user_id, ip_address, user_agent, location, success) 
                VALUES (?, ?, ?, ?, TRUE)
            ");
            $stmt->execute([
                $user_id,
                $ip_address,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                $this->getLocationFromIP($ip_address)
            ]);
        } catch (PDOException $e) {
            error_log("Login history error: " . $e->getMessage());
        }
    }
    
    private function logFailedLogin($identifier, $ip_address, $user_id = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO login_history (user_id, ip_address, user_agent, success) 
                VALUES (?, ?, ?, FALSE)
            ");
            $stmt->execute([
                $user_id,
                $ip_address,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        } catch (PDOException $e) {
            error_log("Failed login history error: " . $e->getMessage());
        }
    }
    
    private function getLocationFromIP($ip) {
        // Simple implementation
        return "Unknown";
    }

    private function normalizePhone($phone) {
        if ($phone === null) {
            return null;
        }

        $phone = trim($phone);
        if ($phone === '') {
            return null;
        }

        $phone = preg_replace('/[^0-9+]/', '', $phone);

        if (strpos($phone, '00') === 0) {
            $phone = '+' . substr($phone, 2);
        }

        if ($phone !== '' && $phone[0] !== '+') {
            $phone = '+' . $phone;
        }

        return preg_match('/^\+[1-9][0-9]{7,14}$/', $phone) ? $phone : null;
    }
}

// Create global auth instance
$auth = new Auth();
?>
