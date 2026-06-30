<?php

namespace App\Services;

use App\Models\AffiliateCampaign;
use App\Models\AffiliateClick;
use App\Models\AffiliateCreative;
use App\Models\AffiliateTree;
use App\Models\Agent;
use App\Models\CommissionSettlement;
use App\Models\EarningsAccount;
use App\Models\Product;
use App\Models\RegistrationTracking;
use App\Models\StoreOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 联盟推广增强服务 (M3-05)
 *
 * 推荐链接生成、收益账户关联(M1.1-19)、佣金自动结算(M2-127)、提现入口(M3-72)、商品级推广(M2-149)
 */
class AffiliateEnhancedService
{
    public function __construct(
        protected AffiliateService $affiliateService,
    ) {}

    // ─── 推荐链接生成 ───

    /**
     * 生成推广链接
     */
    public function generateReferralLink(Agent $agent, ?AffiliateCampaign $campaign = null, ?AffiliateCreative $creative = null, ?int $productId = null): array
    {
        $code = $this->generateReferralCode($agent);
        $baseUrl = config('app.url');
        $params = [
            'ref' => $code,
            'utm_source' => 'affiliate',
            'utm_medium' => $campaign?->type ?? 'referral',
            'utm_campaign' => $campaign?->slug ?? 'direct',
        ];

        if ($creative) {
            $params['utm_content'] = $creative->name;
            $params['creative_id'] = $creative->id;
        }
        if ($productId) {
            $params['product_id'] = $productId;
        }

        $landingUrl = $productId
            ? $baseUrl . '/products/' . $productId . '?' . http_build_query($params)
            : $baseUrl . '/register?' . http_build_query($params);

        // 保存推荐码
        RegistrationTracking::updateOrCreate(
            ['invite_code' => $code],
            [
                'agent_id' => $agent->id,
                'campaign_id' => $campaign?->id,
                'creative_id' => $creative?->id,
                'product_id' => $productId,
                'landing_url' => $landingUrl,
                'expires_at' => $campaign?->ends_at ?? now()->addYear(),
            ]
        );

        $shortCode = substr($code, 0, 8);

        return [
            'referral_code' => $code,
            'short_code' => $shortCode,
            'full_url' => $landingUrl,
            'short_url' => $baseUrl . '/s/' . $shortCode,
            'campaign_id' => $campaign?->id,
            'creative_id' => $creative?->id,
            'product_id' => $productId,
            'created_at' => now()->toIso8601String(),
        ];
    }

    /**
     * 获取代理所有推广链接
     */
    public function getReferralLinks(Agent $agent): array
    {
        return RegistrationTracking::where('agent_id', $agent->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($t) => [
                'id' => $t->id,
                'invite_code' => $t->invite_code,
                'landing_url' => $t->landing_url,
                'campaign_id' => $t->campaign_id,
                'creative_id' => $t->creative_id,
                'product_id' => $t->product_id,
                'converted' => $t->converted,
                'converted_at' => $t->converted_at?->toIso8601String(),
                'conversion_type' => $t->conversion_type,
                'clicks' => AffiliateClick::where('referral_code', $t->invite_code)->count(),
                'created_at' => $t->created_at->toIso8601String(),
                'expires_at' => $t->expires_at?->toIso8601String(),
            ])
            ->toArray();
    }

    // ─── 收益账户关联 (M1.1-19) ───

    /**
     * 结算佣金到收益账户
     * 替代直接 increment agent->total_earned
     */
    public function settleToEarningsAccount(int $agentId, float $amount, string $source = 'affiliate_commission', ?string $notes = null): CommissionSettlement
    {
        $agent = Agent::findOrFail($agentId);

        // 获取或创建收益账户
        $account = EarningsAccount::firstOrCreate(
            ['user_id' => $agent->user_id],
            [
                'type' => 'agent',
                'pending_balance' => 0,
                'available_balance' => 0,
                'total_withdrawn' => 0,
                'frozen_amount' => 0,
                'status' => 'active',
            ]
        );

        DB::transaction(function () use ($agent, $account, $amount, $source, $notes) {
            // 入 pending_balance (T+30 冻结期)
            $account->increment('pending_balance', $amount);
            $agent->increment('total_earned', $amount);
            $agent->increment('downline_earnings', $amount);

            // 创建结算记录
            CommissionSettlement::create([
                'agent_id' => $agent->id,
                'period' => now()->format('Y-m'),
                'status' => 'pending',
                'invoice_amount' => $amount,
                'commission_rate' => $agent->commission_rate,
                'commission_amount' => $amount,
                'rate_type' => 'affiliate',
                'settlement_type' => $source,
                'settled_at' => now(),
                'notes' => $notes ?? "联盟推广佣金 {$source}",
            ]);
        });

        Log::info('联盟佣金已结算到收益账户', [
            'agent_id' => $agentId,
            'amount' => $amount,
            'source' => $source,
            'account_id' => $account->id,
        ]);

        return CommissionSettlement::where('agent_id', $agentId)
            ->where('settlement_type', $source)
            ->latest()
            ->first();
    }

    /**
     * 多级分成 + 收益账户结算
     */
    public function distributeWithEarningsAccount(int $agentId, float $commissionAmount, string $source = 'affiliate_commission'): void
    {
        // 直接代理佣金 (一级)
        $this->settleToEarningsAccount($agentId, $commissionAmount, $source, '直接推广佣金');

        // 多级分成
        $treeRelations = AffiliateTree::where('child_agent_id', $agentId)
            ->where('status', 'active')
            ->get();

        foreach ($treeRelations as $relation) {
            $rate = $relation->rate;
            if ($rate <= 0) continue;

            $shareAmount = round($commissionAmount * ($rate / 100), 2);
            if ($shareAmount <= 0) continue;

            $levelLabel = $relation->level === 1 ? '一级上级分成' : '二级上级分成';
            $this->settleToEarningsAccount(
                $relation->parent_agent_id,
                $shareAmount,
                $source,
                $levelLabel
            );
        }
    }

    // ─── 商品级推广链接 (M2-149) ───

    /**
     * 生成商品推广链接
     */
    public function generateProductReferralLink(Agent $agent, Product $product, ?AffiliateCampaign $campaign = null): array
    {
        // 创建素材
        $creative = AffiliateCreative::create([
            'campaign_id' => $campaign?->id,
            'type' => 'link',
            'name' => "商品推广: {$product->name}",
            'url' => url("/products/{$product->id}"),
            'utm_params' => [
                'utm_source' => 'affiliate',
                'utm_medium' => 'product',
                'utm_campaign' => $campaign?->slug ?? 'product-share',
                'utm_content' => $product->sku ?? $product->id,
            ],
            'is_active' => true,
        ]);

        return $this->generateReferralLink($agent, $campaign, $creative, $product->id);
    }

    /**
     * 商品推广业绩
     */
    public function getProductAffiliateStats(int $productId): array
    {
        $clicks = AffiliateClick::whereHas('creative', fn($q) => $q->where('utm_params->utm_content', (string) $productId));
        $converted = (clone $clicks)->where('converted', true);

        return [
            'product_id' => $productId,
            'total_clicks' => $clicks->count(),
            'total_conversions' => $converted->count(),
            'conversion_rate' => $clicks->count() > 0 ? round($converted->count() / $clicks->count() * 100, 2) : 0,
            'total_commission' => $converted->sum('commission_amount'),
        ];
    }

    // ─── 代理推广门户增强 ───

    /**
     * 代理推广门户数据
     */
    public function getAgentPortalData(Agent $agent): array
    {
        $summary = $this->affiliateService->getAgentAffiliateSummary($agent);
        $links = $this->getReferralLinks($agent);

        // 收益账户
        $earningsAccount = EarningsAccount::where('user_id', $agent->user_id)->first();
        $pendingCommission = CommissionSettlement::where('agent_id', $agent->id)
            ->where('status', 'pending')
            ->sum('commission_amount');

        // 最近的推广活动
        $campaigns = AffiliateCampaign::where('status', 'active')
            ->orWhere('status', 'paused')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->toArray();

        // 最近转化
        $recentConversions = AffiliateClick::where('agent_id', $agent->id)
            ->where('converted', true)
            ->with('convertedUser')
            ->orderBy('converted_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'user_name' => $c->convertedUser?->name ?? 'N/A',
                'commission' => $c->commission_amount,
                'converted_at' => $c->converted_at?->toIso8601String(),
            ])
            ->toArray();

        // 提现入口信息
        $withdrawalInfo = [
            'available_balance' => $earningsAccount?->available_balance ?? 0,
            'pending_balance' => $earningsAccount?->pending_balance ?? 0,
            'min_withdrawal' => config('affiliate.min_withdrawal', 100),
            'withdrawal_url' => '/admin/withdrawals',
        ];

        return [
            'summary' => $summary,
            'referral_links' => $links,
            'earnings_account' => $earningsAccount,
            'pending_commission' => $pendingCommission,
            'campaigns' => $campaigns,
            'recent_conversions' => $recentConversions,
            'withdrawal' => $withdrawalInfo,
        ];
    }

    // ─── 转化归因增强（支持收益账户） ───

    /**
     * 转化归因 + 自动结算到收益账户
     */
    public function attributeConversionWithSettlement(string $referralCode, int $convertedUserId, float $commissionAmount = 0): ?AffiliateClick
    {
        $click = $this->affiliateService->attributeConversion($referralCode, $convertedUserId, $commissionAmount);

        if ($click && $commissionAmount > 0 && $click->agent_id) {
            // 使用收益账户结算
            $this->distributeWithEarningsAccount($click->agent_id, $commissionAmount);
        }

        return $click;
    }

    // ─── 工具方法 ───

    /**
     * 生成唯一推荐码
     */
    protected function generateReferralCode(Agent $agent): string
    {
        $prefix = 'AF';
        do {
            $code = $prefix . strtoupper(Str::random(6)) . $agent->id;
        } while (RegistrationTracking::where('invite_code', $code)->exists());

        return $code;
    }
}
