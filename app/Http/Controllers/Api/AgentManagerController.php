<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Services\AgentManagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 代理商/经销商管理控制器 (M3-04)
 *
 * 等级/分成比例/业绩报表/收益账户关联+佣金结算+提现入口
 */
class AgentManagerController extends Controller
{
    public function __construct(
        protected AgentManagerService $agentManager,
    ) {}

    /**
     * 仪表盘
     *
     * GET /api/agent-manager/dashboard
     */
    public function dashboard(): JsonResponse
    {
        return ApiResponse::success($this->agentManager->getDashboard());
    }

    /**
     * 代理列表
     *
     * GET /api/agent-manager/agents
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'level', 'search']);
        return ApiResponse::success($this->agentManager->listAgents($filters));
    }

    /**
     * 创建代理
     *
     * POST /api/agent-manager/agents
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'level' => 'required|in:regular,silver,gold,platinum',
            'commission_rate' => 'numeric|min:0|max:100',
            'contact_name' => 'nullable|string|max:100',
            'contact_phone' => 'nullable|string|max:30',
            'company' => 'nullable|string|max:200',
            'notes' => 'nullable|string|max:1000',
            'parent_agent_id' => 'nullable|exists:agents,id',
            'multi_level_rate' => 'numeric|min:0|max:100',
        ]);

        $agent = $this->agentManager->createAgent($validated);
        return ApiResponse::success($agent->load('user'), '代理创建成功', 201);
    }

    /**
     * 代理详情
     *
     * GET /api/agent-manager/agents/{agent}
     */
    public function show(Agent $agent): JsonResponse
    {
        return ApiResponse::success($this->agentManager->getAgentDetail($agent->id));
    }

    /**
     * 更新代理
     *
     * PUT /api/agent-manager/agents/{agent}
     */
    public function update(Request $request, Agent $agent): JsonResponse
    {
        $validated = $request->validate([
            'level' => 'in:regular,silver,gold,platinum',
            'commission_rate' => 'numeric|min:0|max:100',
            'status' => 'in:pending,active,suspended,terminated',
            'contact_name' => 'nullable|string|max:100',
            'contact_phone' => 'nullable|string|max:30',
            'company' => 'nullable|string|max:200',
            'notes' => 'nullable|string|max:1000',
            'multi_level_rate' => 'numeric|min:0|max:100',
        ]);

        $agent = $this->agentManager->updateAgent($agent->id, $validated);
        return ApiResponse::success($agent->load('user'), '代理已更新');
    }

    /**
     * 审核通过
     *
     * POST /api/agent-manager/agents/{agent}/approve
     */
    public function approve(Agent $agent): JsonResponse
    {
        $agent = $this->agentManager->approveAgent($agent->id);
        return ApiResponse::success($agent->load('user'), '代理已审核通过');
    }

    /**
     * 业绩报表
     *
     * GET /api/agent-manager/agents/{agent}/performance
     */
    public function performance(Request $request, Agent $agent): JsonResponse
    {
        $period = $request->get('period', 'monthly');
        return ApiResponse::success($this->agentManager->getPerformanceReport($agent->id, $period));
    }

    /**
     * 排行榜
     *
     * GET /api/agent-manager/leaderboard
     */
    public function leaderboard(Request $request): JsonResponse
    {
        $metric = $request->get('metric', 'total_earned');
        $limit = (int) $request->get('limit', 20);
        return ApiResponse::success($this->agentManager->getLeaderboard($metric, $limit));
    }
}
