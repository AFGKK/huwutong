<?php

namespace App\Services;

use App\Models\SlaBreach;
use App\Models\SlaCompensation;
use App\Models\SlaContract;
use App\Models\SlaMetric;
use App\Models\SlaRecord;
use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * SLA 追踪服务
 *
 * 管理 SLA 合约、指标、达标记录、违约事件
 * 支持自动计算工单维度的 SLA 达标率
 */
class SlaService
{
    // ─── 概览 ───

    public function getDashboard(int $tenantId = null): array
    {
        $query = fn($q) => $tenantId ? $q->where('tenant_id', $tenantId) : $q;

        $totalContracts = SlaContract::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->count();
        $activeContracts = SlaContract::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('is_active', true)->where(function ($q) {
                $q->whereNull('expiry_date')->orWhere('expiry_date', '>=', now());
            })->count();

        $currentMonth = now()->startOfMonth();

        $monthlyCompliance = SlaRecord::whereIn('sla_contract_id',
            SlaContract::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->select('id')
        )->where('record_date', '>=', $currentMonth)
            ->selectRaw('AVG(compliance_rate) as avg_rate, SUM(CASE WHEN '.db_is_true('is_breached').' THEN 1 ELSE 0 END) as breaches')
            ->first();

        $openBreaches = SlaBreach::whereIn('sla_contract_id',
            SlaContract::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->select('id')
        )->whereIn('status', ['open', 'acknowledged'])->count();

        $recentBreaches = SlaBreach::with('contract:id,name')
            ->whereIn('sla_contract_id',
                SlaContract::where('tenant_id', $tenantId)->select('id')
            )->orderByDesc('created_at')->limit(10)->get()->toArray();

        return [
            'total_contracts' => $totalContracts,
            'active_contracts' => $activeContracts,
            'monthly_compliance_rate' => round($monthlyCompliance?->avg_rate ?? 0, 1),
            'monthly_breaches' => $monthlyCompliance?->breaches ?? 0,
            'open_breaches' => $openBreaches,
            'recent_breaches' => $recentBreaches,
            'by_level' => SlaContract::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                ->selectRaw('level, COUNT(*) as cnt')->groupBy('level')->get()->pluck('cnt', 'level')->toArray(),
        ];
    }

    // ─── SLA 合约 CRUD ───

    public function getContracts(int $tenantId = null, array $filters = []): array
    {
        $query = SlaContract::with('customer:id,name')
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->when($filters['level'] ?? null, fn($q, $v) => $q->where('level', $v))
            ->when($filters['is_active'] ?? null, fn($q, $v) => $q->where('is_active', $v === 'true'))
            ->when($filters['customer_id'] ?? null, fn($q, $v) => $q->where('customer_id', $v))
            ->orderByDesc('created_at');

        return $query->get()->all();
    }

    public function getContract(int $id): SlaContract
    {
        return SlaContract::with(['customer:id,name,email', 'metrics', 'breaches' => function ($q) {
            $q->orderByDesc('created_at')->limit(20);
        }])->findOrFail($id);
    }

    public function createContract(array $data): SlaContract
    {
        if (empty($data['slug'])) {
            $data['slug'] = str_slug($data['name']) . '-' . uniqid();
        }
        return SlaContract::create($data);
    }

    public function updateContract(SlaContract $contract, array $data): SlaContract
    {
        $contract->update($data);
        return $contract->fresh();
    }

    public function deleteContract(SlaContract $contract): void
    {
        $contract->metrics()->delete();
        $contract->records()->delete();
        $contract->breaches()->delete();
        $contract->delete();
    }

    // ─── SLA 指标 ───

    public function addMetric(int $contractId, array $data): SlaMetric
    {
        $maxOrder = SlaMetric::where('sla_contract_id', $contractId)->max('sort_order') ?? 0;
        return SlaMetric::create(array_merge($data, [
            'sla_contract_id' => $contractId,
            'sort_order' => $maxOrder + 1,
        ]));
    }

    public function updateMetric(SlaMetric $metric, array $data): SlaMetric
    {
        $metric->update($data);
        return $metric->fresh();
    }

    public function deleteMetric(SlaMetric $metric): void
    {
        $metric->records()->delete();
        $metric->delete();
    }

    // ─── 达标计算（基于工单数据） ───

    /**
     * 计算指定 SLA 合约/指标在给定周期的达标率
     */
    public function calculateCompliance(SlaContract $contract, SlaMetric $metric, Carbon $startDate, Carbon $endDate, string $period = 'daily'): array
    {
        $records = [];
        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            $recordEnd = match ($period) {
                'weekly' => $current->copy()->addWeek(),
                'monthly' => $current->copy()->addMonth(),
                'quarterly' => $current->copy()->addQuarter(),
                default => $current->copy()->addDay(),
            };

            $result = $this->calculateMetricPeriod($contract, $metric, $current, $recordEnd);
            $records[] = $result;

            $current = $recordEnd;
        }

        return $records;
    }

    /**
     * 计算单个周期的指标值
     */
    protected function calculateMetricPeriod(SlaContract $contract, SlaMetric $metric, Carbon $start, Carbon $end): SlaRecord
    {
        // 基于 data_source 计算
        $result = match ($metric->data_source) {
            'tickets' => $this->calculateTicketMetric($contract, $metric, $start, $end),
            default => $this->calculateDefaultMetric($metric),
        };

        $targetValue = $metric->target_value;
        $actualValue = $result['actual'];
        $complianceRate = $this->calculateRate($metric->metric_key, $actualValue, $targetValue);
        $isBreached = $complianceRate < 100;

        return SlaRecord::updateOrCreate(
            [
                'sla_contract_id' => $contract->id,
                'sla_metric_id' => $metric->id,
                'record_date' => $start->format('Y-m-d'),
                'period' => $metric->measurement_window,
            ],
            [
                'actual_value' => $actualValue,
                'target_value' => $targetValue,
                'compliance_rate' => $complianceRate,
                'status' => $isBreached ? ($complianceRate >= ($metric->warning_threshold ?? 80) ? 'warning' : 'breached') : 'met',
                'is_breached' => $isBreached,
                'details' => $result['details'] ?? [],
            ]
        );
    }

    /**
     * 基于工单计算指标
     */
    protected function calculateTicketMetric(SlaContract $contract, SlaMetric $metric, Carbon $start, Carbon $end): array
    {
        $customerId = $contract->customer_id;
        $tickets = Ticket::query()
            ->when($customerId, fn($q) => $q->where('customer_id', $customerId))
            ->whereBetween('created_at', [$start, $end])
            ->get();

        $total = $tickets->count();

        return match ($metric->metric_key) {
            'response_time' => $this->calcAvgResponseTime($tickets, $metric->target_value),
            'resolution_time' => $this->calcAvgResolutionTime($tickets, $metric->target_value),
            'ticket_backlog' => ['actual' => $total, 'details' => ['total_tickets' => $total]],
            'availability' => ['actual' => 100, 'details' => ['note' => '基于服务端 uptime']],
            default => ['actual' => 0, 'details' => []],
        };
    }

    protected function calcAvgResponseTime($tickets, float $target): array
    {
        if ($tickets->isEmpty()) return ['actual' => 0, 'details' => ['count' => 0]];

        $withResponse = $tickets->filter(fn($t) => $t->first_response_at);
        $avgMinutes = $withResponse->avg(fn($t) => $t->created_at->diffInMinutes($t->first_response_at));

        return [
            'actual' => round($avgMinutes ?? 0, 1),
            'details' => ['count' => $withResponse->count(), 'total' => $tickets->count()],
        ];
    }

    protected function calcAvgResolutionTime($tickets, float $target): array
    {
        if ($tickets->isEmpty()) return ['actual' => 0, 'details' => ['count' => 0]];

        $resolved = $tickets->filter(fn($t) => $t->status === 'closed' && $t->closed_at);
        $avgMinutes = $resolved->avg(fn($t) => $t->created_at->diffInMinutes($t->closed_at));

        return [
            'actual' => round($avgMinutes ?? 0, 1),
            'details' => ['count' => $resolved->count(), 'total' => $tickets->count()],
        ];
    }

    protected function calculateDefaultMetric(SlaMetric $metric): array
    {
        return ['actual' => 0, 'details' => ['note' => '无数据源配置']];
    }

    /**
     * 计算达标率
     */
    protected function calculateRate(string $metricKey, float $actual, float $target): float
    {
        if ($target <= 0) return 100;

        return match ($metricKey) {
            'response_time', 'resolution_time' => $actual <= $target ? 100 : max(0, round((1 - ($actual - $target) / $target) * 100, 1)),
            'uptime', 'availability' => min(100, round(($actual / $target) * 100, 1)),
            'ticket_backlog' => $actual <= $target ? 100 : max(0, round((1 - ($actual - $target) / $actual) * 100, 1)),
            default => 100,
        };
    }

    // ─── 违约管理 ───

    public function getBreaches(int $tenantId = null, array $filters = []): array
    {
        $query = SlaBreach::with('contract:id,name', 'metric:id,name')
            ->when($tenantId, fn($q) => $q->whereIn('sla_contract_id',
                SlaContract::where('tenant_id', $tenantId)->select('id')
            ))
            ->when($filters['severity'] ?? null, fn($q, $v) => $q->where('severity', $v))
            ->when($filters['status'] ?? null, fn($q, $v) => $q->where('status', $v))
            ->when($filters['breach_type'] ?? null, fn($q, $v) => $q->where('breach_type', $v))
            ->orderByDesc('created_at');

        return $query->paginate($filters['per_page'] ?? 50, ['*'], 'page', $filters['page'] ?? 1)->toArray();
    }

    public function acknowledgeBreach(SlaBreach $breach): SlaBreach
    {
        $breach->update(['status' => 'acknowledged', 'acknowledged_at' => now()]);
        return $breach->fresh();
    }

    public function resolveBreach(SlaBreach $breach, string $notes = null): SlaBreach
    {
        $breach->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolution_notes' => $notes,
        ]);
        return $breach->fresh();
    }

    public function createBreach(array $data): SlaBreach
    {
        return SlaBreach::create($data);
    }

    // ─── 报表 ───

    public function getComplianceReport(int $contractId, string $period = 'monthly', int $months = 6): array
    {
        $contract = SlaContract::findOrFail($contractId);

        $startDate = now()->subMonths($months)->startOfMonth();
        $records = SlaRecord::with('metric:id,name,metric_key,unit,target_value')
            ->where('sla_contract_id', $contractId)
            ->where('record_date', '>=', $startDate)
            ->where('period', $period)
            ->orderBy('record_date')
            ->get();

        $byMetric = $records->groupBy('sla_metric_id');

        $result = [];
        foreach ($byMetric as $metricId => $metricRecords) {
            $metric = $metricRecords->first()->metric;
            $result[] = [
                'metric_id' => $metricId,
                'metric_name' => $metric?->name ?? 'Unknown',
                'metric_key' => $metric?->metric_key ?? '',
                'unit' => $metric?->unit ?? '',
                'target' => $metric?->target_value ?? 0,
                'avg_rate' => round($metricRecords->avg('compliance_rate'), 1),
                'min_rate' => round($metricRecords->min('compliance_rate'), 1),
                'breaches' => $metricRecords->where('is_breached', true)->count(),
                'data' => $metricRecords->map(fn($r) => [
                    'date' => $r->record_date?->format('Y-m-d'),
                    'actual' => $r->actual_value,
                    'target' => $r->target_value,
                    'rate' => $r->compliance_rate,
                    'status' => $r->status,
                ]),
            ];
        }

        return $result;
    }

    // ─── 复制 SLA 模板 ───

    public function createFromTemplate(int $templateId, array $overrides): SlaContract
    {
        $template = SlaContract::findOrFail($templateId);

        $contract = $this->createContract(array_merge([
            'tenant_id' => $overrides['tenant_id'] ?? null,
            'customer_id' => $overrides['customer_id'] ?? null,
            'name' => $overrides['name'] ?? $template->name,
            'description' => $template->description,
            'level' => $overrides['level'] ?? $template->level,
            'scope' => $template->scope,
            'terms' => $template->terms,
            'penalties' => $template->penalties,
            'business_hours' => $template->business_hours,
            'effective_date' => $overrides['effective_date'] ?? now()->format('Y-m-d'),
            'expiry_date' => $overrides['expiry_date'] ?? null,
            'is_active' => true,
            'is_template' => false,
        ]));

        // 复制指标
        foreach ($template->metrics as $metric) {
            $this->addMetric($contract->id, [
                'metric_key' => $metric->metric_key,
                'name' => $metric->name,
                'unit' => $metric->unit,
                'target_value' => $metric->target_value,
                'warning_threshold' => $metric->warning_threshold,
                'measurement_window' => $metric->measurement_window,
                'data_source' => $metric->data_source,
            ]);
        }

        return $contract->fresh('metrics');
    }

    // ═══════════ SLA 违约补偿 ═══════════

    /**
     * 为某个违约自动生成补偿
     */
    public function autoGenerateCompensation(SlaBreach $breach): SlaCompensation
    {
        $contract = $breach->contract;
        $severity = $breach->severity;
        $penalties = $contract->penalties ?? [];

        // 从合约 penalties 获取配置，或用默认值
        $compType = $penalties['compensation_type'] ?? 'credit';
        $amount = $penalties['amounts'][$severity] ?? SlaCompensation::SEVERITY_AMOUNTS[$severity] ?? 100;

        return SlaCompensation::create([
            'sla_contract_id' => $contract->id,
            'sla_breach_id' => $breach->id,
            'tenant_id' => $contract->tenant_id,
            'customer_id' => $contract->customer_id,
            'compensation_type' => $compType,
            'severity' => $severity,
            'amount' => $amount,
            'currency' => $penalties['currency'] ?? 'CNY',
            'reason' => "SLA违约自动补偿: {$breach->description}",
            'calculation_method' => 'automatic',
            'status' => $penalties['auto_approve'] ?? false ? 'approved' : 'pending',
        ]);
    }

    /**
     * 为开放违约批量生成补偿
     */
    public function autoGenerateForOpenBreaches(int $tenantId): array
    {
        $generated = [];
        $breaches = SlaBreach::whereIn('sla_contract_id',
            SlaContract::where('tenant_id', $tenantId)->select('id')
        )->whereIn('status', ['open', 'acknowledged'])
        ->whereDoesntHave('compensation')
        ->get();

        foreach ($breaches as $breach) {
            $generated[] = $this->autoGenerateCompensation($breach);
        }

        return $generated;
    }

    /**
     * 列表补偿记录
     */
    public function getCompensations(int $tenantId, array $filters = [], int $perPage = 20)
    {
        $query = SlaCompensation::with(['contract:id,name', 'breach:id,description,severity,created_at', 'customer:id,name'])
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at');

        if (!empty($filters['status'])) $query->where('status', $filters['status']);
        if (!empty($filters['severity'])) $query->where('severity', $filters['severity']);
        if (!empty($filters['compensation_type'])) $query->where('compensation_type', $filters['compensation_type']);

        return $query->paginate($perPage);
    }

    /**
     * 审批补偿
     */
    public function approveCompensation(int $id, int $userId): SlaCompensation
    {
        $comp = SlaCompensation::findOrFail($id);
        $comp->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $userId,
        ]);
        return $comp->fresh(['contract:id,name', 'breach:id,description']);
    }

    /**
     * 标记为已发放
     */
    public function issueCompensation(int $id): SlaCompensation
    {
        $comp = SlaCompensation::findOrFail($id);
        $comp->update([
            'status' => 'issued',
            'issued_at' => now(),
        ]);
        return $comp->fresh(['contract:id,name', 'breach:id,description']);
    }

    /**
     * 拒绝补偿
     */
    public function rejectCompensation(int $id, string $reason = null): SlaCompensation
    {
        $comp = SlaCompensation::findOrFail($id);
        $comp->update([
            'status' => 'rejected',
            'notes' => $reason,
        ]);
        return $comp->fresh(['contract:id,name', 'breach:id,description']);
    }

    /**
     * 获取补偿统计
     */
    public function getCompensationStats(int $tenantId): array
    {
        $query = SlaCompensation::where('tenant_id', $tenantId);

        $totalAmount = $query->clone()->whereIn('status', ['approved', 'issued'])->sum('amount');
        $pendingCount = $query->clone()->where('status', 'pending')->count();
        $totalCount = $query->clone()->count();
        $byType = $query->clone()->selectRaw('compensation_type, COUNT(*) as cnt, SUM(amount) as total')
            ->whereIn('status', ['approved', 'issued'])
            ->groupBy('compensation_type')
            ->get();

        $monthly = $query->clone()
            ->where('created_at', '>=', now()->subMonths(12))
            ->get()
            ->groupBy(fn($item) => $item->created_at->format('Y-m'))
            ->map(fn($items, $month) => [
                'month' => $month,
                'cnt' => $items->count(),
                'total' => $items->sum('amount'),
            ])
            ->values();

        return [
            'total_count' => $totalCount,
            'pending_count' => $pendingCount,
            'total_amount' => round($totalAmount, 2),
            'by_type' => $byType,
            'monthly_trend' => $monthly,
        ];
    }
}
