<?php
// Create message_favorites table and add is_edited column
$pdo = new PDO('mysql:host=127.0.0.1;dbname=huwutong', 'root', 'root');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    $pdo->exec("ALTER TABLE conversation_messages ADD COLUMN is_edited TINYINT(1) DEFAULT 0 AFTER content");
    echo "OK: added is_edited\n";
} catch (PDOException $e) {
    echo strpos($e->getMessage(), 'Duplicate column') ? "OK: is_edited exists\n" : "ERROR: ".$e->getMessage()."\n";
}

$sql = "CREATE TABLE IF NOT EXISTS message_favorites (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    message_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    UNIQUE KEY unique_fav (user_id, message_id),
    CONSTRAINT fk_fav_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_fav_msg FOREIGN KEY (message_id) REFERENCES conversation_messages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
$pdo->exec($sql);
echo "OK: message_favorites table created.\n";

// Add group mute columns
try {
    $pdo->exec("ALTER TABLE conversation_participants ADD COLUMN is_muted_until DATETIME NULL AFTER is_muted");
    echo "OK: added is_muted_until\n";
} catch (PDOException $e) {
    echo strpos($e->getMessage(), 'Duplicate column') ? "OK: is_muted_until exists\n" : "ERROR: ".$e->getMessage()."\n";
}

echo "Done.\n";
