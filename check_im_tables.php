<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=huwutong', 'root', '');
$tables = ['user_conversations', 'conversation_participants', 'conversation_messages', 'message_reactions', 'user_online_statuses', 'custom_emojis', 'channel_members', 'channel_messages', 'channels', 'oa_articles', 'oa_comments', 'oa_submissions'];
foreach ($tables as $t) {
    $stmt = $pdo->query("SHOW TABLES LIKE '$t'");
    echo $t . ': ' . ($stmt->rowCount() > 0 ? "EXISTS" : "MISSING") . PHP_EOL;
}
