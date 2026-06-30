/**
 * HWT License SDK Client for Node.js
 *
 * 基于 M2-34 统一错误码标准的 Node.js SDK 示例
 * 对应 API: /api/license/activate, /api/license/validate, /api/license/check-feature
 */

const crypto = require('crypto');
const axios = require('axios');

class HWTClient {
  constructor(apiKey, host = 'https://api.huwutong.com') {
    this.apiKey = apiKey;
    this.host = host.replace(/\/$/, '');
    this.http = axios.create({
      timeout: 30000,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Authorization': `Bearer ${apiKey}`,
      },
    });
  }

  /**
   * 激活 License
   * POST /api/license/activate
   */
  async activate(licenseKey, deviceInfo = {}) {
    return this.post('/api/license/activate', {
      license_key: licenseKey,
      fingerprint: deviceInfo.fingerprint || '',
      components: {
        mac: deviceInfo.mac || '',
        cpu_id: deviceInfo.cpuId || '',
        disk_sn: deviceInfo.diskSn || '',
        system_uuid: deviceInfo.systemUuid || '',
      },
      platform: deviceInfo.platform || process.platform,
      metadata: deviceInfo.metadata || {},
    });
  }

  /**
   * 验证 License
   * POST /api/license/validate
   */
  async validate(licenseKey, fingerprint = '') {
    return this.post('/api/license/validate', {
      license_key: licenseKey,
      fingerprint,
    });
  }

  /**
   * 检查 Feature Flag
   * POST /api/license/check-feature
   */
  async checkFeature(licenseKey, featureCode) {
    return this.post('/api/license/check-feature', {
      license_key: licenseKey,
      feature_code: featureCode,
    });
  }

  /**
   * 查询 License 信息
   * GET /api/license/info/{key}
   */
  async getLicenseInfo(licenseKey) {
    try {
      const res = await this.http.get(`${this.host}/api/license/info/${encodeURIComponent(licenseKey)}`);
      return res.data;
    } catch (err) {
      return this._handleError(err);
    }
  }

  /**
   * 发送心跳
   * POST /api/telemetry/heartbeat
   */
  async heartbeat(licenseKey, fingerprint) {
    return this.post('/api/telemetry/heartbeat', {
      license_key: licenseKey,
      fingerprint,
      timestamp: Math.floor(Date.now() / 1000),
      sdk_version: '1.0.0',
    });
  }

  async post(path, data) {
    try {
      const headers = this._buildSignatureHeaders(data);
      const res = await this.http.post(`${this.host}${path}`, data, { headers });
      return res.data;
    } catch (err) {
      return this._handleError(err);
    }
  }

  /**
   * 构建 HMAC-SHA256 签名头
   * 排序参数 key → key=value 拼接 → HmacSHA256
   */
  _buildSignatureHeaders(data) {
    const nonce = crypto.createHash('md5').update(String(Date.now())).digest('hex').substring(0, 16);
    const timestamp = String(Math.floor(Date.now() / 1000));

    const sortedKeys = Object.keys(data).sort();
    const signStr = sortedKeys
      .map(k => `${k}=${typeof data[k] === 'object' ? JSON.stringify(data[k]) : String(data[k])}`)
      .join('');

    const signature = crypto
      .createHmac('sha256', this.apiKey)
      .update(signStr + nonce + timestamp)
      .digest('hex');

    return {
      'X-Nonce': nonce,
      'X-Timestamp': timestamp,
      'X-Signature': signature,
    };
  }

  _handleError(err) {
    if (err.response) {
      const body = err.response.data;
      const error = body.error || body;
      return {
        success: false,
        error: error.code || 'UNKNOWN_ERROR',
        message: error.message || err.message,
        statusCode: err.response.status,
      };
    }
    return {
      success: false,
      error: 'NETWORK_ERROR',
      message: err.message,
      statusCode: 0,
    };
  }
}

module.exports = { HWTClient };
