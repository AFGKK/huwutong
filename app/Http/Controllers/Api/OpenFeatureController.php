<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\OpenFeature\EvaluationContext;
use App\Services\OpenFeature\OpenFeatureProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * OpenFeature Flag Evaluation API Controller
 *
 * 实现 OpenFeature 标准协议，分为两个访问层级：
 * - 公共端点（Public）：需要 License Key 验证，用于 SDK 集成
 * - 管理端点（Protected）：需要管理员认证
 *
 * 同时支持 flagd 兼容模式（/flagd/evaluation/v1/*）
 */
class OpenFeatureController extends Controller
{
    const SUPPORTED_TYPES = ['boolean', 'string', 'integer', 'float', 'object'];

    public function __construct(
        protected OpenFeatureProvider $provider,
    ) {}

    /**
     * Provider 元信息
     *
     * GET /api/openfeature/metadata
     */
    public function metadata(): JsonResponse
    {
        return ApiResponse::success($this->provider->metadata());
    }

    /**
     * Flagd 兼容的健康检查端点
     *
     * GET /flagd/evaluation/v1/health
     */
    public function health(): JsonResponse
    {
        return ApiResponse::success(['status' => 'SERVING']);
    }

    /**
     * 评估单个 Boolean Flag（SDK 通用接口）
     *
     * POST /api/openfeature/evaluate
     * {
     *     "flag_key": "ai_features",
     *     "type": "boolean",
     *     "default_value": false,
     *     "targeting_key": "LICENSE-KEY-xxx",
     *     "context": { ... }
     * }
     */
    public function evaluate(Request $request): JsonResponse
    {
        $request->validate([
            'flag_key' => 'required|string',
            'type' => 'nullable|string|in:' . implode(',', self::SUPPORTED_TYPES),
            'default_value' => 'nullable',
            'targeting_key' => 'nullable|string',
            'context' => 'nullable|array',
        ]);

        $flagKey = $request->input('flag_key');
        $type = $request->input('type', 'boolean');
        $defaultValue = $request->input('default_value', match ($type) {
            'boolean' => false,
            'string' => '',
            'integer' => 0,
            'float' => 0.0,
            'object' => [],
            default => null,
        });
        $context = EvaluationContext::fromRequest($request->all());

        $result = match ($type) {
            'boolean' => $this->provider->resolveBooleanEvaluation($flagKey, $defaultValue, $context),
            'string' => $this->provider->resolveStringEvaluation($flagKey, $defaultValue, $context),
            'integer' => $this->provider->resolveIntegerEvaluation($flagKey, $defaultValue, $context),
            'float' => $this->provider->resolveFloatEvaluation($flagKey, $defaultValue, $context),
            'object' => $this->provider->resolveObjectEvaluation($flagKey, $defaultValue, $context),
            default => $this->provider->resolveBooleanEvaluation($flagKey, $defaultValue, $context),
        };

        return ApiResponse::success($result->toArray());
    }

    /**
     * 批量评估多个 Flag
     *
     * POST /api/openfeature/evaluate/bulk
     * {
     *     "flags": { "flag_key": { "type": "boolean", "default": false } },
     *     "targeting_key": "LICENSE-KEY-xxx",
     *     "context": { ... }
     * }
     */
    public function evaluateBulk(Request $request): JsonResponse
    {
        $request->validate([
            'flags' => 'required|array',
            'flags.*.type' => 'nullable|string|in:' . implode(',', self::SUPPORTED_TYPES),
            'flags.*.default' => 'nullable',
            'targeting_key' => 'nullable|string',
            'context' => 'nullable|array',
        ]);

        $flags = $request->input('flags', []);
        $context = EvaluationContext::fromRequest($request->all());

        $results = $this->provider->resolveBulk($flags, $context);

        $formatted = [];
        foreach ($results as $key => $value) {
            $formatted[$key] = $value->toArray();
        }

        return ApiResponse::success(['flags' => $formatted]);
    }

    /**
     * 获取给定上下文的所有可用 Flags
     *
     * POST /api/openfeature/flags
     */
    public function allFlags(Request $request): JsonResponse
    {
        $request->validate([
            'targeting_key' => 'nullable|string',
            'context' => 'nullable|array',
        ]);

        $context = EvaluationContext::fromRequest($request->all());
        $results = $this->provider->getAllFlags($context);

        $formatted = [];
        foreach ($results as $key => $value) {
            $formatted[$key] = $value->toArray();
        }

        return ApiResponse::success(['flags' => $formatted]);
    }

    /**
     * Flagd 兼容模式：评估指定类型的 Flag
     *
     * POST /flagd/evaluation/v1/{type}
     *
     * flagd 协议：
     * {
     *     "flagKey": "...",
     *     "context": { "targetingKey": "...", ... }
     * }
     */
    public function flagdEvaluate(string $type, Request $request): JsonResponse
    {
        if (! in_array($type, self::SUPPORTED_TYPES, true)) {
            return ApiResponse::error('INVALID_FLAG_TYPE', "不支持的 Flag 类型: {$type}", 400);
        }

        $request->validate([
            'flagKey' => 'required|string',
            'context' => 'nullable|array',
        ]);

        $flagKey = $request->input('flagKey');
        $ctx = $request->input('context', []);
        $defaultValue = $request->input('defaultValue', match ($type) {
            'boolean' => false,
            'string' => '',
            'integer' => 0,
            'float' => 0.0,
            'object' => [],
            default => null,
        });

        $context = new EvaluationContext(
            targetingKey: $ctx['targetingKey'] ?? null,
            attributes: $ctx,
        );

        $result = match ($type) {
            'boolean' => $this->provider->resolveBooleanEvaluation($flagKey, $defaultValue, $context),
            'string' => $this->provider->resolveStringEvaluation($flagKey, $defaultValue, $context),
            'integer' => $this->provider->resolveIntegerEvaluation($flagKey, $defaultValue, $context),
            'float' => $this->provider->resolveFloatEvaluation($flagKey, $defaultValue, $context),
            'object' => $this->provider->resolveObjectEvaluation($flagKey, $defaultValue, $context),
            default => $this->provider->resolveBooleanEvaluation($flagKey, $defaultValue, $context),
        };

        // flagd 响应格式
        return response()->json([
            'value' => $result->value,
            'reason' => $result->reason,
            'variant' => $result->variant,
        ]);
    }

    /**
     * Flagd 兼容：批量评估
     *
     * POST /flagd/evaluation/v1/bulk
     *
     * {
     *     "flags": [
     *         { "flagKey": "...", "type": "boolean", "context": {...} },
     *     ]
     * }
     */
    public function flagdBulk(Request $request): JsonResponse
    {
        $request->validate([
            'flags' => 'required|array',
            'flags.*.flagKey' => 'required|string',
            'flags.*.type' => 'nullable|string|in:' . implode(',', self::SUPPORTED_TYPES),
        ]);

        $flags = $request->input('flags', []);
        $results = [];

        foreach ($flags as $flag) {
            $flagKey = $flag['flagKey'];
            $type = $flag['type'] ?? 'boolean';
            $ctx = $flag['context'] ?? [];
            $defaultValue = $flag['defaultValue'] ?? match ($type) {
                'boolean' => false,
                'string' => '',
                'integer' => 0,
                'float' => 0.0,
                'object' => [],
                default => null,
            };

            $context = new EvaluationContext(
                targetingKey: $ctx['targetingKey'] ?? null,
                attributes: $ctx,
            );

            $result = $this->provider->resolveBulk(
                [$flagKey => ['type' => $type, 'default' => $defaultValue]],
                $context,
            );

            $resolved = $result[$flagKey] ?? null;
            $results[] = [
                'flagKey' => $flagKey,
                'value' => $resolved?->value ?? $defaultValue,
                'reason' => $resolved?->reason ?? 'DEFAULT',
                'variant' => $resolved?->variant ?? null,
            ];
        }

        return response()->json(['flags' => $results]);
    }

    /**
     * 管理端点：列出所有 Feature Flags（含评估状态）
     *
     * GET /api/openfeature/manage/flags
     */
    public function manageAllFlags(Request $request): JsonResponse
    {
        $context = EvaluationContext::fromRequest($request->all());
        $allFlags = $this->provider->getAllFlags($context);

        // 同时列出系统中所有已注册的 FeatureFlags
        $registeredFlags = \App\Models\FeatureFlag::all();
        $result = [];

        foreach ($registeredFlags as $flag) {
            $evaluated = $allFlags[$flag->key] ?? null;
            $result[] = [
                'key' => $flag->key,
                'name' => $flag->name,
                'description' => $flag->description,
                'is_active' => $flag->is_active,
                'evaluated' => $evaluated?->toArray(),
            ];
        }

        return ApiResponse::success($result);
    }
}
