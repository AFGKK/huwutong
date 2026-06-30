<?php

namespace App\Services;

use App\Models\Device;
use App\Models\License;
use App\Models\UsageRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 客户用量看板服务 (M2-97)
 *
 * 提供客户门户内用量数据的查询：
 * - API 调用趋势
 * - 各端点调用分布
 * - 功能使用排行
 * - 通信客户门户 Usage.vue 前端
 */
class UsageDashboardService
{
    /**
     * 获取 API 调用趋势（按天）
     */
    public function getApiCalls(int $tenantId, ?int $customerId = null, string $period = 'month', int $days = 7): array
    {
        $now = now();
        $startDate = match ($period) {
            'last_month' => (clone $now)->subMonthNoOverflow()->startOfMonth(),
            'quarter' => (clone $now)->subDays(90),
            default => $period === '7d' ? (clone $now)->subDays(6)->startOfDay() : (clone $now)->subDays($days - 1)->startOfDay(),
        };
        $endDate = $now->copy()->endOfDay();

        $query = UsageRecord::where('tenant_id', $tenantId)
            ->whereIn('metric_key', ['api_call.activate', 'api_call.validate', 'api_call.revoke', 'api_call.check'])
            ->whereBetween('recorded_at', [$startDate, $endDate]);

        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        $raw = $query->select(
            DB::raw('DATE(recorded_at) as date'),
            DB::raw('SUM(quantity) as count')
        )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        // 补全天数
        $daily = [];
        $current = $startDate->copy();
        while ($current->lte($endDate)) {
            $dateStr = $current->toDateString();
            $daily[] = [
                'date' => $dateStr,
                'count' => (int) ($raw[$dateStr]->count ?? 0),
            ];
            $current->addDay();
        }

        return [
            'daily' => $daily,
            'total' => array_sum(array_column($daily, 'count')),
        ];
    }

    /**
     * 获取各端点调用统计
     */
    public function getEndpointStats(int $tenantId, ?int $customerId = null, string $period = 'month'): array
    {
        $now = now();
        $startDate = match ($period) {
            'last_month' => (clone $now)->subMonthNoOverflow()->startOfMonth(),
            'quarter' => (clone $now)->subDays(90),
            default => (clone $now)->startOfMonth(),
        };
        $endDate = $now->copy()->endOfDay();

        $endpointLabels = [
            'api_call.activate' => ['endpoint' => '/api/license/activate', 'method' => 'POST'],
            'api_call.validate' => ['endpoint' => '/api/license/validate', 'method' => 'POST'],
            'api_call.revoke'   => ['endpoint' => '/api/license/revoke',   'method' => 'POST'],
            'api_call.check'    => ['endpoint' => '/api/feature/check',    'method' => 'GET'],
        ];

        $query = UsageRecord::where('tenant_id', $tenantId)
            ->whereIn('metric_key', array_keys($endpointLabels))
            ->whereBetween('recorded_at', [$startDate, $endDate]);

        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        $raw = $query->select('metric_key', DB::raw('SUM(quantity) as count'))
            ->groupBy('metric_key')
            ->get()
            ->keyBy('metric_key');

        $results = [];
        foreach ($endpointLabels as $key => $label) {
            $results[] = [
                'endpoint' => $label['endpoint'],
                'method' => $label['method'],
                'count' => (int) ($raw[$key]->count ?? 0),
            ];
        }

        return $results;
    }

    /**
     * 获取功能使用排行
     */
    public function getFeatureUsage(int $tenantId, ?int $customerId = null, string $period = 'month'): array
    {
        $now = now();
        $startDate = match ($period) {
            'last_month' => (clone $now)->subMonthNoOverflow()->startOfMonth(),
            'quarter' => (clone $now)->subDays(90),
            default => (clone $now)->startOfMonth(),
        };
        $endDate = $now->copy()->endOfDay();

        $query = UsageRecord::where('tenant_id', $tenantId)
            ->whereBetween('recorded_at', [$startDate, $endDate]);

        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        $raw = $query->select('action', DB::raw('SUM(quantity) as count'))
            ->groupBy('action')
            ->orderByDesc('count')
            ->limit(15)
            ->get();

        // 补充中文标签
        $actionLabels = [
            'activate'  => 'License 激活',
            'validate'  => 'License 验证',
            'revoke'    => 'License 吊销',
            'check'     => 'Feature 检查',
            'device_bind' => '设备绑定',
            'device_unbind' => '设备解绑',
            'register'  => '注册设备',
            'heartbeat' => '设备心跳',
            'webhook_send' => 'Webhook 推送',
            'email_send' => '邮件发送',
        ];

        return $raw->map(function ($item) use ($actionLabels) {
            return [
                'name' => $actionLabels[$item->action] ?? $item->action,
                'count' => (int) $item->count,
            ];
        })->toArray();
    }

    /**
     * 获取概览统计数据（用于门户首页卡片）
     */
    public function getOverview(int $tenantId, ?int $customerId = null): array
    {
        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        $lastMonthStart = (clone $now)->subMonthNoOverflow()->startOfMonth();
        $lastMonthEnd = (clone $now)->subMonthNoOverflow()->endOfMonth();

        // API 调用次数
        $apiCallsThisMonth = UsageRecord::where('tenant_id', $tenantId)
            ->whereIn('metric_key', ['api_call.activate', 'api_call.validate', 'api_call.revoke', 'api_call.check'])
            ->where('recorded_at', '>=', $monthStart)
            ->when($customerId, fn($q) => $q->where('customer_id', $customerId))
            ->sum('quantity');

        $apiCallsLastMonth = UsageRecord::where('tenant_id', $tenantId)
            ->whereIn('metric_key', ['api_call.activate', 'api_call.validate', 'api_call.revoke', 'api_call.check'])
            ->whereBetween('recorded_at', [$lastMonthStart, $lastMonthEnd])
            ->when($customerId, fn($q) => $q->where('customer_id', $customerId))
            ->sum('quantity');

        // 活跃设备数
        $activeDevices = Device::where('tenant_id', $tenantId)
            ->when($customerId, fn($q) => $q->whereHas('licenses', fn($l) => $l->where('customer_id', $customerId)))
            ->count();

        // 本月新增设备
        $newDevices = Device::where('tenant_id', $tenantId)
            ->where('created_at', '>=', $monthStart)
            ->when($customerId, fn($q) => $q->whereHas('licenses', fn($l) => $l->where('customer_id', $customerId)))
            ->count();

        // 配额使用率（简单平均）
        $licenses = License::where('tenant_id', $tenantId)
            ->whereIn('status', ['active', 'suspended'])
            ->when($customerId, fn($q) => $q->where('customer_id', $customerId))
            ->get();

        $avgUsage = 0;
        if ($licenses->count() > 0) {
            $totalPercent = $licenses->sum(function ($l) {
                $max = $l->max_devices ?: 1;
                return min(round(($l->active_devices_count ?? 0) / $max * 100), 100);
            });
            $avgUsage = round($totalPercent / $licenses->count());
        }

        $apiTrend = $apiCallsLastMonth > 0
            ? round((($apiCallsThisMonth - $apiCallsLastMonth) / $apiCallsLastMonth) * 100)
            : ($apiCallsThisMonth > 0 ? 100 : 0);

        return [
            'api_calls' => (int) $apiCallsThisMonth,
            'active_devices' => $activeDevices,
            'new_devices' => $newDevices,
            'quota_usage' => $avgUsage,
            'api_trend' => $apiTrend,
        ];
    }
}
