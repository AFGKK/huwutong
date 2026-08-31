<?php

namespace App\Services;

use App\Enums\ApiErrorCode;
use App\Models\Device;
use App\Models\License;
use App\Models\Product;
use Illuminate\Support\Facades\Log;

/**
 * AI 错误诊断引擎
 *
 * 分析激活失败/授权错误的根因，提供自然语言解释和建议解决方案。
 * 自包含诊断逻辑（无需外部 AI API），当配置了 LLM 服务时自动使用 LLM 增强。
 */
class DiagnosticEngineService
{
    /**
     * 已知错误码的中英文诊断模板
     */
    protected function getDiagnosisTemplates(): array
    {
        return [
            'LICENSE_NOT_FOUND' => [
                'summary' => __('app.diagnostic.LICENSE_NOT_FOUND.summary'),
                'detail' => __('app.diagnostic.LICENSE_NOT_FOUND.detail'),
                'suggestions' => [
                    __('app.diagnostic.LICENSE_NOT_FOUND.suggestions.0'),
                    __('app.diagnostic.LICENSE_NOT_FOUND.suggestions.1'),
                    __('app.diagnostic.LICENSE_NOT_FOUND.suggestions.2'),
                ],
                'severity' => 'high',
            ],
            'LICENSE_EXPIRED' => [
                'summary' => __('app.diagnostic.LICENSE_EXPIRED.summary'),
                'detail' => __('app.diagnostic.LICENSE_EXPIRED.detail'),
                'suggestions' => [
                    __('app.diagnostic.LICENSE_EXPIRED.suggestions.0'),
                    __('app.diagnostic.LICENSE_EXPIRED.suggestions.1'),
                    __('app.diagnostic.LICENSE_EXPIRED.suggestions.2'),
                ],
                'severity' => 'high',
            ],
            'LICENSE_NOT_ACTIVATABLE' => [
                'summary' => __('app.diagnostic.LICENSE_NOT_ACTIVATABLE.summary'),
                'detail' => __('app.diagnostic.LICENSE_NOT_ACTIVATABLE.detail'),
                'suggestions' => [
                    __('app.diagnostic.LICENSE_NOT_ACTIVATABLE.suggestions.0'),
                    __('app.diagnostic.LICENSE_NOT_ACTIVATABLE.suggestions.1'),
                    __('app.diagnostic.LICENSE_NOT_ACTIVATABLE.suggestions.2'),
                ],
                'severity' => 'high',
            ],
            'LICENSE_ALREADY_ACTIVE' => [
                'summary' => __('app.diagnostic.LICENSE_ALREADY_ACTIVE.summary'),
                'detail' => __('app.diagnostic.LICENSE_ALREADY_ACTIVE.detail'),
                'suggestions' => [
                    __('app.diagnostic.LICENSE_ALREADY_ACTIVE.suggestions.0'),
                    __('app.diagnostic.LICENSE_ALREADY_ACTIVE.suggestions.1'),
                ],
                'severity' => 'medium',
            ],
            'DEVICE_LIMIT_EXCEEDED' => [
                'summary' => __('app.diagnostic.DEVICE_LIMIT_EXCEEDED.summary'),
                'detail' => __('app.diagnostic.DEVICE_LIMIT_EXCEEDED.detail'),
                'suggestions' => [
                    __('app.diagnostic.DEVICE_LIMIT_EXCEEDED.suggestions.0'),
                    __('app.diagnostic.DEVICE_LIMIT_EXCEEDED.suggestions.1'),
                    __('app.diagnostic.DEVICE_LIMIT_EXCEEDED.suggestions.2'),
                    __('app.diagnostic.DEVICE_LIMIT_EXCEEDED.suggestions.3'),
                ],
                'severity' => 'high',
            ],
            'DEVICE_BLACKLISTED' => [
                'summary' => __('app.diagnostic.DEVICE_BLACKLISTED.summary'),
                'detail' => __('app.diagnostic.DEVICE_BLACKLISTED.detail'),
                'suggestions' => [
                    __('app.diagnostic.DEVICE_BLACKLISTED.suggestions.0'),
                    __('app.diagnostic.DEVICE_BLACKLISTED.suggestions.1'),
                    __('app.diagnostic.DEVICE_BLACKLISTED.suggestions.2'),
                ],
                'severity' => 'critical',
            ],
            'DEVICE_FINGERPRINT_INVALID' => [
                'summary' => __('app.diagnostic.DEVICE_FINGERPRINT_INVALID.summary'),
                'detail' => __('app.diagnostic.DEVICE_FINGERPRINT_INVALID.detail'),
                'suggestions' => [
                    __('app.diagnostic.DEVICE_FINGERPRINT_INVALID.suggestions.0'),
                    __('app.diagnostic.DEVICE_FINGERPRINT_INVALID.suggestions.1'),
                    __('app.diagnostic.DEVICE_FINGERPRINT_INVALID.suggestions.2'),
                ],
                'severity' => 'medium',
            ],
            'LICENSE_BLACKLISTED' => [
                'summary' => __('app.diagnostic.LICENSE_BLACKLISTED.summary'),
                'detail' => __('app.diagnostic.LICENSE_BLACKLISTED.detail'),
                'suggestions' => [
                    __('app.diagnostic.LICENSE_BLACKLISTED.suggestions.0'),
                    __('app.diagnostic.LICENSE_BLACKLISTED.suggestions.1'),
                    __('app.diagnostic.LICENSE_BLACKLISTED.suggestions.2'),
                ],
                'severity' => 'critical',
            ],
            'LICENSE_INVALID_KEY' => [
                'summary' => __('app.diagnostic.LICENSE_INVALID_KEY.summary'),
                'detail' => __('app.diagnostic.LICENSE_INVALID_KEY.detail'),
                'suggestions' => [
                    __('app.diagnostic.LICENSE_INVALID_KEY.suggestions.0'),
                    __('app.diagnostic.LICENSE_INVALID_KEY.suggestions.1'),
                    __('app.diagnostic.LICENSE_INVALID_KEY.suggestions.2'),
                    __('app.diagnostic.LICENSE_INVALID_KEY.suggestions.3'),
                ],
                'severity' => 'medium',
            ],
            'TRIAL_NOT_ALLOWED' => [
                'summary' => __('app.diagnostic.TRIAL_NOT_ALLOWED.summary'),
                'detail' => __('app.diagnostic.TRIAL_NOT_ALLOWED.detail'),
                'suggestions' => [
                    __('app.diagnostic.TRIAL_NOT_ALLOWED.suggestions.0'),
                    __('app.diagnostic.TRIAL_NOT_ALLOWED.suggestions.1'),
                ],
                'severity' => 'low',
            ],
            'TRIAL_ALREADY_USED' => [
                'summary' => __('app.diagnostic.TRIAL_ALREADY_USED.summary'),
                'detail' => __('app.diagnostic.TRIAL_ALREADY_USED.detail'),
                'suggestions' => [
                    __('app.diagnostic.TRIAL_ALREADY_USED.suggestions.0'),
                    __('app.diagnostic.TRIAL_ALREADY_USED.suggestions.1'),
                ],
                'severity' => 'low',
            ],
            'TRIAL_EXPIRED' => [
                'summary' => __('app.diagnostic.TRIAL_EXPIRED.summary'),
                'detail' => __('app.diagnostic.TRIAL_EXPIRED.detail'),
                'suggestions' => [
                    __('app.diagnostic.TRIAL_EXPIRED.suggestions.0'),
                    __('app.diagnostic.TRIAL_EXPIRED.suggestions.1'),
                ],
                'severity' => 'medium',
            ],
            'AUTH_FAILED' => [
                'summary' => __('app.diagnostic.AUTH_FAILED.summary'),
                'detail' => __('app.diagnostic.AUTH_FAILED.detail'),
                'suggestions' => [
                    __('app.diagnostic.AUTH_FAILED.suggestions.0'),
                    __('app.diagnostic.AUTH_FAILED.suggestions.1'),
                    __('app.diagnostic.AUTH_FAILED.suggestions.2'),
                ],
                'severity' => 'medium',
            ],
            'TOO_MANY_REQUESTS' => [
                'summary' => __('app.diagnostic.TOO_MANY_REQUESTS.summary'),
                'detail' => __('app.diagnostic.TOO_MANY_REQUESTS.detail'),
                'suggestions' => [
                    __('app.diagnostic.TOO_MANY_REQUESTS.suggestions.0'),
                    __('app.diagnostic.TOO_MANY_REQUESTS.suggestions.1'),
                    __('app.diagnostic.TOO_MANY_REQUESTS.suggestions.2'),
                ],
                'severity' => 'low',
            ],
            'PAYMENT_FAILED' => [
                'summary' => __('app.diagnostic.PAYMENT_FAILED.summary'),
                'detail' => __('app.diagnostic.PAYMENT_FAILED.detail'),
                'suggestions' => [
                    __('app.diagnostic.PAYMENT_FAILED.suggestions.0'),
                    __('app.diagnostic.PAYMENT_FAILED.suggestions.1'),
                    __('app.diagnostic.PAYMENT_FAILED.suggestions.2'),
                    __('app.diagnostic.PAYMENT_FAILED.suggestions.3'),
                ],
                'severity' => 'high',
            ],
            'SUBSCRIPTION_EXPIRED' => [
                'summary' => __('app.diagnostic.SUBSCRIPTION_EXPIRED.summary'),
                'detail' => __('app.diagnostic.SUBSCRIPTION_EXPIRED.detail'),
                'suggestions' => [
                    __('app.diagnostic.SUBSCRIPTION_EXPIRED.suggestions.0'),
                    __('app.diagnostic.SUBSCRIPTION_EXPIRED.suggestions.1'),
                    __('app.diagnostic.SUBSCRIPTION_EXPIRED.suggestions.2'),
                ],
                'severity' => 'high',
            ],
        ];
    }

    /**
     * 诊断单个错误码
     */
    public function diagnose(string $errorCode, array $context = []): array
    {
        $template = $this->getDiagnosisTemplates()[$errorCode] ?? $this->generateGenericDiagnosis($errorCode);

        // 注入上下文详情
        $detail = $this->injectContext($template['detail'], $context);
        $suggestions = array_map(
            fn($s) => $this->injectContext($s, $context),
            $template['suggestions']
        );

        // 如果有上下文中的额外诊断信息，补充进去
        $contextualHints = $this->extractContextualHints($errorCode, $context);
        if (!empty($contextualHints)) {
            $suggestions = array_merge($suggestions, $contextualHints);
        }

        return [
            'error_code' => $errorCode,
            'summary' => $template['summary'],
            'detail' => $detail,
            'suggestions' => $suggestions,
            'severity' => $template['severity'],
            'code' => $this->getHttpStatusCode($errorCode),
        ];
    }

    /**
     * 诊断激活失败（从异常/错误结果自动提取诊断信息）
     */
    public function diagnoseActivationFailure(
        ?License $license = null,
        ?Device $device = null,
        ?string $errorCode = null,
        ?string $errorMessage = null,
        array $extraContext = []
    ): array {
        // 自动推断错误码
        $resolvedCode = $errorCode ?: $this->inferErrorCode($license, $device, $errorMessage);
        $context = $extraContext;

        // 收集上下文
        if ($license) {
            $context['license_key'] = $license->license_key;
            $context['license_status'] = $license->status;
            $context['license_type'] = $license->type;
            $context['expires_at'] = $license->expires_at?->toDateString();
            $context['created_at'] = $license->created_at->toDateString();
            $context['max_devices'] = $license->max_devices;

            // 检查是否超出设备限制
            if ($resolvedCode === 'DEVICE_LIMIT_EXCEEDED') {
                $activeDevices = $license->activations()?->count() ?? 0;
                $context['active_devices'] = $activeDevices;
                $context['max_devices'] = $license->max_devices;
                $context['over_limit_by'] = $activeDevices - $license->max_devices;
            }
        }

        if ($device) {
            $context['device_fingerprint'] = substr($device->fingerprint, 0, 16) . '...';
            $context['device_platform'] = $device->platform;
            $context['device_is_trusted'] = $device->trust_score > 80;
        }

        if ($errorMessage) {
            $context['error_message'] = $errorMessage;
        }

        $result = $this->diagnose($resolvedCode, $context);

        // 附加 device/license 快照信息
        $result['context'] = [
            'license_id' => $license?->id,
            'license_key' => $license?->license_key,
            'license_status' => $license?->status,
            'device_fingerprint' => $device?->fingerprint,
            'error_message' => $errorMessage,
        ];

        // 尝试使用 LLM 增强诊断（如果可用）
        try {
            $enhanced = $this->enhanceWithLlm($result, $context);
            if ($enhanced) {
                $result['llm_enhanced'] = true;
                $result['llm_analysis'] = $enhanced;
            }
        } catch (\Throwable $e) {
            // LLM 增强失败不影响基础诊断
            Log::debug('DiagnosticEngine: LLM enhancement failed', ['error' => $e->getMessage()]);
        }

        Log::info('DiagnosticEngine: activation failure diagnosed', [
            'error_code' => $resolvedCode,
            'license_id' => $license?->id,
            'device_fingerprint' => $device?->fingerprint ? substr($device->fingerprint, 0, 8) . '...' : null,
        ]);

        return $result;
    }

    /**
     * 批量诊断多个错误
     */
    public function diagnoseBatch(array $errors): array
    {
        $results = [];
        foreach ($errors as $error) {
            $results[] = $this->diagnose(
                $error['code'] ?? 'UNKNOWN_ERROR',
                $error['context'] ?? []
            );
        }
        return $results;
    }

    /**
     * 获取从错误码到中英文建议映射（用于 SDK 集成）
     */
    public function getSdkSuggestionMap(): array
    {
        $map = [];
        foreach ($this->getDiagnosisTemplates() as $code => $template) {
            $map[$code] = [
                'summary' => $template['summary'],
                'suggestions' => $template['suggestions'],
                'severity' => $template['severity'],
            ];
        }
        return $map;
    }

    /**
     * 根据错误码生成通用诊断
     */
    protected function generateGenericDiagnosis(string $errorCode): array
    {
        return [
            'summary' => __('app.diagnostic.generic.summary', ['code' => $errorCode]),
            'detail' => __('app.diagnostic.generic.detail', ['code' => $errorCode]),
            'suggestions' => [
                __('app.diagnostic.generic.suggestions.0'),
                __('app.diagnostic.generic.suggestions.1'),
                __('app.diagnostic.generic.suggestions.2'),
            ],
            'severity' => 'medium',
        ];
    }

    /**
     * 注入上下文变量到详情/建议中
     */
    protected function injectContext(string $text, array $context): string
    {
        $replacements = [];
        foreach ($context as $key => $value) {
            $replacements['{' . $key . '}'] = (string) $value;
        }
        return strtr($text, $replacements);
    }

    /**
     * 根据 License/Device 推断错误码
     */
    protected function inferErrorCode(?License $license, ?Device $device, ?string $errorMessage): string
    {
        if (!$license) {
            return 'LICENSE_NOT_FOUND';
        }

        return match ($license->status) {
            'expired' => 'LICENSE_EXPIRED',
            'revoked' => 'LICENSE_REVOKED',
            'blacklisted' => 'LICENSE_BLACKLISTED',
            'suspended', 'frozen' => 'LICENSE_NOT_ACTIVATABLE',
            'active' => 'LICENSE_ALREADY_ACTIVE',
            default => 'UNKNOWN_ERROR',
        };
    }

    /**
     * 从错误详情中提取额外建议
     */
    protected function extractContextualHints(string $errorCode, array $context): array
    {
        $hints = [];

        if ($errorCode === 'DEVICE_LIMIT_EXCEEDED') {
            $active = $context['active_devices'] ?? 0;
            $max = $context['max_devices'] ?? 0;
            $over = $context['over_limit_by'] ?? 0;
            $hints[] = __('app.diagnostic.hints.device_limit', ['active' => $active, 'max' => $max, 'over' => $over]);
            $hints[] = __('app.diagnostic.hints.check_device_mgmt');
        }

        if ($errorCode === 'LICENSE_EXPIRED' && isset($context['expires_at'])) {
            $hints[] = __('app.diagnostic.hints.license_expired', ['date' => $context['expires_at']]);
        }

        if ($errorCode === 'PAYMENT_FAILED' && isset($context['attempt_number'])) {
            $hints[] = __('app.diagnostic.hints.payment_attempts', ['count' => $context['attempt_number']]);
        }

        return $hints;
    }

    /**
     * 使用 LLM 增强诊断（如果配置了 LLM 服务）
     */
    protected function enhanceWithLlm(array $diagnosis, array $context): ?array
    {
        try {
            $llmService = app(LlmService::class);

            $prompt = sprintf(
                "你是一个软件授权系统的错误诊断专家。用户遇到了以下错误，请提供更详细的原因分析和解决方案。\n\n" .
                "错误码: %s\n摘要: %s\n详情: %s\n上下文: %s\n\n" .
                "请用中文以 JSON 格式回复: {\"root_cause\": \"根本原因分析\", \"technical_detail\": \"技术细节\", \"step_by_step_solution\": [\"步骤1\", \"步骤2\"]}",
                $diagnosis['error_code'],
                $diagnosis['summary'],
                $diagnosis['detail'],
                json_encode($context, JSON_UNESCAPED_UNICODE)
            );

            $response = $llmService->chat([
                ['role' => 'system', 'content' => '你是一个专业的技术支持工程师，擅长分析软件授权问题。'],
                ['role' => 'user', 'content' => $prompt],
            ], ['no_fallback' => true]);

            $content = $response['choices'][0]['message']['content'] ?? '';

            // 尝试解析 JSON
            $parsed = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $parsed;
            }

            return ['raw_response' => $content];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * 获取 HTTP 状态码
     */
    protected function getHttpStatusCode(string $errorCode): int
    {
        $enum = ApiErrorCode::tryFrom($errorCode);
        return $enum ? $enum->httpStatus() : 400;
    }
}
