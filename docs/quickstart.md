# M2-39 🚀 客户集成快速入门指南

> **任务**：M2-39 "5分钟接入互物通" — PHP/Node/Python
> **版本**：v1.0 | **日期**：2026-06-14

---

## 1. 概述

互物通提供简单易用的 API 和 SDK，让您的应用程序快速集成 License 授权管理功能。

本指南将带您在 **5 分钟** 内完成集成。支持以下语言：

| 语言 | SDK | 包管理器 | 示例项目 |
|:----|:----|:--------|:--------|
| **PHP** (Laravel) | `huwutong/laravel-sdk` | Composer | `sdk/php/` |
| **Node.js** (Express) | `@huwutong/sdk` | npm | `sdk/node/` |
| **Python** (Flask) | `huwutong-sdk` | pip | `sdk/python/` |

---

## 2. 快速开始（5 分钟）

### 2.1 获取 API 凭证

1. 登录 [管理后台](https://admin.huwutong.com)
2. 进入 **API 密钥管理** → **创建新密钥**
3. 选择权限范围（建议先选只读权限测试）
4. 复制 `API Key` 和 `API Secret`

```bash
# 保存到环境变量
export HWT_API_KEY="your-api-key-here"
export HWT_API_SECRET="your-api-secret-here"
export HWT_BASE_URL="https://api.huwutong.com"
```

### 2.2 PHP (Laravel) 集成

```bash
# 步骤 1: 安装 SDK
composer require huwutong/laravel-sdk

# 步骤 2: 发布配置
php artisan vendor:publish --provider="Huwutong\LaravelSdk\ServiceProvider"
```

配置 `.env`：
```ini
HWT_API_KEY=your-api-key-here
HWT_API_SECRET=your-api-secret-here
HWT_BASE_URL=https://api.huwutong.com
```

```php
<?php
// 步骤 3: 验证 License（核心功能）

use Huwutong\LaravelSdk\Facades\HwtLicense;

// 激活 License
$result = HwtLicense::activate([
    'license_key' => 'HWT-PRO-XXXXXXXXXX',
    'device_id' => 'unique-device-id',
    'fingerprint' => hash('sha256', 'cpu-mac-motherboard-info'),
]);

if ($result->success) {
    echo "激活成功！有效期至: " . $result->data['expires_at'];
} else {
    echo "激活失败: " . $result->message;
}

// 验证 License（每次启动/请求时调用）
$validation = HwtLicense::validate([
    'license_key' => 'HWT-PRO-XXXXXXXXXX',
    'device_id' => 'unique-device-id',
]);

if ($validation->valid) {
    echo "License 有效，剩余 {$validation->data['days_left']} 天";
} else {
    // License 无效，限制功能
}

// 吊销 License（用户卸载/更换设备时）
HwtLicense::revoke([
    'license_key' => 'HWT-PRO-XXXXXXXXXX',
    'device_id' => 'old-device-id',
]);
```

**完整示例**：参见 `sdk/php/examples/basic-integration.php`

### 2.3 Node.js (Express) 集成

```bash
# 步骤 1: 安装 SDK
npm install @huwutong/sdk

# 步骤 2: 配置
```

创建 `.env`：
```ini
HWT_API_KEY=your-api-key-here
HWT_API_SECRET=your-api-secret-here
HWT_BASE_URL=https://api.huwutong.com
```

```javascript
// 步骤 3: 初始化（app.js）

import { HwtClient } from '@huwutong/sdk';

const client = new HwtClient({
  apiKey: process.env.HWT_API_KEY,
  apiSecret: process.env.HWT_API_SECRET,
  baseUrl: process.env.HWT_BASE_URL,
});

// 激活 License
async function activateLicense(licenseKey, deviceId) {
  try {
    const result = await client.license.activate({
      license_key: licenseKey,
      device_id: deviceId,
      fingerprint: require('crypto')
        .createHash('sha256')
        .update('cpu-mac-motherboard')
        .digest('hex'),
    });
    console.log('激活成功:', result);
    return result;
  } catch (error) {
    console.error('激活失败:', error.message);
    throw error;
  }
}

// Express 中间件：验证 License
function licenseMiddleware(req, res, next) {
  const licenseKey = req.headers['x-license-key'];
  const deviceId = req.headers['x-device-id'];

  if (!licenseKey || !deviceId) {
    return res.status(401).json({ error: '缺少 License 信息' });
  }

  client.license.validate({ license_key: licenseKey, device_id: deviceId })
    .then((result) => {
      if (result.valid) {
        req.license = result.data;
        next();
      } else {
        res.status(403).json({ error: 'License 无效' });
      }
    })
    .catch(() => res.status(500).json({ error: '验证服务异常' }));
}

// 使用中间件保护路由
app.use('/api/protected', licenseMiddleware);
app.get('/api/protected/data', (req, res) => {
  res.json({ message: '受保护的数据', license: req.license });
});
```

**完整示例**：参见 `sdk/node/examples/basic-integration.js`

### 2.4 Python (Flask) 集成

```bash
# 步骤 1: 安装 SDK
pip install huwutong-sdk

# 步骤 2: 配置
```

创建 `.env`：
```ini
HWT_API_KEY=your-api-key-here
HWT_API_SECRET=your-api-secret-here
HWT_BASE_URL=https://api.huwutong.com
```

```python
# 步骤 3: 使用 SDK（app.py）

from huwutong import HwtClient
from flask import Flask, request, jsonify
import hashlib
import os

app = Flask(__name__)

client = HwtClient(
    api_key=os.getenv('HWT_API_KEY'),
    api_secret=os.getenv('HWT_API_SECRET'),
    base_url=os.getenv('HWT_BASE_URL'),
)

# 激活 License
def activate_license(license_key, device_id):
    try:
        result = client.license.activate(
            license_key=license_key,
            device_id=device_id,
            fingerprint=hashlib.sha256(
                b'cpu-mac-motherboard'
            ).hexdigest(),
        )
        print(f'激活成功: {result}')
        return result
    except Exception as e:
        print(f'激活失败: {e}')
        raise

# Flask 装饰器：验证 License
def require_license(f):
    def decorated(*args, **kwargs):
        license_key = request.headers.get('X-License-Key')
        device_id = request.headers.get('X-Device-Id')

        if not license_key or not device_id:
            return jsonify({'error': '缺少 License 信息'}), 401

        try:
            result = client.license.validate(
                license_key=license_key,
                device_id=device_id,
            )
            if result['valid']:
                request.license = result['data']
                return f(*args, **kwargs)
            else:
                return jsonify({'error': 'License 无效'}), 403
        except Exception:
            return jsonify({'error': '验证服务异常'}), 500

    return decorated

# 使用装饰器保护路由
@app.route('/api/protected/data')
@require_license
def protected_data():
    return jsonify({
        'message': '受保护的数据',
        'license': request.license,
    })

if __name__ == '__main__':
    app.run(port=5000)
```

**完整示例**：参见 `sdk/python/examples/basic-integration.py`

---

## 3. 常见集成场景

### 3.1 场景 A：桌面应用验证

```python
# Python 桌面应用每次启动时验证
import huwutong
import hashlib, platform, uuid

client = huwutong.HwtClient(
    api_key='YOUR_API_KEY',
    api_secret='YOUR_API_SECRET'
)

# 生成设备指纹
fingerprint = hashlib.sha256(
    f"{platform.node()}-{uuid.getnode()}".encode()
).hexdigest()

# 验证
result = client.license.validate(
    license_key='HWT-ENT-XXXXXXXXXX',
    device_id=str(uuid.uuid4()),
    fingerprint=fingerprint,
)

if not result['valid']:
    print("License 无效，请购买正版授权")
    sys.exit(1)
```

### 3.2 场景 B：Web 应用中间件

```javascript
// Node.js Express 全局中间件
const express = require('express');
const { HwtClient } = require('@huwutong/sdk');

const app = express();
const client = new HwtClient({
  apiKey: process.env.HWT_API_KEY,
  apiSecret: process.env.HWT_API_SECRET,
});

// 验证 License 中间件
app.use(async (req, res, next) => {
  // 公开路由不验证
  if (req.path.startsWith('/public')) return next();

  const licenseKey = req.cookies?.license_key || req.headers['x-license-key'];
  if (!licenseKey) return res.redirect('/activate');

  const { valid, data } = await client.license.validate({
    license_key: licenseKey,
    device_id: req.deviceId,
  });

  if (!valid) return res.redirect('/activate');
  req.licenseInfo = data;
  next();
});
```

### 3.3 场景 C：SaaS 多租户自动授权

```php
// Laravel 中：注册成功自动创建 Trial License
use Huwutong\LaravelSdk\Facades\HwtLicense;

public function registered($user)
{
    // 为新客户创建 14 天 Trial License
    $license = HwtLicense::createTrial([
        'product_id' => 1,
        'customer_id' => $user->customer_id,
        'valid_days' => 14,
        'max_devices' => 3,
    ]);

    // 发送激活邮件
    Mail::to($user)->send(new LicenseWelcomeMail($license));
}
```

---

## 4. API 参考

### 4.1 核心端点

| 端点 | 方法 | 说明 | 频率 |
|:----|:----:|:----|:----:|
| `/api/license/activate` | POST | 激活 License | 低频 |
| `/api/license/validate` | GET | 验证 License | 高频 |
| `/api/license/revoke` | POST | 吊销 License | 低频 |
| `/api/license/check` | GET | 检查 License 状态 | 中频 |
| `/api/health/live` | GET | 健康检查 | 高频 |

### 4.2 错误码

| 错误码 | HTTP 状态码 | 说明 | 处理建议 |
|:------|:----------:|:----|:--------|
| `LICENSE_EXPIRED` | 403 | License 已过期 | 提示用户续期 |
| `LICENSE_INVALID` | 403 | License Key 无效 | 检查 Key 是否正确 |
| `DEVICE_LIMIT` | 403 | 设备数超限 | 提示用户解绑设备 |
| `FINGERPRINT_MISMATCH` | 403 | 设备指纹不匹配 | 重新激活或联系客服 |
| `LICENSE_SUSPENDED` | 403 | License 已被挂起 | 联系客服 |
| `RATE_LIMITED` | 429 | 请求过于频繁 | 降低请求频率 |
| `INVALID_SIGNATURE` | 401 | 签名验证失败 | 检查 API Secret |

### 4.3 限流说明

| 端点 | 限制 | 周期 |
|:----|:---:|:----|
| `/api/license/validate` | 1000 次 | 每分钟 |
| `/api/license/activate` | 100 次 | 每小时 |
| `/api/license/revoke` | 50 次 | 每小时 |

---

## 5. 最佳实践

### 5.1 缓存验证结果

```javascript
// SDK 自动缓存验证结果（默认 24h），无需额外配置
const result = await client.license.validate({ license_key, device_id });
// 后续 24h 内相同请求使用本地缓存，提升性能
```

### 5.2 离线宽限期

SDK 内置离线宽限期机制：网络不可用时可继续使用本地缓存结果（默认可离线使用 7 天）。

### 5.3 错误处理

```python
try:
    result = client.license.validate(license_key=key, device_id=dev)
except huwutong.RateLimitError:
    # 限流了，等待后重试
    time.sleep(1)
    result = client.license.validate(license_key=key, device_id=dev)
except huwutong.NetworkError:
    # 网络异常，使用本地缓存
    result = client.get_cached_result(license_key=key)
except huwutong.AuthError:
    # API 凭证错误
    log.error("API 凭证配置错误，请检查 HWT_API_KEY")
```

### 5.4 安全性建议

- API Secret **永远不要**硬编码在代码中
- 移动端使用 **非对称加密** 通信
- Web 端通过 **后端代理** 调用 API，不直接暴露 API Key
- 启用 **IP 白名单** 限制 API Key 的使用范围
- 定期 **轮换 API Key**

---

## 6. 故障排除

| 问题 | 可能原因 | 解决方案 |
|:----|:--------|:--------|
| 激活返回 `FINGERPRINT_MISMATCH` | 设备硬件变更 | 宽容匹配模式（3取2） |
| 验证返回 `LICENSE_EXPIRED` | License 已过期 | 登录后台续期 |
| SDK 报 `Connection timeout` | 网络不通 | 检查防火墙/代理设置 |
| 激活返回 `DEVICE_LIMIT` | 绑定设备数超限 | 解绑不用的设备 |
| API 返回 401 | API Key 无效 | 检查 API Key 配置 |

---

## 7. 下一步

- 📖 [API 完整文档](https://developers.huwutong.com/docs/api)
- 💻 [SDK GitHub 仓库](https://github.com/huwutong/sdk)
- 🎓 [视频教程](https://developers.huwutong.com/tutorials)
- 💬 [开发者社区](https://github.com/huwutong/discussions)
- 🆘 [帮助中心](https://help.huwutong.com)
