<?php
date_default_timezone_set('Asia/Dhaka');

/**
 * Simple .env loader
 */
function loadEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Load environment variables if .env exists
loadEnv(__DIR__ . '/../.env');

class DatabaseConfig {
    // Database Configuration
    public static function getHost() { return getenv('DB_HOST') ?: 'localhost'; }
    public static function getName() { return getenv('DB_NAME') ?: 'dream'; }
    public static function getUser() { return getenv('DB_USER') ?: 'root'; }
    public static function getPass() { return getenv('DB_PASS') ?: ''; }
    const DB_CHARSET = 'utf8mb4';
    
    // Security Configuration
    const MAX_LOGIN_ATTEMPTS = 5;
    const LOCKOUT_TIME = 900; // 15 minutes
    const SESSION_TIMEOUT = 1800; // 30 minutes
    
    // JWT Configuration
    public static function getJwtSecret() { 
        return getenv('JWT_SECRET') ?: '7535d3f26d41e280fd659f0793e2d77fee6b165b7001f505a62e67c93864bd5b'; 
    }
    
    // Encryption Configuration
    public static function getEncryptionKey() { 
        return getenv('ENCRYPTION_KEY') ?: 'R1B5KpP+F9k8eZ9rP2sA7m7Y0Z1E2M9F0KJXc3nq8y4='; 
    }
    
    // SMTP Configuration
    public static function getSmtpHost() { return getenv('SMTP_HOST') ?: 'smtp.gmail.com'; }
    public static function getSmtpPort() { return getenv('SMTP_PORT') ?: 587; }
    public static function getSmtpUser() { return getenv('SMTP_USER') ?: 'noreply@dreambd.com'; }
    public static function getSmtpPass() { return getenv('SMTP_PASS') ?: ''; }
}

class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        $dsn = "mysql:host=" . DatabaseConfig::getHost() . 
               ";dbname=" . DatabaseConfig::getName() . 
               ";charset=" . DatabaseConfig::DB_CHARSET;
        
        try {
            $this->connection = new PDO($dsn, DatabaseConfig::getUser(), DatabaseConfig::getPass(), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
            $this->connection->exec("SET time_zone = '+06:00'");
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection error.");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }
    
    public function getConnection() { return $this->connection; }
}

// Global connection testing and auto-schema
try {
    $db = Database::getInstance()->getConnection();
    
    // Robust Schema Check (only run if site_settings doesn't exist or flag is missing)
    $runSchema = false;
    try {
        $stmt = $db->query("SELECT value FROM site_settings WHERE `key` = 'schema_initialized'");
        if (!$stmt->fetch()) $runSchema = true;
    } catch (PDOException $e) {
        $runSchema = true;
    }

    if ($runSchema) {
        $queries = [
            // Core Tables
            "CREATE TABLE IF NOT EXISTS site_settings (`key` VARCHAR(80) PRIMARY KEY, `value` TEXT, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS users (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) UNIQUE NOT NULL, email VARCHAR(120) UNIQUE NOT NULL, password_hash VARCHAR(255) NOT NULL, full_name VARCHAR(120), role VARCHAR(30) DEFAULT 'user', balance DECIMAL(12,2) DEFAULT 0.00, bronze_coins INT UNSIGNED DEFAULT 0, silver_coins INT UNSIGNED DEFAULT 0, gold_coins INT UNSIGNED DEFAULT 0, avatar VARCHAR(255) DEFAULT 'default.png', status VARCHAR(30) DEFAULT 'active', registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS user_sessions (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, session_token VARCHAR(128) NOT NULL, payload LONGTEXT, user_agent TEXT, ip_address VARCHAR(45), last_activity INT UNSIGNED NOT NULL, expires_at DATETIME NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uniq_token (session_token), INDEX idx_user (user_id), INDEX idx_activity (last_activity)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS posts (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, content TEXT NOT NULL, image_path VARCHAR(255), privacy ENUM('public','friends','private') DEFAULT 'public', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_user (user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS post_comments (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, post_id INT UNSIGNED NOT NULL, user_id INT UNSIGNED NOT NULL, comment_text TEXT NOT NULL, parent_comment_id INT UNSIGNED DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_post (post_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS post_likes (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, post_id INT UNSIGNED NOT NULL, user_id INT UNSIGNED NOT NULL, reaction_type VARCHAR(20) DEFAULT 'like', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uniq_like (post_id, user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS notifications (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, actor_id INT UNSIGNED, type VARCHAR(30) NOT NULL, entity_id INT UNSIGNED, message VARCHAR(255), is_read TINYINT(1) DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_user (user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS messages (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, sender_id INT UNSIGNED NOT NULL, receiver_id INT UNSIGNED NOT NULL, body TEXT NOT NULL, is_read TINYINT(1) DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_thread (sender_id, receiver_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS slider_content (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, title VARCHAR(255) NOT NULL, badge VARCHAR(100) DEFAULT 'New', description TEXT NOT NULL, status ENUM('active', 'inactive') DEFAULT 'active', sort_order INT DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS tournaments (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, title VARCHAR(150) NOT NULL, description TEXT, status VARCHAR(40) DEFAULT 'upcoming', entry_fee DECIMAL(10,2) DEFAULT 0.00, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS tournament_participants (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tournament_id INT UNSIGNED NOT NULL, user_id INT UNSIGNED NOT NULL, status ENUM('registered','confirmed','cancelled') DEFAULT 'registered', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_tournament (tournament_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS p2p_trades (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, seller_id INT UNSIGNED NOT NULL, buyer_id INT UNSIGNED NOT NULL, quantity INT UNSIGNED NOT NULL, status ENUM('pending','paid','completed','cancelled') DEFAULT 'pending', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "INSERT IGNORE INTO site_settings (`key`, `value`) VALUES ('schema_initialized', '1')",
            "INSERT IGNORE INTO site_settings (`key`, `value`) VALUES ('slider_enabled', '1')"
        ];

        foreach ($queries as $sql) {
            try { $db->exec($sql); } catch (PDOException $e) { error_log("Initial Schema: " . $e->getMessage()); }
        }

        // Add missing columns if they don't exist (Migration style)
        $migrations = [
            "ALTER TABLE users ADD COLUMN IF NOT EXISTS uuid CHAR(36) AFTER id",
            "ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(30) AFTER full_name",
            "ALTER TABLE users ADD COLUMN IF NOT EXISTS bio TEXT AFTER phone",
            "ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login DATETIME AFTER status",
            "ALTER TABLE slider_content ADD COLUMN IF NOT EXISTS slider_type ENUM('features','tournament','leaderboard','ads') DEFAULT 'features' AFTER badge",
            "ALTER TABLE user_sessions MODIFY last_activity INT UNSIGNED NOT NULL",
            "ALTER TABLE user_sessions ADD COLUMN IF NOT EXISTS expires_at DATETIME NOT NULL AFTER last_activity"
        ];
        foreach ($migrations as $sql) {
            try { $db->exec($sql); } catch (PDOException $e) {}
        }
    }
} catch (Exception $e) {
    error_log("Config Error: " . $e->getMessage());
}