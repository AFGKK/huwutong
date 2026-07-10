<?php

/**
 * 准备 PostgreSQL 测试库（本地 phpEnv 执行一次即可）
 *
 *   php scripts/prepare-test-pgsql.php
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

$pdo->exec("SELECT 1 FROM pg_database WHERE datname = '{$database}'")
    ?: null;

$exists = $pdo->query("SELECT 1 FROM pg_database WHERE datname = ".$pdo->quote($database))->fetchColumn();
if (! $exists) {
    $pdo->exec("CREATE DATABASE {$database}");
    echo "Created database {$database}\n";
}

$pdoDb = new PDO("pgsql:host={$host};port={$port};dbname={$database}", $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdoDb->exec('CREATE EXTENSION IF NOT EXISTS vector');

try {
    $pdo->exec("ALTER DATABASE {$database} SET max_locks_per_transaction = 512");
    echo "Set max_locks_per_transaction=512 for {$database}\n";
} catch (PDOException $e) {
    echo "Note: could not ALTER DATABASE max_locks_per_transaction (restart PG or set in postgresql.conf)\n";
}

echo "Prepared {$database}: pgvector enabled\n";
echo "Run: php artisan migrate --force --env=testing\n";
