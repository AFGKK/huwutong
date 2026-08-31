<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\CommissionPayout;
use App\Models\CommissionSettlement;
use App\Models\ReferralLink;
use App\Services\CommissionEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/**
 * 渠道合作伙伴门户
 *
 * 管理端 API — 合作伙伴概览、业绩分析、等级管理
 */
class ChannelPartnerController extends Controller
{
    public function __construct(
        protected CommissionEngineService $engine,
    ) {}

    protected function ensureAdmin(): void
    {
        if (Gate::denies('admin')) {
            abort(403, __('app.api.partner.admin_required'));
        }
    }

    /**
     * 合作伙伴概览看板
     */
    public function dashboard(Request $request): JsonResponse
    {
        $this->ensureAdmin();

        $totalPartners = Agent::count();
        $activePartners = Agent::where('status', 'active')->count();
        $pendingApproval = Agent::where('status', 'pending')->count();

        // 等级分布
        $levelDistribution = Agent::selectRaw('level, count(*) as count')
            ->groupBy('level')
            ->pluck('count', 'level');

        // 月度趋势
        $monthlyTrend = CommissionSettlement::selectRaw(
            "period, SUM(commission_amount) as amount, COUNT(*) as count"
        )
            ->where('period', '>=', now()->subMonths(12)->format('Y-m'))
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        // 本月TOP合作伙伴
        $topPartners = Agent::with('user:id,name,email')
            ->whereHas('settlements', function ($q) {
                $q->where('period', now()->format('Y-m'));
            })
            ->withSum(['settlements as monthly_amount' => fn($q) => $q->where('period', now()->format('Y-m'))], 'commission_amount')
            ->orderByDesc('monthly_amount')
            ->limit(10)
            ->get()
            ->map(fn($a) => [
                'id' => $a->id,
                'agent_code' => $a->agent_code,
                'name' => $a->user?->name ?? 'N/A',
                'email' => $a->user?->email ?? '',
                'level' => $a->level,
                'monthly_amount' => round($a->monthly_amount ?? 0, 2),
                'total_earned' => $a->total_earned,
            ]);

        // 总佣金统计
        $totalSettled = CommissionSettlement::sum('commission_amount');
        $totalPaid = CommissionPayout::where('status', 'completed')->sum('net_amount');
        $pendingPayouts = CommissionPayout::where('status', 'pending')->sum('amount');

        return response()->json([
            'success' => true,
            'data' => [
                'total_partners' => $totalPartners,
                'active_partners' => $activePartners,
                'pending_approval' => $pendingApproval,
                'level_distribution' => $levelDistribution,
                'monthly_trend' => $monthlyTrend,
                'top_partners' => $topPartners,
                'total_settled' => round($totalSettled, 2),
                'total_paid' => round($totalPaid, 2),
                'pending_payouts' => round($pendingPayouts, 2),
            ],
        ]);
    }

    /**
     * 合作伙伴列表（增强版）
     */
    public function partners(Request $request): JsonResponse
    {
        $this->ensureAdmin();

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

        $partners = $query->paginate($request->input('per_page', 20));

        $partners->getCollection()->transform(function ($a) {
            $stats = $this->engine->getAgentStats($a);
            return [
                'id' => $a->id,
                'agent_code' => $a->agent_code,
                'user_id' => $a->user_id,
                'name' => $a->user?->name ?? 'N/A',
                'email' => $a->user?->email ?? '',
                'level' => $a->level,
                'status' => $a->status,
                'commission_rate' => $a->commission_rate,
                'effective_rate' => $a->effective_rate,
                'contact_name' => $a->contact_name,
                'contact_phone' => $a->contact_phone,
                'company' => $a->company,
                'notes' => $a->notes,
                'total_earned' => $a->total_earned,
                'total_withdrawn' => $a->total_withdrawn,
                'available_balance' => $a->available_balance,
                'stats' => $stats,
                'approved_at' => $a->approved_at?->toIso8601String(),
                'created_at' => $a->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $partners,
        ]);
    }

    /**
     * 合作伙伴详情
     */
    public function showPartner(Agent $agent): JsonResponse
    {
        $this->ensureAdmin();

        $agent->load('user:id,name,email');
        $agent->loadCount('referralLinks as referral_links_count');
        $agent->loadCount('subscriptions as subscriptions_count');
        $stats = $this->engine->getAgentStats($agent);

        // 最近结算
        $recentSettlements = CommissionSettlement::where('agent_id', $agent->id)
            ->with('subscription:id,license_key', 'invoice:id,invoice_no,amount')
            ->latest()
            ->limit(20)
            ->get();

        // 月度业绩趋势
        $monthlyPerformance = CommissionSettlement::where('agent_id', $agent->id)
            ->selectRaw("period, SUM(commission_amount) as amount, COUNT(*) as count")
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'agent' => $agent,
                'stats' => $stats,
                'recent_settlements' => $recentSettlements,
                'monthly_performance' => $monthlyPerformance,
            ],
        ]);
    }

    /**
     * 审批合作伙伴
     */
    public function approvePartner(Agent $agent): JsonResponse
    {
        $this->ensureAdmin();

        $agent->update([
            'status' => 'active',
            'approved_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => __('app.api.partner.approved'),
        ]);
    }

    /**
     * 更新合作伙伴等级
     */
    public function updatePartnerLevel(Request $request, Agent $agent): JsonResponse
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'level' => 'required|in:regular,silver,gold,platinum',
        ]);

        $agent->update(['level' => $validated['level']]);

        return response()->json([
            'success' => true,
            'message' => __('app.api.partner.level_updated'),
            'data' => $agent->fresh(),
        ]);
    }

    /**
     * 合作伙伴结算明细
     */
    public function partnerSettlements(Request $request): JsonResponse
    {
        $this->ensureAdmin();

        $query = CommissionSettlement::with([
            'agent:id,agent_code,user_id',
            'agent.user:id,name,email',
            'subscription:id,license_key',
        ]);

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
            'data' => $query->orderBy('created_at', 'desc')
                ->paginate($request->input('per_page', 20)),
        ]);
    }

    /**
     * 合作伙伴推广链接列表
     */
    public function partnerReferralLinks(Request $request): JsonResponse
    {
        $this->ensureAdmin();

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

    // ============ 合作伙伴自助 API ============

    /**
     * 合作伙伴自助 — 我的看板
     */
    public function myDashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $agent = Agent::where('user_id', $user->id)->first();

        if (! $agent) {
            return response()->json(['success' => false, 'message' => __('app.api.partner.not_partner')], 403);
        }

        $stats = $this->engine->getAgentStats($agent);

        // 近期结算
        $recentSettlements = CommissionSettlement::where('agent_id', $agent->id)
            ->with('subscription:id,license_key')
            ->latest()
            ->limit(10)
            ->get();

        // 推广链接
        $links = ReferralLink::where('agent_id', $agent->id)
            ->where('is_active', true)
            ->get()
            ->map(fn($l) => [
                'id' => $l->id,
                'name' => $l->name,
                'code' => $l->code,
                'url' => url("/ref/{$l->code}"),
                'target_url' => $l->target_url,
                'clicks' => $l->clicks ?? 0,
                'conversions' => $l->conversions ?? 0,
                'created_at' => $l->created_at?->toIso8601String(),
            ]);

        // 等级权益
        $tierBenefits = $this->getTierBenefits($agent->level);

        return response()->json([
            'success' => true,
            'data' => [
                'agent' => $agent,
                'stats' => $stats,
                'recent_settlements' => $recentSettlements,
                'referral_links' => $links,
                'tier_benefits' => $tierBenefits,
            ],
        ]);
    }

    /**
     * 合作伙伴自助 — 提现记录
     */
    public function myPayouts(Request $request): JsonResponse
    {
        $user = $request->user();
        $agent = Agent::where('user_id', $user->id)->first();

        if (! $agent) {
            return response()->json(['success' => false, 'message' => __('app.api.partner.not_partner')], 403);
        }

        $payouts = CommissionPayout::where('agent_id', $agent->id)
            ->latest()
            ->paginate($request->input('per_page', 20));

        $payouts->getCollection()->transform(fn($p) => [
            'id' => $p->id,
            'amount' => $p->amount,
            'fee' => $p->fee,
            'net_amount' => $p->net_amount,
            'status' => $p->status,
            'payout_method' => $p->payout_method,
            'requested_at' => $p->requested_at?->toIso8601String(),
            'processed_at' => $p->processed_at?->toIso8601String(),
            'notes' => $p->notes,
        ]);

        return response()->json([
            'success' => true,
            'data' => $payouts,
        ]);
    }

    /**
     * 合作伙伴自助 — 发起提现
     */
    public function myRequestPayout(Request $request): JsonResponse
    {
        $user = $request->user();
        $agent = Agent::where('user_id', $user->id)->first();

        if (! $agent) {
            return response()->json(['success' => false, 'message' => __('app.api.partner.not_partner')], 403);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:100',
            'payout_method' => 'required|in:bank_transfer,alipay,wechat,balance',
            'account_info' => 'required|array',
        ]);

        try {
            $payout = $this->engine->requestPayout(
                $agent,
                $validated['amount'],
                $validated['payout_method'],
                $validated['account_info'],
            );

            return response()->json([
                'success' => true,
                'message' => __('app.api.partner.withdraw_submitted'),
                'data' => $payout,
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * 合作伙伴自助 — 等级权益说明
     */
    public function tierBenefits(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'regular' => [
                    'label' => __('app.api.partner.tier_regular'),
                    'rate' => __('app.api.partner.rate_5'),
                    'benefits' => [__('app.api.partner.ben_base_rate'), __('app.api.partner.ben_standard_link'), __('app.api.partner.ben_monthly')],
                ],
                'silver' => [
                    'label' => __('app.api.partner.tier_silver'),
                    'rate' => __('app.api.partner.rate_10'),
                    'min_requirements' => __('app.api.partner.req_10k'),
                    'benefits' => [__('app.api.partner.ben_rate_10'), __('app.api.partner.ben_manager'), __('app.api.partner.ben_priority')],
                ],
                'gold' => [
                    'label' => __('app.api.partner.tier_gold'),
                    'rate' => __('app.api.partner.rate_20'),
                    'min_requirements' => __('app.api.partner.req_50k'),
                    'benefits' => [__('app.api.partner.ben_rate_20'), __('app.api.partner.ben_manager'), __('app.api.partner.ben_priority'), __('app.api.partner.ben_marketing')],
                ],
                'platinum' => [
                    'label' => __('app.api.partner.tier_platinum'),
                    'rate' => __('app.api.partner.rate_30'),
                    'min_requirements' => __('app.api.partner.req_200k'),
                    'benefits' => [__('app.api.partner.ben_rate_30'), __('app.api.partner.ben_manager'), __('app.api.partner.ben_priority'), __('app.api.partner.ben_marketing'), __('app.api.partner.ben_tech'), __('app.api.partner.ben_cobrand')],
                ],
            ],
        ]);
    }

    protected function getTierBenefits(string $level): array
    {
        $all = [
            'regular' => ['label' => __('app.api.partner.tier_regular'), 'rate' => __('app.api.partner.rate_5'), 'color' => '#909399'],
            'silver' => ['label' => __('app.api.partner.tier_silver'), 'rate' => __('app.api.partner.rate_10'), 'color' => '#909399'],
            'gold' => ['label' => __('app.api.partner.tier_gold'), 'rate' => __('app.api.partner.rate_20'), 'color' => '#e6a23c'],
            'platinum' => ['label' => __('app.api.partner.tier_platinum'), 'rate' => __('app.api.partner.rate_30'), 'color' => '#0f172a'],
        ];

        $benefits = [];
        $found = false;
        foreach ($all as $l => $info) {
            $benefits[] = $info;
            if ($l === $level) {
                $found = true;
                break;
            }
        }

        return [
            'current_level' => $level,
            'current_label' => $all[$level]['label'] ?? $level,
            'current_color' => $all[$level]['color'] ?? '#909399',
            'benefits' => $benefits,
            'next_level' => $this->getNextLevel($level),
        ];
    }

    protected function getNextLevel(string $level): ?string
    {
        $order = ['regular' => 'silver', 'silver' => 'gold', 'gold' => 'platinum'];
        return $order[$level] ?? null;
    }
}
