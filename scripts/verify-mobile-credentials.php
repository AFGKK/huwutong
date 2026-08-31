<?php
/**
 * 真机 / 账号类凭据就绪检查
 *
 * 检查 FCM、微信小程序 AppID、Flutter 配置文件、商店 Fastlane 是否已从占位符替换。
 *
 * 用法: php scripts/verify-mobile-credentials.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$passed = 0;
$failed = 0;
$warned = 0;

function check(bool $ok, string $label, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) {
        $passed++;
        echo "  [✓] {$label}" . ($detail ? " — {$detail}" : '') . "\n";
    } else {
        $failed++;
        echo "  [✗] {$label}" . ($detail ? " — {$detail}" : '') . "\n";
    }
}

function warn(string $label, string $detail = ''): void
{
    global $warned;
    $warned++;
    echo "  [!] {$label}" . ($detail ? " — {$detail}" : '') . "\n";
}

function loadEnv(string $path): array
{
    if (!file_exists($path)) {
        return [];
    }
    $vars = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (str_contains($line, '=')) {
            [$k, $v] = explode('=', $line, 2);
            $vars[trim($k)] = trim($v, " \t\"'");
        }
    }
    return $vars;
}

function isPlaceholder(string $value): bool
{
    if ($value === '') {
        return true;
    }
    $patterns = [
        '/^wx0+$/',
        '/^wx0000000000000000$/',
        '/YOUR_/i',
        '/your-/i',
        '/placeholder/i',
        '/example\.com/i',
    ];
    foreach ($patterns as $p) {
        if (preg_match($p, $value)) {
            return true;
        }
    }
    return false;
}

echo "\n";
echo "══════════════════════════════════════════════════\n";
echo " 互物通 — 真机/账号凭据就绪检查\n";
echo " 详见 docs/真机账号上架指南.md\n";
echo "══════════════════════════════════════════════════\n\n";

$env = loadEnv($root . '/.env');

// ─── 1. FCM 后端配置 ───
echo "[1/5] FCM 推送 (D-28)\n";

$fcmProjectId = $env['FCM_PROJECT_ID'] ?? '';
$fcmCredsPath = $env['FCM_CREDENTIALS_PATH'] ?? $root . '/storage/app/fcm-credentials.json';
if (!str_starts_with($fcmCredsPath, '/') && !preg_match('/^[A-Za-z]:/', $fcmCredsPath)) {
    $fcmCredsPath = $root . '/' . ltrim($fcmCredsPath, '/');
}

check(!isPlaceholder($fcmProjectId), 'FCM_PROJECT_ID 已配置', $fcmProjectId ?: '(空)');

$credsExist = file_exists($fcmCredsPath);
check($credsExist, 'FCM 凭证 JSON 存在', $fcmCredsPath);

if ($credsExist) {
    $json = json_decode((string) file_get_contents($fcmCredsPath), true);
    check(is_array($json) && !empty($json['project_id']), 'FCM JSON 含 project_id');
    check(!empty($json['private_key']), 'FCM JSON 含 private_key');
}

$googleServices = $root . '/mobile/android/app/google-services.json';
if (file_exists($googleServices)) {
    $gs = json_decode((string) file_get_contents($googleServices), true);
    $pid = $gs['project_info']['project_id'] ?? '';
    check(!isPlaceholder((string) $pid), 'google-services.json 已替换', (string) $pid);
} else {
    check(false, 'google-services.json 存在');
}

$iosPlist = $root . '/mobile/ios/Runner/GoogleService-Info.plist';
$iosExample = $root . '/mobile/ios/Runner/GoogleService-Info.plist.example';
if (file_exists($iosPlist)) {
    $plist = file_get_contents($iosPlist);
    check(!str_contains($plist, 'YOUR_'), 'GoogleService-Info.plist 已替换');
} elseif (file_exists($iosExample)) {
    warn('GoogleService-Info.plist 缺失', '从 Firebase 下载并复制为 ios/Runner/GoogleService-Info.plist');
} else {
    check(false, 'GoogleService-Info.plist.example 模板');
}

// ─── 2. 微信小程序 ───
echo "\n[2/5] 微信小程序 (D-31)\n";

$miniAppId = $env['WECHAT_MINI_PROGRAM_APPID'] ?? '';
$miniSecret = $env['WECHAT_MINI_PROGRAM_SECRET'] ?? '';

// 尝试从数据库 site_settings 读取（若 Laravel 可用）
try {
    require $root . '/vendor/autoload.php';
    $app = require_once $root . '/bootstrap/app.php';
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $fromDb = \App\Models\SiteSetting::getWechatMiniProgramConfig();
    if (!empty($fromDb['appid'])) {
        $miniAppId = $miniAppId ?: $fromDb['appid'];
    }
    if (!empty($fromDb['secret'])) {
        $miniSecret = $miniSecret ?: $fromDb['secret'];
    }
    check(true, 'SiteSetting 微信小程序配置可读');
} catch (Throwable $e) {
    warn('无法读取 SiteSetting', '仅检查 .env；部署后请在管理后台填写');
}

check(!isPlaceholder($miniAppId), '微信小程序 AppID', $miniAppId ?: '(空)');
check($miniSecret !== '', '微信小程序 AppSecret', $miniSecret ? '(已设置)' : '(空)');

$projectConfig = $root . '/miniprogram/project.config.json';
if (file_exists($projectConfig)) {
    $pc = json_decode((string) file_get_contents($projectConfig), true);
    $pcAppId = $pc['appid'] ?? '';
    check(!isPlaceholder((string) $pcAppId), 'project.config.json appid', (string) $pcAppId);

    if ($miniAppId && !isPlaceholder($miniAppId) && $pcAppId !== $miniAppId) {
        warn('project.config.json 与 .env AppID 不一致', "运行 php scripts/sync-miniprogram-config.php");
    }
}

$configJs = $root . '/miniprogram/utils/config.js';
if (file_exists($configJs)) {
    $js = file_get_contents($configJs);
    preg_match("/WX_APP_ID:\s*'([^']+)'/", $js, $m);
    $jsAppId = $m[1] ?? '';
    check(!isPlaceholder($jsAppId), 'miniprogram config.js WX_APP_ID', $jsAppId);
}

// ─── 3. Flutter 项目结构 ───
echo "\n[3/5] Flutter 项目 (D-28/D-29)\n";

$flutterFiles = [
    'mobile/pubspec.yaml' => 'pubspec.yaml',
    'mobile/lib/services/push_service.dart' => 'PushService',
    'mobile/lib/providers/auth_provider.dart' => 'AuthProvider',
    'mobile/android/app/build.gradle.kts' => 'Android Gradle',
    'mobile/ios/Runner/Info.plist' => 'iOS Info.plist',
];
foreach ($flutterFiles as $rel => $label) {
    check(file_exists($root . '/' . $rel), $label);
}

$keyExample = $root . '/mobile/android/key.properties.example';
check(file_exists($keyExample), 'Android 签名 key.properties.example');

$keyProps = $root . '/mobile/android/key.properties';
if (file_exists($keyProps)) {
    check(true, 'Android key.properties 已创建（Release 签名）');
} else {
    warn('Android key.properties 未创建', 'Release 打包前从 example 复制并填写');
}

// ─── 4. 商店 Fastlane ───
echo "\n[4/5] 应用商店 Fastlane (D-29)\n";

$fastlaneFiles = [
    'mobile/android/fastlane/Fastfile' => 'Android Fastfile',
    'mobile/ios/fastlane/Fastfile' => 'iOS Fastfile',
    'mobile/store/store_listing.md' => '商店物料文档',
];
foreach ($fastlaneFiles as $rel => $label) {
    check(file_exists($root . '/' . $rel), $label);
}

$screenshots = glob($root . '/mobile/store/screenshots/*.png') ?: [];
check(count($screenshots) >= 6, '商店截图资源', count($screenshots) . ' 张');

// ─── 5. 文档 ───
echo "\n[5/5] 文档\n";
check(file_exists($root . '/docs/真机账号上架指南.md'), '真机账号上架指南');

echo "\n══════════════════════════════════════════════════\n";
echo " 结果: {$passed} 通过, {$failed} 失败, {$warned} 警告\n";
if ($failed > 0) {
    echo " 请先补齐凭据后再执行 T-20 / T-21 真机测试。\n";
    echo " 参考: docs/真机账号上架指南.md\n";
    exit(1);
}
if ($warned > 0) {
    echo " 存在警告项，建议处理后再上架。\n";
    exit(0);
}
echo " 凭据检查通过，可开始真机冒烟与小程序测试。\n";
exit(0);
