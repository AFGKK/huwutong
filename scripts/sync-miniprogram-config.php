<?php
/**
 * 从 .env / SiteSetting 同步微信小程序配置到 miniprogram 目录
 *
 * 用法:
 *   php scripts/sync-miniprogram-config.php
 *   php scripts/sync-miniprogram-config.php --dry-run
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$dryRun = in_array('--dry-run', $argv ?? [], true);

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

echo "同步微信小程序配置" . ($dryRun ? ' (dry-run)' : '') . "\n\n";

$env = loadEnv($root . '/.env');
$appId = $env['WECHAT_MINI_PROGRAM_APPID'] ?? '';
$apiBase = rtrim($env['APP_URL'] ?? 'https://88.huwutong.com', '/') . '/api';

// 优先 SiteSetting
try {
    require $root . '/vendor/autoload.php';
    $app = require_once $root . '/bootstrap/app.php';
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $wx = \App\Models\SiteSetting::getWechatMiniProgramConfig();
    if (!empty($wx['appid'])) {
        $appId = $wx['appid'];
        echo "  AppID 来源: SiteSetting\n";
    }
    $siteUrl = \App\Models\SiteSetting::where('key', 'site_url')->value('value');
    if ($siteUrl) {
        $apiBase = rtrim($siteUrl, '/') . '/api';
    }
} catch (Throwable $e) {
    echo "  AppID 来源: .env (SiteSetting 不可用: {$e->getMessage()})\n";
}

if ($appId === '' || preg_match('/^wx0+$/', $appId)) {
    echo "  [✗] 未找到有效 AppID。请在 .env 或管理后台系统设置中配置 WECHAT_MINI_PROGRAM_APPID。\n";
    exit(1);
}

echo "  AppID: {$appId}\n";
echo "  API_BASE_URL: {$apiBase}\n\n";

// 1. project.config.json
$projectConfigPath = $root . '/miniprogram/project.config.json';
if (!file_exists($projectConfigPath)) {
    echo "  [✗] 缺少 miniprogram/project.config.json\n";
    exit(1);
}
$projectConfig = json_decode((string) file_get_contents($projectConfigPath), true);
$projectConfig['appid'] = $appId;
$projectJson = json_encode($projectConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

if ($dryRun) {
    echo "  [dry-run] 将更新 project.config.json appid → {$appId}\n";
} else {
    file_put_contents($projectConfigPath, $projectJson);
    echo "  [✓] 已更新 project.config.json\n";
}

// 2. utils/config.js
$configJsPath = $root . '/miniprogram/utils/config.js';
if (!file_exists($configJsPath)) {
    echo "  [✗] 缺少 miniprogram/utils/config.js\n";
    exit(1);
}
$configJs = file_get_contents($configJsPath);
$configJs = preg_replace(
    "/(API_BASE_URL:\s*)'[^']*'/",
    "\${1}'{$apiBase}'",
    $configJs,
    1
);
$configJs = preg_replace(
    "/(WX_APP_ID:\s*)'[^']*'/",
    "\${1}'{$appId}'",
    $configJs,
    1
);

if ($dryRun) {
    echo "  [dry-run] 将更新 config.js WX_APP_ID / API_BASE_URL\n";
} else {
    file_put_contents($configJsPath, $configJs);
    echo "  [✓] 已更新 utils/config.js\n";
}

echo "\n完成。请在微信开发者工具中重新打开项目并配置服务器合法域名。\n";
