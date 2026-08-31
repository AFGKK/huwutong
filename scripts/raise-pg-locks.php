<?php

/**
 * 提升本地 PostgreSQL max_locks_per_transaction（需管理员权限）
 *
 *   # 以管理员打开 PowerShell 后执行：
 *   php scripts/raise-pg-locks.php
 *
 * 或手动：
 *   1. 编辑 C:\Program Files\PostgreSQL\16\data\postgresql.conf
 *      max_locks_per_transaction = 512
 *   2. Restart-Service postgresql-x64-16
 */

declare(strict_types=1);

$password = getenv('DB_PASSWORD') ?: '';
if ($password === '') {
    foreach (['.env.testing', '.env'] as $file) {
        $path = dirname(__DIR__).DIRECTORY_SEPARATOR.$file;
        if (! is_file($path)) {
            continue;
        }
        foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
            if (str_starts_with($line, 'DB_PASSWORD=')) {
                $password = trim(substr($line, strlen('DB_PASSWORD=')), " \t\"'");
                break 2;
            }
        }
    }
}

$pdo = new PDO('pgsql:host=127.0.0.1;port=5432;dbname=postgres', 'postgres', $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$configFile = $pdo->query('SHOW config_file')->fetchColumn();
$current = (int) $pdo->query('SHOW max_locks_per_transaction')->fetchColumn();

echo "config_file={$configFile}\n";
echo "current max_locks_per_transaction={$current}\n";

if (! is_writable($configFile) && ! is_writable(dirname($configFile))) {
    echo "ERROR: 无法写入 {$configFile}，请以管理员身份运行本脚本。\n";
    exit(1);
}

$content = file_get_contents($configFile);
$replacement = "max_locks_per_transaction = 512\t\t# raised for Laravel test suite";

if (preg_match('/^#?\s*max_locks_per_transaction\s*=.*/m', $content)) {
    $content = preg_replace('/^#?\s*max_locks_per_transaction\s*=.*/m', $replacement, $content, 1);
} else {
    $content .= "\n{$replacement}\n";
}

file_put_contents($configFile, $content);
echo "Wrote max_locks_per_transaction = 512 to config\n";

if (PHP_OS_FAMILY === 'Windows') {
    echo "Restarting postgresql-x64-16...\n";
    passthru('powershell -Command "Restart-Service postgresql-x64-16 -Force"', $code);
    if ($code !== 0) {
        echo "ERROR: 重启失败，请手动以管理员执行: Restart-Service postgresql-x64-16\n";
        exit(1);
    }
    sleep(2);
} else {
    echo "Please restart PostgreSQL manually, e.g.: sudo systemctl restart postgresql\n";
}

$pdo2 = new PDO('pgsql:host=127.0.0.1;port=5432;dbname=postgres', 'postgres', $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$after = (int) $pdo2->query('SHOW max_locks_per_transaction')->fetchColumn();
echo "After restart: max_locks_per_transaction={$after}\n";
echo $after >= 256 ? "OK\n" : "WARN: 值仍偏低，请确认服务已重启\n";
