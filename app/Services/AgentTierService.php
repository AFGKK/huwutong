<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\AgentTierDefinition;
use App\Models\AgentTierHistory;
use App\Models\AgentTierRule;
use App\Models\CommissionSettlement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 代理商等级管理与报表服务 (M3-04)
 *
 * 等级晋升规则评估、自动晋升、等级变更、统计报表
 */
class AgentTierService
{
    /**
     * 默认等级定义（在数据库中创建）
     */
    protected function getDefaultTiers(): array
    {
        $fn = fn(string $k) => __('app.agent_tier_service.' . $k);
        return [
            ['level' => 'regular', 'name' => $fn('tier_regular'), 'sort_order' => 1, 'default_rate' => 5.0,
             'color' => '#909399', 'benefits' => [$fn('benefit_basic_commission'), $fn('benefit_referral_link')]],
            ['level' => 'silver', 'name' => $fn('tier_silver'), 'sort_order' => 2, 'default_rate' => 10.0,
             'color' => '#C0C4CC', 'benefits' => [$fn('benefit_basic_commission'), $fn('benefit_referral_link'), $fn('benefit_monthly_bonus'), $fn('benefit_priority_support')]],
            ['level' => 'gold', 'name' => $fn('tier_gold'), 'sort_order' => 3, 'default_rate' => 20.0,
             'color' => '#E6A23C', 'benefits' => [$fn('benefit_premium_commission'), $fn('benefit_referral_link'), $fn('benefit_monthly_bonus'), $fn('benefit_priority_support'), $fn('benefit_dedicated_operations')]],
            ['level' => 'platinum', 'name' => $fn('tier_platinum'), 'sort_order' => 4, 'default_rate' => 30.0,
             'color' => '#0f172a', 'benefits' => [$fn('benefit_max_commission'), $fn('benefit_referral_link'), $fn('benefit_quarterly_dividend'), $fn('benefit_dedicated_support'), $fn('benefit_dedicated_operations'), $fn('benefit_brand_authorization')]],
        ];
    }

    /** @var array 等级排序映射 */
    const TIER_ORDER = ['regular' => 1, 'silver' => 2, 'gold' => 3, 'platinum' => 4];

    /**
     * 初始化默认等级定义
     */
    public function initDefaultTiers(): array
    {
        $created = [];
        foreach ($this->getDefaultTiers() as $tier) {
            $definition = AgentTierDefinition::updateOrCreate(
                ['level' => $tier['level']],
                $tier
            );
            $created[] = $definition;
        }

        // 初始化默认晋升规则
        $rules = [
            ['from_level' => 'regular', 'to_level' => 'silver', 'min_days' => 30, 'min_subscriptions' => 3,
             'min_total_amount' => 1000, 'min_referrals' => 1, 'period' => 'auto',
             'description' => '在册30天+3个订阅+累计¥1000+推荐1客户'],
            ['from_level' => 'silver', 'to_level' => 'gold', 'min_days' => 90, 'min_subscriptions' => 10,
             'min_total_amount' => 10000, 'min_referrals' => 5, 'min_monthly_amount' => 2000, 'period' => 'auto',
             'description' => '在册90天+10个订阅+累计¥10000+推荐5客户+月均¥2000'],
            ['from_level' => 'gold', 'to_level' => 'platinum', 'min_days' => 180, 'min_subscriptions' => 30,
             'min_total_amount' => 50000, 'min_referrals' => 15, 'min_monthly_amount' => 5000, 'period' => 'manual',
             'description' => '在册180天+30个订阅+累计¥50000+推荐15客户+月均¥5000（需人工审核）'],
        ];

        foreach ($rules as $rule) {
            AgentTierRule::updateOrCreate(
                ['from_level' => $rule['from_level'], 'to_level' => $rule['to_level']],
                $rule
            );
        }

        return $created;
    }

    /**
     * 获取所有等级定义
     */
    public function getTierDefinitions(): array
    {
        return AgentTierDefinition::orderBy('sort_order')->get()->toArray();
    }

    /**
     * 获取晋升规则
     */
    public function getPromotionRules(): array
    {
        return AgentTierRule::where('is_active', true)->orderBy('from_level')->get()->toArray();
    }

    /**
     * 评估代理商是否可以晋升
     *
     * @return array 包含是否可晋升、晋升目标、达标详情
     */
    public function evaluatePromotion(Agent $agent): array
    {
        $currentLevel = $agent->level;
        $tierOrder = self::TIER_ORDER;

        // 已经是最高等级
        if ($currentLevel === 'platinum') {
            return [
                'can_promote' => false,
                'current_level' => $currentLevel,
                'message' => __('app.agent_tier_service.msg_max_level'),
            ];
        }

        // 找到下个等级
        $nextLevel = null;
        foreach ($tierOrder as $level => $order) {
            if ($order > ($tierOrder[$currentLevel] ?? 0)) {
                $nextLevel = $level;
                break;
            }
        }

        if (! $nextLevel) {
            return [
                'can_promote' => false,
                'current_level' => $currentLevel,
                'message' => __('app.agent_tier_service.msg_no_promotion_target'),
            ];
        }

        // 获取晋升规则
        $rule = AgentTierRule::where('from_level', $currentLevel)
            ->where('to_level', $nextLevel)
            ->where('is_active', true)
            ->first();

        if (! $rule) {
            return [
                'can_promote' => false,
                'current_level' => $currentLevel,
                'target_level' => $nextLevel,
                'message' => __('app.agent_tier_service.msg_no_promotion_rule'),
            ];
        }

        // 检查各项条件
        $details = $this->checkPromotionConditions($agent, $rule);
        $allMet = collect($details)->every(fn($d) => $d['met']);

        return [
            'can_promote' => $allMet,
            'current_level' => $currentLevel,
            'target_level' => $nextLevel,
            'rule_type' => $rule->period,
            'details' => $details,
            'message' => $allMet ? __('app.agent_tier_service.msg_all_conditions_met') : __('app.agent_tier_service.msg_some_conditions_not_met'),
        ];
    }

    /**
     * 检查晋升条件明细
     */
    protected function checkPromotionConditions(Agent $agent, AgentTierRule $rule): array
    {
        $results = [];

        // 在册天数
        $daysSinceCreation = $agent->created_at->diffInDays(now());
        $results[] = [
            'condition' => 'minimum_days',
            'label' => __('app.agent_tier_service.label_days_registered'),
            'required' => $rule->min_days,
            'current' => $daysSinceCreation,
            'met' => $daysSinceCreation >= $rule->min_days,
        ];

        // 累计订阅数
        $subscriptionsCount = $agent->tier_subscriptions_total;
        $results[] = [
            'condition' => 'min_subscriptions',
            'label' => __('app.agent_tier_service.label_total_subscriptions'),
            'required' => $rule->min_subscriptions,
            'current' => $subscriptionsCount,
            'met' => $subscriptionsCount >= $rule->min_subscriptions,
        ];

        // 累计金额
        $totalAmount = $agent->tier_revenue_total;
        $results[] = [
            'condition' => 'min_total_amount',
            'label' => __('app.agent_tier_service.label_total_amount'),
            'required' => $rule->min_total_amount,
            'current' => $totalAmount,
            'met' => $totalAmount >= $rule->min_total_amount,
        ];

        // 推荐客户数
        $referrals = $agent->tier_referrals_total;
        $results[] = [
            'condition' => 'min_referrals',
            'label' => __('app.agent_tier_service.label_referral_count'),
            'required' => $rule->min_referrals,
            'current' => $referrals,
            'met' => $referrals >= $rule->min_referrals,
        ];

        // 单月金额
        if ($rule->min_monthly_amount > 0) {
            $monthly = $agent->tier_monthly_revenue;
            $results[] = [
                'condition' => 'min_monthly_amount',
                'label' => __('app.agent_tier_service.label_monthly_amount'),
                'required' => $rule->min_monthly_amount,
                'current' => $monthly,
                'met' => $monthly >= $rule->min_monthly_amount,
            ];
        }

        return $results;
    }

    /**
     * 自动晋升代理商
     * 对符合条件的代理商自动升级
     */
    public function autoPromoteAgents(?int $agentId = null): array
    {
        $query = Agent::where('status', 'active')
            ->where('level', '!=', 'platinum')
            ->where(function ($q) {
                $q->whereNull('tier_next_review_at')
                  ->orWhere('tier_next_review_at', '<=', now());
            });

        if ($agentId) {
            $query->where('id', $agentId);
        }

        $agents = $query->get();
        $promoted = 0;
        $results = [];

        foreach ($agents as $agent) {
            $evaluation = $this->evaluatePromotion($agent);

            if ($evaluation['can_promote'] && $evaluation['rule_type'] === 'auto') {
                $this->promoteAgent($agent, $evaluation['target_level'], 'auto');
                $promoted++;
                $results[] = [
                    'agent_id' => $agent->id,
                    'agent_code' => $agent->agent_code,
                    'from' => $agent->level,
                    'to' => $evaluation['target_level'],
                ];
            }

            // 更新下次评估时间
            $agent->updateQuietly(['tier_next_review_at' => now()->addDays(30)]);
        }

        Log::info('代理自动晋升评估完成', [
            'total_checked' => $agents->count(),
            'promoted' => $promoted,
        ]);

        return [
            'total_checked' => $agents->count(),
            'promoted' => $promoted,
            'details' => $results,
        ];
    }

    /**
     * 手动晋升代理商
     */
    public function promoteAgent(Agent $agent, string $targetLevel, string $reason = 'manual', ?int $operatorId = null, ?string $remark = null): Agent
    {
        $fromLevel = $agent->level;

        DB::transaction(function () use ($agent, $targetLevel, $fromLevel, $reason, $operatorId, $remark) {
            // 记录变更历史
            AgentTierHistory::create([
                'agent_id' => $agent->id,
                'from_level' => $fromLevel,
                'to_level' => $targetLevel,
                'reason' => $reason,
                'remark' => $remark,
                'operated_by' => $operatorId,
            ]);

            // 获取目标等级佣金率
            $tierDef = AgentTierDefinition::where('level', $targetLevel)->first();
            $defaultRate = $tierDef ? $tierDef->default_rate : match($targetLevel) {
                'silver' => 10.0,
                'gold' => 20.0,
                'platinum' => 30.0,
                default => 5.0,
            };

            // 更新代理商等级（仅当未设自定义佣金率时使用默认率）
            $updateData = [
                'level' => $targetLevel,
                'tier_last_promoted_at' => now(),
                'tier_next_review_at' => now()->addDays(30),
            ];
            if ($agent->commission_rate === null || $agent->commission_rate == 0) {
                $updateData['commission_rate'] = $defaultRate;
            }
            $agent->update($updateData);
        });

        Log::info("代理商晋升: {$fromLevel} → {$targetLevel}", [
            'agent_id' => $agent->id,
            'reason' => $reason,
        ]);

        return $agent->fresh();
    }

    /**
     * 降级代理商（降级或取消资格）
     */
    public function demoteAgent(Agent $agent, string $targetLevel, string $reason, ?int $operatorId = null, ?string $remark = null): Agent
    {
        $fromLevel = $agent->level;

        DB::transaction(function () use ($agent, $targetLevel, $fromLevel, $reason, $operatorId, $remark) {
            AgentTierHistory::create([
                'agent_id' => $agent->id,
                'from_level' => $fromLevel,
                'to_level' => $targetLevel,
                'reason' => 'demotion',
                'remark' => "{$reason}: {$remark}",
                'operated_by' => $operatorId,
            ]);

            $tierDef = AgentTierDefinition::where('level', $targetLevel)->first();

            $agent->update([
                'level' => $targetLevel,
                'commission_rate' => $tierDef ? $tierDef->default_rate : 5.0,
                'tier_last_promoted_at' => now(),
                'tier_next_review_at' => now()->addDays(30),
            ]);
        });

        return $agent->fresh();
    }

    /**
     * 更新代理商业绩统计指标
     */
    public function refreshAgentStats(Agent $agent): Agent
    {
        $settlements = CommissionSettlement::where('agent_id', $agent->id)
            ->where('status', '!=', 'cancelled');

        $totalRevenue = (clone $settlements)->sum('commission_amount');

        // 本月金额
        $monthlyRevenue = (clone $settlements)
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('commission_amount');

        // 订阅数
        $subscriptionsCount = \App\Models\SubscriptionAgent::where('agent_id', $agent->id)->count();

        // 推荐客户数 — 统计该代理关联的不同用户数
        $referralsCount = \App\Models\SubscriptionAgent::where('agent_id', $agent->id)
            ->distinct('subscription_id')
            ->count();

        $agent->updateQuietly([
            'tier_subscriptions_total' => $subscriptionsCount,
            'tier_revenue_total' => $totalRevenue,
            'tier_referrals_total' => $referralsCount,
            'tier_monthly_revenue' => $monthlyRevenue,
        ]);

        return $agent->fresh();
    }

    /**
     * 获取代理商报表数据
     */
    public function getAgentReport(Agent $agent, string $period = 'monthly'): array
    {
        $this->refreshAgentStats($agent);

        $evaluation = $this->evaluatePromotion($agent);

        // 月度趋势
        $monthlyTrend = CommissionSettlement::where('agent_id', $agent->id)
            ->where('status', '!=', 'cancelled')
            ->where('created_at', '>=', now()->subMonths(12))
            ->selectRaw(db_date_format('created_at', '%Y-%m').' as month, count(*) as total, sum(commission_amount) as amount')
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->toArray();

        // 等级变更历史
        $history = AgentTierHistory::where('agent_id', $agent->id)
            ->with('operator')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->toArray();

        return [
            'agent' => $agent->load('user'),
            'evaluation' => $evaluation,
            'stats' => [
                'available_balance' => $agent->available_balance,
                'total_earned' => $agent->total_earned,
                'total_withdrawn' => $agent->total_withdrawn,
                'tier_subscriptions_total' => $agent->tier_subscriptions_total,
                'tier_revenue_total' => $agent->tier_revenue_total,
                'tier_referrals_total' => $agent->tier_referrals_total,
                'tier_monthly_revenue' => $agent->tier_monthly_revenue,
            ],
            'monthly_trend' => $monthlyTrend,
            'history' => $history,
        ];
    }

    /**
     * 获取平台代理商总览报表
     */
    public function getPlatformOverview(): array
    {
        $tiers = AgentTierDefinition::orderBy('sort_order')->get();
        $byLevel = [];
        foreach ($tiers as $tier) {
            $agents = Agent::where('level', $tier->level)->where('status', 'active');
            $byLevel[$tier->level] = [
                'level_name' => $tier->name,
                'count' => $agents->count(),
                'total_earned' => (clone $agents)->sum('total_earned'),
                'available_balance' => (clone $agents)->sum(DB::raw('total_earned - total_withdrawn')),
            ];
        }

        $totalAgents = Agent::where('status', 'active')->count();
        $monthlySettlements = CommissionSettlement::where('status', '!=', 'cancelled')
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('commission_amount');

        $pendingPayouts = \App\Models\CommissionPayout::where('status', 'pending')->sum('amount');

        return [
            'total_agents' => $totalAgents,
            'active_agents' => Agent::where('status', 'active')->count(),
            'monthly_settlements' => $monthlySettlements,
            'pending_payouts' => $pendingPayouts,
            'total_earned_all' => Agent::sum('total_earned'),
            'total_withdrawn_all' => Agent::sum('total_withdrawn'),
            'by_level' => $byLevel,
        ];
    }
}
