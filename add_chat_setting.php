<?php
try {
    $db = new PDO('mysql:host=127.0.0.1;dbname=huwutong;charset=utf8mb4', 'root', 'root');
    $stmt = $db->prepare('SELECT id FROM site_settings WHERE `key` = ?');
    $stmt->execute(['chat_widget_enabled']);
    if (!$stmt->fetch()) {
        $db->prepare('INSERT INTO site_settings (`group`, `key`, `value`, `type`, `description`, `is_public`, `created_at`, `updated_at`) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())')
            ->execute(['interface', 'chat_widget_enabled', '1', 'switch', '启用前端在线客服聊天按钮', 1]);
        echo "✅ 已添加\n";
    } else {
        echo "✅ 已存在\n";
    }
} catch(Exception $e) {
    echo '❌ ' . $e->getMessage() . "\n";
}
