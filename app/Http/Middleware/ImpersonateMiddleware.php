<?php

namespace App\Http\Middleware;

use App\Http\ApiResponse;
use App\Services\ImpersonateService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 模拟登录中间件
 *
 * 检测请求头 X-Impersonate-Token，存在时根据模拟会话
 * 将当前请求的用户切换为目标用户上下文。
 *
 * 工作原理：
 * 1. 模拟令牌在校验身份前通过中间件注入
 * 2. 认证中间件验证原始 token 后，此中间件执行用户切换
 * 3. 被模拟用户的请求在控制器中可通过 request()->user() 获取
 * 4. 原始操作者可通过请求属性获取
 *
 * 用法：
 *   Route::middleware(['auth:sanctum', 'impersonate'])->group(...)
 */
class ImpersonateMiddleware
{
    public function __construct(
        protected ImpersonateService $impersonateService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Impersonate-Token');

        if (empty($token)) {
            return $next($request);
        }

        $session = $this->impersonateService->getSession($token);

        if (!$session) {
            return ApiResponse::unauthorized('模拟令牌无效或已过期');
        }

        // 注入原始操作者信息到请求属性
        $request->attributes->set('impersonator_id', $session['impersonator_id']);
        $request->attributes->set('impersonator_name', $session['impersonator_name']);
        $request->attributes->set('impersonator_email', $session['impersonator_email']);
        $request->attributes->set('is_impersonated', true);

        return $next($request);
    }
}
