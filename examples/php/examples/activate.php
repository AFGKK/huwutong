<?php

/**
 * HWT License 激活示例
 *
 * 用法: php examples/activate.php <license_key> [api_key] [host]
 *
 * 示例:
 *   php examples/activate.php HWT-XXXX-XXXX
 *   php examples/activate.php HWT-XXXX-XXXX sk_test_xxx https://api.huwutong.com
 */

require __DIR__ . '/../vendor/autoload.php';

use Huwutong\Demo\HWTClient;

// 从命令行参数或环境变量读取配置
$licenseKey = $argv[1] ?? getenv('HWT_LICENSE_KEY');
$apiKey = $argv[2] ?? getenv('HWT_API_KEY') ?? 'your_api_key_here';
$host = $argv[3] ?? getenv('HWT_HOST') ?? 'https://api.huwutong.com';

if (!$licenseKey) {
    echo "用法: php examples/activate.php <license_key>\n";
    echo "或设置环境变量 HWT_LICENSE_KEY\n";
    exit(1);
}

// 初始化客户端
$client = new HWTClient($apiKey, $host);

// 模拟设备信息
$deviceInfo = [
    'fingerprint' => hash('sha256', gethostname() . php_uname('n')),
    'mac' => '00:1A:2B:3C:4D:5E',
    'cpu_id' => 'Intel(R) Core(TM) i7-10700K',
    'disk_sn' => 'WD-WCC9K3R4V8F9',
    'system_uuid' => 'A1B2C3D4-E5F6-7890-ABCD-EF1234567890',
    'platform' => PHP_OS_FAMILY,
    'metadata' => [
        'hostname' => gethostname(),
        'php_version' => PHP_VERSION,
    ],
];

echo "正在激活 License: {$licenseKey}\n";
echo "API 地址: {$host}\n";
echo "---\n";

try {
    $result = $client->activate($licenseKey, $deviceInfo);

    if (isset($result['success']) && $result['success']) {
        $data = $result['data'] ?? $result;
        echo "✅ 激活成功!\n";
        echo "License Key: " . ($data['license_key'] ?? $licenseKey) . "\n";
        echo "状态:       " . ($data['status'] ?? 'active') . "\n";
        echo "到期时间:   " . ($data['expires_at'] ?? 'N/A') . "\n";
        echo "设备 ID:    " . ($data['device_id'] ?? 'N/A') . "\n";
    } else {
        $error = $result['error'] ?? 'UNKNOWN';
        $message = $result['message'] ?? '未知错误';
        echo "❌ 激活失败 [{$error}]: {$message}\n";
    }
} catch (\Exception $e) {
    echo "❌ 异常: " . $e->getMessage() . "\n";
    exit(1);
}
