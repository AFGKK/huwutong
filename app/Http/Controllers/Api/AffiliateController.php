<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AffiliateCampaign;
use App\Models\AffiliateClick;
use App\Models\AffiliateCreative;
use App\Models\AffiliateTree;
use App\Models\Agent;
use App\Services\AffiliateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 联盟推广控制器 (M3-05)
 */
class AffiliateController extends Controller
{
    public function __construct(
        protected AffiliateService $affiliateService,
    ) {}

    // ─── 推广活动管理 ───

    /**
     * 活动列表
     *
     * GET /api/affiliate/campaigns
     */
    public function campaigns(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'nullable|in:draft,active,paused,completed',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = AffiliateCampaign::with('creator');

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $campaigns = $query->orderBy('created_at', 'desc')
            ->paginate($validated['per_page'] ?? 20);

        $campaigns->getCollection()->transform(fn($c) => $this->formatCampaign($c));

        return ApiResponse::success($campaigns);
    }

    /**
     * 创建活动
     *
     * POST /api/affiliate/campaigns
     */
    public function storeCampaign(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'slug' => 'required|string|max:100|unique:affiliate_campaigns,slug',
            'description' => 'nullable|string',
            'type' => 'required|in:referral,commission,reward,rebate',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'reward_first' => 'nullable|numeric|min:0',
            'reward_renewal' => 'nullable|numeric|min:0',
            'reward_upgrade' => 'nullable|numeric|min:0',
            'budget_total' => 'nullable|numeric|min:0',
            'max_participants' => 'nullable|integer|min:0',
            'target_audience' => 'nullable|array',
            'target_audience.*' => 'string',
            'terms' => 'nullable|array',
            'terms.*' => 'string',
        ]);

        $campaign = $this->affiliateService->createCampaign($validated, $request->user()->id);

        return ApiResponse::success($this->formatCampaign($campaign), '推广活动已创建');
    }

    /**
     * 活动详情
     *
     * GET /api/affiliate/campaigns/{campaign}
     */
    public function showCampaign(AffiliateCampaign $campaign): JsonResponse
    {
        $campaign->load(['creator', 'creatives']);
        return ApiResponse::success($this->formatCampaign($campaign));
    }

    /**
     * 更新活动
     *
     * PUT /api/affiliate/campaigns/{campaign}
     */
    public function updateCampaign(Request $request, AffiliateCampaign $campaign): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'string|max:100',
            'description' => 'nullable|string',
            'status' => 'in:draft,active,paused,completed',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'reward_first' => 'nullable|numeric|min:0',
            'reward_renewal' => 'nullable|numeric|min:0',
            'reward_upgrade' => 'nullable|numeric|min:0',
            'budget_total' => 'nullable|numeric|min:0',
            'max_participants' => 'nullable|integer|min:0',
            'target_audience' => 'nullable|array',
            'target_audience.*' => 'string',
            'terms' => 'nullable|array',
            'terms.*' => 'string',
        ]);

        $campaign->update($validated);
        return ApiResponse::success($this->formatCampaign($campaign->fresh()), '活动已更新');
    }

    /**
     * 刷新活动统计
     *
     * POST /api/affiliate/campaigns/{campaign}/refresh
     */
    public function refreshCampaign(AffiliateCampaign $campaign): JsonResponse
    {
        $result = $this->affiliateService->refreshCampaignStats($campaign);
        return ApiResponse::success($this->formatCampaign($result), '统计已刷新');
    }

    // ─── 推广素材管理 ───

    /**
     * 素材列表
     *
     * GET /api/affiliate/campaigns/{campaign}/creatives
     */
    public function creatives(AffiliateCampaign $campaign): JsonResponse
    {
        return ApiResponse::success($campaign->creatives);
    }

    /**
     * 创建素材
     *
     * POST /api/affiliate/campaigns/{campaign}/creatives
     */
    public function storeCreative(Request $request, AffiliateCampaign $campaign): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:banner,landing_page,link,coupon,qr_code',
            'name' => 'required|string|max:100',
            'url' => 'nullable|string|max:500',
            'content' => 'nullable|string',
            'image_url' => 'nullable|string|max:500',
            'utm_params' => 'nullable|array',
        ]);

        $validated['campaign_id'] = $campaign->id;

        $creative = AffiliateCreative::create($validated);

        return ApiResponse::success($creative, '推广素材已创建');
    }

    /**
     * 素材转化统计
     *
     * GET /api/affiliate/campaigns/{campaign}/creative-stats
     */
    public function creativeStats(AffiliateCampaign $campaign): JsonResponse
    {
        return ApiResponse::success($this->affiliateService->getCreativeStats($campaign->id));
    }

    // ─── 多级关系链 ───

    /**
     * 建立推广关系（A推荐B）
     *
     * POST /api/affiliate/tree
     */
    public function buildTree(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'parent_agent_id' => 'required|integer|exists:agents,id',
            'child_agent_id' => 'required|integer|exists:agents,id|different:parent_agent_id',
        ]);

        $parent = Agent::findOrFail($validated['parent_agent_id']);
        $child = Agent::findOrFail($validated['child_agent_id']);

        // 检查是否已有关系
        $existing = AffiliateTree::where('parent_agent_id', $parent->id)
            ->where('child_agent_id', $child->id)
            ->exists();

        if ($existing) {
            return ApiResponse::error('ALREADY_EXISTS', '该关系已存在', 400);
        }

        $tree = $this->affiliateService->buildAffiliateTree($parent, $child);
        $child->update(['parent_agent_id' => $parent->id, 'referral_source' => 'affiliate']);

        return ApiResponse::success($tree, '推广关系已建立');
    }

    /**
     * 查看代理的上线链
     *
     * GET /api/affiliate/agents/{agent}/upline
     */
    public function upline(Agent $agent): JsonResponse
    {
        return ApiResponse::success($this->affiliateService->getUpline($agent));
    }

    /**
     * 查看代理的下级团队树
     *
     * GET /api/affiliate/agents/{agent}/downline
     */
    public function downline(Agent $agent, Request $request): JsonResponse
    {
        $maxDepth = min((int) $request->input('max_depth', 3), 5);
        return ApiResponse::success($this->affiliateService->getDownlineTree($agent, $maxDepth));
    }

    // ─── 点击/转化追踪 ───

    /**
     * 记录推广点击
     *
     * POST /api/affiliate/clicks
     */
    public function recordClick(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'agent_id' => 'nullable|integer|exists:agents,id',
            'campaign_id' => 'nullable|integer|exists:affiliate_campaigns,id',
            'creative_id' => 'nullable|integer|exists:affiliate_creatives,id',
            'referral_code' => 'nullable|string|max:50',
            'referrer_url' => 'nullable|string|max:500',
            'landing_url' => 'nullable|string|max:500',
            'utm_params' => 'nullable|array',
        ]);

        $validated['ip_address'] = $request->ip();
        $validated['user_agent'] = $request->userAgent();

        $click = $this->affiliateService->recordClick($validated);

        return ApiResponse::success($click, '点击已记录');
    }

    /**
     * 转化归因
     *
     * POST /api/affiliate/attribute
     */
    public function attributeConversion(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'referral_code' => 'required|string|max:50',
            'converted_user_id' => 'required|integer|exists:users,id',
            'commission_amount' => 'nullable|numeric|min:0',
        ]);

        $result = $this->affiliateService->attributeConversion(
            $validated['referral_code'],
            $validated['converted_user_id'],
            $validated['commission_amount'] ?? 0
        );

        return ApiResponse::success([
            'attributed' => ! is_null($result),
            'click' => $result,
        ], $result ? '转化已归因' : '未找到匹配点击');
    }

    // ─── 数据看板 ───

    /**
     * 平台推广总看板
     *
     * GET /api/affiliate/dashboard
     */
    public function dashboard(): JsonResponse
    {
        return ApiResponse::success($this->affiliateService->getDashboard());
    }

    /**
     * 代理推广总结
     *
     * GET /api/affiliate/agents/{agent}/summary
     */
    public function agentSummary(Agent $agent): JsonResponse
    {
        return ApiResponse::success($this->affiliateService->getAgentAffiliateSummary($agent));
    }

    /**
     * 推广点击日志
     *
     * GET /api/affiliate/clicks
     */
    public function clickLogs(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'agent_id' => 'nullable|integer|exists:agents,id',
            'campaign_id' => 'nullable|integer|exists:affiliate_campaigns,id',
            'converted' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = AffiliateClick::with(['agent.user', 'campaign', 'creative']);

        if (! empty($validated['agent_id'])) $query->where('agent_id', $validated['agent_id']);
        if (! empty($validated['campaign_id'])) $query->where('campaign_id', $validated['campaign_id']);
        if (isset($validated['converted'])) $query->where('converted', $validated['converted']);

        $logs = $query->orderBy('created_at', 'desc')
            ->paginate($validated['per_page'] ?? 20);

        return ApiResponse::success($logs);
    }

    /**
     * 格式化活动数据
     */
    protected function formatCampaign(AffiliateCampaign $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'slug' => $c->slug,
            'description' => $c->description,
            'status' => $c->status,
            'type' => $c->type,
            'is_active' => $c->isActive(),
            'starts_at' => $c->starts_at?->toIso8601String(),
            'ends_at' => $c->ends_at?->toIso8601String(),
            'reward_first' => $c->reward_first,
            'reward_renewal' => $c->reward_renewal,
            'reward_upgrade' => $c->reward_upgrade,
            'budget_total' => $c->budget_total,
            'budget_used' => $c->budget_used,
            'budget_remaining' => $c->budget_total - $c->budget_used,
            'max_participants' => $c->max_participants,
            'participant_count' => $c->participant_count,
            'conversion_count' => $c->conversion_count,
            'target_audience' => $c->target_audience,
            'terms' => $c->terms,
            'creator_name' => $c->creator?->name,
            'creatives_count' => $c->creatives?->count() ?? 0,
            'created_at' => $c->created_at->toIso8601String(),
        ];
    }
}
