# HWT License SDK 集成示例

> 互物通企业授权管理系统 — 多语言 SDK 集成 Demo

本目录提供 **PHP-Laravel / Node.js Express / Python Flask** 三种语言的完整集成示例，
展示如何将 HWT License 授权系统集成到您的应用中。

## 📋 目录结构

```
examples/
├── README.md              # 本文档
├── php/                   # PHP 集成示例
│   ├── composer.json
│   ├── src/HWTClient.php  # SDK 客户端封装
│   └── examples/
│       ├── activate.php   # License 激活
│       └── validate.php   # License 验证
├── nodejs/                # Node.js 集成示例
│   ├── package.json
│   ├── .env.example
│   ├── src/client.js      # SDK 客户端封装
│   └── examples/
│       ├── activate.js    # License 激活
│       ├── validate.js    # License 验证
│       └── server.js      # Express 中间件集成
└── python/                # Python 集成示例
    ├── app.py             # Flask 中间件集成
    └── (SDK 位于 sdk/python/)
```

## 🚀 快速开始

### 前置条件

1. 在互物通管理后台创建 API Key
2. 准备一个有效的 License Key
3. 设置环境变量或直接传入参数

### PHP

```bash
cd examples/php
composer install
php examples/activate.php HWT-XXXX-XXXX
php examples/validate.php HWT-XXXX-XXXX
```

### Node.js

```bash
cd examples/nodejs
npm install
node examples/activate.js HWT-XXXX-XXXX
node examples/validate.js HWT-XXXX-XXXX
node examples/server.js          # 启动 Express 示例服务
```

### Python

```bash
cd sdk/python
pip install -e .                    # 安装 SDK
cd ../../examples/python
pip install flask
HWT_API_KEY=sk_test_xxx python app.py  # 启动 Flask 示例服务
```

## 🔗 核心 API

| 端点 | 方法 | 说明 | SDK 方法 |
|------|------|------|----------|
| `/api/license/activate` | POST | 激活 License | `client.activate()` |
| `/api/license/validate` | POST | 验证 License | `client.validate()` |
| `/api/license/check-feature` | POST | 检查 Feature Flag | `client.checkFeature()` |
| `/api/license/info/{key}` | GET | 查询 License 信息 | `client.getLicenseInfo()` |
| `/api/telemetry/heartbeat` | POST | SDK 心跳上报 | `client.heartbeat()` |
| `/api/offline/verify` | POST | 离线验证 | `client.verifyOffline()` |

## 📖 错误码 (M2-34 标准)

| 错误码 | 说明 | HTTP 状态 |
|--------|------|:--------:|
| `LICENSE_EXPIRED` | License 已过期 | 403 |
| `LICENSE_SUSPENDED` | License 已被挂起 | 403 |
| `LICENSE_REVOKED` | License 已被吊销 | 403 |
| `DEVICE_LIMIT` | 设备数量超限 | 429 |
| `FINGERPRINT_MISMATCH` | 设备指纹不匹配 | 401 |
| `ACTIVATION_LIMIT` | 激活次数超限 | 429 |
| `RATE_LIMITED` | 请求频率超限 | 429 |
| `SIGNATURE_INVALID` | 签名验证失败 | 401 |
| `LICENSE_NOT_FOUND` | License Key 不存在 | 404 |
| `SDK_VERSION_DEPRECATED` | SDK 版本已废弃 | 426 |

## 📄 许可证

MIT License
