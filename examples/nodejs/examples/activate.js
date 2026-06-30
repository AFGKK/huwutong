/**
 * HWT License 激活示例 (Node.js)
 *
 * 用法: node examples/activate.js <license_key>
 *
 * 示例:
 *   node examples/activate.js HWT-XXXX-XXXX
 *   HWT_API_KEY=sk_test_xxx node examples/activate.js HWT-XXXX-XXXX
 */

require('dotenv').config();
const os = require('os');
const crypto = require('crypto');
const { HWTClient } = require('../src/client');

const licenseKey = process.argv[2] || process.env.HWT_LICENSE_KEY;
const apiKey = process.env.HWT_API_KEY || 'your_api_key_here';
const host = process.env.HWT_HOST || 'https://api.huwutong.com';

if (!licenseKey) {
  console.error('用法: node examples/activate.js <license_key>');
  console.error('或设置环境变量 HWT_LICENSE_KEY');
  process.exit(1);
}

const client = new HWTClient(apiKey, host);

const deviceInfo = {
  fingerprint: crypto.createHash('sha256').update(os.hostname() + os.platform()).digest('hex'),
  mac: '00:1A:2B:3C:4D:5E',
  cpuId: os.cpus()[0]?.model || 'Unknown',
  platform: os.platform(),
  metadata: {
    hostname: os.hostname(),
    nodeVersion: process.version,
  },
};

console.log(`正在激活 License: ${licenseKey}`);
console.log(`API 地址: ${host}`);
console.log('---');

client.activate(licenseKey, deviceInfo)
  .then(result => {
    if (result.success !== false) {
      const data = result.data || result;
      const isValid = data.valid || result.success;
      if (isValid) {
        console.log('✅ 激活成功!');
        console.log(`License Key: ${data.license_key || licenseKey}`);
        console.log(`状态:       ${data.status || 'active'}`);
        console.log(`到期时间:   ${data.expires_at || 'N/A'}`);
        console.log(`设备 ID:    ${data.device_id || 'N/A'}`);
      } else {
        console.log('❌ 激活失败');
        console.log(`错误码: [${data.error_code || result.error || 'UNKNOWN'}]`);
        console.log(`消息:   ${data.message || result.message || '未知错误'}`);
      }
    } else {
      console.log(`❌ 激活失败 [${result.error}]: ${result.message}`);
    }
  })
  .catch(err => {
    console.error('❌ 异常:', err.message);
    process.exit(1);
  });
