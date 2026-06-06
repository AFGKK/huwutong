<?php

namespace App\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

class ApiResponse
{
    /**
     * 成功响应
     *
     * @param mixed       $data    响应数据
     * @param string      $message 成功消息
     * @param int         $code    HTTP 状态码
     * @param array       $extra   额外字段
     */
    public static function success(
        mixed  $data = null,
        string $message = '操作成功',
        int    $code = 200,
        array  $extra = [],
    ): JsonResponse {
        $response = array_filter([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ]);

        return response()->json(array_merge($response, $extra), $code);
    }

    /**
     * 创建成功响应（201）
     */
    public static function created(mixed $data = null, string $message = '创建成功'): JsonResponse
    {
        return static::success($data, $message, 201);
    }

    /**
     * 无内容响应（204）
     */
    public static function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    /**
     * 错误响应
     *
     * @param string      $code    错误码（如 LICENSE_EXPIRED）
     * @param string      $message 错误描述
     * @param int         $status  HTTP 状态码
     * @param array       $details 错误详情（字段级验证错误等）
     */
    public static function error(
        string $code = 'UNKNOWN_ERROR',
        string $message = '请求失败',
        int    $status = 400,
        array  $details = [],
    ): JsonResponse {
        $error = [
            'code' => $code,
            'message' => $message,
        ];

        if (! empty($details)) {
            $error['details'] = $details;
        }

        return response()->json([
            'success' => false,
            'error' => $error,
        ], $status);
    }

    /**
     * 验证错误响应（422）
     */
    public static function validationError(string $message = '验证失败', array $details = []): JsonResponse
    {
        return static::error('VALIDATION_ERROR', $message, 422, $details);
    }

    /**
     * 未授权响应（401）
     */
    public static function unauthorized(string $message = '未授权访问'): JsonResponse
    {
        return static::error('UNAUTHORIZED', $message, 401);
    }

    /**
     * 禁止访问（403）
     */
    public static function forbidden(string $message = '权限不足'): JsonResponse
    {
        return static::error('FORBIDDEN', $message, 403);
    }

    /**
     * 未找到响应（404）
     */
    public static function notFound(string $message = '资源不存在'): JsonResponse
    {
        return static::error('NOT_FOUND', $message, 404);
    }

    /**
     * 分页响应
     *
     * @param LengthAwarePaginator $paginator
     * @param string               $message
     * @return JsonResponse
     */
    public static function paginated(LengthAwarePaginator $paginator, string $message = '查询成功'): JsonResponse
    {
        $meta = [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $paginator->items(),
            'meta' => $meta,
        ]);
    }
}
