<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\CommissionPlan;
use App\Models\CommissionPlanItem;
use App\Models\CommissionSettlement;
use App\Models\CommissionPayout;
use App\Models\ReferralLink;
use App\Models\SubscriptionAgent;
use App\Models\User;
use App\Services\CommissionEngineService;
use App\Services\EarningsNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * 佣金/分销管理 API
 *
 * M2-127 佣金结算引擎 — 代理/计划/结算/提现
 */
class CommissionController extends Controller
{
    public function __construct(
        protected CommissionEngineService $engine,
        protected EarningsNotifier $earningsNotifier,
    ) {}

    // ──────────────── 代理管理 ────────────────

    public function agents(Request $request): JsonResponse
    {
        $query = Agent::with('user:id,name,email')->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('level')) {
            $query->where('level', $request->input('level'));
        }
        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(function ($q) use ($s) {
                $q->where('agent_code', 'like', "%{$s}%")
                  ->orWhere('contact_name', 'like', "%{$s}%")
                  ->orWhere('company', 'like', "%{$s}%");
            });
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate($request->input('per_page', 20)),
        ]);
    }

    public function showAgent(Agent $agent): JsonResponse
    {
        $agent->load('user:id,name,email');
        $agent->stats = $this->engine->getAgentStats($agent);

        return response()->json(['success' => true, 'data' => $agent]);
    }

    public function storeAgent(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'level' => 'required|in:regular,silver,gold,platinum',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'contact_name' => 'nullable|string|max:100',
            'contact_phone' => 'nullable|string|max:20',
            'company' => 'nullable|string|max:200',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $user = User::findOrFail($request->input('user_id'));

        if (Agent::where('user_id', $user->id)->exists()) {
            return response()->json(['success' => false, 'message' => __('app.api.commission.already_agent')], 422);
        }

        $agent = $this->engine->registerAgent($user, $validator->validated());

        return response()->json([
            'success' => true,
            'message' => __('app.api.commission.agent_created'),
            'data' => $agent->load('user:id,name,email'),
        ], 201);
    }

    public function updateAgent(Request $request, Agent $agent): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'level' => 'in:regular,silver,gold,platinum',
            'status' => 'in:active,suspended,terminated',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'contact_name' => 'nullable|string|max:100',
            'contact_phone' => 'nullable|string|max:20',
            'company' => 'nullable|string|max:200',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $agent->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => __('app.api.commission.agent_updated'),
            'data' => $agent->fresh()->load('user:id,name,email'),
        ]);
    }

    // ──────────────── 佣金计划 ────────────────

    public function plans(Request $request): JsonResponse
    {
        $query = CommissionPlan::with('items.product:id,name')->orderBy('created_at', 'desc');

        return response()->json([
            'success' => true,
            'data' => $query->paginate($request->input('per_page', 20)),
        ]);
    }

    public function storePlan(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'slug' => 'required|string|max:100|unique:commission_plans,slug',
            'description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $plan = CommissionPlan::create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => __('app.api.commission.plan_created'),
            'data' => $plan,
        ], 201);
    }

    public function updatePlan(Request $request, CommissionPlan $commissionPlan): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'string|max:100',
            'is_active' => 'boolean',
            'description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $commissionPlan->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => __('app.api.commission.plan_updated'),
            'data' => $commissionPlan->fresh()->load('items'),
        ]);
    }

    // ──────────────── 计划明细 ────────────────

    public function planItems(CommissionPlan $commissionPlan): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $commissionPlan->items()->with('product:id,name')->get(),
        ]);
    }

    public function storePlanItem(Request $request, CommissionPlan $commissionPlan): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'nullable|exists:products,id',
            'product_category' => 'nullable|string|max:50',
            'agent_level' => 'required|string|max:30',
            'commission_rate' => 'required|numeric|min:0|max:100',
            'rate_type' => 'in:percentage,fixed',
            'fixed_amount' => 'nullable|numeric|min:0',
            'priority' => 'integer|min:0|max:99',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        if (! $request->input('product_id') && ! $request->input('product_category')) {
            return response()->json([
                'success' => false, 'message' => __('app.api.commission.specify_product'),
            ], 422);
        }

        $item = $commissionPlan->items()->create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => __('app.api.commission.plan_detail_created'),
            'data' => $item->load('product:id,name'),
        ], 201);
    }

    public function updatePlanItem(Request $request, CommissionPlanItem $commissionPlanItem): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'commission_rate' => 'numeric|min:0|max:100',
            'rate_type' => 'in:percentage,fixed',
            'fixed_amount' => 'nullable|numeric|min:0',
            'agent_level' => 'string|max:30',
            'priority' => 'integer|min:0|max:99',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $commissionPlanItem->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => __('app.api.commission.plan_detail_updated'),
            'data' => $commissionPlanItem->fresh(),
        ]);
    }

    public function destroyPlanItem(CommissionPlanItem $commissionPlanItem): JsonResponse
    {
        $commissionPlanItem->delete();
        return response()->json(['success' => true, 'message' => __('app.api.commission.detail_deleted')]);
    }

    // ──────────────── 结算管理 ────────────────

    public function settlements(Request $request): JsonResponse
    {
        $query = CommissionSettlement::with([
            'agent:id,agent_code,user_id',
            'agent.user:id,name,email',
            'subscription:id,license_key,product_id',
            'invoice:id,invoice_no,total',
        ])->orderBy('created_at', 'desc');

        if ($request->filled('agent_id')) {
            $query->where('agent_id', $request->input('agent_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('period')) {
            $query->where('period', $request->input('period'));
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate($request->input('per_page', 20)),
        ]);
    }

    // ──────────────── 提现管理 ────────────────

    public function payouts(Request $request): JsonResponse
    {
        $query = CommissionPayout::with('agent.user:id,name,email')
            ->orderBy('created_at', 'desc');

        if ($request->filled('agent_id')) {
            $query->where('agent_id', $request->input('agent_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate($request->input('per_page', 20)),
        ]);
    }

    public function processPayout(Request $request, CommissionPayout $commissionPayout): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:processing,completed,failed,cancelled',
            'transaction_id' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        if ($data['status'] === 'completed') {
            $data['processed_at'] = now();
        }

        if ($data['status'] === 'cancelled') {
            // 取消提现，退还余额
            $agent = $commissionPayout->agent;
            $agent->increment('total_earned', $commissionPayout->amount);
            $agent->decrement('total_withdrawn', $commissionPayout->amount);
            $agent->user?->increment('commission_balance', $commissionPayout->amount);
        }

        $commissionPayout->update($data);

        // ⭐ M2-128 发送提现状态变更通知
        try {
            $agent = $commissionPayout->agent;
            if ($agent && $agent->user) {
                $oldStatus = $commissionPayout->getOriginal('status') ?: 'pending';
                $this->earningsNotifier->notifyPayoutStatusChanged(
                    agent: $agent,
                    payout: $commissionPayout->fresh(),
                    oldStatus: $oldStatus,
                    newStatus: $data['status'],
                );
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('提现状态通知发送失败', [
                'payout_id' => $commissionPayout->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => __('app.api.commission.withdrawal_updated'),
            'data' => $commissionPayout->fresh(),
        ]);
    }

    // ──────────────── 推广链接 ────────────────

    public function referralLinks(Request $request): JsonResponse
    {
        $query = ReferralLink::with('agent.user:id,name,email')
            ->orderBy('created_at', 'desc');

        if ($request->filled('agent_id')) {
            $query->where('agent_id', $request->input('agent_id'));
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate($request->input('per_page', 20)),
        ]);
    }

    public function storeReferralLink(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'agent_id' => 'required|exists:agents,id',
            'name' => 'nullable|string|max:100',
            'target_url' => 'nullable|string|max:500',
            'utm_source' => 'nullable|string|max:100',
            'utm_medium' => 'nullable|string|max:100',
            'utm_campaign' => 'nullable|string|max:100',
            'expires_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $code = $this->generateReferralCode();
        $data = $validator->validated();
        $data['code'] = $code;

        $link = ReferralLink::create($data);

        return response()->json([
            'success' => true,
            'message' => __('app.api.commission.link_created'),
            'data' => $link,
        ], 201);
    }

    public function destroyReferralLink(ReferralLink $referralLink): JsonResponse
    {
        $referralLink->update(['is_active' => false]);
        return response()->json(['success' => true, 'message' => __('app.api.commission.link_deactivated')]);
    }

    // ──────────────── 统计 ────────────────

    public function dashboard(Request $request): JsonResponse
    {
        $totalAgents = Agent::count();
        $activeAgents = Agent::where('status', 'active')->count();
        $totalSettled = CommissionSettlement::sum('commission_amount');
        $totalPaid = CommissionPayout::where('status', 'completed')->sum('net_amount');
        $pendingSettlements = CommissionSettlement::whereIn('status', ['pending', 'pending_release'])
            ->sum('commission_amount');
        $monthlySettled = CommissionSettlement::where('period', now()->format('Y-m'))
            ->sum('commission_amount');

        // 待处理提现
        $pendingPayouts = CommissionPayout::where('status', 'pending')->count();
        $pendingPayoutAmount = CommissionPayout::where('status', 'pending')->sum('amount');

        // 月度趋势
        $monthlyTrend = CommissionSettlement::selectRaw(
            "period, SUM(commission_amount) as amount, COUNT(*) as count"
        )
            ->where('period', '>=', now()->subMonths(12)->format('Y-m'))
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_agents' => $totalAgents,
                'active_agents' => $activeAgents,
                'total_settled' => round($totalSettled, 2),
                'total_paid' => round($totalPaid, 2),
                'pending_settlements' => round($pendingSettlements, 2),
                'monthly_settled' => round($monthlySettled, 2),
                'pending_payouts' => $pendingPayouts,
                'pending_payout_amount' => round($pendingPayoutAmount, 2),
                'monthly_trend' => $monthlyTrend,
            ],
        ]);
    }

    // ──────────────── 公共/代理端 ────────────────

    /**
     * 代理查看自己的佣金概览
     */
    public function myCommission(Request $request): JsonResponse
    {
        $user = $request->user();
        $agent = Agent::where('user_id', $user->id)->first();

        if (! $agent) {
            return response()->json(['success' => false, 'message' => __('app.api.commission.not_agent')], 403);
        }

        $stats = $this->engine->getAgentStats($agent);
        $recentSettlements = CommissionSettlement::where('agent_id', $agent->id)
            ->with('subscription:id,license_key')
            ->latest()
            ->limit(20)
            ->get();
        $payouts = CommissionPayout::where('agent_id', $agent->id)
            ->latest()
            ->limit(10)
            ->get();
        $links = ReferralLink::where('agent_id', $agent->id)
            ->where('is_active', true)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'agent' => $agent,
                'stats' => $stats,
                'recent_settlements' => $recentSettlements,
                'payouts' => $payouts,
                'referral_links' => $links,
            ],
        ]);
    }

    /**
     * 代理发起提现
     */
    public function requestPayout(Request $request): JsonResponse
    {
        $user = $request->user();
        $agent = Agent::where('user_id', $user->id)->first();

        if (! $agent) {
            return response()->json(['success' => false, 'message' => __('app.api.commission.not_agent')], 403);
        }

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:100',
            'payout_method' => 'required|in:bank_transfer,alipay,wechat,balance',
            'account_info' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $payout = $this->engine->requestPayout(
                $agent,
                $request->input('amount'),
                $request->input('payout_method'),
                $request->input('account_info'),
            );

            return response()->json([
                'success' => true,
                'message' => __('app.api.commission.withdrawal_submitted'),
                'data' => $payout,
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    protected function generateReferralCode(): string
    {
        do {
            $code = strtoupper(\Illuminate\Support\Str::random(8));
        } while (ReferralLink::where('code', $code)->exists());

        return $code;
    }
}
