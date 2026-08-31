<?php

namespace App\Http\Middleware;

use App\Enums\ErrorCode;
use App\Http\ApiResponse;
use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

/**
 * API Key 鉴权中间件 (M2-28: 密钥分级管理)
 *
 * 支持：
 * - 三级权限: read-only / read-write / admin
 * - 四级等级: free / standard / enterprise / custom
 * - HTTP 方法白名单 (allowed_methods)
 * - 端点白名单 (allowed_endpoints)
 * - 单 IP / 多 IP 白名单绑定
 * - Referer 白名单
 * - 总配额 / 每日配额
 * - 自动记录用量和审计日志
 *
 * 使用方式：
 *   Route::middleware('api-key:read-only')->get('/api/...', ...);
 *   Route::middleware('api-key:admin')->post('/api/api-keys', ...);
 */
class ApiKeyAuthMiddleware
{
    public function handle(Request $request, Closure $next, string $requiredLevel = 'read-only'): Response
    {
        $apiKeyValue = $request->header('X-Api-Key') ?? $request->query('api_key');

        if (empty($apiKeyValue)) {
            return ApiResponse::error(ErrorCode::API_KEY_REQUIRED, null, 401);
        }

        // 查找匹配的 key（key_id 部分: ak_xxx.secret）
        $parts = explode('.', $apiKeyValue, 2);
        $keyId = $parts[0];

        /** @var ApiKey|null $apiKey */
        $apiKey = ApiKey::where('key_id', $keyId)
            ->where('is_active', true)
            ->first();

        if (! $apiKey) {
            return ApiResponse::error(ErrorCode::API_KEY_INVALID, null, 401);
        }

        // 验证 secret
        $secret = $parts[1] ?? '';
        if (! Hash::check($secret, $apiKey->secret)) {
            return ApiResponse::error(ErrorCode::API_KEY_INVALID, null, 401);
        }

        // 检查有效期
        if ($apiKey->expires_at && now()->gt($apiKey->expires_at)) {
            return ApiResponse::error(ErrorCode::API_KEY_EXPIRED, null, 401);
        }

        // 检查 IP 绑定
        if (! $apiKey->matchesIp($request->ip())) {
            return ApiResponse::error(ErrorCode::API_KEY_IP_MISMATCH, null, 403);
        }

        // 检查 Referer
        if (! $apiKey->matchesReferrer($request->header('Referer'))) {
            return ApiResponse::errorCode(ErrorCode::FORBIDDEN, [], ['reason' => 'referer_not_allowed'], 403);
        }

        // 检查权限级别
        if (! $apiKey->hasMinimumPermission($requiredLevel)) {
            return ApiResponse::error(ErrorCode::API_KEY_INSUFFICIENT, null, 403);
        }

        // 检查方法权限
        if (! $apiKey->canMethod($request->method())) {
            return ApiResponse::error(ErrorCode::API_KEY_METHOD_DENIED, null, 403);
        }

        // 检查端点权限
        if (! $apiKey->canAccess($request->path())) {
            return ApiResponse::error(ErrorCode::API_KEY_ENDPOINT_DENIED, null, 403);
        }

        // 检查总配额
        if (! $apiKey->hasQuota()) {
            return ApiResponse::error(ErrorCode::API_KEY_QUOTA_EXCEEDED, null, 429);
        }

        // 检查每日配额
        if (! $apiKey->hasDailyQuota()) {
            return ApiResponse::error(ErrorCode::RATE_LIMITED, __('app.middleware.api_key_daily_quota_exhausted'), 429);
        }

        // 记录使用
        $apiKey->recordUsage();

        // 将 API Key 信息注入请求
        $request->merge(['_api_key' => $apiKey]);
        $request->setUserResolver(fn () => $apiKey->tenant?->users()->first());
        $request->headers->set('X-Tenant-Id', (string) $apiKey->tenant_id);

        return $next($request);
    }
}
