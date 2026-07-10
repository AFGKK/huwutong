<?php

namespace App\Services;

use App\Models\CrossBorderMonthlyReport;
use App\Models\CrossBorderPayment;
use App\Models\CurrencyConversionLog;
use App\Models\ExchangeRate;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

/**
 * 跨境支付与多币种增强服务 (M3-83)
 *
 * 核心功能：
 * 1. 货币转换审计 — 记录每次转换的详细信息
 * 2. 跨境支付追踪 — 记录跨境交易的合规和费用信息
 * 3. 月度跨境报表 — 按货币聚合的收入/费用/退款统计
 * 4. 合规检查 — 对跨境支付进行合规检查
 */
class CrossBorderService
{
    // ═══════ 货币转换审计 ═══════

    public function logConversion(
        int $tenantId,
        string $from, string $to,
        float $fromAmount, float $toAmount,
        float $rateUsed,
        array $extra = []
    ): CurrencyConversionLog {
        return CurrencyConversionLog::create(array_merge([
            'tenant_id' => $tenantId,
            'from_currency' => $from,
            'to_currency' => $to,
            'from_amount' => $fromAmount,
            'to_amount' => $toAmount,
            'rate_used' => $rateUsed,
            'rate_markup' => $extra['rate_markup'] ?? 0,
            'conversion_type' => $extra['conversion_type'] ?? 'auto',
            'source' => $extra['source'] ?? null,
            'customer_id' => $extra['customer_id'] ?? null,
            'invoice_id' => $extra['invoice_id'] ?? null,
            'metadata' => $extra['metadata'] ?? null,
        ], $extra));
    }

    public function getConversionLogs(int $tenantId, array $filters = [], int $perPage = 20): array
    {
        $query = CurrencyConversionLog::where('tenant_id', $tenantId);

        if (!empty($filters['from_currency'])) $query->where('from_currency', $filters['from_currency']);
        if (!empty($filters['to_currency'])) $query->where('to_currency', $filters['to_currency']);
        if (!empty($filters['source'])) $query->where('source', $filters['source']);
        if (!empty($filters['from_date'])) $query->whereDate('created_at', '>=', $filters['from_date']);
        if (!empty($filters['to_date'])) $query->whereDate('created_at', '<=', $filters['to_date']);
        if (!empty($filters['customer_id'])) $query->where('customer_id', $filters['customer_id']);

        return $query->orderByDesc('created_at')
            ->paginate($perPage)
            ->toArray();
    }

    // ═══════ 跨境支付追踪 ═══════

    public function recordCrossBorderPayment(int $tenantId, array $data): CrossBorderPayment
    {
        // 如果提供了 invoice_id 但未提供 amount_cny，自动计算
        if (empty($data['amount_cny']) && !empty($data['amount']) && !empty($data['currency']) && $data['currency'] !== 'CNY') {
            $rate = ExchangeRate::where('from_currency', $data['currency'])
                ->where('to_currency', 'CNY')
                ->where('effective_at', '<=', now())
                ->orderByDesc('effective_at')
                ->first();

            if ($rate) {
                $data['amount_cny'] = round($data['amount'] * $rate->rate, 2);
                $data['exchange_rate'] = $rate->rate;
            }
        }

        // 合规检查
        $complianceCheck = $this->performComplianceCheck($data);
        $data['compliance_info'] = $complianceCheck;
        $data['tenant_id'] = $tenantId;

        return CrossBorderPayment::create($data);
    }

    public function getCrossBorderPayments(int $tenantId, array $filters = [], int $perPage = 20): array
    {
        $query = CrossBorderPayment::where('tenant_id', $tenantId);

        if (!empty($filters['currency'])) $query->where('currency', $filters['currency']);
        if (!empty($filters['status'])) $query->where('status', $filters['status']);
        if (!empty($filters['payment_gateway'])) $query->where('payment_gateway', $filters['payment_gateway']);
        if (!empty($filters['from_date'])) $query->whereDate('created_at', '>=', $filters['from_date']);
        if (!empty($filters['to_date'])) $query->whereDate('created_at', '<=', $filters['to_date']);

        return $query->orderByDesc('created_at')
            ->paginate($perPage)
            ->toArray();
    }

    /**
     * 合规检查 — 检查跨境支付是否需要额外审核
     */
    public function performComplianceCheck(array $paymentData): array
    {
        $checks = [];
        $passed = true;

        // 检查1：大额交易（>等值10万CNY）
        $amountCny = $paymentData['amount_cny'] ?? 0;
        if ($amountCny > 100000) {
            $checks[] = ['type' => 'large_amount', 'status' => 'warning', 'message' => '大额跨境交易需备案'];
            $passed = false;
        }

        // 检查2：非CNY、非USD的高风险货币
        $highRiskCurrencies = ['JPY', 'KRW']; // 示例：高波动货币
        if (in_array($paymentData['currency'] ?? '', $highRiskCurrencies)) {
            $checks[] = ['type' => 'high_risk_currency', 'status' => 'info', 'message' => '货币波动较大，建议关注汇率风险'];
        }

        // 检查3：客户国家非CN
        $customerCountry = $paymentData['customer_country'] ?? 'CN';
        if ($customerCountry !== 'CN') {
            $checks[] = ['type' => 'cross_border', 'status' => 'info', 'message' => "跨境交易至 {$customerCountry}"];
        }

        // 检查4：退款类型的跨境交易
        if (($paymentData['transaction_type'] ?? 'payment') === 'refund' && $amountCny > 50000) {
            $checks[] = ['type' => 'cross_border_refund', 'status' => 'warning', 'message' => '大额跨境退款需人工审核'];
            $passed = false;
        }

        return [
            'passed' => $passed,
            'checks' => $checks,
            'checked_at' => now()->toIso8601String(),
        ];
    }

    // ═══════ 月度报表 ═══════

    public function generateMonthlyReport(int $tenantId, string $reportMonth): CrossBorderMonthlyReport
    {
        // 从 cross_border_payments 聚合数据
        $payments = CrossBorderPayment::where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->where(DB::raw(db_date_format('created_at', '%Y-%m')), $reportMonth)
            ->get()
            ->groupBy('currency');

        $results = [];
        foreach ($payments as $currency => $txns) {
            $revenue = $txns->where('transaction_type', 'payment')->sum('amount');
            $refunds = $txns->where('transaction_type', 'refund')->sum('amount');
            $fees = $txns->sum('gateway_fee');
            $feesCny = $txns->sum('gateway_fee_cny');
            $revenueCny = $txns->where('transaction_type', 'payment')->sum('amount_cny');
            $customerIds = $txns->pluck('customer_id')->unique()->filter()->count();
            $countries = $txns->whereNotNull('customer_country')
                ->groupBy('customer_country')
                ->map->count()
                ->sortDesc()
                ->take(5)
                ->toArray();

            $results[] = [
                'currency' => $currency,
                'total_revenue' => $revenue,
                'total_revenue_cny' => $revenueCny,
                'total_refunds' => $refunds,
                'total_fees' => $fees,
                'total_fees_cny' => $feesCny,
                'net_revenue' => $revenue - $refunds,
                'transaction_count' => $txns->count(),
                'customer_count' => $customerIds,
                'top_countries' => $countries,
            ];
        }

        // 批量写入月度报表
        foreach ($results as $result) {
            CrossBorderMonthlyReport::updateOrCreate(
                ['tenant_id' => $tenantId, 'report_month' => $reportMonth, 'currency' => $result['currency']],
                $result
            );
        }

        // 返回第一个结果或空报告
        if (!empty($results)) {
            $first = $results[0];
            return CrossBorderMonthlyReport::where('tenant_id', $tenantId)
                ->where('report_month', $reportMonth)
                ->where('currency', $first['currency'])
                ->first();
        }

        return CrossBorderMonthlyReport::create([
            'tenant_id' => $tenantId,
            'report_month' => $reportMonth,
            'currency' => 'CNY',
        ]);
    }

    public function getMonthlyReports(int $tenantId, array $filters = []): array
    {
        $query = CrossBorderMonthlyReport::where('tenant_id', $tenantId);

        if (!empty($filters['currency'])) $query->where('currency', $filters['currency']);
        if (!empty($filters['from_month'])) $query->where('report_month', '>=', $filters['from_month']);
        if (!empty($filters['to_month'])) $query->where('report_month', '<=', $filters['to_month']);

        return $query->orderByDesc('report_month')
            ->orderBy('currency')
            ->get()
            ->toArray();
    }

    // ═══════ 仪表盘统计 ═══════

    public function getDashboardStats(int $tenantId): array
    {
        // 本月的跨境支付统计
        $thisMonth = now()->format('Y-m');

        $monthlyPayments = CrossBorderPayment::where('tenant_id', $tenantId)
            ->where(DB::raw(db_date_format('created_at', '%Y-%m')), $thisMonth)
            ->where('status', 'completed');

        $totalCrossBorderRevenue = (clone $monthlyPayments)
            ->where('transaction_type', 'payment')
            ->sum('amount_cny');

        $totalFees = (clone $monthlyPayments)->sum('gateway_fee_cny');
        $totalTransactions = (clone $monthlyPayments)->count();
        $currencyCount = (clone $monthlyPayments)->distinct('currency')->count('currency');
        $totalConversions = CurrencyConversionLog::where('tenant_id', $tenantId)->count();

        $byCurrency = (clone $monthlyPayments)
            ->selectRaw('currency, SUM(amount_cny) as total, COUNT(*) as cnt')
            ->groupBy('currency')
            ->orderByDesc('total')
            ->get()
            ->toArray();

        $complianceWarnings = CrossBorderPayment::where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->where('compliance_info->passed', false)
            ->count();

        return [
            'monthly_revenue_cny' => $totalCrossBorderRevenue,
            'monthly_fees_cny' => $totalFees,
            'monthly_transactions' => $totalTransactions,
            'active_currencies' => $currencyCount,
            'total_conversions' => $totalConversions,
            'by_currency' => $byCurrency,
            'compliance_warnings' => $complianceWarnings,
        ];
    }
}
