<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Widget JWT 认证中间件
 *
 * 验证嵌入式 Widget 的 JWT 令牌，
 * 将客户 ID 和权限注入请求对象。
 */
class WidgetAuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken() ?? $request->query('token');

        if (!$token) {
            throw new HttpException(401, '缺少 Widget 令牌');
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new HttpException(401, 'Widget 令牌格式无效');
        }

        [$header, $payload, $signature] = $parts;

        // 验证签名
        $secret = config('app.key');
        $expectedSig = base64url_encode(hash_hmac('sha256', "{$header}.{$payload}", $secret, true));

        if (!hash_equals($expectedSig, $signature)) {
            throw new HttpException(401, 'Widget 令牌签名无效');
        }

        // 解析 payload
        $data = json_decode(base64url_decode($payload), true);
        if (!$data || !isset($data['customer_id'])) {
            throw new HttpException(401, 'Widget 令牌数据无效');
        }

        // 检查过期
        if (isset($data['exp']) && $data['exp'] < time()) {
            throw new HttpException(401, 'Widget 令牌已过期');
        }

        // 检查缓存中是否存在（撤销检测）
        $cached = Cache::get("widget_token:{$data['jti']}");
        if (!$cached) {
            throw new HttpException(401, 'Widget 令牌已被撤销');
        }

        // 注入请求
        $request->merge([
            'widget_customer_id' => $data['customer_id'],
            'widget_permissions' => $data['permissions'] ?? [],
        ]);

        return $next($request);
    }
}
