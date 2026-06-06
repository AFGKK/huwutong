<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 全局资源写入保护中间件
 *
 * 对白名单资源的写入操作进行检查，仅允许 super-admin 和 admin 角色。
 * 需在路由或控制器级别应用于白名单资源的写入端点。
 */
class GlobalResourceWriteProtection
{
    /**
     * 处理请求
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 仅拦截写入请求
        if (! $request->isMethod('post') && ! $request->isMethod('put')
            && ! $request->isMethod('patch') && ! $request->isMethod('delete')) {
            return $next($request);
        }

        // 检查是否有写入权限
        if (! GlobalResourceWhitelist::canWrite()) {
            return response()->json([
                'success' => false,
                'error_code' => 'FORBIDDEN_WRITE',
                'message' => '无权限修改全局资源，仅超管和管理员可执行此操作',
            ], 403);
        }

        return $next($request);
    }
}
