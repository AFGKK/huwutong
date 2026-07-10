<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\License;
use App\Models\MeteredPrice;
use App\Models\MeteredBillingAlert;
use App\Models\MeteredAlertHistory;
use App\Models\MeteredAutoSwitchRule;
use App\Models\Subscription;
use App\Models\UsageAggregate;
use App\Services\UsageMeterService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 用量计费 Metered Billing
 *
 * 桥接 UsageMeterService 计量的用量数据和 BillingService 的账单系统。
 * 支持：
 * - 价格阶梯配置（MeteredPrice）
 * - 用量聚合→账单行项目生成
 * - 月度/季度/年度结算
 * - 用量超额保护
 * - 混合计费（订阅费 + 用量费）
 */
class MeteredBillingService
{
    public function __construct(
        protected UsageMeterService $usageMeterService,
    ) {}

    /**
     * 为订阅生成用量账单
     *
     * @param Subscription $subscription 订阅
     * @param string|null $periodStart 计费周期开始（null=从上一次结算到now）
     * @param string|null $periodEnd 计费周期结束
     * @param bool $dryRun 试算模式（不生成发票）
     * @return array{invoice: ?Invoice, line_items: array, totals: array, errors: array}
     */
    public function generateMeteredInvoice(Subscription $subscription, ?string $periodStart = null, ?string $periodEnd = null, bool $dryRun = false): array
    {
        $tenantId = $subscription->tenant_id;
        $meteredConfig = $subscription->metered_config;

        if (!$meteredConfig || !($meteredConfig['enabled'] ?? false)) {
            return ['invoice' => null, 'line_items' => [], 'totals' => [], 'errors' => ['订阅未启用用量计费']];
        }

        // 确定计费周期
        $periodEnd = $periodEnd ? Carbon::parse($periodEnd) : Carbon::now();
        $periodStart = $periodStart
            ? Carbon::parse($periodStart)
            : ($subscription->last_billed_at ? Carbon::parse($subscription->last_billed_at) : $subscription->starts_at);

        if (!$periodStart) {
            $periodStart = $periodEnd->copy()->subMonth();
        }

        $billingPeriod = $meteredConfig['billing_period'] ?? 'monthly';
        $capType = $meteredConfig['cap_type'] ?? 'soft';
        $monthlyCap = $meteredConfig['monthly_cap'] ?? null;

        // 获取该订阅关联的所有License的用量
        $licenses = License::where('subscription_id', $subscription->id)
            ->where('tenant_id', $tenantId)
            ->get();

        if ($licenses->isEmpty()) {
            return ['invoice' => null, 'line_items' => [], 'totals' => [], 'errors' => ['订阅下没有License']];
        }

        // 获取活跃的价格配置
        $meteredPrices = MeteredPrice::where('tenant_id', $tenantId)
            ->where('billing_period', $billingPeriod)
            ->where('is_active', true)
            ->get()
            ->keyBy('metric_key');

        if ($meteredPrices->isEmpty()) {
            return ['invoice' => null, 'line_items' => [], 'totals' => [], 'errors' => ['未找到用量计费价格配置']];
        }

        $lineItems = [];
        $errors = [];
        $totalAmount = 0;
        $totalQuantity = 0;

        foreach ($meteredPrices as $metricKey => $meteredPrice) {
            // 聚合该周期内所有License的用量
            $quantity = $this->getAggregatedUsage($licenses, $metricKey, $periodStart, $periodEnd);

            if ($quantity <= 0) continue;

            // 计算费用
            $costResult = $meteredPrice->calculateCost($quantity);

            // 软上限：超出部分只有警告
            if ($capType === 'hard' && $monthlyCap !== null && $costResult['total_cost'] > $monthlyCap) {
                $costResult['total_cost'] = min($costResult['total_cost'], $monthlyCap);
                $costResult['capped'] = true;
            }

            $unitPrice = $quantity > 0 ? round($costResult['total_cost'] / $quantity, 4) : 0;

            $lineItems[] = [
                'type' => 'metered_usage',
                'description' => "{$meteredPrice->name}（{$periodStart->format('Y-m-d')} ~ {$periodEnd->format('Y-m-d')}）",
                'metric_key' => $metricKey,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'amount' => $costResult['total_cost'],
                'currency' => $costResult['currency'] ?? 'CNY',
                'breakdown' => $costResult,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
            ];

            $totalAmount += $costResult['total_cost'];
            $totalQuantity += $quantity;
        }

        $totalAmount = round($totalAmount, 2);

        if ($totalAmount <= 0) {
            return ['invoice' => null, 'line_items' => $lineItems, 'totals' => ['amount' => 0], 'errors' => ['用量金额为0，跳过结算']];
        }

        if ($dryRun) {
            return [
                'invoice' => null,
                'line_items' => $lineItems,
                'totals' => [
                    'amount' => $totalAmount,
                    'total_quantity' => $totalQuantity,
                    'period_start' => $periodStart->toDateTimeString(),
                    'period_end' => $periodEnd->toDateTimeString(),
                    'currency' => 'CNY',
                ],
                'errors' => $errors,
            ];
        }

        // 创建发票
        $invoice = null;
        DB::transaction(function () use ($subscription, $tenantId, $lineItems, $totalAmount, $periodStart, $periodEnd, &$invoice) {
            $invoice = Invoice::create([
                'tenant_id' => $tenantId,
                'customer_id' => $subscription->customer_id,
                'subscription_id' => $subscription->id,
                'invoice_no' => $this->generateInvoiceNo($tenantId),
                'amount' => $totalAmount,
                'subtotal' => $totalAmount,
                'discount_amount' => 0,
                'currency' => 'CNY',
                'status' => 'pending',
                'paid' => false,
                'billing_reason' => 'metered_usage',
                'due_at' => Carbon::now()->addDays(7),
                'metadata' => [
                    'billing_period' => $periodStart->format('Y-m-d') . ' ~ ' . $periodEnd->format('Y-m-d'),
                    'type' => 'metered',
                ],
            ]);

            foreach ($lineItems as $item) {
                $invoice->lineItems()->create([
                    'tenant_id' => $tenantId,
                    'type' => $item['type'],
                    'description' => $item['description'],
                    'metric_key' => $item['metric_key'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'amount' => $item['amount'],
                    'currency' => $item['currency'] ?? 'CNY',
                    'breakdown' => $item['breakdown'] ?? null,
                    'period_start' => $item['period_start'],
                    'period_end' => $item['period_end'],
                    'sort_order' => 0,
                ]);
            }

            // 更新订阅的 last_billed_at
            $subscription->update([
                'last_billed_at' => Carbon::now(),
                'total_paid' => round(($subscription->total_paid ?? 0) + $totalAmount, 2),
            ]);
        });

        return [
            'invoice' => $invoice,
            'line_items' => $lineItems,
            'totals' => [
                'amount' => $totalAmount,
                'total_quantity' => $totalQuantity,
                'period_start' => $periodStart->toDateTimeString(),
                'period_end' => $periodEnd->toDateTimeString(),
                'currency' => 'CNY',
            ],
            'errors' => $errors,
        ];
    }

    /**
     * 批量结算所有启用了用量计费的订阅
     */
    public function batchGenerateMeteredInvoices(string $billingPeriod = 'monthly', bool $dryRun = false): array
    {
        $subscriptions = Subscription::where('status', 'active')
            ->whereNotNull('metered_config')
            ->where('metered_config->enabled', true)
            ->get();

        $results = [];
        foreach ($subscriptions as $subscription) {
            try {
                $results[] = [
                    'subscription_id' => $subscription->id,
                    'customer_id' => $subscription->customer_id,
                    'result' => $this->generateMeteredInvoice($subscription, null, null, $dryRun),
                ];
            } catch (\Exception $e) {
                Log::error("Metered billing failed for subscription {$subscription->id}: {$e->getMessage()}");
                $results[] = [
                    'subscription_id' => $subscription->id,
                    'customer_id' => $subscription->customer_id,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $successCount = count(array_filter($results, fn($r) => empty($r['error'])));
        return [
            'total' => count($subscriptions),
            'success' => $successCount,
            'results' => $results,
        ];
    }

    /**
     * 获取某个License在周期内的用量
     */
    public function getLicenseUsage(License $license, ?string $metricKey = null, ?string $periodStart = null, ?string $periodEnd = null): array
    {
        $periodEnd = $periodEnd ? Carbon::parse($periodEnd) : Carbon::now();
        $periodStart = $periodStart ? Carbon::parse($periodStart) : $periodEnd->copy()->startOfMonth();

        $query = UsageAggregate::where('license_id', $license->id)
            ->where('period_start', '>=', $periodStart)
            ->where('period_end', '<=', $periodEnd);

        if ($metricKey) {
            $query->where('metric_key', $metricKey);
        }

        $aggregates = $query->get();

        $metrics = [];
        foreach ($aggregates as $agg) {
            $key = $agg->metric_key;
            if (!isset($metrics[$key])) {
                $metrics[$key] = ['metric_key' => $key, 'total_quantity' => 0, 'record_count' => 0];
            }
            $metrics[$key]['total_quantity'] += $agg->total_quantity;
            $metrics[$key]['record_count'] += $agg->record_count;
        }

        // 尝试计算费用
        $prices = MeteredPrice::where('tenant_id', $license->tenant_id)
            ->where('is_active', true)
            ->get()
            ->keyBy('metric_key');

        foreach ($metrics as $key => &$metric) {
            if (isset($prices[$key])) {
                $cost = $prices[$key]->calculateCost($metric['total_quantity']);
                $metric['cost'] = $cost['total_cost'];
                $metric['cost_details'] = $cost;
            } else {
                $metric['cost'] = 0;
                $metric['cost_details'] = null;
            }
        }

        return [
            'license_id' => $license->id,
            'license_key' => $license->license_key,
            'period_start' => $periodStart->toDateTimeString(),
            'period_end' => $periodEnd->toDateTimeString(),
            'metrics' => array_values($metrics),
            'total_cost' => round(array_sum(array_column($metrics, 'cost')), 2),
        ];
    }

    /**
     * 获取用量计费概览（仪表盘）
     */
    public function getOverview(int $tenantId): array
    {
        $activePrices = MeteredPrice::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->count();

        $activeSubscriptions = Subscription::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereNotNull('metered_config')
            ->where('metered_config->enabled', true)
            ->count();

        $totalSubscriptions = Subscription::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->count();

        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now();

        // 本月用量计费总额
        $monthlyAmount = InvoiceLineItem::where('tenant_id', $tenantId)
            ->where('type', 'metered_usage')
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->sum('amount');

        // 价格配置
        $priceConfigs = MeteredPrice::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'metric_key' => $p->metric_key,
                'name' => $p->name,
                'unit' => $p->unit,
                'billing_period' => $p->billing_period,
                'tiers' => $p->tiers,
                'base_fee' => $p->base_fee,
                'included_quantity' => $p->included_quantity,
            ]);

        // 最近用量计费发票
        $recentInvoices = Invoice::where('tenant_id', $tenantId)
            ->where('billing_reason', 'metered_usage')
            ->with('customer:id,user_id', 'customer.user:id,name', 'lineItems')
            ->latest()
            ->take(10)
            ->get()
            ->map(fn($inv) => [
                'id' => $inv->id,
                'invoice_no' => $inv->invoice_no,
                'customer_name' => $inv->customer?->user?->name,
                'amount' => $inv->amount,
                'status' => $inv->status,
                'paid' => $inv->paid,
                'created_at' => $inv->created_at->toDateTimeString(),
                'line_items_count' => $inv->lineItems->count(),
            ]);

        return [
            'active_prices' => $activePrices,
            'active_subscriptions' => $activeSubscriptions,
            'total_subscriptions' => $totalSubscriptions,
            'metered_adoption_rate' => $totalSubscriptions > 0
                ? round(($activeSubscriptions / $totalSubscriptions) * 100, 1) : 0,
            'monthly_metered_amount' => round($monthlyAmount, 2),
            'price_configs' => $priceConfigs,
            'recent_invoices' => $recentInvoices,
        ];
    }

    /**
     * 获取该订阅关联的所有License在周期内的聚合用量
     */
    protected function getAggregatedUsage($licenses, string $metricKey, Carbon $periodStart, Carbon $periodEnd): float
    {
        $licenseIds = $licenses->pluck('id');

        $total = UsageAggregate::whereIn('license_id', $licenseIds)
            ->where('metric_key', $metricKey)
            ->where('period_start', '>=', $periodStart)
            ->where('period_end', '<=', $periodEnd)
            ->sum('total_quantity');

        return (float) $total;
    }

    /**
     * 生成发票号
     */
    protected function generateInvoiceNo(int $tenantId): string
    {
        $date = now()->format('Ymd');
        $seq = DB::table('invoices')
            ->where('tenant_id', $tenantId)
            ->whereDate('created_at', today())
            ->count() + 1;

        return "INV-MT-{$date}-{$tenantId}-" . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    // ═══════════════════════════════════════════════════════════════
    // 超额预警
    // ═══════════════════════════════════════════════════════════════

    /**
     * 检查并触发所有超额预警
     */
    public function evaluateAlerts(int $tenantId): array
    {
        $triggered = [];
        $alerts = MeteredBillingAlert::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get();

        foreach ($alerts as $alert) {
            try {
                $currentValue = $this->getAlertMetricValue($alert);
                $shouldTrigger = $this->shouldTriggerAlert($alert, $currentValue);

                if ($shouldTrigger) {
                    $this->fireAlert($alert, $currentValue);
                    $triggered[] = [
                        'alert_id' => $alert->id,
                        'name' => $alert->name,
                        'current_value' => $currentValue,
                        'threshold' => $alert->threshold_value,
                    ];
                }
            } catch (\Exception $e) {
                Log::warning("Alert eval failed: {$alert->id}", ['error' => $e->getMessage()]);
            }
        }
        return $triggered;
    }

    protected function getAlertMetricValue(MeteredBillingAlert $alert): float
    {
        $query = UsageAggregate::where('tenant_id', $alert->tenant_id)
            ->where('metric_key', $alert->metric_key);

        if ($alert->subscription_id) {
            $query->whereHas('license.subscription', fn($q) => $q->where('id', $alert->subscription_id));
        }

        $periodStart = match ($alert->window_type) {
            'daily' => now()->startOfDay(),
            'monthly' => now()->startOfMonth(),
            'billing_period' => $this->getPeriodStart($alert->subscription),
            default => now()->startOfMonth(),
        };

        return (float) $query->where('period_start', '>=', $periodStart)->sum('total_quantity');
    }

    protected function shouldTriggerAlert(MeteredBillingAlert $alert, float $currentValue): bool
    {
        if ($alert->threshold_type === 'percentage' && $alert->percentage) {
            $ratio = ($currentValue / max($alert->threshold_value, 1)) * 100;
            return $alert->direction === 'above' ? $ratio >= $alert->percentage : $ratio <= $alert->percentage;
        }
        return $alert->direction === 'above'
            ? $currentValue >= $alert->threshold_value
            : $currentValue <= $alert->threshold_value;
    }

    protected function fireAlert(MeteredBillingAlert $alert, float $currentValue): void
    {
        $channels = $alert->notify_channels ?? ['email'];
        foreach ($channels as $channel) {
            try {
                MeteredAlertHistory::create([
                    'alert_id' => $alert->id,
                    'tenant_id' => $alert->tenant_id,
                    'metric_key' => $alert->metric_key,
                    'current_value' => $currentValue,
                    'threshold_value' => $alert->threshold_value,
                    'channel' => $channel,
                    'status' => 'sent',
                    'message' => "{$alert->name}: 当前 {$currentValue}, 阈值 {$alert->threshold_value}",
                    'triggered_at' => now(),
                ]);
            } catch (\Exception $e) {
                Log::error("Alert fire failed", ['alert_id' => $alert->id, 'error' => $e->getMessage()]);
            }
        }
        $alert->update(['last_triggered_at' => now()]);
    }

    protected function getPeriodStart(?Subscription $subscription): Carbon
    {
        if (!$subscription) return now()->startOfMonth();
        return $subscription->last_billed_at ?? $subscription->starts_at ?? now()->startOfMonth();
    }

    // ═══════════════════════════════════════════════════════════════
    // 自动切换套餐
    // ═══════════════════════════════════════════════════════════════

    /**
     * 评估自动切换套餐规则
     */
    public function evaluateAutoSwitchRules(int $tenantId): array
    {
        $recommendations = [];
        $rules = MeteredAutoSwitchRule::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get();

        foreach ($rules as $rule) {
            try {
                $trend = $this->getUsageTrend($rule);
                $shouldSwitch = $this->shouldAutoSwitch($rule, $trend);

                if ($shouldSwitch) {
                    $sub = $rule->subscription;
                    $recommendations[] = [
                        'rule_id' => $rule->id,
                        'rule_name' => $rule->name,
                        'subscription_id' => $rule->subscription_id,
                        'current_plan' => $sub?->pricing_plan_slug,
                        'target_plan' => $rule->target_plan_slug,
                        'action' => $rule->action,
                        'requires_confirmation' => $rule->require_confirmation,
                        'usage_trend' => $trend,
                        'evaluated_at' => now()->toDateTimeString(),
                    ];
                    $rule->update(['last_evaluated_at' => now()]);

                    if (!$rule->require_confirmation) {
                        $sub?->update(['pricing_plan_slug' => $rule->target_plan_slug]);
                    }
                }
            } catch (\Exception $e) {
                Log::warning("Auto-switch eval failed: {$rule->id}", ['error' => $e->getMessage()]);
            }
        }
        return $recommendations;
    }

    protected function getUsageTrend(MeteredAutoSwitchRule $rule): array
    {
        $days = $rule->condition_days;
        $startDate = now()->subDays($days);

        $aggregates = UsageAggregate::where('tenant_id', $rule->tenant_id)
            ->where('metric_key', $rule->metric_key)
            ->where('period_start', '>=', $startDate)
            ->when($rule->subscription_id, fn($q) => $q->whereHas('license.subscription', fn($sq) => $sq->where('id', $rule->subscription_id)))
            ->orderBy('period_start')
            ->get();

        $total = $aggregates->sum('total_quantity');
        return [
            'total' => (int) $total,
            'daily_avg' => $days > 0 ? round($total / $days, 2) : 0,
            'days' => $days,
            'consecutive_high_days' => $this->countConsecutiveHighDays($aggregates, $rule->condition_value),
        ];
    }

    protected function shouldAutoSwitch(MeteredAutoSwitchRule $rule, array $trend): bool
    {
        return match ($rule->condition_type) {
            'usage_consecutive' => ($trend['consecutive_high_days'] ?? 0) >= $rule->condition_days,
            'usage_average' => ($trend['daily_avg'] ?? 0) >= $rule->condition_value,
            default => false,
        };
    }

    protected function countConsecutiveHighDays($aggregates, float $threshold): int
    {
        $maxRun = 0;
        $currentRun = 0;
        foreach ($aggregates as $agg) {
            if ($agg->total_quantity >= $threshold) {
                $currentRun++;
                $maxRun = max($maxRun, $currentRun);
            } else {
                $currentRun = 0;
            }
        }
        return $maxRun;
    }
}
