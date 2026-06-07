<?php

namespace App\Services;

use App\Enums\ErrorCode;
use Illuminate\Support\Facades\App;

/**
 * M2-34: 错误码标准化服务
 *
 * 提供错误码消息查找、元数据查询和格式化功能。
 * 集成 Laravel 多语言支持，按当前语言返回对应消息。
 */
class ErrorCodeService
{
    /**
     * 获取错误码的中文/英文消息
     *
     * @param ErrorCode|string $code    错误码枚举或字符串
     * @param array            $replace 替换占位符 [:key => value]
     * @param string|null      $locale  语言代码（默认当前语言）
     * @return string
     */
    public function message(ErrorCode|string $code, array $replace = [], ?string $locale = null): string
    {
        $key = $code instanceof ErrorCode ? $code->value : strtoupper($code);

        $trans = trans("errors.$key", $replace, $locale ?: App::getLocale());

        // 如果翻译不存在或直接返回了 key，则返回英文 fallback
        if ($trans === "errors.$key") {
            $trans = trans("errors.$key", $replace, 'en');
        }

        return $trans !== "errors.$key" ? $trans : "Unknown error code: $key";
    }

    /**
     * 获取错误码的 HTTP 状态码
     */
    public function httpStatus(ErrorCode|string $code): int
    {
        $enum = $code instanceof ErrorCode ? $code : $this->resolve($code);
        return $enum ? $enum->httpStatus() : 500;
    }

    /**
     * 获取错误域
     */
    public function domain(ErrorCode|string $code): string
    {
        $enum = $code instanceof ErrorCode ? $code : $this->resolve($code);
        return $enum ? $enum->domain() : 'UNKNOWN';
    }

    /**
     * 判断错误是否可安全重试
     */
    public function isRetrySafe(ErrorCode|string $code): bool
    {
        $enum = $code instanceof ErrorCode ? $code : $this->resolve($code);
        return $enum && $enum->isRetrySafe();
    }

    /**
     * 判断是否为客户端错误
     */
    public function isClientError(ErrorCode|string $code): bool
    {
        $enum = $code instanceof ErrorCode ? $code : $this->resolve($code);
        return $enum && $enum->isClientError();
    }

    /**
     * 判断是否为服务器错误
     */
    public function isServerError(ErrorCode|string $code): bool
    {
        $enum = $code instanceof ErrorCode ? $code : $this->resolve($code);
        return $enum && $enum->isServerError();
    }

    /**
     * 解析字符串为 ErrorCode 枚举
     */
    public function resolve(string $code): ?ErrorCode
    {
        $upper = strtoupper($code);
        // 使用枚举的 tryFrom 方法安全尝试
        try {
            return ErrorCode::tryFrom($upper);
        } catch (\ValueError) {
            return null;
        }
    }

    /**
     * 获取所有错误码列表（按域分组），含消息和元数据
     *
     * @return array<int, array{code: string, domain: string, message: string, http_status: int, retry_safe: bool}>
     */
    public function all(?string $locale = null): array
    {
        $result = [];
        foreach (ErrorCode::all() as $code) {
            $result[] = [
                'code' => $code->value,
                'domain' => $code->domain(),
                'message' => $this->message($code, [], $locale),
                'http_status' => $code->httpStatus(),
                'retry_safe' => $code->isRetrySafe(),
            ];
        }
        return $result;
    }

    /**
     * 按域分组获取错误码（含消息）
     *
     * @return array<string, array<int, array{code: string, message: string, http_status: int, retry_safe: bool}>>
     */
    public function grouped(?string $locale = null): array
    {
        $groups = [];
        foreach (ErrorCode::groupedByDomain() as $domain => $codes) {
            foreach ($codes as $code) {
                $groups[$domain][] = [
                    'code' => $code->value,
                    'message' => $this->message($code, [], $locale),
                    'http_status' => $code->httpStatus(),
                    'retry_safe' => $code->isRetrySafe(),
                ];
            }
        }
        return $groups;
    }

    /**
     * 构建 ApiResponse 可用的标准错误数组
     *
     * @param ErrorCode|string $code    错误码
     * @param array            $replace 消息占位符
     * @param array            $extra   额外字段（如 details）
     * @return array{code: string, message: string, retry_safe: bool, domain: string}
     */
    public function toArray(ErrorCode|string $code, array $replace = [], array $extra = []): array
    {
        $enum = $code instanceof ErrorCode ? $code : $this->resolve($code);

        return array_filter(array_merge([
            'code' => $enum?->value ?? strtoupper((string) $code),
            'message' => $this->message($enum ?? $code, $replace),
            'retry_safe' => $enum?->isRetrySafe() ?? false,
            'domain' => $enum?->domain() ?? 'UNKNOWN',
        ], $extra));
    }
}
