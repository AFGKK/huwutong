<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AffiliateBudgetTopup;
use App\Models\AffiliateCampaign;
use App\Models\AffiliateClick;
use App\Models\AffiliateCreative;
use App\Models\AffiliateTree;
use App\Services\AffiliateEnhancedService;
use App\Models\Order;
use Illuminate\Support\Facades\Validator;
use App\Services\CommissionAiRecommendationService;
use App\Services\StoreAffiliateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

/**
 * 分销/联盟推广系统 (M2-149 🛒)
 */
class StoreAffiliateController extends Controller
{
    public function __construct(
        protected StoreAffiliateService $storeAffiliateService,
        protected AffiliateEnhancedService $affiliateEnhancedService,
        protected CommissionAiRecommendationService $commissionAiRecommendationService,
    ) {}

    /**
     * 可推广商品列表
     */
    public function promotableSkus(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 12), 50);
        $page = (int) $request->input('page', 1);
        return ApiResponse::success(
            $this->storeAffiliateService->getPromotableSkus($request->user()->tenant_id, $perPage, $page)
        );
    }

    /**
     * 生成商品推广链接
     */
    public function generateLink(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sku_ids' => 'required|array',
            'sku_ids.*' => 'integer|exists:product_skus,id',
            'campaign_id' => 'nullable|integer|exists:affiliate_campaigns,id',
        ]);

        $agent = Agent::where('user_id', $request->user()->id)->first();
        if (!$agent) {
            // 自动创建推广员
            $agent = Agent::create([
                'user_id' => $request->user()->id,
                'agent_code' => 'AF' . strtoupper(substr(md5($request->user()->id . time()), 0, 8)),
                'level' => 'basic',
                'status' => 'active',
                'commission_rate' => config('affiliate.default_commission_rate', 10),
                'approved_at' => now(),
            ]);
        }

        $results = $this->storeAffiliateService->batchGenerateLinks(
            $agent->id,
            $validated['sku_ids'],
            $validated['campaign_id'] ?? null
        );

        return ApiResponse::success($results, __('app.api.affiliate.links_generated'));
    }

    /**
     * 订单关联推广人（通过推广码）
     */
    public function linkOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'referral_code' => 'required|string|max:50',
        ]);

        $order = Order::findOrFail($validated['order_id']);

        try {
            $this->storeAffiliateService->attributeOrderToAffiliate(
                $order,
                $validated['referral_code']
            );
            return ApiResponse::success(null, __('app.api.affiliate.order_linked'));
        } catch (\Throwable $e) {
            return ApiResponse::error('LINK_FAILED', $e->getMessage(), 500);
        }
    }

    /**
     * 结算订单佣金
     */
    public function settleCommission(Request $request, int $orderId): JsonResponse
    {
        $order = Order::findOrFail($orderId);

        try {
            $this->storeAffiliateService->calculateAndSettleOrderCommission($order);
            return ApiResponse::success(null, __('app.api.affiliate.commission_settled'));
        } catch (\Throwable $e) {
            return ApiResponse::error('SETTLE_FAILED', $e->getMessage(), 500);
        }
    }

    /**
     * 推广业绩看板
     */
    public function dashboard(Request $request): JsonResponse
    {
        $agent = Agent::where('user_id', $request->user()->id)->first();
        if (!$agent) {
            return ApiResponse::success([
                'total_orders' => 0, 'total_commission' => 0,
                'pending_commission' => 0, 'month_commission' => 0,
                'trend' => [], 'product_ranking' => [],
                'agent_id' => null,
            ]);
        }

        return ApiResponse::success(
            array_merge(
                $this->storeAffiliateService->getDashboard($agent->id),
                ['agent_id' => $agent->id, 'agent_code' => $agent->agent_code]
            )
        );
    }

    /**
     * 获取当前用户的推广员信息
     */
    public function myAgent(Request $request): JsonResponse
    {
        $agent = Agent::where('user_id', $request->user()->id)->first();
        if (!$agent) {
            return ApiResponse::success(null);
        }
        return ApiResponse::success($agent);
    }

    /**
     * 推广订单列表
     */
    public function orders(Request $request): JsonResponse
    {
        $agent = Agent::where('user_id', $request->user()->id)->first();
        if (!$agent) {
            return ApiResponse::paginated(new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20));
        }

        return ApiResponse::paginated(
            $this->storeAffiliateService->getOrders($agent->id, $request->all())
        );
    }

    /**
     * 推广链接列表
     */
    public function links(Request $request): JsonResponse
    {
        $agent = Agent::where('user_id', $request->user()->id)->first();
        if (!$agent) {
            return ApiResponse::paginated(new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20));
        }

        return ApiResponse::paginated(
            $this->storeAffiliateService->getLinks($agent->id, $request->all())
        );
    }

    /**
     * 推广活动列表（门户端）
     */
    public function campaigns(Request $request): JsonResponse
    {
        $query = AffiliateCampaign::where('status', AffiliateCampaign::STATUS_ACTIVE)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $campaigns = $query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 20));

        return ApiResponse::paginated($campaigns);
    }

    /**
     * 推广员统计概览（门户端）
     */
    public function agentSummary(Request $request): JsonResponse
    {
        $agent = Agent::where('user_id', $request->user()->id)->first();
        if (!$agent) {
            return ApiResponse::success([
                'total_clicks' => 0, 'total_conversions' => 0,
                'total_commission' => 0, 'downline_count' => 0,
            ]);
        }

        $totalClicks = AffiliateClick::where('agent_id', $agent->id)->count();
        $totalConversions = AffiliateClick::where('agent_id', $agent->id)->where('converted', true)->count();
        $totalCommission = AffiliateClick::where('agent_id', $agent->id)->where('converted', true)->sum('commission_amount');
        $downlineCount = AffiliateTree::where('parent_agent_id', $agent->id)->count();

        return ApiResponse::success([
            'total_clicks' => $totalClicks,
            'total_conversions' => $totalConversions,
            'total_commission' => (float) $totalCommission,
            'downline_count' => $downlineCount,
        ]);
    }

    /**
     * 下级代理列表（门户端/管理端）
     */
    public function downline(Request $request, Agent $agent): JsonResponse
    {
        $downline = AffiliateTree::with('childAgent.user')
            ->where('parent_agent_id', $agent->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($tree) => [
                'id' => $tree->childAgent->id ?? null,
                'agent_code' => $tree->childAgent->agent_code ?? null,
                'name' => $tree->childAgent->user->name ?? null,
                'level' => $tree->level,
                'rate' => $tree->rate,
                'status' => $tree->status,
                'created_at' => $tree->created_at,
            ]);

        return ApiResponse::success($downline);
    }

    /**
     * 点击/转化记录（门户端）
     */
    public function clickLogs(Request $request): JsonResponse
    {
        $query = AffiliateClick::with('campaign')
            ->orderBy('created_at', 'desc');

        // 管理员查看全部，普通用户只看自己的
        $user = $request->user();
        $isAdmin = $user->hasRole(['super-admin', 'admin', 'tenant-admin']);
        if (!$isAdmin) {
            $agent = Agent::where('user_id', $user->id)->first();
            if (!$agent) {
                return ApiResponse::paginated(new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20));
            }
            $query->where('agent_id', $agent->id);
        }

        // 筛选条件
        if ($request->filled('campaign_id')) {
            $query->where('campaign_id', $request->input('campaign_id'));
        }
        if ($request->filled('agent_id')) {
            $query->where('agent_id', $request->input('agent_id'));
        }
        if ($request->filled('converted')) {
            $query->where('converted', $request->boolean('converted'));
        }

        $clicks = $query->paginate($request->input('per_page', 20));

        return ApiResponse::paginated($clicks);
    }

    /**
     * 建立推广关系（管理端）
     *
     * POST /api/store-affiliate/tree
     */
    public function buildTree(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'parent_agent_id' => 'required|integer|exists:agents,id',
            'child_agent_id' => 'required|integer|exists:agents,id|different:parent_agent_id',
            'level' => 'nullable|integer|in:1,2',
            'rate' => 'nullable|numeric|min:0|max:100',
        ])->validate();

        $parent = Agent::findOrFail($validated['parent_agent_id']);
        $child = Agent::findOrFail($validated['child_agent_id']);

        // 检查是否已有关系
        $existing = AffiliateTree::where('parent_agent_id', $parent->id)
            ->where('child_agent_id', $child->id)
            ->exists();

        if ($existing) {
            return ApiResponse::error('ALREADY_EXISTS', __('app.api.affiliate.relation_exists'), 400);
        }

        $level = (int) ($validated['level'] ?? 1);
        $rate = $validated['rate'] ?? match ($level) {
            1 => 10,
            2 => 5,
            default => 0,
        };

        $tree = AffiliateTree::create([
            'parent_agent_id' => $parent->id,
            'child_agent_id' => $child->id,
            'level' => $level,
            'rate' => (float) $rate,
            'status' => 'active',
            'attributed_at' => now(),
        ]);

        // 更新父代理的子代理计数
        $parent->updateQuietly(['downline_count' => AffiliateTree::where('parent_agent_id', $parent->id)->count()]);
        $child->update(['parent_agent_id' => $parent->id, 'referral_source' => 'affiliate']);

        return ApiResponse::success($tree, __('app.api.affiliate.relation_created'));
    }

    /**
     * 查询代理的上级链（管理端）
     *
     * GET /api/store-affiliate/agents/{agent}/upline
     */
    public function upline(Agent $agent): JsonResponse
    {
        $upline = AffiliateTree::where('child_agent_id', $agent->id)
            ->with('parentAgent.user')
            ->orderBy('level')
            ->get()
            ->map(fn ($tree) => [
                'id' => $tree->parentAgent->id ?? null,
                'agent_code' => $tree->parentAgent->agent_code ?? null,
                'name' => $tree->parentAgent->user->name ?? null,
                'level' => $tree->level,
                'rate' => $tree->rate,
                'created_at' => $tree->created_at,
            ]);

        return ApiResponse::success($upline);
    }

    /**
     * 创建推广活动（管理端）
     */
    public function storeCampaign(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:100|unique:affiliate_campaigns,slug',
            'description' => 'nullable|string',
            'type' => 'required|string|in:referral,commission,reward,rebate',
            'billing_mode' => 'nullable|string|in:cpa,cpc,cpm',
            'status' => 'nullable|string|in:draft,active,paused,completed',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'reward_first' => 'nullable|numeric|min:0',
            'reward_renewal' => 'nullable|numeric|min:0',
            'reward_upgrade' => 'nullable|numeric|min:0',
            'budget_total' => 'nullable|numeric|min:0',
            'cost_per_click' => 'nullable|numeric|min:0',
            'cost_per_impression' => 'nullable|numeric|min:0',
            'platform_share_rate' => 'nullable|numeric|min:0|max:100',
            'max_participants' => 'nullable|integer|min:1',
        ])->validate();

        $campaign = AffiliateCampaign::create(array_merge($validated, [
            'slug' => $validated['slug'] ?? (str()->slug($validated['name']) ?: 'campaign-' . strtolower(substr(md5($validated['name'] . time()), 0, 12))),
            'billing_mode' => $validated['billing_mode'] ?? 'cpa',
            'status' => $validated['status'] ?? 'draft',
            'created_by' => $request->user()->id,
        ]));

        return ApiResponse::success($campaign, __('app.api.affiliate.campaign_created'), 201);
    }

    /**
     * 更新推广活动（管理端）
     */
    public function updateCampaign(Request $request, AffiliateCampaign $campaign): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'slug' => 'nullable|string|max:100|unique:affiliate_campaigns,slug,' . $campaign->id,
            'description' => 'nullable|string',
            'type' => 'sometimes|string|in:referral,commission,reward,rebate',
            'billing_mode' => 'nullable|string|in:cpa,cpc,cpm',
            'status' => 'nullable|string|in:draft,active,paused,completed',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'reward_first' => 'nullable|numeric|min:0',
            'reward_renewal' => 'nullable|numeric|min:0',
            'reward_upgrade' => 'nullable|numeric|min:0',
            'budget_total' => 'nullable|numeric|min:0',
            'cost_per_click' => 'nullable|numeric|min:0',
            'cost_per_impression' => 'nullable|numeric|min:0',
            'platform_share_rate' => 'nullable|numeric|min:0|max:100',
            'max_participants' => 'nullable|integer|min:1',
        ])->validate();

        $campaign->update($validated);

        return ApiResponse::success($campaign->fresh(), __('app.api.affiliate.campaign_updated'));
    }

    /**
     * 删除推广活动（管理端）
     */
    public function destroyCampaign(AffiliateCampaign $campaign): JsonResponse
    {
        $campaign->delete();
        return ApiResponse::success(null, __('app.api.affiliate.campaign_deleted'));
    }

    /**
     * 获取当前用户的个性化活动推广链接（门户端）
     *
     * GET /api/store-affiliate/campaigns/{campaign}/my-link
     */
    public function myCampaignLink(Request $request, AffiliateCampaign $campaign): JsonResponse
    {
        // 获取或创建推广员
        $agent = Agent::where('user_id', $request->user()->id)->first();
        if (!$agent) {
            $agent = Agent::create([
                'user_id' => $request->user()->id,
                'agent_code' => 'AF' . strtoupper(substr(md5($request->user()->id . time()), 0, 8)),
                'level' => 'basic',
                'status' => 'active',
                'commission_rate' => config('affiliate.default_commission_rate', 10),
                'approved_at' => now(),
            ]);
        }

        // 生成推广链接（含推广码）
        $linkData = $this->affiliateEnhancedService->generateReferralLink(
            $agent,
            $campaign,
            null, // no creative
            null  // no product
        );

        $fullUrl = url("/ref/{$campaign->slug}?ref={$linkData['referral_code']}");

        return ApiResponse::success([
            'referral_code' => $linkData['referral_code'],
            'campaign_link' => $fullUrl,
            'agent_id' => $agent->id,
        ]);
    }

    /**
     * 充值预算（管理端）- 支持多种支付方式
     *
     * POST /api/store-affiliate/campaigns/{campaign}/deposit
     */
    public function depositBudget(Request $request, AffiliateCampaign $campaign): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'nullable|string|in:mock_instant,wechat,alipay,stripe,paypal',
        ])->validate();

        $amount = (float) $validated['amount'];
        $method = $validated['payment_method'] ?? 'mock_instant';

        // 不能超过总预算
        $newDeposited = ($campaign->budget_deposited ?? 0) + $amount;
        if ($campaign->budget_total > 0 && $newDeposited > $campaign->budget_total) {
            return ApiResponse::error('BUDGET_EXCEEDS_TOTAL',
                __('app.api.affiliate.budget_exceeds', ['new' => $newDeposited, 'total' => $campaign->budget_total]), 400);
        }

        // 创建充值记录
        $topup = AffiliateBudgetTopup::create([
            'campaign_id' => $campaign->id,
            'user_id' => $request->user()->id,
            'amount' => $amount,
            'status' => 'pending',
            'payment_method' => $method,
        ]);

        if ($method === 'mock_instant') {
            // 模拟支付：即时到账
            $topup->update([
                'status' => 'completed',
                'transaction_id' => 'MOCK' . date('YmdHis') . $topup->id,
                'paid_at' => now(),
            ]);
            $campaign->increment('budget_deposited', $amount);

            Log::info('推广活动预算充值（模拟到账）', [
                'campaign_id' => $campaign->id,
                'topup_id' => $topup->id,
                'amount' => $amount,
                'user_id' => $request->user()->id,
            ]);

            return ApiResponse::success([
                'topup_id' => $topup->id,
                'amount' => $amount,
                'status' => 'completed',
                'payment_method' => $method,
                'budget_deposited' => $campaign->fresh()->budget_deposited,
                'budget_used' => $campaign->budget_used,
                'remaining' => $campaign->fresh()->budget_deposited - $campaign->budget_used,
                'message' => __('app.api.affiliate.topup_paid', ['amount' => $amount]),
            ], __('app.api.affiliate.topup_ok'));
        }

        // 真实支付流程（微信/支付宝/Stripe/PayPal）
        $gatewayMap = [
            'wechat' => \App\Services\Payment\WechatPaymentGateway::class,
            'alipay' => \App\Services\Payment\AlipayPaymentGateway::class,
            'stripe' => \App\Services\Payment\StripePaymentGateway::class,
            'paypal' => \App\Services\Payment\PaypalPaymentGateway::class,
        ];

        $gatewayClass = $gatewayMap[$method] ?? null;
        if (!$gatewayClass) {
            $topup->update(['status' => 'failed', 'notes' => __('app.api.affiliate.unsupported_pay')]);
            return ApiResponse::error('INVALID_PAYMENT_METHOD', __('app.api.affiliate.unsupported_pay'), 400);
        }

        try {
            // 创建虚拟 Invoice 用于支付
            $invoice = new \App\Models\Invoice();
            $invoice->id = $topup->id;
            $invoice->invoice_no = 'TOPUP-' . str_pad($topup->id, 8, '0', STR_PAD_LEFT);
            $invoice->amount = $amount;

            /** @var \App\Contracts\PaymentGateway $gateway */
            $gateway = app($gatewayClass);
            $result = $gateway->charge($invoice, [
                'subject' => __('app.api.affiliate.topup_subject', ['name' => $campaign->name]),
                'description' => __('app.api.affiliate.topup_desc', ['id' => $campaign->id, 'name' => $campaign->name, 'amount' => $amount]),
            ]);

            if ($result['success'] ?? false) {
                $topup->update([
                    'transaction_id' => $result['transaction_id'] ?? null,
                ]);
                return ApiResponse::success([
                    'topup_id' => $topup->id,
                    'amount' => $amount,
                    'status' => 'pending',
                    'payment_method' => $method,
                    'payment_url' => $result['payment_url'] ?? null,
                    'transaction_id' => $result['transaction_id'] ?? null,
                ], __('app.api.affiliate.payment_created'));
            }

            $topup->update(['status' => 'failed', 'notes' => $result['error'] ?? __('app.api.affiliate.payment_failed')]);
            return ApiResponse::error('PAYMENT_FAILED', $result['error'] ?? __('app.api.affiliate.payment_failed'), 400);
        } catch (\Throwable $e) {
            $topup->update(['status' => 'failed', 'notes' => $e->getMessage()]);
            Log::error('推广活动预算充值支付异常', [
                'topup_id' => $topup->id,
                'method' => $method,
                'error' => $e->getMessage(),
            ]);
            return ApiResponse::error('PAYMENT_ERROR', __('app.api.affiliate.payment_error', ['error' => $e->getMessage()]), 500);
        }
    }

    // ─── 素材管理 ───

    /**
     * 素材列表
     *
     * GET /api/store-affiliate/campaigns/{campaign}/creatives
     */
    public function creatives(Request $request, AffiliateCampaign $campaign): JsonResponse
    {
        $query = $campaign->creatives()->orderByDesc('id');
        $user = $request->user();

        if (!$user->hasRole('super-admin') && !$user->hasRole('admin')) {
            $query->where('status', 'approved')->where('is_active', true);
        }

        return ApiResponse::success($query->get());
    }

    /**
     * 创建素材
     *
     * POST /api/store-affiliate/campaigns/{campaign}/creatives
     */
    public function storeCreative(Request $request, AffiliateCampaign $campaign): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'type' => 'required|in:banner,image,video,text,landing_page,link,coupon,qr_code',
            'name' => 'required|string|max:100',
            'url' => 'nullable|string|max:500',
            'content' => 'nullable|string',
            'image_url' => 'nullable|string|max:500',
            'utm_params' => 'nullable|array',
            'is_active' => 'nullable|boolean',
            'commission_amount' => 'nullable|numeric|min:0|max:99999999.99',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
        ])->validate();

        $validated['campaign_id'] = $campaign->id;
        // 管理员直接创建=已审核，用户提交=待审核
        $user = $request->user();
        if ($user && $user->hasRole('super-admin')) {
            $validated['status'] = 'approved';
        } else {
            $validated['status'] = 'pending';
            $validated['created_by'] = $user?->id;
        }

        $creative = AffiliateCreative::create($validated);

        return ApiResponse::success($creative, __('app.api.affiliate.creative_created'));
    }

    /**
     * 更新素材
     *
     * PUT /api/store-affiliate/campaigns/{campaign}/creatives/{creative}
     */
    public function updateCreative(Request $request, AffiliateCampaign $campaign, AffiliateCreative $creative): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'type' => 'sometimes|in:banner,image,video,text,landing_page,link,coupon,qr_code',
            'name' => 'sometimes|string|max:100',
            'url' => 'nullable|string|max:500',
            'content' => 'nullable|string',
            'image_url' => 'nullable|string|max:500',
            'utm_params' => 'nullable|array',
            'is_active' => 'nullable|boolean',
            'commission_amount' => 'nullable|numeric|min:0|max:99999999.99',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
        ])->validate();

        $creative->update($validated);

        return ApiResponse::success($creative->fresh(), __('app.api.affiliate.creative_updated'));
    }

    /**
     * 删除素材
     *
     * DELETE /api/store-affiliate/campaigns/{campaign}/creatives/{creative}
     */
    public function destroyCreative(AffiliateCampaign $campaign, AffiliateCreative $creative): JsonResponse
    {
        $creative->delete();
        return ApiResponse::success(null, __('app.api.affiliate.creative_deleted'));
    }

    /**
     * 素材转化统计
     *
     * GET /api/store-affiliate/campaigns/{campaign}/creative-stats
     */
    public function creativeStats(AffiliateCampaign $campaign): JsonResponse
    {
        $stats = AffiliateCreative::where('campaign_id', $campaign->id)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'type' => $c->type,
                'click_count' => $c->click_count,
                'conversion_count' => $c->conversion_count,
                'conversion_rate' => $c->click_count > 0
                    ? round($c->conversion_count / $c->click_count * 100, 1)
                    : 0,
            ]);

        return ApiResponse::success($stats);
    }

    /**
     * 审核素材（管理端）
     *
     * POST /api/store-affiliate/campaigns/{campaign}/creatives/{creative}/review
     */
    public function reviewCreative(Request $request, AffiliateCampaign $campaign, AffiliateCreative $creative): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'action' => 'required|in:approved,rejected',
            'review_notes' => 'nullable|string|max:500',
        ])->validate();

        $creative->update([
            'status' => $validated['action'],
            'review_notes' => $validated['review_notes'] ?? null,
            'reviewed_at' => now(),
            'is_active' => $validated['action'] === 'approved',
        ]);

        $msg = $validated['action'] === 'approved' ? __('app.api.affiliate.creative_approved') : __('app.api.affiliate.creative_rejected');
        return ApiResponse::success($creative->fresh(), $msg);
    }

    /**
     * 所有待审核/已驳回素材（管理端）
     *
     * GET /api/store-affiliate/pending-creatives?status=pending|rejected
     */
    public function pendingCreatives(Request $request): JsonResponse
    {
        $status = $request->input('status', 'pending');
        $creatives = AffiliateCreative::where('status', $status)
            ->with(['campaign', 'creator'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 50));

        return ApiResponse::paginated($creatives);
    }

    /**
     * 重新提交审核（将已驳回素材改为待审核）
     *
     * POST /api/store-affiliate/creatives/{creative}/resubmit
     */
    public function resubmitCreative(Request $request, AffiliateCreative $creative): JsonResponse
    {
        if ((int) $creative->created_by !== (int) $request->user()->id) {
            return ApiResponse::error('FORBIDDEN', __('app.api.affiliate.forbidden_creative'), 403);
        }

        if ($creative->status !== 'rejected') {
            return ApiResponse::error('INVALID_STATUS', __('app.api.affiliate.resubmit_rejected_only'), 422);
        }

        $creative->update([
            'status' => 'pending',
            'review_notes' => null,
            'reviewed_at' => null,
            'is_active' => false,
        ]);

        return ApiResponse::success($creative->fresh(), __('app.api.affiliate.creative_resubmitted'));
    }

    /**
     * 用户提交素材（门户端）
     *
     * POST /api/store-affiliate/creatives/submit
     */
    public function submitCreative(Request $request): JsonResponse
    {
        $agent = Agent::where('user_id', $request->user()->id)->where('status', 'active')->first();
        if (!$agent) {
            return ApiResponse::error('NOT_AGENT', __('app.api.affiliate.not_agent'), 403);
        }

        $validated = Validator::make($request->all(), [
            'campaign_id' => 'required|exists:affiliate_campaigns,id',
            'type' => 'required|in:banner,image,video,text,landing_page,link,coupon,qr_code',
            'name' => 'required|string|max:100',
            'url' => 'nullable|string|max:500',
            'content' => 'nullable|string',
            'image_url' => 'nullable|string|max:500',
        ])->validate();

        $validated['status'] = 'pending';
        $validated['created_by'] = $request->user()->id;
        $validated['is_active'] = false;

        $creative = AffiliateCreative::create($validated);

        return ApiResponse::success($creative->load('campaign'), __('app.api.affiliate.creative_submitted'));
    }

    /**
     * 申请成为推广员
     *
     * POST /api/store-affiliate/apply-agent
     */
    public function applyAgent(Request $request): JsonResponse
    {
        $user = $request->user();
        $existing = Agent::where('user_id', $user->id)->first();

        if ($existing) {
            if ($existing->status === 'active') {
                return ApiResponse::error('ALREADY_AGENT', __('app.api.affiliate.already_agent'));
            }

            if ($existing->status === 'pending') {
                return ApiResponse::error('ALREADY_PENDING', __('app.api.affiliate.already_pending'));
            }

            if ($existing->status === 'rejected') {
                $existing->update([
                    'status' => 'pending',
                    'notes' => null,
                    'approved_at' => null,
                ]);

                return ApiResponse::success($existing->fresh(), __('app.api.affiliate.agent_resubmitted'));
            }

            return ApiResponse::error('ALREADY_AGENT', __('app.api.affiliate.already_agent_status', ['status' => $existing->status]));
        }

        $agent = Agent::create([
            'user_id' => $user->id,
            'agent_code' => 'AF' . strtoupper(substr(md5($user->id . time()), 0, 8)),
            'level' => 'basic',
            'status' => 'pending',
            'commission_rate' => config('affiliate.default_commission_rate', 10),
        ]);

        return ApiResponse::success($agent, __('app.api.affiliate.agent_submitted'));
    }

    /**
     * 待审核推广员列表
     *
     * GET /api/store-affiliate/pending-agents
     */
    public function pendingAgents(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasRole('admin') && !$user->hasRole('super-admin')) {
            return ApiResponse::error('FORBIDDEN', __('app.api.affiliate.forbidden_view_pending'), 403);
        }

        $agents = Agent::where('status', 'pending')
            ->with('user:id,name,email')
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 50));

        return ApiResponse::paginated($agents);
    }

    /**
     * 审核推广员
     *
     * POST /api/store-affiliate/agents/{agent}/review
     */
    public function reviewAgent(Request $request, Agent $agent): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasRole('admin') && !$user->hasRole('super-admin')) {
            return ApiResponse::error('FORBIDDEN', __('app.api.affiliate.forbidden_review'), 403);
        }

        $validated = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'action' => 'required|in:approved,rejected',
            'notes' => 'nullable|string|max:500',
        ])->validate();

        if ($agent->status !== 'pending') {
            return ApiResponse::error('INVALID_STATUS', __('app.api.affiliate.already_handled'), 422);
        }

        if ($validated['action'] === 'approved') {
            $agent->update([
                'status' => 'active',
                'approved_at' => now(),
                'notes' => null,
            ]);
            $msg = __('app.api.affiliate.agent_approved');
        } else {
            $agent->update([
                'status' => 'rejected',
                'notes' => $validated['notes'] ?? null,
                'approved_at' => null,
            ]);
            $msg = __('app.api.affiliate.agent_rejected');
        }

        return ApiResponse::success($agent->fresh()->load('user:id,name,email'), $msg);
    }

    /**
     * 我提交的素材（门户端）
     *
     * GET /api/store-affiliate/my-creatives
     */
    public function myCreatives(Request $request): JsonResponse
    {
        $creatives = AffiliateCreative::where('created_by', $request->user()->id)
            ->with('campaign')
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));

        return ApiResponse::paginated($creatives);
    }

    // ══════════════════════════════════════════
    // ╎  AI 佣金推荐
    // ══════════════════════════════════════════

    /**
     * AI 推荐最优佣金率
     *
     * POST /api/store-affiliate/ai/recommend-rate
     */
    public function aiRecommendRate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'nullable|integer|exists:products,id',
            'campaign_type' => 'nullable|string|in:referral,commission,reward,rebate',
            'product_price' => 'nullable|numeric|min:0',
        ]);

        $result = $this->commissionAiRecommendationService->recommendCommissionRate(
            productId: $validated['product_id'] ?? null,
            campaignType: $validated['campaign_type'] ?? null,
            productPrice: $validated['product_price'] ?? null,
        );

        return ApiResponse::success($result, __('app.api.affiliate.commission_recommended'));
    }

    /**
     * AI 批量推荐佣金率
     *
     * POST /api/store-affiliate/ai/batch-recommend
     */
    public function aiBatchRecommend(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_ids' => 'required|array',
            'product_ids.*' => 'integer|exists:products,id',
            'campaign_type' => 'nullable|string|in:referral,commission,reward,rebate',
        ]);

        $results = $this->commissionAiRecommendationService->batchRecommend(
            productIds: $validated['product_ids'],
            campaignType: $validated['campaign_type'] ?? null,
        );

        return ApiResponse::success($results, __('app.api.affiliate.commission_batch'));
    }

    /**
     * AI 获取创建活动预设建议
     *
     * GET /api/store-affiliate/ai/campaign-presets
     */
    public function aiCampaignPresets(): JsonResponse
    {
        return ApiResponse::success(
            $this->commissionAiRecommendationService->getCampaignPresets(),
            __('app.api.affiliate.presets_loaded')
        );
    }

    /**
     * AI 佣金效率分析
     *
     * GET /api/store-affiliate/ai/efficiency-analysis
     */
    public function aiEfficiencyAnalysis(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days' => 'nullable|integer|min:1|max:365',
        ]);

        return ApiResponse::success(
            $this->commissionAiRecommendationService->analyzeCommissionEfficiency(
                tenantId: $request->user()->tenant_id,
                days: $validated['days'] ?? 90,
            ),
            __('app.api.affiliate.efficiency_done')
        );
    }
}
