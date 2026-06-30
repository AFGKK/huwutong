/**
 * HWT License Edge Verifier — KV 缓存管理器
 *
 * 使用 Cloudflare KV 缓存验证结果
 * - Token 验证结果缓存 (5 分钟 TTL)
 * - 吊销列表缓存 (1 小时 TTL)
 * - 公钥缓存 (1 天 TTL)
 *
 * @m3-53 EdgeVerifier
 */

export class CacheManager {
  constructor(env) {
    this.kv = env.HWT_CACHE;
    this.defaultTtl = 300; // 5分钟
  }

  /**
   * 获取缓存
   * @param {string} key
   * @returns {Promise<object|null>}
   */
  async get(key) {
    if (!this.kv) return null;

    try {
      const value = await this.kv.get(key, 'json');
      if (!value) return null;

      // 检查 TTL
      if (value.cached_at && value.ttl) {
        const age = (Date.now() - value.cached_at) / 1000;
        if (age > value.ttl) {
          await this.kv.delete(key).catch(() => {});
          return null;
        }
      }

      return value;
    } catch {
      return null;
    }
  }

  /**
   * 设置缓存
   * @param {string} key
   * @param {object} value
   * @param {number} ttl 秒
   */
  async set(key, value, ttl = this.defaultTtl) {
    if (!this.kv) return;

    try {
      await this.kv.put(key, JSON.stringify({
        ...value,
        cached_at: Date.now(),
        ttl,
      }), { expirationTtl: ttl });
    } catch {
      // 静默失败，不影响主流程
    }
  }

  /**
   * 删除缓存
   */
  async delete(key) {
    if (!this.kv) return;
    try {
      await this.kv.delete(key);
    } catch {
      // 静默
    }
  }

  /**
   * 批量获取
   */
  async getMany(keys) {
    if (!this.kv) return keys.map(() => null);
    return Promise.all(keys.map(k => this.get(k)));
  }

  /**
   * 缓存吊销列表
   */
  async getRevocationList() {
    return this.get('global:crl');
  }

  /**
   * 更新吊销列表缓存
   */
  async setRevocationList(crl) {
    await this.set('global:crl', crl, 3600); // 1小时
  }

  /**
   * 缓存公钥
   */
  async getPublicKey(keyVersion) {
    return this.get(`key:${keyVersion}`);
  }

  /**
   * 更新公钥缓存
   */
  async setPublicKey(keyVersion, publicKey) {
    await this.set(`key:${keyVersion}`, { public_key: publicKey }, 86400); // 1天
  }
}
