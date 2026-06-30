<?php

namespace App\Services;

use App\Models\UsageAggregate;
use App\Models\UsageRecord;
use App\Models\Tenant;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * M2-132 API 用量分端点统计服务
 *
 * 基于现有 UsageMeterService 体系，提供按 API 端点拆分的用量统计：
 * - 按端点(activate/validate/revoke/check)拆分调用量
 * - 各端点延迟 P50/P99
 * - 错误率
 * - 用量趋势图
 * - 超额预警
 */
class EndpointUsageAnalyticsService
{
    /**
     * API 端点指标键
     */
    const ENDPOINT_METRICS = [
        'api_call.activate' => [
            'name' => 'License 激活',
            'method' => 'POST',
            'path' => '/api/activate',
            'color' => '#409eff',
        ],
        'api_call.validate' => [
            'name' => 'License 验证',
            'method' => 'POST',
            'path' => '/api/validate',
            'color' => '#67c23a',
        ],
        'api_call.revoke' => [
            'name' => 'License 吊销',
            'method' => 'POST',
            'path' => '/api/revoke',
            'color' => '#e6a23c',
        ],
        'api_call.check' => [
            'name' => 'Feature 检查',
            'method' => 'GET',
            'path' => '/api/check',
            'color' => '#f56c6c',
        ],
    ];

    /**
     * 获取各端点用量概览
     *
     * @return array [endpoint => {total, today, this_month, quota_limit, quota_used}]
     */
    public function getEndpointOverview(Tenant $tenant, ?Customer $customer = null): array
    {
        $overview = [];
        $now = now();

        foreach (self::ENDPOINT_METRICS as $metricKey => $info) {
            $query = UsageAggregate::where('tenant_id', $tenant->id)
                ->where('metric_key', $metricKey);

            if ($customer) {
                $query->where('customer_id', $customer->id);
            }

            // 当前月份聚合
            $currentPeriod = $query->clone()
                ->where('period', 'monthly')
                ->where('period_start', '<=', $now->endOfMonth()->toDateString())
                ->where('period_end', '>=', $now->startOfMonth()->toDateString())
                ->first();

            // 全部历史
            $totalQuantity = (int) $query->clone()->sum('total_quantity');

            // 今天用量 - 从 usage_records 直接查
            $todayQuantity = (int) UsageRecord::where('tenant_id', $tenant->id)
                ->where('metric_key', $metricKey)
                ->when($customer, fn($q) => $q->where('customer_id', $customer->id))
                ->where('recorded_at', '>=', $now->copy()->startOfDay()->format('Y-m-d H:i:s'))
                ->sum('quantity');

            // 上月总量
            $lastMonth = (clone $query)
                ->where('period', 'monthly')
                ->where('period_start', '<=', $now->copy()->subMonth()->endOfMonth()->toDateString())
                ->where('period_end', '>=', $now->copy()->subMonth()->startOfMonth()->toDateString())
                ->first();

            $overview[$metricKey] = [
                'metric_key' => $metricKey,
                'name' => $info['name'],
                'method' => $info['method'],
                'path' => $info['path'],
                'color' => $info['color'],
                'total_quantity' => $totalQuantity,
                'today_quantity' => $todayQuantity,
                'this_month_quantity' => $currentPeriod ? (int) $currentPeriod->total_quantity : 0,
                'last_month_quantity' => $lastMonth ? (int) $lastMonth->total_quantity : 0,
                'monthly_change_percent' => $this->calcChangePercent(
                    $lastMonth?->total_quantity ?? 0,
                    $currentPeriod?->total_quantity ?? 0
                ),
            ];
        }

        return $overview;
    }

    /**
     * 获取用量趋势（按天）
     *
     * @param string $days 天数（默认 30）
     * @return array
     */
    public function getUsageTrend(Tenant $tenant, ?Customer $customer = null, int $days = 30): array
    {
        $startDate = now()->subDays($days)->startOfDay();
        $endDate = now()->endOfDay();

        $records = UsageRecord::where('tenant_id', $tenant->id)
            ->whereIn('metric_key', array_keys(self::ENDPOINT_METRICS))
            ->when($customer, fn($q) => $q->where('customer_id', $customer->id))
            ->where('recorded_at', '>=', $startDate)
            ->where('recorded_at', '<=', $endDate)
            ->selectRaw("DATE(recorded_at) as date, metric_key, SUM(quantity) as total")
            ->groupBy('date', 'metric_key')
            ->orderBy('date')
            ->get();

        // 按日期+端点组织
        $trend = [];
        $dateMap = [];

        foreach ($records as $record) {
            $date = $record->date;
            if (! isset($dateMap[$date])) {
                $dateMap[$date] = ['date' => $date];
                foreach (self::ENDPOINT_METRICS as $key => $info) {
                    $dateMap[$date][$key] = 0;
                }
            }
            $dateMap[$date][$record->metric_key] = (int) $record->total;
        }

        // 填充缺失日期
        $current = Carbon::parse($startDate);
        while ($current <= $endDate) {
            $dateStr = $current->toDateString();
            if (! isset($dateMap[$dateStr])) {
                $entry = ['date' => $dateStr];
                foreach (self::ENDPOINT_METRICS as $key => $info) {
                    $entry[$key] = 0;
                }
                $trend[] = $entry;
            } else {
                $trend[] = $dateMap[$dateStr];
            }
            $current->addDay();
        }

        return $trend;
    }

    /**
     * 获取各端点延迟统计
     *
     * 从 UsageRecord.context JSON 中提取 latency_ms。
     *
     * @return array [metric_key => {p50, p90, p99, avg, max, sample_count}]
     */
    public function getLatencyStats(Tenant $tenant, ?Customer $customer = null, int $days = 7): array
    {
        $startDate = now()->subDays($days)->startOfDay();

        $query = UsageRecord::where('tenant_id', $tenant->id)
            ->whereIn('metric_key', array_keys(self::ENDPOINT_METRICS))
            ->where('recorded_at', '>=', $startDate);

        if ($customer) {
            $query->where('customer_id', $customer->id);
        }

        $records = $query->whereNotNull('context')
            ->select(['metric_key', 'context'])
            ->get();

        // 按端点收集延迟数据
        $latencyData = [];
        foreach (self::ENDPOINT_METRICS as $key => $info) {
            $latencyData[$key] = [];
        }

        foreach ($records as $record) {
            $ctx = $record->context;
            if (isset($ctx['latency_ms'])) {
                $metricKey = $record->metric_key;
                if (isset($latencyData[$metricKey])) {
                    $latencyData[$metricKey][] = (float) $ctx['latency_ms'];
                }
            }
        }

        $stats = [];
        foreach ($latencyData as $metricKey => $values) {
            if (empty($values)) {
                $stats[$metricKey] = [
                    'p50' => 0,
                    'p90' => 0,
                    'p99' => 0,
                    'avg' => 0,
                    'max' => 0,
                    'sample_count' => 0,
                ];
                continue;
            }

            sort($values);
            $count = count($values);

            $stats[$metricKey] = [
                'p50' => $this->percentile($values, 50),
                'p90' => $this->percentile($values, 90),
                'p99' => $this->percentile($values, 99),
                'avg' => round(array_sum($values) / $count, 2),
                'max' => round(max($values), 2),
                'sample_count' => $count,
            ];
        }

        return $stats;
    }

    /**
     * 获取各端点错误率统计
     *
     * 从 UsageRecord.context JSON 中提取 is_error / error_code。
     */
    public function getErrorStats(Tenant $tenant, ?Customer $customer = null, int $days = 7): array
    {
        $startDate = now()->subDays($days)->startOfDay();

        $query = UsageRecord::where('tenant_id', $tenant->id)
            ->whereIn('metric_key', array_keys(self::ENDPOINT_METRICS))
            ->where('recorded_at', '>=', $startDate);

        if ($customer) {
            $query->where('customer_id', $customer->id);
        }

        $records = $query->select(['metric_key', 'context'])->get();

        // 按端点统计错误
        $errorData = [];
        $totalData = [];

        foreach (self::ENDPOINT_METRICS as $key => $info) {
            $errorData[$key] = 0;
            $totalData[$key] = 0;
        }

        foreach ($records as $record) {
            $metricKey = $record->metric_key;
            if (! isset($totalData[$metricKey])) continue;

            $totalData[$metricKey]++;
            $ctx = $record->context;
            if ($ctx && ! empty($ctx['is_error'])) {
                $errorData[$metricKey]++;
            }
        }

        $stats = [];
        foreach (self::ENDPOINT_METRICS as $metricKey => $info) {
            $total = $totalData[$metricKey];
            $errors = $errorData[$metricKey];

            $stats[$metricKey] = [
                'total_requests' => $total,
                'error_count' => $errors,
                'error_rate' => $total > 0 ? round(($errors / $total) * 100, 2) : 0,
                'success_rate' => $total > 0 ? round((($total - $errors) / $total) * 100, 2) : 100,
            ];
        }

        return $stats;
    }

    /**
     * 获取超额预警
     *
     * 对比本月用量与上月，检测异常增长。
     */
    public function getAlertData(Tenant $tenant, ?Customer $customer = null): array
    {
        $alerts = [];
        $now = now();

        foreach (self::ENDPOINT_METRICS as $metricKey => $info) {
            $query = UsageAggregate::where('tenant_id', $tenant->id)
                ->where('metric_key', $metricKey);

            if ($customer) {
                $query->where('customer_id', $customer->id);
            }

            // 本月
            $currentMonth = (clone $query)
                ->where('period', 'monthly')
                ->where('period_start', '<=', $now->endOfMonth()->toDateString())
                ->where('period_end', '>=', $now->startOfMonth()->toDateString())
                ->first();

            // 上月
            $lastMonth = (clone $query)
                ->where('period', 'monthly')
                ->where('period_start', '<=', $now->copy()->subMonth()->endOfMonth()->toDateString())
                ->where('period_end', '>=', $now->copy()->subMonth()->startOfMonth()->toDateString())
                ->first();

            $currentTotal = $currentMonth ? (int) $currentMonth->total_quantity : 0;
            $lastTotal = $lastMonth ? (int) $lastMonth->total_quantity : 0;

            $alert = [
                'metric_key' => $metricKey,
                'name' => $info['name'],
                'this_month' => $currentTotal,
                'last_month' => $lastTotal,
                'change_percent' => 0,
                'level' => 'normal',
            ];

            if ($lastTotal > 0) {
                $change = round((($currentTotal - $lastTotal) / $lastTotal) * 100, 1);
                $alert['change_percent'] = $change;

                if ($change > 200) {
                    $alert['level'] = 'critical';
                    $alert['message'] = "{$info['name']} 本月调用量激增 {$change}%，需关注";
                } elseif ($change > 100) {
                    $alert['level'] = 'warning';
                    $alert['message'] = "{$info['name']} 本月调用量增长 {$change}%，建议关注趋势";
                } elseif ($change > 50) {
                    $alert['level'] = 'info';
                    $alert['message'] = "{$info['name']} 本月调用量增长 {$change}%";
                }
            }

            $alerts[] = $alert;
        }

        return $alerts;
    }

    /**
     * 获取分端点的错误详情（按错误码）
     */
    public function getErrorDetail(Tenant $tenant, ?Customer $customer = null, int $days = 7): array
    {
        $startDate = now()->subDays($days)->startOfDay();

        $query = UsageRecord::where('tenant_id', $tenant->id)
            ->whereIn('metric_key', array_keys(self::ENDPOINT_METRICS))
            ->where('recorded_at', '>=', $startDate);

        if ($customer) {
            $query->where('customer_id', $customer->id);
        }

        $records = $query->select(['metric_key', 'context'])->get();

        $errorBreakdown = [];
        foreach (self::ENDPOINT_METRICS as $key => $info) {
            $errorBreakdown[$key] = [];
        }

        foreach ($records as $record) {
            $ctx = $record->context;
            if ($ctx && ! empty($ctx['is_error']) && ! empty($ctx['error_code'])) {
                $metricKey = $record->metric_key;
                if (! isset($errorBreakdown[$metricKey][$ctx['error_code']])) {
                    $errorBreakdown[$metricKey][$ctx['error_code']] = [
                        'error_code' => $ctx['error_code'],
                        'error_message' => $ctx['error_message'] ?? '未知错误',
                        'count' => 0,
                    ];
                }
                $errorBreakdown[$metricKey][$ctx['error_code']]['count']++;
            }
        }

        // 格式化为数组
        $result = [];
        foreach ($errorBreakdown as $metricKey => $errors) {
            $result[$metricKey] = array_values($errors);
        }

        return $result;
    }

    /**
     * 计算变化百分比
     */
    protected function calcChangePercent(int $oldValue, int $newValue): float
    {
        if ($oldValue === 0) {
            return $newValue > 0 ? 100 : 0;
        }
        return round((($newValue - $oldValue) / $oldValue) * 100, 1);
    }

    /**
     * 计算百分位数
     */
    protected function percentile(array $sortedValues, int $percentile): float
    {
        $count = count($sortedValues);
        if ($count === 0) return 0;

        $index = (int) ceil(($percentile / 100) * $count) - 1;
        $index = max(0, min($index, $count - 1));

        return round($sortedValues[$index], 2);
    }
}
