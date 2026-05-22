<?php
class DatabaseConfig {
    // Database Configuration
    const DB_HOST = 'localhost';
    const DB_NAME = 'dream';
    const DB_USER = 'root';
    const DB_PASS = '';
    const DB_CHARSET = 'utf8mb4';
    
    // Security Configuration
    const MAX_LOGIN_ATTEMPTS = 5;
    const LOCKOUT_TIME = 900; // 15 minutes in seconds
    const SESSION_TIMEOUT = 1800; // 30 minutes
    const REMEMBER_ME_EXPIRE = 2592000; // 30 days
    
    // Password Requirements
    const MIN_PASSWORD_LENGTH = 8;
    const REQUIRE_UPPERCASE = true;
    const REQUIRE_LOWERCASE = true;
    const REQUIRE_NUMBERS = true;
    const REQUIRE_SYMBOLS = true;
    
    // JWT Configuration
    const JWT_SECRET = '7535d3f26d41e280fd659f0793e2d77fee6b165b7001f505a62e67c93864bd5b';
    const JWT_ALGORITHM = 'HS256';
    const JWT_EXPIRE = 3600; // 1 hour
    
    // Encryption Keys (Change these in production!)
    const ENCRYPTION_KEY = 'R1B5KpP+F9k8eZ9rP2sA7m7Y0Z1E2M9F0KJXc3nq8y4=';
    const ENCRYPTION_CIPHER = 'AES-256-CBC';
    
    // API Rate Limiting
    const RATE_LIMIT_REQUESTS = 100;
    const RATE_LIMIT_PERIOD = 60; // 1 minute
    
    // Email Configuration
    const SMTP_HOST = 'smtp.gmail.com';
    const SMTP_PORT = 587;
    const SMTP_USER = 'noreply@dreambd.com';
    const SMTP_PASS = '';
    const SMTP_SECURE = 'tls';
    
    // File Upload Configuration
    const MAX_FILE_SIZE = 5242880; // 5MB
    const ALLOWED_FILE_TYPES = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    const UPLOAD_PATH = __DIR__ . '/../assets/images/users/';
    
    // CORS Configuration
    const ALLOWED_ORIGINS = ['https://dreambd.com', 'https://www.dreambd.com'];
    const ALLOWED_METHODS = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'];
    const ALLOWED_HEADERS = ['Content-Type', 'Authorization', 'X-Requested-With'];
}

class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        $dsn = "mysql:host=" . DatabaseConfig::DB_HOST . 
               ";dbname=" . DatabaseConfig::DB_NAME . 
               ";charset=" . DatabaseConfig::DB_CHARSET;
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
        ];
        
        try {
            $this->connection = new PDO($dsn, DatabaseConfig::DB_USER, DatabaseConfig::DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection error. Please try again later.");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    public function commit() {
        return $this->connection->commit();
    }
    
    public function rollBack() {
        return $this->connection->rollBack();
    }
    
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
}

session_start();

// Initialize connection variables
$connectionClass = 'error';
$connectionStatus = 'Connection Failed';

try {
    // Attempt to connect to the database
    $dbInstance = Database::getInstance();
    $connection = $dbInstance->getConnection();
    
    // Test the connection with a simple query
    $connection->query("SELECT 1");
    
    $connectionClass = 'success';
    $connectionStatus = 'Database connection successful!';
    
} catch (Exception $e) {
    $connectionStatus = 'Connection Error: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Connection Status</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #ccfffc 0%, #fdcbeb 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            text-align: center;
            max-width: 500px;
            width: 100%;
            animation: fadeIn 0.8s ease-out;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .status-icon {
            width: 100px;
            height: 100px;
            margin: 0 auto 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .success .status-icon {
            background: linear-gradient(135deg, #4CAF50, #8BC34A);
            color: white;
            box-shadow: 0 10px 30px rgba(76, 175, 80, 0.3);
        }
        
        .error .status-icon {
            background: linear-gradient(135deg, #F44336, #E91E63);
            color: white;
            box-shadow: 0 10px 30px rgba(244, 67, 54, 0.3);
            animation: shake 0.5s ease-in-out;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }
        
        h1 {
            color: #333;
            margin-bottom: 20px;
            font-size: 28px;
            font-weight: 600;
        }
        
        .status-message {
            font-size: 18px;
            color: #555;
            margin-bottom: 30px;
            line-height: 1.6;
            padding: 15px;
            border-radius: 10px;
            background: #f8f9fa;
        }
        
        .success .status-message {
            color: #2e7d32;
            background: #e8f5e9;
            border-left: 4px solid #4CAF50;
        }
        
        .error .status-message {
            color: #c62828;
            background: #ffebee;
            border-left: 4px solid #F44336;
        }
        
        .database-info {
            background: #f5f5f5;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
            text-align: left;
            animation: slideUp 0.6s ease-out 0.3s both;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .info-item:last-child {
            margin-bottom: 0;
            border-bottom: none;
        }
        
        .label {
            font-weight: 600;
            color: #666;
        }
        
        .value {
            color: #333;
            font-family: 'Courier New', monospace;
        }
        
        .timestamp {
            margin-top: 20px;
            color: #888;
            font-size: 14px;
        }
        
        .connection-dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 8px;
            animation: blink 1.5s infinite;
        }
        
        .success .connection-dot {
            background: #4CAF50;
            box-shadow: 0 0 10px #4CAF50;
        }
        
        .error .connection-dot {
            background: #F44336;
            box-shadow: 0 0 10px #F44336;
        }
        
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
</head>
<body>
    <div class="container <?php echo $connectionClass; ?>">
        <div class="status-icon">
            <?php if ($connectionClass === 'success'): ?>
                ✓
            <?php else: ?>
                ✗
            <?php endif; ?>
        </div>
        
        <h1>Database Connection Status</h1>
        
        <div class="status-message">
            <span class="connection-dot"></span>
            <?php echo $connectionStatus; ?>
        </div>
        
        <?php if ($connectionClass === 'success'): ?>
        <div class="database-info">
            <div class="info-item">
                <span class="label">Host:</span>
                <span class="value"><?php echo htmlspecialchars(DatabaseConfig::DB_HOST); ?></span>
            </div>
            <div class="info-item">
                <span class="label">Database:</span>
                <span class="value"><?php echo htmlspecialchars(DatabaseConfig::DB_NAME); ?></span>
            </div>
            <div class="info-item">
                <span class="label">Username:</span>
                <span class="value"><?php echo htmlspecialchars(DatabaseConfig::DB_USER); ?></span>
            </div>
            <div class="info-item">
                <span class="label">Charset:</span>
                <span class="value"><?php echo htmlspecialchars(DatabaseConfig::DB_CHARSET); ?></span>
            </div>
            <div class="info-item">
                <span class="label">Status:</span>
                <span class="value" style="color: #4CAF50; font-weight: bold;">
                    Connected ✓
                </span>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="timestamp">
            Checked at: <?php echo date('Y-m-d H:i:s'); ?><br>
            PHP Version: <?php echo phpversion(); ?>
        </div>
    </div>
</body>
</html>