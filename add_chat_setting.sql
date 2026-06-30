INSERT IGNORE INTO site_settings (`group`, `key`, `value`, `type`, `description`, `is_public`, `created_at`, `updated_at`) 
VALUES ('interface', 'chat_widget_enabled', '1', 'switch', '启用前端在线客服聊天按钮', 1, NOW(), NOW());
