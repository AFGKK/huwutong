<?php

namespace App\Services;

use App\Models\ConversionFunnelEvent;
use App\Models\ConversionFunnelSummary;
use App\Models\License;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Trial→付费转化漏斗服务 (M2-101)
 */
class ConversionFunnelService
{
    /**
     * 记录转化事件
     */
    public function trackEvent(int $tenantId, string $stage, string $event, ?int $customerId = null, ?int $licenseId = null, array $metadata = []): ConversionFunnelEvent
    {
        return ConversionFunnelEvent::create([
            'tenant_id' => $tenantId,
            'customer_id' => $customerId,
            'license_id' => $licenseId,
            'stage' => $stage,
            'event' => $event,
            'metadata' => $metadata,
            'source' => $metadata['source'] ?? null,
            'campaign' => $metadata['campaign'] ?? null,
            'occurred_at' => now(),
        ]);
    }

    /**
     * 获取漏斗数据
     */
    public function getFunnelData(int $tenantId, array $filters = []): array
    {
        $startDate = $filters['start_date'] ?? now()->subDays(30)->toDateString();
        $endDate = $filters['end_date'] ?? now()->toDateString();

        $stages = config('conversion-funnel.funnel.stages', []);

        $data = [];
        $previousCount = null;

        foreach ($stages as $key => $config) {
            $count = ConversionFunnelEvent::where('tenant_id', $tenantId)
                ->where('stage', $key)
                ->whereDate('occurred_at', '>=', $startDate)
                ->whereDate('occurred_at', '<=', $endDate)
                ->count();

            $dropOff = $previousCount !== null ? $previousCount - $count : 0;
            $dropRate = $previousCount && $previousCount > 0
                ? round(($dropOff / $previousCount) * 100, 1)
                : 0;

            $data[] = [
                'stage' => $key,
                'label' => $config['label'],
                'order' => $config['order'],
                'count' => $count,
                'drop_off' => $dropOff,
                'drop_rate' => $dropRate,
                'conversion_from_first' => $previousCount !== null
                    ? round(($count / $data[0]['count']) * 100, 1)
                    : 100,
            ];

            $previousCount = $count;
        }

        // 整体转化率
        $first = $data[0]['count'] ?? 0;
        $last = !empty($data) ? end($data)['count'] : 0;
        $overallRate = $first > 0 ? round(($last / $first) * 100, 1) : 0;

        return [
            'stages' => $data,
            'overall_rate' => $overallRate,
            'total_started' => $first,
            'total_converted' => $last,
            'period' => ['start' => $startDate, 'end' => $endDate],
        ];
    }

    /**
     * 获取按来源/渠道拆分
     */
    public function getBySource(int $tenantId, string $startDate, string $endDate): array
    {
        $rows = ConversionFunnelEvent::where('tenant_id', $tenantId)
            ->whereNotNull('source')
            ->whereDate('occurred_at', '>=', $startDate)
            ->whereDate('occurred_at', '<=', $endDate)
            ->selectRaw('source, stage, COUNT(*) as count')
            ->groupBy('source', 'stage')
            ->get();

        $grouped = [];
        foreach ($rows as $row) {
            $source = $row->source ?: 'direct';
            if (!isset($grouped[$source])) {
                $grouped[$source] = ['source' => $source, 'total' => 0, 'converted' => 0, 'rate' => 0];
            }
            $grouped[$source]['total'] += $row->count;
            if ($row->stage === 'converted') {
                $grouped[$source]['converted'] += $row->count;
            }
        }

        foreach ($grouped as &$g) {
            $g['rate'] = $g['total'] > 0 ? round(($g['converted'] / $g['total']) * 100, 1) : 0;
        }

        return array_values($grouped);
    }

    /**
     * 获取趋势数据
     */
    public function getTrend(int $tenantId, int $days = 30): array
    {
        $results = ConversionFunnelSummary::where('tenant_id', $tenantId)
            ->where('date', '>=', now()->subDays($days))
            ->orderBy('date')
            ->get();

        return $results->toArray();
    }

    /**
     * 获取仪表盘
     */
    public function getDashboard(int $tenantId): array
    {
        $funnel = $this->getFunnelData($tenantId);

        // 今日注册
        $todayRegistered = ConversionFunnelEvent::where('tenant_id', $tenantId)
            ->where('stage', 'trial_registered')
            ->whereDate('occurred_at', today())
            ->count();

        // 7天转化率
        $weekly = $this->getFunnelData($tenantId, [
            'start_date' => now()->subDays(7)->toDateString(),
            'end_date' => now()->toDateString(),
        ]);

        // 流失最多的阶段
        $worstStage = collect($funnel['stages'])->sortByDesc('drop_rate')->first();

        return [
            'funnel' => $funnel,
            'weekly_rate' => $weekly['overall_rate'],
            'today_registered' => $todayRegistered,
            'worst_stage' => $worstStage ? [
                'stage' => $worstStage['stage'],
                'label' => $worstStage['label'],
                'drop_rate' => $worstStage['drop_rate'],
            ] : null,
        ];
    }

    /**
     * 每日汇总（定时任务调用）
     */
    public function generateDailySummary(int $tenantId): ConversionFunnelSummary
    {
        $date = today();
        $stages = config('conversion-funnel.funnel.stages', []);

        $counts = [];
        foreach ($stages as $key => $config) {
            $counts[$key] = ConversionFunnelEvent::where('tenant_id', $tenantId)
                ->where('stage', $key)
                ->whereDate('occurred_at', $date)
                ->count();
        }

        $registered = $counts['trial_registered'] ?? 0;
        $converted = $counts['converted'] ?? 0;
        $rate = $registered > 0 ? round(($converted / $registered) * 100, 2) : 0;

        // 按来源统计
        $bySource = ConversionFunnelEvent::where('tenant_id', $tenantId)
            ->whereDate('occurred_at', $date)
            ->whereNotNull('source')
            ->selectRaw('source, COUNT(*) as count')
            ->groupBy('source')
            ->pluck('count', 'source')
            ->toArray();

        return ConversionFunnelSummary::updateOrCreate(
            ['tenant_id' => $tenantId, 'date' => $date],
            array_merge($counts, [
                'conversion_rate' => $rate,
                'by_source' => $bySource,
            ])
        );
    }
}
