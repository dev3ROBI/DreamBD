<?php
require_once __DIR__ . '/database/config.php';
try {
    $db = Database::getInstance()->getConnection();
    // Add image_path to p2p_chat_messages if it doesn't exist
    $db->exec("ALTER TABLE p2p_chat_messages ADD COLUMN image_path VARCHAR(255) DEFAULT NULL AFTER message");
    echo "Column added successfully.\n";
} catch (Exception $e) {
    echo "Error or column already exists: " . $e->getMessage() . "\n";
}
