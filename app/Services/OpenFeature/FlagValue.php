<?php

namespace App\Services\OpenFeature;

/**
 * OpenFeature Flag Value
 *
 * 表示一个 Flag 的评估结果，包含值、原因和变体信息。
 *
 * @template T of bool|string|int|float|array
 */
class FlagValue
{
    /**
     * @param T $value
     * @param string $reason 评估原因（STATIC, DEFAULT, TARGETING_MATCH, SPLIT, DISABLED, ERROR, UNKNOWN）
     * @param string|null $variant 变体名称
     * @param string|null $errorCode 错误码（当 reason=ERROR 时）
     */
    public function __construct(
        public readonly mixed   $value,
        public readonly string  $reason = 'STATIC',
        public readonly ?string $variant = null,
        public readonly ?string $errorCode = null,
    ) {}

    /**
     * 创建一个默认值（flag 不存在或出错时的降级）
     *
     * @template T of bool|string|int|float|array
     * @param T $defaultValue
     * @return self<T>
     */
    public static function default(mixed $defaultValue): self
    {
        return new self(
            value: $defaultValue,
            reason: 'DEFAULT',
        );
    }

    /**
     * 创建一个匹配值
     *
     * @template T of bool|string|int|float|array
     * @param T $value
     * @param string|null $variant
     * @return self<T>
     */
    public static function match(mixed $value, ?string $variant = null): self
    {
        return new self(
            value: $value,
            reason: 'TARGETING_MATCH',
            variant: $variant,
        );
    }

    /**
     * 创建一个错误值
     *
     * @template T of bool|string|int|float|array
     * @param T $defaultValue
     * @param string $errorCode
     * @return self<T>
     */
    public static function error(mixed $defaultValue, string $errorCode = 'GENERAL'): self
    {
        return new self(
            value: $defaultValue,
            reason: 'ERROR',
            errorCode: $errorCode,
        );
    }

    /**
     * 转换为数组（用于 API 响应）
     */
    public function toArray(): array
    {
        return array_filter([
            'value' => $this->value,
            'reason' => $this->reason,
            'variant' => $this->variant,
            'error_code' => $this->errorCode,
        ], fn($v) => $v !== null);
    }
}
