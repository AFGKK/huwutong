/**
 * HWT License Express 集成示例
 *
 * 展示如何在 Express 应用中集成 HWT License 验证中间件
 *
 * 用法: node examples/server.js
 * 访问: http://localhost:3000
 */

require('dotenv').config();
const express = require('express');
const { HWTClient } = require('../src/client');

const app = express();
app.use(express.json());

const client = new HWTClient(
  process.env.HWT_API_KEY || 'your_api_key_here',
  process.env.HWT_HOST || 'https://api.huwutong.com'
);

// License 验证中间件
async function licenseMiddleware(req, res, next) {
  const licenseKey = req.headers['x-license-key'];

  if (!licenseKey) {
    return res.status(401).json({
      error: 'LICENSE_KEY_REQUIRED',
      message: '请提供 X-License-Key 请求头',
    });
  }

  const result = await client.validate(licenseKey);

  if (result.success !== false && (result.data?.valid || result.valid)) {
    req.license = result.data || result;
    next();
  } else {
    const errorCode = result.error || 'LICENSE_INVALID';
    const statusMap = {
      LICENSE_EXPIRED: 403,
      LICENSE_SUSPENDED: 403,
      LICENSE_REVOKED: 403,
      DEVICE_LIMIT: 429,
    };
    res.status(statusMap[errorCode] || 401).json({
      error: errorCode,
      message: result.message || 'License 无效',
    });
  }
}

// 公开路由
app.get('/', (req, res) => {
  res.json({
    service: 'HWT License Demo API',
    version: '1.0.0',
    endpoints: {
      '/api/protected': '需要 License 验证 (X-License-Key header)',
    },
  });
});

// 受保护路由（需 License 验证）
app.get('/api/protected', licenseMiddleware, (req, res) => {
  res.json({
    message: '访问成功!',
    license: {
      key: req.license.license_key,
      status: req.license.status,
      expires_at: req.license.expires_at,
    },
  });
});

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
  console.log(`🚀 HWT License Demo Server 运行在 http://localhost:${PORT}`);
  console.log('测试: curl -H "X-License-Key: HWT-XXXX" http://localhost:3000/api/protected');
});
