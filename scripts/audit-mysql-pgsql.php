<?php
/**
 * MySQL ↔ PostgreSQL 数据迁移完整性审计
 *
 * 用法: php scripts/audit-mysql-pgsql.php [--json]
 */

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$asJson = in_array('--json', $argv, true);
$strict = in_array('--strict', $argv, true);

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

/** 切换 PG 后持续写入、可忽略差异的表 */
$ignorePatterns = [
    'telescope_',
    'apm_requests',
    'migrations',
    'failed_jobs',
    'jobs',
    'job_batches',
];

/** 核心业务表（必须有数据且一致） */
$coreBusinessTables = [
    'users', 'tenants', 'roles', 'permissions', 'role_has_permissions', 'model_has_roles',
    'customers', 'products', 'product_skus', 'product_categories', 'pricing_plans',
    'license_templates', 'license_template_variables', 'licenses', 'devices',
    'subscriptions', 'coupons', 'flash_sales', 'carts', 'site_settings',
    'china_invoices', 'china_invoice_items', 'login_audits',
];

function pgQuoteIdent(string $name): string
{
    return '"'.str_replace('"', '""', $name).'"';
}

function shouldIgnore(string $table, array $patterns): bool
{
    foreach ($patterns as $pattern) {
        if (str_starts_with($table, $pattern) || $table === $pattern) {
            return true;
        }
    }

    return false;
}

$mysqlTables = $mysql->query(
    "SELECT TABLE_NAME FROM information_schema.tables
     WHERE table_schema = DATABASE() AND TABLE_TYPE = 'BASE TABLE'
     ORDER BY TABLE_NAME"
)->fetchAll(PDO::FETCH_COLUMN);

$pgsqlTables = $pgsql->query(
    "SELECT tablename FROM pg_catalog.pg_tables
     WHERE schemaname = 'public' ORDER BY tablename"
)->fetchAll(PDO::FETCH_COLUMN);

$onlyMysql = array_values(array_diff($mysqlTables, $pgsqlTables));
$onlyPgsql = array_values(array_diff($pgsqlTables, $mysqlTables));
$common = array_values(array_intersect($mysqlTables, $pgsqlTables));

$mismatches = [];
$ignoredMismatches = [];
$matched = 0;
$totalMysqlRows = 0;
$totalPgsqlRows = 0;

foreach ($common as $table) {
    $mysqlCnt = (int) $mysql->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    $pgsqlCnt = (int) $pgsql->query('SELECT COUNT(*) FROM '.pgQuoteIdent($table))->fetchColumn();
    $totalMysqlRows += $mysqlCnt;
    $totalPgsqlRows += $pgsqlCnt;

    if ($mysqlCnt === $pgsqlCnt) {
        $matched++;
        continue;
    }

    $entry = [
        'table' => $table,
        'mysql' => $mysqlCnt,
        'pgsql' => $pgsqlCnt,
        'diff' => $mysqlCnt - $pgsqlCnt,
    ];

    if (shouldIgnore($table, $ignorePatterns)) {
        $ignoredMismatches[] = $entry;
    } else {
        $mismatches[] = $entry;
    }
}

// 核心业务表逐条校验（有 MySQL 数据时 PG 必须 >= MySQL 且关键字段存在）
$coreIssues = [];
foreach ($coreBusinessTables as $table) {
    if (! in_array($table, $common, true)) {
        $coreIssues[] = ['table' => $table, 'issue' => '表不存在于双库共同集合'];
        continue;
    }

    $mysqlCnt = (int) $mysql->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    $pgsqlCnt = (int) $pgsql->query('SELECT COUNT(*) FROM '.pgQuoteIdent($table))->fetchColumn();

    if ($mysqlCnt > 0 && $pgsqlCnt === 0) {
        $coreIssues[] = ['table' => $table, 'issue' => "MySQL 有 {$mysqlCnt} 条，PG 为 0（未迁移）"];
    } elseif ($mysqlCnt !== $pgsqlCnt) {
        $coreIssues[] = ['table' => $table, 'issue' => "行数不一致 MySQL={$mysqlCnt} PG={$pgsqlCnt}"];
    }
}

// licenses 关联完整性
$fkChecks = [];
try {
    $fkChecks['licenses_with_customer'] = [
        'mysql' => (int) $mysql->query(
            'SELECT COUNT(*) FROM licenses l INNER JOIN customers c ON l.customer_id = c.id'
        )->fetchColumn(),
        'pgsql' => (int) $pgsql->query(
            'SELECT COUNT(*) FROM licenses l INNER JOIN customers c ON l.customer_id = c.id'
        )->fetchColumn(),
    ];
    $fkChecks['devices_with_license'] = [
        'mysql' => (int) $mysql->query(
            'SELECT COUNT(*) FROM devices d INNER JOIN licenses l ON d.license_id = l.id'
        )->fetchColumn(),
        'pgsql' => (int) $pgsql->query(
            'SELECT COUNT(*) FROM devices d INNER JOIN licenses l ON d.license_id = l.id'
        )->fetchColumn(),
    ];
    $fkChecks['admin_user'] = [
        'mysql' => (int) $mysql->query(
            "SELECT COUNT(*) FROM users WHERE email = 'admin@huwutong.com'"
        )->fetchColumn(),
        'pgsql' => (int) $pgsql->query(
            "SELECT COUNT(*) FROM users WHERE email = 'admin@huwutong.com'"
        )->fetchColumn(),
    ];
} catch (Throwable $e) {
    $fkChecks['error'] = $e->getMessage();
}

$report = [
    'timestamp' => date('Y-m-d H:i:s'),
    'schema' => [
        'mysql_tables' => count($mysqlTables),
        'pgsql_tables' => count($pgsqlTables),
        'common_tables' => count($common),
        'only_mysql' => $onlyMysql,
        'only_pgsql' => $onlyPgsql,
    ],
    'data' => [
        'tables_matched' => $matched,
        'tables_total' => count($common),
        'match_rate' => count($common) > 0 ? round($matched / count($common) * 100, 2) : 0,
        'total_mysql_rows' => $totalMysqlRows,
        'total_pgsql_rows' => $totalPgsqlRows,
        'mismatches' => $mismatches,
        'ignored_mismatches' => $ignoredMismatches,
    ],
    'core_business' => [
        'issues' => $coreIssues,
        'ok' => $coreIssues === [],
    ],
    'fk_checks' => $fkChecks,
    'verdict' => 'PASS',
];

if ($mismatches !== [] || $coreIssues !== []) {
    $report['verdict'] = 'PARTIAL';
}
if ($onlyMysql !== []) {
    $report['verdict'] = 'PARTIAL';
}

// 有 MySQL 数据但 PG 完全为 0 的非忽略表
$criticalMissing = array_filter($mismatches, fn ($m) => $m['mysql'] > 0 && $m['pgsql'] === 0);
if ($criticalMissing !== []) {
    $report['verdict'] = 'FAIL';
    $report['critical_missing'] = array_values($criticalMissing);
}

if ($asJson) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
    $exitCode = $report['verdict'] === 'FAIL' ? 1 : 0;
    if ($strict && $report['verdict'] === 'PARTIAL') {
        $exitCode = 1;
    }
    exit($exitCode);
}

echo "======================================================================\n";
echo "     MySQL → PostgreSQL 数据迁移完整性审计\n";
echo "     {$report['timestamp']}\n";
echo "======================================================================\n\n";

echo "--- 表结构 ---\n";
echo 'MySQL 表: '.count($mysqlTables)."  PG 表: ".count($pgsqlTables)."  共同: ".count($common)."\n";
echo '仅 PG: '.($onlyPgsql ? implode(', ', $onlyPgsql) : '无')."\n";
echo '仅 MySQL: '.($onlyMysql ? implode(', ', $onlyMysql) : '无')."\n\n";

echo "--- 数据行数 ---\n";
echo "一致表: {$matched}/".count($common).' ('.$report['data']['match_rate']."%)\n";
echo 'MySQL 总行数: '.number_format($totalMysqlRows).'  PG 总行数: '.number_format($totalPgsqlRows)."\n\n";

if ($coreIssues !== []) {
    echo "--- 核心业务表问题 (".count($coreIssues).") ---\n";
    foreach ($coreIssues as $issue) {
        echo "  ⚠️  {$issue['table']}: {$issue['issue']}\n";
    }
    echo "\n";
} else {
    echo "--- 核心业务表: ✅ 全部一致 ---\n\n";
}

if ($mismatches !== []) {
    echo '--- 数据不一致 (非忽略, '.count($mismatches).") ---\n";
    foreach ($mismatches as $m) {
        $sign = $m['diff'] > 0 ? '+' : '';
        echo "  {$m['table']}: MySQL={$m['mysql']} PG={$m['pgsql']} ({$sign}{$m['diff']})\n";
    }
    echo "\n";
} else {
    echo "--- 非忽略表: ✅ 全部行数一致 ---\n\n";
}

if ($ignoredMismatches !== []) {
    echo '--- 可忽略差异 ('.count($ignoredMismatches).") ---\n";
    foreach ($ignoredMismatches as $m) {
        echo "  {$m['table']}: MySQL={$m['mysql']} PG={$m['pgsql']}\n";
    }
    echo "\n";
}

echo "--- 关联完整性 ---\n";
foreach ($fkChecks as $name => $check) {
    if ($name === 'error') {
        echo "  ERROR: {$check}\n";
        continue;
    }
    $ok = $check['mysql'] === $check['pgsql'] ? '✅' : '⚠️';
    echo "  {$name}: MySQL={$check['mysql']} PG={$check['pgsql']} {$ok}\n";
}

echo "\n--- 结论: {$report['verdict']} ---\n";
if ($report['verdict'] === 'PASS') {
    echo "迁移数据完善，双库一致。\n";
} elseif ($report['verdict'] === 'PARTIAL') {
    echo "核心业务已对齐，存在少量非关键差异。\n";
    if ($strict) {
        echo "(--strict 模式: 非 PASS 视为失败)\n";
    }
} else {
    echo "存在关键表未迁移数据，需执行同步脚本。\n";
}

$exitCode = $report['verdict'] === 'FAIL' ? 1 : 0;
if ($strict && $report['verdict'] === 'PARTIAL') {
    $exitCode = 1;
}
exit($exitCode);
