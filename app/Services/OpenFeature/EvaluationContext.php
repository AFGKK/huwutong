<?php

namespace App\Services\OpenFeature;

/**
 * OpenFeature Evaluation Context
 *
 * 表示 Flag 评估的上下文信息，包含 target（License key 或 User ID）
 * 和可选的额外属性。
 */
class EvaluationContext
{
    /**
     * @param string|null $targetingKey 目标标识（License Key 或 User ID）
     * @param array<string, mixed> $attributes 额外上下文属性
     */
    public function __construct(
        public readonly ?string $targetingKey = null,
        public readonly array  $attributes = [],
    ) {}

    /**
     * 从请求数据创建上下文
     */
    public static function fromRequest(array $data): self
    {
        return new self(
            targetingKey: $data['targeting_key'] ?? $data['license_key'] ?? null,
            attributes: $data['attributes'] ?? $data['context'] ?? [],
        );
    }

    /**
     * 合并另一个上下文的属性
     */
    public function merge(self $other): self
    {
        return new self(
            targetingKey: $other->targetingKey ?? $this->targetingKey,
            attributes: array_merge($this->attributes, $other->attributes),
        );
    }
}
