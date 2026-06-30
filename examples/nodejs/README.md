# HWT License Node.js SDK Demo

> 互物通授权系统 Node.js 集成示例 — 支持 Express / 独立使用

## 安装

```bash
npm install
cp .env.example .env
# 编辑 .env 填入你的 API Key
```

## 用法

```bash
# 激活 License
node examples/activate.js HWT-XXXX-XXXX

# 验证 License
node examples/validate.js HWT-XXXX-XXXX

# 启动 Express 示例服务（需 License 验证的 API）
node examples/server.js
```

## Express 中间件集成

```javascript
const { HWTClient } = require('./src/client');

const client = new HWTClient(process.env.HWT_API_KEY);

// 在路由中使用 License 验证中间件
app.get('/api/protected', async (req, res) => {
    const licenseKey = req.headers['x-license-key'];
    if (!licenseKey) return res.status(401).json({ error: 'LICENSE_KEY_REQUIRED' });

    const result = await client.validate(licenseKey);
    if (result.data?.valid) {
        // License 有效，继续处理
        next();
    } else {
        res.status(403).json({ error: result.error, message: result.message });
    }
});
```

## API 参考

| 方法 | 说明 |
|------|------|
| `client.activate(key, deviceInfo)` | 激活 License |
| `client.validate(key, fingerprint)` | 验证 License |
| `client.checkFeature(key, feature)` | 检查 Feature |
| `client.getLicenseInfo(key)` | 查询信息 |
| `client.heartbeat(key, fingerprint)` | 心跳上报 |
