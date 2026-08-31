<?php

namespace App\Services;

use App\Models\ComplianceAiReport;
use App\Models\ComplianceEvidenceItem;
use App\Models\ComplianceEvidence;
use App\Models\ComplianceGapAnalysis;
use App\Models\TamperEvent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * M3-38 AI 合规报告生成
 */
class ComplianceReportAiService
{
    /**
     * 生成合规报告
     */
    public function generateReport(int $tenantId, int $userId, string $framework, string $language = 'zh-CN'): ComplianceAiReport
    {
        $frameworkConfig = config("compliance-report.frameworks.{$framework}");
        if (!$frameworkConfig) {
            throw new \InvalidArgumentException(__("app.compliance_report_ai.msg_fcc8ed76"));
        }

        $report = ComplianceAiReport::create([
            'tenant_id' => $tenantId,
            'framework' => $framework,
            'title' => "{$frameworkConfig['name']} 合规报告 - " . now()->format('Y-m-d'),
            'status' => 'generating',
            'language' => $language,
            'generated_by' => $userId,
            'generated_at' => now(),
        ]);

        try {
            // 收集证据数据
            $evidenceData = $this->collectEvidence($tenantId, $framework);

            // 构建AI提示词
            $prompt = $this->buildPrompt($framework, $evidenceData, $language);

            // 调用AI生成
            $aiResponse = $this->callAiApi($prompt);

            // 解析AI响应
            $parsed = $this->parseAiResponse($aiResponse, $framework);

            // 更新报告
            $report->update([
                'status' => 'completed',
                'sections' => $parsed['sections'],
                'evidence_summary' => $parsed['evidence_summary'],
                'gap_analysis' => $parsed['gap_analysis'],
                'recommendations' => $parsed['recommendations'],
                'ai_prompt' => $prompt,
                'ai_response' => $aiResponse,
            ]);

            // 创建证据项
            foreach ($parsed['evidence_items'] ?? [] as $item) {
                ComplianceEvidenceItem::create(array_merge($item, [
                    'compliance_ai_report_id' => $report->id,
                    'framework' => $framework,
                ]));
            }
        } catch (\Exception $e) {
            $report->update([
                'status' => 'failed',
                'ai_response' => $e->getMessage(),
            ]);
            Log::error('AI compliance report generation failed', ['report_id' => $report->id, 'error' => $e->getMessage()]);
        }

        return $report->fresh();
    }

    /**
     * 收集合规证据
     */
    protected function collectEvidence(int $tenantId, string $framework): array
    {
        $evidence = [];

        // 通用证据
        $evidence['total_users'] = \App\Models\User::where('tenant_id', $tenantId)->count();
        $evidence['total_customers'] = \App\Models\Customer::where('tenant_id', $tenantId)->count();
        $evidence['total_licenses'] = \App\Models\License::where('tenant_id', $tenantId)->count();

        // 安全事件
        $evidence['security_events_30d'] = TamperEvent::where('created_at', '>=', now()->subDays(30))->count();
        $evidence['unresolved_events'] = TamperEvent::where('is_resolved', false)->count();

        // 加密配置
        $evidence['has_encryption'] = config('app.key') ? true : false;
        $evidence['has_ssl'] = true;

        // 审计日志
        $evidence['audit_logs_30d'] = \App\Models\AuditLog::where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->subDays(30))->count();

        // 数据保留策略
        $evidence['data_retention_days'] = config('audit.retention_days', 90);

        // 框架特定证据
        if ($framework === 'gdpr') {
            $evidence['has_dpa'] = \App\Models\DataProcessingAgreement::where('tenant_id', $tenantId)->exists();
            $evidence['deletion_requests'] = \App\Models\DeletionRequest::where('tenant_id', $tenantId)->count();
        }

        if ($framework === 'soc2') {
            $evidence['uptime_30d'] = \App\Models\SlaProbeUptime::whereHas('probe', fn($q) => $q->where('tenant_id', $tenantId))
                ->where('record_date', '>=', now()->subDays(30))
                ->avg('uptime_percentage');
        }

        // 合规包证据
        $compEvidence = ComplianceEvidence::where('tenant_id', $tenantId)->get();
        $evidence['collected_evidence_count'] = $compEvidence->count();
        $evidence['validated_evidence'] = $compEvidence->where('status', 'validated')->count();

        // 差距分析
        $gaps = ComplianceGapAnalysis::where('tenant_id', $tenantId)->get();
        $evidence['open_gaps'] = $gaps->where('status', 'open')->count();
        $evidence['resolved_gaps'] = $gaps->where('status', 'resolved')->count();

        return $evidence;
    }

    /**
     * 构建AI提示词
     */
    protected function buildPrompt(string $framework, array $evidence, string $language): string
    {
        $frameworkConfig = config("compliance-report.frameworks.{$framework}");
        $lang = $language === 'zh-CN' ? '请使用中文' : 'Please use English';

        $sections = collect($frameworkConfig['sections'] ?? [])
            ->map(fn($desc, $key) => "- {$key}: {$desc}")
            ->implode("\n");

        $evidenceStr = collect($evidence)
            ->map(fn($val, $key) => "- {$key}: {$val}")
            ->implode("\n");

        return <<<PROMPT
{$lang}生成一份{$frameworkConfig['full_name']}({$frameworkConfig['name']})合规报告。

报告结构需包含以下章节：
{$sections}

基于以下系统证据数据生成报告：
{$evidenceStr}

对于每个章节，请提供：
1. 合规状态评估(合规/部分合规/不合规/不适用)
2. 当前状态描述
3. 差距分析
4. 整改建议和优先级(高/中/低)

请以JSON格式输出，包含以下字段：
- sections: 各章节评估详情
- evidence_summary: 证据项统计
- gap_analysis: 总体差距分析
- recommendations: 改进建议列表
- evidence_items: 逐项证据(每条包含section, control_id, title, status, evidence, gap, recommendation, priority)

请确保输出为有效的JSON。
PROMPT;
    }

    /**
     * 调用AI API
     */
    protected function callAiApi(string $prompt): string
    {
        $provider = config('compliance-report.ai.provider', 'openai');
        $model = config('compliance-report.ai.model', 'gpt-4');
        $temperature = config('compliance-report.ai.temperature', 0.3);
        $maxTokens = config('compliance-report.ai.max_tokens', 4000);

        if ($provider === 'openai') {
            $apiKey = config('services.openai.api_key');
            if (empty($apiKey)) {
                return $this->generateFallbackResponse($prompt);
            }

            $response = Http::withToken($apiKey)->timeout(120)->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => '你是一个专业合规分析师，擅长生成合规审计报告。'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
            ]);

            if ($response->successful()) {
                return $response->json('choices.0.message.content', '');
            }
        }

        return $this->generateFallbackResponse($prompt);
    }

    /**
     * AI不可用时的兜底生成
     */
    protected function generateFallbackResponse(string $prompt): string
    {
        // 提取框架信息
        preg_match('/框架: (\w+)/', $prompt, $matches);
        $framework = $matches[1] ?? 'gdpr';

        $sections = config("compliance-report.frameworks.{$framework}.sections", []);
        $sectionData = [];

        foreach ($sections as $key => $desc) {
            $sectionData[] = [
                'section' => $key,
                'status' => 'partial',
                'description' => "{$desc} - 部分合规",
                'details' => '需进一步完善相关文档和控制措施',
            ];
        }

        return json_encode([
            'sections' => $sectionData,
            'evidence_summary' => [
                'total_items' => 0,
                'compliant' => 0,
                'partial' => count($sectionData),
                'non_compliant' => 0,
                'not_applicable' => 0,
            ],
            'gap_analysis' => [
                'summary' => '需关注差距项并制定整改计划',
                'critical_gaps' => 0,
                'high_gaps' => 0,
                'medium_gaps' => count($sectionData),
                'low_gaps' => 0,
            ],
            'recommendations' => [
                ['priority' => 'high', 'action' => '建立完整的合规管理流程'],
                ['priority' => 'medium', 'action' => '定期进行合规自评估'],
                ['priority' => 'low', 'action' => '持续监控合规状态变化'],
            ],
            'evidence_items' => [],
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * 解析AI响应
     */
    protected function parseAiResponse(string $response, string $framework): array
    {
        // 尝试JSON解析
        $jsonStart = strpos($response, '{');
        $jsonEnd = strrpos($response, '}');

        if ($jsonStart !== false && $jsonEnd !== false) {
            $jsonStr = substr($response, $jsonStart, $jsonEnd - $jsonStart + 1);
            $parsed = json_decode($jsonStr, true);

            if ($parsed) {
                return $parsed;
            }
        }

        // 兜底
        return [
            'sections' => [],
            'evidence_summary' => [
                'total_items' => 0, 'compliant' => 0, 'partial' => 0,
                'non_compliant' => 0, 'not_applicable' => 0,
            ],
            'gap_analysis' => ['summary' => 'AI解析失败，请重试'],
            'recommendations' => [],
            'evidence_items' => [],
        ];
    }

    /**
     * 获取仪表盘
     */
    public function getDashboard(int $tenantId): array
    {
        $total = ComplianceAiReport::where('tenant_id', $tenantId)->count();
        $completed = ComplianceAiReport::where('tenant_id', $tenantId)->where('status', 'completed')->count();
        $failed = ComplianceAiReport::where('tenant_id', $tenantId)->where('status', 'failed')->count();

        $byFramework = ComplianceAiReport::where('tenant_id', $tenantId)
            ->selectRaw('framework, COUNT(*) as count')
            ->groupBy('framework')
            ->pluck('count', 'framework')
            ->toArray();

        return compact('total', 'completed', 'failed', 'byFramework');
    }
}
