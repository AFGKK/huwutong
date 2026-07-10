<?php
/**
 * MySQL → PostgreSQL 业务数据同步脚本
 *
 * 用法:
 *   php scripts/sync-mysql-to-pgsql.php              # 同步核心表
 *   php scripts/sync-mysql-to-pgsql.php --dry-run    # 仅预览
 *   php scripts/sync-mysql-to-pgsql.php --all        # 同步所有不一致表（除 telescope/apm）
 *
 * 环境变量（可选，默认读取 .env 中 PG + 本地 MySQL root/root）:
 *   MYSQL_HOST, MYSQL_PORT, MYSQL_DATABASE, MYSQL_USERNAME, MYSQL_PASSWORD
 */

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$dryRun = in_array('--dry-run', $argv, true);
$syncAll = in_array('--all', $argv, true);

$mysqlDsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    env('MYSQL_HOST', '127.0.0.1'),
    env('MYSQL_PORT', '3306'),
    env('MYSQL_DATABASE', env('DB_DATABASE', 'huwutong'))
);

$pgDsn = sprintf(
    'pgsql:host=%s;port=%s;dbname=%s',
    env('DB_HOST', '127.0.0.1'),
    env('DB_PORT', '5432'),
    env('DB_DATABASE', 'huwutong')
);

try {
    $mysql = new PDO($mysqlDsn, env('MYSQL_USERNAME', 'root'), env('MYSQL_PASSWORD', 'root'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pgsql = new PDO($pgDsn, env('DB_USERNAME', 'postgres'), env('DB_PASSWORD', ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (Throwable $e) {
    fwrite(STDERR, "连接失败: {$e->getMessage()}\n");
    exit(1);
}

/** 按外键依赖顺序同步的核心表 */
$coreTables = [
    'customers',
    'products',
    'product_skus',
    'license_templates',
    'license_template_variables',
    'licenses',
    'devices',
    'license_activations',
    'subscriptions',
    'carts',
    'cart_items',
    'coupons',
    'coupon_usages',
    'flash_sales',
    'dashboard_widgets',
    'data_lineage_records',
    'kb_articles',
    'logs',
    'metered_tiered_pricings',
    'metered_tier_pricing_tiers',
    'metered_billing_alerts',
    'metered_auto_switch_rules',
    'china_invoice_templates',
    'china_tax_devices',
    'china_invoices',
    'china_invoice_items',
    'china_tax_reports',
    'login_audits',
    'role_has_permissions',
    'model_has_roles',
    'site_settings',
];

/** 无单列 id 的复合主键表 */
$compositeKeys = [
    'role_has_permissions' => ['permission_id', 'role_id'],
    'model_has_roles' => ['role_id', 'model_type', 'model_id'],
];

/** 按唯一键 upsert（非 id 主冲突列） */
$conflictColumns = [
];

/** 同步前清空（id/唯一键映射不一致时全量替换） */
$truncateBeforeSync = [
    'site_settings',
];

/** 跳过的调试/日志表 */
$skipTables = [
    'telescope_entries',
    'telescope_entries_tags',
    'telescope_monitoring',
    'apm_requests',
    'migrations',
    'failed_jobs',
    'jobs',
    'job_batches',
];

echo "=== MySQL → PostgreSQL 数据同步 ===\n";
echo '模式: '.($dryRun ? 'DRY-RUN' : 'APPLY')."\n";
echo '时间: '.date('Y-m-d H:i:s')."\n\n";

function pgQuoteIdent(string $name): string
{
    return '"'.str_replace('"', '""', $name).'"';
}

function getTableColumns(PDO $pdo, string $driver, string $table): array
{
    if ($driver === 'mysql') {
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
        $cols = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $cols[] = $row['Field'];
        }

        return $cols;
    }

    $stmt = $pdo->prepare(
        'SELECT column_name FROM information_schema.columns
         WHERE table_schema = ? AND table_name = ?
         ORDER BY ordinal_position'
    );
    $stmt->execute(['public', $table]);

    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function tableExists(PDO $pdo, string $driver, string $table): bool
{
    if ($driver === 'mysql') {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = ?'
        );
        $stmt->execute([$table]);

        return (int) $stmt->fetchColumn() > 0;
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = ? AND table_name = ?'
    );
    $stmt->execute(['public', $table]);

    return (int) $stmt->fetchColumn() > 0;
}

function syncTable(
    PDO $mysql,
    PDO $pgsql,
    string $table,
    bool $dryRun,
    array $compositeKeys,
    array $conflictColumns = [],
    array $truncateBeforeSync = []
): array {
    if (! tableExists($mysql, 'mysql', $table) || ! tableExists($pgsql, 'pgsql', $table)) {
        return ['table' => $table, 'status' => 'skip', 'reason' => '表不存在', 'synced' => 0];
    }

    $mysqlCols = getTableColumns($mysql, 'mysql', $table);
    $pgCols = getTableColumns($pgsql, 'pgsql', $table);
    $columns = array_values(array_intersect($mysqlCols, $pgCols));

    if ($columns === []) {
        return ['table' => $table, 'status' => 'skip', 'reason' => '无共同列', 'synced' => 0];
    }

    $mysqlCount = (int) $mysql->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    $pgCountBefore = (int) $pgsql->query('SELECT COUNT(*) FROM '.pgQuoteIdent($table))->fetchColumn();

    if ($mysqlCount === 0) {
        return ['table' => $table, 'status' => 'skip', 'reason' => 'MySQL 无数据', 'synced' => 0, 'pg_before' => $pgCountBefore];
    }

    if ($dryRun) {
        return [
            'table' => $table,
            'status' => 'dry-run',
            'mysql' => $mysqlCount,
            'pg_before' => $pgCountBefore,
            'synced' => $mysqlCount,
        ];
    }

    $rows = $mysql->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
    $synced = 0;

    $pgsql->beginTransaction();

    try {
        // 复合主键表或需全量替换的表：先清空再插入
        if (isset($compositeKeys[$table]) || in_array($table, $truncateBeforeSync, true)) {
            $pgsql->exec('DELETE FROM '.pgQuoteIdent($table));
            $colList = implode(', ', array_map(pgQuoteIdent(...), $columns));
            $placeholders = implode(', ', array_fill(0, count($columns), '?'));
            $insertSql = 'INSERT INTO '.pgQuoteIdent($table)." ({$colList}) VALUES ({$placeholders})";
            $stmt = $pgsql->prepare($insertSql);
            foreach ($rows as $row) {
                $vals = array_map(fn ($c) => $row[$c] ?? null, $columns);
                $stmt->execute($vals);
                $synced++;
            }
        } elseif (isset($conflictColumns[$table])) {
            $conflict = $conflictColumns[$table];
            $colList = implode(', ', array_map(pgQuoteIdent(...), $columns));
            $placeholders = implode(', ', array_fill(0, count($columns), '?'));
            $updateCols = array_filter($columns, fn ($c) => ! in_array($c, $conflict, true));
            $updateSet = implode(', ', array_map(fn ($c) => pgQuoteIdent($c).' = EXCLUDED.'.pgQuoteIdent($c), $updateCols));
            $conflictList = implode(', ', array_map(pgQuoteIdent(...), $conflict));
            $insertSql = 'INSERT INTO '.pgQuoteIdent($table)." ({$colList}) VALUES ({$placeholders})"
                ." ON CONFLICT ({$conflictList}) DO UPDATE SET {$updateSet}";
            $stmt = $pgsql->prepare($insertSql);
            foreach ($rows as $row) {
                $vals = array_map(fn ($c) => $row[$c] ?? null, $columns);
                $stmt->execute($vals);
                $synced++;
            }
        } elseif (in_array('id', $columns, true)) {
            $colList = implode(', ', array_map(pgQuoteIdent(...), $columns));
            $placeholders = implode(', ', array_fill(0, count($columns), '?'));
            $updateCols = array_filter($columns, fn ($c) => $c !== 'id');
            $updateSet = implode(', ', array_map(fn ($c) => pgQuoteIdent($c).' = EXCLUDED.'.pgQuoteIdent($c), $updateCols));
            $insertSql = 'INSERT INTO '.pgQuoteIdent($table)." ({$colList}) VALUES ({$placeholders})"
                ." ON CONFLICT (".pgQuoteIdent('id').") DO UPDATE SET {$updateSet}";
            $stmt = $pgsql->prepare($insertSql);
            foreach ($rows as $row) {
                $vals = array_map(fn ($c) => $row[$c] ?? null, $columns);
                $stmt->execute($vals);
                $synced++;
            }
        } else {
            $pgsql->exec('DELETE FROM '.pgQuoteIdent($table));
            $colList = implode(', ', array_map(pgQuoteIdent(...), $columns));
            $placeholders = implode(', ', array_fill(0, count($columns), '?'));
            $insertSql = 'INSERT INTO '.pgQuoteIdent($table)." ({$colList}) VALUES ({$placeholders})";
            $stmt = $pgsql->prepare($insertSql);
            foreach ($rows as $row) {
                $vals = array_map(fn ($c) => $row[$c] ?? null, $columns);
                $stmt->execute($vals);
                $synced++;
            }
        }

        $pgsql->commit();
    } catch (Throwable $e) {
        $pgsql->rollBack();
        throw $e;
    }

    $pgCountAfter = (int) $pgsql->query('SELECT COUNT(*) FROM '.pgQuoteIdent($table))->fetchColumn();

    return [
        'table' => $table,
        'status' => 'ok',
        'mysql' => $mysqlCount,
        'pg_before' => $pgCountBefore,
        'pg_after' => $pgCountAfter,
        'synced' => $synced,
    ];
}

function fixSequences(PDO $pgsql): int
{
    $fixed = 0;
    $seqs = $pgsql->query(
        "SELECT sequencename FROM pg_sequences WHERE schemaname = 'public'"
    )->fetchAll(PDO::FETCH_COLUMN);

    foreach ($seqs as $seqName) {
        if (! str_ends_with($seqName, '_id_seq')) {
            continue;
        }
        $table = str_replace('_id_seq', '', $seqName);
        try {
            $maxId = (int) $pgsql->query(
                'SELECT COALESCE(MAX(id), 0) FROM '.pgQuoteIdent($table)
            )->fetchColumn();
            $curr = (int) $pgsql->query(
                "SELECT last_value FROM pg_sequences WHERE sequencename = '{$seqName}'"
            )->fetchColumn();
            if ($maxId > $curr) {
                $pgsql->query("SELECT setval('{$seqName}', {$maxId})");
                $fixed++;
            }
        } catch (Throwable) {
            // 表无 id 列则跳过
        }
    }

    return $fixed;
}

$tablesToSync = $coreTables;

if ($syncAll) {
    $mysqlTables = $mysql->query(
        "SELECT TABLE_NAME FROM information_schema.tables
         WHERE table_schema = DATABASE() AND TABLE_TYPE = 'BASE TABLE'"
    )->fetchAll(PDO::FETCH_COLUMN);
    $tablesToSync = array_values(array_diff($mysqlTables, $skipTables));
}

$results = [];
$errors = [];

foreach ($tablesToSync as $table) {
    try {
        $result = syncTable($mysql, $pgsql, $table, $dryRun, $compositeKeys, $conflictColumns, $truncateBeforeSync);
        $results[] = $result;
        $status = $result['status'];
        $line = str_pad($table, 32)." [{$status}]";
        if (isset($result['mysql'])) {
            $line .= " MySQL={$result['mysql']}";
        }
        if (isset($result['pg_after'])) {
            $line .= " PG={$result['pg_after']}";
        }
        if (isset($result['synced']) && $status !== 'skip') {
            $line .= " synced={$result['synced']}";
        }
        if (isset($result['reason'])) {
            $line .= " ({$result['reason']})";
        }
        echo $line."\n";
    } catch (Throwable $e) {
        $errors[] = "{$table}: {$e->getMessage()}";
        echo str_pad($table, 32)." [ERROR] {$e->getMessage()}\n";
    }
}

if (! $dryRun && $errors === []) {
    $fixed = fixSequences($pgsql);
    echo "\n序列修复: {$fixed} 个\n";
}

echo "\n--- 关键表验证 ---\n";
$keyTables = ['licenses', 'customers', 'devices', 'products', 'product_skus', 'subscriptions', 'role_has_permissions'];
foreach ($keyTables as $tbl) {
    try {
        $mc = (int) $mysql->query("SELECT COUNT(*) FROM `{$tbl}`")->fetchColumn();
        $pc = (int) $pgsql->query('SELECT COUNT(*) FROM '.pgQuoteIdent($tbl))->fetchColumn();
        $ok = $mc === $pc ? '✅' : '⚠️';
        echo str_pad($tbl, 24)." MySQL={$mc}  PG={$pc}  {$ok}\n";
    } catch (Throwable $e) {
        echo str_pad($tbl, 24)." ERROR: {$e->getMessage()}\n";
    }
}

if ($errors !== []) {
    echo "\n失败 ".count($errors)." 项:\n";
    foreach ($errors as $err) {
        echo "  - {$err}\n";
    }
    exit(1);
}

echo "\n同步完成。\n";
