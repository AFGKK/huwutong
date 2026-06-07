<?php

namespace App\Exceptions;

use App\Enums\ErrorCode;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * M2-34: 标准化 SDK 异常
 *
 * 所有业务异常统一使用此类，确保 ApiResponse 格式一致。
 *
 * 用法示例：
 *   throw new SdkException(ErrorCode::LICENSE_EXPIRED);
 *   throw new SdkException(ErrorCode::LICENSE_EXPIRED, ['days' => 5]);
 *   throw new SdkException(ErrorCode::LICENSE_EXPIRED, httpStatus: 403);
 */
class SdkException extends Exception
{
    /**
     * 标准化错误码
     */
    public readonly ErrorCode $errorCode;

    /**
     * 消息占位符替换参数
     *
     * @var array<string, mixed>
     */
    public readonly array $replace;

    /**
     * 错误详情（字段级错误等）
     *
     * @var array<string, mixed>
     */
    public readonly array $details;

    /**
     * @param ErrorCode              $errorCode  标准化错误码
     * @param array<string, mixed>   $replace    消息占位符替换参数
     * @param int|null               $httpStatus HTTP 状态码（默认使用错误码定义）
     * @param array<string, mixed>   $details    额外详情
     * @param \Throwable|null        $previous   前一个异常
     */
    public function __construct(
        ErrorCode $errorCode,
        array $replace = [],
        ?int $httpStatus = null,
        array $details = [],
        ?\Throwable $previous = null,
    ) {
        $this->errorCode = $errorCode;
        $this->replace = $replace;
        $this->details = $details;

        // 用英文消息兜底作为 Exception message
        $message = trans("errors.{$errorCode->value}", $replace, 'en');
        $message = $message !== "errors.{$errorCode->value}" ? $message : $errorCode->value;

        parent::__construct(
            message: $message,
            code: $httpStatus ?? $errorCode->httpStatus(),
            previous: $previous,
        );
    }

    /**
     * 将异常渲染为 API 响应
     */
    public function render(Request $request): JsonResponse
    {
        $error = app(\App\Services\ErrorCodeService::class)->toArray(
            code: $this->errorCode,
            replace: $this->replace,
            extra: ! empty($this->details) ? ['details' => $this->details] : [],
        );

        return response()->json([
            'success' => false,
            'error' => $error,
        ], $this->getCode());
    }

    /**
     * 快速创建异常并立即抛出
     *
     * @throws self
     */
    public static function throw(
        ErrorCode $errorCode,
        array $replace = [],
        ?int $httpStatus = null,
        array $details = [],
    ): never {
        throw new self($errorCode, $replace, $httpStatus, $details);
    }
}
