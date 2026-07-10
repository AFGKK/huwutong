<?php

namespace App\Services;

use App\Models\ApiImpactNotification;
use App\Models\ApiVersion;
use App\Models\ApiVersionCall;
use App\Models\ApiVersionRoute;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * API 变更影响分析服务 (M2-111)
 *
 * 分析废弃/即将停用的 API 版本对客户的影响：
 * - 哪些客户仍在调用旧版本
 * - 调用量统计
 * - 受影响端点
 * - 定向通知
 */
class ApiImpactAnalyzerService
{
    /**
     * 看板总览
     */
    public function dashboard(): array
    {
        $deprecatedVersions = ApiVersion::whereIn('status', ['deprecated', 'sunset'])->get();
        $retiredVersions = ApiVersion::where('status', 'retired')->count();
        $totalToday = (int) ApiVersionCall::where('call_date', today())->sum('call_count');

        $impactSummary = [];
        foreach ($deprecatedVersions as $v) {
            $callCount = (int) ApiVersionCall::where('api_version_id', $v->id)
                ->where('call_date', '>=', now()->subDays(30))
                ->sum('call_count');
            $tenantCount = ApiVersionCall::where('api_version_id', $v->id)
                ->where('call_date', '>=', now()->subDays(30))
                ->distinct('tenant_id')->count('tenant_id');

            $impactSummary[] = [
                'version_id' => $v->id,
                'version' => $v->version,
                'status' => $v->status,
                'deprecated_at' => $v->deprecated_at?->toDateString(),
                'sunset_at' => $v->sunset_at?->toDateString(),
                'call_count_30d' => $callCount,
                'affected_tenants' => $tenantCount,
                'days_until_sunset' => $v->sunset_at ? now()->diffInDays($v->sunset_at, false) : null,
            ];
        }

        return [
            'deprecated_versions' => $deprecatedVersions->count(),
            'retired_versions' => $retiredVersions,
            'total_calls_today' => $totalToday,
            'impact_summary' => $impactSummary,
            'pending_notifications' => ApiImpactNotification::where('status', 'pending')->count(),
        ];
    }

    /**
     * 针对指定版本的详细影响分析
     */
    public function analyzeVersion(int $versionId, int $days = 30): array
    {
        $version = ApiVersion::findOrFail($versionId);
        $since = now()->subDays($days);

        // 总体调用统计
        $totalCalls = (int) ApiVersionCall::where('api_version_id', $versionId)
            ->where('call_date', '>=', $since)
            ->sum('call_count');

        $totalTenants = ApiVersionCall::where('api_version_id', $versionId)
            ->where('call_date', '>=', $since)
            ->distinct('tenant_id')->count('tenant_id');

        // 按方法统计
        $byMethod = ApiVersionCall::where('api_version_id', $versionId)
            ->where('call_date', '>=', $since)
            ->selectRaw('method, SUM(call_count) as total_calls')
            ->groupBy('method')
            ->pluck('total_calls', 'method');

        // 按路径统计 TOP 10
        $byPath = ApiVersionCall::where('api_version_id', $versionId)
            ->where('call_date', '>=', $since)
            ->selectRaw('path, SUM(call_count) as total_calls')
            ->groupBy('path')
            ->orderByDesc('total_calls')
            ->limit(10)
            ->get();

        // 受影响客户 TOP 20
        $affectedTenants = ApiVersionCall::where('api_version_id', $versionId)
            ->where('call_date', '>=', $since)
            ->selectRaw('tenant_id, SUM(call_count) as total_calls')
            ->groupBy('tenant_id')
            ->orderByDesc('total_calls')
            ->limit(20)
            ->with('tenant:id,name,email')
            ->get();

        // 月度趋势
        $monthlyTrend = ApiVersionCall::where('api_version_id', $versionId)
            ->where('call_date', '>=', $since)
            ->selectRaw(db_date_format('call_date', '%Y-%m').' as month, SUM(call_count) as total_calls')
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // 废弃路线由
        $deprecatedRoutes = ApiVersionRoute::where('api_version_id', $versionId)
            ->where('is_deprecated', true)
            ->get();

        return [
            'version' => $version->only(['id', 'version', 'name', 'status', 'deprecated_at', 'sunset_at', 'migration_guide']),
            'analysis_period_days' => $days,
            'total_calls' => $totalCalls,
            'total_tenants' => $totalTenants,
            'by_method' => $byMethod,
            'by_path' => $byPath,
            'affected_tenants' => $affectedTenants->map(fn($t) => [
                'tenant_id' => $t->tenant_id,
                'name' => $t->tenant->name ?? '—',
                'email' => $t->tenant->email ?? '—',
                'total_calls' => (int) $t->total_calls,
            ]),
            'monthly_trend' => $monthlyTrend,
            'deprecated_routes' => $deprecatedRoutes,
        ];
    }

    /**
     * 所有废弃/即将停用版本的综合影响报告
     */
    public function overallReport(int $days = 30): array
    {
        $since = now()->subDays($days);
        $versions = ApiVersion::whereIn('status', ['deprecated', 'sunset', 'active'])->get();
        $reports = [];

        foreach ($versions as $v) {
            $callCount = (int) ApiVersionCall::where('api_version_id', $v->id)
                ->where('call_date', '>=', $since)->sum('call_count');
            $tenantCount = ApiVersionCall::where('api_version_id', $v->id)
                ->where('call_date', '>=', $since)->distinct('tenant_id')->count('tenant_id');

            $reports[] = [
                'version_id' => $v->id,
                'version' => $v->version,
                'status' => $v->status,
                'deprecated_at' => $v->deprecated_at?->toDateString(),
                'sunset_at' => $v->sunset_at?->toDateString(),
                'is_default' => $v->is_default,
                'call_count' => $callCount,
                'tenant_count' => $tenantCount,
                'impact_level' => $this->calculateImpactLevel($v->status, $callCount, $tenantCount),
            ];
        }

        return [
            'generated_at' => now()->toDateTimeString(),
            'period_days' => $days,
            'versions' => $reports,
            'total_versions' => count($reports),
            'total_deprecated_calls' => collect($reports)->whereIn('status', ['deprecated', 'sunset'])->sum('call_count'),
        ];
    }

    /**
     * 查询指定客户使用的 API 版本
     */
    public function customerVersionUsage(int $tenantId, int $days = 90): array
    {
        $since = now()->subDays($days);

        $usage = ApiVersionCall::where('tenant_id', $tenantId)
            ->where('call_date', '>=', $since)
            ->with('apiVersion:id,version,name,status,deprecated_at,sunset_at')
            ->selectRaw('api_version_id, SUM(call_count) as total_calls')
            ->groupBy('api_version_id')
            ->orderByDesc('total_calls')
            ->get();

        return [
            'tenant_id' => $tenantId,
            'period_days' => $days,
            'versions' => $usage->map(fn($u) => [
                'version_id' => $u->api_version_id,
                'version' => $u->apiVersion->version,
                'name' => $u->apiVersion->name,
                'status' => $u->apiVersion->status,
                'deprecated_at' => $u->apiVersion->deprecated_at?->toDateString(),
                'sunset_at' => $u->apiVersion->sunset_at?->toDateString(),
                'total_calls' => (int) $u->total_calls,
                'is_affected' => in_array($u->apiVersion->status, ['deprecated', 'sunset']),
            ]),
            'affected_count' => $usage->where('apiVersion.status', 'deprecated')
                ->orWhere('apiVersion.status', 'sunset')->count(),
        ];
    }

    /**
     * 发送迁移通知
     */
    public function sendNotifications(int $versionId, string $channel = 'email'): array
    {
        $version = ApiVersion::findOrFail($versionId);
        $affectedTenants = ApiVersionCall::where('api_version_id', $versionId)
            ->where('call_date', '>=', now()->subDays(30))
            ->select('tenant_id')
            ->distinct()
            ->pluck('tenant_id');

        $sent = 0;
        $migrationGuide = $version->migration_guide;
        $sunsetDate = $version->sunset_at?->toDateString() ?? '未设定';

        foreach ($affectedTenants as $tenantId) {
            $tenant = Tenant::find($tenantId);
            if (!$tenant) continue;

            $message = "【API 版本迁移通知】您正在使用的 API 版本 {$version->version} 已标记为" .
                ($version->status === 'deprecated' ? '废弃' : '即将停用') .
                ($sunsetDate !== '未设定' ? "，计划停用日期: {$sunsetDate}" : '') .
                "。请尽快迁移到最新版本。" .
                ($migrationGuide ? "\n\n迁移指南: {$migrationGuide}" : '');

            try {
                ApiImpactNotification::create([
                    'api_version_id' => $versionId,
                    'tenant_id' => $tenantId,
                    'channel' => $channel,
                    'status' => 'sent',
                    'message' => $message,
                    'context' => [
                        'tenant_name' => $tenant->name,
                        'tenant_email' => $tenant->email,
                        'version' => $version->version,
                        'status' => $version->status,
                        'sunset_at' => $version->sunset_at?->toDateString(),
                        'migration_guide' => $migrationGuide,
                    ],
                    'sent_at' => now(),
                ]);
                $sent++;
            } catch (\Throwable $e) {
                Log::warning("API impact notification failed for tenant {$tenantId}: " . $e->getMessage());
            }
        }

        return ['version' => $version->version, 'channel' => $channel, 'sent' => $sent, 'total_tenants' => $affectedTenants->count()];
    }

    /**
     * 通知历史
     */
    public function notificationHistory(int $versionId): array
    {
        return ApiImpactNotification::where('api_version_id', $versionId)
            ->with('tenant:id,name,email')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->toArray();
    }

    /**
     * 导出影响报告 CSV
     */
    public function exportReport(int $versionId, int $days = 30): array
    {
        $analysis = $this->analyzeVersion($versionId, $days);
        $rows = [];

        // 头部
        $rows[] = ['section' => 'API 变更影响分析报告', '', '', ''];
        $rows[] = ['版本', $analysis['version']['version'], '状态', $analysis['version']['status']];
        $rows[] = ['分析周期', "{$days}天", '总调用量', $analysis['total_calls']];
        $rows[] = ['受影响客户数', $analysis['total_tenants'], '', ''];
        $rows[] = [];

        // 受影响客户
        $rows[] = ['section' => '受影响客户列表', '客户名称', '调用量', ''];
        foreach ($analysis['affected_tenants'] as $t) {
            $rows[] = ['', $t['name'], $t['total_calls'], ''];
        }
        $rows[] = [];

        // 月度趋势
        $rows[] = ['section' => '月度调用趋势', '月份', '调用量', ''];
        foreach ($analysis['monthly_trend'] as $t) {
            $rows[] = ['', $t['month'], $t['total_calls'], ''];
        }

        return $rows;
    }

    /**
     * 计算影响等级
     */
    protected function calculateImpactLevel(string $status, int $callCount, int $tenantCount): string
    {
        if ($status === 'retired') return '已停用';

        if ($status === 'sunset') {
            if ($callCount > 100000) return 'critical';
            if ($callCount > 10000) return 'high';
            if ($callCount > 0) return 'medium';
            return 'low';
        }

        if ($status === 'deprecated') {
            if ($callCount > 100000) return 'high';
            if ($callCount > 10000) return 'medium';
            if ($callCount > 0) return 'low';
            return 'none';
        }

        return 'none';
    }
}
