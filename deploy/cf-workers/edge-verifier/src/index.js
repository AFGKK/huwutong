/**
 * HWT License Edge Verifier — Cloudflare Worker
 *
 * 边缘授权验证 (<10ms 全球 200+ 节点)
 * - 边缘缓存 License 验证 Token
 * - 缓存穿透时回源验证
 * - 回源降级兜底 (fallback degraded mode)
 *
 * @m3-53 EdgeVerifier
 */

import { Verifier } from './verifier';
import { CacheManager } from './cache';
import { FallbackHandler } from './fallback';
import { RateLimiter } from './rate-limiter';

export default {
  async fetch(request, env, ctx) {
    const startTime = Date.now();
    const url = new URL(request.url);
    const path = url.pathname;

    // CORS headers
    const corsHeaders = {
      'Access-Control-Allow-Origin': '*',
      'Access-Control-Allow-Methods': 'GET, POST, OPTIONS',
      'Access-Control-Allow-Headers': 'Content-Type, Authorization, X-Edge-Token',
    };

    // Handle preflight
    if (request.method === 'OPTIONS') {
      return new Response(null, { status: 204, headers: corsHeaders });
    }

    // ─── 路由分发 ───
    try {
      // POST /api/edge/verify — 验证 License Token
      if (path === '/api/edge/verify' && request.method === 'POST') {
        return await handleVerify(request, env, ctx, startTime, corsHeaders);
      }

      // POST /api/edge/token — 解析 Token 信息
      if (path === '/api/edge/token' && request.method === 'POST') {
        return await handleTokenInfo(request, env, startTime, corsHeaders);
      }

      // GET /api/edge/health — 健康检查
      if (path === '/api/edge/health') {
        return handleHealth(env, startTime, corsHeaders);
      }

      // 404
      return new Response(JSON.stringify({
        success: false,
        error: 'NOT_FOUND',
        message: 'Endpoint not found',
        edge_region: request.cf?.region || 'unknown',
      }), {
        status: 404,
        headers: { ...corsHeaders, 'Content-Type': 'application/json' },
      });
    } catch (err) {
      console.error(`Edge error: ${err.message}`, err.stack);

      return new Response(JSON.stringify({
        success: false,
        error: 'INTERNAL_ERROR',
        message: '边缘验证服务异常',
        edge_region: request.cf?.region || 'unknown',
      }), {
        status: 500,
        headers: { ...corsHeaders, 'Content-Type': 'application/json' },
      });
    }
  },
};

/**
 * 处理 License 验证请求
 */
async function handleVerify(request, env, ctx, startTime, corsHeaders) {
  // 1. 限流检查
  const rateLimiter = new RateLimiter(env);
  const clientIp = request.headers.get('CF-Connecting-IP') || 'unknown';
  const rateResult = await rateLimiter.check(clientIp);

  if (!rateResult.allowed) {
    return new Response(JSON.stringify({
      success: false,
      error: 'RATE_LIMITED',
      message: '请求频率超限，请稍后重试',
      retry_after: rateResult.retryAfter,
      edge_region: request.cf?.region || 'unknown',
    }), {
      status: 429,
      headers: {
        ...corsHeaders,
        'Content-Type': 'application/json',
        'Retry-After': String(rateResult.retryAfter),
        'X-RateLimit-Limit': String(rateResult.limit),
        'X-RateLimit-Remaining': String(rateResult.remaining),
      },
    });
  }

  // 2. 解析请求体
  let body;
  try {
    body = await request.json();
  } catch {
    return new Response(JSON.stringify({
      success: false,
      error: 'INVALID_BODY',
      message: '请求体必须是 JSON 格式',
    }), {
      status: 400,
      headers: { ...corsHeaders, 'Content-Type': 'application/json' },
    });
  }

  const { license_key, token, product_code } = body;

  if (!license_key && !token) {
    return new Response(JSON.stringify({
      success: false,
      error: 'MISSING_PARAMS',
      message: '请提供 license_key 或 token',
    }), {
      status: 400,
      headers: { ...corsHeaders, 'Content-Type': 'application/json' },
    });
  }

  // 3. 实例化验证器
  const verifier = new Verifier(env);
  const cache = new CacheManager(env);
  const cacheKey = `verify:${token || license_key}`;

  // 4. 尝试从缓存读取
  const cached = await cache.get(cacheKey);
  if (cached) {
    const elapsed = Date.now() - startTime;

    return new Response(JSON.stringify({
      success: true,
      valid: cached.valid,
      data: cached.data,
      cached: true,
      ttl: cached.ttl,
      edge_region: request.cf?.region || 'unknown',
      colo: request.cf?.colo || 'unknown',
      latency_ms: elapsed,
    }), {
      status: 200,
      headers: {
        ...corsHeaders,
        'Content-Type': 'application/json',
        'X-Cache': 'HIT',
        'X-Edge-Latency': `${elapsed}ms`,
      },
    });
  }

  // 5. 本地验证 Token
  const result = await verifier.verifyLocal(license_key, token, product_code);

  if (result.valid) {
    // 写入缓存
    const ttl = parseInt(env.EDGE_CACHE_TTL || '300');
    await cache.set(cacheKey, result, ttl);
    ctx.waitUntil(cache.set(cacheKey, result, ttl));

    const elapsed = Date.now() - startTime;

    return new Response(JSON.stringify({
      success: true,
      valid: true,
      data: result.data,
      cached: false,
      edge_region: request.cf?.region || 'unknown',
      colo: request.cf?.colo || 'unknown',
      latency_ms: elapsed,
    }), {
      status: 200,
      headers: {
        ...corsHeaders,
        'Content-Type': 'application/json',
        'X-Cache': 'MISS',
        'X-Edge-Latency': `${elapsed}ms`,
      },
    });
  }

  // 6. 本地验证失败 → 回源验证
  const fallback = new FallbackHandler(env);
  const fallbackResult = await fallback.verify(request, body);

  if (fallbackResult.valid) {
    const elapsed = Date.now() - startTime;

    return new Response(JSON.stringify({
      success: true,
      valid: true,
      data: fallbackResult.data,
      origin: fallbackResult.origin,
      cached: false,
      edge_region: request.cf?.region || 'unknown',
      colo: request.cf?.colo || 'unknown',
      latency_ms: elapsed,
    }), {
      status: 200,
      headers: {
        ...corsHeaders,
        'Content-Type': 'application/json',
        'X-Cache': 'MISS',
        'X-Origin': fallbackResult.origin || 'unknown',
        'X-Edge-Latency': `${elapsed}ms`,
      },
    });
  }

  // 7. 验证失败
  const elapsed = Date.now() - startTime;

  return new Response(JSON.stringify({
    success: true,
    valid: false,
    error: fallbackResult.error || result.error || 'VERIFICATION_FAILED',
    message: fallbackResult.message || result.message || 'License 验证失败',
    edge_region: request.cf?.region || 'unknown',
    colo: request.cf?.colo || 'unknown',
    latency_ms: elapsed,
  }), {
    status: 200, // 业务错误用 200，让客户端判断 valid 字段
    headers: {
      ...corsHeaders,
      'Content-Type': 'application/json',
      'X-Cache': 'MISS',
      'X-Edge-Latency': `${elapsed}ms`,
    },
  });
}

/**
 * 处理 Token 信息查询
 */
async function handleTokenInfo(request, env, startTime, corsHeaders) {
  let body;
  try {
    body = await request.json();
  } catch {
    return new Response(JSON.stringify({
      success: false,
      error: 'INVALID_BODY',
      message: '请求体必须是 JSON 格式',
    }), {
      status: 400,
      headers: { ...corsHeaders, 'Content-Type': 'application/json' },
    });
  }

  const { token } = body;
  if (!token) {
    return new Response(JSON.stringify({
      success: false,
      error: 'MISSING_TOKEN',
      message: '请提供 token',
    }), {
      status: 400,
      headers: { ...corsHeaders, 'Content-Type': 'application/json' },
    });
  }

  const verifier = new Verifier(env);
  const info = verifier.decodeToken(token);

  return new Response(JSON.stringify({
    success: true,
    ...info,
    edge_region: request.cf?.region || 'unknown',
    colo: request.cf?.colo || 'unknown',
    latency_ms: Date.now() - startTime,
  }), {
    status: 200,
    headers: { ...corsHeaders, 'Content-Type': 'application/json' },
  });
}

/**
 * 健康检查
 */
function handleHealth(env, startTime, corsHeaders) {
  return new Response(JSON.stringify({
    success: true,
    status: 'healthy',
    version: '1.0.0',
    edge_region: 'unknown',
    colo: 'unknown',
    cache_available: !!env.HWT_CACHE,
    timestamp: new Date().toISOString(),
    latency_ms: Date.now() - startTime,
  }), {
    status: 200,
    headers: { ...corsHeaders, 'Content-Type': 'application/json' },
  });
}
