<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\UsageRecord;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 客户自助分析仪表盘 API
 * 
 * 为客户门户提供用量分析图表数据
 */
class CustomerAnalyticsController extends Controller
{
    /**
     * 分析仪表盘概览
     */
    public function overview(Request $request)
    {
        $tenantId = $request->user()->tenant_id ?? 1;
        $period = $request->get('period', 'month'); // month | quarter | year

        $dateFrom = match ($period) {
            'month' => now()->subMonth(),
            'quarter' => now()->subMonths(3),
            'year' => now()->subYear(),
            default => now()->subMonth(),
        };
        $prevDateFrom = (clone $dateFrom)->subDays($dateFrom->diffInDays(now()));

        // License 统计
        $totalLicenses = License::where('tenant_id', $tenantId)->whereNull('deleted_at')->count();
        $activeLicenses = License::where('tenant_id', $tenantId)->where('status', 'active')->whereNull('deleted_at')->count();
        $newLicenses = License::where('tenant_id', $tenantId)->whereNull('deleted_at')->where('created_at', '>=', $dateFrom)->count();
        $prevNewLicenses = License::where('tenant_id', $tenantId)->whereNull('deleted_at')->whereBetween('created_at', [$prevDateFrom, $dateFrom])->count();

        // 设备统计
        $totalDevices = Device::where('tenant_id', $tenantId)->count();
        $activeDevices = Device::where('tenant_id', $tenantId)->where('last_seen_at', '>=', now()->subDays(7))->count();
        $newDevices = Device::where('tenant_id', $tenantId)->where('created_at', '>=', $dateFrom)->count();

        // 订单/消费统计
        $totalOrders = DB::table('orders')->where('tenant_id', $tenantId)->count();
        $periodOrders = DB::table('orders')->where('tenant_id', $tenantId)->where('created_at', '>=', $dateFrom)->count();
        $totalSpend = DB::table('orders')->where('tenant_id', $tenantId)->where('status', 'completed')->sum('total_amount');
        $periodSpend = DB::table('orders')->where('tenant_id', $tenantId)->where('status', 'completed')->where('created_at', '>=', $dateFrom)->sum('total_amount');

        // API 调用
        $apiCalls = UsageRecord::where('tenant_id', $tenantId)->where('created_at', '>=', $dateFrom)->count();
        $prevApiCalls = UsageRecord::where('tenant_id', $tenantId)->whereBetween('created_at', [$prevDateFrom, $dateFrom])->count();

        return response()->json([
            'success' => true,
            'data' => [
                'licenses' => [
                    'total' => $totalLicenses,
                    'active' => $activeLicenses,
                    'inactive' => max(0, $totalLicenses - $activeLicenses),
                    'new' => $newLicenses,
                    'prev_new' => $prevNewLicenses,
                    'activation_rate' => $totalLicenses > 0 ? round($activeLicenses / $totalLicenses * 100, 1) : 0,
                ],
                'devices' => [
                    'total' => $totalDevices,
                    'active' => $activeDevices,
                    'new' => $newDevices,
                ],
                'orders' => [
                    'total' => $totalOrders,
                    'period' => $periodOrders,
                    'total_spend' => round($totalSpend, 2),
                    'period_spend' => round($periodSpend, 2),
                ],
                'api' => [
                    'period_calls' => $apiCalls,
                    'prev_period_calls' => $prevApiCalls,
                    'trend' => $prevApiCalls > 0 ? round(($apiCalls - $prevApiCalls) / $prevApiCalls * 100, 1) : 0,
                ],
            ],
        ]);
    }

    /**
     * License 激活趋势 (按天)
     */
    public function licenseTrend(Request $request)
    {
        $tenantId = $request->user()->tenant_id ?? 1;
        $days = min((int) $request->get('days', 30), 90);

        $trend = License::where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw("DATE(created_at) as date, COUNT(*) as count")
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date')
            ->toArray();

        // 填充缺失日期
        $result = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $result[] = [
                'date' => $date,
                'activated' => (int) ($trend[$date]['count'] ?? 0),
            ];
        }

        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * License 类型分布
     */
    public function licenseDistribution(Request $request)
    {
        $tenantId = $request->user()->tenant_id ?? 1;

        $byStatus = License::where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->selectRaw("status, COUNT(*) as count")
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $byProduct = License::where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->selectRaw("COALESCE(product_id, 0) as product_id, COUNT(*) as count")
            ->groupBy('product_id')
            ->with('product:id,name')
            ->get()
            ->map(fn($l) => [
                'name' => $l->product?->name ?? 'Unknown',
                'count' => $l->count,
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'by_status' => collect($byStatus)->map(fn($c, $s) => ['status' => $s, 'count' => $c])->values(),
                'by_product' => $byProduct,
            ],
        ]);
    }

    /**
     * 月度消费趋势
     */
    public function spendTrend(Request $request)
    {
        $tenantId = $request->user()->tenant_id ?? 1;
        $months = min((int) $request->get('months', 12), 24);

        $trend = DB::table('orders')->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->where('created_at', '>=', now()->subMonths($months))
            ->selectRaw(db_date_format('created_at', '%Y-%m')." as month, SUM(total_amount) as total, COUNT(*) as count")
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month')
            ->toArray();

        $result = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $month = now()->subMonths($i)->format('Y-m');
            $result[] = [
                'month' => $month,
                'spend' => round((float) ($trend[$month]['total'] ?? 0), 2),
                'orders' => (int) ($trend[$month]['count'] ?? 0),
            ];
        }

        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * 设备活跃趋势
     */
    public function deviceTrend(Request $request)
    {
        $tenantId = $request->user()->tenant_id ?? 1;
        $days = min((int) $request->get('days', 30), 90);

        $trend = Device::where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw("DATE(created_at) as date, COUNT(*) as count")
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date')
            ->toArray();

        $result = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $result[] = [
                'date' => $date,
                'count' => (int) ($trend[$date]['count'] ?? 0),
            ];
        }

        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * 按 License 的用量排行
     */
    public function topLicenses(Request $request)
    {
        $tenantId = $request->user()->tenant_id ?? 1;
        $limit = min((int) $request->get('limit', 10), 50);

        $licenses = License::where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->with('product:id,name')
            ->withCount('devices')
            ->orderByDesc('devices_count')
            ->limit($limit)
            ->get()
            ->map(fn($l) => [
                'license_key' => $l->license_key,
                'product_name' => $l->product?->name ?? 'Unknown',
                'status' => $l->status,
                'device_count' => $l->devices_count,
                'max_devices' => $l->max_devices ?? 0,
                'usage_percent' => ($l->max_devices ?? 0) > 0 ? round($l->devices_count / $l->max_devices * 100, 1) : 0,
                'created_at' => $l->created_at,
            ]);

        return response()->json(['success' => true, 'data' => $licenses]);
    }

    /**
     * 客户健康评分
     */
    public function healthScore(Request $request)
    {
        $tenantId = $request->user()->tenant_id ?? 1;

        // License 激活率
        $totalLicenses = License::where('tenant_id', $tenantId)->whereNull('deleted_at')->count();
        $activeLicenses = License::where('tenant_id', $tenantId)->where('status', 'active')->whereNull('deleted_at')->count();
        $activationRate = $totalLicenses > 0 ? $activeLicenses / $totalLicenses : 0;

        // 设备使用率
        $licensesWithCap = License::where('tenant_id', $tenantId)->whereNull('deleted_at')->where('max_devices', '>', 0)->get();
        $deviceUsage = $licensesWithCap->sum(fn($l) => $l->max_devices > 0 ? min(($l->active_devices_count ?? 0) / $l->max_devices, 1) : 0);
        $deviceUsageRate = $licensesWithCap->count() > 0 ? $deviceUsage / $licensesWithCap->count() : 0;

        // 近期活跃度 (7天内有活跃设备)
        $recentlyActiveDevices = Device::where('tenant_id', $tenantId)->where('last_seen_at', '>=', now()->subDays(7))->count();
        $totalDevices = Device::where('tenant_id', $tenantId)->count();
        $recentActivityRate = $totalDevices > 0 ? $recentlyActiveDevices / $totalDevices : 0;

        // 综合评分 (0-100)
        $score = round(
            $activationRate * 35 +
            $deviceUsageRate * 30 +
            $recentActivityRate * 35
        );

        $issues = [];
        if ($activationRate < 0.5) $issues[] = 'License 激活率低于 50%';
        if ($deviceUsageRate < 0.3) $issues[] = '设备使用率偏低';
        if ($recentActivityRate < 0.3) $issues[] = '近期活跃度不足';

        return response()->json([
            'success' => true,
            'data' => [
                'score' => min($score, 100),
                'level' => $score >= 80 ? 'healthy' : ($score >= 50 ? 'warning' : 'critical'),
                'activation_rate' => round($activationRate * 100, 1),
                'device_usage_rate' => round($deviceUsageRate * 100, 1),
                'recent_activity_rate' => round($recentActivityRate * 100, 1),
                'total_licenses' => $totalLicenses,
                'active_licenses' => $activeLicenses,
                'total_devices' => $totalDevices,
                'recently_active_devices' => $recentlyActiveDevices,
                'issues' => $issues,
            ],
        ]);
    }

    /**
     * 导出为 CSV
     */
    public function export(Request $request)
    {
        $tenantId = $request->user()->tenant_id ?? 1;
        $type = $request->get('type', 'licenses'); // licenses | devices | orders

        $data = match ($type) {
            'licenses' => License::where('tenant_id', $tenantId)->whereNull('deleted_at')->with('product:id,name')->get()->toArray(),
            'devices' => Device::where('tenant_id', $tenantId)->get()->toArray(),
            'orders' => DB::table('orders')->where('tenant_id', $tenantId)->get()->map(fn($o) => (array) $o)->toArray(),
            default => [],
        };

        if (empty($data)) {
            return response()->json(['success' => false, 'message' => 'No data to export'], 404);
        }

        // 展平数据：只保留标量字段
        $flattened = array_map(function ($row) {
            $flat = [];
            foreach ($row as $k => $v) {
                if (is_scalar($v) || is_null($v)) {
                    $flat[$k] = $v;
                } elseif (is_object($v) && isset($v->name)) {
                    $flat[$k] = $v->name;
                }
            }
            return $flat;
        }, $data);

        $headers = array_keys($flattened[0]);
        $csv = implode(',', $headers) . "\n";
        foreach ($flattened as $row) {
            $csv .= implode(',', array_map(fn($v) => '"' . str_replace('"', '""', (string) $v) . '"', array_values($row))) . "\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$type}_export.csv\"",
        ]);
    }
}
