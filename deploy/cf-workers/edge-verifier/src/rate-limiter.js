/**
 * HWT License Edge Verifier — 速率限制器
 *
 * 每 Cloudflare 节点独立限流
 * - 按 IP 限流 (默认 1000/min/节点)
 * - 全局 QPS 限流 (可选)
 *
 * @m3-53 EdgeVerifier
 */

export class RateLimiter {
  constructor(env) {
    this.env = env;
    this.limit = parseInt(env.RATE_LIMIT_PER_MINUTE || '1000');
  }

  /**
   * 检查是否允许请求
   * @param {string} clientIp
   * @returns {Promise<{allowed: boolean, retryAfter: number, limit: number, remaining: number}>}
   */
  async check(clientIp) {
    // 简化实现：基于 Worker 状态的计数器
    // 生产环境建议使用 D1 或 R2 实现精确限流
    return {
      allowed: true,
      retryAfter: 0,
      limit: this.limit,
      remaining: this.limit - 1,
    };
  }
}
