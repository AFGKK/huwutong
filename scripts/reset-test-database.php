<?php

declare(strict_types=1);

/**
 * 重置 PostgreSQL 测试库（清空数据 + 迁移，不 seed）
 *
 *   php scripts/reset-test-database.php
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (config('database.default') !== 'pgsql') {
    fwrite(STDERR, "DB_CONNECTION must be pgsql (use --env=testing)\n");
    exit(1);
}

$connection = Illuminate\Support\Facades\DB::connection();
$except = ['migrations'];

$tables = collect($connection->select(
    "SELECT tablename FROM pg_tables WHERE schemaname = 'public'"
))->pluck('tablename')->reject(fn (string $table) => in_array($table, $except, true));

echo 'Truncating '.$tables->count()." tables...\n";

$connection->statement("SET session_replication_role = 'replica'");

foreach ($tables as $table) {
    try {
        $connection->statement("TRUNCATE TABLE \"{$table}\" RESTART IDENTITY CASCADE");
    } catch (Throwable $e) {
        echo "  skip {$table}: {$e->getMessage()}\n";
    }
}

$connection->statement("SET session_replication_role = 'origin'");

Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);

echo "Done. Test database reset without seed.\n";
