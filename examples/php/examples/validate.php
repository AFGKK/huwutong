<?php

/**
 * HWT License 验证示例
 *
 * 用法: php examples/validate.php <license_key> [api_key] [host]
 *
 * 错误码参考 (M2-34):
 * - LICENSE_EXPIRED:     License 已过期
 * - LICENSE_SUSPENDED:   License 已被挂起
 * - LICENSE_REVOKED:     License 已被吊销
 * - DEVICE_LIMIT:        设备数量超限
 * - FINGERPRINT_MISMATCH: 设备指纹不匹配
 */

require __DIR__ . '/../vendor/autoload.php';

use Huwutong\Demo\HWTClient;

$licenseKey = $argv[1] ?? getenv('HWT_LICENSE_KEY');
$apiKey = $argv[2] ?? getenv('HWT_API_KEY') ?? 'your_api_key_here';
$host = $argv[3] ?? getenv('HWT_HOST') ?? 'https://api.huwutong.com';

if (!$licenseKey) {
    echo "用法: php examples/validate.php <license_key>\n";
    exit(1);
}

$client = new HWTClient($apiKey, $host);
$fingerprint = hash('sha256', gethostname() . php_uname('n'));

echo "正在验证 License: {$licenseKey}\n";
echo "---\n";

$result = $client->validate($licenseKey, $fingerprint);

if (isset($result['data']['valid']) && $result['data']['valid']) {
    $data = $result['data'];
    echo "✅ License 有效!\n";
    echo "状态:     " . ($data['status'] ?? 'active') . "\n";
    echo "到期时间: " . ($data['expires_at'] ?? 'N/A') . "\n";

    if (!empty($data['features'])) {
        echo "可用功能: " . implode(', ', $data['features']) . "\n";
    }
} else {
    $error = $result['error'] ?? $result['data']['error_code'] ?? 'UNKNOWN';
    $message = $result['message'] ?? $result['data']['message'] ?? '验证失败';

    echo "❌ 验证失败\n";
    echo "错误码:   [{$error}]\n";
    echo "消息:     {$message}\n";

    // 根据 M2-34 错误码给出建议
    $suggestions = [
        'LICENSE_EXPIRED' => 'License 已过期，请联系管理员续期',
        'LICENSE_SUSPENDED' => 'License 已被挂起，请联系客服',
        'LICENSE_REVOKED' => 'License 已被吊销',
        'DEVICE_LIMIT' => '设备数量已达上限',
        'FINGERPRINT_MISMATCH' => '设备指纹不匹配，请重新激活',
    ];
    if (isset($suggestions[$error])) {
        echo "建议:     {$suggestions[$error]}\n";
    }
}
