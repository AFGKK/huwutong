<?php

namespace App\Http\Middleware;

use App\Enums\ErrorCode;
use App\Http\ApiResponse;
use App\Models\ApiKey;
use App\Services\FineGrainedApiKeyService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 细粒度 API Key 端点权限验证中间件 (M2-138)
 *
 * 升级自 ApiKeyAuthMiddleware，增加端点级细粒度权限验证
 *
 * 支持：
 * - 端点级权限 (activate/validate/revoke/check)
 * - HTTP 方法级权限
 * - IP 白名单绑定
 * - 有效期检查（精确到小时）
 * - 用量配额检查
 *
 * 使用方式：
 *   Route::middleware('finer-grained-api-key:activate,POST')->get('/api/activate', ...);
 *   Route::middleware('finer-grained-api-key:validate,GET')->get('/api/validate', ...);
 */
class FineGrainedApiKeyMiddleware
{
    protected FineGrainedApiKeyService $fineGrainedService;

    public function __construct(FineGrainedApiKeyService $fineGrainedService)
    {
        $this->fineGrainedService = $fineGrainedService;
    }

    public function handle(Request $request, Closure $next, string $endpoint = '', string $method = ''): Response
    {
        $apiKeyValue = $request->header('X-Api-Key') ?? $request->query('api_key');

        if (empty($apiKeyValue)) {
            return ApiResponse::error(ErrorCode::API_KEY_REQUIRED, null, 401);
        }

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
        if (! \Illuminate\Support\Facades\Hash::check($secret, $apiKey->secret)) {
            return ApiResponse::error(ErrorCode::API_KEY_INVALID, null, 401);
        }

        // 检查有效期（精确到小时）
        if ($apiKey->expires_at && now()->gt($apiKey->expires_at)) {
            return ApiResponse::error(ErrorCode::API_KEY_EXPIRED, null, 401);
        }

        // 检查 IP 绑定
        if (! $apiKey->matchesIp($request->ip())) {
            return ApiResponse::error(ErrorCode::API_KEY_IP_MISMATCH, null, 403);
        }

        // 检查 Referer
        if (! $apiKey->matchesReferrer($request->header('Referer'))) {
            return ApiResponse::error(ErrorCode::FORBIDDEN, __('app.middleware.referer_not_allowed'), 403);
        }

        // 检查端点级细粒度权限
        $targetEndpoint = $endpoint ?: $request->path();
        $targetMethod = $method ?: $request->method();

        $accessCheck = $this->fineGrainedService->checkEndpointAccess(
            $apiKey,
            $targetEndpoint,
            $targetMethod
        );

        if (! $accessCheck['allowed']) {
            return ApiResponse::error(
                ErrorCode::API_KEY_ENDPOINT_DENIED,
                $accessCheck['reason'],
                403
            );
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

        // 将 API Key 信息和端点权限注入请求
        $request->merge([
            '_api_key' => $apiKey,
            '_endpoint_permissions' => $this->fineGrainedService->getKeyEndpointPermissions($apiKey),
        ]);
        $request->setUserResolver(fn () => $apiKey->tenant?->users()->first());
        $request->headers->set('X-Tenant-Id', (string) $apiKey->tenant_id);

        return $next($request);
    }
}
