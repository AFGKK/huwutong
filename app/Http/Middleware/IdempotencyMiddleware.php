<?php

namespace App\Http\Middleware;

use App\Http\ApiResponse;
use App\Services\IdempotencyService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyMiddleware
{
    public function __construct(
        protected IdempotencyService $idempotencyService,
    ) {}

    /**
     * 处理幂等性请求
     *
     * 客户端在 POST/PUT/PATCH 请求头中传入 Idempotency-Key
     * 如果该 key 已处理过，直接返回缓存的结果
     * 否则处理请求并缓存结果
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 仅对 POST/PUT/PATCH 方法生效
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH'])) {
            return $next($request);
        }

        $idempotencyKey = $request->header('Idempotency-Key');

        // 如果没有传入 key，直接放行（幂等性可选）
        if (empty($idempotencyKey)) {
            return $next($request);
        }

        // 验证 key 格式
        if (! $this->idempotencyService->isValidKey($idempotencyKey)) {
            return ApiResponse::validationError('Idempotency-Key 格式无效，需为 UUID v4', [
                'idempotency_key' => ['格式无效，应为 UUID v4（如 550e8400-e29b-41d4-a716-446655440000）'],
            ]);
        }

        // 检查是否已处理过
        $cachedResult = $this->idempotencyService->get($idempotencyKey);
        if ($cachedResult !== null) {
            // 直接返回缓存的结果（相同的 HTTP 状态码）
            return response()->json(
                $cachedResult['body'],
                $cachedResult['status'],
                ['X-Idempotency-Replayed' => 'true'],
            );
        }

        // 处理请求
        $response = $next($request);

        // 只缓存成功的响应（2xx）
        if ($response->isSuccessful() || $response->isRedirection()) {
            $this->idempotencyService->save(
                $idempotencyKey,
                [
                    'status' => $response->getStatusCode(),
                    'body' => json_decode($response->getContent(), true),
                ],
            );
        }

        return $response;
    }
}
