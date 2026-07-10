<?php

/**
 * 一键运行 PostgreSQL 迁移后验证套件
 *
 * 用法: php scripts/verify-pgsql-all.php
 */

$root = dirname(__DIR__);
$scripts = [
    'check-db-health.php' => '数据库健康检查',
    'verify-pgsql-p0.php' => 'P0 SQL 兼容层',
    'verify-pgsql-fts.php' => '全文搜索索引',
    'verify-read-write.php' => '读写分离',
    'verify-health.php' => 'HTTP 健康端点',
];

echo "=== PostgreSQL 验证套件 ===\n\n";

$failed = 0;
foreach ($scripts as $file => $label) {
    $path = $root.'/scripts/'.$file;
    echo str_repeat('─', 50)."\n";
    echo "▶ {$label} ({$file})\n";
    echo str_repeat('─', 50)."\n";

    if (! file_exists($path)) {
        echo "⚠️  脚本不存在，跳过\n\n";
        continue;
    }

    passthru(PHP_BINARY.' '.escapeshellarg($path), $code);
    echo "\n";
    if ($code !== 0) {
        $failed++;
        echo "❌ {$label} 失败 (exit {$code})\n\n";
    }
}

echo str_repeat('═', 50)."\n";
if ($failed === 0) {
    echo "✅ 全部验证通过\n";
    exit(0);
}

echo "❌ {$failed} 项验证失败\n";
exit(1);
