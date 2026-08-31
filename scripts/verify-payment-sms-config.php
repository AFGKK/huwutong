<?php
/**
 * D-03 / D-04 支付/短信配置就绪检查
 * 验证支付网关和短信/邮件生产配置是否就绪
 * 
 * 用法: php scripts/verify-payment-sms-config.php
 */

$passed = 0;
$failed = 0;

echo "══════════════════════════════════════════════════\n";
echo " 互物通 — 支付/短信配置就绪检查 (D-03 / D-04)\n";
echo "══════════════════════════════════════════════════\n\n";

// ─── 1. 支付配置 ───
echo "[1/3] 支付网关配置\n";

$env = file_get_contents(__DIR__ . '/../.env');

$paymentChecks = [
    'PAYMENT_DRIVER' => '/^PAYMENT_DRIVER=/m',
];

foreach ($paymentChecks as $name => $pattern) {
    if (preg_match($pattern, $env)) {
        echo "  ✅ {$name} 已配置\n";
        $passed++;
    } else {
        echo "  ❌ {$name} 缺失\n";
        $failed++;
    }
}

// 检查 Gateway 文件完整性
$gateways = [
    'AlipayPaymentGateway' => 'app/Services/Payment/AlipayPaymentGateway.php',
    'StripePaymentGateway' => 'app/Services/Payment/StripePaymentGateway.php',
    'PaypalPaymentGateway' => 'app/Services/Payment/PaypalPaymentGateway.php',
    'WechatPaymentGateway' => 'app/Services/Payment/WechatPaymentGateway.php',
    'MockPaymentGateway' => 'app/Services/Payment/MockPaymentGateway.php',
];

foreach ($gateways as $name => $path) {
    if (file_exists(__DIR__ . '/../' . $path)) {
        echo "  ✅ {$name}\n";
        $passed++;
    } else {
        echo "  ❌ {$name} 缺失\n";
        $failed++;
    }
}

// 检查 Webhook 控制器
$webhookFiles = [
    'PaymentWebhookController' => 'app/Http/Controllers/Api/PaymentWebhookController.php',
];

foreach ($webhookFiles as $name => $path) {
    if (file_exists(__DIR__ . '/../' . $path)) {
        echo "  ✅ {$name}\n";
        $passed++;
    } else {
        echo "  ❌ {$name} 缺失\n";
        $failed++;
    }
}

echo "\n  生产模板:\n";
$prodEnv = __DIR__ . '/../.env.production.example';
if (file_exists($prodEnv)) {
    $prodContent = file_get_contents($prodEnv);
    if (preg_match('/^PAYMENT_DRIVER=/m', $prodContent)) {
        echo "  ✅ .env.production.example 含支付配置\n";
        $passed++;
    }
}

// ─── 2. 短信配置 ───
echo "\n[2/3] 短信服务配置\n";

$smsChecks = [
    'SMS_DRIVER' => '/^SMS_DRIVER=/m',
    'SmsService' => file_exists(__DIR__ . '/../app/Services/SmsService.php'),
    'AliyunSmsClient' => file_exists(__DIR__ . '/../app/Services/Sms/AliyunSmsClient.php'),
];

foreach ($smsChecks as $name => $check) {
    if ($check) {
        echo "  ✅ {$name} 已就绪\n";
        $passed++;
    } else {
        echo "  ❌ {$name} 缺失\n";
        $failed++;
    }
}

// ─── 3. 邮件配置 ───
echo "\n[3/3] 邮件服务配置\n";

$mailChecks = [
    'MAIL_MAILER' => preg_match('/^MAIL_MAILER=/m', $env),
    'Mail/VerifyCodeMail' => file_exists(__DIR__ . '/../app/Mail/VerifyCodeMail.php'),
    'Mail 配置文件' => file_exists(__DIR__ . '/../config/mail.php'),
];

foreach ($mailChecks as $name => $check) {
    if ($check) {
        echo "  ✅ {$name} 已就绪\n";
        $passed++;
    } else {
        echo "  ❌ {$name} 缺失\n";
        $failed++;
    }
}

// ─── 结果 ───
echo "\n══════════════════════════════════════════════════\n";
echo " 结果: {$passed} 通过, {$failed} 失败\n";
echo "══════════════════════════════════════════════════\n";

if ($failed > 0) {
    echo "\n⚠️  有 {$failed} 项未通过，请检查缺失文件\n";
    exit(1);
}

echo "\n✅ 支付/短信/邮件配置验证通过！\n";
echo "  生产部署前需填写:\n";
echo "    - .env: PAYMENT_DRIVER=alipay (或 stripe)\n";
echo "    - .env: ALIPAY_APP_ID / ALIPAY_PRIVATE_KEY / ALIPAY_PUBLIC_KEY\n";
echo "    - .env: SMS_DRIVER=aliyun + ALIYUN_SMS_*\n";
echo "    - .env: MAIL_MAILER=smtp + SMTP 凭据\n";
exit(0);
