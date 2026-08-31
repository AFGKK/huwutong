<?php

namespace App\Http\Middleware;

use App\Services\TokenIntrospectionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Token 内省中间件
 *
 * 在每个认证请求上执行 Token 实时检查：
 * 1. 黑名单检查（是否被手动吊销）
 * 2. Token 版本检查（密码修改/权限变更后是否过期）
 * 3. 用户状态检查
 *
 * 使用方式: Route::middleware('auth:sanctum', 'introspect')
 */
class TokenIntrospectionMiddleware
{
    public function __construct(
        protected TokenIntrospectionService $introspectionService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $token = $user->currentAccessToken();

        if (! $token) {
            return $next($request);
        }

        $tokenId = (string) $token->getKey();

        // 1. 检查黑名单
        if ($this->introspectionService->isBlacklisted($tokenId)) {
            $token->delete();

            return response()->json([
                'success' => false,
                'message' => __('app.middleware.token_revoked'),
                'code' => 'TOKEN_REVOKED',
            ], 401);
        }

        // 2. 检查 Token 版本
        if (! $this->introspectionService->checkTokenVersion($tokenId, $user->id)) {
            $token->delete();

            return response()->json([
                'success' => false,
                'message' => __('app.middleware.token_expired_version_changed'),
                'code' => 'TOKEN_VERSION_EXPIRED',
            ], 401);
        }

        // 3. 检查用户状态
        if ($user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => __('app.middleware.account_disabled'),
                'code' => 'USER_INACTIVE',
            ], 403);
        }

        return $next($request);
    }
}
