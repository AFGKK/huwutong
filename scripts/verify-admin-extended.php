<?php
/** 扩展后台 API 冒烟（正确路径） */
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\User;

$admin = User::where('email', 'admin@huwutong.com')->first() ?? User::first();
$token = $admin->createToken('smoke', ['admin', 'super-admin'])->plainTextToken;
$tenant = $admin->tenant_id;

$tests = [
    ['GET', '/api/admin/tenants', '租户'],
    ['GET', '/api/admin/users', '用户管理'],
    ['GET', '/api/billing/payment-methods', '支付方式'],
    ['GET', '/api/billing/coupons/validate?code=test', '优惠券'],
    ['GET', '/api/admin/revenue/dashboard', '收益看板'],
    ['GET', '/api/admin/waf/dashboard', 'WAF'],
    ['GET', '/api/admin/business-metrics/dashboard', '业务指标'],
    ['GET', '/api/admin/air-gapped/status', '气隙部署'],
    ['GET', '/api/apm/dashboard', 'APM'],
    ['GET', '/api/reports/revenue-trend', '收入趋势'],
];

$ok = 0; $missing = 0; $err = 0;
foreach ($tests as [$m, $path, $label]) {
    $req = Illuminate\Http\Request::create($path, $m);
    $req->headers->set('Authorization', 'Bearer '.$token);
    $req->headers->set('Accept', 'application/json');
    if ($tenant) $req->headers->set('X-Tenant-Id', (string) $tenant);
    $res = $app->handle($req);
    $s = $res->getStatusCode();
    if ($s === 404) { $missing++; echo "❌ MISSING [$label] $path\n"; }
    elseif ($s >= 200 && $s < 300) { $ok++; echo "✅ [$label] $path → $s\n"; }
    else { $err++; echo "⚠️  [$label] $path → $s\n"; }
}
Laravel\Sanctum\PersonalAccessToken::findToken($token)?->delete();
echo "\nOK=$ok MISSING=$missing ERR=$err\n";
