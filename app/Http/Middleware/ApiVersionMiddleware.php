<?php

namespace App\Http\Middleware;

use App\Http\ApiResponse;
use App\Models\ApiVersion;
use App\Services\ApiVersionManagerService;
use Closure;
use Illuminate\Http\Request;

/**
 * API 版本管理中间件
 *
 * 功能：
 *  - 从 URL 路径提取版本号（/api/v1/... /api/v2/...）
 *  - 检查版本是否可用/已废弃/已退役
 *  - 废弃版本添加 Sunset/Deprecation 响应头
 *  - 记录版本调用统计
 *  - 无版本号请求自动分配默认版本
 */
class ApiVersionMiddleware
{
    public function __construct(
        protected ApiVersionManagerService $versionManager,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $path = $request->path();

        // 跳过非 API 路径、健康探针（不应依赖 DB）
        if (! str_starts_with($path, 'api/')) {
            return $next($request);
        }

        if (preg_match('#^api/health/(live|ready|status)$#', $path)) {
            return $next($request);
        }

        // 测试环境或无 API 版本记录时，自动跳过版本检查
        try {
            if (ApiVersion::count() === 0) {
                $request->attributes->set('api_version', 'v1');
                $request->attributes->set('api_version_id', null);

                return $next($request);
            }
        } catch (\Throwable) {
            $request->attributes->set('api_version', 'v1');
            $request->attributes->set('api_version_id', null);

            return $next($request);
        }

        $result = $this->versionManager->checkRequestVersion($path);

        if (!$result['available']) {
            $statusCode = $result['status_code'] ?? 404;
            $message = $result['message'] ?? 'API version not available';

            return ApiResponse::error('API_VERSION_UNAVAILABLE', $message, $statusCode);
        }

        /** @var ApiVersion $version */
        $version = $result['version'];

        // 设置请求属性
        $request->attributes->set('api_version', $version->version);
        $request->attributes->set('api_version_id', $version->id);

        $response = $next($request);

        // 添加版本响应头（已废弃版本的警告）
        foreach ($result['headers'] as $key => $value) {
            $response->headers->set($key, $value);
        }

        // 后台记录调用（fire-and-forget, 不阻塞）
        if ($response->isSuccessful() || $response->isClientError()) {
            try {
                $tenantId = $request->user()?->tenant_id
                    ?? $request->attributes->get('tenant_id');

                $this->versionManager->recordCall($version, $request, $tenantId);
            } catch (\Throwable $e) {
                // Silently fail - don't break the request for analytics
            }
        }

        return $response;
    }
}
