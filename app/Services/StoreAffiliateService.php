<?php

namespace App\Services;

use App\Models\AffiliateClick;
use App\Models\AffiliateCreative;
use App\Models\AffiliateTree;
use App\Models\Agent;
use App\Models\Product;
use App\Models\ProductSku;
use App\Models\RegistrationTracking;
use App\Models\StoreOrder;
use App\Models\StoreOrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 电商分销/联盟推广服务 (M2-149)
 *
 * 商品级推广链接生成、下单自动关联推广人、佣金自动结算、推广业绩看板
 */
class StoreAffiliateService
{
    public function __construct(
        protected AffiliateEnhancedService $enhancedService,
    ) {}

    /**
     * 可推广商品 SKU 列表（含佣金率）
     */
    public function getPromotableSkus(?int $tenantId = null, int $perPage = 13, int $page = 1): array
    {
        $query = ProductSku::where('is_active', true)
            ->where(function ($q) {
                $q->where('stock', '>', 0)->orWhere('stock', -1);
            })
            ->with(['product' => function ($q) {
                $q->select('id', 'name', 'image_url', 'category_id', 'user_id')
                  ->with('category:id,name');
            }])
            ->inRandomOrder();

        $paginator = $query->paginate($perPage, ['id', 'product_id', 'name', 'sku_code', 'price', 'compare_at_price', 'sold_count', 'image_url', 'commission_rate'], 'page', $page);

        $transformed = collect($paginator->items())->map(function ($sku) {
            $rate = $sku->commission_rate ?? config('affiliate.default_commission_rate', 10);
            return [
                'id' => $sku->id,
                'name' => $sku->name,
                'sku_code' => $sku->sku_code,
                'price' => $sku->price,
                'compare_at_price' => $sku->compare_at_price,
                'sold_count' => $sku->sold_count,
                'image_url' => $sku->image_url ?: $sku->product?->image_url,
                'product_name' => $sku->product?->name,
                'category_id' => $sku->product?->category_id,
                'category_name' => $sku->product?->category?->name,
                'commission_rate' => $rate,
                'commission_amount' => round($sku->price * $rate / 100, 2),
            ];
        })->values();

        return [
            'data' => $transformed,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'has_more' => $paginator->hasMorePages(),
        ];
    }

    /**
     * 批量生成推广链接
     */
    public function batchGenerateLinks(int $agentId, array $skuIds, ?int $campaignId = null): array
    {
        $agent = Agent::find($agentId);
        if (!$agent) return [];

        $results = [];
        foreach ($skuIds as $skuId) {
            $sku = ProductSku::with('product')->find($skuId);
            if (!$sku) continue;

            $code = $this->generateReferralCode($agent);
            $landingUrl = url("/products/{$sku->product?->slug}?ref={$code}&sku={$skuId}");
            $commissionRate = $sku->commission_rate ?? config('affiliate.default_commission_rate', 10);

            $click = AffiliateClick::create([
                'agent_id' => $agentId,
                'referral_code' => $code,
                'landing_url' => $landingUrl,
                'campaign_id' => $campaignId,
            ]);

            $results[] = [
                'click_id' => $click->id,
                'sku_id' => $skuId,
                'sku_name' => $sku->name,
                'product_name' => $sku->product?->name,
                'link' => $landingUrl,
                'referral_code' => $code,
                'commission_rate' => $commissionRate,
                'commission_amount' => round($sku->price * $commissionRate / 100, 2),
                'price' => $sku->price,
                'created_at' => $click->created_at->toDateTimeString(),
            ];
        }

        return $results;
    }

    /**
     * 推广业绩看板
     */
    public function getDashboard(int $agentId): array
    {
        $clicks = AffiliateClick::where('agent_id', $agentId);

        $totalOrders = (clone $clicks)->where('converted', true)->count();
        $totalCommission = (clone $clicks)->where('converted', true)->sum('commission_amount');
        $pendingCommission = (clone $clicks)->where('converted', true)->whereNull('converted_at')->sum('commission_amount');

        // 本月佣金
        $monthCommission = (clone $clicks)->where('converted', true)
            ->whereMonth('converted_at', now()->month)
            ->whereYear('converted_at', now()->year)
            ->sum('commission_amount');

        // 近7天趋势
        $trend = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dayCommission = (clone $clicks)->where('converted', true)
                ->whereDate('converted_at', $date)
                ->sum('commission_amount');
            $trend[] = [
                'date' => $date->toDateString(),
                'label' => $date->format('m/d'),
                'commission' => (float) $dayCommission,
            ];
        }

        // 商品推广排行
        $productRanking = AffiliateClick::where('agent_id', $agentId)
            ->where('converted', true)
            ->selectRaw('landing_url, count(*) as qty, sum(commission_amount) as revenue')
            ->groupBy('landing_url')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                $name = '商品';
                if (preg_match('#/products/([^/?]+)#', $item->landing_url, $m)) {
                    $product = Product::where('slug', $m[1])->first();
                    $name = $product?->name ?: $m[1];
                }
                return [
                    'name' => $name,
                    'qty' => $item->qty,
                    'revenue' => (float) $item->revenue,
                ];
            })->toArray();

        return [
            'total_orders' => $totalOrders,
            'total_commission' => (float) $totalCommission,
            'pending_commission' => (float) $pendingCommission,
            'month_commission' => (float) $monthCommission,
            'trend' => $trend,
            'product_ranking' => $productRanking,
        ];
    }

    /**
     * 推广订单列表
     */
    public function getOrders(int $agentId, array $params = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $perPage = (int) ($params['per_page'] ?? 20);

        return AffiliateClick::where('agent_id', $agentId)
            ->where('converted', true)
            ->with(['convertedUser' => function ($q) {
                $q->select('id', 'name', 'email');
            }])
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->through(function ($click) {
                return [
                    'id' => $click->id,
                    'order_no' => 'ORD-' . str_pad($click->id, 8, '0', STR_PAD_LEFT),
                    'customer_name' => $click->convertedUser?->name ?: '未知用户',
                    'final_amount' => (float) ($click->commission_amount * 100 / max(config('affiliate.default_commission_rate', 10), 1)),
                    'commission_amount' => (float) $click->commission_amount,
                    'commission_rate' => config('affiliate.default_commission_rate', 10),
                    'status' => $click->converted && $click->commission_amount > 0 ? 'settled' : 'pending',
                    'created_at' => $click->converted_at?->toDateTimeString() ?: $click->created_at->toDateTimeString(),
                ];
            });
    }

    /**
     * 推广链接列表
     */
    public function getLinks(int $agentId, array $params = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $perPage = (int) ($params['per_page'] ?? 20);

        return AffiliateClick::where('agent_id', $agentId)
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->through(function ($click) {
                return [
                    'id' => $click->id,
                    'landing_url' => $click->landing_url,
                    'referral_code' => $click->referral_code,
                    'converted' => (bool) $click->converted,
                    'commission_amount' => (float) ($click->commission_amount ?: 0),
                    'created_at' => $click->created_at->toDateTimeString(),
                ];
            });
    }

    /**
     * 下单时自动关联推广人
     */
    public function attributeOrderToAffiliate(StoreOrder $order, string $referralCode): ?Agent
    {
        $tracking = \App\Models\RegistrationTracking::where('invite_code', $referralCode)->first();
        if (!$tracking || !$tracking->agent_id) {
            return null;
        }
        $agent = Agent::find($tracking->agent_id);
        if (!$agent) return null;
        $order->updateQuietly(['affiliate_agent_id' => $agent->id, 'referral_code' => $referralCode]);
        AffiliateClick::create([
            'agent_id' => $agent->id, 'referral_code' => $referralCode,
            'converted' => true, 'converted_at' => now(), 'converted_user_id' => $order->user_id,
            'landing_url' => url("/products/{$order->id}"),
        ]);
        Log::info('电商订单已关联推广人', ['order_id' => $order->id, 'agent_id' => $agent->id, 'referral_code' => $referralCode]);
        return $agent;
    }

    /**
     * 计算并结算订单佣金（含预算管控）
     */
    public function calculateAndSettleOrderCommission(StoreOrder $order): void
    {
        if (!$order->affiliate_agent_id) return;
        $agent = Agent::find($order->affiliate_agent_id);
        if (!$agent) return;
        $totalCommission = 0;
        foreach ($order->items as $item) {
            $product = $item->product;
            if (!$product) continue;
            $rate = $this->getProductCommissionRate($product, $agent);
            $totalCommission += round($item->subtotal * ($rate / 100), 2);
        }
        if ($totalCommission <= 0) return;

        // 预算管控：通过 referral_code 查找关联的推广活动
        $campaign = null;
        if ($order->referral_code) {
            $origClick = AffiliateClick::where('referral_code', $order->referral_code)
                ->where('agent_id', $agent->id)
                ->whereNotNull('campaign_id')
                ->first();
            if ($origClick && $origClick->campaign_id) {
                $campaign = \App\Models\AffiliateCampaign::find($origClick->campaign_id);
            }
        }

        if ($campaign && $campaign->budget_total > 0) {
            // 检查已存入预算是否充足
            $deposited = $campaign->budget_deposited ?? 0;
            $currentUsed = $campaign->budget_used ?? 0;
            $available = $deposited - $currentUsed;
            if ($available <= 0) {
                Log::warning('推广活动预算余额不足，跳过结算', [
                    'campaign_id' => $campaign->id,
                    'deposited' => $deposited,
                    'used' => $currentUsed,
                    'order_id' => $order->id,
                ]);
                return;
            }
            // 如果佣金超过可用余额，按余额结算
            if ($totalCommission > $available) {
                $totalCommission = $available;
            }
            // 实时扣减预算
            $campaign->increment('budget_used', $totalCommission);
        }

        $this->enhancedService->settleToEarningsAccount($agent->id, $totalCommission, 'store_commission', "电商订单 #{$order->id} 分销佣金");
        $this->enhancedService->distributeWithEarningsAccount($agent->id, $totalCommission, 'store_commission');
    }

    /**
     * 获取商品佣金率
     */
    public function getProductCommissionRate(Product $product, Agent $agent): float
    {
        $productRate = $product->getAttribute('commission_rate');
        if ($productRate !== null && $productRate > 0) return (float) $productRate;
        return $agent->commission_rate ?: config("agent-manager.default_commission_rates.{$agent->level}", 5.0);
    }

    /**
     * 生成推广码
     */
    protected function generateReferralCode(Agent $agent): string
    {
        return strtoupper(substr(md5($agent->id . time() . uniqid()), 0, 8));
    }

    /**
     * 商品推广业绩看板
     */
    public function getProductPerformanceDashboard(): array
    {
        // 按商品统计
        $productStats = AffiliateCreative::where('type', 'link')
            ->where('utm_params->utm_source', 'affiliate')
            ->where('utm_params->utm_medium', 'product')
            ->get()
            ->map(fn($c) => [
                'creative_id' => $c->id,
                'name' => $c->name,
                'clicks' => $c->click_count,
                'conversions' => $c->conversion_count,
                'conversion_rate' => $c->click_count > 0 ? round($c->conversion_count / $c->click_count * 100, 2) : 0,
                'is_active' => $c->is_active,
            ])
            ->toArray();

        // 按代理统计
        $agentStats = AffiliateClick::where('converted', true)
            ->whereHas('campaign', fn($q) => $q->where('type', 'referral'))
            ->selectRaw('agent_id, count(*) as conversions, sum(commission_amount) as commission')
            ->groupBy('agent_id')
            ->orderByDesc('commission')
            ->limit(10)
            ->get()
            ->toArray();

        return [
            'product_stats' => $productStats,
            'top_agents' => $agentStats,
        ];
    }

    /**
     * 注册时自动建立推广关系链
     *
     * 当用户通过推广链接注册时：
     * 1. 通过推广码找到推广人(Agent)
     * 2. 自动为注册用户创建 Agent 账户
     * 3. 建立上下级关系 (AffiliateTree)
     */
    public function autoBuildAgentRelationshipOnRegistration(User $user, string $referralCode): ?Agent
    {
        // 通过推广码找到对应的注册追踪记录
        $tracking = RegistrationTracking::where('invite_code', $referralCode)->first();
        if (!$tracking || !$tracking->agent_id) {
            Log::warning('推广码未找到对应推广人', ['referral_code' => $referralCode]);
            return null;
        }

        $parentAgent = Agent::find($tracking->agent_id);
        if (!$parentAgent) {
            Log::warning('推广人(Agent)不存在', ['agent_id' => $tracking->agent_id]);
            return null;
        }

        // 更新注册追踪记录的用户ID
        $tracking->updateQuietly(['user_id' => $user->id]);

        // 检查用户是否已有 Agent 账户，没有则自动创建
        $childAgent = Agent::where('user_id', $user->id)->first();
        if (!$childAgent) {
            $childAgent = Agent::create([
                'user_id' => $user->id,
                'agent_code' => 'AF' . strtoupper(substr(md5($user->id . time()), 0, 8)),
                'level' => 'basic',
                'status' => 'active',
                'commission_rate' => config('affiliate.default_commission_rate', 10),
                'parent_agent_id' => $parentAgent->id,
                'referral_source' => 'affiliate',
                'approved_at' => now(),
            ]);
            Log::info('注册用户自动成为推广员', [
                'user_id' => $user->id,
                'agent_id' => $childAgent->id,
                'parent_agent_id' => $parentAgent->id,
            ]);
        } else {
            // 已有 Agent，更新上级关系
            $childAgent->update([
                'parent_agent_id' => $parentAgent->id,
                'referral_source' => 'affiliate',
            ]);
        }

        // 检查是否已有树关系
        $existing = AffiliateTree::where('parent_agent_id', $parentAgent->id)
            ->where('child_agent_id', $childAgent->id)
            ->exists();

        if (!$existing) {
            // 建立一级关系
            AffiliateTree::create([
                'parent_agent_id' => $parentAgent->id,
                'child_agent_id' => $childAgent->id,
                'level' => 1,
                'rate' => 10,
                'status' => 'active',
                'attributed_at' => now(),
            ]);

            // 更新父代理的子代理计数
            $parentAgent->updateQuietly([
                'downline_count' => AffiliateTree::where('parent_agent_id', $parentAgent->id)->count(),
            ]);

            // 自动建立二级关系（如果推广人也有上级）
            if ($parentAgent->parent_agent_id) {
                $grandparent = Agent::find($parentAgent->parent_agent_id);
                if ($grandparent) {
                    $existingL2 = AffiliateTree::where('parent_agent_id', $grandparent->id)
                        ->where('child_agent_id', $childAgent->id)
                        ->exists();

                    if (!$existingL2) {
                        AffiliateTree::create([
                            'parent_agent_id' => $grandparent->id,
                            'child_agent_id' => $childAgent->id,
                            'level' => 2,
                            'rate' => 5,
                            'status' => 'active',
                            'attributed_at' => now(),
                        ]);

                        $grandparent->updateQuietly([
                            'downline_count' => AffiliateTree::where('parent_agent_id', $grandparent->id)->count(),
                        ]);
                    }
                }
            }

            Log::info('推广关系链自动建立', [
                'parent' => $parentAgent->id,
                'child' => $childAgent->id,
                'level' => 1,
            ]);
        }

        return $childAgent;
    }
}
