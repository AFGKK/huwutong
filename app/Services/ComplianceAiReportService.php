<?php

namespace App\Services;

use App\Models\ComplianceAiReport;
use App\Models\ComplianceEvidence;
use App\Models\ComplianceFramework;
use App\Models\ComplianceGapAnalysis;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * AI 合规报告生成服务 (M3-38)
 *
 * 基于 LLM 自动生成合规报告，支持多框架：
 * - SOC 2 Type II 审计报告
 * - ISO 27001 差距分析报告
 * - GDPR 合规评估报告
 * - PIPL 合规评估报告
 * - 自定义合规框架
 *
 * 利用 ComplianceAiReport 模型存储报告，
 * ComplianceEvidence 模型自动收集证据链。
 */
class ComplianceAiReportService
{
    const FRAMEWORKS = [
        'soc2' => 'SOC 2 Type II',
        'iso27001' => 'ISO 27001',
        'gdpr' => 'GDPR',
        'pipl' => 'PIPL',
        'custom' => '自定义框架',
    ];

    public function __construct(
        protected LlmService $llmService,
    ) {}

    // ─── 报告生成 ───

    /**
     * 生成合规报告
     */
    public function generateReport(
        Tenant $tenant,
        string $framework,
        string $language = 'zh',
        ?int $generatedBy = null,
        array $options = [],
    ): ComplianceAiReport {
        // 1. 收集证据
        $evidence = $this->collectEvidence($tenant, $framework);

        // 2. 获取框架要求
        $requirements = $this->getFrameworkRequirements($framework);

        // 3. 计算差距分析
        $gapAnalysis = $this->performGapAnalysis($evidence, $requirements);

        // 4. AI 生成报告
        $report = $this->aiGenerateReport($tenant, $framework, $language, $evidence, $gapAnalysis);

        // 5. 持久化
        $complianceReport = DB::transaction(function () use ($tenant, $framework, $language, $report, $generatedBy, $gapAnalysis, $evidence) {
            $sections = $report['sections'] ?? [];
            $recommendations = $report['recommendations'] ?? [];

            $record = ComplianceAiReport::create([
                'tenant_id' => $tenant->id,
                'framework' => $framework,
                'title' => self::FRAMEWORKS[$framework] ?? "{$framework} 合规报告",
                'status' => 'completed',
                'sections' => $sections,
                'evidence_summary' => $this->buildEvidenceSummary($evidence),
                'gap_analysis' => $gapAnalysis,
                'recommendations' => $recommendations,
                'ai_prompt' => $report['prompt'] ?? '',
                'ai_response' => $report['raw_response'] ?? '',
                'language' => $language,
                'generated_by' => $generatedBy,
                'generated_at' => now(),
            ]);

            // 保存报告文件
            $filePath = $this->saveReportFile($record, $report);
            if ($filePath) {
                $record->update(['file_path' => $filePath]);
            }

            return $record;
        });

        return $complianceReport;
    }

    /**
     * 收集合规证据
     */
    protected function collectEvidence(Tenant $tenant, string $framework): array
    {
        $evidence = ComplianceEvidence::where('tenant_id', $tenant->id)
            ->where('framework_code', $framework)
            ->where('status', 'validated')
            ->get()
            ->toArray();

        if (empty($evidence)) {
            Log::info("无已验证证据，尝试收集最新证据", [
                'tenant_id' => $tenant->id,
                'framework' => $framework,
            ]);

            // 触发证据收集（委派给具体收集器）
            $this->triggerEvidenceCollection($tenant, $framework);

            // 重新读取
            $evidence = ComplianceEvidence::where('tenant_id', $tenant->id)
                ->where('framework_code', $framework)
                ->where('status', 'validated')
                ->get()
                ->toArray();
        }

        return $evidence;
    }

    /**
     * 触发自动化证据收集
     */
    protected function triggerEvidenceCollection(Tenant $tenant, string $framework): void
    {
        // 不同框架的证据收集策略
        $collectors = match ($framework) {
            'soc2' => [
                'access_control' => '收集访问控制配置和日志',
                'encryption' => '收集加密设置和密钥管理记录',
                'monitoring' => '收集监控和告警配置',
                'backup' => '收集备份策略和恢复演练记录',
                'change_management' => '收集变更管理记录',
            ],
            'iso27001' => [
                'isk' => '信息安全管理体系文档',
                'access_control' => '访问控制策略和日志',
                'incident_response' => '安全事件响应记录',
                'business_continuity' => '业务连续性计划',
                'vendor_management' => '供应商管理记录',
            ],
            'gdpr' => [
                'data_inventory' => '数据资产清单',
                'consent_records' => '用户同意记录',
                'dpa' => '数据处理协议',
                'dpo_contact' => '数据保护官联系方式',
                'breach_notification' => '泄露通知记录',
            ],
            'pipl' => [
                'personal_info_list' => '个人信息收集清单',
                'consent_mechanism' => '单独同意机制配置',
                'cross_border' => '跨境数据传输评估',
                'pia' => '个人信息保护影响评估',
                'dpo' => '数据保护负责人设置',
            ],
            default => [],
        };

        foreach ($collectors as $type => $description) {
            try {
                ComplianceEvidence::updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'framework_code' => $framework,
                        'evidence_type' => $type,
                    ],
                    [
                        'title' => $description,
                        'status' => 'pending',
                        'collected_at' => now(),
                    ]
                );
            } catch (\Throwable $e) {
                Log::warning("证据收集失败", [
                    'tenant_id' => $tenant->id,
                    'framework' => $framework,
                    'type' => $type,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * 获取框架要求
     */
    protected function getFrameworkRequirements(string $framework): array
    {
        $requirements = ComplianceFramework::where('code', $framework)
            ->with('controls')
            ->first();

        return $requirements ? $requirements->toArray() : $this->getDefaultRequirements($framework);
    }

    /**
     * 内置默认框架要求（作为兜底）
     */
    protected function getDefaultRequirements(string $framework): array
    {
        return match ($framework) {
            'soc2' => [
                'name' => 'SOC 2 Type II',
                'controls' => [
                    'CC1' => 'Control Environment',
                    'CC2' => 'Communication and Information',
                    'CC3' => 'Risk Assessment',
                    'CC4' => 'Monitoring Activities',
                    'CC5' => 'Control Activities',
                    'CC6' => 'Logical and Physical Access',
                    'CC7' => 'System Operations',
                    'CC8' => 'Change Management',
                    'CC9' => 'Risk Mitigation',
                ],
            ],
            'iso27001' => [
                'name' => 'ISO 27001:2022',
                'controls' => [
                    'A.5' => 'Information Security Policies',
                    'A.6' => 'Organization of Information Security',
                    'A.7' => 'Human Resource Security',
                    'A.8' => 'Asset Management',
                    'A.9' => 'Access Control',
                    'A.10' => 'Cryptography',
                    'A.11' => 'Physical and Environmental Security',
                    'A.12' => 'Operations Security',
                    'A.13' => 'Communications Security',
                    'A.14' => 'System Acquisition, Development and Maintenance',
                    'A.15' => 'Supplier Relationships',
                    'A.16' => 'Incident Management',
                    'A.17' => 'Business Continuity',
                    'A.18' => 'Compliance',
                ],
            ],
            'gdpr' => [
                'name' => 'General Data Protection Regulation',
                'controls' => [
                    'Art.5' => 'Principles of Processing',
                    'Art.6' => 'Lawfulness of Processing',
                    'Art.7' => 'Conditions for Consent',
                    'Art.12' => 'Transparent Information',
                    'Art.15' => 'Right of Access',
                    'Art.17' => 'Right to Erasure',
                    'Art.20' => 'Right to Data Portability',
                    'Art.25' => 'Data Protection by Design',
                    'Art.30' => 'Records of Processing',
                    'Art.32' => 'Security of Processing',
                    'Art.33' => 'Breach Notification',
                    'Art.35' => 'Data Protection Impact Assessment',
                ],
            ],
            'pipl' => [
                'name' => 'Personal Information Protection Law of China',
                'controls' => [
                    'Art.6' => 'Minimization Principle',
                    'Art.7' => 'Consent Requirement',
                    'Art.13' => 'Legal Bases for Processing',
                    'Art.14' => 'Separate Consent',
                    'Art.17' => 'Information Collection Notice',
                    'Art.21' => 'Entrusted Processing',
                    'Art.23' => 'Provision to Other Processors',
                    'Art.28' => 'Sensitive Personal Information',
                    'Art.30' => 'Notice for Sensitive PI',
                    'Art.38' => 'Cross-border Transfer',
                    'Art.51' => 'Security Measures',
                    'Art.55' => 'PIA Requirement',
                    'Art.57' => 'Breach Notification',
                ],
            ],
            default => [
                'name' => $framework,
                'controls' => [],
            ],
        };
    }

    /**
     * 执行差距分析
     */
    protected function performGapAnalysis(array $evidence, array $requirements): array
    {
        $gaps = [];
        $controls = $requirements['controls'] ?? [];

        foreach ($controls as $ref => $name) {
            $matched = array_filter($evidence, fn($e) =>
                str_contains($e['control_ref'] ?? '', $ref)
                || str_contains($e['evidence_type'] ?? '', $ref)
            );

            $gaps[] = [
                'control_ref' => $ref,
                'control_name' => is_string($name) ? $name : ($name['name'] ?? ''),
                'status' => empty($matched) ? 'gap' : 'covered',
                'evidence_count' => count($matched),
                'evidence_items' => array_map(fn($e) => [
                    'id' => $e['id'],
                    'title' => $e['title'],
                    'status' => $e['status'],
                    'collected_at' => $e['collected_at'],
                ], $matched),
            ];
        }

        $total = count($gaps);
        $covered = count(array_filter($gaps, fn($g) => $g['status'] === 'covered'));

        return [
            'total_controls' => $total,
            'covered' => $covered,
            'gaps' => $total - $covered,
            'coverage_rate' => $total > 0 ? round($covered / $total * 100, 2) : 0,
            'details' => $gaps,
        ];
    }

    /**
     * AI 生成报告正文
     */
    protected function aiGenerateReport(
        Tenant $tenant,
        string $framework,
        string $language,
        array $evidence,
        array $gapAnalysis,
    ): array {
        $frameworkName = self::FRAMEWORKS[$framework] ?? $framework;
        $evidenceSummary = collect($evidence)->groupBy('evidence_type')->map(fn($items) => $items->count())->toArray();
        $gapSummary = $gapAnalysis['details'] ?? [];

        $outputLang = $language === 'zh' ? '中文' : '英文';
        $prompt = "你是一位专业的合规分析师。请为以下企业生成 {$frameworkName} 合规报告。\n\n"
            . "企业信息：\n"
            . "- 名称：{$tenant->name}\n"
            . "- 行业：授权管理/SaaS\n\n"
            . "证据摘要：\n" . json_encode($evidenceSummary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n"
            . "差距分析：\n" . json_encode($gapSummary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n"
            . "覆盖情况：{$gapAnalysis['covered']}/{$gapAnalysis['total_controls']} ({$gapAnalysis['coverage_rate']}%)\n\n"
            . "请生成报告，包含以下章节：\n"
            . "1. 执行摘要（Executive Summary）- 整体合规状态概述\n"
            . "2. 范围和方法论（Scope & Methodology）\n"
            . "3. 控制域评估（Control Assessment）- 逐项分析\n"
            . "4. 差距分析总结（Gap Analysis Summary）\n"
            . "5. 改进建议（Recommendations）- 按优先级排列\n"
            . "6. 结论（Conclusion）\n\n"
            . "请用 {$outputLang} 输出，使用专业合规报告的语气。";

        try {
            $response = $this->llmService->chat($prompt);

            // 解析 LLM 输出为结构化章节
            $sections = $this->parseSections($response);

            return [
                'sections' => $sections,
                'recommendations' => $this->extractRecommendations($response),
                'prompt' => $prompt,
                'raw_response' => $response,
            ];
        } catch (\Throwable $e) {
            Log::error('AI 合规报告生成失败', [
                'tenant_id' => $tenant->id,
                'framework' => $framework,
                'error' => $e->getMessage(),
            ]);

            // 降级：生成结构化报告而不依赖 AI
            return $this->fallbackReport($tenant, $framework, $language, $evidence, $gapAnalysis);
        }
    }

    /**
     * 降级方案：不依赖 AI 的结构化报告
     */
    protected function fallbackReport(Tenant $tenant, string $framework, string $language, array $evidence, array $gapAnalysis): array
    {
        $isZh = $language === 'zh';

        $sections = [
            [
                'title' => $isZh ? '执行摘要' : 'Executive Summary',
                'content' => $isZh
                    ? "本报告为 {$tenant->name} 的 " . (self::FRAMEWORKS[$framework] ?? $framework) . " 合规评估。"
                    . "当前覆盖率为 {$gapAnalysis['coverage_rate']}%（{$gapAnalysis['covered']}/{$gapAnalysis['total_controls']}）。"
                    . "系统共收集 " . count($evidence) . " 条证据，发现 {$gapAnalysis['gaps']} 个差距项。"
                    : "This report presents the " . (self::FRAMEWORKS[$framework] ?? $framework) . " compliance assessment for {$tenant->name}. "
                    . "Current coverage rate is {$gapAnalysis['coverage_rate']}% ({$gapAnalysis['covered']}/{$gapAnalysis['total_controls']}). "
                    . "A total of " . count($evidence) . " evidence items collected, with {$gapAnalysis['gaps']} gaps identified.",
            ],
            [
                'title' => $isZh ? '范围和方法论' : 'Scope & Methodology',
                'content' => $isZh
                    ? "评估范围涵盖互物通授权管理系统的全部控制域。"
                      . "评估方法包括：自动化证据收集、控制点映射、差距分析。"
                      . "证据来源包括系统日志、配置快照、访问控制列表、加密设置等。"
                    : "The assessment covers all control domains of the Huwutong License Management System. "
                      . "Methodology includes: automated evidence collection, control mapping, and gap analysis. "
                      . "Evidence sources include system logs, configuration snapshots, access control lists, encryption settings, etc.",
            ],
            [
                'title' => $isZh ? '控制域评估' : 'Control Assessment',
                'content' => $isZh
                    ? "共评估 {$gapAnalysis['total_controls']} 个控制点，其中 {$gapAnalysis['covered']} 个已覆盖，{$gapAnalysis['gaps']} 个存在差距。"
                    : "Total {$gapAnalysis['total_controls']} controls assessed, {$gapAnalysis['covered']} covered, {$gapAnalysis['gaps']} gaps identified.",
                'sub_sections' => array_map(fn($g) => [
                    'title' => $g['control_ref'] . ' - ' . $g['control_name'],
                    'status' => $g['status'],
                    'content' => $g['status'] === 'covered'
                        ? ($isZh ? "已覆盖，{$g['evidence_count']} 条证据" : "Covered with {$g['evidence_count']} evidence items")
                        : ($isZh ? '未覆盖，需要补充' : 'Not covered, requires remediation'),
                ], $gapAnalysis['details'] ?? []),
            ],
            [
                'title' => $isZh ? '差距分析总结' : 'Gap Analysis Summary',
                'content' => $isZh
                    ? "共发现 {$gapAnalysis['gaps']} 个差距项，覆盖率为 {$gapAnalysis['coverage_rate']}%。"
                      . "建议优先处理未覆盖的关键控制点。"
                    : "{$gapAnalysis['gaps']} gaps identified with {$gapAnalysis['coverage_rate']}% coverage rate. "
                      . "Priority should be given to addressing uncovered critical controls.",
            ],
            [
                'title' => $isZh ? '改进建议' : 'Recommendations',
                'content' => $isZh
                    ? "1. 立即补充未覆盖控制点的证据\n"
                      . "2. 对差距项制定整改计划\n"
                      . "3. 定期（至少每季度）执行合规评估\n"
                      . "4. 建立持续监控机制\n"
                      . "5. 考虑引入自动化合规工具"
                    : "1. Immediately supplement evidence for uncovered controls\n"
                      . "2. Develop remediation plans for all gaps\n"
                      . "3. Conduct compliance assessments regularly (at least quarterly)\n"
                      . "4. Establish continuous monitoring mechanisms\n"
                      . "5. Consider introducing automated compliance tools",
            ],
            [
                'title' => $isZh ? '结论' : 'Conclusion',
                'content' => $isZh
                    ? "{$tenant->name} 的 " . (self::FRAMEWORKS[$framework] ?? $framework) . " 合规状态为 " . ($gapAnalysis['coverage_rate'] >= 80 ? '良好' : ($gapAnalysis['coverage_rate'] >= 60 ? '一般' : '需改进')) . "。"
                      . "建议根据上述改进建议制定行动计划。"
                    : "The " . (self::FRAMEWORKS[$framework] ?? $framework) . " compliance status of {$tenant->name} is "
                      . ($gapAnalysis['coverage_rate'] >= 80 ? 'Good' : ($gapAnalysis['coverage_rate'] >= 60 ? 'Fair' : 'Needs Improvement')) . ". "
                      . "It is recommended to develop an action plan based on the recommendations above.",
            ],
        ];

        return [
            'sections' => $sections,
            'recommendations' => [
                ['priority' => 'high', 'title' => '补充证据', 'description' => '立即补充未覆盖控制点的合规证据'],
                ['priority' => 'high', 'title' => '整改计划', 'description' => '对差距项制定详细整改计划和时间表'],
                ['priority' => 'medium', 'title' => '定期评估', 'description' => '建立定期合规评估机制'],
                ['priority' => 'medium', 'title' => '持续监控', 'description' => '建立持续合规监控机制'],
                ['priority' => 'low', 'title' => '自动化工具', 'description' => '评估引入自动化合规管理工具'],
            ],
            'prompt' => '',
            'raw_response' => '',
        ];
    }

    /**
     * 解析 LLM 响应为结构化章节
     */
    protected function parseSections(string $response): array
    {
        $sections = [];
        $lines = explode("\n", $response);
        $currentSection = null;
        $currentContent = [];

        foreach ($lines as $line) {
            if (preg_match('/^#{1,3}\s+(.+)$/', $line, $m)) {
                if ($currentSection) {
                    $sections[] = [
                        'title' => $currentSection,
                        'content' => implode("\n", $currentContent),
                    ];
                }
                $currentSection = trim($m[1]);
                $currentContent = [];
            } else {
                $currentContent[] = $line;
            }
        }

        if ($currentSection) {
            $sections[] = [
                'title' => $currentSection,
                'content' => implode("\n", $currentContent),
            ];
        }

        return $sections ?: [
            ['title' => '报告全文', 'content' => $response],
        ];
    }

    /**
     * 从 LLM 响应中提取改进建议
     */
    protected function extractRecommendations(string $response): array
    {
        $recommendations = [];

        // 尝试匹配改进建议章节
        if (preg_match('/##?\s*(?:改进建议|Recommendations|建议)\s*\n(.+?)(?=\n##?\s|$)/s', $response, $m)) {
            $lines = array_filter(explode("\n", $m[1]), fn($l) => preg_match('/^\d+[.、\)]/', trim($l)));
            foreach ($lines as $line) {
                $recommendations[] = [
                    'priority' => 'medium',
                    'title' => mb_substr(trim($line), 0, 50),
                    'description' => trim($line),
                ];
            }
        }

        return $recommendations ?: [
            ['priority' => 'medium', 'title' => '请参考正文建议', 'description' => 'AI 未能提取结构化建议，请查看报告正文'],
        ];
    }

    /**
     * 构建证据摘要
     */
    protected function buildEvidenceSummary(array $evidence): array
    {
        $grouped = collect($evidence)->groupBy('evidence_type')->map(function ($items, $type) {
            return [
                'type' => $type,
                'count' => $items->count(),
                'validated' => $items->where('status', 'validated')->count(),
                'latest' => $items->sortByDesc('collected_at')->first()['collected_at'] ?? null,
            ];
        });

        return [
            'total_items' => count($evidence),
            'types' => $grouped->values()->toArray(),
            'coverage_span' => [
                'oldest' => collect($evidence)->min('collected_at'),
                'newest' => collect($evidence)->max('collected_at'),
            ],
        ];
    }

    /**
     * 保存报告文件
     */
    protected function saveReportFile(ComplianceAiReport $report, array $content): ?string
    {
        try {
            $fileName = "compliance/{$report->tenant_id}/{$report->framework}_{$report->id}.md";
            $fileContent = "# {$report->title}\n\n";

            foreach ($content['sections'] ?? [] as $section) {
                $fileContent .= "## {$section['title']}\n\n{$section['content']}\n\n";
            }

            if (! empty($content['recommendations'])) {
                $fileContent .= "## 改进建议\n\n";
                foreach ($content['recommendations'] as $rec) {
                    $fileContent .= "- [{$rec['priority']}] {$rec['title']}: {$rec['description']}\n";
                }
            }

            Storage::disk('local')->put($fileName, $fileContent);
            return $fileName;
        } catch (\Throwable $e) {
            Log::error('合规报告文件保存失败', [
                'report_id' => $report->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    // ─── 报告查询 ───

    /**
     * 获取报告列表
     */
    public function getReports(int $tenantId, array $filters = []): array
    {
        $query = ComplianceAiReport::where('tenant_id', $tenantId)
            ->when($filters['framework'] ?? null, fn($q) => $q->where('framework', $filters['framework']))
            ->when($filters['status'] ?? null, fn($q) => $q->where('status', $filters['status']))
            ->when($filters['date_from'] ?? null, fn($q) => $q->where('generated_at', '>=', $filters['date_from']))
            ->when($filters['date_to'] ?? null, fn($q) => $q->where('generated_at', '<=', $filters['date_to']))
            ->orderByDesc('generated_at');

        return $query->paginate($filters['per_page'] ?? 20)->toArray();
    }

    /**
     * 获取报告详情
     */
    public function getReportDetail(int $reportId): ?ComplianceAiReport
    {
        return ComplianceAiReport::with(['tenant', 'generator', 'evidenceItems'])->find($reportId);
    }

    /**
     * 获取报告文件内容
     */
    public function getReportContent(ComplianceAiReport $report): ?string
    {
        if (! $report->file_path) {
            return null;
        }

        try {
            return Storage::disk('local')->get($report->file_path);
        } catch (\Throwable $e) {
            Log::error('读取报告文件失败', ['path' => $report->file_path, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * 删除报告
     */
    public function deleteReport(ComplianceAiReport $report): void
    {
        DB::transaction(function () use ($report) {
            if ($report->file_path) {
                Storage::disk('local')->delete($report->file_path);
            }
            $report->delete();
        });
    }

    // ─── 仪表盘 ───

    /**
     * 合规报告仪表盘
     */
    public function getDashboard(int $tenantId = null): array
    {
        $baseQuery = ComplianceAiReport::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId));

        return [
            'total_reports' => $baseQuery->count(),
            'frameworks' => $baseQuery->selectRaw('framework, COUNT(*) as cnt')
                ->groupBy('framework')->pluck('cnt', 'framework')->toArray(),
            'recent_10' => $baseQuery->with('generator:id,name')
                ->orderByDesc('generated_at')->limit(10)->get()->toArray(),
            'status_breakdown' => $baseQuery->selectRaw('status, COUNT(*) as cnt')
                ->groupBy('status')->pluck('cnt', 'status')->toArray(),
            'average_coverage' => $baseQuery->whereNotNull('gap_analysis')
                ->get()->average(fn($r) => $r->gap_analysis['coverage_rate'] ?? 0),
        ];
    }
}
