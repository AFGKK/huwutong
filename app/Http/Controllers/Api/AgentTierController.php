<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentTierDefinition;
use App\Models\AgentTierHistory;
use App\Models\AgentTierRule;
use App\Services\AgentTierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 代理商等级管理与报表控制器 (M3-04)
 */
class AgentTierController extends Controller
{
    public function __construct(
        protected AgentTierService $tierService,
    ) {}

    // ─── 等级定义 ───

    /**
     * 获取等级定义列表
     *
     * GET /api/agent-tiers
     */
    public function tierDefinitions(): JsonResponse
    {
        return ApiResponse::success($this->tierService->getTierDefinitions());
    }

    /**
     * 初始化默认等级
     *
     * POST /api/agent-tiers/init
     */
    public function initTiers(): JsonResponse
    {
        $result = $this->tierService->initDefaultTiers();
        return ApiResponse::success($result, __("app.agent_tier.msg_1078f008"));
    }

    /**
     * 更新等级定义
     *
     * PUT /api/agent-tiers/{tierDefinition}
     */
    public function updateTier(Request $request, AgentTierDefinition $tierDefinition): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'string|max:50',
            'default_rate' => 'numeric|min:0|max:100',
            'benefits' => 'nullable|array',
            'benefits.*' => 'string',
            'color' => 'nullable|string|max:20',
            'icon' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ]);

        $tierDefinition->update($validated);
        return ApiResponse::success($tierDefinition->fresh(), __('app.agent_tier.tier_updated'));
    }

    // ─── 晋升规则 ───

    /**
     * 获取晋升规则
     *
     * GET /api/agent-tiers/rules
     */
    public function promotionRules(): JsonResponse
    {
        return ApiResponse::success($this->tierService->getPromotionRules());
    }

    /**
     * 更新晋升规则
     *
     * PUT /api/agent-tiers/rules/{rule}
     */
    public function updateRule(Request $request, AgentTierRule $rule): JsonResponse
    {
        $validated = $request->validate([
            'min_days' => 'integer|min:0',
            'min_subscriptions' => 'integer|min:0',
            'min_total_amount' => 'numeric|min:0',
            'min_referrals' => 'integer|min:0',
            'min_monthly_amount' => 'numeric|min:0',
            'period' => 'in:auto,manual',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ]);

        $rule->update($validated);
        return ApiResponse::success($rule->fresh(), __('app.agent_tier.rule_updated'));
    }

    // ─── 代理商等级管理 ───

    /**
     * 评估单个代理商的晋升可能性
     *
     * GET /api/agent-tiers/agents/{agent}/evaluate
     */
    public function evaluateAgent(Agent $agent): JsonResponse
    {
        $result = $this->tierService->evaluatePromotion($agent);
        $result['agent'] = $agent->load('user');
        return ApiResponse::success($result);
    }

    /**
     * 晋升代理商
     *
     * POST /api/agent-tiers/agents/{agent}/promote
     */
    public function promoteAgent(Request $request, Agent $agent): JsonResponse
    {
        $validated = $request->validate([
            'target_level' => 'required|string|in:silver,gold,platinum',
            'reason' => 'nullable|string|max:50',
            'remark' => 'nullable|string|max:500',
        ]);

        $currentOrder = AgentTierService::TIER_ORDER[$agent->level] ?? 0;
        $targetOrder = AgentTierService::TIER_ORDER[$validated['target_level']] ?? 0;

        if ($targetOrder <= $currentOrder) {
            return ApiResponse::error('INVALID_PROMOTION', __("app.agent_tier.msg_7df32767"), 400);
        }

        $result = $this->tierService->promoteAgent(
            $agent,
            $validated['target_level'],
            $validated['reason'] ?? 'manual',
            $request->user()->id,
            $validated['remark'] ?? null
        );

        return ApiResponse::success($result, __("app.agent_tier.msg_c7e43c17"));
    }

    /**
     * 降级代理商
     *
     * POST /api/agent-tiers/agents/{agent}/demote
     */
    public function demoteAgent(Request $request, Agent $agent): JsonResponse
    {
        $validated = $request->validate([
            'target_level' => 'required|string|in:regular,silver,gold',
            'reason' => 'required|string|max:500',
        ]);

        $currentOrder = AgentTierService::TIER_ORDER[$agent->level] ?? 0;
        $targetOrder = AgentTierService::TIER_ORDER[$validated['target_level']] ?? 0;

        if ($targetOrder >= $currentOrder) {
            return ApiResponse::error('INVALID_DEMOTION', __("app.agent_tier.msg_ba117a29"), 400);
        }

        $result = $this->tierService->demoteAgent(
            $agent,
            $validated['target_level'],
            $validated['reason'],
            $request->user()->id,
            $validated['reason']
        );

        return ApiResponse::success($result, __("app.agent_tier.msg_c10502e5"));
    }

    /**
     * 自动晋升评估（批量）
     *
     * POST /api/agent-tiers/auto-promote
     */
    public function autoPromote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'agent_id' => 'nullable|integer|exists:agents,id',
        ]);

        $result = $this->tierService->autoPromoteAgents($validated['agent_id'] ?? null);
        return ApiResponse::success($result, __("app.agent_tier.msg_03b745a3"));
    }

    /**
     * 获取等级变更历史
     *
     * GET /api/agent-tiers/history
     */
    public function history(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'agent_id' => 'nullable|integer|exists:agents,id',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = AgentTierHistory::with(['agent.user', 'operator']);
        if (! empty($validated['agent_id'])) {
            $query->where('agent_id', $validated['agent_id']);
        }

        $history = $query->orderBy('created_at', 'desc')
            ->paginate($validated['per_page'] ?? 20);

        return ApiResponse::success($history);
    }

    // ─── 报表 ───

    /**
     * 单个代理商报表
     *
     * GET /api/agent-tiers/agents/{agent}/report
     */
    public function agentReport(Agent $agent): JsonResponse
    {
        $result = $this->tierService->getAgentReport($agent);
        return ApiResponse::success($result);
    }

    /**
     * 平台代理商总览报表
     *
     * GET /api/agent-tiers/overview
     */
    public function platformOverview(): JsonResponse
    {
        return ApiResponse::success($this->tierService->getPlatformOverview());
    }
}
