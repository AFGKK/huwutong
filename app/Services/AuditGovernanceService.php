<?php

namespace App\Services;

use App\Models\AuditBatchOperation;
use App\Models\AuditLogAnnotation;
use App\Models\AuditLogTag;
use App\Models\CleanupSchedule;
use App\Models\ComplianceFramework;
use App\Models\ComplianceReport;
use App\Models\ComplianceReportExport;
use App\Models\DataRetentionAudit;
use App\Models\Log;
use App\Models\RetentionPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * 审计治理服务
 *
 * 提供合规报告生成、审计日志增强（标签/备注/批量操作）、数据保留治理三大能力。
 */
class AuditGovernanceService
{
    // ─── 合规报告 ───

    /**
     * 获取所有合规框架
     */
    public function getFrameworks(): array
    {
        return ComplianceFramework::orderBy('code')->get()->all();
    }

    /**
     * 初始化默认的合规框架
     */
    public function seedFrameworks(): void
    {
        $frameworks = [
            [
                'code' => 'SOC2',
                'name' => 'SOC 2 Type II',
                'description' => 'Service Organization Control 2 - 面向服务组织的信任服务准则',
                'control_domains' => ['安全', '可用性', '处理完整性', '保密性', '隐私'],
            ],
            [
                'code' => 'GDPR',
                'name' => '通用数据保护条例',
                'description' => 'General Data Protection Regulation - 欧盟数据隐私保护法规',
                'control_domains' => ['数据主体权利', '数据最小化', '存储限制', '完整性/保密性', '问责制'],
            ],
            [
                'code' => 'HIPAA',
                'name' => '健康保险可携性和责任法案',
                'description' => 'Health Insurance Portability and Accountability Act - 美国医疗数据保护标准',
                'control_domains' => ['隐私规则', '安全规则', '违规通知', '行政保障', '物理保障', '技术保障'],
            ],
            [
                'code' => 'PCI_DSS',
                'name' => '支付卡行业数据安全标准',
                'description' => 'Payment Card Industry Data Security Standard - 支付卡数据处理安全标准',
                'control_domains' => ['网络安全管理', '持卡人数据保护', '漏洞管理', '访问控制', '网络监控', '安全策略'],
            ],
            [
                'code' => 'ISO27001',
                'name' => 'ISO/IEC 27001:2022',
                'description' => '信息安全管理体系标准',
                'control_domains' => ['信息安全策略', '资产管理', '访问控制', '加密', '物理安全', '操作安全', '通信安全'],
            ],
        ];

        foreach ($frameworks as $fw) {
            ComplianceFramework::firstOrCreate(
                ['code' => $fw['code']],
                $fw
            );
        }
    }

    /**
     * 生成合规报告
     */
    public function generateReport(int $frameworkId, array $params): ComplianceReport
    {
        $framework = ComplianceFramework::findOrFail($frameworkId);

        $periodStart = $params['period_start'] ?? now()->subMonth()->toDateString();
        $periodEnd = $params['period_end'] ?? now()->toDateString();

        // 分析审计日志发现项
        $findings = $this->analyzeComplianceFindings($framework, $periodStart, $periodEnd);

        // 统计控制项
        $totalControls = count($findings);
        $passed = count(array_filter($findings, fn($f) => ($f['status'] ?? '') === 'pass'));
        $failed = count(array_filter($findings, fn($f) => ($f['status'] ?? '') === 'fail'));
        $na = count(array_filter($findings, fn($f) => ($f['status'] ?? '') === 'na'));

        // 风险等级
        $riskLevel = $this->determineRiskLevel($failed, $totalControls);

        // 收集证据引用
        $evidenceRefs = $this->collectEvidence($periodStart, $periodEnd);

        return ComplianceReport::create([
            'framework_id' => $frameworkId,
            'title' => $params['title'] ?? $framework->name . ' 合规报告',
            'type' => $params['type'] ?? 'on_demand',
            'status' => 'generated',
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'controls_assessed' => $framework->control_domains,
            'findings' => $findings,
            'evidence_refs' => $evidenceRefs,
            'summary' => $this->generateSummary($framework, $passed, $failed, $na, $totalControls),
            'risk_level' => $riskLevel,
            'passed_count' => $passed,
            'failed_count' => $failed,
            'na_count' => $na,
            'generated_by' => auth()->id(),
            'generated_at' => now(),
        ]);
    }

    /**
     * 分析合规发现项
     */
    protected function analyzeComplianceFindings(ComplianceFramework $framework, string $periodStart, string $periodEnd): array
    {
        $findings = [];
        $domains = $framework->control_domains ?? [];

        foreach ($domains as $domain) {
            $findings[] = [
                'domain' => $domain,
                'status' => $this->assessDomain($domain, $periodStart, $periodEnd),
                'description' => $this->getDomainDescription($framework->code, $domain),
                'details' => $this->getDomainDetails($domain, $periodStart, $periodEnd),
            ];
        }

        return $findings;
    }

    /**
     * 评估某个控制域
     */
    protected function assessDomain(string $domain, string $from, string $to): string
    {
        // 根据审计日志分析域的健康状态
        $recentErrors = Log::where('type', 'error')
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $to . ' 23:59:59')
            ->where('description', 'like', "%{$domain}%")
            ->count();

        $securityEvents = Log::where('type', 'security')
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $to . ' 23:59:59')
            ->count();

        if ($recentErrors > 10 || $securityEvents > 50) {
            return 'fail';
        }
        if ($recentErrors > 3 || $securityEvents > 10) {
            return 'warn';
        }
        return 'pass';
    }

    protected function getDomainDescription(string $frameworkCode, string $domain): string
    {
        $descriptions = [
            'SOC2' => [
                '安全' => '系统和数据访问控制措施',
                '可用性' => '系统正常运行时间和可用性保障',
                '处理完整性' => '数据处理准确性和完整性',
                '保密性' => '敏感信息保密保护',
                '隐私' => '个人信息的收集和使用规范',
            ],
            'GDPR' => [
                '数据主体权利' => '用户访问、更正、删除个人数据的权利',
                '数据最小化' => '仅收集必要的数据',
                '存储限制' => '数据存储期限控制',
                '完整性/保密性' => '数据安全保护措施',
                '问责制' => '合规证明和记录',
            ],
        ];

        return $descriptions[$frameworkCode][$domain] ?? "{$domain} 控制域评估";
    }

    protected function getDomainDetails(string $domain, string $from, string $to): array
    {
        $logs = Log::where('created_at', '>=', $from)
            ->where('created_at', '<=', $to . ' 23:59:59')
            ->where('description', 'like', "%{$domain}%")
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'action', 'description', 'type', 'created_at']);

        return [
            'related_logs' => $logs->toArray(),
            'total_events' => Log::where('created_at', '>=', $from)
                ->where('created_at', '<=', $to . ' 23:59:59')
                ->where(function ($q) use ($domain) {
                    $q->where('description', 'like', "%{$domain}%")
                      ->orWhere('type', 'security');
                })->count(),
            'assessed_at' => now()->toIso8601String(),
        ];
    }

    protected function determineRiskLevel(int $failed, int $total): string
    {
        if ($total === 0) return 'low';
        $ratio = $failed / $total;
        if ($ratio > 0.3) return 'critical';
        if ($ratio > 0.15) return 'high';
        if ($ratio > 0.05) return 'medium';
        return 'low';
    }

    protected function generateSummary(ComplianceFramework $framework, int $passed, int $failed, int $na, int $total): string
    {
        $passRate = $total > 0 ? round($passed / $total * 100, 1) : 0;
        $dateRange = now()->subMonth()->format('Y-m-d') . ' 至 ' . now()->format('Y-m-d');

        return sprintf(
            '%s 合规报告 (%s)：通过 %d/%d 项 (%.1f%%)，失败 %d 项，不适用 %d 项。',
            $framework->name,
            $dateRange,
            $passed, $total, $passRate,
            $failed, $na
        );
    }

    /**
     * 收集证据引用
     */
    protected function collectEvidence(string $from, string $to): array
    {
        return [
            'total_logs' => Log::whereBetween('created_at', [$from, $to . ' 23:59:59'])->count(),
            'audit_logs' => Log::ofType('audit')->whereBetween('created_at', [$from, $to . ' 23:59:59'])->count(),
            'security_logs' => Log::ofType('security')->whereBetween('created_at', [$from, $to . ' 23:59:59'])->count(),
            'error_logs' => Log::ofType('error')->whereBetween('created_at', [$from, $to . ' 23:59:59'])->count(),
            'date_range' => ['from' => $from, 'to' => $to],
            'merkle_anchors' => \App\Models\AuditChainAnchor::whereBetween('anchored_at', [$from, $to . ' 23:59:59'])->count(),
        ];
    }

    // ─── 审计日志标签 ───

    public function getTags(): array
    {
        return AuditLogTag::withCount('logs')->orderBy('name')->get()->all();
    }

    public function createTag(array $data): AuditLogTag
    {
        return AuditLogTag::create($data);
    }

    public function updateTag(AuditLogTag $tag, array $data): AuditLogTag
    {
        $tag->update($data);
        return $tag->fresh();
    }

    public function deleteTag(AuditLogTag $tag): void
    {
        $tag->logs()->detach();
        $tag->delete();
    }

    /**
     * 为日志添加标签
     */
    public function tagLogs(array $logIds, array $tagIds): int
    {
        $count = 0;
        foreach ($logIds as $logId) {
            $log = Log::find($logId);
            if ($log) {
                $log->tags()->syncWithoutDetaching($tagIds);
                $count++;
            }
        }
        return $count;
    }

    /**
     * 移除日志标签
     */
    public function untagLogs(array $logIds, array $tagIds): int
    {
        $count = 0;
        foreach ($logIds as $logId) {
            $log = Log::find($logId);
            if ($log) {
                $log->tags()->detach($tagIds);
                $count++;
            }
        }
        return $count;
    }

    // ─── 审计日志备注 ───

    public function addAnnotation(int $logId, string $content): AuditLogAnnotation
    {
        return AuditLogAnnotation::create([
            'log_id' => $logId,
            'user_id' => auth()->id(),
            'content' => $content,
        ]);
    }

    public function getAnnotations(int $logId): array
    {
        return AuditLogAnnotation::with('user')
            ->where('log_id', $logId)
            ->orderByDesc('created_at')
            ->get()
            ->all();
    }

    public function deleteAnnotation(int $id): void
    {
        AuditLogAnnotation::where('id', $id)
            ->where('user_id', auth()->id())
            ->delete();
    }

    // ─── 批量操作 ───

    public function createBatchOperation(string $type, array $logIds, array $params = []): AuditBatchOperation
    {
        return AuditBatchOperation::create([
            'operation_type' => $type,
            'log_ids' => $logIds,
            'params' => $params,
            'user_id' => auth()->id(),
            'status' => 'pending',
        ]);
    }

    public function getBatchOperations(int $limit = 20): array
    {
        return AuditBatchOperation::with('user')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->all();
    }

    // ─── 数据保留治理 ───

    /**
     * 执行数据清理并提供审计记录
     */
    public function executeRetentionCleanup(string $type, ?int $customDays = null): DataRetentionAudit
    {
        $retentionDays = $customDays ?? \App\Models\AuditRetentionPolicy::getEffectiveDays($type);

        $cutoff = now()->subDays($retentionDays);

        $totalBefore = Log::ofType($type)->count();

        $query = Log::ofType($type)->where('created_at', '<', $cutoff);
        $toPrune = (clone $query)->count();

        $pruned = 0;
        $status = 'completed';

        try {
            DB::transaction(function () use ($query, $toPrune, &$pruned) {
                $batchSize = config('audit.prune_batch_size', 1000);
                $query->chunk($batchSize, function ($logs) use (&$pruned) {
                    $count = $logs->count();
                    Log::whereIn('id', $logs->pluck('id'))->delete();
                    $pruned += $count;
                });
            });
        } catch (\Exception $e) {
            $status = 'failed';
        }

        if ($pruned < $toPrune) {
            $status = 'partial';
        }

        $totalAfter = Log::ofType($type)->count();

        return DataRetentionAudit::create([
            'type' => $type,
            'retention_days' => $retentionDays,
            'total_logs_before' => $totalBefore,
            'pruned_count' => $pruned,
            'total_logs_after' => $totalAfter,
            'status' => $status,
            'notes' => "清理 {$type} 类型超过 {$retentionDays} 天的日志",
            'initiated_by' => auth()->id(),
            'executed_at' => now(),
        ]);
    }

    /**
     * 获取数据保留治理仪表盘数据
     */
    public function getRetentionDashboard(): array
    {
        $types = ['audit', 'security', 'error', 'system'];
        $byType = [];

        foreach ($types as $type) {
            $count = Log::ofType($type)->count();
            $oldest = Log::ofType($type)->orderBy('created_at')->value('created_at');
            $newest = Log::ofType($type)->latest()->value('created_at');
            $retentionDays = \App\Models\AuditRetentionPolicy::getEffectiveDays($type);
            $cutoff = now()->subDays($retentionDays);
            $toPrune = $count > 0 ? Log::ofType($type)->where('created_at', '<', $cutoff)->count() : 0;

            $byType[] = [
                'type' => $type,
                'count' => $count,
                'oldest' => $oldest,
                'newest' => $newest,
                'retention_days' => $retentionDays,
                'to_prune' => $toPrune,
                'storage_mb' => $this->estimateStorageMb($type),
            ];
        }

        // 最近清理记录
        $recentCleanups = DataRetentionAudit::with('initiator')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->all();

        // 存储趋势
        $storageTrend = Log::selectRaw('DATE(created_at) as date, type, count(*) as count')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date', 'type')
            ->orderBy('date')
            ->get()
            ->groupBy('type')
            ->toArray();

        return [
            'by_type' => $byType,
            'recent_cleanups' => $recentCleanups,
            'storage_trend' => $storageTrend,
            'total_logs' => Log::count(),
            'total_storage_mb' => $this->estimateStorageMb(),
        ];
    }

    protected function estimateStorageMb(?string $type = null): float
    {
        $tableName = (new Log())->getTable();
        $query = "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb
                  FROM information_schema.tables WHERE table_name = ?";
        $result = \DB::select($query, [$tableName]);

        $total = (float) ($result[0]->size_mb ?? 0);

        if ($type) {
            $ratio = Log::ofType($type)->count() / max(Log::count(), 1);
            return round($total * $ratio, 2);
        }

        return $total;
    }

    /**
     * 审计治理概览仪表盘
     */
    public function getGovernanceDashboard(): array
    {
        $this->seedFrameworks();

        $frameworks = ComplianceFramework::where('is_active', true)->get();
        $frameworkSummary = [];
        foreach ($frameworks as $fw) {
            $latestReport = $fw->reports()->latest()->first();
            $frameworkSummary[] = [
                'id' => $fw->id,
                'code' => $fw->code,
                'name' => $fw->name,
                'latest_report' => $latestReport ? [
                    'id' => $latestReport->id,
                    'status' => $latestReport->status,
                    'risk_level' => $latestReport->risk_level,
                    'passed_count' => $latestReport->passed_count,
                    'failed_count' => $latestReport->failed_count,
                    'generated_at' => $latestReport->generated_at,
                ] : null,
                'report_count' => $fw->reports()->count(),
            ];
        }

        $tagStats = AuditLogTag::withCount('logs')->get();

        // 合规报告导出记录数
        $exportCount = ComplianceReportExport::count();

        return [
            'frameworks' => $frameworkSummary,
            'tag_stats' => $tagStats,
            'total_annotations' => AuditLogAnnotation::count(),
            'total_batch_ops' => AuditBatchOperation::count(),
            'total_cleanups' => DataRetentionAudit::count(),
            'total_exports' => $exportCount,
        ];
    }

    // ═══════════════════════════════════════════════════
    //  多数据源保留策略管理
    // ═══════════════════════════════════════════════════

    /**
     * 获取所有数据源的保留策略列表
     */
    public function getAllRetentionPolicies(): array
    {
        return RetentionPolicy::getAllPolicies();
    }

    /**
     * 创建或更新数据源保留策略
     */
    public function saveRetentionPolicy(array $data): RetentionPolicy
    {
        $validSources = array_keys(RetentionPolicy::defaultSources());

        $policy = RetentionPolicy::updateOrCreate(
            ['data_source' => $data['data_source']],
            [
                'display_name' => $data['display_name'] ?? (RetentionPolicy::defaultSources()[$data['data_source']]['display_name'] ?? $data['data_source']),
                'retention_days' => $data['retention_days'],
                'is_active' => $data['is_active'] ?? true,
                'is_system' => $data['is_system'] ?? in_array($data['data_source'], $validSources),
                'description' => $data['description'] ?? null,
            ]
        );

        return $policy->fresh();
    }

    /**
     * 切换策略激活状态
     */
    public function toggleRetentionPolicy(int $id): bool
    {
        $policy = RetentionPolicy::findOrFail($id);
        $policy->update(['is_active' => !$policy->is_active]);
        return $policy->is_active;
    }

    /**
     * 删除自定义保留策略
     */
    public function deleteRetentionPolicy(int $id): bool
    {
        $policy = RetentionPolicy::findOrFail($id);
        if ($policy->is_system) {
            return false;
        }
        return $policy->delete();
    }

    /**
     * 获取增强的数据保留审计仪表盘（覆盖所有数据源）
     */
    public function getExtendedRetentionDashboard(): array
    {
        $policies = RetentionPolicy::getAllPolicies();
        $bySource = [];

        foreach ($policies as $policy) {
            $count = $this->countDataSource($policy['data_source']);
            $oldest = $this->oldestDataSourceRecord($policy['data_source']);
            $retentionDays = $policy['retention_days'];
            $cutoff = now()->subDays($retentionDays);
            $toPrune = $this->countExpiredRecords($policy['data_source'], $cutoff);
            $storageMb = $this->estimateDataSourceStorage($policy['data_source']);

            $bySource[] = [
                'data_source' => $policy['data_source'],
                'display_name' => $policy['display_name'],
                'count' => $count,
                'oldest' => $oldest,
                'retention_days' => $retentionDays,
                'to_prune' => $toPrune,
                'storage_mb' => $storageMb,
                'is_active' => $policy['is_active'],
            ];
        }

        $recentCleanups = DataRetentionAudit::with('initiator')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->all();

        $totalStorage = array_sum(array_column($bySource, 'storage_mb'));
        $totalRecords = array_sum(array_column($bySource, 'count'));

        return [
            'by_source' => $bySource,
            'recent_cleanups' => $recentCleanups,
            'total_records' => $totalRecords,
            'total_storage_mb' => round($totalStorage, 2),
            'policies' => $policies,
        ];
    }

    /**
     * 执行多数据源清理
     */
    public function executeExtendedCleanup(string $dataSource, ?int $customDays = null): DataRetentionAudit
    {
        $retentionDays = $customDays ?? RetentionPolicy::getEffectiveDays($dataSource);
        $cutoff = now()->subDays($retentionDays);

        $totalBefore = $this->countDataSource($dataSource);
        $toPrune = $this->countExpiredRecords($dataSource, $cutoff);

        $pruned = 0;
        $status = 'completed';

        try {
            $pruned = $this->pruneDataSource($dataSource, $cutoff);
        } catch (\Exception $e) {
            $status = 'failed';
        }

        if ($pruned < $toPrune) {
            $status = 'partial';
        }

        $totalAfter = $this->countDataSource($dataSource);

        $displayName = RetentionPolicy::defaultSources()[$dataSource]['display_name'] ?? $dataSource;

        return DataRetentionAudit::create([
            'type' => $dataSource,
            'data_source' => $dataSource,
            'retention_days' => $retentionDays,
            'total_logs_before' => $totalBefore,
            'pruned_count' => $pruned,
            'total_logs_after' => $totalAfter,
            'status' => $status,
            'notes' => "清理 {$displayName} 超过 {$retentionDays} 天的数据",
            'initiated_by' => auth()->id(),
            'executed_at' => now(),
        ]);
    }

    /**
     * 获取清理调度配置列表
     */
    public function getCleanupSchedules(): array
    {
        $defaultSources = RetentionPolicy::defaultSources();
        $schedules = CleanupSchedule::all()->keyBy('data_source');

        $result = [];
        foreach ($defaultSources as $source => $default) {
            $schedule = $schedules->get($source);
            $result[] = [
                'id' => $schedule?->id,
                'data_source' => $source,
                'display_name' => $default['display_name'],
                'frequency' => $schedule?->frequency ?? 'daily',
                'time_of_day' => $schedule?->time_of_day ?? '02:00',
                'day_of_week' => $schedule?->day_of_week ?? '0',
                'batch_size' => $schedule?->batch_size ?? 1000,
                'is_active' => $schedule?->is_active ?? true,
                'last_run_at' => $schedule?->last_run_at,
                'next_run_at' => $schedule?->next_run_at,
            ];
        }

        return $result;
    }

    /**
     * 保存清理调度配置
     */
    public function saveCleanupSchedule(array $data): CleanupSchedule
    {
        return CleanupSchedule::updateOrCreate(
            ['data_source' => $data['data_source']],
            [
                'frequency' => $data['frequency'] ?? 'daily',
                'time_of_day' => $data['time_of_day'] ?? '02:00',
                'day_of_week' => $data['day_of_week'] ?? '0',
                'batch_size' => $data['batch_size'] ?? 1000,
                'is_active' => $data['is_active'] ?? true,
            ]
        );
    }

    // ═══════════════════════════════════════════════════
    //  合规报告导出
    // ═══════════════════════════════════════════════════

    /**
     * 获取报告的导出记录
     */
    public function getReportExports(int $reportId): array
    {
        return ComplianceReportExport::with('generator')
            ->where('compliance_report_id', $reportId)
            ->orderByDesc('created_at')
            ->get()
            ->all();
    }

    /**
     * 导出合规报告
     */
    public function exportReport(int $reportId, string $format): ComplianceReportExport
    {
        $report = ComplianceReport::with('framework')->findOrFail($reportId);

        $export = ComplianceReportExport::create([
            'compliance_report_id' => $reportId,
            'format' => $format,
            'status' => 'processing',
            'generated_by' => auth()->id(),
            'generated_at' => now(),
        ]);

        try {
            $content = match ($format) {
                'json' => $this->generateJsonExport($report),
                'csv' => $this->generateCsvExport($report),
                default => throw new \InvalidArgumentException("Unsupported format: {$format}"),
            };

            $fileName = "compliance_report_{$reportId}_{$format}/{$format}." . ($format === 'json' ? 'json' : 'csv');
            $path = "exports/compliance/{$fileName}";
            Storage::disk('local')->put($path, $content);

            $export->update([
                'status' => 'completed',
                'file_path' => $path,
                'file_size' => strlen($content),
            ]);
        } catch (\Exception $e) {
            $export->update(['status' => 'failed']);
            throw $e;
        }

        return $export->fresh()->load('generator');
    }

    /**
     * 生成 JSON 格式合规报告导出
     */
    protected function generateJsonExport(ComplianceReport $report): string
    {
        $data = [
            'report' => [
                'title' => $report->title,
                'framework' => $report->framework?->name,
                'type' => $report->type,
                'period' => ['start' => $report->period_start, 'end' => $report->period_end],
                'generated_at' => $report->generated_at?->toIso8601String(),
                'risk_level' => $report->risk_level,
                'summary' => $report->summary,
            ],
            'statistics' => [
                'passed' => $report->passed_count,
                'failed' => $report->failed_count,
                'na' => $report->na_count,
            ],
            'findings' => $report->findings ?? [],
            'evidence' => $report->evidence_refs ?? [],
        ];

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * 生成 CSV 格式合规报告导出
     */
    protected function generateCsvExport(ComplianceReport $report): string
    {
        $output = fopen('php://temp', 'r+');

        // 报告头信息
        fputcsv($output, ['合规报告导出']);
        fputcsv($output, ['标题', $report->title]);
        fputcsv($output, ['框架', $report->framework?->name ?? '']);
        fputcsv($output, ['报告期', "{$report->period_start} ~ {$report->period_end}"]);
        fputcsv($output, ['风险等级', $report->risk_level ?? '']);
        fputcsv($output, ['生成时间', $report->generated_at?->toDateTimeString() ?? '']);
        fputcsv($output, ['']);
        fputcsv($output, ['控制域评估']);
        fputcsv($output, ['控制域', '状态', '描述', '关联事件数']);

        foreach (($report->findings ?? []) as $finding) {
            fputcsv($output, [
                $finding['domain'] ?? '',
                $finding['status'] ?? '',
                $finding['description'] ?? '',
                $finding['details']['total_events'] ?? 0,
            ]);
        }

        fputcsv($output, ['']);
        fputcsv($output, ['统计']);
        fputcsv($output, ['通过', $report->passed_count]);
        fputcsv($output, ['失败', $report->failed_count]);
        fputcsv($output, ['不适用', $report->na_count]);

        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        return $content;
    }

    // ═══════════════════════════════════════════════════
    //  数据源辅助方法
    // ═══════════════════════════════════════════════════

    /**
     * 数据源映射：data_source => 对应的 Model class
     */
    protected function sourceModelMapping(): array
    {
        return [
            'audit_log' => Log::class,
            'security_log' => Log::class,
            'error_log' => Log::class,
            'system_log' => Log::class,
            'apm_request' => \App\Models\ApmRequest::class,
            'webhook_event' => \App\Models\WebhookEvent::class,
            'webhook_delivery' => \App\Models\EventDelivery::class,
            'license' => \App\Models\License::class,
            'api_endpoint' => \App\Models\ApiDocEndpoint::class,
        ];
    }

    /**
     * 对 Log 类型的数据源，获取其内部 type 字段值
     */
    protected function logTypeFromSource(string $dataSource): ?string
    {
        return match ($dataSource) {
            'audit_log' => 'audit',
            'security_log' => 'security',
            'error_log' => 'error',
            'system_log' => 'system',
            default => null,
        };
    }

    /**
     * 统计某数据源记录总数
     */
    public function countDataSource(string $dataSource): int
    {
        $modelClass = $this->sourceModelMapping()[$dataSource] ?? null;
        if (!$modelClass) return 0;

        $query = $modelClass::query();

        $logType = $this->logTypeFromSource($dataSource);
        if ($logType && $modelClass === Log::class) {
            $query->where('type', $logType);
        }

        return $query->count();
    }

    /**
     * 获取某数据源最早记录时间
     */
    public function oldestDataSourceRecord(string $dataSource): ?string
    {
        $modelClass = $this->sourceModelMapping()[$dataSource] ?? null;
        if (!$modelClass) return null;

        $query = $modelClass::query();

        $logType = $this->logTypeFromSource($dataSource);
        if ($logType && $modelClass === Log::class) {
            $query->where('type', $logType);
        }

        $record = $query->orderBy('created_at')->value('created_at');
        return $record ? (string) $record : null;
    }

    /**
     * 计算某数据源过期记录数
     */
    public function countExpiredRecords(string $dataSource, \Carbon\Carbon $cutoff): int
    {
        $modelClass = $this->sourceModelMapping()[$dataSource] ?? null;
        if (!$modelClass) return 0;

        $query = $modelClass::where('created_at', '<', $cutoff);

        $logType = $this->logTypeFromSource($dataSource);
        if ($logType && $modelClass === Log::class) {
            $query->where('type', $logType);
        }

        return $query->count();
    }

    /**
     * 执行数据源清理
     */
    protected function pruneDataSource(string $dataSource, \Carbon\Carbon $cutoff): int
    {
        $modelClass = $this->sourceModelMapping()[$dataSource] ?? null;
        if (!$modelClass) return 0;

        $pruned = 0;
        $batchSize = config('audit.prune_batch_size', 1000);

        do {
            $query = $modelClass::where('created_at', '<', $cutoff);

            $logType = $this->logTypeFromSource($dataSource);
            if ($logType && $modelClass === Log::class) {
                $query->where('type', $logType);
            }

            $ids = (clone $query)->limit($batchSize)->pluck('id')->toArray();
            $count = count($ids);

            if ($count > 0) {
                $modelClass::whereIn('id', $ids)->delete();
                $pruned += $count;
            }
        } while ($count >= $batchSize);

        return $pruned;
    }

    /**
     * 估算数据源存储空间（MB）
     */
    public function estimateDataSourceStorage(string $dataSource): float
    {
        $modelClass = $this->sourceModelMapping()[$dataSource] ?? null;
        if (!$modelClass) return 0;

        $model = new $modelClass;
        $tableName = $model->getTable();

        $result = \DB::select(
            "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb
             FROM information_schema.tables WHERE table_name = ?",
            [$tableName]
        );

        $total = (float) ($result[0]->size_mb ?? 0);

        $logType = $this->logTypeFromSource($dataSource);
        if ($logType && $modelClass === Log::class) {
            $ratio = Log::ofType($logType)->count() / max(Log::count(), 1);
            return round($total * $ratio, 2);
        }

        return $total;
    }
}
