<?php
/**
 * 安全运行 P3 迁移脚本
 */
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

echo "=== 开始执行 P3 迁移 ===\n\n";

// ── 迁移1: RBAC 增强 ──
echo "--- [1/2] RBAC 权限增强 ---\n";
$migrator = $app->make('migrator');
$path = __DIR__.'/database/migrations/2026_06_27_000001_create_permission_audit_logs_table.php';

if (function_exists('opcache_invalidate')) {
    opcache_invalidate($path, true);
}

try {
    require $path;
    $migration = include $path;
    if (method_exists($migration, 'up')) {
        $migration->up();
        echo "  ✅ RBAC 迁移完成\n";
    }
} catch (\Exception $e) {
    echo "  ⚠️  RBAC 迁移: " . $e->getMessage() . "\n";
}

// ── 迁移2: 结算系统 ──
echo "\n--- [2/2] 财务结算系统 ---\n";
$path2 = __DIR__.'/database/migrations/2026_06_27_000002_create_settlement_tables.php';

if (function_exists('opcache_invalidate')) {
    opcache_invalidate($path2, true);
}

try {
    require $path2;
    $migration2 = include $path2;
    if (method_exists($migration2, 'up')) {
        $migration2->up();
        echo "  ✅ 结算迁移完成\n";
    }
} catch (\Exception $e) {
    echo "  ⚠️  结算迁移: " . $e->getMessage() . "\n";
}

echo "\n=== 迁移执行完毕 ===\n";
echo "\n=== 最终表状态 ===\n";
$tables = [
    'permission_audit_logs', 'role_hierarchy', 'role_templates',
    'settlement_cycles', 'settlement_batches', 'settlement_batch_items', 'platform_fees',
];
foreach ($tables as $table) {
    $status = Schema::hasTable($table) ? '✅' : '❌';
    echo "  $status $table\n";
}
echo "\n";
