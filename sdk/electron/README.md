# Huwutong Electron SDK

M2-21: 基于统一错误码标准 M2-34

## 安装

```bash
npm install @huwutong/sdk
```

或直接复制 `src/` 到项目中使用。

## 快速开始 (Electron 主进程)

```javascript
const { HwtClient } = require('@huwutong/sdk');

const client = new HwtClient({
  apiKey: 'your_api_key',
  host: 'https://api.huwutong.com',
});

async function main() {
  // 激活
  const activation = await client.activate('LICENSE-KEY', {
    machine_id: 'unique-machine-id',
    hostname: os.hostname(),
  });
  console.log(activation.success);

  // 验证
  const validation = await client.validate('LICENSE-KEY', {
    machine_id: 'unique-machine-id',
  });
  console.log(validation.isValid);
}

main();
```

## Electron 渲染进程中使用 (预加载桥接)

```javascript
// preload.js
const { contextBridge } = require('electron');
const { HwtClient } = require('@huwutong/sdk');

const client = new HwtClient({ apiKey: process.env.HWT_API_KEY });

contextBridge.exposeInMainWorld('hwtSdk', {
  activate: (key, info) => client.activate(key, info),
  validate: (key, info) => client.validate(key, info),
});
```
