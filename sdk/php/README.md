# HWT License PHP SDK

官方 PHP SDK，用于集成 HWT License 激活/验证/设备管理功能。

## 安装

```bash
composer require huwutong/huwutong-sdk-php
```

## 快速开始

```php
<?php

require 'vendor/autoload.php';

use Huwutong\HWTClient;

$client = new HWTClient([
    'api_key' => 'your_api_key_here',
    'host'    => 'https://api.huwutong.com',
]);

// 激活 License
$result = $client->activate('LICENSE-KEY-HERE', [
    'machine_id' => 'unique-machine-id',
    'hostname'   => 'server-01',
    'platform'   => 'linux',
]);
echo "激活成功，到期: {$result['expires_at']}\n";

// 验证 License
$result = $client->validate('LICENSE-KEY-HERE', [
    'machine_id' => 'unique-machine-id',
]);
if ($result['is_valid']) {
    echo "License 有效，到期: {$result['expires_at']}\n";
} else {
    echo "License 无效: {$result['message']}\n";
}

// 检查 Feature Flag
$enabled = $client->checkFeature('LICENSE-KEY-HERE', 'ai_features');
echo "AI 功能: " . ($enabled ? '启用' : '未启用') . "\n";
```

## API 参考

| 方法 | 说明 |
|------|------|
| `activate()` | 激活 License |
| `validate()` | 验证 License |
| `deactivate()` | 解除激活 |
| `getLicenseInfo()` | 查询 License |
| `checkFeature()` | 检查 Feature |
| `verifyOffline()` | 离线验证 |
| `getOfflineLicense()` | 获取离线文件 |

## 错误处理

```php
<?php

use Huwutong\HWTClient;
use Huwutong\Exception\HWTApiException;
use Huwutong\Exception\HWTNetworkException;

$client = new HWTClient(['api_key' => 'xxx']);

try {
    $result = $client->activate('INVALID-KEY', []);
} catch (HWTApiException $e) {
    echo "API 错误 [{$e->getCode()}]: {$e->getMessage()}\n";
} catch (HWTNetworkException $e) {
    echo "网络错误: {$e->getMessage()}\n";
}
```

## Composer 安装包结构

```
huwutong-sdk-php/
├── composer.json
├── README.md
├── src/
│   ├── HWTClient.php          # 主客户端
│   ├── Config.php             # 配置管理
│   ├── Exception/
│   │   ├── HWTApiException.php
│   │   └── HWTNetworkException.php
│   └── Util/
│       ├── Signature.php      # 签名工具
│       └── Validator.php      # 输入验证
├── tests/
│   ├── HWTClientTest.php
│   └── SignatureTest.php
└── examples/
    ├── activate.php
    ├── validate.php
    └── offline.php
```

## composer.json

```json
{
    "name": "huwutong/huwutong-sdk-php",
    "description": "HWT License 官方 PHP SDK - License 激活/验证/设备管理",
    "type": "library",
    "require": {
        "php": ">=8.1",
        "guzzlehttp/guzzle": "^7.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.0"
    },
    "autoload": {
        "psr-4": {
            "Huwutong\\": "src/"
        }
    },
    "license": "MIT",
    "authors": [
        {
            "name": "Huwutong Team",
            "email": "dev@huwutong.com"
        }
    ]
}
```

## 依赖

- PHP >= 8.1
- GuzzleHttp ^7.0（HTTP 客户端）
- 可选的：ext-sodium（Ed25519 离线验签）

> **完整源码仓库**: `https://github.com/huwutong/huwutong-sdk-php`
> **文档**: `https://developers.huwutong.com`
