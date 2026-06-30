<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\License;
use App\Models\PersonalizedRecommendation;
use App\Models\PricingPlan;
use App\Models\UserBehavior;
use App\Models\UserPreference;
use Illuminate\Support\Facades\DB;

/**
 * AI 驱动个性化服务 (M3-80)
 *
 * 三大核心功能：
 * 1. 用户行为追踪 — 记录和分析用户行为
 * 2. 个性化推荐引擎 — 基于 RFM/行为/规则的推荐
 * 3. 个性化门户主页 — 动态内容聚合
 */
class PersonalizationService
{
    // ═══════ 用户行为追踪 ═══════

    public function recordBehavior(array $data): UserBehavior
    {
        return UserBehavior::create(array_merge($data, [
            'occurred_at' => $data['occurred_at'] ?? now(),
        ]));
    }

    public function getBehaviorStats(int $tenantId, array $filters = []): array
    {
        $query = UserBehavior::where('tenant_id', $tenantId);

        if (!empty($filters['event_type'])) $query->where('event_type', $filters['event_type']);
        if (!empty($filters['customer_id'])) $query->where('customer_id', $filters['customer_id']);
        if (!empty($filters['from'])) $query->where('occurred_at', '>=', $filters['from']);
        if (!empty($filters['to'])) $query->where('occurred_at', '<=', $filters['to']);

        $totalEvents = (clone $query)->count();

        $byType = (clone $query)
            ->selectRaw('event_type, COUNT(*) as cnt')
            ->groupBy('event_type')
            ->orderByDesc('cnt')
            ->get();

        $dailyTrend = (clone $query)
            ->selectRaw('DATE(occurred_at) as date, COUNT(*) as cnt')
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->limit(30)
            ->get();

        $topCustomers = (clone $query)
            ->selectRaw('customer_id, COUNT(*) as cnt')
            ->whereNotNull('customer_id')
            ->groupBy('customer_id')
            ->orderByDesc('cnt')
            ->limit(10)
            ->with('customer:id,name')
            ->get();

        return [
            'total_events' => $totalEvents,
            'by_type' => $byType,
            'daily_trend' => $dailyTrend,
            'top_customers' => $topCustomers,
        ];
    }

    // ═══════ 用户偏好 ═══════

    public function getPreference(int $userId, string $key): ?string
    {
        $pref = UserPreference::where('user_id', $userId)
            ->where('preference_key', $key)
            ->first();

        return $pref?->preference_value;
    }

    public function setPreference(int $tenantId, int $userId, string $key, $value, ?int $customerId = null): UserPreference
    {
        $type = match (true) {
            is_bool($value) => 'boolean',
            is_array($value) || is_object($value) => 'json',
            is_int($value) => 'integer',
            default => 'string',
        };

        $serialized = is_array($value) || is_object($value) ? json_encode($value) : (string) $value;

        return UserPreference::updateOrCreate(
            ['user_id' => $userId, 'preference_key' => $key],
            [
                'tenant_id' => $tenantId,
                'customer_id' => $customerId,
                'preference_value' => $serialized,
                'preference_type' => $type,
            ]
        );
    }

    public function getAllPreferences(int $userId): array
    {
        return UserPreference::where('user_id', $userId)
            ->pluck('preference_value', 'preference_key')
            ->toArray();
    }

    // ═══════ 推荐引擎 ═══════

    /**
     * 为指定客户生成个性化推荐
     */
    public function generateRecommendations(int $tenantId, int $customerId): array
    {
        $generated = [];

        // 1. 基于 RFM 的 License 推荐
        $rfmRecs = $this->generateRfmRecommendations($tenantId, $customerId);
        $generated = array_merge($generated, $rfmRecs);

        // 2. 基于行为的 Feature 推荐
        $behaviorRecs = $this->generateBehaviorRecommendations($tenantId, $customerId);
        $generated = array_merge($generated, $behaviorRecs);

        // 3. 基于规则的 Addon 推荐
        $ruleRecs = $this->generateRuleRecommendations($tenantId, $customerId);
        $generated = array_merge($generated, $ruleRecs);

        // 批量保存
        foreach ($generated as &$rec) {
            $existing = PersonalizedRecommendation::where('tenant_id', $tenantId)
                ->where('customer_id', $customerId)
                ->where('recommendation_type', $rec['recommendation_type'])
                ->where('recommendable_id', $rec['recommendable_id'])
                ->where('recommendable_type', $rec['recommendable_type'])
                ->where('is_dismissed', false)
                ->first();

            if (!$existing) {
                $existing = PersonalizedRecommendation::create($rec);
            }
            $rec = $existing;
        }

        return $generated;
    }

    /**
     * 基于 RFM 分数的推荐：活跃但 License 少 → 推荐更多 License
     */
    protected function generateRfmRecommendations(int $tenantId, int $customerId): array
    {
        $recs = [];
        $rfm = \App\Models\RfmScore::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->first();

        if (!$rfm) return [];

        // 高价值客户但没有很多 License → 推荐升级
        $licenseCount = License::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->count();

        if ($rfm->rfm_segment === 'Champions' && $licenseCount <= 1) {
            $plans = PricingPlan::where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->orderBy('price_monthly', 'desc')
                ->limit(2)
                ->get();

            foreach ($plans as $plan) {
                $recs[] = [
                    'tenant_id' => $tenantId,
                    'customer_id' => $customerId,
                    'recommendation_type' => 'license',
                    'recommendable_id' => $plan->id,
                    'recommendable_type' => PricingPlan::class,
                    'reason' => '作为高价值客户，推荐升级套餐获取更多 License',
                    'score' => 0.9,
                    'source' => 'rfm',
                ];
            }
        }

        // 睡眠/流失风险客户 → 推荐恢复套餐
        if (in_array($rfm->rfm_segment, ['About to Sleep', 'Lost']) || $rfm->rfm_total < 3) {
            $starter = PricingPlan::where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->orderBy('price_monthly')
                ->first();

            if ($starter) {
                $recs[] = [
                    'tenant_id' => $tenantId,
                    'customer_id' => $customerId,
                    'recommendation_type' => 'license',
                    'recommendable_id' => $starter->id,
                    'recommendable_type' => PricingPlan::class,
                    'reason' => '为您推荐入门套餐，重新激活服务',
                    'score' => 0.7,
                    'source' => 'rfm',
                ];
            }
        }

        return $recs;
    }

    /**
     * 基于行为的推荐：分析用户常用功能，推荐相关增强服务
     */
    protected function generateBehaviorRecommendations(int $tenantId, int $customerId): array
    {
        $recs = [];

        // 获取最近30天的前3个行为类型
        $topTypes = UserBehavior::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->where('occurred_at', '>=', now()->subDays(30))
            ->selectRaw('event_type, COUNT(*) as cnt')
            ->groupBy('event_type')
            ->orderByDesc('cnt')
            ->limit(3)
            ->pluck('event_type')
            ->toArray();

        // API 调用频繁 → 推荐 API 扩展包
        if (in_array('api_call', $topTypes)) {
            $recs[] = [
                'tenant_id' => $tenantId,
                'customer_id' => $customerId,
                'recommendation_type' => 'addon',
                'recommendable_id' => 0,
                'recommendable_type' => 'App\Models\VasService',
                'reason' => '检测到大量 API 调用，推荐 API 扩展包提升限额',
                'score' => 0.85,
                'source' => 'behavior',
            ];
        }

        // 频繁创建工单 → 推荐技术支持包
        if (in_array('support_ticket', $topTypes)) {
            $recs[] = [
                'tenant_id' => $tenantId,
                'customer_id' => $customerId,
                'recommendation_type' => 'addon',
                'recommendable_id' => 0,
                'recommendable_type' => 'App\Models\VasService',
                'reason' => '检测到频繁工单，推荐高级技术支持服务',
                'score' => 0.8,
                'source' => 'behavior',
            ];
        }

        return $recs;
    }

    /**
     * 基于规则的推荐：新客户推荐入门内容
     */
    protected function generateRuleRecommendations(int $tenantId, int $customerId): array
    {
        $recs = [];
        $customer = Customer::find($customerId);
        if (!$customer) return [];

        $isNew = $customer->created_at >= now()->subDays(30);
        $licenseCount = License::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->count();

        // 新客户且只有1个 License → 推荐文档/入门
        if ($isNew && $licenseCount <= 1) {
            $recs[] = [
                'tenant_id' => $tenantId,
                'customer_id' => $customerId,
                'recommendation_type' => 'article',
                'recommendable_id' => 0,
                'recommendable_type' => 'guide',
                'reason' => '欢迎！推荐查看快速入门指南',
                'score' => 1.0,
                'source' => 'rule',
            ];
        }

        return $recs;
    }

    /**
     * 获取某个客户的活跃推荐
     */
    public function getActiveRecommendations(int $tenantId, int $customerId, int $limit = 5): array
    {
        return PersonalizedRecommendation::with('recommendable')
            ->where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->where('is_dismissed', false)
            ->orderByDesc('score')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * 标记推荐为已忽略
     */
    public function dismissRecommendation(int $id): void
    {
        PersonalizedRecommendation::where('id', $id)->update([
            'is_dismissed' => true,
            'dismissed_at' => now(),
        ]);
    }

    /**
     * 标记推荐为已点击
     */
    public function clickRecommendation(int $id): void
    {
        PersonalizedRecommendation::where('id', $id)->update([
            'clicked_at' => now(),
        ]);
    }

    /**
     * 管理员刷新所有客户的推荐
     */
    public function refreshAllRecommendations(int $tenantId): array
    {
        $customerIds = Customer::where('tenant_id', $tenantId)
            ->pluck('id')
            ->toArray();

        $count = 0;
        foreach ($customerIds as $customerId) {
            try {
                $this->generateRecommendations($tenantId, $customerId);
                $count++;
            } catch (\Exception $e) {
                // 跳过单个客户错误
            }
        }

        return ['refreshed' => $count, 'total' => count($customerIds)];
    }

    // ═══════ 个性化门户主页 ═══════

    /**
     * 为门户主页聚合个性化内容
     */
    public function getPersonalizedHomepage(int $tenantId, int $customerId, int $userId): array
    {
        // 1. 活跃推荐
        $recommendations = $this->getActiveRecommendations($tenantId, $customerId, 5);

        // 2. 用户偏好
        $preferences = $this->getAllPreferences($userId);

        // 3. 热门功能
        $popularFeatures = $this->getPopularFeatures($tenantId, $customerId);

        // 4. 近期活动
        $recentActivity = UserBehavior::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->orderByDesc('occurred_at')
            ->limit(5)
            ->get()
            ->toArray();

        // 5. 常用操作快捷入口
        $quickActions = $this->getQuickActions($tenantId, $customerId);

        // 6. License 统计
        $licenseCount = License::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->count();

        $activeLicenseCount = License::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->where('status', 'active')
            ->count();

        return [
            'recommendations' => $recommendations,
            'preferences' => $preferences,
            'popular_features' => $popularFeatures,
            'recent_activity' => $recentActivity,
            'quick_actions' => $quickActions,
            'stats' => [
                'total_licenses' => $licenseCount,
                'active_licenses' => $activeLicenseCount,
            ],
        ];
    }

    /**
     * 获取热门功能（基于全局行为统计）
     */
    protected function getPopularFeatures(int $tenantId, int $customerId): array
    {
        return UserBehavior::where('tenant_id', $tenantId)
            ->where('event_type', 'feature_use')
            ->where('occurred_at', '>=', now()->subDays(30))
            ->selectRaw('event_action, COUNT(*) as cnt')
            ->groupBy('event_action')
            ->orderByDesc('cnt')
            ->limit(8)
            ->get()
            ->toArray();
    }

    /**
     * 基于客户状态的快捷操作建议
     */
    protected function getQuickActions(int $tenantId, int $customerId): array
    {
        $customer = Customer::find($customerId);
        $licenseCount = License::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->count();

        $actions = [
            ['key' => 'view_licenses', 'label' => '查看 License', 'icon' => 'Key', 'route' => '/portal/licenses'],
            ['key' => 'create_ticket', 'label' => '提交工单', 'icon' => 'ChatDotSquare', 'route' => '/portal/tickets'],
        ];

        if ($licenseCount === 0) {
            $actions[] = ['key' => 'buy_license', 'label' => '购买 License', 'icon' => 'ShoppingCart', 'route' => '/portal/billing'];
        }

        return $actions;
    }

    // ═══════ 管理端统计 ═══════

    public function getAdminDashboard(int $tenantId): array
    {
        $totalEvents = UserBehavior::where('tenant_id', $tenantId)->count();
        $todayEvents = UserBehavior::where('tenant_id', $tenantId)
            ->whereDate('occurred_at', today())
            ->count();

        $activeRecommendations = PersonalizedRecommendation::where('tenant_id', $tenantId)
            ->where('is_dismissed', false)
            ->count();

        $clickedRecommendations = PersonalizedRecommendation::where('tenant_id', $tenantId)
            ->whereNotNull('clicked_at')
            ->count();

        $preferenceCount = UserPreference::where('tenant_id', $tenantId)->count();
        $customerCount = Customer::where('tenant_id', $tenantId)->count();

        $topEvents = UserBehavior::where('tenant_id', $tenantId)
            ->selectRaw('event_type, COUNT(*) as cnt')
            ->groupBy('event_type')
            ->orderByDesc('cnt')
            ->limit(5)
            ->get();

        $last7Days = UserBehavior::where('tenant_id', $tenantId)
            ->where('occurred_at', '>=', now()->subDays(7))
            ->selectRaw('DATE(occurred_at) as date, COUNT(*) as cnt')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'total_events' => $totalEvents,
            'today_events' => $todayEvents,
            'active_recommendations' => $activeRecommendations,
            'clicked_recommendations' => $clickedRecommendations,
            'preference_count' => $preferenceCount,
            'customer_count' => $customerCount,
            'top_events' => $topEvents,
            'last_7_days_trend' => $last7Days,
        ];
    }
}
