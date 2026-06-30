<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AffiliateCampaign;
use App\Models\Agent;
use App\Models\Product;
use App\Services\AffiliateEnhancedService;
use App\Services\StoreAffiliateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 联盟推广增强控制器 (M3-05)
 *
 * 推荐链接、收益账户关联、商品级推广、提现入口
 */
class AffiliateEnhancedController extends Controller
{
    public function __construct(
        protected AffiliateEnhancedService $enhancedService,
        protected StoreAffiliateService $storeService,
    ) {}

    /**
     * 生成推广链接
     *
     * POST /api/affiliate/enhanced/generate-link
     */
    public function generateLink(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'agent_id' => 'required|exists:agents,id',
            'campaign_id' => 'nullable|exists:affiliate_campaigns,id',
            'creative_id' => 'nullable|exists:affiliate_creatives,id',
            'product_id' => 'nullable|exists:products,id',
        ]);

        $agent = Agent::findOrFail($validated['agent_id']);
        $campaign = isset($validated['campaign_id']) ? AffiliateCampaign::find($validated['campaign_id']) : null;
        $creative = isset($validated['creative_id']) ? \App\Models\AffiliateCreative::find($validated['creative_id']) : null;

        $link = $this->enhancedService->generateReferralLink(
            $agent, $campaign, $creative, $validated['product_id'] ?? null
        );

        return ApiResponse::success($link, '推广链接已生成');
    }

    /**
     * 获取代理的推广链接列表
     *
     * GET /api/affiliate/enhanced/agents/{agent}/links
     */
    public function agentLinks(Agent $agent): JsonResponse
    {
        return ApiResponse::success($this->enhancedService->getReferralLinks($agent));
    }

    /**
     * 代理推广门户数据
     *
     * GET /api/affiliate/enhanced/agents/{agent}/portal
     */
    public function agentPortal(Agent $agent): JsonResponse
    {
        return ApiResponse::success($this->enhancedService->getAgentPortalData($agent));
    }

    /**
     * 结算佣金到收益账户
     *
     * POST /api/affiliate/enhanced/settle-commission
     */
    public function settleCommission(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'agent_id' => 'required|exists:agents,id',
            'amount' => 'required|numeric|min:0.01',
            'source' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:500',
        ]);

        $settlement = $this->enhancedService->settleToEarningsAccount(
            $validated['agent_id'],
            $validated['amount'],
            $validated['source'] ?? 'manual',
            $validated['notes'] ?? null,
        );

        return ApiResponse::success($settlement, '佣金已结算');
    }

    /**
     * 转化归因 + 收益账户结算
     *
     * POST /api/affiliate/enhanced/attribute
     */
    public function attributeWithSettlement(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'referral_code' => 'required|string|max:50',
            'converted_user_id' => 'required|exists:users,id',
            'commission_amount' => 'nullable|numeric|min:0',
        ]);

        $result = $this->enhancedService->attributeConversionWithSettlement(
            $validated['referral_code'],
            $validated['converted_user_id'],
            $validated['commission_amount'] ?? 0,
        );

        return ApiResponse::success([
            'attributed' => !is_null($result),
            'click' => $result,
        ], $result ? '转化已归因并结算' : '未找到匹配点击');
    }

    /**
     * 生成商品推广链接 (M2-149)
     *
     * POST /api/affiliate/enhanced/product-link
     */
    public function productLink(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'agent_id' => 'required|exists:agents,id',
            'product_id' => 'required|exists:products,id',
            'campaign_id' => 'nullable|exists:affiliate_campaigns,id',
        ]);

        $agent = Agent::findOrFail($validated['agent_id']);
        $product = Product::findOrFail($validated['product_id']);
        $campaign = isset($validated['campaign_id']) ? AffiliateCampaign::find($validated['campaign_id']) : null;

        $link = $this->enhancedService->generateProductReferralLink($agent, $product, $campaign);

        return ApiResponse::success($link, '商品推广链接已生成');
    }

    /**
     * 电商订单关联推广人 (M2-149)
     *
     * POST /api/affiliate/enhanced/attribute-order
     */
    public function attributeOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => 'required|exists:store_orders,id',
            'referral_code' => 'required|string|max:50',
        ]);

        $order = \App\Models\StoreOrder::findOrFail($validated['order_id']);
        $agent = $this->storeService->attributeOrderToAffiliate($order, $validated['referral_code']);

        if (!$agent) {
            return ApiResponse::error('NO_AGENT_FOUND', '未找到推广人', 404);
        }

        // 自动结算佣金
        $this->storeService->calculateAndSettleOrderCommission($order);

        return ApiResponse::success([
            'order_id' => $order->id,
            'agent_id' => $agent->id,
            'agent_name' => $agent->contact_name ?: $agent->user?->name,
        ], '订单已关联推广人并结算佣金');
    }

    /**
     * 商品推广业绩
     *
     * GET /api/affiliate/enhanced/product-stats/{product}
     */
    public function productStats(Product $product): JsonResponse
    {
        return ApiResponse::success($this->enhancedService->getProductAffiliateStats($product->id));
    }

    /**
     * 电商分销看板
     *
     * GET /api/affiliate/enhanced/store-dashboard
     */
    public function storeDashboard(): JsonResponse
    {
        return ApiResponse::success($this->storeService->getProductPerformanceDashboard());
    }
}
