<?php

/**
 * D-02: 验证 HTTPS / Sanctum / Reverb 环境变量一致性
 *
 * 用法: php scripts/verify-https-config.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';

$app = require_once $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$errors = [];
$warnings = [];
$ok = [];

$appUrl = rtrim((string) config('app.url'), '/');
$forceHttps = (bool) config('app.force_https');
$reverbScheme = env('REVERB_SCHEME', 'https');
$reverbHost = env('REVERB_HOST', '');
$reverbPort = env('REVERB_PORT', '443');
$viteScheme = env('VITE_REVERB_SCHEME', '');
$stateful = config('sanctum.stateful', []);

if (! str_starts_with($appUrl, 'https://')) {
    $errors[] = 'APP_URL 应以 https:// 开头（当前: '.$appUrl.')';
} else {
    $ok[] = 'APP_URL 使用 HTTPS: '.$appUrl;
}

if ($forceHttps) {
    $ok[] = 'FORCE_HTTPS=true，URL 生成将强制 https';
} else {
    $warnings[] = 'FORCE_HTTPS 未启用，生产环境建议设为 true';
}

if ($reverbScheme !== 'https') {
    $errors[] = 'REVERB_SCHEME 应为 https（当前: '.$reverbScheme.')';
} else {
    $ok[] = 'REVERB_SCHEME=https';
}

if ($viteScheme !== 'https') {
    $errors[] = 'VITE_REVERB_SCHEME 应为 https（需 npm run build 后生效，当前 env: '.$viteScheme.')';
} else {
    $ok[] = 'VITE_REVERB_SCHEME=https';
}

if ((int) $reverbPort !== 443) {
    $warnings[] = 'REVERB_PORT 生产通常为 443（Nginx 终止 TLS），当前: '.$reverbPort;
} else {
    $ok[] = 'REVERB_PORT=443';
}

$hostFromUrl = parse_url($appUrl, PHP_URL_HOST) ?: '';
if ($reverbHost && $hostFromUrl && $reverbHost !== $hostFromUrl) {
    $warnings[] = "REVERB_HOST ($reverbHost) 与 APP_URL 主机 ($hostFromUrl) 不一致（多域部署时可忽略）";
} elseif ($reverbHost) {
    $ok[] = 'REVERB_HOST='.$reverbHost;
}

if ($hostFromUrl && ! in_array($hostFromUrl, $stateful, true)) {
    $errors[] = "SANCTUM_STATEFUL_DOMAINS 未包含 APP_URL 主机: $hostFromUrl";
} else {
    $ok[] = 'SANCTUM_STATEFUL_DOMAINS 含 APP_URL 主机';
}

if (filter_var(env('SESSION_SECURE_COOKIE'), FILTER_VALIDATE_BOOLEAN)) {
    $ok[] = 'SESSION_SECURE_COOKIE=true';
} else {
    $warnings[] = 'SESSION_SECURE_COOKIE 未启用，HTTPS 环境建议 true';
}

echo "=== D-02 HTTPS 配置验证 ===\n\n";

foreach ($ok as $line) {
    echo "  ✅ $line\n";
}
foreach ($warnings as $line) {
    echo "  ⚠️  $line\n";
}
foreach ($errors as $line) {
    echo "  ❌ $line\n";
}

echo "\nSanctum stateful domains: ".implode(', ', $stateful)."\n";

if ($errors !== []) {
    echo "\n结果: 未通过 (".count($errors)." 项错误)\n";
    exit(1);
}

echo "\n结果: 通过".($warnings !== [] ? '（'.count($warnings).' 项警告）' : '')."\n";
exit(0);
