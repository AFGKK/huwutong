<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = $app['db']->connection();

// 创建 announcements 表
if (!$db->getSchemaBuilder()->hasTable('announcements')) {
    $db->statement("CREATE TABLE `announcements` (
        `id` bigint unsigned NOT NULL AUTO_INCREMENT,
        `conversation_id` bigint unsigned NOT NULL,
        `sender_id` bigint unsigned NOT NULL,
        `title` varchar(200) NOT NULL,
        `content` text NOT NULL,
        `created_at` timestamp NULL DEFAULT NULL,
        `updated_at` timestamp NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `announcements_conversation_id_index` (`conversation_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci");
    echo "Created announcements table\n";
}

// 创建 announcement_reads 表
if (!$db->getSchemaBuilder()->hasTable('announcement_reads')) {
    $db->statement("CREATE TABLE `announcement_reads` (
        `id` bigint unsigned NOT NULL AUTO_INCREMENT,
        `announcement_id` bigint unsigned NOT NULL,
        `user_id` bigint unsigned NOT NULL,
        `read_at` timestamp NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `announcement_reads_announcement_id_user_id_unique` (`announcement_id`,`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci");
    echo "Created announcement_reads table\n";
}

// 记录迁移
$db->table('migrations')->updateOrInsert(
    ['migration' => '2026_06_18_000001_create_announcements_tables'],
    ['batch' => $db->table('migrations')->max('batch')]
);
echo "Migration recorded.\nDone.\n";
