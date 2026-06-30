/**
 * HWT License SDK for Electron / Node.js (M2-21)
 *
 * 基于统一错误码标准 M2-34
 * 支持: activate / validate / deactivate / checkFeature / getOfflineLicense / verifyOffline
 */

const crypto = require('crypto');
const fetch = require('node-fetch');

class HwtApiError extends Error {
  constructor(code, message, status = 400) {
    super(`[${code}] ${message}`);
    this.name = 'HwtApiError';
    this.code = code;
    this.status = status;
  }
}

class HwtNetworkError extends Error {
  constructor(message) {
    super(`Network error: ${message}`);
    this.name = 'HwtNetworkError';
  }
}

class HwtClient {
  constructor(options = {}) {
    this.apiKey = options.apiKey;
    this.host = (options.host || 'https://api.huwutong.com').replace(/\/+$/, '');
    this.secretKey = options.secretKey || '';
    this.timeout = options.timeout || 30000;
  }

  // ── Public API ──

  /** 激活 License */
  async activate(licenseKey, deviceInfo = {}) {
    return this._post('/api/license/activate', {
      license_key: licenseKey,
      device_info: deviceInfo,
      timestamp: Math.floor(Date.now() / 1000),
    });
  }

  /** 验证 License */
  async validate(licenseKey, deviceInfo = {}) {
    const res = await this._post('/api/license/validate', {
      license_key: licenseKey,
      device_info: deviceInfo,
      timestamp: Math.floor(Date.now() / 1000),
    });
    return {
      ...res,
      isValid: res.success && res.data?.is_valid === true,
    };
  }

  /** 停用 License */
  async deactivate(licenseKey, deviceInfo = {}) {
    return this._post('/api/license/deactivate', {
      license_key: licenseKey,
      device_info: deviceInfo,
      timestamp: Math.floor(Date.now() / 1000),
    });
  }

  /** 检查 Feature Flag */
  async checkFeature(licenseKey, featureKey) {
    return this._post('/api/license/check-feature', {
      license_key: licenseKey,
      feature: featureKey,
      timestamp: Math.floor(Date.now() / 1000),
    });
  }

  /** 获取离线 License */
  async getOfflineLicense(licenseKey) {
    return this._post('/api/offline/generate', {
      license_key: licenseKey,
      timestamp: Math.floor(Date.now() / 1000),
    });
  }

  /** 验证离线 License */
  async verifyOffline(licenseData) {
    return this._post('/api/offline/verify', {
      license_data: licenseData,
      timestamp: Math.floor(Date.now() / 1000),
    });
  }

  // ── Internal ──

  async _post(path, payload) {
    const url = `${this.host}${path}`;
    const body = JSON.stringify(payload);

    const headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'Authorization': `Bearer ${this.apiKey}`,
    };

    if (this.secretKey) {
      const nonce = crypto.randomBytes(16).toString('hex');
      const timestamp = Math.floor(Date.now() / 1000).toString();
      const signature = this._hmac(body, nonce, timestamp);
      headers['X-Nonce'] = nonce;
      headers['X-Timestamp'] = timestamp;
      headers['X-Signature'] = signature;
    }

    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), this.timeout);

    try {
      const response = await fetch(url, {
        method: 'POST',
        headers,
        body,
        signal: controller.signal,
      });

      const result = await response.json();

      if (result.error) {
        throw new HwtApiError(
          result.error.code || 'UNKNOWN',
          result.error.message || 'Unknown error',
          response.status
        );
      }

      return result;
    } catch (err) {
      if (err instanceof HwtApiError) throw err;
      if (err.name === 'AbortError') {
        throw new HwtNetworkError('Request timeout');
      }
      throw new HwtNetworkError(err.message);
    } finally {
      clearTimeout(timer);
    }
  }

  _hmac(body, nonce, timestamp) {
    const data = `${body}${nonce}${timestamp}`;
    return crypto.createHmac('sha256', this.secretKey)
      .update(data)
      .digest('hex');
  }
}

module.exports = { HwtClient, HwtApiError, HwtNetworkError };
