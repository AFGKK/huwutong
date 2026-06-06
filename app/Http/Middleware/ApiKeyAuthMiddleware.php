<?php

namespace App\Http\Middleware;

use App\Http\ApiResponse;
use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

/**
 * API Key 鉴权中间件
 *
 * 支持：
 * - 通过 X-Api-Key header 或 ?api_key 查询参数认证
 * - 按权限级别限制（read-only / read-write / admin）
 * - 按端点白名单限制（allowed_endpoints）
 * - 按 IP 绑定限制
 * - 用量配额检查
 * - 自动更新 last_used_at 和 usage_count
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
            return ApiResponse::error('API_KEY_REQUIRED', '缺少 API Key', 401);
        }

        // 查找匹配的 key（key_id 部分）
        $parts = explode('.', $apiKeyValue, 2);
        $keyId = $parts[0];

        $apiKey = ApiKey::where('key_id', $keyId)
            ->where('is_active', true)
            ->first();

        if (! $apiKey) {
            return ApiResponse::error('API_KEY_INVALID', 'API Key 无效', 401);
        }

        // 验证 secret
        $secret = $parts[1] ?? '';
        if (! Hash::check($secret, $apiKey->secret)) {
            return ApiResponse::error('API_KEY_INVALID', 'API Key 无效', 401);
        }

        // 检查有效期
        if ($apiKey->expires_at && now()->gt($apiKey->expires_at)) {
            return ApiResponse::error('API_KEY_EXPIRED', 'API Key 已过期', 401);
        }

        // 检查 IP 绑定
        if (! $apiKey->matchesIp($request->ip())) {
            return ApiResponse::error('API_KEY_IP_MISMATCH', 'API Key 不允许当前 IP', 403);
        }

        // 检查权限级别
        $levelMap = ['read-only' => 1, 'read-write' => 2, 'admin' => 3];
        $required = $levelMap[$requiredLevel] ?? 1;
        $current = $levelMap[$apiKey->permissions] ?? 1;

        if ($current < $required) {
            return ApiResponse::error('API_KEY_INSUFFICIENT', 'API Key 权限不足', 403);
        }

        // 检查方法权限
        if (! $apiKey->canMethod($request->method())) {
            return ApiResponse::error('API_KEY_METHOD_DENIED', 'API Key 不允许此 HTTP 方法', 403);
        }

        // 检查端点权限
        if (! $apiKey->canAccess($request->path())) {
            return ApiResponse::error('API_KEY_ENDPOINT_DENIED', 'API Key 不允许此端点', 403);
        }

        // 检查配额
        if (! $apiKey->hasQuota()) {
            return ApiResponse::error('API_KEY_QUOTA_EXCEEDED', 'API Key 请求配额已用完', 429);
        }

        // 记录使用
        $apiKey->increment('usage_count');
        $apiKey->update(['last_used_at' => now()]);

        // 将 API Key 信息注入请求
        $request->merge(['_api_key' => $apiKey]);
        $request->setUserResolver(fn () => $apiKey->tenant?->users()->first());
        $request->headers->set('X-Tenant-Id', (string) $apiKey->tenant_id);

        return $next($request);
    }
}
