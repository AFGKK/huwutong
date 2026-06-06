<?php

namespace App\Services;

use App\Models\License;
use App\Models\UsageAggregate;
use App\Models\UsageQuota;
use App\Models\UsageRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 用量计量系统 (M2-10)
 *
 * 支持三种计量模式：
 * - 按次计数 (counter) — API 调用次数、设备验证次数等
 * - 按量计量 (volume) — 传输字节数、存储用量等
 * - 按时间窗计量 (window) — 同时在线数、月度活跃设备数等
 *
 * 配套配额管理：配额检查、超额告警、自动拦截
 * 配套聚合查询：按产品/客户/License/时间维度聚合统计
 */
class UsageMeterService
{
    const CACHE_PREFIX = 'usage_meter:';
    const CACHE_TTL = 60; // 秒

    /**
     * 预定义计量指标
     */
    const METRICS = [
        'api_call.activate'        => ['name' => 'License 激活', 'unit' => 'count', 'window' => 'monthly'],
        'api_call.validate'        => ['name' => 'License 验证', 'unit' => 'count', 'window' => 'monthly'],
        'api_call.revoke'          => ['name' => 'License 吊销', 'unit' => 'count', 'window' => 'monthly'],
        'api_call.check'           => ['name' => 'Feature 检查', 'unit' => 'count', 'window' => 'monthly'],
        'device.active'            => ['name' => '活跃设备数', 'unit' => 'count', 'window' => 'daily'],
        'device.register'          => ['name' => '设备注册', 'unit' => 'count', 'window' => 'monthly'],
        'storage.used_bytes'       => ['name' => '存储用量', 'unit' => 'bytes', 'window' => 'total'],
        'ai.tokens_used'           => ['name' => 'AI Token 消耗', 'unit' => 'tokens', 'window' => 'monthly'],
        'ai.api_calls'             => ['name' => 'AI API 调用', 'unit' => 'count', 'window' => 'monthly'],
        'webhook.delivered'        => ['name' => 'Webhook 投递', 'unit' => 'count', 'window' => 'monthly'],
        'email.sent'               => ['name' => '邮件发送', 'unit' => 'count', 'window' => 'monthly'],
    ];

    /**
     * 记录一次用量
     *
     * @param array $params [
     *   'tenant_id'   => int,
     *   'license_id'  => ?int,
     *   'customer_id' => ?int,
     *   'metric_key'  => string,  // 取自 self::METRICS 键名
     *   'action'      => string,  // 具体操作名
     *   'quantity'    => int,     // 默认 1
     *   'unit'        => string,  // 默认 'count'
     *   'context'     => ?array,  // 额外上下文
     *   'recorded_at' => ?Carbon, // 实际发生时间，默认 now()
     * ]
     * @return UsageRecord
     */
    public function record(array $params): UsageRecord
    {
        $tenantId = $params['tenant_id'];
        $metricKey = $params['metric_key'];
        $recordedAt = $params['recorded_at'] ?? now();
        $quantity = $params['quantity'] ?? 1;

        $record = UsageRecord::create([
            'tenant_id'   => $tenantId,
            'license_id'  => $params['license_id'] ?? null,
            'customer_id' => $params['customer_id'] ?? null,
            'metric_key'  => $metricKey,
            'action'      => $params['action'],
            'window_type' => self::METRICS[$metricKey]['window'] ?? 'total',
            'quantity'    => $quantity,
            'unit'        => $params['unit'] ?? (self::METRICS[$metricKey]['unit'] ?? 'count'),
            'context'     => $params['context'] ?? null,
            'recorded_at' => $recordedAt,
        ]);

        // 清除相关聚合缓存
        $this->clearAggregateCache($tenantId, $metricKey);

        Log::debug('UsageMeter: recorded', [
            'metric_key' => $metricKey,
            'tenant_id'  => $tenantId,
            'quantity'   => $quantity,
        ]);

        return $record;
    }

    /**
     * 批量记录用量
     */
    public function recordBatch(array $records): int
    {
        $count = 0;
        DB::transaction(function () use ($records, &$count) {
            foreach ($records as $params) {
                $this->record($params);
                $count++;
            }
        });

        return $count;
    }

    /**
     * 检查是否超过配额
     *
     * @param License $license
     * @param string $metricKey
     * @return array{allowed: bool, current: int, limit: int|null, remaining: int|null}
     */
    public function checkQuota(License $license, string $metricKey): array
    {
        $tenantId = $license->tenant_id;

        // 查找适用的配额规则（License 级优先，其次产品级）
        $quota = UsageQuota::where('is_active', true)
            ->where('metric_key', $metricKey)
            ->where(function ($q) use ($license) {
                $q->where('license_id', $license->id)
                  ->orWhere(function ($sub) use ($license) {
                      $sub->whereNull('license_id')
                          ->where('product_id', $license->product_id);
                  });
            })
            ->orderBy('license_id', 'desc') // license 级优先
            ->first();

        if (! $quota) {
            // 无配额限制
            return ['allowed' => true, 'current' => 0, 'limit' => null, 'remaining' => null];
        }

        // 计算当前用量
        $current = $this->getUsageInWindow(
            $tenantId,
            $metricKey,
            $quota->window_type,
            $license->id,
        );

        $limit = (int) $quota->quota_limit;
        $remaining = max(0, $limit - $current);

        if ($current >= $limit) {
            if ($quota->action_on_exceed === UsageQuota::ACTION_BLOCK) {
                Log::warning('UsageMeter: quota exceeded (blocked)', [
                    'license_id'  => $license->id,
                    'metric_key'  => $metricKey,
                    'current'     => $current,
                    'limit'       => $limit,
                ]);

                return ['allowed' => false, 'current' => $current, 'limit' => $limit, 'remaining' => 0];
            }

            // warn / log 模式仅记录，不拦截
            Log::info('UsageMeter: quota exceeded (warn)', [
                'license_id'  => $license->id,
                'metric_key'  => $metricKey,
                'current'     => $current,
                'limit'       => $limit,
                'action'      => $quota->action_on_exceed,
            ]);
        }

        return ['allowed' => true, 'current' => $current, 'limit' => $limit, 'remaining' => $remaining];
    }

    /**
     * 获取指定时间窗内的用量
     *
     * @param int    $tenantId
     * @param string $metricKey
     * @param string $windowType  total / daily / monthly / custom
     * @param int|null $licenseId
     * @param int|null $customerId
     * @param Carbon|null $startDate
     * @param Carbon|null $endDate
     * @return int
     */
    public function getUsageInWindow(
        int $tenantId,
        string $metricKey,
        string $windowType = 'total',
        ?int $licenseId = null,
        ?int $customerId = null,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
    ): int {
        $query = UsageRecord::where('tenant_id', $tenantId)
            ->where('metric_key', $metricKey);

        if ($licenseId) {
            $query->where('license_id', $licenseId);
        }
        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        switch ($windowType) {
            case 'daily':
                $query->whereDate('recorded_at', $startDate ?? today());
                break;
            case 'monthly':
                $now = now();
                $query->whereYear('recorded_at', $startDate?->year ?? $now->year)
                      ->whereMonth('recorded_at', $startDate?->month ?? $now->month);
                break;
            case 'total':
                // 无时间过滤
                break;
            case 'custom':
            default:
                if ($startDate) {
                    $query->where('recorded_at', '>=', $startDate);
                }
                if ($endDate) {
                    $query->where('recorded_at', '<=', $endDate);
                }
                break;
        }

        return (int) $query->sum('quantity');
    }

    /**
     * 获取用量统计（含聚合趋势）
     *
     * @param int    $tenantId
     * @param string $metricKey
     * @param string $period     daily / monthly
     * @param int    $limit      返回期数
     * @return array
     */
    public function getStats(int $tenantId, string $metricKey, string $period = 'monthly', int $limit = 12): array
    {
        // 优先查聚合表，没有则实时计算
        $aggregates = UsageAggregate::where('tenant_id', $tenantId)
            ->where('metric_key', $metricKey)
            ->where('period', $period)
            ->orderBy('period_start', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn (UsageAggregate $agg) => [
                'period_start' => $agg->period_start->toDateString(),
                'period_end'   => $agg->period_end->toDateString(),
                'total'        => $agg->total_quantity,
                'records'      => $agg->record_count,
            ]);

        if ($aggregates->isNotEmpty()) {
            return [
                'metric_key' => $metricKey,
                'period'     => $period,
                'data'       => $aggregates->values()->toArray(),
            ];
        }

        // 降级：实时计算
        $rawData = UsageRecord::where('tenant_id', $tenantId)
            ->where('metric_key', $metricKey)
            ->selectRaw(
                $period === 'daily'
                    ? "DATE(recorded_at) as period_start, DATE(recorded_at) as period_end, SUM(quantity) as total, COUNT(*) as records"
                    : "DATE_FORMAT(recorded_at, '%Y-%m-01') as period_start, LAST_DAY(recorded_at) as period_end, SUM(quantity) as total, COUNT(*) as records"
            )
            ->groupBy('period_start', 'period_end')
            ->orderBy('period_start', 'desc')
            ->limit($limit)
            ->get();

        return [
            'metric_key' => $metricKey,
            'period'     => $period,
            'data'       => $rawData->toArray(),
        ];
    }

    /**
     * 执行聚合（通常由定时任务调用）
     *
     * 将原始记录聚合到 usage_aggregates 表，清理过期原始记录。
     */
    public function aggregate(string $period = 'daily'): array
    {
        $stats = ['aggregated' => 0, 'period' => $period];

        // 按 tenant + metric_key 分组聚合
        $rows = DB::table('usage_records')
            ->select(
                'tenant_id',
                'license_id',
                'customer_id',
                'metric_key',
                DB::raw($period === 'daily'
                    ? "DATE(recorded_at) as period_start"
                    : "DATE_FORMAT(recorded_at, '%Y-%m-01') as period_start"
                ),
                DB::raw($period === 'daily'
                    ? "DATE(recorded_at) as period_end"
                    : "LAST_DAY(recorded_at) as period_end"
                ),
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('COUNT(*) as record_count'),
            )
            ->groupBy('tenant_id', 'license_id', 'customer_id', 'metric_key', 'period_start', 'period_end')
            ->get();

        DB::transaction(function () use ($rows, $period, &$stats) {
            foreach ($rows as $row) {
                UsageAggregate::updateOrCreate(
                    [
                        'tenant_id'    => $row->tenant_id,
                        'license_id'   => $row->license_id,
                        'customer_id'  => $row->customer_id,
                        'metric_key'   => $row->metric_key,
                        'period'       => $period,
                        'period_start' => $row->period_start,
                    ],
                    [
                        'period_end'     => $row->period_end,
                        'total_quantity' => $row->total_quantity,
                        'record_count'   => $row->record_count,
                    ]
                );
                $stats['aggregated']++;
            }
        });

        Log::info('UsageMeter: aggregation completed', $stats);

        return $stats;
    }

    /**
     * 创建或更新配额规则
     */
    public function upsertQuota(array $data): UsageQuota
    {
        $quota = UsageQuota::updateOrCreate(
            [
                'tenant_id'   => $data['tenant_id'],
                'license_id'  => $data['license_id'] ?? null,
                'product_id'  => $data['product_id'] ?? null,
                'metric_key'  => $data['metric_key'],
                'window_type' => $data['window_type'],
            ],
            [
                'quota_limit'      => $data['quota_limit'],
                'action_on_exceed' => $data['action_on_exceed'] ?? UsageQuota::ACTION_BLOCK,
                'is_active'        => $data['is_active'] ?? true,
            ]
        );

        $cacheKey = $this->getQuotaCacheKey($quota);
        Cache::forget($cacheKey);

        return $quota;
    }

    /**
     * 获取可用的计量指标列表
     */
    public function getAvailableMetrics(): array
    {
        return collect(self::METRICS)->map(function ($meta, $key) {
            return [
                'key'         => $key,
                'name'        => $meta['name'],
                'unit'        => $meta['unit'],
                'window_type' => $meta['window'],
            ];
        })->values()->toArray();
    }

    /**
     * 清空指定租户的用量记录（用于测试/重置）
     */
    public function clearTenantData(int $tenantId): void
    {
        UsageRecord::where('tenant_id', $tenantId)->delete();
        UsageAggregate::where('tenant_id', $tenantId)->delete();
        UsageQuota::where('tenant_id', $tenantId)->delete();
    }

    /**
     * 清除聚合缓存
     */
    protected function clearAggregateCache(int $tenantId, string $metricKey): void
    {
        Cache::forget(self::CACHE_PREFIX . "stats:{$tenantId}:{$metricKey}");
    }

    /**
     * 获取配额缓存键
     */
    protected function getQuotaCacheKey(UsageQuota $quota): string
    {
        return self::CACHE_PREFIX . "quota:{$quota->tenant_id}:{$quota->metric_key}:{$quota->id}";
    }
}
