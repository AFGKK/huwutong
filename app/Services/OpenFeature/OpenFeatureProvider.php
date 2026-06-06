<?php

namespace App\Services\OpenFeature;

use App\Models\License;
use App\Services\FeatureFlagService;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * OpenFeature Provider
 *
 * 实现 OpenFeature 标准的 Flag Evaluation Provider 接口。
 * 内部委托给 FeatureFlagService 进行实际评估。
 *
 * 支持的 Flag 类型：
 * - boolean: 功能开关（如 ai_features, reporting）
 * - string: 字符串配置（如 theme, region）
 * - integer: 数字配置（如 max_concurrent_users）
 * - float: 浮点配置（如 sampling_rate）
 * - object: 对象配置（如 rate_limit_config）
 *
 * Flagd 兼容模式：支持 POST /flagd/evaluation/v1/{type} 风格的端点
 */
class OpenFeatureProvider
{
    /**
     * 支持的 Flag 类型
     */
    const SUPPORTED_TYPES = ['boolean', 'string', 'integer', 'float', 'object'];

    /**
     * 已知的系统 Feature Flag（boolean 类型）
     * 当 FeatureFlagService 中查询不到时，尝试从系统已知列表查找
     */
    const KNOWN_FLAGS = [
        'boolean' => [
            'api_access',
            'advanced_features',
            'reporting',
            'audit_log',
            'webhook',
            'sso',
            'white_label',
            'bulk_operation',
            'export',
            'import',
        ],
    ];

    /**
     * 默认值映射（当 flag 不存在时的降级值）
     */
    const DEFAULT_VALUES = [
        'boolean' => false,
        'string' => '',
        'integer' => 0,
        'float' => 0.0,
        'object' => [],
    ];

    public function __construct(
        protected FeatureFlagService $featureFlagService,
    ) {}

    /**
     * Provider 元信息
     */
    public function metadata(): array
    {
        return [
            'name' => 'HWT OpenFeature Provider',
            'version' => '1.0.0',
            'supported_types' => self::SUPPORTED_TYPES,
        ];
    }

    /**
     * 解析 Boolean 类型 Flag
     */
    public function resolveBooleanEvaluation(
        string            $flagKey,
        bool              $defaultValue = false,
        ?EvaluationContext $context = null,
    ): FlagValue {
        return $this->evaluate($flagKey, 'boolean', $defaultValue, $context);
    }

    /**
     * 解析 String 类型 Flag
     */
    public function resolveStringEvaluation(
        string            $flagKey,
        string            $defaultValue = '',
        ?EvaluationContext $context = null,
    ): FlagValue {
        return $this->evaluate($flagKey, 'string', $defaultValue, $context);
    }

    /**
     * 解析 Integer 类型 Flag
     */
    public function resolveIntegerEvaluation(
        string            $flagKey,
        int               $defaultValue = 0,
        ?EvaluationContext $context = null,
    ): FlagValue {
        return $this->evaluate($flagKey, 'integer', $defaultValue, $context);
    }

    /**
     * 解析 Float 类型 Flag
     */
    public function resolveFloatEvaluation(
        string            $flagKey,
        float             $defaultValue = 0.0,
        ?EvaluationContext $context = null,
    ): FlagValue {
        return $this->evaluate($flagKey, 'float', $defaultValue, $context);
    }

    /**
     * 解析 Object 类型 Flag
     */
    public function resolveObjectEvaluation(
        string            $flagKey,
        array             $defaultValue = [],
        ?EvaluationContext $context = null,
    ): FlagValue {
        return $this->evaluate($flagKey, 'object', $defaultValue, $context);
    }

    /**
     * 批量评估多个 Flag
     *
     * @param array<string, array{type: string, default: mixed}> $flags
     * @return array<string, FlagValue>
     */
    public function resolveBulk(array $flags, ?EvaluationContext $context = null): array
    {
        $results = [];
        foreach ($flags as $flagKey => $config) {
            $results[$flagKey] = $this->evaluate(
                $flagKey,
                $config['type'] ?? 'boolean',
                $config['default'] ?? false,
                $context,
            );
        }
        return $results;
    }

    /**
     * 获取所有可评估的 Flags（面向给定 License）
     *
     * @param EvaluationContext|null $context
     * @return array<string, FlagValue>
     */
    public function getAllFlags(?EvaluationContext $context = null): array
    {
        $license = $this->resolveLicense($context);

        if (! $license) {
            return [];
        }

        $product = $license->product;
        if (! $product) {
            return [];
        }

        $features = $this->featureFlagService->getProductFeatures($product);
        $results = [];

        foreach ($features as $feature) {
            $results[$feature['key']] = $this->evaluate(
                $feature['key'],
                'boolean',
                false,
                $context,
            );
        }

        return $results;
    }

    /**
     * 执行实际评估
     *
     * @template T of bool|string|int|float|array
     * @param string $flagKey
     * @param string $type
     * @param T $defaultValue
     * @param EvaluationContext|null $context
     * @return FlagValue<T>
     */
    protected function evaluate(
        string            $flagKey,
        string            $type,
        mixed             $defaultValue,
        ?EvaluationContext $context = null,
    ): FlagValue {
        try {
            // 1. 没有上下文 → 无法评估 → 返回默认值
            if (! $context || ! $context->targetingKey) {
                // 尝试从系统已知配置获取全局值
                return $this->evaluateGlobalFlag($flagKey, $type, $defaultValue);
            }

            // 2. 解析 License
            $license = $this->resolveLicense($context);

            if (! $license) {
                return FlagValue::default($defaultValue);
            }

            // 3. Boolean 类型 → 委托给 FeatureFlagService
            if ($type === 'boolean') {
                $enabled = $this->featureFlagService->hasFeature($license, $flagKey);
                return FlagValue::match($enabled, $enabled ? 'on' : 'off');
            }

            // 4. 非 Boolean 类型 → 从 metadata 或产品配置查找
            return $this->evaluateNonBooleanFlag($license, $flagKey, $type, $defaultValue);
        } catch (\Throwable $e) {
            return FlagValue::error($defaultValue, 'GENERAL');
        }
    }

    /**
     * 解析 License（从 context 或 attributes 中获取 License Key）
     */
    protected function resolveLicense(?EvaluationContext $context): ?License
    {
        if (! $context || ! $context->targetingKey) {
            return null;
        }

        try {
            // 按 License Key 查找
            $license = License::where('license_key', $context->targetingKey)->first();

            // 如果上下文 attributes 中有 license_id，也尝试查找
            if (! $license && ! empty($context->attributes['license_id'])) {
                $license = License::find($context->attributes['license_id']);
            }

            return $license;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * 评估全局 Flag（无上下文时）
     */
    protected function evaluateGlobalFlag(string $flagKey, string $type, mixed $defaultValue): FlagValue
    {
        // 对于 boolean 类型，检查数据库中的全局 flag
        if ($type === 'boolean') {
            $flag = \App\Models\FeatureFlag::where('key', $flagKey)->first();
            if ($flag) {
                return FlagValue::match(
                    $flag->is_active,
                    $flag->is_active ? 'on' : 'off',
                );
            }
        }

        // 检查是否在已知 flags 列表中
        if (in_array($flagKey, self::KNOWN_FLAGS[$type] ?? [], true)) {
            // 已知 flag 但没有找到具体配置 → 返回默认值（已知但未配置）
            return FlagValue::default($defaultValue);
        }

        return FlagValue::default($defaultValue);
    }

    /**
     * 评估非 Boolean 类型 Flag（String/Integer/Float/Object）
     */
    protected function evaluateNonBooleanFlag(
        License $license,
        string  $flagKey,
        string  $type,
        mixed   $defaultValue,
    ): FlagValue {
        $metadata = $license->metadata ?? [];

        // 从 License metadata 查找
        if (isset($metadata[$flagKey]) || array_key_exists($flagKey, $metadata)) {
            $value = $metadata[$flagKey];

            // 类型校验
            $valid = match ($type) {
                'string' => is_string($value),
                'integer' => is_int($value),
                'float' => is_float($value) || is_int($value),
                'object' => is_array($value),
                default => false,
            };

            if ($valid) {
                $castValue = match ($type) {
                    'float' => (float) $value,
                    default => $value,
                };
                return FlagValue::match($castValue, "metadata:{$flagKey}");
            }
        }

        // 从产品配置查找（预留扩展点）
        // 未来可以从 product.config 或 product.settings 读取

        // 从全局配置表查找（预留扩展点）

        return FlagValue::default($defaultValue);
    }
}
