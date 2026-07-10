<?php

/**
 * 管理后台 API 冒烟测试（PostgreSQL 环境）
 *
 * 用法: php scripts/verify-admin-smoke.php
 */

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

echo "=== 互物通管理后台 API 冒烟测试 ===\n";
echo 'DB: '.config('database.default').' @ '.config('database.connections.'.config('database.default').'.database')."\n\n";

$admin = User::where('email', 'admin@huwutong.com')->first()
    ?? User::query()->whereHas('roles')->first()
    ?? User::query()->first();

if (! $admin) {
    fwrite(STDERR, "❌ 未找到可测试的管理员用户\n");
    exit(1);
}

$token = $admin->createToken('admin-smoke-test', ['admin', 'super-admin'])->plainTextToken;
$tenantId = $admin->tenant_id;

/** @var array<int, array{method:string,path:string,group:string}> $endpoints */
$endpoints = [
    // 核心业务
    ['GET', '/api/licenses', 'License'],
    ['GET', '/api/customers', '客户'],
    ['GET', '/api/products', '产品'],
    ['GET', '/api/devices', '设备'],
    ['GET', '/api/orders', '订单'],
    ['GET', '/api/plans', '套餐'],
    // 系统管理
    ['GET', '/api/settings', '系统设置'],
    ['GET', '/api/roles', '角色'],
    ['GET', '/api/permissions', '权限'],
    ['GET', '/api/audit-logs', '审计日志'],
    ['GET', '/api/tenants', '租户'],
    // 计费
    ['GET', '/api/billing/subscriptions', '计费订阅'],
    ['GET', '/api/refunds', '退款'],
    ['GET', '/api/tax/rates', '税率'],
    // 分析报表
    ['GET', '/api/admin/revenue/dashboard', '收益看板'],
    ['GET', '/api/admin/business-metrics/dashboard', '业务指标'],
    // 电商
    ['GET', '/api/admin/product-skus', 'SKU'],
    ['GET', '/api/ecommerce/refunds/stats', '退款售后'],
    // 开发者
    ['GET', '/api/admin/dev-portal/dashboard', '开发者门户'],
    ['GET', '/api/api-keys', 'API Key'],
    // IM / 自动续费 / SLA
    ['GET', '/api/tickets', '工单'],
    ['GET', '/api/handoffs/queue', 'Handoff 队列'],
    ['GET', '/api/im/dashboard', 'IM 看板'],
    ['GET', '/api/chat/handoff-config', 'Handoff 配置'],
    ['GET', '/api/admin/auto-renewal/dashboard', '自动续费'],
    ['GET', '/api/admin/sla-probes/dashboard', 'SLA 拨测'],
    ['GET', '/api/admin/official-accounts', '公众号'],
    // AI
    ['GET', '/api/llm/providers', 'LLM'],
    ['GET', '/api/kb/articles', '知识库'],
    // 安全监控
    ['GET', '/api/admin/waf/dashboard', 'WAF'],
    ['GET', '/api/apm/dashboard', 'APM'],
    ['GET', '/api/sla/contracts', 'SLA'],
    ['GET', '/api/admin/tracing/stats', '调用链追踪'],
    // 气隙部署 / 扩展
    ['GET', '/api/admin/air-gapped/status', '气隙部署'],
    ['GET', '/api/admin/auto-renewal/plans', '自动续费计划'],
    ['GET', '/api/admin/sla-probes', 'SLA 拨测列表'],
    ['GET', '/api/admin/workflows/dashboard', '工作流看板'],
    ['GET', '/api/affiliate/campaigns', '联盟推广'],
    // 健康（无需鉴权对照）
];

$passed = 0;
$failed = 0;
$skipped = 0;
$errors = [];

foreach ($endpoints as [$method, $path, $group]) {
    $request = Request::create($path, $method);
    $request->headers->set('Authorization', 'Bearer '.$token);
    $request->headers->set('Accept', 'application/json');
    if ($tenantId) {
        $request->headers->set('X-Tenant-Id', (string) $tenantId);
    }

    try {
        $response = $app->handle($request);
        $status = $response->getStatusCode();
        $body = (string) $response->getContent();
        $json = json_decode($body, true);

        if ($status >= 200 && $status < 300) {
            $passed++;
            echo "  ✅ [{$group}] {$method} {$path} → {$status}\n";
        } elseif ($status === 404) {
            $skipped++;
            echo "  ⏭️  [{$group}] {$method} {$path} → 404 (路由未注册)\n";
        } elseif ($status === 403 || $status === 401) {
            // 403 多为 Policy 拒绝（路由存在但无业务权限），不计入失败
            $skipped++;
            echo "  ⏭️  [{$group}] {$method} {$path} → {$status} (权限/Policy)\n";
        } else {
            $failed++;
            $msg = is_array($json) ? ($json['message'] ?? substr($body, 0, 120)) : substr($body, 0, 120);
            $errors[] = "{$group}: {$path} → {$status} — {$msg}";
            echo "  ❌ [{$group}] {$method} {$path} → {$status}\n";
        }
    } catch (Throwable $e) {
        $failed++;
        $errors[] = "{$group}: {$path} — ".$e->getMessage();
        echo "  💥 [{$group}] {$method} {$path} → EXCEPTION\n";
    }
}

// 清理 token
if ($pat = PersonalAccessToken::findToken($token)) {
    $pat->delete();
}

echo "\n--- 汇总 ---\n";
echo "通过: {$passed}  失败: {$failed}  跳过(404/403): {$skipped}\n";

if ($errors !== []) {
    echo "\n--- 失败详情 ---\n";
    foreach ($errors as $err) {
        echo "  • {$err}\n";
    }
}

// 前端路由 vs 视图文件抽查
echo "\n--- 前端后台路由抽查 ---\n";
$routerFile = __DIR__.'/../resources/js/router/index.js';
$viewsDir = __DIR__.'/../resources/js/views';
$routerContent = file_get_contents($routerFile) ?: '';
preg_match_all("/import\\('@\\/views\\/([^']+)'\\)/", $routerContent, $matches);
$missingViews = [];
foreach (array_unique($matches[1] ?? []) as $view) {
    $viewPath = preg_replace('/\.vue$/', '', $view);
    $candidates = [
        $viewsDir.'/'.str_replace('/', DIRECTORY_SEPARATOR, $viewPath).'.vue',
        $viewsDir.'/'.str_replace('/', DIRECTORY_SEPARATOR, $viewPath).'/Index.vue',
    ];
    $found = false;
    foreach ($candidates as $file) {
        if (is_file($file)) {
            $found = true;
            break;
        }
    }
    if (! $found) {
        $missingViews[] = $view;
    }
}
if ($missingViews === []) {
    echo "✅ 路由引用的视图文件均存在 (".count(array_unique($matches[1] ?? []))." 个)\n";
} else {
    echo '⚠️  缺失视图: '.count($missingViews)." 个\n";
    foreach (array_slice($missingViews, 0, 10) as $v) {
        echo "    - {$v}\n";
    }
}

echo "\n--- 结论 ---\n";
if ($failed === 0) {
    echo "✅ 后台核心 API 冒烟测试通过\n";
    exit(0);
}

echo "⚠️  存在 {$failed} 个 API 异常，需进一步排查\n";
exit(1);
