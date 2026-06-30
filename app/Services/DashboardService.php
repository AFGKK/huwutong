<?php

namespace App\Services;

use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Models\DashboardWidgetCache;
use App\Models\DashboardWidgetTemplate;
use App\Models\License;
use App\Models\Log;
use App\Models\Subscription;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * 自定义仪表盘服务
 *
 * 提供：
 * - 仪表盘 CRUD（多布局）
 * - Widget CRUD + 位置/大小配置
 * - 多种数据源小部件（统计/图表/列表/指标）
 * - Widget 模板仓库
 * - 数据刷新与缓存
 */
class DashboardService
{
    // ─── 仪表盘 CRUD ───

    public function getDashboards(int $userId, ?int $tenantId = null): array
    {
        return Dashboard::withCount('widgets')
            ->where('user_id', $userId)
            ->when($tenantId, fn($q) => $q->orWhere('tenant_id', $tenantId))
            ->orderBy('sort_order')
            ->orderByDesc('updated_at')
            ->get()->all();
    }

    public function getDashboard(int $id): Dashboard
    {
        return Dashboard::with('widgets.cache')->findOrFail($id);
    }

    public function createDashboard(array $data): Dashboard
    {
        return Dashboard::create($data);
    }

    public function updateDashboard(Dashboard $dashboard, array $data): Dashboard
    {
        $dashboard->update($data);
        return $dashboard->fresh();
    }

    public function deleteDashboard(Dashboard $dashboard): void
    {
        $dashboard->widgets()->delete();
        $dashboard->delete();
    }

    public function setDefault(Dashboard $dashboard): Dashboard
    {
        Dashboard::where('user_id', $dashboard->user_id)->update(['is_default' => false]);
        $dashboard->update(['is_default' => true]);
        return $dashboard->fresh();
    }

    public function duplicateDashboard(Dashboard $dashboard): Dashboard
    {
        $clone = $dashboard->replicate();
        $clone->name = $dashboard->name . ' (副本)';
        $clone->is_default = false;
        $clone->push();

        foreach ($dashboard->widgets as $widget) {
            $w = $widget->replicate();
            $w->dashboard_id = $clone->id;
            $w->save();
        }

        return $clone->fresh('widgets');
    }

    // ─── Widget CRUD ───

    public function addWidget(int $dashboardId, array $data): DashboardWidget
    {
        $maxOrder = DashboardWidget::where('dashboard_id', $dashboardId)->max('sort_order') ?? 0;

        return DashboardWidget::create(array_merge($data, [
            'dashboard_id' => $dashboardId,
            'sort_order' => $maxOrder + 1,
        ]));
    }

    public function updateWidget(DashboardWidget $widget, array $data): DashboardWidget
    {
        $widget->update($data);
        return $widget->fresh();
    }

    public function deleteWidget(DashboardWidget $widget): void
    {
        $widget->cache()?->delete();
        $widget->delete();
    }

    public function reorderWidgets(int $dashboardId, array $order): void
    {
        foreach ($order as $item) {
            DashboardWidget::where('id', $item['id'])
                ->where('dashboard_id', $dashboardId)
                ->update([
                    'sort_order' => $item['sort_order'],
                    'layout' => $item['layout'] ?? null,
                ]);
        }
    }

    // ─── Widget 数据 ───

    public function getWidgetData(DashboardWidget $widget): array
    {
        $cache = $widget->cache;

        if ($cache && !$cache->isExpired()) {
            return $cache->data ?? [];
        }

        $data = $this->fetchWidgetData($widget);

        // 更新缓存
        DashboardWidgetCache::updateOrCreate(
            ['widget_id' => $widget->id],
            [
                'data' => $data,
                'cached_at' => now(),
                'expires_at' => now()->addSeconds($widget->visual_options['refresh_interval'] ?? 300),
                'refresh_interval_seconds' => $widget->visual_options['refresh_interval'] ?? 300,
            ]
        );

        return $data;
    }

    public function refreshWidgetData(DashboardWidget $widget): array
    {
        $data = $this->fetchWidgetData($widget);

        DashboardWidgetCache::updateOrCreate(
            ['widget_id' => $widget->id],
            [
                'data' => $data,
                'cached_at' => now(),
                'expires_at' => now()->addSeconds($widget->visual_options['refresh_interval'] ?? 300),
                'refresh_interval_seconds' => $widget->visual_options['refresh_interval'] ?? 300,
            ]
        );

        return $data;
    }

    protected function fetchWidgetData(DashboardWidget $widget): array
    {
        $ds = $widget->data_source ?? [];
        $type = $ds['type'] ?? 'none';

        return match ($type) {
            'stats' => $this->fetchStatsData($ds),
            'license_stats' => $this->fetchLicenseStats($ds),
            'recent_licenses' => $this->fetchRecentLicenses($ds),
            'recent_tickets' => $this->fetchRecentTickets($ds),
            'subscription_stats' => $this->fetchSubscriptionStats($ds),
            'audit_stats' => $this->fetchAuditStats($ds),
            'user_stats' => $this->fetchUserStats($ds),
            'custom_query' => $this->fetchCustomQuery($ds),
            default => ['note' => '数据源未配置'],
        };
    }

    protected function fetchStatsData(array $ds): array
    {
        return [
            'total_licenses' => License::count(),
            'active_licenses' => License::where('status', 'active')->count(),
            'total_subscriptions' => Subscription::count(),
            'active_subscriptions' => Subscription::where('status', 'active')->count(),
            'total_users' => User::count(),
            'today_logs' => Log::whereDate('created_at', today())->count(),
        ];
    }

    protected function fetchLicenseStats(array $ds): array
    {
        $byStatus = License::selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')->get()->pluck('cnt', 'status')->toArray();

        $expiring = License::where('status', 'active')
            ->whereBetween('expires_at', [now(), now()->addDays(30)])
            ->count();

        return [
            'by_status' => $byStatus,
            'total' => array_sum($byStatus),
            'expiring_soon' => $expiring,
        ];
    }

    protected function fetchRecentLicenses(array $ds): array
    {
        $limit = $ds['limit'] ?? 10;
        return License::with('customer:id,name')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    protected function fetchRecentTickets(array $ds): array
    {
        $limit = $ds['limit'] ?? 10;
        return Ticket::with('user:id,name')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    protected function fetchSubscriptionStats(array $ds): array
    {
        $byStatus = Subscription::selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')->get()->pluck('cnt', 'status')->toArray();

        $revenue = Subscription::where('status', 'active')
            ->sum('amount');

        return [
            'by_status' => $byStatus,
            'total' => array_sum($byStatus),
            'monthly_revenue' => $revenue,
        ];
    }

    protected function fetchAuditStats(array $ds): array
    {
        $days = $ds['days'] ?? 7;
        $since = now()->subDays($days);

        $byDate = Log::selectRaw('DATE(created_at) as date, COUNT(*) as cnt')
            ->where('created_at', '>=', $since)
            ->groupBy('date')
            ->orderBy('date')
            ->get()->toArray();

        return [
            'period_days' => $days,
            'total' => Log::where('created_at', '>=', $since)->count(),
            'by_date' => $byDate,
        ];
    }

    protected function fetchUserStats(array $ds): array
    {
        $days = $ds['days'] ?? 30;
        $since = now()->subDays($days);

        return [
            'total' => User::count(),
            'active_today' => User::whereDate('last_login_at', today())->count(),
            'new_last_30d' => User::where('created_at', '>=', $since)->count(),
        ];
    }

    protected function fetchCustomQuery(array $ds): array
    {
        return ['note' => '自定义查询暂未实现'];
    }

    // ─── Widget 模板 ───

    public function getWidgetTemplates(string $category = null): array
    {
        $query = DashboardWidgetTemplate::where('is_system', true)
            ->orWhere('is_system', false);

        if ($category) {
            $query->where('category', $category);
        }

        return $query->orderBy('sort_order')->orderBy('name')->get()->all();
    }

    public function createWidgetFromTemplate(int $dashboardId, int $templateId, array $overrides = []): DashboardWidget
    {
        $template = DashboardWidgetTemplate::findOrFail($templateId);

        $data = array_merge([
            'type' => $template->type,
            'title' => $template->name,
            'description' => $template->description,
            'config' => $template->default_config,
            'layout' => $template->default_layout ?? ['w' => 4, 'h' => 3],
            'data_source' => [],
            'visual_options' => $template->default_visual_options ?? [
                'refresh_interval' => 300,
                'border' => true,
            ],
        ], $overrides);

        return $this->addWidget($dashboardId, $data);
    }

    // ─── 仪表盘数据（全量加载） ───

    public function getDashboardWithData(int $id): array
    {
        $dashboard = $this->getDashboard($id);

        $widgets = [];
        foreach ($dashboard->widgets as $widget) {
            $widgetData = $this->getWidgetData($widget);
            $widgets[] = [
                'id' => $widget->id,
                'type' => $widget->type,
                'title' => $widget->title,
                'description' => $widget->description,
                'config' => $widget->config,
                'layout' => $widget->layout,
                'data_source' => $widget->data_source,
                'visual_options' => $widget->visual_options,
                'sort_order' => $widget->sort_order,
                'is_visible' => $widget->is_visible,
                'data' => $widgetData,
            ];
        }

        return [
            'id' => $dashboard->id,
            'name' => $dashboard->name,
            'description' => $dashboard->description,
            'layout_type' => $dashboard->layout_type,
            'layout_config' => $dashboard->layout_config,
            'columns' => $dashboard->columns,
            'is_default' => $dashboard->is_default,
            'is_shared' => $dashboard->is_shared,
            'tags' => $dashboard->tags,
            'widget_count' => count($widgets),
            'widgets' => $widgets,
        ];
    }

    // ─── 看板统计 ───

    public function getDashboardOverview(int $userId, ?int $tenantId = null): array
    {
        $totalDashboards = Dashboard::where('user_id', $userId)
            ->when($tenantId, fn($q) => $q->orWhere('tenant_id', $tenantId))
            ->count();

        $totalWidgets = DashboardWidget::whereIn('dashboard_id',
            Dashboard::where('user_id', $userId)->select('id')
        )->count();

        $defaultDashboard = Dashboard::where('user_id', $userId)
            ->where('is_default', true)
            ->first();

        return [
            'total_dashboards' => $totalDashboards,
            'total_widgets' => $totalWidgets,
            'default_dashboard_id' => $defaultDashboard?->id,
            'default_dashboard_name' => $defaultDashboard?->name,
            'widget_types' => DashboardWidget::whereIn('dashboard_id',
                Dashboard::where('user_id', $userId)->select('id')
            )->selectRaw('type, COUNT(*) as cnt')
                ->groupBy('type')->get()->pluck('cnt', 'type')->toArray(),
        ];
    }
}
