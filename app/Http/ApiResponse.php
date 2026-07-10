<?php

namespace App\Http;

use App\Enums\ErrorCode;
use App\Services\ErrorCodeService;
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
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];

        // 仅移除 null 的 data（保留空数组）
        if (is_null($response['data'])) {
            unset($response['data']);
        }

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
     * @param ErrorCode|string $code    标准化错误码或字符串
     * @param string|null      $message 错误描述（null 时自动从多语言获取）
     * @param int              $status  HTTP 状态码
     * @param array            $details 错误详情（字段级验证错误等）
     * @param array            $replace 消息占位符替换参数
     */
    public static function error(
        ErrorCode|string $code = 'UNKNOWN_ERROR',
        ?string $message = null,
        int    $status = 400,
        array  $details = [],
        array  $replace = [],
    ): JsonResponse {
        // 解析错误码
        $errorCode = $code instanceof ErrorCode ? $code : ErrorCode::tryFrom($code);

        // 自动获取消息（优先传入的 message，其次自动翻译）
        if ($message === null && $errorCode !== null) {
            /** @var ErrorCodeService $svc */
            $svc = app(ErrorCodeService::class);
            $message = $svc->message($errorCode, $replace);
        }

        $message ??= '请求失败';

        $error = [
            'code' => $errorCode?->value ?? (string) $code,
            'message' => $message,
            'retry_safe' => $errorCode?->isRetrySafe() ?? false,
            'domain' => $errorCode?->domain() ?? 'UNKNOWN',
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
     * 使用标准化错误码响应
     *
     * @param ErrorCode          $code     标准化错误码枚举
     * @param array              $replace  消息占位符
     * @param array              $details  额外详情
     * @param int|null           $status   HTTP 状态码（默认使用枚举定义）
     */
    public static function errorCode(
        ErrorCode $code,
        array $replace = [],
        array $details = [],
        ?int $status = null,
    ): JsonResponse {
        return static::error(
            $code,
            null,
            $status ?? $code->httpStatus(),
            $details,
            $replace,
        );
    }

    /**
     * 验证错误响应（422）
     */
    public static function validationError(?string $message = null, array $details = []): JsonResponse
    {
        return static::errorCode(ErrorCode::VALIDATION_ERROR, [], $details, 422);
    }

    /**
     * 未授权响应（401）
     */
    public static function unauthorized(?string $message = null): JsonResponse
    {
        return static::error(ErrorCode::UNAUTHORIZED, $message, ErrorCode::UNAUTHORIZED->httpStatus());
    }

    /**
     * 禁止访问（403）
     */
    public static function forbidden(?string $message = null): JsonResponse
    {
        return static::error(ErrorCode::FORBIDDEN, $message, ErrorCode::FORBIDDEN->httpStatus());
    }

    /**
     * 未找到响应（404）
     */
    public static function notFound(?string $message = null): JsonResponse
    {
        return static::error(ErrorCode::NOT_FOUND, $message, ErrorCode::NOT_FOUND->httpStatus());
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
