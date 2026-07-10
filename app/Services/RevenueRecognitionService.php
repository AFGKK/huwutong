<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\MonthlyRevenueSnapshot;
use App\Models\RevenueRecognitionLine;
use App\Models\RevenueRecognitionSchedule;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 收入确认服务（ASC 606 / IFRS 15）（M3-55）
 *
 * 核心功能：
 * - 创建收入确认排程（订阅创建时自动生成分期确认计划）
 * - 执行收入确认（定时任务每日运行）
 * - 计算递延收入余额
 * - 生成月度收入快照（MRR/ARR/Churn）
 * - 生成 ASC 606 财务报告
 */
class RevenueRecognitionService
{
    // ═══════════════════════════════════════════
    // 排程创建
    // ═══════════════════════════════════════════

    /**
     * 为发票创建收入确认排程
     *
     * 根据订阅的 billing_period 决定分期方式：
     * - monthly: 一次性确认（1期）
     * - quarterly: 分期3个月
     * - semi_annually: 分期6个月
     * - yearly: 分期12个月
     */
    public function createSchedule(Invoice $invoice): RevenueRecognitionSchedule
    {
        $subscription = $invoice->subscription;
        $billingPeriod = $subscription?->billing_period ?? 'monthly';
        $amount = (float) $invoice->amount;

        // 计算分期参数
        $totalPeriods = $this->getPeriodCount($billingPeriod);
        $startDate = $invoice->paid_at?->toDateString() ?? now()->toDateString();
        $endDate = Carbon::parse($startDate)->addMonths($totalPeriods)->subDay()->toDateString();

        // 创建排程
        $schedule = RevenueRecognitionSchedule::create([
            'tenant_id' => $invoice->tenant_id,
            'invoice_id' => $invoice->id,
            'subscription_id' => $subscription?->id,
            'revenue_type' => $invoice->billing_reason === 'upgrade' ? 'upgrade' : 'subscription',
            'billing_period' => $billingPeriod,
            'total_amount' => $amount,
            'recognized_amount' => 0,
            'deferred_amount' => $amount,
            'currency' => $invoice->currency ?? 'CNY',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_periods' => $totalPeriods,
            'recognized_periods' => 0,
            'recognition_method' => 'straight_line',
            'status' => $totalPeriods <= 1 ? 'completed' : 'active',
            'metadata' => [
                'invoice_no' => $invoice->invoice_no,
                'billing_reason' => $invoice->billing_reason,
                'customer_id' => $invoice->customer_id,
            ],
        ]);

        // 创建确认明细行
        $this->createScheduleLines($schedule, $amount, $totalPeriods, $startDate);

        // 如果只有1期（如月付），立即确认全部
        if ($totalPeriods <= 1) {
            $this->recognizeLine($schedule, 1);
        }

        Log::info('RevenueRecognition: schedule created', [
            'invoice_id' => $invoice->id,
            'amount' => $amount,
            'periods' => $totalPeriods,
            'method' => 'straight_line',
        ]);

        return $schedule->fresh();
    }

    /**
     * 创建确认明细行
     */
    protected function createScheduleLines(
        RevenueRecognitionSchedule $schedule,
        float $totalAmount,
        int $totalPeriods,
        string $startDate,
    ): void {
        // 直线法：每月金额 = 总金额 / 总月数
        $perPeriod = round($totalAmount / $totalPeriods, 2);
        $remainder = round($totalAmount - ($perPeriod * $totalPeriods), 2);

        $lines = [];
        for ($i = 1; $i <= $totalPeriods; $i++) {
            $amount = $perPeriod;
            if ($i === $totalPeriods) {
                $amount += $remainder; // 尾差调整
            }

            $recognitionDate = Carbon::parse($startDate)->addMonths($i - 1)->toDateString();

            $lines[] = [
                'schedule_id' => $schedule->id,
                'period_number' => $i,
                'recognition_date' => $recognitionDate,
                'amount' => round($amount, 2),
                'currency' => $schedule->currency,
                'description' => "第 {$i}/{$totalPeriods} 期 — {$recognitionDate}",
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        RevenueRecognitionLine::insert($lines);
    }

    // ═══════════════════════════════════════════
    // 收入确认执行
    // ═══════════════════════════════════════════

    /**
     * 执行当期收入确认（定时任务调用）
     *
     * 找出所有需要在本期确认且尚未确认的收入行并确认
     *
     * @return array ['recognized_count' => int, 'total_amount' => float, 'details' => array]
     */
    public function processRecognition(?Carbon $asOfDate = null): array
    {
        $asOfDate = $asOfDate ?? now();
        $dateStr = $asOfDate->toDateString();
        $recognized = [];
        $totalAmount = 0;

        // 查找所有应该在本期确认但尚未确认的行
        $pendingLines = RevenueRecognitionLine::where('status', 'pending')
            ->where('recognition_date', '<=', $dateStr)
            ->with('schedule')
            ->get();

        foreach ($pendingLines as $line) {
            $schedule = $line->schedule;

            // 检查排程是否有效
            if (! $schedule || $schedule->status !== 'active') {
                continue;
            }

            $this->recognizeLine($schedule, $line->period_number);

            $recognized[] = [
                'schedule_id' => $schedule->id,
                'period' => $line->period_number,
                'amount' => (float) $line->amount,
                'date' => $dateStr,
            ];
            $totalAmount += (float) $line->amount;
        }

        Log::info('RevenueRecognition: processed', [
            'as_of_date' => $dateStr,
            'lines_recognized' => count($recognized),
            'total_amount' => $totalAmount,
        ]);

        return [
            'recognized_count' => count($recognized),
            'total_amount' => round($totalAmount, 2),
            'details' => $recognized,
        ];
    }

    /**
     * 确认单条收入行
     */
    protected function recognizeLine(RevenueRecognitionSchedule $schedule, int $periodNumber): void
    {
        DB::transaction(function () use ($schedule, $periodNumber) {
            $line = RevenueRecognitionLine::where('schedule_id', $schedule->id)
                ->where('period_number', $periodNumber)
                ->first();

            if (! $line || $line->status === 'recognized') {
                return;
            }

            $line->update([
                'status' => 'recognized',
                'recognized_at' => now(),
            ]);

            $newRecognized = (float) $schedule->recognized_amount + (float) $line->amount;
            $newDeferred = (float) $schedule->total_amount - $newRecognized;
            $newPeriods = $schedule->recognized_periods + 1;

            $schedule->update([
                'recognized_amount' => round($newRecognized, 2),
                'deferred_amount' => round(max(0, $newDeferred), 2),
                'recognized_periods' => $newPeriods,
                'last_recognized_at' => now(),
                'status' => $newPeriods >= $schedule->total_periods ? 'completed' : 'active',
                'completed_at' => $newPeriods >= $schedule->total_periods ? now() : null,
            ]);
        });
    }

    // ═══════════════════════════════════════════
    // 递延收入查询
    // ═══════════════════════════════════════════

    /**
     * 获取递延收入余额（截止日期）
     */
    public function getDeferredRevenue(?Carbon $asOfDate = null, ?int $tenantId = null): float
    {
        $query = RevenueRecognitionSchedule::where('status', 'active');

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        if ($asOfDate) {
            $query->where('start_date', '<=', $asOfDate->toDateString());
        }

        return (float) $query->sum('deferred_amount');
    }

    /**
     * 获取已确认收入总额
     */
    public function getRecognizedRevenue(
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        ?int $tenantId = null,
    ): float {
        $query = RevenueRecognitionLine::where('status', 'recognized');

        if ($tenantId) {
            $query->whereHas('schedule', fn($q) => $q->where('tenant_id', $tenantId));
        }

        if ($startDate) {
            $query->whereDate('recognition_date', '>=', $startDate->toDateString());
        }

        if ($endDate) {
            $query->whereDate('recognition_date', '<=', $endDate->toDateString());
        }

        return (float) $query->sum('amount');
    }

    /**
     * 获取排程概览（含进度）
     */
    public function getScheduleOverview(int $tenantId, array $filters = []): array
    {
        $query = RevenueRecognitionSchedule::forTenant($tenantId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('start_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('end_date', '<=', $filters['date_to']);
        }

        $schedules = $query->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 20);

        return [
            'schedules' => $schedules,
            'summary' => $this->getSummary($tenantId),
        ];
    }

    /**
     * 收入确认汇总
     */
    public function getSummary(int $tenantId): array
    {
        $activeSchedules = RevenueRecognitionSchedule::forTenant($tenantId);
        $completedSchedules = RevenueRecognitionSchedule::forTenant($tenantId)->completed();

        $totalAmount = (float) (clone $activeSchedules)->sum('total_amount');
        $recognized = (float) (clone $activeSchedules)->sum('recognized_amount');
        $deferred = (float) (clone $activeSchedules)->sum('deferred_amount');
        $completedAmount = (float) $completedSchedules->sum('total_amount');

        $activeCount = (clone $activeSchedules)->where('status', 'active')->count();
        $completedCount = $completedSchedules->count();
        $pendingCount = (clone $activeSchedules)->where('status', 'pending')->count();

        // 本月应确认
        $thisMonthStart = now()->startOfMonth()->toDateString();
        $thisMonthEnd = now()->endOfMonth()->toDateString();
        $thisMonthToRecognize = (float) RevenueRecognitionLine::where('status', 'pending')
            ->whereHas('schedule', fn($q) => $q->where('tenant_id', $tenantId))
            ->whereDate('recognition_date', '>=', $thisMonthStart)
            ->whereDate('recognition_date', '<=', $thisMonthEnd)
            ->sum('amount');

        return [
            'total_amount' => $totalAmount + $completedAmount,
            'recognized_amount' => $recognized + $completedAmount,
            'deferred_amount' => $deferred,
            'completion_rate' => ($totalAmount + $completedAmount) > 0
                ? round((($recognized + $completedAmount) / ($totalAmount + $completedAmount)) * 100, 1)
                : 0,
            'active_schedules' => $activeCount,
            'completed_schedules' => $completedCount,
            'pending_schedules' => $pendingCount,
            'this_month_to_recognize' => $thisMonthToRecognize,
        ];
    }

    // ═══════════════════════════════════════════
    // 月度快照
    // ═══════════════════════════════════════════

    /**
     * 生成月度收入快照
     */
    public function generateMonthlySnapshot(int $tenantId, ?string $yearMonth = null): MonthlyRevenueSnapshot
    {
        $yearMonth = $yearMonth ?? now()->format('Y-m');
        [$year, $month] = explode('-', $yearMonth);
        $monthStart = Carbon::create((int)$year, (int)$month, 1)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        // 当月发票收入
        $invoicedRevenue = (float) Invoice::where('tenant_id', $tenantId)
            ->where('status', 'paid')
            ->whereDate('paid_at', '>=', $monthStart)
            ->whereDate('paid_at', '<=', $monthEnd)
            ->sum('amount');

        // 当月退款
        $refunds = (float) Invoice::where('tenant_id', $tenantId)
            ->where('status', 'refunded')
            ->whereDate('refunded_at', '>=', $monthStart)
            ->whereDate('refunded_at', '<=', $monthEnd)
            ->sum('amount');

        // 当月已确认收入
        $recognizedRevenue = $this->getRecognizedRevenue($monthStart, $monthEnd, $tenantId);

        // 递延收入余额
        $deferredRevenue = $this->getDeferredRevenue($monthEnd, $tenantId);

        // 活跃订阅数
        $activeSubscriptions = Subscription::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->count();

        // ARR 变动（对比上月，含 expansion/contraction 分解）
        $prevMonthEnd = $monthStart->copy()->subDay();
        $currentArr = $this->calculateARR($tenantId, $monthEnd);
        $prevArr = $this->calculateARR($tenantId, $prevMonthEnd);

        // 分解ARR变动: 通过当月新增订阅 vs 取消订阅估算
        $newSubsArr = (float) Subscription::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereDate('starts_at', '>=', $monthStart)
            ->whereDate('starts_at', '<=', $monthEnd)
            ->get()
            ->sum(fn($s) => match ($s->billing_period) {
                'yearly' => (float) $s->price,
                'semi_annually' => (float) $s->price * 2,
                'quarterly' => (float) $s->price * 4,
                default => (float) $s->price * 12,
            });

        $churnedSubsArr = (float) Subscription::where('tenant_id', $tenantId)
            ->whereIn('status', ['cancelled', 'expired'])
            ->where(function ($q) use ($monthStart, $monthEnd) {
                $q->whereDate('ends_at', '>=', $monthStart)
                  ->whereDate('ends_at', '<=', $monthEnd);
            })
            ->get()
            ->sum(fn($s) => match ($s->billing_period) {
                'yearly' => (float) $s->price,
                'semi_annually' => (float) $s->price * 2,
                'quarterly' => (float) $s->price * 4,
                default => (float) $s->price * 12,
            });

        // expansion: 当月升级订阅的增量
        $expansionArr = (float) Subscription::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereDate('updated_at', '>=', $monthStart)
            ->whereDate('updated_at', '<=', $monthEnd)
            ->whereColumn('price', '>', DB::raw('(SELECT price FROM subscriptions AS s2 WHERE s2.id = subscriptions.id AND s2.updated_at < subscriptions.updated_at ORDER BY s2.updated_at DESC LIMIT 1)'))
            ->get()
            ->sum(fn($s) => match ($s->billing_period) {
                'yearly' => (float) $s->price,
                'semi_annually' => (float) $s->price * 2,
                'quarterly' => (float) $s->price * 4,
                default => (float) $s->price * 12,
            });

        // contraction: 当月降级订阅的减少
        $contractionArr = 0; // 简化为 0，需要对比升级前价格差异

        $netNewArr = max(0, $newSubsArr);
        $churnedArr = max(0, $churnedSubsArr);

        $snapshot = MonthlyRevenueSnapshot::updateOrCreate(
            ['tenant_id' => $tenantId, 'year_month' => $yearMonth],
            [
                'invoiced_revenue' => $invoicedRevenue,
                'recognized_revenue' => $recognizedRevenue,
                'deferred_revenue' => $deferredRevenue,
                'refunds' => $refunds,
                'net_new_arr' => $netNewArr,
                'expansion_arr' => 0,
                'contraction_arr' => 0,
                'churned_arr' => $churnedArr,
                'active_subscriptions' => $activeSubscriptions,
                'breakdown' => [
                    'invoiced_count' => Invoice::where('tenant_id', $tenantId)
                        ->where('status', 'paid')
                        ->whereDate('paid_at', '>=', $monthStart)
                        ->whereDate('paid_at', '<=', $monthEnd)
                        ->count(),
                    'refunded_count' => Invoice::where('tenant_id', $tenantId)
                        ->where('status', 'refunded')
                        ->whereDate('refunded_at', '>=', $monthStart)
                        ->whereDate('refunded_at', '<=', $monthEnd)
                        ->count(),
                ],
            ]
        );

        Log::info('RevenueRecognition: monthly snapshot generated', [
            'tenant_id' => $tenantId,
            'year_month' => $yearMonth,
            'invoiced' => $invoicedRevenue,
            'recognized' => $recognizedRevenue,
            'deferred' => $deferredRevenue,
        ]);

        return $snapshot;
    }

    /**
     * 获取指定月份的 ARR
     */
    protected function calculateARR(int $tenantId, Carbon $asOfDate): float
    {
        $subscriptions = Subscription::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where('starts_at', '<=', $asOfDate)
            ->get();

        $arr = 0;
        foreach ($subscriptions as $sub) {
            $monthlyPrice = match ($sub->billing_period) {
                'yearly' => (float) $sub->price / 12,
                'semi_annually' => (float) $sub->price / 6,
                'quarterly' => (float) $sub->price / 3,
                default => (float) $sub->price, // monthly
            };
            $arr += $monthlyPrice * 12;
        }

        return round($arr, 2);
    }

    // ═══════════════════════════════════════════
    // ASC 606 财务报告导出
    // ═══════════════════════════════════════════

    /**
     * 生成 ASC 606 收入确认报告（增强版，含按产品拆分）
     */
    public function generateASC606Report(int $tenantId, string $year, string $month): array
    {
        $monthStart = Carbon::createFromFormat('Y-m', "{$year}-{$month}")->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        // 当月确认的排程明细
        $recognizedLines = RevenueRecognitionLine::where('status', 'recognized')
            ->whereHas('schedule', fn($q) => $q->where('tenant_id', $tenantId))
            ->whereDate('recognition_date', '>=', $monthStart)
            ->whereDate('recognition_date', '<=', $monthEnd)
            ->with('schedule.invoice', 'schedule.subscription')
            ->get();

        // 当月新创建的排程
        $newSchedules = RevenueRecognitionSchedule::forTenant($tenantId)
            ->whereDate('created_at', '>=', $monthStart)
            ->whereDate('created_at', '<=', $monthEnd)
            ->get();

        // 期末递延余额
        $periodEndDeferred = $this->getDeferredRevenue($monthEnd, $tenantId);

        // 期初递延余额
        $periodStartDeferred = $this->getDeferredRevenue(
            $monthStart->copy()->subDay(),
            $tenantId
        );

        // 已开发票总额
        $totalInvoiced = (float) Invoice::where('tenant_id', $tenantId)
            ->where('status', 'paid')
            ->whereDate('paid_at', '>=', $monthStart)
            ->whereDate('paid_at', '<=', $monthEnd)
            ->sum('amount');

        // 按产品拆分收入
        $productBreakdown = [];
        if (config('revenue-recognition.report.include_breakdown_by_product', false)) {
            $grouped = $recognizedLines->groupBy(fn($l) => $l->schedule?->subscription?->plan ?? 'unknown');
            foreach ($grouped as $plan => $lines) {
                $productBreakdown[] = [
                    'product' => $plan,
                    'recognized_amount' => round($lines->sum('amount'), 2),
                    'transaction_count' => $lines->count(),
                ];
            }
        }

        return [
            'report_period' => "{$year}-{$month}",
            'period_start' => $monthStart->toDateString(),
            'period_end' => $monthEnd->toDateString(),
            'currency' => config('revenue-recognition.report.currency', 'CNY'),
            'recognition_method' => config('revenue-recognition.recognition_method', 'straight_line'),
            'opening_deferred_revenue' => $periodStartDeferred,
            'closing_deferred_revenue' => $periodEndDeferred,
            'total_invoiced' => $totalInvoiced,
            'recognized_revenue' => $recognizedLines->sum('amount'),
            'change_in_deferred' => round($periodEndDeferred - $periodStartDeferred, 2),
            'new_schedules_count' => $newSchedules->count(),
            'new_schedules_value' => $newSchedules->sum('total_amount'),
            'product_breakdown' => $productBreakdown,
            'recognized_transactions' => $recognizedLines->map(function ($line) {
                return [
                    'schedule_id' => $line->schedule_id,
                    'period' => $line->period_number,
                    'amount' => (float) $line->amount,
                    'recognition_date' => $line->recognition_date,
                    'invoice_no' => $line->schedule?->invoice?->invoice_no,
                    'subscription_id' => $line->schedule?->subscription_id,
                    'product' => $line->schedule?->subscription?->plan ?? 'unknown',
                ];
            }),
            'monthly_snapshot' => MonthlyRevenueSnapshot::where('tenant_id', $tenantId)
                ->where('year_month', "{$year}-{$month}")
                ->first(),
        ];
    }

    // ═══════════════════════════════════════════
    // 排程取消/退款处理
    // ═══════════════════════════════════════════

    /**
     * 取消排程（退款/取消订阅时调用）
     */
    public function cancelSchedule(int $scheduleId, string $reason = 'refund'): RevenueRecognitionSchedule
    {
        $schedule = RevenueRecognitionSchedule::findOrFail($scheduleId);

        if ($schedule->status === 'cancelled') {
            throw new \RuntimeException('排程已被取消');
        }

        DB::transaction(function () use ($schedule, $reason) {
            // 标记所有待确认行为 skipped
            RevenueRecognitionLine::where('schedule_id', $schedule->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'skipped',
                    'description' => $reason,
                ]);

            // 更新排程状态
            $schedule->update([
                'status' => 'cancelled',
                'deferred_amount' => 0,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
                'metadata' => array_merge($schedule->metadata ?? [], [
                    'cancelled_at' => now()->toIso8601String(),
                    'cancel_reason' => $reason,
                ]),
            ]);
        });

        Log::info('RevenueRecognition: schedule cancelled', [
            'schedule_id' => $schedule->id,
            'reason' => $reason,
            'tenant_id' => $schedule->tenant_id,
        ]);

        return $schedule->fresh();
    }

    /**
     * 重算排程（修复误差后重新分摊）
     */
    public function recomputeSchedule(int $scheduleId): RevenueRecognitionSchedule
    {
        $schedule = RevenueRecognitionSchedule::with('lines')->findOrFail($scheduleId);

        if ($schedule->status === 'cancelled') {
            throw new \RuntimeException('已取消的排程无法重算');
        }

        DB::transaction(function () use ($schedule) {
            $totalPeriods = $schedule->total_periods;
            $totalAmount = (float) $schedule->total_amount;
            $alreadyRecognized = (float) RevenueRecognitionLine::where('schedule_id', $schedule->id)
                ->where('status', 'recognized')
                ->sum('amount');

            $remainingPeriods = RevenueRecognitionLine::where('schedule_id', $schedule->id)
                ->where('status', 'pending')
                ->count();

            if ($remainingPeriods <= 0) {
                throw new \RuntimeException('没有待确认行需要重算');
            }

            $remainingAmount = $totalAmount - $alreadyRecognized;
            $perPeriod = round($remainingAmount / $remainingPeriods, 2);
            $remainder = round($remainingAmount - ($perPeriod * $remainingPeriods), 2);

            $pendingLines = RevenueRecognitionLine::where('schedule_id', $schedule->id)
                ->where('status', 'pending')
                ->orderBy('period_number')
                ->get();

            foreach ($pendingLines as $i => $line) {
                $amount = $perPeriod;
                if ($i === $pendingLines->count() - 1) {
                    $amount += $remainder;
                }
                $line->update(['amount' => round($amount, 2)]);
            }

            $schedule->update([
                'deferred_amount' => $remainingAmount,
                'recognized_amount' => $alreadyRecognized,
            ]);
        });

        Log::info('RevenueRecognition: schedule recomputed', [
            'schedule_id' => $schedule->id,
        ]);

        return $schedule->fresh()->load('lines');
    }

    // ═══════════════════════════════════════════
    // ASC 606 报告 CSV 导出
    // ═══════════════════════════════════════════

    /**
     * 导出 ASC 606 报告为 CSV 格式
     */
    public function exportASC606Csv(int $tenantId, string $year, string $month): string
    {
        $report = $this->generateASC606Report($tenantId, $year, $month);

        $lines = [];
        // 标题行
        $lines[] = [
            'period' => $report['report_period'],
            'type' => 'SUMMARY',
            'description' => '期初递延收入',
            'amount' => $report['opening_deferred_revenue'],
            'currency' => $report['currency'],
        ];
        $lines[] = [
            'period' => $report['report_period'],
            'type' => 'SUMMARY',
            'description' => '当月开票总额',
            'amount' => $report['total_invoiced'],
            'currency' => $report['currency'],
        ];
        $lines[] = [
            'period' => $report['report_period'],
            'type' => 'SUMMARY',
            'description' => '当月已确认收入',
            'amount' => $report['recognized_revenue'],
            'currency' => $report['currency'],
        ];
        $lines[] = [
            'period' => $report['report_period'],
            'type' => 'SUMMARY',
            'description' => '期末递延收入',
            'amount' => $report['closing_deferred_revenue'],
            'currency' => $report['currency'],
        ];

        // 明细行
        foreach ($report['recognized_transactions'] as $tx) {
            $lines[] = [
                'period' => $report['report_period'],
                'type' => 'TRANSACTION',
                'description' => "排程#{$tx['schedule_id']} 第{$tx['period']}期",
                'amount' => $tx['amount'],
                'currency' => $report['currency'],
            ];
        }

        // 生成 CSV
        $csv = fopen('php://temp', 'r+');
        fputcsv($csv, ['期间', '类型', '描述', '金额', '币种']);
        foreach ($lines as $line) {
            fputcsv($csv, [$line['period'], $line['type'], $line['description'], $line['amount'], $line['currency']]);
        }
        rewind($csv);
        $content = stream_get_contents($csv);
        fclose($csv);

        return $content;
    }

    // ═══════════════════════════════════════════
    // 批处理
    // ═══════════════════════════════════════════

    /**
     * 批量创建排程（用于需要追认已付发票的场景）
     */
    public function createSchedulesForExistingInvoices(int $tenantId, ?array $invoiceIds = null): array
    {
        $query = Invoice::where('tenant_id', $tenantId)
            ->where('status', 'paid')
            ->whereNotNull('paid_at')
            ->whereDoesntHave('revenueSchedule');

        if ($invoiceIds) {
            $query->whereIn('id', $invoiceIds);
        }

        $invoices = $query->get();
        $created = 0;

        foreach ($invoices as $invoice) {
            try {
                $this->createSchedule($invoice);
                $created++;
            } catch (\Exception $e) {
                Log::warning('RevenueRecognition: failed to create schedule for invoice', [
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'total_candidates' => $invoices->count(),
            'created' => $created,
        ];
    }

    /**
     * 生成指定范围月份的月度快照
     */
    public function generateMonthlySnapshots(int $tenantId, string $startMonth, string $endMonth): array
    {
        $start = Carbon::parse($startMonth . '-01');
        $end = Carbon::parse($endMonth . '-01');
        $generated = [];

        $current = $start->copy();
        while ($current->lessThanOrEqualTo($end)) {
            $snapshot = $this->generateMonthlySnapshot($tenantId, $current->format('Y-m'));
            $generated[] = $snapshot;
            $current->addMonth();
        }

        return $generated;
    }

    // ═══════════════════════════════════════════
    // 辅助方法
    // ═══════════════════════════════════════════

    /**
     * 根据计费周期获取分期月数
     */
    public function getPeriodCount(string $billingPeriod): int
    {
        return match ($billingPeriod) {
            'yearly' => 12,
            'semi_annually' => 6,
            'quarterly' => 3,
            default => 1, // monthly or unknown
        };
    }
}
