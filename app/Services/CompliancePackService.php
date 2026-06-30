<?php

namespace App\Services;

use App\Models\ComplianceEvidence;
use App\Models\ComplianceFramework;
use App\Models\ComplianceGapAnalysis;
use App\Models\CompliancePolicyDocument;
use App\Models\ComplianceQuestionnaire;
use App\Models\ComplianceQuestionnaireResponse;
use App\Models\ComplianceReport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * SOC2 / ISO27001 合规准备包服务
 *
 * 提供完整的合规准备工具：
 * - 预填审计问卷模板（问题+指引+控制引用）
 * - 证据收集清单与自动化收集
 * - 安全策略文档模板（可填入组织信息并生成正式文档）
 * - 合规差距分析（当前状态 vs 目标状态 + 整改计划）
 * - 合规报告导出（PDF/HTML/JSON）
 *
 * @m3-69 CompliancePack
 */
class CompliancePackService
{
    /**
     * 支持的合规框架
     */
    const FRAMEWORKS = [
        'SOC2' => [
            'name' => 'SOC 2',
            'domains' => ['SEC', 'AVA', 'PID', 'CON', 'PRI'],
            'version' => '2024',
        ],
        'ISO27001' => [
            'name' => 'ISO 27001:2022',
            'domains' => ['A.5', 'A.6', 'A.7', 'A.8', 'A.9', 'A.10', 'A.11', 'A.12', 'A.13', 'A.14'],
            'version' => '2022',
        ],
    ];

    /**
     * ─── 审计问卷管理 ───
     */

    /**
     * 获取问卷模板
     */
    public function getQuestionnaireTemplates(string $frameworkCode): array
    {
        $cacheKey = "compliance:questionnaire:{$frameworkCode}";

        return Cache::remember($cacheKey, 86400, function () use ($frameworkCode) {
            $questions = ComplianceQuestionnaire::where('framework_code', $frameworkCode)
                ->where('is_active', true)
                ->orderBy('category')
                ->orderBy('sort_order')
                ->get();

            if ($questions->isNotEmpty()) {
                return $questions->toArray();
            }

            // 首次运行，种子数据
            return $this->seedQuestionnaireTemplates($frameworkCode);
        });
    }

    /**
     * 种子问卷数据
     */
    public function seedQuestionnaireTemplates(string $frameworkCode): array
    {
        $templates = $frameworkCode === 'SOC2'
            ? $this->getSoc2QuestionnaireTemplate()
            : $this->getIso27001QuestionnaireTemplate();

        $created = [];
        foreach ($templates as $data) {
            $question = ComplianceQuestionnaire::firstOrCreate(
                [
                    'framework_code' => $frameworkCode,
                    'question_key' => $data['question_key'],
                ],
                $data,
            );
            $created[] = $question->toArray();
        }

        return $created;
    }

    /**
     * 获取或创建问卷响应
     */
    public function getOrCreateResponse(int $questionId, int $reportId): ComplianceQuestionnaireResponse
    {
        return ComplianceQuestionnaireResponse::firstOrCreate(
            ['question_id' => $questionId, 'report_id' => $reportId],
            ['status' => 'pending'],
        );
    }

    /**
     * 提交问卷响应
     */
    public function submitResponse(int $reportId, array $answers): array
    {
        $count = 0;
        foreach ($answers as $answer) {
            $response = ComplianceQuestionnaireResponse::updateOrCreate(
                [
                    'question_id' => $answer['question_id'],
                    'report_id' => $reportId,
                ],
                [
                    'response' => $answer['response'] ?? '',
                    'evidence_refs' => $answer['evidence_refs'] ?? [],
                    'notes' => $answer['notes'] ?? '',
                    'status' => 'answered',
                    'answered_by' => auth()->id(),
                    'answered_at' => now(),
                ],
            );
            $count++;
        }

        return ['submitted' => $count];
    }

    /**
     * ─── 证据收集 ───
     */

    /**
     * 获取证据收集清单
     */
    public function getEvidenceChecklist(string $frameworkCode): array
    {
        $checklist = $frameworkCode === 'SOC2'
            ? $this->getSoc2EvidenceChecklist()
            : $this->getIso27001EvidenceChecklist();

        // 标记已收集项
        $collected = ComplianceEvidence::framework($frameworkCode)
            ->pluck('status', 'control_ref')
            ->toArray();

        foreach ($checklist as &$item) {
            $status = $collected[$item['control_ref']] ?? null;
            $item['collection_status'] = $status ?? 'not_collected';

            // 获取最新证据
            if ($status) {
                $evidence = ComplianceEvidence::framework($frameworkCode)
                    ->control($item['control_ref'])
                    ->latest()
                    ->first();
                $item['latest_evidence'] = $evidence ? [
                    'id' => $evidence->id,
                    'title' => $evidence->title,
                    'collected_at' => $evidence->collected_at?->toIso8601String(),
                    'status' => $evidence->status,
                ] : null;
            }
        }

        return $checklist;
    }

    /**
     * 自动收集证据
     */
    public function collectEvidence(string $frameworkCode, string $controlRef, string $evidenceType): ComplianceEvidence
    {
        $evidence = new ComplianceEvidence();
        $evidence->framework_code = $frameworkCode;
        $evidence->control_ref = $controlRef;
        $evidence->evidence_type = $evidenceType;
        $evidence->title = $this->getEvidenceTitle($frameworkCode, $controlRef, $evidenceType);
        $evidence->description = $this->getEvidenceDescription($frameworkCode, $controlRef, $evidenceType);
        $evidence->source = 'automated_collection';
        $evidence->collected_by = auth()->id();
        $evidence->collected_at = now();
        $evidence->status = 'collected';
        $evidence->tags = [$frameworkCode, $controlRef, $evidenceType];

        // 收集实际证据内容
        $evidence->content = $this->gatherEvidenceContent($frameworkCode, $controlRef, $evidenceType);
        $evidence->metadata = [
            'collected_via' => 'CompliancePackService',
            'collection_method' => 'automated',
            'timestamp' => now()->toIso8601String(),
        ];

        $evidence->save();

        Log::info('Compliance evidence collected', [
            'framework' => $frameworkCode,
            'control' => $controlRef,
            'type' => $evidenceType,
            'evidence_id' => $evidence->id,
        ]);

        return $evidence;
    }

    /**
     * 验证证据
     */
    public function validateEvidence(int $evidenceId, string $status, ?string $notes = null): ComplianceEvidence
    {
        $evidence = ComplianceEvidence::findOrFail($evidenceId);
        $evidence->status = $status;
        $evidence->validated_by = auth()->id();
        $evidence->validated_at = now();
        if ($notes) {
            $evidence->notes = $notes;
        }
        $evidence->save();

        return $evidence;
    }

    /**
     * ─── 差距分析 ───
     */

    /**
     * 运行差距分析
     */
    public function runGapAnalysis(string $frameworkCode, int $reportId): array
    {
        $controls = $frameworkCode === 'SOC2'
            ? $this->getSoc2Controls()
            : $this->getIso27001Controls();

        $results = [];

        foreach ($controls as $control) {
            // 检查是否已有分析记录
            $existing = ComplianceGapAnalysis::framework($frameworkCode)
                ->where('control_ref', $control['ref'])
                ->first();

            // 获取相关证据状态
            $evidenceCount = ComplianceEvidence::framework($frameworkCode)
                ->control($control['ref'])
                ->count();
            $validatedCount = ComplianceEvidence::framework($frameworkCode)
                ->control($control['ref'])
                ->validated()
                ->count();

            // 评估差距等级
            $gapLevel = $this->assessGapLevel($validatedCount, $evidenceCount, $control);

            $gap = $existing ?? new ComplianceGapAnalysis();
            $gap->framework_code = $frameworkCode;
            $gap->report_id = $reportId;
            $gap->control_ref = $control['ref'];
            $gap->control_title = $control['title'];
            $gap->current_state = $this->describeCurrentState($validatedCount, $evidenceCount);
            $gap->target_state = $control['target_state'] ?? 'fully_compliant';
            $gap->gap_description = $gapLevel['description'];
            $gap->risk_level = $gapLevel['risk'];
            $gap->priority = $gapLevel['priority'];
            $gap->remediation_status = $existing ? $existing->remediation_status : 'identified';
            if (!$existing) {
                $gap->remediation_plan = $gapLevel['remediation_plan'];
                $gap->remediation_steps = $gapLevel['steps'] ?? [];
            }
            $gap->save();

            $results[] = $gap->toArray();
        }

        return $results;
    }

    /**
     * 更新整改计划
     */
    public function updateRemediation(int $gapId, array $data): ComplianceGapAnalysis
    {
        $gap = ComplianceGapAnalysis::findOrFail($gapId);
        $gap->fill($data);

        if ($data['remediation_status'] ?? null === 'completed') {
            $gap->completed_at = now();
            $gap->verified_by = auth()->id();
        }

        $gap->save();
        return $gap;
    }

    /**
     * ─── 安全策略文档 ───
     */

    /**
     * 获取策略文档模板
     */
    public function getPolicyDocuments(string $frameworkCode): array
    {
        $cacheKey = "compliance:policies:{$frameworkCode}";

        return Cache::remember($cacheKey, 86400, function () use ($frameworkCode) {
            $docs = CompliancePolicyDocument::framework($frameworkCode)
                ->where('is_active', true)
                ->orderBy('category')
                ->get();

            if ($docs->isNotEmpty()) {
                return $docs->toArray();
            }

            return $this->seedPolicyDocuments($frameworkCode);
        });
    }

    /**
     * 种子策略文档模板
     */
    public function seedPolicyDocuments(string $frameworkCode): array
    {
        $templates = $frameworkCode === 'SOC2'
            ? $this->getSoc2PolicyTemplates()
            : $this->getIso27001PolicyTemplates();

        $created = [];
        foreach ($templates as $data) {
            $doc = CompliancePolicyDocument::firstOrCreate(
                [
                    'framework_code' => $frameworkCode,
                    'doc_key' => $data['doc_key'],
                ],
                $data,
            );
            $created[] = $doc->toArray();
        }

        return $created;
    }

    /**
     * 生成正式策略文档
     */
    public function generatePolicyDocument(int $docId, array $fieldValues): string
    {
        $doc = CompliancePolicyDocument::findOrFail($docId);

        $content = $doc->content_template;

        // 替换占位符
        foreach ($fieldValues as $key => $value) {
            $content = str_replace("{{{$key}}}", $value, $content);
        }

        // 生成文档文件名
        $slug = Str::slug($doc->title);
        $fileName = "policy_{$slug}_v{$doc->version}_" . now()->format('Ymd') . '.html';

        // 存储到文件
        Storage::disk('local')->put("compliance/policies/{$fileName}", $content);

        // 更新文档状态
        $doc->status = 'generated';
        $doc->save();

        return $fileName;
    }

    /**
     * ─── 报告导出 ───
     */

    /**
     * 生成合规报告 (PDF/HTML)
     */
    public function generateComplianceReport(int $reportId, string $format = 'html'): string
    {
        $report = ComplianceReport::with(['framework', 'generator'])->findOrFail($reportId);

        // 收集评估数据
        $gaps = ComplianceGapAnalysis::where('report_id', $reportId)->get();
        $evidenceStats = $this->getEvidenceStats($report->framework->code);

        // 生成 HTML 内容
        $html = view('compliance.report-template', [
            'report' => $report,
            'gaps' => $gaps,
            'evidenceStats' => $evidenceStats,
            'generatedAt' => now(),
        ])->render();

        $fileName = "compliance_report_{$report->framework->code}_{$report->id}_" . now()->format('Ymd');

        if ($format === 'html') {
            $fileName .= '.html';
            Storage::disk('local')->put("compliance/reports/{$fileName}", $html);
        } else {
            // PDF via dompdf
            $fileName .= '.pdf';
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
            Storage::disk('local')->put("compliance/reports/{$fileName}", $pdf->output());
        }

        return $fileName;
    }

    /**
     * ─── 仪表盘统计 ───
     */

    /**
     * 获取合规包仪表盘数据
     */
    public function getDashboard(): array
    {
        $frameworks = ComplianceFramework::whereIn('code', ['SOC2', 'ISO27001'])
            ->where('is_active', true)
            ->get();

        $stats = [];
        foreach ($frameworks as $fw) {
            $evidenceCount = ComplianceEvidence::framework($fw->code)->count();
            $validatedCount = ComplianceEvidence::framework($fw->code)->validated()->count();
            $openGaps = ComplianceGapAnalysis::framework($fw->code)->open()->count();
            $totalGaps = ComplianceGapAnalysis::framework($fw->code)->count();
            $policyCount = CompliancePolicyDocument::framework($fw->code)->where('is_active', true)->count();
            $questionsCount = ComplianceQuestionnaire::framework($fw->code)->where('is_active', true)->count();
            $answeredCount = ComplianceQuestionnaireResponse::whereHas('question', function ($q) use ($fw) {
                $q->where('framework_code', $fw->code);
            })->where('status', 'answered')->count();

            $stats[$fw->code] = [
                'framework_name' => $fw->name,
                'evidence_total' => $evidenceCount,
                'evidence_validated' => $validatedCount,
                'evidence_validation_rate' => $evidenceCount > 0 ? round(($validatedCount / $evidenceCount) * 100) : 0,
                'gaps_open' => $openGaps,
                'gaps_total' => $totalGaps,
                'gaps_resolved' => $totalGaps - $openGaps,
                'policy_count' => $policyCount,
                'questionnaire_total' => $questionsCount,
                'questionnaire_answered' => $answeredCount,
                'questionnaire_progress' => $questionsCount > 0 ? round(($answeredCount / $questionsCount) * 100) : 0,
                'readiness_score' => $this->calculateReadinessScore($evidenceCount, $validatedCount, $totalGaps, $openGaps, $questionsCount, $answeredCount),
            ];
        }

        return [
            'frameworks' => $stats,
            'overall_readiness' => $this->calculateOverallReadiness($stats),
        ];
    }

    /**
     * ─── 种子数据 ───
     */

    protected function getSoc2QuestionnaireTemplate(): array
    {
        return [
            // SEC - 安全
            ['category' => 'SEC', 'question_key' => 'sec_01', 'question' => '是否有正式的访问控制策略？', 'guidance' => '描述组织如何管理用户访问权限，包括身份验证、授权和审计。', 'control_ref' => 'SEC-1', 'severity' => 'critical', 'sort_order' => 1, 'is_required' => true, 'response_type' => 'textarea'],
            ['category' => 'SEC', 'question_key' => 'sec_02', 'question' => '是否实施了多因素认证（MFA）？', 'guidance' => '描述 MFA 的覆盖范围（管理员/所有用户/外部访问）。', 'control_ref' => 'SEC-2', 'severity' => 'critical', 'sort_order' => 2, 'is_required' => true, 'response_type' => 'select'],
            ['category' => 'SEC', 'question_key' => 'sec_03', 'question' => '是否有漏洞管理程序？', 'guidance' => '描述漏洞扫描频率、修复 SLA 和报告流程。', 'control_ref' => 'SEC-3', 'severity' => 'high', 'sort_order' => 3, 'is_required' => true, 'response_type' => 'textarea'],
            ['category' => 'SEC', 'question_key' => 'sec_04', 'question' => '是否部署了入侵检测/防御系统（IDS/IPS）？', 'guidance' => '描述使用的工具、覆盖范围和告警响应流程。', 'control_ref' => 'SEC-4', 'severity' => 'high', 'sort_order' => 4, 'is_required' => true, 'response_type' => 'textarea'],
            ['category' => 'SEC', 'question_key' => 'sec_05', 'question' => '是否有安全事件响应计划？', 'guidance' => '描述事件响应流程、团队角色和演练频率。', 'control_ref' => 'SEC-5', 'severity' => 'critical', 'sort_order' => 5, 'is_required' => true, 'response_type' => 'textarea'],
            // AVA - 可用性
            ['category' => 'AVA', 'question_key' => 'ava_01', 'question' => '是否有高可用性架构？', 'guidance' => '描述故障转移、负载均衡和多区域部署策略。', 'control_ref' => 'AVA-1', 'severity' => 'high', 'sort_order' => 6, 'is_required' => true, 'response_type' => 'textarea'],
            ['category' => 'AVA', 'question_key' => 'ava_02', 'question' => '是否有备份和恢复流程？', 'guidance' => '描述备份频率、保留策略和恢复测试结果。', 'control_ref' => 'AVA-2', 'severity' => 'critical', 'sort_order' => 7, 'is_required' => true, 'response_type' => 'textarea'],
            ['category' => 'AVA', 'question_key' => 'ava_03', 'question' => '服务等级目标（SLO）是否已定义？', 'guidance' => '列出关键服务的 SLO 以及监控方式。', 'control_ref' => 'AVA-3', 'severity' => 'high', 'sort_order' => 8, 'is_required' => true, 'response_type' => 'textarea'],
            // PID - 处理完整性
            ['category' => 'PID', 'question_key' => 'pid_01', 'question' => '是否有数据输入验证机制？', 'guidance' => '描述 API/表单层面的输入验证、Sanitization 策略。', 'control_ref' => 'PID-1', 'severity' => 'high', 'sort_order' => 9, 'is_required' => true, 'response_type' => 'textarea'],
            ['category' => 'PID', 'question_key' => 'pid_02', 'question' => '是否有数据处理审计日志？', 'guidance' => '描述日志记录范围、保留策略和防篡改措施。', 'control_ref' => 'PID-2', 'severity' => 'high', 'sort_order' => 10, 'is_required' => true, 'response_type' => 'textarea'],
            // CON - 保密性
            ['category' => 'CON', 'question_key' => 'con_01', 'question' => '是否实施了静态数据加密？', 'guidance' => '描述数据库、文件存储和备份的加密方案。', 'control_ref' => 'CON-1', 'severity' => 'critical', 'sort_order' => 11, 'is_required' => true, 'response_type' => 'textarea'],
            ['category' => 'CON', 'question_key' => 'con_02', 'question' => '是否实施了传输中数据加密？', 'guidance' => '描述 TLS 版本、证书管理和内部通信加密。', 'control_ref' => 'CON-2', 'severity' => 'critical', 'sort_order' => 12, 'is_required' => true, 'response_type' => 'textarea'],
            ['category' => 'CON', 'question_key' => 'con_03', 'question' => '是否有数据分类和标记策略？', 'guidance' => '描述数据分类级别（公开/内部/机密/受限）及处理要求。', 'control_ref' => 'CON-3', 'severity' => 'medium', 'sort_order' => 13, 'is_required' => true, 'response_type' => 'textarea'],
            // PRI - 隐私
            ['category' => 'PRI', 'question_key' => 'pri_01', 'question' => '是否有隐私政策并对外公开？', 'guidance' => '提供隐私政策 URL，描述收集的个人信息类型和用途。', 'control_ref' => 'PRI-1', 'severity' => 'high', 'sort_order' => 14, 'is_required' => true, 'response_type' => 'textarea'],
            ['category' => 'PRI', 'question_key' => 'pri_02', 'question' => '是否有数据留存和销毁策略？', 'guidance' => '描述不同类型数据的保留期限和安全销毁方法。', 'control_ref' => 'PRI-2', 'severity' => 'high', 'sort_order' => 15, 'is_required' => true, 'response_type' => 'textarea'],
            ['category' => 'PRI', 'question_key' => 'pri_03', 'question' => '是否有用户数据访问和删除机制？', 'guidance' => '描述用户如何访问、更正和删除其个人数据。', 'control_ref' => 'PRI-3', 'severity' => 'high', 'sort_order' => 16, 'is_required' => true, 'response_type' => 'textarea'],
        ];
    }

    protected function getIso27001QuestionnaireTemplate(): array
    {
        return [
            // A.5 信息安全策略
            ['category' => 'A.5', 'question_key' => 'a5_01', 'question' => '信息安全策略是否已定义、批准和发布？', 'guidance' => '描述策略的制定过程、审批人和发布时间。', 'control_ref' => 'A.5.1', 'severity' => 'critical', 'sort_order' => 1, 'is_required' => true, 'response_type' => 'textarea'],
            ['category' => 'A.5', 'question_key' => 'a5_02', 'question' => '信息安全策略是否定期评审？', 'guidance' => '描述评审频率和最近一次评审日期。', 'control_ref' => 'A.5.1', 'severity' => 'high', 'sort_order' => 2, 'is_required' => true, 'response_type' => 'textarea'],
            // A.6 组织安全
            ['category' => 'A.6', 'question_key' => 'a6_01', 'question' => '是否有信息安全角色和职责定义？', 'guidance' => '描述 CISO、安全团队和各业务部门的安全职责。', 'control_ref' => 'A.6.1', 'severity' => 'high', 'sort_order' => 3, 'is_required' => true, 'response_type' => 'textarea'],
            ['category' => 'A.6', 'question_key' => 'a6_02', 'question' => '是否有供应商安全评估流程？', 'guidance' => '描述第三方风险评估、合同安全条款和监控。', 'control_ref' => 'A.6.2', 'severity' => 'high', 'sort_order' => 4, 'is_required' => true, 'response_type' => 'textarea'],
            // A.8 资产管理
            ['category' => 'A.8', 'question_key' => 'a8_01', 'question' => '是否有资产清单和管理流程？', 'guidance' => '描述资产分类、标识和责任人。', 'control_ref' => 'A.8.1', 'severity' => 'high', 'sort_order' => 5, 'is_required' => true, 'response_type' => 'textarea'],
            ['category' => 'A.8', 'question_key' => 'a8_02', 'question' => '是否有媒介处置策略？', 'guidance' => '描述存储介质的安全处置和销毁流程。', 'control_ref' => 'A.8.3', 'severity' => 'medium', 'sort_order' => 6, 'is_required' => false, 'response_type' => 'textarea'],
            // A.9 访问控制
            ['category' => 'A.9', 'question_key' => 'a9_01', 'question' => '是否有正式的访问控制策略？', 'guidance' => '描述用户注册、权限审批和访问审查流程。', 'control_ref' => 'A.9.1', 'severity' => 'critical', 'sort_order' => 7, 'is_required' => true, 'response_type' => 'textarea'],
            ['category' => 'A.9', 'question_key' => 'a9_02', 'question' => '是否有特权访问管理？', 'guidance' => '描述管理员账号管理、PAM 工具和会话审计。', 'control_ref' => 'A.9.2', 'severity' => 'critical', 'sort_order' => 8, 'is_required' => true, 'response_type' => 'textarea'],
            // A.10 加密
            ['category' => 'A.10', 'question_key' => 'a10_01', 'question' => '是否使用了加密技术保护敏感信息？', 'guidance' => '描述加密策略、密钥管理和使用的算法。', 'control_ref' => 'A.10.1', 'severity' => 'critical', 'sort_order' => 9, 'is_required' => true, 'response_type' => 'textarea'],
            // A.11 物理安全
            ['category' => 'A.11', 'question_key' => 'a11_01', 'question' => '是否有物理安全控制措施？', 'guidance' => '描述数据中心/机房的访问控制、监控和环境控制。', 'control_ref' => 'A.11.1', 'severity' => 'high', 'sort_order' => 10, 'is_required' => true, 'response_type' => 'textarea'],
            // A.12 操作安全
            ['category' => 'A.12', 'question_key' => 'a12_01', 'question' => '是否有变更管理流程？', 'guidance' => '描述变更申请、审批、测试和部署流程。', 'control_ref' => 'A.12.1', 'severity' => 'high', 'sort_order' => 11, 'is_required' => true, 'response_type' => 'textarea'],
            ['category' => 'A.12', 'question_key' => 'a12_02', 'question' => '是否有恶意软件防护措施？', 'guidance' => '描述防病毒、EDR 和安全基线配置。', 'control_ref' => 'A.12.2', 'severity' => 'high', 'sort_order' => 12, 'is_required' => true, 'response_type' => 'textarea'],
            ['category' => 'A.12', 'question_key' => 'a12_03', 'question' => '是否有日志记录和监控机制？', 'guidance' => '描述日志范围、集中管理和异常告警。', 'control_ref' => 'A.12.4', 'severity' => 'critical', 'sort_order' => 13, 'is_required' => true, 'response_type' => 'textarea'],
            // A.13 通信安全
            ['category' => 'A.13', 'question_key' => 'a13_01', 'question' => '是否保护了网络服务的安全性？', 'guidance' => '描述网络分段、防火墙规则和远程访问安全。', 'control_ref' => 'A.13.1', 'severity' => 'high', 'sort_order' => 14, 'is_required' => true, 'response_type' => 'textarea'],
            ['category' => 'A.13', 'question_key' => 'a13_02', 'question' => '是否有信息传输策略？', 'guidance' => '描述数据传输加密、文件传输和电子邮件的安全控制。', 'control_ref' => 'A.13.2', 'severity' => 'high', 'sort_order' => 15, 'is_required' => true, 'response_type' => 'textarea'],
            // A.14 系统获取与开发
            ['category' => 'A.14', 'question_key' => 'a14_01', 'question' => '是否有安全开发生命周期（SDL）？', 'guidance' => '描述安全需求分析、代码审查、安全测试流程。', 'control_ref' => 'A.14.1', 'severity' => 'high', 'sort_order' => 16, 'is_required' => true, 'response_type' => 'textarea'],
            ['category' => 'A.14', 'question_key' => 'a14_02', 'question' => '是否有渗透测试和漏洞扫描计划？', 'guidance' => '描述测试频率、范围和处理流程。', 'control_ref' => 'A.14.2', 'severity' => 'high', 'sort_order' => 17, 'is_required' => true, 'response_type' => 'textarea'],
        ];
    }

    protected function getSoc2Controls(): array
    {
        return [
            ['ref' => 'SEC-1', 'title' => '访问控制', 'target_state' => 'fully_implemented', 'weight' => 10],
            ['ref' => 'SEC-2', 'title' => '多因素认证', 'target_state' => 'fully_implemented', 'weight' => 10],
            ['ref' => 'SEC-3', 'title' => '漏洞管理', 'target_state' => 'fully_implemented', 'weight' => 8],
            ['ref' => 'SEC-4', 'title' => '入侵检测', 'target_state' => 'fully_implemented', 'weight' => 6],
            ['ref' => 'SEC-5', 'title' => '事件响应', 'target_state' => 'fully_implemented', 'weight' => 10],
            ['ref' => 'AVA-1', 'title' => '高可用性架构', 'target_state' => 'fully_implemented', 'weight' => 8],
            ['ref' => 'AVA-2', 'title' => '备份与恢复', 'target_state' => 'fully_implemented', 'weight' => 10],
            ['ref' => 'AVA-3', 'title' => 'SLO 定义', 'target_state' => 'defined', 'weight' => 6],
            ['ref' => 'PID-1', 'title' => '数据输入验证', 'target_state' => 'fully_implemented', 'weight' => 6],
            ['ref' => 'PID-2', 'title' => '处理审计日志', 'target_state' => 'fully_implemented', 'weight' => 8],
            ['ref' => 'CON-1', 'title' => '静态数据加密', 'target_state' => 'fully_implemented', 'weight' => 10],
            ['ref' => 'CON-2', 'title' => '传输中数据加密', 'target_state' => 'fully_implemented', 'weight' => 10],
            ['ref' => 'CON-3', 'title' => '数据分类', 'target_state' => 'defined', 'weight' => 6],
            ['ref' => 'PRI-1', 'title' => '隐私政策', 'target_state' => 'published', 'weight' => 8],
            ['ref' => 'PRI-2', 'title' => '数据留存策略', 'target_state' => 'defined', 'weight' => 6],
            ['ref' => 'PRI-3', 'title' => '用户数据访问', 'target_state' => 'fully_implemented', 'weight' => 8],
        ];
    }

    protected function getIso27001Controls(): array
    {
        return [
            ['ref' => 'A.5.1', 'title' => '信息安全策略', 'target_state' => 'fully_implemented', 'weight' => 8],
            ['ref' => 'A.5.2', 'title' => '策略评审', 'target_state' => 'scheduled', 'weight' => 4],
            ['ref' => 'A.6.1', 'title' => '安全角色与职责', 'target_state' => 'fully_implemented', 'weight' => 8],
            ['ref' => 'A.6.2', 'title' => '供应商安全', 'target_state' => 'partially_implemented', 'weight' => 6],
            ['ref' => 'A.8.1', 'title' => '资产清单', 'target_state' => 'fully_implemented', 'weight' => 6],
            ['ref' => 'A.8.3', 'title' => '媒介处置', 'target_state' => 'defined', 'weight' => 4],
            ['ref' => 'A.9.1', 'title' => '访问控制策略', 'target_state' => 'fully_implemented', 'weight' => 10],
            ['ref' => 'A.9.2', 'title' => '特权访问管理', 'target_state' => 'fully_implemented', 'weight' => 10],
            ['ref' => 'A.10.1', 'title' => '加密控制', 'target_state' => 'fully_implemented', 'weight' => 10],
            ['ref' => 'A.11.1', 'title' => '物理安全', 'target_state' => 'fully_implemented', 'weight' => 8],
            ['ref' => 'A.12.1', 'title' => '变更管理', 'target_state' => 'fully_implemented', 'weight' => 8],
            ['ref' => 'A.12.2', 'title' => '恶意软件防护', 'target_state' => 'fully_implemented', 'weight' => 8],
            ['ref' => 'A.12.4', 'title' => '日志与监控', 'target_state' => 'fully_implemented', 'weight' => 10],
            ['ref' => 'A.13.1', 'title' => '网络安全', 'target_state' => 'fully_implemented', 'weight' => 8],
            ['ref' => 'A.13.2', 'title' => '信息传输', 'target_state' => 'fully_implemented', 'weight' => 6],
            ['ref' => 'A.14.1', 'title' => '安全开发生命周期', 'target_state' => 'fully_implemented', 'weight' => 8],
            ['ref' => 'A.14.2', 'title' => '渗透测试', 'target_state' => 'scheduled', 'weight' => 6],
        ];
    }

    protected function getSoc2EvidenceChecklist(): array
    {
        return [
            ['control_ref' => 'SEC-1', 'title' => '访问控制策略文档', 'evidence_type' => 'policy_document', 'description' => '正式的访问控制策略文档', 'suggested_source' => '安全策略文档库'],
            ['control_ref' => 'SEC-2', 'title' => 'MFA 配置截图', 'evidence_type' => 'configuration_snapshot', 'description' => '多因素认证配置截图和日志', 'suggested_source' => '认证系统管理界面'],
            ['control_ref' => 'SEC-3', 'title' => '漏洞扫描报告', 'evidence_type' => 'scan_report', 'description' => '最近的漏洞扫描报告和修复记录', 'suggested_source' => '漏洞扫描工具'],
            ['control_ref' => 'SEC-4', 'title' => 'IDS/IPS 告警记录', 'evidence_type' => 'log_sample', 'description' => 'IDS/IPS 告警和响应记录样本', 'suggested_source' => 'SIEM/安全监控'],
            ['control_ref' => 'SEC-5', 'title' => '事件响应计划文档', 'evidence_type' => 'policy_document', 'description' => '安全事件响应计划和演练记录', 'suggested_source' => '安全文档库'],
            ['control_ref' => 'AVA-1', 'title' => '架构图（高可用）', 'evidence_type' => 'configuration_snapshot', 'description' => '系统架构图显示故障转移/负载均衡', 'suggested_source' => 'DevOps/架构文档'],
            ['control_ref' => 'AVA-2', 'title' => '备份验证报告', 'evidence_type' => 'scan_report', 'description' => '最近的备份恢复测试报告', 'suggested_source' => '备份系统'],
            ['control_ref' => 'AVA-3', 'title' => 'SLO 监控仪表盘', 'evidence_type' => 'log_sample', 'description' => 'SLO 达标率监控仪表盘截图', 'suggested_source' => '可观测性平台'],
            ['control_ref' => 'PID-1', 'title' => '输入验证测试报告', 'evidence_type' => 'scan_report', 'description' => '输入验证测试结果', 'suggested_source' => 'QA/测试团队'],
            ['control_ref' => 'PID-2', 'title' => '审计日志样本', 'evidence_type' => 'log_sample', 'description' => '数据处理审计日志样本（脱敏）', 'suggested_source' => '审计日志系统'],
            ['control_ref' => 'CON-1', 'title' => '数据加密配置', 'evidence_type' => 'configuration_snapshot', 'description' => '静态数据加密配置和密钥管理策略', 'suggested_source' => '数据库/存储配置'],
            ['control_ref' => 'CON-2', 'title' => 'TLS 配置审计', 'evidence_type' => 'scan_report', 'description' => 'TLS/SSL 配置审计结果', 'suggested_source' => '网络安全扫描'],
            ['control_ref' => 'CON-3', 'title' => '数据分类策略文档', 'evidence_type' => 'policy_document', 'description' => '数据分类和标记策略文档', 'suggested_source' => '数据治理文档'],
            ['control_ref' => 'PRI-1', 'title' => '隐私政策 URL', 'evidence_type' => 'configuration_snapshot', 'description' => '公开隐私政策的 URL 和最后更新日期', 'suggested_source' => '网站/法律团队'],
            ['control_ref' => 'PRI-2', 'title' => '数据留存策略文档', 'evidence_type' => 'policy_document', 'description' => '数据留存和销毁策略文档', 'suggested_source' => '合规文档'],
            ['control_ref' => 'PRI-3', 'title' => 'DSR 处理记录', 'evidence_type' => 'log_sample', 'description' => '最近的数据主体请求处理记录', 'suggested_source' => 'DSR 管理系统'],
        ];
    }

    protected function getIso27001EvidenceChecklist(): array
    {
        return [
            ['control_ref' => 'A.5.1', 'title' => '信息安全策略文档', 'evidence_type' => 'policy_document', 'description' => '经审批的信息安全策略文档', 'suggested_source' => '文档管理系统'],
            ['control_ref' => 'A.5.2', 'title' => '策略评审记录', 'evidence_type' => 'log_sample', 'description' => '信息安全策略定期评审会议记录', 'suggested_source' => '内部审计记录'],
            ['control_ref' => 'A.6.1', 'title' => '安全组织架构图', 'evidence_type' => 'policy_document', 'description' => '信息安全角色和职责定义文档', 'suggested_source' => 'HR/组织管理'],
            ['control_ref' => 'A.6.2', 'title' => '供应商风险评估', 'evidence_type' => 'scan_report', 'description' => '第三方供应商安全评估报告', 'suggested_source' => '采购/供应商管理'],
            ['control_ref' => 'A.8.1', 'title' => '资产清单', 'evidence_type' => 'configuration_snapshot', 'description' => '信息系统资产清单（脱敏）', 'suggested_source' => 'IT 资产管理'],
            ['control_ref' => 'A.8.3', 'title' => '介质处置记录', 'evidence_type' => 'log_sample', 'description' => '存储介质销毁/安全处置记录', 'suggested_source' => 'IT 运维'],
            ['control_ref' => 'A.9.1', 'title' => '访问控制矩阵', 'evidence_type' => 'configuration_snapshot', 'description' => '系统访问控制矩阵和权限表', 'suggested_source' => 'IAM 系统'],
            ['control_ref' => 'A.9.2', 'title' => '特权账号审计日志', 'evidence_type' => 'log_sample', 'description' => '管理员操作审计日志样本', 'suggested_source' => 'PAM/审计系统'],
            ['control_ref' => 'A.10.1', 'title' => '加密策略文档', 'evidence_type' => 'policy_document', 'description' => '加密策略和密钥管理实践文档', 'suggested_source' => '安全架构文档'],
            ['control_ref' => 'A.11.1', 'title' => '物理安全审计记录', 'evidence_type' => 'scan_report', 'description' => '数据中心物理安全审计报告', 'suggested_source' => '物理安全团队'],
            ['control_ref' => 'A.12.1', 'title' => '变更管理记录样本', 'evidence_type' => 'log_sample', 'description' => '变更管理流程记录样本', 'suggested_source' => 'ITSM/变更管理'],
            ['control_ref' => 'A.12.2', 'title' => '防病毒控制报告', 'evidence_type' => 'scan_report', 'description' => '端点保护/防病毒控制状态报告', 'suggested_source' => '安全运营团队'],
            ['control_ref' => 'A.12.4', 'title' => '日志管理配置', 'evidence_type' => 'configuration_snapshot', 'description' => '日志集中管理和保留策略配置', 'suggested_source' => 'SIEM/日志管理'],
            ['control_ref' => 'A.13.1', 'title' => '网络拓扑图', 'evidence_type' => 'configuration_snapshot', 'description' => '网络安全架构图和防火墙规则集', 'suggested_source' => '网络团队'],
            ['control_ref' => 'A.13.2', 'title' => '数据传输安全策略', 'evidence_type' => 'policy_document', 'description' => '数据传输加密和安全传输策略', 'suggested_source' => '安全策略文档'],
            ['control_ref' => 'A.14.1', 'title' => '安全开发指南', 'evidence_type' => 'policy_document', 'description' => '安全编码标准和 SDL 流程文档', 'suggested_source' => '工程团队'],
            ['control_ref' => 'A.14.2', 'title' => '渗透测试报告', 'evidence_type' => 'scan_report', 'description' => '最近的渗透测试和漏洞扫描报告', 'suggested_source' => '外部安全团队'],
        ];
    }

    protected function getSoc2PolicyTemplates(): array
    {
        return [
            ['category' => '信息安全', 'doc_key' => 'info_security_policy', 'title' => '信息安全总体策略', 'description' => '定义组织信息安全目标和原则的顶层策略文档', 'content_template' => '<h1>信息安全策略</h1><p>版本: {{version}}</p><p>组织: {{organization_name}}</p><h2>1. 目的</h2><p>{{purpose}}</p><h2>2. 范围</h2><p>{{scope}}</p><h2>3. 策略声明</h2><p>{{policy_statements}}</p><h2>4. 角色与职责</h2><p>{{roles_responsibilities}}</p><h2>5. 合规</h2><p>{{compliance}}</p><p>生效日期: {{effective_date}}</p><p>批准人: {{approver}}</p>', 'placeholder_fields' => '["version","organization_name","purpose","scope","policy_statements","roles_responsibilities","compliance","effective_date","approver"]', 'version' => '1.0'],
            ['category' => '访问控制', 'doc_key' => 'access_control_policy', 'title' => '访问控制策略', 'description' => '管理系统用户访问权限的策略', 'content_template' => '<h1>访问控制策略</h1><p>版本: {{version}}</p><h2>1. 用户注册与注销</h2><p>{{user_provisioning}}</p><h2>2. 权限管理</h2><p>{{permission_management}}</p><h2>3. 认证要求</h2><p>{{authentication}}</p><h2>4. 访问审查</h2><p>{{access_review}}</p><p>生效日期: {{effective_date}}</p>', 'placeholder_fields' => '["version","user_provisioning","permission_management","authentication","access_review","effective_date"]', 'version' => '1.0'],
            ['category' => '加密', 'doc_key' => 'cryptography_policy', 'title' => '加密与密钥管理策略', 'description' => '定义数据加密和密钥管理要求的策略', 'content_template' => '<h1>加密策略</h1><p>版本: {{version}}</p><h2>1. 加密要求</h2><p>{{encryption_requirements}}</p><h2>2. 密钥管理</h2><p>{{key_management}}</p><h2>3. 证书管理</h2><p>{{certificate_management}}</p><p>生效日期: {{effective_date}}</p>', 'placeholder_fields' => '["version","encryption_requirements","key_management","certificate_management","effective_date"]', 'version' => '1.0'],
            ['category' => '事件响应', 'doc_key' => 'incident_response_plan', 'title' => '安全事件响应计划', 'description' => '定义安全事件的检测、报告和响应流程', 'content_template' => '<h1>安全事件响应计划</h1><p>版本: {{version}}</p><h2>1. 事件定义与分类</h2><p>{{incident_definitions}}</p><h2>2. 响应团队</h2><p>{{response_team}}</p><h2>3. 检测与报告</h2><p>{{detection_reporting}}</p><h2>4. 响应流程</h2><p>{{response_process}}</p><h2>5. 事后复盘</h2><p>{{post_mortem}}</p>', 'placeholder_fields' => '["version","incident_definitions","response_team","detection_reporting","response_process","post_mortem"]', 'version' => '1.0'],
            ['category' => '可用性', 'doc_key' => 'business_continuity', 'title' => '业务连续性计划', 'description' => '确保在中断事件中业务持续运行的策略', 'content_template' => '<h1>业务连续性计划</h1><p>版本: {{version}}</p><h2>1. BIA 结果</h2><p>{{bia_results}}</p><h2>2. 恢复策略</h2><p>{{recovery_strategy}}</p><h2>3. 备份策略</h2><p>{{backup_strategy}}</p><h2>4. 测试计划</h2><p>{{testing_plan}}</p>', 'placeholder_fields' => '["version","bia_results","recovery_strategy","backup_strategy","testing_plan"]', 'version' => '1.0'],
            ['category' => '隐私', 'doc_key' => 'privacy_policy', 'title' => '隐私策略', 'description' => '定义个人信息收集、使用和保护的原则', 'content_template' => '<h1>隐私策略</h1><p>版本: {{version}}</p><h2>1. 信息收集</h2><p>{{information_collection}}</p><h2>2. 信息使用</h2><p>{{information_use}}</p><h2>3. 信息分享</h2><p>{{information_sharing}}</p><h2>4. 用户权利</h2><p>{{user_rights}}</p><h2>5. 数据保留</h2><p>{{data_retention}}</p>', 'placeholder_fields' => '["version","information_collection","information_use","information_sharing","user_rights","data_retention"]', 'version' => '1.0'],
        ];
    }

    protected function getIso27001PolicyTemplates(): array
    {
        return [
            ['category' => '信息安全', 'doc_key' => 'iso_info_security_policy', 'title' => '信息安全策略（ISO 27001）', 'description' => '符合 ISO 27001:2022 A.5 要求的顶层信息安全策略', 'content_template' => '<h1>信息安全策略</h1><p>依据 ISO 27001:2022</p><p>版本: {{version}}</p><p>组织: {{organization_name}}</p><h2>1. 信息安全目标</h2><p>{{objectives}}</p><h2>2. 范围声明</h2><p>{{scope}}</p><h2>3. 策略框架</h2><p>{{policy_framework}}</p><h2>4. 持续改进</h2><p>{{continuous_improvement}}</p><p>生效日期: {{effective_date}}</p>', 'placeholder_fields' => '["version","organization_name","objectives","scope","policy_framework","continuous_improvement","effective_date"]', 'version' => '1.0'],
            ['category' => '操作安全', 'doc_key' => 'change_management_policy', 'title' => '变更管理策略', 'description' => '符合 ISO 27001 A.12.1 的变更管理流程', 'content_template' => '<h1>变更管理策略</h1><p>版本: {{version}}</p><h2>1. 变更分类</h2><p>{{change_classification}}</p><h2>2. 变更审批流程</h2><p>{{approval_process}}</p><h2>3. 测试要求</h2><p>{{testing_requirements}}</p><h2>4. 紧急变更</h2><p>{{emergency_changes}}</p>', 'placeholder_fields' => '["version","change_classification","approval_process","testing_requirements","emergency_changes"]', 'version' => '1.0'],
            ['category' => '访问控制', 'doc_key' => 'iso_access_control', 'title' => '访问控制策略（ISO 27001）', 'description' => '符合 ISO 27001 A.9 要求的访问控制策略', 'content_template' => '<h1>访问控制策略</h1><p>依据 ISO 27001:2022 A.9</p><p>版本: {{version}}</p><h2>1. 业务访问控制要求</h2><p>{{business_requirements}}</p><h2>2. 用户访问管理</h2><p>{{user_access_management}}</p><h2>3. 用户职责</h2><p>{{user_responsibilities}}</p><h2>4. 系统与应用访问控制</h2><p>{{system_access_control}}</p>', 'placeholder_fields' => '["version","business_requirements","user_access_management","user_responsibilities","system_access_control"]', 'version' => '1.0'],
            ['category' => '供应商安全', 'doc_key' => 'supplier_security', 'title' => '供应商安全策略', 'description' => '符合 ISO 27001 A.6.2 的供应商安全管理', 'content_template' => '<h1>供应商安全策略</h1><p>版本: {{version}}</p><h2>1. 供应商分类</h2><p>{{supplier_classification}}</p><h2>2. 安全评估</h2><p>{{security_assessment}}</p><h2>3. 合同安全条款</h2><p>{{contractual_clauses}}</p><h2>4. 持续监控</h2><p>{{ongoing_monitoring}}</p>', 'placeholder_fields' => '["version","supplier_classification","security_assessment","contractual_clauses","ongoing_monitoring"]', 'version' => '1.0'],
        ];
    }

    /**
     * ─── 辅助方法 ───
     */

    protected function assessGapLevel(int $validatedCount, int $evidenceCount, array $control): array
    {
        if ($validatedCount >= 1) {
            return [
                'risk' => 'low',
                'priority' => 'low',
                'description' => '已满足控制要求',
                'remediation_plan' => '无需整改',
                'steps' => [],
            ];
        }

        if ($evidenceCount >= 1) {
            return [
                'risk' => 'medium',
                'priority' => 'medium',
                'description' => '证据已收集但未验证',
                'remediation_plan' => '安排证据验证',
                'steps' => ['安排安全负责人验证证据', '记录验证结果和日期'],
            ];
        }

        if ($control['weight'] >= 8) {
            return [
                'risk' => 'high',
                'priority' => 'high',
                'description' => '高权重控制域缺少任何证据',
                'remediation_plan' => '立即收集并实施控制措施',
                'steps' => ['分配负责人', '制定实施计划', '收集证据', '安排验证'],
            ];
        }

        return [
            'risk' => 'medium',
            'priority' => 'medium',
            'description' => '控制域证据未收集',
            'remediation_plan' => '按照证据清单收集所需证据',
            'steps' => ['参考证据清单收集证据', '提交证据进行验证'],
        ];
    }

    protected function describeCurrentState(int $validatedCount, int $evidenceCount): string
    {
        if ($validatedCount >= 1) return 'fully_compliant';
        if ($evidenceCount >= 1) return 'evidence_collected';
        return 'not_started';
    }

    protected function getEvidenceTitle(string $frameworkCode, string $controlRef, string $evidenceType): string
    {
        $titles = [
            'policy_document' => "{$frameworkCode}/{$controlRef} 策略文档",
            'configuration_snapshot' => "{$frameworkCode}/{$controlRef} 配置快照",
            'scan_report' => "{$frameworkCode}/{$controlRef} 扫描报告",
            'log_sample' => "{$frameworkCode}/{$controlRef} 日志样本",
        ];
        return $titles[$evidenceType] ?? "{$frameworkCode}/{$controlRef} 证据";
    }

    protected function getEvidenceDescription(string $frameworkCode, string $controlRef, string $evidenceType): string
    {
        $descs = [
            'policy_document' => "自动收集的 {$frameworkCode} {$controlRef} 策略文档证据",
            'configuration_snapshot' => "自动收集的 {$frameworkCode} {$controlRef} 配置快照",
            'scan_report' => "自动收集的 {$frameworkCode} {$controlRef} 扫描报告",
            'log_sample' => "自动收集的 {$frameworkCode} {$controlRef} 日志样本",
        ];
        return $descs[$evidenceType] ?? "{$frameworkCode} {$controlRef} 自动收集证据";
    }

    protected function gatherEvidenceContent(string $frameworkCode, string $controlRef, string $evidenceType): string
    {
        // 实际场景中应根据控制域类型从系统自动收集
        // 这里是框架性实现
        $data = [
            'framework' => $frameworkCode,
            'control' => $controlRef,
            'evidence_type' => $evidenceType,
            'collection_timestamp' => now()->toIso8601String(),
            'source_system' => config('app.url'),
            'status' => 'auto_collected',
        ];

        return json_encode($data, JSON_PRETTY_PRINT);
    }

    protected function getEvidenceStats(string $frameworkCode): array
    {
        $total = ComplianceEvidence::framework($frameworkCode)->count();
        $validated = ComplianceEvidence::framework($frameworkCode)->validated()->count();
        $byType = ComplianceEvidence::framework($frameworkCode)
            ->selectRaw('evidence_type, COUNT(*) as count')
            ->groupBy('evidence_type')
            ->pluck('count', 'evidence_type')
            ->toArray();

        return [
            'total' => $total,
            'validated' => $validated,
            'by_type' => $byType,
        ];
    }

    protected function calculateReadinessScore(int $evidence, int $validated, int $totalGaps, int $openGaps, int $totalQuestions, int $answered): int
    {
        $scores = [];

        // 证据分数 (40%)
        if ($evidence > 0) {
            $scores[] = ($validated / max($evidence, 1)) * 40;
        }

        // 差距分数 (30%)
        if ($totalGaps > 0) {
            $scores[] = (1 - $openGaps / max($totalGaps, 1)) * 30;
        }

        // 问卷分数 (30%)
        if ($totalQuestions > 0) {
            $scores[] = ($answered / max($totalQuestions, 1)) * 30;
        }

        if (empty($scores)) {
            return 0;
        }

        return min(100, (int) round(array_sum($scores)));
    }

    protected function calculateOverallReadiness(array $frameworkStats): int
    {
        $scores = array_column($frameworkStats, 'readiness_score');
        if (empty($scores)) return 0;
        return (int) round(array_sum($scores) / count($scores));
    }
}
