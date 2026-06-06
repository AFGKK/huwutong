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
     * 已知错误码的中文解释模板
     */
    private const DIAGNOSIS_TEMPLATES = [
        'LICENSE_NOT_FOUND' => [
            'summary' => 'License Key 不存在',
            'detail' => '系统数据库中未找到您提供的 License Key。可能原因：输入错误、从未发放过该 Key、或 Key 已被删除。',
            'suggestions' => [
                '请检查输入的 License Key 是否完整无误（注意区分大小写和连字符）',
                '请联系销售或管理员确认该 Key 是否已正确发放',
                '如果是从邮件中复制的，请检查是否复制了额外的空格',
            ],
            'severity' => 'high',
        ],
        'LICENSE_EXPIRED' => [
            'summary' => 'License 已过期',
            'detail' => '该 License 的有效期已截止，当前无法激活或使用。',
            'suggestions' => [
                '请前往后台续期或购买新的 License',
                '联系销售代表商议续费方案',
                '如果认为此过期有误，请联系技术支持并提供 License Key',
            ],
            'severity' => 'high',
        ],
        'LICENSE_NOT_ACTIVATABLE' => [
            'summary' => 'License 当前不允许激活',
            'detail' => 'License 的状态不允许执行激活操作。可能已被挂起、冻结、撤销或处于其他非激活状态。',
            'suggestions' => [
                '检查 License 当前状态（待激活/已挂起/已冻结/已撤销）',
                '如果已挂起/冻结，请联系管理员恢复',
                '如果已撤销，需要重新申请 License',
            ],
            'severity' => 'high',
        ],
        'LICENSE_ALREADY_ACTIVE' => [
            'summary' => 'License 已激活',
            'detail' => '该 License 已经在一个设备上激活，无法重复激活。',
            'suggestions' => [
                '如果需要在另一台设备使用，可以先在旧设备上解绑',
                '如果您认为此激活有误，请提供设备指纹信息联系技术支持',
            ],
            'severity' => 'medium',
        ],
        'DEVICE_LIMIT_EXCEEDED' => [
            'summary' => '设备数量已达上限',
            'detail' => '此 License 允许绑定的设备数量已达到最大值（{max_devices} 台），无法继续添加新设备。',
            'suggestions' => [
                '登录管理后台查看已绑定的设备列表',
                '解绑不再使用的旧设备以释放名额',
                '升级许可证以获得更多设备绑定名额',
                '如果确有需要，联系销售人员申请临时扩容',
            ],
            'severity' => 'high',
        ],
        'DEVICE_BLACKLISTED' => [
            'summary' => '设备已被列入黑名单',
            'detail' => '该设备因安全原因被系统列入黑名单，不允许激活或使用。',
            'suggestions' => [
                '检查设备是否存在异常（如虚拟机/模拟器、频繁更换硬件等）',
                '如果您认为这是误判，请联系技术支持并提供设备指纹',
                '可以在后台设备管理中查看黑名单原因',
            ],
            'severity' => 'critical',
        ],
        'DEVICE_FINGERPRINT_INVALID' => [
            'summary' => '设备指纹无效',
            'detail' => '系统无法生成有效的设备指纹，或指纹格式不符合要求。',
            'suggestions' => [
                '检查 SDK 版本是否为最新',
                '确保设备提供了必要的硬件信息（MAC、CPU、主板序列号等）',
                '检查是否有安全软件阻止了指纹采集',
            ],
            'severity' => 'medium',
        ],
        'LICENSE_BLACKLISTED' => [
            'summary' => 'License 已被列入黑名单',
            'detail' => '该 License 因违反使用条款、退款或其他安全原因被列入黑名单，永久禁用。',
            'suggestions' => [
                '此操作不可逆，无法恢复',
                '如有疑问，请提供 License Key 联系技术支持',
                '购买新的 License 以继续使用',
            ],
            'severity' => 'critical',
        ],
        'LICENSE_INVALID_KEY' => [
            'summary' => 'License Key 格式无效',
            'detail' => '您提供的 License Key 格式不正确，不符合系统 Key 的格式规范。',
            'suggestions' => [
                '确认输入完整的 License Key（包括 HWT- 前缀）',
                '检查是否有多余的空格或特殊字符',
                '正确的格式通常是 HWT-XXXXXXXX-XXXXXXXX',
                '如果是从邮件复制，尝试手动输入',
            ],
            'severity' => 'medium',
        ],
        'TRIAL_NOT_ALLOWED' => [
            'summary' => '不允许创建试用',
            'detail' => '当前产品/客户不支持试用，或试用功能已被管理员关闭。',
            'suggestions' => [
                '联系管理员确认该产品是否开启试用功能',
                '直接购买正式 License',
            ],
            'severity' => 'low',
        ],
        'TRIAL_ALREADY_USED' => [
            'summary' => '已使用过试用',
            'detail' => '该客户已经使用过该产品的试用授权，无法再次创建试用。',
            'suggestions' => [
                '试用期结束后请购买正式 License',
                '如果您认为此限制有误，联系技术支持',
            ],
            'severity' => 'low',
        ],
        'TRIAL_EXPIRED' => [
            'summary' => '试用已过期',
            'detail' => '试用授权已到期，无法继续使用。',
            'suggestions' => [
                '购买正式 License 以继续使用',
                '需要更多试用时间？联系销售代表商议',
            ],
            'severity' => 'medium',
        ],
        'AUTH_FAILED' => [
            'summary' => '登录认证失败',
            'detail' => '邮箱/手机号或密码验证未通过。',
            'suggestions' => [
                '检查邮箱/手机号是否输入正确',
                '如果忘记密码，请使用"忘记密码"功能重置',
                '连续多次失败可能导致账号临时锁定，请稍后再试',
            ],
            'severity' => 'medium',
        ],
        'TOO_MANY_REQUESTS' => [
            'summary' => '请求频率过高',
            'detail' => '短时间内发起了过多请求，系统已触发限流保护。',
            'suggestions' => [
                '请稍等片刻后再试（通常需要等待 60 秒）',
                '如果您的应用需要高频率访问，请联系我们申请 API 配额提升',
                '检查客户端是否有重试循环，建议加入指数退避策略',
            ],
            'severity' => 'low',
        ],
        'PAYMENT_FAILED' => [
            'summary' => '支付失败',
            'detail' => '支付处理失败，无法完成续费或购买。',
            'suggestions' => [
                '检查银行卡余额是否充足',
                '确认银行卡信息（卡号、有效期、CVV）是否正确',
                '尝试更换其他支付方式',
                '联系发卡行确认是否有支付限制',
            ],
            'severity' => 'high',
        ],
        'SUBSCRIPTION_EXPIRED' => [
            'summary' => '订阅已过期',
            'detail' => '您的订阅已过期，关联的 License 已被停用。',
            'suggestions' => [
                '立即续费以恢复服务',
                '续费后关联的 License 将自动恢复',
                '超过宽限期后可能需要重新激活',
            ],
            'severity' => 'high',
        ],
    ];

    /**
     * 诊断单个错误码
     */
    public function diagnose(string $errorCode, array $context = []): array
    {
        $template = self::DIAGNOSIS_TEMPLATES[$errorCode] ?? $this->generateGenericDiagnosis($errorCode);

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
        foreach (self::DIAGNOSIS_TEMPLATES as $code => $template) {
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
            'summary' => "系统检测到错误: {$errorCode}",
            'detail' => "出现未预定义的错误类型 {$errorCode}。",
            'suggestions' => [
                '记录完整的错误信息和时间戳',
                '联系技术支持并提供错误代码',
                '如果问题持续，请提交工单',
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
            $hints[] = "当前已激活 {$active} 台设备（上限 {$max} 台），已超出 {$over} 台";
            $hints[] = "建议查看后台设备管理，了解各设备最后活跃时间";
        }

        if ($errorCode === 'LICENSE_EXPIRED' && isset($context['expires_at'])) {
            $hints[] = "该 License 于 {$context['expires_at']} 到期";
        }

        if ($errorCode === 'PAYMENT_FAILED' && isset($context['attempt_number'])) {
            $hints[] = "已尝试 {$context['attempt_number']} 次支付均未成功";
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
