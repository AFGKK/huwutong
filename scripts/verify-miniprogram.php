<?php
/**
 * T-21: 微信小程序审核前就绪检查
 *
 * 用法: php scripts/verify-miniprogram.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$passed = 0;
$failed = 0;
$warned = 0;

function ok(bool $cond, string $label, string $detail = ''): void
{
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "  [✓] {$label}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    } else {
        $failed++;
        echo "  [✗] {$label}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    }
}

function warn(string $label, string $detail = ''): void
{
    global $warned;
    $warned++;
    echo "  [!] {$label}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
}

function isPlaceholderAppId(string $id): bool
{
    return $id === '' || preg_match('/^wx0+$/', $id) === 1 || str_contains($id, '0000000000000000');
}

echo "\n══════════════════════════════════════════════════\n";
echo " 互物通 — 微信小程序审核前检查 (T-21)\n";
echo "══════════════════════════════════════════════════\n\n";

$mp = $root . '/miniprogram';

echo "[1/4] 项目结构\n";
$required = [
    'app.js',
    'app.json',
    'app.wxss',
    'project.config.json',
    'utils/api.js',
    'utils/auth.js',
    'utils/config.js',
    'pages/index/index.js',
    'pages/result/result.js',
    'pages/activate/activate.js',
    'pages/mine/mine.js',
    'pages/activations/activations.js',
    'pages/agreement/agreement.js',
    'pages/webview/webview.js',
    'utils/webview.js',
];
foreach ($required as $rel) {
    ok(file_exists($mp . '/' . $rel), $rel);
}

$appJs = (string) file_get_contents($mp . '/app.js');
ok(str_contains($appJs, 'App({'), 'app.js 已注册 App()');

$appJson = json_decode((string) file_get_contents($mp . '/app.json'), true) ?: [];
ok(isset($appJson['tabBar']['list']) && count($appJson['tabBar']['list']) >= 2, 'tabBar 已配置');
ok(file_exists($mp . '/assets/tab/search.png'), 'tabBar 查询图标');
ok(file_exists($mp . '/assets/tab/mine.png'), 'tabBar 我的图标');

echo "\n[2/4] AppID / API 配置\n";
$pc = json_decode((string) file_get_contents($mp . '/project.config.json'), true) ?: [];
$appId = (string) ($pc['appid'] ?? '');
ok(!isPlaceholderAppId($appId), 'project.config.json appid', $appId ?: '(空)');

$configJs = (string) file_get_contents($mp . '/utils/config.js');
preg_match("/WX_APP_ID:\s*'([^']+)'/", $configJs, $mApp);
preg_match("/API_BASE_URL:\s*'([^']+)'/", $configJs, $mApi);
$jsAppId = $mApp[1] ?? '';
$apiBase = $mApi[1] ?? '';
ok(!isPlaceholderAppId($jsAppId), 'config.js WX_APP_ID', $jsAppId ?: '(空)');
ok($apiBase !== '' && str_starts_with($apiBase, 'https://'), 'config.js API_BASE_URL 为 HTTPS', $apiBase ?: '(空)');

if (!isPlaceholderAppId($appId) && !isPlaceholderAppId($jsAppId) && $appId !== $jsAppId) {
    warn('AppID 不一致', '运行 php scripts/sync-miniprogram-config.php');
}

echo "\n[3/4] 激活页参数 / 路由\n";
$activate = (string) file_get_contents($mp . '/pages/activate/activate.js');
ok(str_contains($activate, 'fingerprint'), '激活参数使用 fingerprint');
ok(str_contains($activate, '/license/miniprogram/activate') || str_contains($activate, 'miniprogram/activate'), '激活走小程序专用接口');

$appJson = json_decode((string) file_get_contents($mp . '/app.json'), true) ?: [];
$pages = $appJson['pages'] ?? [];
ok(in_array('pages/index/index', $pages, true), '注册查询页');
ok(in_array('pages/result/result', $pages, true), '注册结果页');
ok(in_array('pages/activate/activate', $pages, true), '注册激活页');
ok(in_array('pages/mine/mine', $pages, true), '注册我的页');
ok(in_array('pages/activations/activations', $pages, true), '注册我的激活页');
ok(in_array('pages/agreement/agreement', $pages, true), '注册协议页');
ok(in_array('pages/webview/webview', $pages, true), '注册 web-view 页');

$mineJs = (string) file_get_contents($mp . '/pages/mine/mine.js');
ok(str_contains($mineJs, 'bind-phone') || str_contains($mineJs, 'getPhoneNumber'), '我的页支持绑定手机号');
ok(str_contains($mineJs, 'activations'), '我的页入口到激活列表');
ok(str_contains($mineJs, 'openPricing') || str_contains($mineJs, 'PRICING'), '我的页入口到定价');
ok(str_contains($mineJs, 'openProducts') || str_contains($mineJs, 'PRODUCTS'), '我的页入口到商城');

$wvUtil = (string) file_get_contents($mp . '/utils/webview.js');
ok(str_contains($wvUtil, 'h5-sso'), 'web-view 工具支持 H5 SSO');

$wv = (string) file_get_contents($mp . '/pages/webview/webview.wxml');
ok(str_contains($wv, 'web-view'), 'web-view 组件已使用');

$hwtMpJs = $root . '/public/js/hwt-miniprogram.js';
ok(file_exists($hwtMpJs), 'H5 小程序环境脚本 public/js/hwt-miniprogram.js');
ok(is_file($root . '/resources/views/public/partials/miniprogram-env.blade.php'), 'H5 miniprogram-env partial');
ok(is_file($root . '/resources/js/utils/miniprogramEnv.js'), 'SPA miniprogramEnv 工具');
ok(is_file($mp . '/ci/upload.js'), 'miniprogram-ci upload 脚本');
ok(is_file($mp . '/ci/preview.js'), 'miniprogram-ci preview 脚本');
ok(is_file($mp . '/package.json'), 'miniprogram package.json');
ok(is_file($root . '/.github/workflows/miniprogram-ci.yml'), 'GitHub Miniprogram CI workflow');
ok(str_contains((string) file_get_contents($mp . '/pages/index/index.wxml'), '互物通'), '查询页品牌文案');
ok(!str_contains((string) file_get_contents($mp . '/pages/index/index.wxml'), '💡'), '查询页已去 emoji');
ok(str_contains((string) file_get_contents($mp . '/pages/result/result.wxml'), 'metrics'), '结果页关键指标');
ok(!str_contains((string) file_get_contents($mp . '/pages/mine/mine.wxml'), '当前 API'), '我的页已隐藏 API 列表项');
ok(str_contains((string) file_get_contents($mp . '/pages/activate/activate.wxml'), 'success-page')
    || str_contains((string) file_get_contents($mp . '/pages/activate/activate.wxml'), '激活成功'), '激活成功态页面');
ok(str_contains((string) file_get_contents($mp . '/pages/activations/activations.wxml'), 'skeleton'), '激活列表骨架屏');

echo "\n[4/4] 后端联调提示\n";
try {
    require $root . '/vendor/autoload.php';
    $app = require_once $root . '/bootstrap/app.php';
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $wx = \App\Models\SiteSetting::getWechatMiniProgramConfig();
    ok(!isPlaceholderAppId((string) ($wx['appid'] ?? '')), 'SiteSetting AppID', $wx['appid'] ?: '(空)');
    ok(!empty($wx['secret']), 'SiteSetting AppSecret', !empty($wx['secret']) ? '(已设置)' : '(空)');
} catch (Throwable $e) {
    warn('无法读取 SiteSetting', $e->getMessage());
}

warn('需人工', '微信公众平台配置 request 合法域名（HTTPS）');
warn('需人工', '微信公众平台配置业务域名（web-view 定价/帮助，如 88.huwutong.com）');
warn('需人工', '开发者工具导入 miniprogram/ 并用真机预览走完 T-21 清单');

echo "\n══════════════════════════════════════════════════\n";
echo " 结果: {$passed} 通过, {$failed} 失败, {$warned} 警告\n";
echo " 指南: docs/真机账号上架指南.md §2\n";
echo "══════════════════════════════════════════════════\n";

exit($failed > 0 ? 1 : 0);
