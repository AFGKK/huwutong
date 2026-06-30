<?php

namespace App\Http\Middleware;

use App\Http\ApiResponse;
use App\Services\EnhancedRateLimiter;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * 增强版 API 限流中间件（应用层——业务限流）
 *
 * 注意：按 M0-11 ADR，此中间件仅处理按租户/API 分级的业务限流。
 * 全局限流（IP 黑名单/CC 防护/硬限制）由网关层 Kong/APISIX 负责。
 * 两层级不冲突，共同作用：
 * - 网关层：硬限制（防止 DDoS/IP 滥用）
 * - 应用层：软限制（按业务规则精细化限流）
 *
 * 支持多级限流组合（IP + 产品 + 租户 + 路径）
 * 在 `rate_limit` 全局中间件基础上增加产品/租户维度
 *
 * 使用方式：
 * 1. 默认规则集: Route::middleware('throttle.enhanced')->post(...)
 * 2. 指定规则集: Route::middleware('throttle.enhanced:activate')->post(...)
 * 3. 自定义规则: Route::middleware('throttle.enhanced:ip,60,60|license,30,60')->post(...)
 */
class EnhancedThrottleMiddleware
{
    public function handle(Request $request, Closure $next, string $rules = 'default'): Response
    {
        $limiter = app(EnhancedRateLimiter::class);

        // 解析规则（支持 slug 名称或内联规则字符串）
        $ruleSet = $this->parseRules($rules);

        $result = $limiter->check($request, $ruleSet);

        // 记录限流统计
        $ruleSlug = is_string($rules) ? $rules : 'custom';
        if (! $result['allowed']) {
            $limiter->recordStat($ruleSlug, $request->ip() ?? 'unknown', true);
            Log::warning('增强限流触发', [
                'rule' => $ruleSlug,
                'ip' => $request->ip(),
                'path' => $request->path(),
                'retry_after' => $result['retry_after'],
            ]);
        }

        $response = $result['allowed']
            ? $next($request)
            : ApiResponse::error(
                'RATE_LIMIT_EXCEEDED',
                '请求过于频繁，请稍后再试',
                429,
                [
                    'retry_after_seconds' => $result['retry_after'],
                ],
            );

        // 添加限流响应头
        if ($response instanceof Response) {
            foreach ($result['headers'] as $header => $value) {
                $response->headers->set($header, $value);
            }
        }

        return $response;
    }

    /**
     * 解析规则字符串或返回规则集名称
     *
     * 格式:
     * - 'activate' → 使用预定义规则集或 DB 规则
     * - 'ip,60,60|license,30,60' → key_type, max_attempts, window_seconds
     */
    protected function parseRules(string $rules): array|string
    {
        // 如果包含 | 或 , 说明是内联规则字符串
        if (str_contains($rules, '|') || str_contains($rules, ',')) {
            return $this->parseInlineRules($rules);
        }

        // 否则当作 slug 传给 limiter，由 limiter 自行加载 DB 规则或回退
        return $rules;
    }

    /**
     * 解析内联规则
     */
    protected function parseInlineRules(string $rules): array
    {
        $parsed = [];
        $segments = explode('|', $rules);
        foreach ($segments as $segment) {
            $parts = explode(',', $segment);
            if (count($parts) >= 3) {
                $parsed[] = [
                    'key_type' => trim($parts[0]),
                    'max_attempts' => (int) trim($parts[1]),
                    'window_seconds' => (int) trim($parts[2]),
                ];
            }
        }

        return $parsed;
    }
}
