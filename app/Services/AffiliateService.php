<?php

namespace App\Services;

use App\Models\AffiliateCampaign;
use App\Models\AffiliateClick;
use App\Models\AffiliateCreative;
use App\Models\AffiliateTree;
use App\Models\Agent;
use App\Models\RegistrationTracking;
use App\Models\SubscriptionAgent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 联盟推广服务 (M3-05)
 *
 * 推广活动管理、多级关系链、点击追踪、转化归因、数据看板。
 */
class AffiliateService
{
    const COMMISSION_PARENT_LEVEL1_RATE = 10; // 一级上级分成比例 10%
    const COMMISSION_PARENT_LEVEL2_RATE = 5;  // 二级上级分成比例 5%

    // ─── 推广活动管理 ───

    /**
     * 创建推广活动
     */
    public function createCampaign(array $data, int $userId): AffiliateCampaign
    {
        $campaign = AffiliateCampaign::create(array_merge($data, [
            'created_by' => $userId,
            'status' => AffiliateCampaign::STATUS_DRAFT,
        ]));

        Log::info('联盟推广活动已创建', [
            'campaign_id' => $campaign->id,
            'name' => $campaign->name,
        ]);

        return $campaign;
    }

    /**
     * 活动参与统计刷新
     */
    public function refreshCampaignStats(AffiliateCampaign $campaign): AffiliateCampaign
    {
        $participants = AffiliateClick::where('campaign_id', $campaign->id)
            ->distinct('agent_id')->count('agent_id');

        $conversions = AffiliateClick::where('campaign_id', $campaign->id)
            ->where('converted', true)->count();

        $budgetUsed = AffiliateClick::where('campaign_id', $campaign->id)
            ->where('converted', true)->sum('commission_amount');

        $campaign->updateQuietly([
            'participant_count' => $participants,
            'conversion_count' => $conversions,
            'budget_used' => $budgetUsed,
        ]);

        return $campaign->fresh();
    }

    // ─── 多级关系链 ───

    /**
     * 建立推广关系链
     * 当代理A成功推荐代理B时，建立 A→B 关系
     */
    public function buildAffiliateTree(Agent $parent, Agent $child, int $level = 1): AffiliateTree
    {
        $rate = match ($level) {
            1 => self::COMMISSION_PARENT_LEVEL1_RATE,
            2 => self::COMMISSION_PARENT_LEVEL2_RATE,
            default => 0,
        };

        $tree = AffiliateTree::updateOrCreate(
            [
                'parent_agent_id' => $parent->id,
                'child_agent_id' => $child->id,
            ],
            [
                'level' => $level,
                'rate' => $rate,
                'status' => 'active',
                'attributed_at' => now(),
            ]
        );

        // 更新父代理的子代理计数
        $parent->updateQuietly(['downline_count' => AffiliateTree::where('parent_agent_id', $parent->id)->count()]);

        // 建立链式关系：自动将 child 的 parent 记录到 grandparent
        if ($level === 1 && $parent->parent_agent_id) {
            $grandparent = Agent::find($parent->parent_agent_id);
            if ($grandparent) {
                $this->buildAffiliateTree($grandparent, $child, 2);
            }
        }

        Log::info('推广关系链建立', [
            'parent' => $parent->id,
            'child' => $child->id,
            'level' => $level,
        ]);

        return $tree;
    }

    /**
     * 获取代理的上级链
     */
    public function getUpline(Agent $agent): array
    {
        $tree = AffiliateTree::where('child_agent_id', $agent->id)
            ->with('parentAgent.user')
            ->get();

        return $tree->sortBy('level')->values()->toArray();
    }

    /**
     * 获取代理的下级链（团队树）
     */
    public function getDownlineTree(Agent $agent, int $maxDepth = 3): array
    {
        $children = Agent::where('parent_agent_id', $agent->id)
            ->with('user')
            ->get();

        $result = [];
        foreach ($children as $child) {
            $node = [
                'agent' => $child->toArray(),
                'level' => 1,
            ];
            if ($maxDepth > 1) {
                $node['children'] = $this->getDownlineRecursive($child, 2, $maxDepth);
            }
            $result[] = $node;
        }

        return $result;
    }

    protected function getDownlineRecursive(Agent $agent, int $currentDepth, int $maxDepth): array
    {
        if ($currentDepth > $maxDepth) return [];

        $children = Agent::where('parent_agent_id', $agent->id)
            ->with('user')
            ->get();

        $result = [];
        foreach ($children as $child) {
            $node = [
                'agent' => $child->toArray(),
                'level' => $currentDepth,
            ];
            $node['children'] = $this->getDownlineRecursive($child, $currentDepth + 1, $maxDepth);
            $result[] = $node;
        }

        return $result;
    }

    /**
     * 记录推广点击
     */
    public function recordClick(array $data): AffiliateClick
    {
        $click = AffiliateClick::create(array_merge($data, [
            'converted' => false,
        ]));

        // 增加素材点击计数
        if ($data['creative_id'] ?? null) {
            AffiliateCreative::where('id', $data['creative_id'])->increment('click_count');
        }

        return $click;
    }

    /**
     * 转化归因
     *
     * 将注册/订阅归属于对应的推广点击
     */
    public function attributeConversion(string $referralCode, int $convertedUserId, float $commissionAmount = 0): ?AffiliateClick
    {
        // 查找关联的点击记录
        $click = AffiliateClick::where('referral_code', $referralCode)
            ->where('converted', false)
            ->latest()
            ->first();

        if (! $click) {
            // 尝试通过注册追踪记录归因
            $tracking = RegistrationTracking::where('invite_code', $referralCode)
                ->where('converted', false)
                ->latest()
                ->first();

            if ($tracking) {
                $tracking->update([
                    'converted' => true,
                    'converted_at' => now(),
                    'conversion_type' => 'subscription',
                ]);
            }

            return null;
        }

        $click->update([
            'converted' => true,
            'converted_at' => now(),
            'converted_user_id' => $convertedUserId,
            'commission_amount' => $commissionAmount,
        ]);

        // 增加素材转化计数
        if ($click->creative_id) {
            AffiliateCreative::where('id', $click->creative_id)->increment('conversion_count');
        }

        // 刷新活动统计
        if ($click->campaign_id) {
            $campaign = AffiliateCampaign::find($click->campaign_id);
            if ($campaign) {
                $this->refreshCampaignStats($campaign);
            }
        }

        // 多级分成计算
        if ($click->agent_id && $commissionAmount > 0) {
            $this->distributeMultiLevelCommission($click->agent_id, $commissionAmount);
        }

        return $click->fresh();
    }

    /**
     * 多级佣金分成
     */
    public function distributeMultiLevelCommission(int $agentId, float $commissionAmount): void
    {
        $treeRelations = AffiliateTree::where('child_agent_id', $agentId)
            ->where('status', 'active')
            ->get();

        foreach ($treeRelations as $relation) {
            $rate = $relation->rate;
            if ($rate <= 0) continue;

            $shareAmount = $commissionAmount * ($rate / 100);
            $parent = Agent::find($relation->parent_agent_id);
            if ($parent) {
                $parent->increment('total_earned', $shareAmount);
                $parent->increment('downline_earnings', $shareAmount);
            }
        }
    }

    /**
     * 获取推广数据看板
     */
    public function getDashboard(): array
    {
        $totalClicks = AffiliateClick::count();
        $totalConversions = AffiliateClick::where('converted', true)->count();
        $totalCommission = AffiliateClick::where('converted', true)->sum('commission_amount');

        $activeCampaigns = AffiliateCampaign::where('status', AffiliateCampaign::STATUS_ACTIVE)->count();

        // 月度趋势
        $monthlyClicks = AffiliateClick::selectRaw("strftime('%Y-%m', created_at) as month, count(*) as clicks, sum(case when converted then 1 else 0 end) as conversions, coalesce(sum(commission_amount), 0) as commission")
            ->where('created_at', '>=', now()->subMonths(12))
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->toArray();

        // 按活动统计
        $campaignStats = AffiliateCampaign::selectRaw('id, name, status, conversion_count, participant_count, budget_total, budget_used')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->toArray();

        // 按代理统计
        $topAgents = AffiliateClick::selectRaw('agent_id, count(*) as clicks, sum(case when converted then 1 else 0 end) as conversions, coalesce(sum(commission_amount), 0) as commission')
            ->whereNotNull('agent_id')
            ->groupBy('agent_id')
            ->orderByDesc('clicks')
            ->limit(10)
            ->get()
            ->toArray();

        return [
            'overview' => [
                'total_clicks' => $totalClicks,
                'total_conversions' => $totalConversions,
                'conversion_rate' => $totalClicks > 0 ? round($totalConversions / $totalClicks * 100, 2) : 0,
                'total_commission' => $totalCommission,
                'active_campaigns' => $activeCampaigns,
            ],
            'monthly_trend' => $monthlyClicks,
            'campaigns' => $campaignStats,
            'top_agents' => $topAgents,
        ];
    }

    /**
     * 代理商推广总结
     */
    public function getAgentAffiliateSummary(Agent $agent): array
    {
        $clicks = AffiliateClick::where('agent_id', $agent->id);
        $converted = (clone $clicks)->where('converted', true);

        $downline = Agent::where('parent_agent_id', $agent->id)->count();
        $upline = $this->getUpline($agent);

        $monthlyClicks = (clone $clicks)
            ->selectRaw("strftime('%Y-%m', created_at) as month, count(*) as clicks, sum(case when converted then 1 else 0 end) as conversions, coalesce(sum(commission_amount), 0) as commission")
            ->where('created_at', '>=', now()->subMonths(12))
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->toArray();

        return [
            'clicks_total' => $clicks->count(),
            'conversions_total' => $converted->count(),
            'conversion_rate' => $clicks->count() > 0 ? round($converted->count() / $clicks->count() * 100, 2) : 0,
            'commission_total' => $converted->sum('commission_amount'),
            'downline_count' => $downline,
            'upline' => $upline,
            'monthly_trend' => $monthlyClicks,
        ];
    }

    /**
     * 获取推广素材转化统计
     */
    public function getCreativeStats(int $campaignId): array
    {
        return AffiliateCreative::where('campaign_id', $campaignId)
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'type' => $c->type,
                'clicks' => $c->click_count,
                'conversions' => $c->conversion_count,
                'conversion_rate' => $c->click_count > 0 ? round($c->conversion_count / $c->click_count * 100, 2) : 0,
                'is_active' => $c->is_active,
            ])
            ->toArray();
    }
}
