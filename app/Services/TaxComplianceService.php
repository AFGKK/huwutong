<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\TaxComplianceDocument;
use App\Models\TaxComplianceReport;
use App\Models\TaxComplianceRule;
use App\Models\TaxRate;
use Illuminate\Support\Facades\DB;

/**
 * 全球税收合规服务 (M3-18)
 *
 * 增强现有税务基础设施，提供：
 * 1. 税务合规报告（VAT/GST/销售税申报）
 * 2. 合规文档与税局通信管理
 * 3. 特殊税务规则引擎
 * 4. 合规仪表盘与逾期提醒
 */
class TaxComplianceService
{
    /**
     * 生成或获取税务合规报告
     */
    public function generateReport(int $tenantId, string $country, string $period, string $reportType = 'vat_return'): TaxComplianceReport
    {
        // 解析周期
        [$year, $month] = explode('-', $period);
        $periodStart = "{$year}-{$month}-01";
        $periodEnd = date('Y-m-t', strtotime($periodStart));

        // 查询该周期内该国家的发票
        $invoices = Invoice::where('tenant_id', $tenantId)
            ->where('billing_country', $country)
            ->whereDate('created_at', '>=', $periodStart)
            ->whereDate('created_at', '<=', $periodEnd)
            ->get();

        $totalSales = 0;
        $totalTaxCollected = 0;
        $totalTaxPayable = 0;
        $totalExempt = 0;
        $totalReverseCharge = 0;
        $breakdown = [];

        foreach ($invoices as $invoice) {
            $amount = (float) ($invoice->subtotal ?? $invoice->amount ?? 0);
            $taxAmount = (float) ($invoice->tax_amount ?? 0);
            $exemptReason = $invoice->tax_exempt_reason;

            $totalSales += $amount;
            $totalTaxCollected += $taxAmount;

            if ($exemptReason === 'exempt') {
                $totalExempt += $amount;
            } elseif ($exemptReason === 'reverse_charge') {
                $totalReverseCharge += $amount;
            }

            // 按税率分类
            $rateKey = (string) ($invoice->tax_rate_applied ?? 0);
            if (!isset($breakdown[$rateKey])) {
                $breakdown[$rateKey] = ['rate' => $invoice->tax_rate_applied ?? 0, 'sales' => 0, 'tax' => 0, 'count' => 0];
            }
            $breakdown[$rateKey]['sales'] += $amount;
            $breakdown[$rateKey]['tax'] += $taxAmount;
            $breakdown[$rateKey]['count']++;
        }

        // 应付税额 = 实收税额（简易计算，实际应考虑进项抵扣）
        $totalTaxPayable = $totalTaxCollected;

        // 获取或创建报告
        $report = TaxComplianceReport::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'country' => $country,
                'period' => $period,
                'report_type' => $reportType,
            ],
            [
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'status' => 'draft',
                'total_sales' => $totalSales,
                'total_tax_collected' => $totalTaxCollected,
                'total_tax_payable' => $totalTaxPayable,
                'total_exempt_sales' => $totalExempt,
                'total_reverse_charge' => $totalReverseCharge,
                'breakdown' => array_values($breakdown),
            ]
        );

        return $report;
    }

    /**
     * 获取合规报告列表
     */
    public function listReports(int $tenantId, array $filters = []): array
    {
        $query = TaxComplianceReport::where('tenant_id', $tenantId)->orderByDesc('period');

        if (!empty($filters['country'])) {
            $query->where('country', strtoupper($filters['country']));
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['report_type'])) {
            $query->where('report_type', $filters['report_type']);
        }
        if (!empty($filters['period'])) {
            $query->where('period', $filters['period']);
        }

        $perPage = $filters['per_page'] ?? 20;
        return $query->paginate($perPage)->withQueryString()->toArray();
    }

    /**
     * 标记报告为已申报
     */
    public function fileReport(int $tenantId, int $reportId): TaxComplianceReport
    {
        $report = TaxComplianceReport::where('tenant_id', $tenantId)->findOrFail($reportId);
        $report->update([
            'status' => 'filed',
            'filed_at' => now(),
        ]);
        return $report;
    }

    // ─── 合规文档管理 ───

    /**
     * 列示合规文档
     */
    public function listDocuments(int $tenantId, array $filters = []): array
    {
        $query = TaxComplianceDocument::with('creator')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('document_date');

        if (!empty($filters['country'])) {
            $query->where('country', strtoupper($filters['country']));
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['document_type'])) {
            $query->where('document_type', $filters['document_type']);
        }
        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('title', 'like', "%{$s}%")
                    ->orWhere('reference_number', 'like', "%{$s}%");
            });
        }

        $perPage = $filters['per_page'] ?? 20;
        return $query->paginate($perPage)->withQueryString()->toArray();
    }

    /**
     * 创建合规文档
     */
    public function createDocument(int $tenantId, int $userId, array $data): TaxComplianceDocument
    {
        return TaxComplianceDocument::create([
            'tenant_id' => $tenantId,
            'created_by' => $userId,
            'document_type' => $data['document_type'],
            'country' => strtoupper($data['country']),
            'title' => $data['title'],
            'reference_number' => $data['reference_number'] ?? null,
            'document_date' => $data['document_date'],
            'due_date' => $data['due_date'] ?? null,
            'status' => $data['status'] ?? 'pending',
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * 更新合规文档
     */
    public function updateDocument(int $tenantId, int $documentId, array $data): TaxComplianceDocument
    {
        $doc = TaxComplianceDocument::where('tenant_id', $tenantId)->findOrFail($documentId);
        $doc->update($data);
        return $doc;
    }

    /**
     * 删除合规文档
     */
    public function deleteDocument(int $tenantId, int $documentId): void
    {
        TaxComplianceDocument::where('tenant_id', $tenantId)->where('id', $documentId)->delete();
    }

    // ─── 合规规则管理 ───

    /**
     * 列示合规规则
     */
    public function listRules(int $tenantId, array $filters = []): array
    {
        $query = TaxComplianceRule::where('tenant_id', $tenantId)->orderByDesc('created_at');

        if (!empty($filters['rule_type'])) {
            $query->where('rule_type', $filters['rule_type']);
        }
        if (!empty($filters['country'])) {
            $query->where('country', strtoupper($filters['country']));
        }
        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = $filters['per_page'] ?? 20;
        return $query->paginate($perPage)->withQueryString()->toArray();
    }

    /**
     * 创建合规规则
     */
    public function createRule(int $tenantId, array $data): TaxComplianceRule
    {
        return TaxComplianceRule::create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'rule_type' => $data['rule_type'],
            'country' => !empty($data['country']) ? strtoupper($data['country']) : null,
            'region_code' => $data['region_code'] ?? null,
            'condition_type' => $data['condition_type'] ?? null,
            'condition_value' => $data['condition_value'] ?? null,
            'rate_modifier' => $data['rate_modifier'] ?? null,
            'action' => $data['action'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'metadata' => $data['metadata'] ?? null,
        ]);
    }

    /**
     * 更新合规规则
     */
    public function updateRule(int $tenantId, int $ruleId, array $data): TaxComplianceRule
    {
        $rule = TaxComplianceRule::where('tenant_id', $tenantId)->findOrFail($ruleId);
        $rule->update($data);
        return $rule;
    }

    /**
     * 删除合规规则
     */
    public function deleteRule(int $tenantId, int $ruleId): void
    {
        TaxComplianceRule::where('tenant_id', $tenantId)->where('id', $ruleId)->delete();
    }

    // ─── 仪表盘 ───

    /**
     * 获取税务合规仪表盘
     */
    public function getDashboard(int $tenantId): array
    {
        // 逾期文档
        $overdueCount = TaxComplianceDocument::where('tenant_id', $tenantId)
            ->where('status', 'overdue')
            ->count();

        // 待处理文档
        $pendingDocs = TaxComplianceDocument::where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->count();

        // 即将到期的文档（未来30天内）
        $upcomingDue = TaxComplianceDocument::where('tenant_id', $tenantId)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '>=', now())
            ->whereDate('due_date', '<=', now()->addDays(30))
            ->where('status', '!=', 'completed')
            ->count();

        // 各国家最新报告
        $latestReports = TaxComplianceReport::whereIn('id', function ($q) use ($tenantId) {
            $q->selectRaw('MAX(id)')
                ->from('tax_compliance_reports')
                ->where('tenant_id', $tenantId)
                ->groupBy('country', 'report_type');
        })->get();

        // 各状态汇总
        $pendingReports = TaxComplianceReport::where('tenant_id', $tenantId)
            ->where('status', '!=', 'filed')
            ->count();

        $filedReports = TaxComplianceReport::where('tenant_id', $tenantId)
            ->where('status', 'filed')
            ->count();

        // 活跃规则数
        $activeRules = TaxComplianceRule::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->count();

        // 覆盖国家数
        $coveredCountries = TaxComplianceReport::where('tenant_id', $tenantId)
            ->distinct()
            ->count('country');

        // 本季度总税务负债
        $quarterStart = now()->startOfQuarter()->toDateString();
        $quarterEnd = now()->endOfQuarter()->toDateString();
        $quarterLiability = TaxComplianceReport::where('tenant_id', $tenantId)
            ->whereBetween('period_start', [$quarterStart, $quarterEnd])
            ->sum('total_tax_payable');

        return [
            'overdue_documents' => $overdueCount,
            'pending_documents' => $pendingDocs,
            'upcoming_due' => $upcomingDue,
            'pending_reports' => $pendingReports,
            'filed_reports' => $filedReports,
            'active_rules' => $activeRules,
            'covered_countries' => $coveredCountries,
            'quarter_liability' => round($quarterLiability, 2),
            'latest_reports' => $latestReports,
        ];
    }
}
