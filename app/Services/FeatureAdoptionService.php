<?php

namespace App\Services;

use App\Models\FeatureDailySummary;
use App\Models\FeatureEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 功能使用率追踪服务 (M2-60)
 */
class FeatureAdoptionService
{
    /**
     * 记录功能使用事件
     */
    public function track(string $featureKey, array $data = []): ?FeatureEvent
    {
        if (!config('feature-adoption.tracking.enabled')) {
            return null;
        }

        // 采样检查
        $sampleRate = config('feature-adoption.tracking.sample_rate', 100);
        if ($sampleRate < 100 && random_int(1, 100) > $sampleRate) {
            return null;
        }

        $features = config('feature-adoption.features', []);
        $featureDef = $features[$featureKey] ?? null;

        return FeatureEvent::create([
            'feature_key' => $featureKey,
            'feature_name' => $featureDef['name'] ?? $featureKey,
            'category' => $featureDef['category'] ?? ($data['category'] ?? null),
            'action' => $data['action'] ?? 'view',
            'user_id' => $data['user_id'] ?? null,
            'customer_id' => $data['customer_id'] ?? null,
            'session_id' => $data['session_id'] ?? null,
            'ip_address' => $data['ip_address'] ?? request()->ip(),
            'user_agent' => $data['user_agent'] ?? request()->userAgent(),
            'page_url' => $data['page_url'] ?? request()->fullUrl(),
            'metadata' => $data['metadata'] ?? null,
        ]);
    }

    /**
     * 批量记录事件（前端埋点批量上报）
     */
    public function batchTrack(array $events): int
    {
        $count = 0;
        foreach ($events as $event) {
            $key = $event['feature_key'] ?? null;
            if ($key) {
                $this->track($key, $event);
                $count++;
            }
        }
        return $count;
    }

    /**
     * 获取仪表盘数据
     */
    public function getDashboard(string $startDate, string $endDate): array
    {
        $events = FeatureEvent::whereBetween('created_at', [$startDate, $endDate])->get();
        $totalEvents = $events->count();

        // 按功能统计
        $byFeature = $events->groupBy('feature_key')->map(function ($group) {
            $first = $group->first();
            return [
                'feature_key' => $first->feature_key,
                'feature_name' => $first->feature_name,
                'category' => $first->category,
                'pv' => $group->count(),
                'uv' => $group->pluck('user_id')->unique()->filter()->count(),
            ];
        })->sortByDesc('pv')->values();

        // 按分类统计
        $byCategory = $events->groupBy('category')->map(function ($group) {
            return [
                'category' => $group->first()->category,
                'pv' => $group->count(),
                'uv' => $group->pluck('user_id')->unique()->filter()->count(),
            ];
        })->values();

        // 高频功能 Top 10
        $topFeatures = $byFeature->take(10);

        // 总活跃用户数
        $activeUsers = $events->pluck('user_id')->unique()->filter()->count();

        // 总体采用率（按功能平均值）
        $totalUsers = User::count();
        $avgAdoptionRate = $totalUsers > 0
            ? round($activeUsers / $totalUsers * 100, 1)
            : 0;

        $categories = config('feature-adoption.categories', []);

        return [
            'stats' => [
                'total_events' => $totalEvents,
                'active_users' => $activeUsers,
                'total_users' => $totalUsers,
                'avg_adoption_rate' => $avgAdoptionRate,
                'feature_count' => count($byFeature),
                'category_count' => count($byCategory),
            ],
            'by_feature' => $byFeature,
            'by_category' => $byCategory,
            'top_features' => $topFeatures,
            'categories' => $categories,
            'features_def' => config('feature-adoption.features', []),
        ];
    }

    /**
     * 获取功能详情
     */
    public function getFeatureDetail(string $featureKey, string $startDate, string $endDate): array
    {
        $events = FeatureEvent::byFeature($featureKey)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $features = config('feature-adoption.features', []);
        $featureDef = $features[$featureKey] ?? null;

        // 按天趋势
        $trend = $events->groupBy(function ($e) {
            return $e->created_at->toDateString();
        })->map(function ($group, $date) {
            return [
                'date' => $date,
                'pv' => $group->count(),
                'uv' => $group->pluck('user_id')->unique()->filter()->count(),
            ];
        })->sortBy('date')->values();

        // 按操作类型
        $byAction = $events->groupBy('action')->map(function ($group) {
            return [
                'action' => $group->first()->action,
                'count' => $group->count(),
            ];
        })->values();

        // 使用用户列表
        $userIds = $events->pluck('user_id')->unique()->filter()->values();
        $totalUsers = User::count();
        $adoptionRate = $totalUsers > 0
            ? round($userIds->count() / $totalUsers * 100, 1)
            : 0;

        return [
            'feature_key' => $featureKey,
            'feature_name' => $featureDef['name'] ?? $featureKey,
            'category' => $featureDef['category'] ?? null,
            'total_events' => $events->count(),
            'unique_users' => $userIds->count(),
            'adoption_rate' => $adoptionRate,
            'trend' => $trend,
            'by_action' => $byAction,
        ];
    }

    /**
     * 获取分类详情
     */
    public function getCategoryDetail(string $category, string $startDate, string $endDate): array
    {
        $events = FeatureEvent::byCategory($category)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        // 按功能统计
        $byFeature = $events->groupBy('feature_key')->map(function ($group) {
            $first = $group->first();
            return [
                'feature_key' => $first->feature_key,
                'feature_name' => $first->feature_name,
                'pv' => $group->count(),
                'uv' => $group->pluck('user_id')->unique()->filter()->count(),
            ];
        })->values();

        // 按天趋势
        $trend = $events->groupBy(function ($e) {
            return $e->created_at->toDateString();
        })->map(function ($group, $date) {
            return [
                'date' => $date,
                'pv' => $group->count(),
                'uv' => $group->pluck('user_id')->unique()->filter()->count(),
            ];
        })->sortBy('date')->values();

        return [
            'category' => $category,
            'category_name' => config("feature-adoption.categories.{$category}", $category),
            'total_events' => $events->count(),
            'unique_users' => $events->pluck('user_id')->unique()->filter()->count(),
            'by_feature' => $byFeature,
            'trend' => $trend,
        ];
    }

    /**
     * 获取漏斗分析
     */
    public function getFunnel(string $funnelKey, string $startDate, string $endDate): array
    {
        $funnels = config('feature-adoption.funnels', []);
        $funnel = $funnels[$funnelKey] ?? null;

        if (!$funnel) {
            return ['error' => '漏斗不存在'];
        }

        $steps = [];
        $totalUsers = User::count();

        foreach ($funnel['steps'] as $index => $stepKey) {
            $userIds = FeatureEvent::byFeature($stepKey)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->pluck('user_id')
                ->unique()
                ->filter()
                ->values();

            $count = $userIds->count();
            $prevCount = $index > 0 ? ($steps[$index - 1]['user_count'] ?? 0) : $count;
            $conversionRate = $prevCount > 0 ? round($count / $prevCount * 100, 1) : 0;
            $adoptionRate = $totalUsers > 0 ? round($count / $totalUsers * 100, 1) : 0;

            $features = config('feature-adoption.features', []);
            $stepDef = $features[$stepKey] ?? null;

            $steps[] = [
                'step' => $index + 1,
                'feature_key' => $stepKey,
                'feature_name' => $stepDef['name'] ?? $stepKey,
                'user_count' => $count,
                'conversion_rate' => $conversionRate,
                'adoption_rate' => $adoptionRate,
                'drop_count' => $index > 0 ? $prevCount - $count : 0,
                'drop_rate' => $index > 0 && $prevCount > 0 ? round(($prevCount - $count) / $prevCount * 100, 1) : 0,
            ];
        }

        return [
            'funnel_key' => $funnelKey,
            'funnel_name' => $funnel['name'],
            'steps' => $steps,
            'overall_conversion' => count($steps) >= 2
                ? round(end($steps)['user_count'] / $steps[0]['user_count'] * 100, 1)
                : 100,
        ];
    }

    /**
     * 获取趋势数据
     */
    public function getTrend(string $startDate, string $endDate): array
    {
        $summaries = FeatureDailySummary::whereBetween('snapshot_date', [$startDate, $endDate])
            ->selectRaw('snapshot_date,
                SUM(pv) as total_pv,
                SUM(uv) as total_uv,
                COUNT(DISTINCT feature_key) as active_features')
            ->groupBy('snapshot_date')
            ->orderBy('snapshot_date')
            ->get();

        return $summaries->toArray();
    }

    /**
     * 生成每日快照
     */
    public function generateDailySnapshot(): array
    {
        $today = now()->startOfDay();
        $tomorrow = now()->startOfDay()->addDay();
        $totalUsers = User::count();

        $events = FeatureEvent::whereBetween('created_at', [$today, $tomorrow])->get();
        $grouped = $events->groupBy('feature_key');

        $created = 0;
        foreach ($grouped as $featureKey => $group) {
            $first = $group->first();
            $pv = $group->count();
            $uv = $group->pluck('user_id')->unique()->filter()->count();
            $adoptionRate = $totalUsers > 0 ? round($uv / $totalUsers * 1000) : 0;

            FeatureDailySummary::updateOrCreate(
                ['snapshot_date' => $today->toDateString(), 'feature_key' => $featureKey],
                [
                    'feature_name' => $first->feature_name,
                    'category' => $first->category,
                    'pv' => $pv,
                    'uv' => $uv,
                    'user_count' => $uv,
                    'adoption_rate' => $adoptionRate,
                ]
            );
            $created++;
        }

        return [
            'snapshot_date' => $today->toDateString(),
            'features_snapshot' => $created,
            'total_events' => $events->count(),
        ];
    }

    /**
     * 获取事件列表
     */
    public function getEvents(array $filters = []): array
    {
        $query = FeatureEvent::query();

        if (!empty($filters['feature_key'])) {
            $query->where('feature_key', $filters['feature_key']);
        }
        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }
        if (!empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }
        if (!empty($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        $perPage = (int) ($filters['per_page'] ?? 20);
        $page = (int) ($filters['page'] ?? 1);

        $total = $query->count();
        $items = $query->orderByDesc('created_at')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * 清理过期事件
     */
    public function prune(int $retentionDays = null): int
    {
        $days = $retentionDays ?? config('feature-adoption.tracking.retention_days', 365);
        $cutoff = now()->subDays($days);

        $deleted = FeatureEvent::where('created_at', '<', $cutoff)->delete();
        FeatureDailySummary::where('snapshot_date', '<', $cutoff->toDateString())->delete();

        Log::info("Feature events pruned: {$deleted} events older than {$days} days deleted");
        return $deleted;
    }
}
