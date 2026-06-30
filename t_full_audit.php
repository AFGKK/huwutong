<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

echo "=== 全面数据审计 ===\n\n";

// ── 1. 检查所有表是否存在及行数 ──
$allTables = \Illuminate\Support\Facades\DB::select('SHOW TABLES');
$key = 'Tables_in_' . env('DB_DATABASE', 'huwutong');
$tables = [];
foreach ($allTables as $t) {
    $tables[] = $t->$key;
}

echo "数据库中共 " . count($tables) . " 张表\n\n";

// ── 2. 检查关键业务表数据 ──
$importantTables = [
    'users' => '用户',
    'tenants' => '租户',
    'permissions' => '权限',
    'roles' => '角色',
    'role_has_permissions' => '角色权限关联',
    'model_has_roles' => '用户角色',
    'products' => '产品',
    'customers' => '客户',
    'licenses' => 'License',
    'subscriptions' => '订阅',
    'devices' => '设备',
    'tax_rates' => '税率',
    'pricing_plans' => '定价方案',
    'coupons' => '优惠券',
    'site_settings' => '系统设置',
    'pages' => '页面',
    'custom_domains' => '自定义域名',
    'llm_providers' => 'LLM 提供商',
    'login_policies' => '登录策略',
    'rate_limit_rules' => '限流规则',
    'alert_rules' => '告警规则',
    'automation_rules' => '自动化规则',
    'dashboard_widget_templates' => '仪表盘 Widget',
    'sla_contracts' => 'SLA 合同',
    'license_templates' => 'License 模板',
    'invite_channels' => '邀请渠道',
    'quota_plans' => '配额方案',
    'workflow_definitions' => '工作流定义',
    'chat_faqs' => '聊天 FAQ',
    'ticket_categories' => '工单分类',
    'kb_categories' => '知识库分类',
    'kb_articles' => '知识库文章',
    'api_sdk_configs' => 'SDK 配置',
    'api_doc_tags' => 'API 文档标签',
];

echo str_pad('表名', 35) . '行数' . str_pad('状态', 12, ' ', STR_PAD_LEFT) . "  说明\n";
echo str_repeat('-', 70) . "\n";

$emptyExpected = []; // 空表但应该有数据
foreach ($importantTables as $table => $label) {
    if (!in_array($table, $tables)) {
        echo str_pad($table, 35) . '  N/A' . str_pad('❌ 不存在', 12, ' ', STR_PAD_LEFT) . "  迁移未创建该表\n";
        $emptyExpected[] = $table;
        continue;
    }
    try {
        $count = \Illuminate\Support\Facades\DB::table($table)->count();
        $status = $count > 0 ? '✅' : '⚠️ 空';
        echo str_pad($table, 35) . str_pad($count, 6, ' ', STR_PAD_LEFT) . str_pad($status, 12, ' ', STR_PAD_LEFT) . "  {$label}\n";
        if ($count == 0) $emptyExpected[] = $table;
    } catch (\Exception $e) {
        echo str_pad($table, 35) . '  ERR' . str_pad('❌ 错误', 12, ' ', STR_PAD_LEFT) . "  {$e->getMessage()}\n";
    }
}

echo "\n=== 发现 " . count($emptyExpected) . " 个需要修复的问题 ===\n";
if (!empty($emptyExpected)) {
    echo "空表列表: " . implode(', ', $emptyExpected) . "\n";
}

// ── 3. 检查已知的列缺失问题 ──
echo "\n=== 检查已知列缺失问题 ===\n";
$columnChecks = [
    ['table' => 'orders', 'column' => 'deleted_at', 'issue' => '仪表盘查询报错'],
    ['table' => 'product_skus', 'column' => 'low_stock_threshold', 'issue' => 'SKU 页面报错'],
    ['table' => 'workflow_definitions', 'column' => 'created_by', 'issue' => '工作流创建报错'],
];

foreach ($columnChecks as $check) {
    $t = $check['table'];
    $c = $check['column'];
    if (in_array($t, $tables) && Schema::hasColumn($t, $c)) {
        echo "  ✅ {$t}.{$c} 存在\n";
    } elseif (in_array($t, $tables)) {
        echo "  ❌ {$t}.{$c} 缺失 - {$check['issue']}\n";
    } else {
        echo "  ⚠️ {$t} 表不存在，跳过\n";
    }
}

// ── 4. 检查用户 tenant_id ──
echo "\n=== 检查用户租户关联 ===\n";
$badUsers = \Illuminate\Support\Facades\DB::table('users')->whereNull('tenant_id')->get();
echo "tenant_id 为空的用户: " . $badUsers->count() . "\n";
foreach ($badUsers as $u) {
    echo "  ❌ User {$u->id}: {$u->email}\n";
}
