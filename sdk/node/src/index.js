'use strict';

const axios = require('axios');

const VERSION = '1.0.0';

class ApiError extends Error {
  constructor(code, message, statusCode = 400, details = {}) {
    super(`[${code}] ${message}`);
    this.name = 'ApiError';
    this.errorCode = code;
    this.statusCode = statusCode;
    this.details = details;
  }
}

class ActivationResult {
  constructor(data = {}) {
    this.success = data.success ?? false;
    this.licenseKey = data.license_key ?? '';
    this.expiresAt = data.expires_at ?? '';
    this.features = data.features ?? [];
    this.message = data.message ?? '';
  }
}

class ValidationResult {
  constructor(data = {}) {
    this.isValid = data.is_valid ?? false;
    this.licenseKey = data.license_key ?? '';
    this.status = data.status ?? '';
    this.expiresAt = data.expires_at ?? '';
    this.machineId = data.machine_id ?? '';
    this.features = data.features ?? [];
    this.message = data.message ?? '';
  }
}

class Client {
  constructor(apiKey, host = 'https://api.huwutong.com') {
    this.apiKey = apiKey;
    this.host = host.replace(/\/+$/, '');
    this.http = axios.create({
      baseURL: this.host,
      timeout: 10000,
      headers: {
        'User-Agent': `HWT-SDK-Node/${VERSION}`,
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
    });
  }

  async activate(licenseKey, machineInfo) {
    const res = await this._call('POST', '/api/activate', {
      license_key: licenseKey,
      machine_info: machineInfo,
    });
    return new ActivationResult(res.data?.data || res.data);
  }

  async validate(licenseKey, context = {}) {
    const res = await this._call('POST', '/api/validate', {
      license_key: licenseKey,
      context,
    });
    return new ValidationResult(res.data?.data || res.data);
  }

  async deactivate(licenseKey, deviceId = '') {
    const res = await this._call('POST', '/api/deactivate', {
      license_key: licenseKey,
      device_id: deviceId,
    });
    const data = res.data?.data || res.data;
    return data.success ?? false;
  }

  async offlineVerify(licenseKey, deviceId) {
    const res = await this._call('POST', '/api/offline/verify', {
      license_key: licenseKey,
      device_id: deviceId,
    });
    return res.data?.data || res.data;
  }

  async checkFeature(licenseKey, feature) {
    const res = await this._call('GET', '/api/check-feature', {
      license_key: licenseKey,
      feature,
    });
    const data = res.data?.data || res.data;
    return data.available ?? false;
  }

  async getLicense(licenseKey) {
    const res = await this._call('GET', `/api/licenses/${encodeURIComponent(licenseKey)}`);
    return res.data?.data || res.data;
  }

  async _call(method, path, params = {}) {
    const config = {
      headers: { Authorization: `Bearer ${this.apiKey}` },
    };
    if (method === 'GET') {
      config.params = params;
    }
    const res = await this.http.request({ method, url: path, data: method !== 'GET' ? params : undefined, ...config });
    if (res.data?.error) {
      throw new ApiError(res.data.code || 'UNKNOWN', res.data.message || 'Unknown error', res.status);
    }
    return res;
  }
}

module.exports = { Client, ApiError, ActivationResult, ValidationResult };
