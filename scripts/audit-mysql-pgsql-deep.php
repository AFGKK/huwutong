<?php

/**
 * 深度审计：MySQL 行是否在 PG 中存在（按 id）
 * 用法: php scripts/audit-mysql-pgsql-deep.php
 */

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$mysql = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', env('MYSQL_HOST', '127.0.0.1'), env('MYSQL_PORT', '3306'), env('MYSQL_DATABASE', 'huwutong')),
    env('MYSQL_USERNAME', 'root'),
    env('MYSQL_PASSWORD', 'root'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$pgsql = new PDO(
    sprintf('pgsql:host=%s;port=%s;dbname=%s', env('DB_HOST', '127.0.0.1'), env('DB_PORT', '5432'), env('DB_DATABASE', 'huwutong')),
    env('DB_USERNAME', 'postgres'),
    env('DB_PASSWORD', ''),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

function qid(string $name): string
{
    return '"'.str_replace('"', '""', $name).'"';
}

$ignore = ['telescope_', 'apm_requests', 'migrations', 'failed_jobs', 'jobs', 'job_batches'];

echo "=== 深度审计：MySQL 数据是否在 PG 中完整 ===\n\n";

// 1. 行数：MySQL > PG
$mysqlTables = $mysql->query(
    "SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema = DATABASE() AND TABLE_TYPE = 'BASE TABLE'"
)->fetchAll(PDO::FETCH_COLUMN);

$dataLoss = [];
foreach ($mysqlTables as $table) {
    foreach ($ignore as $p) {
        if (str_starts_with($table, $p) || $table === $p) {
            continue 2;
        }
    }
    $m = (int) $mysql->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    $p = (int) $pgsql->query('SELECT COUNT(*) FROM '.qid($table))->fetchColumn();
    if ($m > $p) {
        $dataLoss[] = compact('table', 'm', 'p') + ['missing' => $m - $p];
    }
}

echo '--- MySQL 行数 > PG（数据缺失风险）: '.count($dataLoss)." ---\n";
if ($dataLoss === []) {
    echo "✅ 无此类表\n\n";
} else {
    foreach ($dataLoss as $row) {
        echo "  ⚠️  {$row['table']}: MySQL={$row['m']} PG={$row['p']} 缺 {$row['missing']}\n";
    }
    echo "\n";
}

// 2. 核心表 ID 级校验
$core = [
    'users', 'customers', 'licenses', 'devices', 'products', 'product_skus',
    'subscriptions', 'site_settings', 'role_has_permissions', 'permissions', 'roles',
    'pages', 'pricing_plans', 'product_categories', 'coupons', 'carts', 'tenants',
    'license_templates', 'china_invoices', 'china_invoice_items', 'translations',
];

echo "--- 核心表 ID 存在性 ---\n";
$idIssues = 0;
foreach ($core as $table) {
    try {
        $ids = $mysql->query("SELECT id FROM `{$table}` ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        echo "  {$table}: 跳过（无 id 列或表不存在）\n";
        continue;
    }

    if ($ids === []) {
        echo "  {$table}: MySQL 空表 ✅\n";
        continue;
    }

    $in = implode(',', array_map('intval', $ids));
    $pgIds = $pgsql->query('SELECT id FROM '.qid($table)." WHERE id IN ({$in})")->fetchAll(PDO::FETCH_COLUMN);
    $missing = array_values(array_diff($ids, $pgIds));

    if ($missing === []) {
        echo "  {$table}: ".count($ids)." 条全部存在 ✅\n";
    } else {
        $idIssues++;
        echo "  {$table}: 缺 ".count($missing).' 条 id: '.implode(',', array_slice($missing, 0, 10)).(count($missing) > 10 ? '...' : '')."\n";
    }
}

echo "\n--- site_settings key 校验 ---\n";
$mysqlKeys = $mysql->query('SELECT `key` FROM site_settings ORDER BY `key`')->fetchAll(PDO::FETCH_COLUMN);
$pgKeys = $pgsql->query('SELECT "key" FROM site_settings ORDER BY "key"')->fetchAll(PDO::FETCH_COLUMN);
$missingKeys = array_diff($mysqlKeys, $pgKeys);
$extraKeys = array_diff($pgKeys, $mysqlKeys);
echo 'MySQL keys: '.count($mysqlKeys).'  PG keys: '.count($pgKeys)."\n";
if ($missingKeys === [] && $extraKeys === []) {
    echo "✅ key 完全一致\n";
} else {
    if ($missingKeys) {
        echo '⚠️  PG 缺少: '.implode(', ', array_slice($missingKeys, 0, 10)).(count($missingKeys) > 10 ? '...' : '')."\n";
    }
    if ($extraKeys) {
        echo 'ℹ️  PG 新增: '.implode(', ', array_slice($extraKeys, 0, 10)).(count($extraKeys) > 10 ? '...' : '')."\n";
    }
}

echo "\n--- 结论 ---\n";
if ($dataLoss === [] && $idIssues === 0 && $missingKeys === []) {
    echo "✅ PASS — MySQL 业务数据已完整迁移至 PostgreSQL\n";
    exit(0);
}

echo "⚠️ PARTIAL — 见上方详情\n";
exit($dataLoss !== [] || $idIssues > 0 ? 1 : 0);
