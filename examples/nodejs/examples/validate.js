/**
 * HWT License 验证示例 (Node.js)
 *
 * 用法: node examples/validate.js <license_key>
 *
 * 错误码参考 (M2-34):
 * - LICENSE_EXPIRED:       License 已过期
 * - LICENSE_SUSPENDED:     License 已被挂起
 * - LICENSE_REVOKED:       License 已被吊销
 * - DEVICE_LIMIT:          设备数量超限
 * - FINGERPRINT_MISMATCH:  设备指纹不匹配
 */

require('dotenv').config();
const os = require('os');
const crypto = require('crypto');
const { HWTClient } = require('../src/client');

const licenseKey = process.argv[2] || process.env.HWT_LICENSE_KEY;
const apiKey = process.env.HWT_API_KEY || 'your_api_key_here';
const host = process.env.HWT_HOST || 'https://api.huwutong.com';

if (!licenseKey) {
  console.error('用法: node examples/validate.js <license_key>');
  process.exit(1);
}

const client = new HWTClient(apiKey, host);
const fingerprint = crypto.createHash('sha256').update(os.hostname() + os.platform()).digest('hex');

console.log(`正在验证 License: ${licenseKey}`);
console.log('---');

const suggestions = {
  LICENSE_EXPIRED: 'License 已过期，请联系管理员续期',
  LICENSE_SUSPENDED: 'License 已被挂起，请联系客服',
  LICENSE_REVOKED: 'License 已被吊销',
  DEVICE_LIMIT: '设备数量已达上限',
  FINGERPRINT_MISMATCH: '设备指纹不匹配，请重新激活',
};

client.validate(licenseKey, fingerprint)
  .then(result => {
    const data = result.data || result;
    const isValid = data.valid || data.is_valid;

    if (isValid) {
      console.log('✅ License 有效!');
      console.log(`状态:     ${data.status || 'active'}`);
      console.log(`到期时间: ${data.expires_at || 'N/A'}`);
      if (data.features?.length) {
        console.log(`可用功能: ${data.features.join(', ')}`);
      }
    } else {
      const errorCode = result.error || data.error_code || 'UNKNOWN';
      const message = result.message || data.message || '验证失败';

      console.log('❌ 验证失败');
      console.log(`错误码: [${errorCode}]`);
      console.log(`消息:   ${message}`);

      if (suggestions[errorCode]) {
        console.log(`建议:   ${suggestions[errorCode]}`);
      }
    }
  })
  .catch(err => {
    console.error('❌ 异常:', err.message);
  });
