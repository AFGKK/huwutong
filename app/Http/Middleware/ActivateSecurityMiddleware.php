<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 激活安全中间件 — 组合 Nonce + Signature 校验
 *
 * 此为路由中间件组组合，用于激活/验证等高安全端点的保护。
 */
class ActivateSecurityMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // 先执行 Nonce 校验
        $nonceMiddleware = app(NonceMiddleware::class);
        $response = $nonceMiddleware->handle($request, function ($req) use ($next) {
            // 再执行 Signature 校验
            $sigMiddleware = app(SignatureMiddleware::class);
            return $sigMiddleware->handle($req, function ($sReq) use ($next) {
                return $next($sReq);
            });
        });

        return $response;
    }
}
