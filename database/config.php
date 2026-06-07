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

/**
 * Robust env() — checks getenv(), $_ENV, $_SERVER in order.
 * Works even if putenv()/getenv() are restricted on some hosts.
 */
if (!function_exists('env')) {
    function env(string $key, $default = null) {
        $val = getenv($key);
        if ($val !== false && $val !== '') return $val;
        return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    }
}

if (!function_exists('dream_asset')) {
    function dream_asset(string $path): string {
        $normalized = ltrim($path, '/');
        $file = __DIR__ . '/../' . $normalized;
        $version = is_file($file) ? (string) filemtime($file) : '1';
        return htmlspecialchars($normalized . '?v=' . $version, ENT_QUOTES, 'UTF-8');
    }
}

class DatabaseConfig {
    // Database Configuration
    public static function getHost() { return env('DB_HOST', 'localhost'); }
    public static function getName() { return env('DB_NAME', 'dream'); }
    public static function getUser() { return env('DB_USER', 'root'); }
    public static function getPass() { return env('DB_PASS', ''); }
    const DB_CHARSET = 'utf8mb4';
    
    // Security Configuration
    const MAX_LOGIN_ATTEMPTS = 5;
    const LOCKOUT_TIME = 900; // 15 minutes
    const SESSION_TIMEOUT = 86400; // 24 hours
    
    // JWT Configuration
    public static function getJwtSecret() { 
        return env('JWT_SECRET', '7535d3f26d41e280fd659f0793e2d77fee6b165b7001f505a62e67c93864bd5b'); 
    }
    
    // Encryption Configuration
    public static function getEncryptionKey() { 
        return env('ENCRYPTION_KEY', 'R1B5KpP+F9k8eZ9rP2sA7m7Y0Z1E2M9F0KJXc3nq8y4='); 
    }
    
    // SMTP Configuration (Brevo)
    public static function getSmtpHost() { return env('SMTP_HOST', 'smtp-relay.brevo.com'); }
    public static function getSmtpPort() { return env('SMTP_PORT', 587); }
    public static function getSmtpUser() { return env('SMTP_USER', 'ad8803001@smtp-brevo.com'); }
    public static function getSmtpPass() { return env('SMTP_PASS', ''); }

    // reCAPTCHA Configuration
    const RECAPTCHA_SITE_KEY = '6LewAA0tAAAAAHYT_EzWeqK2p6rZ8Rl07XqJVkXu';
    const RECAPTCHA_SECRET_KEY = '6LewAA0tAAAAAIFjaFow-sAzq7OfNVucnVwzHGSm';
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

function dream_column_exists(PDO $db, string $table, string $column): bool {
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
        ");
        $stmt->execute([$table, $column]);
        return (int) $stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function dream_column_type(PDO $db, string $table, string $column): ?string {
    try {
        $stmt = $db->prepare("
            SELECT COLUMN_TYPE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
            LIMIT 1
        ");
        $stmt->execute([$table, $column]);
        $value = $stmt->fetchColumn();
        return $value !== false ? strtolower((string) $value) : null;
    } catch (Throwable $e) {
        return null;
    }
}

function ensureUserSessionsSchema(PDO $db): void {
    $hasSessionToken = dream_column_exists($db, 'user_sessions', 'session_token');
    $idType = dream_column_type($db, 'user_sessions', 'id');

    if (!$hasSessionToken || ($idType !== null && strpos($idType, 'int') !== 0)) {
        $legacyTable = 'user_sessions_legacy_' . date('YmdHis');
        $sourceToken = $hasSessionToken ? 'session_token' : 'id';

        $db->exec("
            CREATE TABLE IF NOT EXISTS user_sessions_migrated (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                session_token VARCHAR(128) NOT NULL,
                payload LONGTEXT NULL,
                user_agent TEXT NULL,
                ip_address VARCHAR(45) NULL,
                last_activity INT UNSIGNED NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_token (session_token),
                INDEX idx_user (user_id),
                INDEX idx_activity (last_activity),
                INDEX idx_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $db->exec("
            INSERT IGNORE INTO user_sessions_migrated
                (user_id, session_token, payload, user_agent, ip_address, last_activity, expires_at, created_at)
            SELECT
                user_id,
                CAST({$sourceToken} AS CHAR(128)),
                payload,
                user_agent,
                ip_address,
                last_activity,
                expires_at,
                COALESCE(created_at, CURRENT_TIMESTAMP)
            FROM user_sessions
        ");

        $db->exec("RENAME TABLE user_sessions TO {$legacyTable}, user_sessions_migrated TO user_sessions");
    }

    $migrations = [
        "ALTER TABLE user_sessions MODIFY id INT UNSIGNED NOT NULL AUTO_INCREMENT",
        "ALTER TABLE user_sessions MODIFY user_id INT UNSIGNED NOT NULL",
        "ALTER TABLE user_sessions MODIFY session_token VARCHAR(128) NOT NULL",
        "ALTER TABLE user_sessions MODIFY last_activity INT UNSIGNED NOT NULL",
        "ALTER TABLE user_sessions MODIFY expires_at DATETIME NOT NULL",
        "ALTER TABLE user_sessions ADD COLUMN IF NOT EXISTS payload LONGTEXT NULL AFTER session_token",
        "ALTER TABLE user_sessions ADD COLUMN IF NOT EXISTS user_agent TEXT NULL AFTER payload",
        "ALTER TABLE user_sessions ADD COLUMN IF NOT EXISTS ip_address VARCHAR(45) NULL AFTER user_agent",
        "ALTER TABLE user_sessions ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER expires_at",
        "ALTER TABLE user_sessions ADD UNIQUE INDEX IF NOT EXISTS uniq_token (session_token)",
        "ALTER TABLE user_sessions ADD INDEX IF NOT EXISTS idx_user (user_id)",
        "ALTER TABLE user_sessions ADD INDEX IF NOT EXISTS idx_activity (last_activity)",
        "ALTER TABLE user_sessions ADD INDEX IF NOT EXISTS idx_expires (expires_at)"
    ];

    foreach ($migrations as $sql) {
        try {
            $db->exec($sql);
        } catch (Throwable $e) {
        }
    }
}

function ensureTournamentFeatureSchema(PDO $db): void {
    $queries = [
        "ALTER TABLE tournament_participants ADD COLUMN IF NOT EXISTS team_id INT UNSIGNED NULL AFTER user_id",
        "ALTER TABLE tournament_participants ADD COLUMN IF NOT EXISTS fee_paid TINYINT(1) NOT NULL DEFAULT 0 AFTER team_name",
        "ALTER TABLE tournament_participants MODIFY team_name VARCHAR(120) NULL",
        "CREATE TABLE IF NOT EXISTS tournament_chat_messages (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tournament_id INT UNSIGNED NOT NULL,
            sender_id INT UNSIGNED NOT NULL,
            message_type ENUM('text','room_card','system') NOT NULL DEFAULT 'text',
            message TEXT NULL,
            room_code VARCHAR(120) NULL,
            room_password VARCHAR(120) NULL,
            room_link VARCHAR(255) NULL,
            invite_note TEXT NULL,
            metadata_json TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_tournament_chat (tournament_id, created_at),
            INDEX idx_sender_chat (sender_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS tournament_results (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tournament_id INT UNSIGNED NOT NULL,
            team_id INT UNSIGNED NULL,
            user_id INT UNSIGNED NULL,
            result_scope ENUM('team','player') NOT NULL DEFAULT 'player',
            placement INT NULL,
            points INT NULL,
            points_earned INT DEFAULT 0,
            kills INT NULL,
            score DECIMAL(10,2) NULL,
            result_label VARCHAR(255) NULL,
            prize_amount DECIMAL(10,2) DEFAULT 0.00,
            notes TEXT NULL,
            result_note TEXT NULL,
            submitted_by INT UNSIGNED NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_tournament_team (tournament_id, team_id),
            UNIQUE KEY uniq_tournament_player (tournament_id, user_id),
            INDEX idx_tournament_result (tournament_id),
            INDEX idx_user_result (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];

    foreach ($queries as $sql) {
        try {
            $db->exec($sql);
        } catch (Throwable $e) {
        }
    }

    // Migrate existing tournament_results table with missing columns
    $tableMigrations = [
        "ALTER TABLE tournament_chat_messages ADD COLUMN IF NOT EXISTS metadata_json TEXT NULL AFTER invite_note",
        "ALTER TABLE tournament_results ADD COLUMN IF NOT EXISTS result_scope ENUM('team','player') NOT NULL DEFAULT 'player' AFTER user_id",
        "ALTER TABLE tournament_results ADD COLUMN IF NOT EXISTS result_label VARCHAR(255) NULL AFTER score",
        "ALTER TABLE tournament_results ADD COLUMN IF NOT EXISTS prize_amount DECIMAL(10,2) DEFAULT 0.00 AFTER result_label",
        "ALTER TABLE tournament_results ADD COLUMN IF NOT EXISTS points_earned INT DEFAULT 0 AFTER points",
        "ALTER TABLE tournament_results ADD COLUMN IF NOT EXISTS notes TEXT NULL AFTER prize_amount",
        "ALTER TABLE tournament_results ADD INDEX IF NOT EXISTS idx_result_tournament_scope (tournament_id, result_scope)",
        "ALTER TABLE tournament_results ADD INDEX IF NOT EXISTS idx_result_user_tournament (user_id, tournament_id)",
        "CREATE TABLE IF NOT EXISTS tournament_leaderboard (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tournament_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            total_points INT DEFAULT 0,
            total_prize DECIMAL(10,2) DEFAULT 0.00,
            tournaments_played INT DEFAULT 0,
            best_rank INT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_player_leaderboard (tournament_id, user_id),
            INDEX idx_leaderboard_points (tournament_id, total_points DESC),
            INDEX idx_user_leaderboard (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];
    foreach ($tableMigrations as $sql) {
        try { $db->exec($sql); } catch (Throwable $e) {}
    }
}

function ensureP2PSchema(PDO $db): void {
    $queries = [
        "CREATE TABLE IF NOT EXISTS p2p_offers (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            agent_id INT UNSIGNED NOT NULL,
            type ENUM('buy','sell') NOT NULL DEFAULT 'sell',
            coin_type ENUM('bronze','silver','gold') NOT NULL DEFAULT 'bronze',
            price_per_coin DECIMAL(10,2) NOT NULL,
            quantity INT UNSIGNED NOT NULL,
            remaining INT UNSIGNED NOT NULL,
            min_amount INT UNSIGNED NOT NULL DEFAULT 1,
            max_amount INT UNSIGNED NOT NULL DEFAULT 0,
            status ENUM('active','completed','cancelled') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_agent (agent_id),
            INDEX idx_type_status (type, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS p2p_payment_settings (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            method VARCHAR(30) NOT NULL,
            number VARCHAR(50) NOT NULL,
            instruction VARCHAR(30) DEFAULT 'send_money',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_user_method (user_id, method)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS p2p_chat_messages (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            trade_id INT UNSIGNED NOT NULL,
            sender_id INT UNSIGNED NOT NULL,
            message TEXT,
            image_path VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_trade (trade_id),
            INDEX idx_sender (sender_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS p2p_reports (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            trade_id INT UNSIGNED NOT NULL,
            reporter_id INT UNSIGNED NOT NULL,
            reported_id INT UNSIGNED NOT NULL,
            reason VARCHAR(100) NOT NULL,
            details TEXT,
            status ENUM('open','resolved','dismissed') DEFAULT 'open',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_trade (trade_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS p2p_reviews (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            trade_id INT UNSIGNED NOT NULL,
            reviewer_id INT UNSIGNED NOT NULL,
            merchant_id INT UNSIGNED NOT NULL,
            rating TINYINT UNSIGNED NOT NULL CHECK (rating >= 1 AND rating <= 5),
            comment VARCHAR(500) DEFAULT '',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_reviewer_merchant (reviewer_id, merchant_id),
            INDEX idx_merchant (merchant_id),
            INDEX idx_reviewer (reviewer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS coin_transactions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            from_user_id INT UNSIGNED,
            to_user_id INT UNSIGNED,
            type VARCHAR(30) NOT NULL,
            coin_type ENUM('bronze','silver','gold') NOT NULL,
            amount INT UNSIGNED NOT NULL,
            price DECIMAL(10,2),
            description VARCHAR(255),
            ref_id INT UNSIGNED,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_from (from_user_id),
            INDEX idx_to (to_user_id),
            INDEX idx_type (type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];
    foreach ($queries as $sql) {
        try { $db->exec($sql); } catch (Throwable $e) {}
    }

    // Add missing columns to p2p_trades
    $tradeMigrations = [
        "ALTER TABLE p2p_trades ADD COLUMN IF NOT EXISTS offer_id INT UNSIGNED NOT NULL AFTER id",
        "ALTER TABLE p2p_trades ADD COLUMN IF NOT EXISTS coin_type ENUM('bronze','silver','gold') NOT NULL DEFAULT 'bronze' AFTER buyer_id",
        "ALTER TABLE p2p_trades ADD COLUMN IF NOT EXISTS total_price DECIMAL(12,2) NOT NULL AFTER quantity",
        "ALTER TABLE p2p_trades ADD COLUMN IF NOT EXISTS payment_method VARCHAR(30) AFTER status",
        "ALTER TABLE p2p_trades ADD COLUMN IF NOT EXISTS sender_phone VARCHAR(30) AFTER payment_method",
        "ALTER TABLE p2p_trades ADD COLUMN IF NOT EXISTS txid VARCHAR(100) AFTER sender_phone",
        "ALTER TABLE p2p_trades ADD COLUMN IF NOT EXISTS completed_at DATETIME AFTER txid",
        "ALTER TABLE p2p_trades MODIFY status ENUM('pending','paid','completed','cancelled','disputed') NOT NULL DEFAULT 'pending'"
    ];
    foreach ($tradeMigrations as $sql) {
        try { $db->exec($sql); } catch (Throwable $e) {}
    }

    try {
        if (!dream_column_exists($db, 'p2p_chat_messages', 'image_path')) {
            $db->exec("ALTER TABLE p2p_chat_messages ADD COLUMN image_path VARCHAR(255) DEFAULT NULL AFTER message");
        }
    } catch (Throwable $e) {}

    // Add missing columns to p2p_reports
    $reportMigrations = [
        "ALTER TABLE p2p_reports ADD COLUMN IF NOT EXISTS resolved_at DATETIME AFTER status",
        "ALTER TABLE p2p_reports ADD COLUMN IF NOT EXISTS resolved_by INT UNSIGNED AFTER resolved_at",
        "ALTER TABLE p2p_reports ADD COLUMN IF NOT EXISTS admin_note VARCHAR(500) AFTER resolved_by"
    ];
    foreach ($reportMigrations as $sql) {
        try { $db->exec($sql); } catch (Throwable $e) {}
    }

    // Migrate p2p_reviews unique key to per-user-per-merchant
    try {
        $db->exec("ALTER TABLE p2p_reviews DROP INDEX IF EXISTS uniq_trade_review");
    } catch (Throwable $e) {}
    try {
        $db->exec("ALTER TABLE p2p_reviews ADD UNIQUE KEY IF NOT EXISTS uniq_reviewer_merchant (reviewer_id, merchant_id)");
    } catch (Throwable $e) {}
}

// Composer autoload
$composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
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

    }

    // Always-run column migrations for existing databases
    $alwaysRun = [
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS uuid CHAR(36) AFTER id",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(30) AFTER full_name",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS bio TEXT AFTER phone",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS location VARCHAR(150) AFTER bio",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS website VARCHAR(255) AFTER location",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS cover_image VARCHAR(255) DEFAULT 'default.jpg' AFTER avatar",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS preferences LONGTEXT AFTER website",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS login_attempts INT DEFAULT 0 AFTER role",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS locked_until DATETIME AFTER login_attempts",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login DATETIME AFTER status",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS last_ip VARCHAR(45) AFTER last_login",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS email_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER status",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS email_verification_token VARCHAR(64) DEFAULT NULL AFTER email_verified",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS email_verification_expires DATETIME DEFAULT NULL AFTER email_verification_token",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_token VARCHAR(64) DEFAULT NULL AFTER email_verification_expires",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_token_expires DATETIME DEFAULT NULL AFTER reset_token",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS agent_verified_at DATETIME AFTER balance",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS coins INT UNSIGNED DEFAULT 0 AFTER agent_verified_at",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS nickname VARCHAR(50) AFTER coins",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS skill_level VARCHAR(30) AFTER nickname",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS favorite_game VARCHAR(80) AFTER skill_level",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS discord VARCHAR(60) AFTER favorite_game",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS gold_coins INT UNSIGNED DEFAULT 0 AFTER discord",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS silver_coins INT UNSIGNED DEFAULT 0 AFTER gold_coins",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS bronze_coins INT UNSIGNED DEFAULT 0 AFTER silver_coins",
        "ALTER TABLE slider_content ADD COLUMN IF NOT EXISTS slider_type ENUM('features','tournament','leaderboard','ads') DEFAULT 'features' AFTER badge",
    ];
    foreach ($alwaysRun as $sql) {
        try { $db->exec($sql); } catch (Throwable $e) {}
    }

    ensureP2PSchema($db);
    ensureUserSessionsSchema($db);
    ensureTournamentFeatureSchema($db);
} catch (Exception $e) {
    error_log("Config Error: " . $e->getMessage());
}
