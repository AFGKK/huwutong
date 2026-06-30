<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$checks = [
    // Site settings - already restored
    'site_settings' => null,
    'pages' => null,
    // Core
    'permissions' => null,
    'roles' => null,
    'role_has_permissions' => null,
    'users' => null,
    'model_has_roles' => null,
    'tenants' => null,
    // LLM
    'llm_providers' => null,
    // Demo
    'products' => null,
    'customers' => null,
    'licenses' => null,
    'subscriptions' => null,
    'tax_rates' => null,
    // Features
    'alert_rules' => null,
    'dashboard_widget_templates' => null,
    'import_mapping_templates' => null,
    'login_policies' => null,
    'sla_contracts' => null,
    'audit_action_dicts' => null,
    'automation_rules' => null,
    'invite_channels' => null,
    'license_templates' => null,
    'portal_branding_configs' => null,
    // Unregistered seeders
    'pricing_plans' => ['unregistered seeder'],
    'coupons' => ['unregistered seeder'],
    'chat_faqs' => ['unregistered seeder'],
    'rate_limit_rules' => ['unregistered seeder'],
    'quota_plans' => ['unregistered seeder'],
    'workflow_definitions' => ['unregistered seeder'],
];

echo "=== 数据库数据恢复检查 ===\n\n";
echo str_pad('表名', 35) . '状态' . str_pad('行数', 10, ' ', STR_PAD_LEFT) . "  说明\n";
echo str_repeat('-', 70) . "\n";

foreach ($checks as $table => $info) {
    $note = is_array($info) ? $info[0] : '';
    if (\Illuminate\Support\Facades\Schema::hasTable($table)) {
        $count = \Illuminate\Support\Facades\DB::table($table)->count();
        $status = $count > 0 ? '✅ 有数据' : '❌ 空';
        echo str_pad($table, 35) . $status . str_pad($count, 10, ' ', STR_PAD_LEFT) . "   $note\n";
    } else {
        echo str_pad($table, 35) . '❌ 表不存在' . str_pad('-', 10, ' ', STR_PAD_LEFT) . "   $note\n";
    }
}

echo "\n=== 未注册的 Seeder（需要手动运行）===\n";
echo "  1. PricingPlanSeeder\n";
echo "  2. ChatFaqSeeder\n";
echo "  3. RateLimitRuleSeeder\n";
echo "  4. TenantIsolationSeeder\n";
echo "  5. WorkflowDefinitionSeeder\n";
echo "  6. ApiDocsPortalSeeder\n";
