/**
 * HWT License Edge Verifier — License 验证器
 *
 * 边缘节点本地验证逻辑
 * - HMAC-SHA256 Token 验证
 * - Token 有效期检查
 * - 产品代码匹配
 * - 时间偏差容忍
 *
 * @m3-53 EdgeVerifier
 */

export class Verifier {
  constructor(env) {
    this.env = env;
    this.maxTokenAge = parseInt(env.MAX_TOKEN_AGE_SECONDS || '3600');
  }

  /**
   * 本地验证 License Token
   * @param {string} licenseKey
   * @param {string} token
   * @param {string} productCode
   * @returns {Promise<{valid: boolean, data?: object, error?: string, message?: string}>}
   */
  async verifyLocal(licenseKey, token, productCode) {
    // 如果有 token，优先验证 token
    if (token) {
      return this.verifyToken(token, productCode);
    }

    // 如果有 license_key，尝试生成临时验证
    if (licenseKey) {
      return this.verifyLicenseKey(licenseKey, productCode);
    }

    return {
      valid: false,
      error: 'MISSING_CREDENTIALS',
      message: '缺少验证凭证',
    };
  }

  /**
   * 验证 JWT/HMAC Token
   */
  async verifyToken(token, productCode) {
    try {
      // 解析 token 结构: header.payload.signature
      const parts = token.split('.');
      if (parts.length !== 3) {
        return {
          valid: false,
          error: 'INVALID_TOKEN_FORMAT',
          message: 'Token 格式无效',
        };
      }

      const [headerB64, payloadB64, signatureB64] = parts;

      // 解码 payload
      let payload;
      try {
        payload = JSON.parse(atob(payloadB64));
      } catch {
        return {
          valid: false,
          error: 'INVALID_PAYLOAD',
          message: 'Token Payload 解析失败',
        };
      }

      // 检查必要字段
      if (!payload.lic_key || !payload.iat || !payload.exp) {
        return {
          valid: false,
          error: 'MISSING_FIELDS',
          message: 'Token 缺少必要字段 (lic_key/iat/exp)',
        };
      }

      // 检查过期
      const now = Math.floor(Date.now() / 1000);
      if (now > payload.exp) {
        return {
          valid: false,
          error: 'TOKEN_EXPIRED',
          message: 'Token 已过期',
          data: { expired_at: payload.exp },
        };
      }

      // 检查是否太旧
      if (now - payload.iat > this.maxTokenAge) {
        return {
          valid: false,
          error: 'TOKEN_TOO_OLD',
          message: 'Token 已超过最大有效期',
          data: { issued_at: payload.iat, max_age: this.maxTokenAge },
        };
      }

      // 验证签名 (HMAC-SHA256)
      const secret = this.env.EDGE_VERIFIER_SECRET;
      if (!secret) {
        // 没有密钥时，信任来自原始服务器的 token (回源已验证过)
        return {
          valid: true,
          data: payload,
          warning: 'no_secret_configured',
        };
      }

      const msg = `${headerB64}.${payloadB64}`;
      const key = await crypto.subtle.importKey(
        'raw',
        new TextEncoder().encode(secret),
        { name: 'HMAC', hash: 'SHA-256' },
        false,
        ['verify'],
      );

      const expectedSig = await crypto.subtle.sign(
        'HMAC',
        key,
        new TextEncoder().encode(msg),
      );

      const expectedSigB64 = btoa(String.fromCharCode(...new Uint8Array(expectedSig)));

      if (signatureB64 !== expectedSigB64) {
        return {
          valid: false,
          error: 'SIGNATURE_MISMATCH',
          message: 'Token 签名验证失败',
        };
      }

      // 产品代码检查
      if (productCode && payload.product_code && payload.product_code !== productCode) {
        return {
          valid: false,
          error: 'PRODUCT_MISMATCH',
          message: `产品代码不匹配: 期望 ${productCode}, 实际 ${payload.product_code}`,
        };
      }

      // 验证通过
      return {
        valid: true,
        data: {
          license_key: payload.lic_key,
          product_code: payload.product_code,
          customer: payload.customer,
          type: payload.type,
          issued_at: payload.iat,
          expires_at: payload.exp,
          seats: payload.seats,
          metadata: payload.metadata || {},
        },
      };
    } catch (err) {
      return {
        valid: false,
        error: 'VERIFY_ERROR',
        message: `Token 验证异常: ${err.message}`,
      };
    }
  }

  /**
   * 直接验证 License Key (边缘快速校验)
   * 通过 key 前缀 + 校验和快速判断
   */
  async verifyLicenseKey(licenseKey, productCode) {
    // License Key 格式校验
    if (!licenseKey || licenseKey.length < 16) {
      return {
        valid: false,
        error: 'INVALID_KEY_FORMAT',
        message: 'License Key 格式无效',
      };
    }

    // 检查产品代码前缀
    if (productCode) {
      const prefixMap = {
        'ENT': 'enterprise',
        'PRO': 'professional',
        'TRIAL': 'trial',
        'DEV': 'developer',
      };

      const keyPrefix = licenseKey.substring(0, 3);
      const expectedPrefix = Object.entries(prefixMap)
        .find(([, code]) => code === productCode)?.[0];

      if (expectedPrefix && keyPrefix !== expectedPrefix) {
        return {
          valid: false,
          error: 'PREFIX_MISMATCH',
          message: `License Key 前缀 ${keyPrefix} 不匹配产品 ${productCode}`,
        };
      }
    }

    // 快速校验和验证 (CRC32-like)
    const checksum = this.quickChecksum(licenseKey);
    if (!checksum.valid) {
      return {
        valid: false,
        error: 'CHECKSUM_FAILED',
        message: 'License Key 校验和验证失败',
      };
    }

    // 基础格式通过，但需要回源获取完整状态
    return {
      valid: true,
      data: {
        license_key: licenseKey,
        verified: 'partial',
        note: '边缘快速校验通过，完整验证需回源',
      },
      partial: true,
    };
  }

  /**
   * 解码 Token 信息（不验证签名）
   */
  decodeToken(token) {
    try {
      const parts = token.split('.');
      if (parts.length !== 3) {
        return { valid: false, error: '格式无效' };
      }

      const payload = JSON.parse(atob(parts[1]));

      return {
        valid: true,
        payload,
        header: JSON.parse(atob(parts[0])),
        expires_in: payload.exp ? payload.exp - Math.floor(Date.now() / 1000) : null,
      };
    } catch (err) {
      return {
        valid: false,
        error: err.message,
      };
    }
  }

  /**
   * 快速校验和
   */
  quickChecksum(key) {
    let hash = 0;
    for (let i = 0; i < key.length; i++) {
      const char = key.charCodeAt(i);
      hash = ((hash << 5) - hash) + char;
      hash = hash & hash; // Convert to 32bit integer
    }

    // 最后 4 位作为简单校验
    const last4 = key.slice(-4);
    const checkDigits = (Math.abs(hash) % 10000).toString().padStart(4, '0');

    return {
      valid: last4 === checkDigits,
      computed: checkDigits,
      actual: last4,
    };
  }
}
