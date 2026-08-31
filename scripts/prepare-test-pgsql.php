<?php

/**
 * 准备 PostgreSQL 测试库（本地 phpEnv 执行一次即可）
 *
 *   php scripts/prepare-test-pgsql.php
 *
 * 注意：max_locks_per_transaction 是服务端参数，ALTER DATABASE 无效。
 * Windows 本地需在 postgresql.conf 中设置并重启服务：
 *   max_locks_per_transaction = 512
 *   Restart-Service postgresql-x64-16   # 需管理员权限
 */

declare(strict_types=1);

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '5432';
$user = getenv('DB_USERNAME') ?: 'postgres';
$password = getenv('DB_PASSWORD') ?: '';
$database = getenv('DB_DATABASE') ?: 'hwut_test';

if ($password === '') {
    $envFile = dirname(__DIR__).'/.env.testing';
    if (is_file($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES) as $line) {
            if (str_starts_with($line, 'DB_PASSWORD=')) {
                $password = trim(substr($line, strlen('DB_PASSWORD=')), " \t\"'");
                break;
            }
        }
    }
}

$dsn = "pgsql:host={$host};port={$port};dbname=postgres";
$pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$exists = $pdo->query("SELECT 1 FROM pg_database WHERE datname = ".$pdo->quote($database))->fetchColumn();
if (! $exists) {
    $pdo->exec("CREATE DATABASE {$database}");
    echo "Created database {$database}\n";
}

$pdoDb = new PDO("pgsql:host={$host};port={$port};dbname={$database}", $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdoDb->exec('CREATE EXTENSION IF NOT EXISTS vector');

$locks = (int) $pdoDb->query('SHOW max_locks_per_transaction')->fetchColumn();
$configFile = $pdo->query('SHOW config_file')->fetchColumn();

echo "Prepared {$database}: pgvector enabled\n";
echo "Current max_locks_per_transaction={$locks}\n";

if ($locks < 256) {
    echo "\n⚠ WARNING: max_locks_per_transaction={$locks} 过低，全量 PHPUnit 可能触发 out of shared memory。\n";
    echo "  请编辑 {$configFile}：\n";
    echo "    max_locks_per_transaction = 512\n";
    echo "  然后以管理员身份重启 PostgreSQL，例如：\n";
    echo "    Restart-Service postgresql-x64-16\n";
    echo "  或运行: php scripts/raise-pg-locks.php（需管理员）\n";
} else {
    echo "OK: max_locks_per_transaction={$locks} 足够运行全量测试\n";
}

echo "Run: php artisan migrate --force --env=testing\n";
