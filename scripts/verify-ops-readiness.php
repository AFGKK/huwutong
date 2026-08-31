<?php
/**
 * 上线前运维就绪检查：品牌素材、备案、OAuth、小程序、支付/短信/邮件、PWA。
 *
 * 用法:
 *   php scripts/verify-ops-readiness.php
 *   php scripts/verify-ops-readiness.php --strict   # 生产门禁：警告也计为失败
 */

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$strict = in_array('--strict', $argv ?? [], true);
$passed = 0;
$failed = 0;
$warned = 0;

function setting(string $key, ?string $default = null): ?string
{
    $v = App\Models\SiteSetting::where('key', $key)->value('value');

    return ($v !== null && $v !== '') ? (string) $v : $default;
}

function mask(?string $v): string
{
    if ($v === null || $v === '') {
        return '(empty)';
    }
    $len = strlen($v);
    if ($len <= 6) {
        return str_repeat('*', $len);
    }

    return substr($v, 0, 3) . str_repeat('*', max(3, $len - 6)) . substr($v, -3);
}

function isDemoBeian(?string $v): bool
{
    if ($v === null || $v === '') {
        return false;
    }

    return str_contains($v, '演示') || str_contains($v, '000000');
}

function ok(bool $cond, string $label, string $detail = ''): void
{
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo '  [✓] '.$label.($detail !== '' ? " — {$detail}" : '')."\n";
    } else {
        $failed++;
        echo '  [✗] '.$label.($detail !== '' ? " — {$detail}" : '')."\n";
    }
}

function warn(string $label, string $detail = ''): void
{
    global $warned, $strict, $failed;
    $warned++;
    echo '  [!] '.$label.($detail !== '' ? " — {$detail}" : '')."\n";
    if ($strict) {
        $failed++;
    }
}

/** 可选能力：默认警告，--strict 时失败 */
function optionalOk(bool $cond, string $label, string $detail = ''): void
{
    global $strict;
    if ($cond) {
        ok(true, $label, $detail);
    } elseif ($strict) {
        ok(false, $label, $detail);
    } else {
        warn($label, $detail !== '' ? $detail : '未配置（非严格模式记为警告）');
    }
}

echo "\n══════════════════════════════════════════════════\n";
echo ' 互物通 — 上线运维就绪检查'.($strict ? ' [STRICT]' : '')."\n";
echo "══════════════════════════════════════════════════\n\n";

echo "[1/5] 品牌与备案\n";
$logo = setting('logo_url', '');
$logoPath = null;
if ($logo && str_starts_with($logo, '/')) {
    $path = parse_url($logo, PHP_URL_PATH) ?: $logo;
    $logoPath = public_path(ltrim($path, '/'));
}
ok($logo !== null && $logo !== '', 'logo_url 已配置', $logo ?: '(empty)');
ok($logoPath !== null && is_file($logoPath), 'Logo 文件存在', $logoPath ? basename($logoPath) : '');

$icp = setting('icp_beian');
$police = setting('police_beian') ?: setting('gongan_beian');
if (! $icp) {
    optionalOk(false, 'ICP 备案号', '(empty)');
} elseif (isDemoBeian($icp)) {
    warn('ICP 备案号仍为演示占位', $icp);
} else {
    ok(true, 'ICP 备案号', $icp);
}
if (! $police) {
    optionalOk(false, '公安备案号', '(empty)');
} elseif (isDemoBeian($police)) {
    warn('公安备案号仍为演示占位', $police);
} else {
    ok(true, '公安备案号', $police);
}

$primary = setting('primary_color') ?: setting('page_primary_color');
ok($primary === '#0f172a' || $primary === '#0F172A', '主题色为 slate', $primary ?: '(empty)');
ok((bool) setting('contact_email'), 'contact_email', setting('contact_email') ?: '(empty)');

echo "\n[2/5] OAuth 第三方登录\n";
$oauth = [
    'wechat' => [
        'enabled' => 'oauth_wechat_enabled',
        'env' => ['OAUTH_WECHAT_APPID', 'OAUTH_WECHAT_SECRET'],
        'db' => ['oauth_wechat_appid', 'oauth_wechat_secret'],
    ],
    'github' => [
        'enabled' => 'oauth_github_enabled',
        'env' => ['OAUTH_GITHUB_CLIENT_ID', 'OAUTH_GITHUB_CLIENT_SECRET'],
        'db' => ['oauth_github_client_id', 'oauth_github_client_secret'],
    ],
    'google' => [
        'enabled' => 'oauth_google_enabled',
        'env' => ['OAUTH_GOOGLE_CLIENT_ID', 'OAUTH_GOOGLE_CLIENT_SECRET'],
        'db' => ['oauth_google_client_id', 'oauth_google_client_secret'],
    ],
    'qq' => [
        'enabled' => 'oauth_qq_enabled',
        'env' => ['OAUTH_QQ_CLIENT_ID', 'OAUTH_QQ_CLIENT_SECRET'],
        'db' => ['oauth_qq_client_id', 'oauth_qq_client_secret'],
    ],
];

foreach ($oauth as $name => $cfg) {
    $en = setting($cfg['enabled'], '0');
    $enabled = $en === '1' || $en === 'true';
    $hasSecret = false;
    foreach ($cfg['env'] as $ek) {
        if ((string) env($ek, '') !== '') {
            $hasSecret = true;
        }
    }
    foreach ($cfg['db'] as $dk) {
        if (setting($dk)) {
            $hasSecret = true;
        }
    }
    if (! $enabled) {
        echo "  [·] OAuth {$name} — 未启用\n";
        continue;
    }
    ok($hasSecret, "OAuth {$name} 已启用且有凭据", $hasSecret ? 'secrets set' : 'missing secrets');
}

echo "\n[3/5] 微信小程序\n";
$mpAppId = (string) (env('WECHAT_MINI_PROGRAM_APPID') ?: setting('wechat_mini_appid', '') ?: '');
$mpSecret = (string) (env('WECHAT_MINI_PROGRAM_SECRET') ?: setting('wechat_mini_secret', '') ?: '');
$placeholder = $mpAppId === '' || preg_match('/^wx0+$/', $mpAppId) === 1 || str_contains($mpAppId, '0000000000000000');
optionalOk(! $placeholder, '小程序 AppId', $mpAppId !== '' ? mask($mpAppId) : '(empty)');
optionalOk($mpSecret !== '', '小程序 AppSecret', $mpSecret !== '' ? mask($mpSecret) : '(empty)');
$ciKey = dirname(__DIR__).'/miniprogram/ci/private.key';
optionalOk(is_file($ciKey), 'CI private.key', is_file($ciKey) ? 'present' : 'missing — 见 miniprogram/ci/README.md');

echo "\n[4/5] 支付 / 短信 / 邮件\n";
$payDriver = setting('payment_driver') ?: (string) env('PAYMENT_DRIVER', 'mock');
if ($payDriver === 'mock' || $payDriver === '') {
    warn('支付驱动为 mock（演示）', $payDriver ?: 'mock');
} else {
    $gateKey = match ($payDriver) {
        'alipay' => 'alipay_enabled',
        'wechat' => 'wechat_enabled',
        'stripe' => 'stripe_enabled',
        'paypal' => 'paypal_enabled',
        default => null,
    };
    $enabled = $gateKey ? (setting($gateKey, '0') === '1') : true;
    ok($enabled, "支付驱动 {$payDriver}", $enabled ? 'enabled' : 'driver set but gateway switch off');
}

$smsDriver = setting('sms_driver') ?: (string) env('SMS_DRIVER', 'log');
if (in_array($smsDriver, ['log', 'array', ''], true)) {
    warn('短信驱动为开发模式', $smsDriver ?: 'log');
} else {
    $hasSmsKey = (bool) (setting('sms_aliyun_key') || setting('sms_tencent_key') || env('SMS_ALIYUN_KEY') || env('ALIYUN_SMS_ACCESS_KEY'));
    ok($hasSmsKey, "短信驱动 {$smsDriver}", $hasSmsKey ? 'credentials present' : 'missing credentials');
}

$mailDriver = setting('mail_driver') ?: (string) env('MAIL_MAILER', 'log');
if (in_array($mailDriver, ['log', 'array', ''], true)) {
    warn('邮件驱动为 log（不会真实发送）', $mailDriver ?: 'log');
} else {
    $smtpHost = setting('smtp_host') ?: (string) env('MAIL_HOST', '');
    $smtpUser = setting('smtp_username') ?: (string) env('MAIL_USERNAME', '');
    ok($smtpHost !== '' && $smtpHost !== '127.0.0.1', 'SMTP 主机', $smtpHost !== '' ? $smtpHost : '(empty)');
    if ($smtpUser === '') {
        warn('SMTP 用户名未配置');
    } else {
        ok(true, 'SMTP 用户名', mask($smtpUser));
    }
}
ok((bool) setting('mail_from_address'), '发件人邮箱', setting('mail_from_address') ?: '(empty)');

echo "\n[5/5] 静态品牌资源\n";
$favicon = setting('favicon_url') ?: '/images/favicon.svg';
$favPath = public_path(ltrim(parse_url($favicon, PHP_URL_PATH) ?: $favicon, '/'));
ok(is_file($favPath), 'Favicon 文件', basename($favPath));
foreach (['images/pwa-icon-192.png', 'images/pwa-icon-512.png'] as $rel) {
    ok(is_file(public_path($rel)), 'PWA 图标 '.$rel, is_file(public_path($rel)) ? 'present' : 'missing');
}

echo "\n参考命令\n";
echo "  php artisan ops:readiness\n";
echo "  php artisan ops:readiness --strict\n";
echo "  php scripts/verify-miniprogram.php\n";
echo "  php scripts/verify-production-config.php\n";

echo "\n──────────────────────────────────────────────────\n";
echo " 通过 {$passed} · 失败 {$failed} · 警告 {$warned}".($strict ? ' （严格模式：警告计入失败）' : '')."\n";
echo "──────────────────────────────────────────────────\n\n";

exit($failed > 0 ? 1 : 0);
