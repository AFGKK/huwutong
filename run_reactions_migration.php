<?php
// Run the message_reactions migration
$pdo = new PDO('mysql:host=127.0.0.1;dbname=huwutong', 'root', 'root');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "CREATE TABLE IF NOT EXISTS message_reactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    message_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    reaction VARCHAR(50) NOT NULL COMMENT 'emoji character like 👍❤️😄',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY unique_reaction (message_id, user_id, reaction),
    CONSTRAINT fk_reactions_message FOREIGN KEY (message_id) REFERENCES conversation_messages(id) ON DELETE CASCADE,
    CONSTRAINT fk_reactions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

$pdo->exec($sql);
echo "OK: message_reactions table created.\n";
