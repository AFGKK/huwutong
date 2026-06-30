<?php

use App\Http\ApiResponse;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

if (! function_exists('current_tenant')) {
    function current_tenant(): ?Tenant
    {
        return app()->bound(Tenant::class) ? app(Tenant::class) : null;
    }
}

if (! function_exists('api_response')) {
    /**
     * 兼容旧版 api_response() 调用
     * 新代码应使用 ApiResponse::success() / ApiResponse::error()
     */
    function api_response($data = null, int $statusCode = 200, array $headers = []): JsonResponse
    {
        if ($statusCode >= 400) {
            $message = is_array($data) ? ($data['error'] ?? $data['message'] ?? 'Error') : 'Error';
            return ApiResponse::error('REQUEST_ERROR', $message, $statusCode, $headers);
        }
        return ApiResponse::success($data, null, $statusCode, $headers);
    }
}
