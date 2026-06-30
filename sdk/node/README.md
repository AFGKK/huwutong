# HWT License Node.js SDK

官方 Node.js SDK，用于集成 HWT License 激活/验证/设备管理功能。

## 安装

```bash
npm install huwutong-sdk
# 或
yarn add huwutong-sdk
```

## 快速开始

```javascript
const { HWTClient } = require('huwutong-sdk');

const client = new HWTClient({
    apiKey: 'your_api_key_here',
    host: 'https://api.huwutong.com',
});

// 激活 License (async/await)
async function activate() {
    const result = await client.activate('LICENSE-KEY-HERE', {
        machine_id: 'unique-machine-id',
        hostname: 'server-01',
        platform: 'linux',
    });
    console.log(`激活成功，到期: ${result.expires_at}`);
}

// 验证 License
async function validate() {
    const result = await client.validate('LICENSE-KEY-HERE', {
        machine_id: 'unique-machine-id',
    });
    if (result.is_valid) {
        console.log(`License 有效，到期: ${result.expires_at}`);
    } else {
        console.log(`License 无效: ${result.message}`);
    }
}

// 检查 Feature Flag
async function checkFeature() {
    const enabled = await client.checkFeature('LICENSE-KEY-HERE', 'ai_features');
    console.log(`AI 功能: ${enabled ? '启用' : '未启用'}`);
}
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

```javascript
const { HWTClient, HWTApiError, HWTNetworkError } = require('huwutong-sdk');

const client = new HWTClient({ apiKey: 'xxx' });

async function example() {
    try {
        await client.activate('INVALID-KEY', {});
    } catch (err) {
        if (err instanceof HWTApiError) {
            console.error(`API 错误 [${err.code}]: ${err.message}`);
        } else if (err instanceof HWTNetworkError) {
            console.error(`网络错误: ${err.message}`);
        }
    }
}
```

## TypeScript 支持

```typescript
import { HWTClient, ActivateResult, ValidateResult } from 'huwutong-sdk';

const client = new HWTClient({
    apiKey: 'your_api_key_here',
    host: 'https://api.huwutong.com',
});

const result: ValidateResult = await client.validate('LICENSE-KEY-HERE', {
    machine_id: 'unique-machine-id',
});
```

## package.json

```json
{
    "name": "huwutong-sdk",
    "version": "1.0.0",
    "description": "HWT License 官方 Node.js SDK - License 激活/验证/设备管理",
    "main": "dist/index.js",
    "types": "dist/index.d.ts",
    "scripts": {
        "build": "tsc",
        "test": "jest",
        "prepublishOnly": "npm run build"
    },
    "dependencies": {
        "axios": "^1.6.0"
    },
    "devDependencies": {
        "@types/node": "^20.0.0",
        "jest": "^29.0.0",
        "typescript": "^5.0.0"
    },
    "license": "MIT",
    "author": "Huwutong Team <dev@huwutong.com>"
}
```

## 项目结构

```
huwutong-sdk-node/
├── package.json
├── tsconfig.json
├── README.md
├── src/
│   ├── index.ts              # 入口
│   ├── HWTClient.ts          # 主客户端
│   ├── Config.ts             # 配置管理
│   ├── types.ts              # TypeScript 类型定义
│   ├── errors.ts             # 错误类
│   └── util/
│       ├── signature.ts      # 签名工具
│       └── validator.ts      # 输入验证
├── tests/
│   ├── HWTClient.test.ts
│   └── signature.test.ts
└── examples/
    ├── activate.js
    ├── validate.js
    └── offline.js
```

## 依赖

- Node.js >= 18 LTS
- axios ^1.6.0（HTTP 客户端）
- TypeScript 5.0+（开发依赖）
- 可选的：sodium-native（Ed25519 离线验签）

> **完整源码仓库**: `https://github.com/huwutong/huwutong-sdk-node`
> **文档**: `https://developers.huwutong.com`
> **NPM**: `https://www.npmjs.com/package/huwutong-sdk`
