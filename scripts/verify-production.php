#!/usr/bin/env php
<?php
/**
 * 互物通 — 生产环境验证脚本
 *
 * 在部署后执行，确认所有生产配置正确。
 * 用法: php scripts/verify-production.php
 */

// 从 .env 读取
$env = parse_ini_file(__DIR__ . '/../.env');

$passed = 0;
$failed = 0;

function check(string $label, bool $condition, string $detail = ''): void
{
    global $passed, $failed;
    $mark = $condition ? '✓' : '✗';
    $color = $condition ? "\033[32m" : "\033[31m";
    $reset = "\033[0m";
    echo "{$color}[{$mark}] {$label}{$reset}" . ($detail ? " — {$detail}" : '') . PHP_EOL;
    if ($condition) $passed++; else $failed++;
}

echo "\n=== 互物通 P0 生产环境验证 ===\n\n";

// ── 1. 环境变量完整性 ──
echo "--- 1. 环境变量完整性 ---\n";

check('APP_KEY 已设置', !empty($env['APP_KEY']) && $env['APP_KEY'] !== 'SomeRandomKey');
check('APP_ENV=production', ($env['APP_ENV'] ?? '') === 'production');
check('APP_DEBUG=false', ($env['APP_DEBUG'] ?? '') === 'false' || ($env['APP_DEBUG'] ?? '') === false);
check('APP_URL 为 HTTPS', str_starts_with($env['APP_URL'] ?? '', 'https://'));
check('FORCE_HTTPS=true', ($env['FORCE_HTTPS'] ?? '') === 'true' || ($env['FORCE_HTTPS'] ?? '') === true);
check('SESSION_SECURE_COOKIE=true', ($env['SESSION_SECURE_COOKIE'] ?? '') === 'true');

// ── 2. Reverb WebSocket ──
echo "\n--- 2. Reverb WebSocket ---\n";

check('BROADCAST_CONNECTION=reverb', ($env['BROADCAST_CONNECTION'] ?? '') === 'reverb');
check('REVERB_APP_KEY 已设置', !empty($env['REVERB_APP_KEY']));
check('REVERB_APP_SECRET 已设置', !empty($env['REVERB_APP_SECRET']));
check('REVERB_SCHEME=https', ($env['REVERB_SCHEME'] ?? '') === 'https');

// 检测 Reverb 进程
$reverbRunning = false;
exec('ps aux | grep reverb:start | grep -v grep', $output, $exitCode);
$reverbRunning = count($output) > 0;
check('Reverb 进程运行中', $reverbRunning);

// ── 3. Supervisor ──
echo "\n--- 3. Supervisor ---\n";

exec('supervisorctl status 2>&1', $supOut, $supExit);
$hasReverb = false;
$hasWorker = false;
foreach ($supOut as $line) {
    if (str_contains($line, 'huwutong-reverb') && str_contains($line, 'RUNNING')) $hasReverb = true;
    if (str_contains($line, 'huwutong-worker') && str_contains($line, 'RUNNING')) $hasWorker = true;
}
check('Supervisor Reverb 守护中', $hasReverb);
check('Supervisor Worker 守护中', $hasWorker);

// ── 4. 数据库 ──
echo "\n--- 4. 数据库 ---\n";

$laravel = require __DIR__ . '/../bootstrap/app.php';
$kernel = $laravel->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    \Illuminate\Support\Facades\DB::connection()->getPdo();
    check('数据库连接正常', true);
} catch (\Throwable $e) {
    check('数据库连接正常', false, $e->getMessage());
}

// 检查待迁移
exec('php ' . __DIR__ . '/../artisan migrate:status --no-ansi 2>&1', $migOut, $migExit);
$pending = 0;
foreach ($migOut as $line) {
    if (str_contains($line, 'Pending')) $pending++;
}
check('无待迁移', $pending === 0, "{$pending} 个待迁移");

// ── 5. 缓存 ──
echo "\n--- 5. 缓存优化 ---\n";

check('config 缓存', file_exists(__DIR__ . '/../bootstrap/cache/config.php'));
check('route 缓存', file_exists(__DIR__ . '/../bootstrap/cache/routes-v7.php'));
check('events 缓存', file_exists(__DIR__ . '/../bootstrap/cache/events.php'));

// ── 6. 支付 ──
echo "\n--- 6. 支付配置 ---\n";

$paymentDriver = $env['PAYMENT_DRIVER'] ?? 'mock';
check('PAYMENT_DRIVER 不是 mock', $paymentDriver !== 'mock', "当前: {$paymentDriver}");

if ($paymentDriver === 'alipay') {
    check('ALIPAY_APP_ID 已设置', !empty($env['ALIPAY_APP_ID']));
    check('ALIPAY_PRIVATE_KEY 已设置', !empty($env['ALIPAY_PRIVATE_KEY']));
    check('ALIPAY_PUBLIC_KEY 已设置', !empty($env['ALIPAY_PUBLIC_KEY']));
    check('ALIPAY_NOTIFY_URL 正确', str_contains($env['ALIPAY_NOTIFY_URL'] ?? '', '//88.huwutong.com/'));
} elseif ($paymentDriver === 'stripe') {
    check('STRIPE_SECRET 已设置', !empty($env['STRIPE_SECRET']));
    check('STRIPE_WEBHOOK_SECRET 已设置', !empty($env['STRIPE_WEBHOOK_SECRET']));
}

// ── 7. 邮件/短信 ──
echo "\n--- 7. 邮件/短信 ---\n";

check('MAIL_MAILER 不是 log', ($env['MAIL_MAILER'] ?? '') !== 'log', "当前: " . ($env['MAIL_MAILER'] ?? '未设置'));
if (($env['MAIL_MAILER'] ?? '') === 'smtp') {
    check('SMTP 主机已设置', !empty($env['MAIL_HOST']));
    check('SMTP 凭据已设置', !empty($env['MAIL_USERNAME']) && !empty($env['MAIL_PASSWORD']));
}
check('SMS_DRIVER 已设置', !empty($env['SMS_DRIVER']));

// ── 8. HTTP 端点检查 ──
echo "\n--- 8. HTTP 端点检查 ---\n";

$appUrl = rtrim($env['APP_URL'] ?? 'http://localhost', '/');
check('就绪探针 /api/health/ready', httpPing("{$appUrl}/api/health/ready"));
check('存活探针 /api/health/live', httpPing("{$appUrl}/api/health/live"));
check('首页可达', httpPing($appUrl));

// ── 汇总 ──
echo "\n==============================\n";
$total = $passed + $failed;
$pct = $total > 0 ? round($passed / $total * 100) : 0;
echo "结果: {$passed}/{$total} 通过 ({$pct}%)\n";

if ($failed === 0) {
    echo "\033[32m✓ 生产环境就绪!\033[0m\n";
} else {
    echo "\033[33m⚠  {$failed} 项未通过，请检查上述 [✗] 项\033[0m\n";
}
echo "\n";

function httpPing(string $url): bool
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_NOBODY => true,
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code >= 200 && $code < 500;
}
