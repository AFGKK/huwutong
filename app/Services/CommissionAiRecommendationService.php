<?php

namespace App\Services;

use App\Models\AffiliateCampaign;
use App\Models\AffiliateClick;
use App\Models\Commission;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/**
 * AI 佣金推荐引擎
 *
 * 基于多维度数据分析，为联盟推广活动推荐最优佣金率：
 * 1. 商品价格区间基准 — 不同价格带的行业惯例佣金率
 * 2. 历史转化数据 — 同类活动/商品的转化率与佣金关系
 * 3. 季节性调整 — 旺季/淡季的动态调整
 * 4. 客户生命周期 — 新客/老客差异化推荐
 */
class CommissionAiRecommendationService
{
    // 价格区间基准佣金率映射
    protected array $priceBenchmarks = [
        'low'      => ['max_price' => 100,   'min_rate' => 20, 'max_rate' => 40, 'default' => 30],
        'medium'   => ['max_price' => 500,   'min_rate' => 15, 'max_rate' => 30, 'default' => 20],
        'high'     => ['max_price' => 2000,  'min_rate' => 10, 'max_rate' => 25, 'default' => 15],
        'premium'  => ['max_price' => PHP_INT_MAX, 'min_rate' => 5,  'max_rate' => 20, 'default' => 10],
    ];

    protected array $campaignTypeBenchmarks = [
        'referral'  => ['min_rate' => 10, 'max_rate' => 50, 'default' => 25],
        'commission' => ['min_rate' => 5,  'max_rate' => 40, 'default' => 15],
        'reward'    => ['min_rate' => 8,  'max_rate' => 35, 'default' => 20],
        'rebate'    => ['min_rate' => 3,  'max_rate' => 20, 'default' => 10],
    ];

    /**
     * 为推广活动推荐最优佣金配置
     *
     * @param int|null $productId  关联商品 ID（可选）
     * @param string|null $campaignType 活动类型
     * @param float|null $productPrice  商品价格（可选，覆盖 productId 查到的价格）
     * @return array{suggested_rate: float, rate_range: array, confidence: float, reason: string, details: array}
     */
    public function recommendCommissionRate(
        ?int $productId = null,
        ?string $campaignType = null,
        ?float $productPrice = null,
    ): array {
        if ($productPrice === null && $productId !== null) {
            $product = Product::find($productId);
            $productPrice = $product?->base_price ?? 0;
        }

        $productPrice = max($productPrice ?? 0, 0);

        // 1. 基于价格区间的基准推荐
        $priceRec = $this->getPriceBenchmark($productPrice);

        // 2. 基于活动类型的基准推荐
        $typeRec = $this->getCampaignTypeBenchmark($campaignType);

        // 3. 历史转化数据学习
        $historyRec = $this->learnFromHistory($productPrice, $campaignType);

        // 4. 季节性调整
        $seasonalFactor = $this->getSeasonalAdjustment();

        // 加权融合
        $weights = ['price' => 0.3, 'type' => 0.25, 'history' => 0.35, 'seasonal' => 0.1];

        $weightedRate = 0;
        $confidence = 0;
        $reasons = [];
        $details = [];

        // 价格基准
        if ($priceRec) {
            $weightedRate += $priceRec['default'] * $weights['price'];
            $confidence += 20 * $weights['price'];
            $reasons[] = $priceRec['reason'];
            $details['price_benchmark'] = $priceRec;
        }

        // 活动类型基准
        if ($typeRec) {
            $weightedRate += $typeRec['default'] * $weights['type'];
            $confidence += 25 * $weights['type'];
            $reasons[] = $typeRec['reason'];
            $details['type_benchmark'] = $typeRec;
        }

        // 历史数据学习
        if ($historyRec) {
            $weightedRate += ($historyRec['avg_rate'] ?? $typeRec['default'] ?? 15) * $weights['history'];
            $confidence += ($historyRec['sample_size'] ?? 0) > 50 ? 40 : ($historyRec['sample_size'] ?? 0) / 1.25;
            if (!empty($historyRec['reason'])) {
                $reasons[] = $historyRec['reason'];
            }
            $details['history'] = $historyRec;
        } else {
            // 无历史数据时提升类型基准权重
            $typeWeight = $weights['type'] + $weights['history'];
            $typeMultiplier = $typeWeight / $weights['type'];
            if ($typeRec) {
                $weightedRate += $typeRec['default'] * ($typeWeight - $typeWeight / $typeMultiplier) ?? 0;
            }
        }

        // 季节性调整
        if ($seasonalFactor !== 1.0) {
            $weightedRate *= $seasonalFactor;
            $reasons[] = $seasonalFactor > 1
                ? '当前为旺季，建议适当上调佣金率以吸引更多推广者'
                : '当前为淡季，建议适当降低佣金率以控制成本';
        }
        $details['seasonal_factor'] = $seasonalFactor;

        // 计算最终范围
        $suggestedRate = round($weightedRate, 1);
        $suggestedRate = max(1, min($suggestedRate, 80));

        $rateRange = [
            'min' => max(1, round($suggestedRate * 0.7, 1)),
            'max' => min(80, round($suggestedRate * 1.3, 1)),
            'recommended' => $suggestedRate,
        ];

        // 置信度归一化到 0-100
        $confidence = min(100, max(10, round($confidence)));

        return [
            'suggested_rate' => $suggestedRate,
            'rate_range' => $rateRange,
            'confidence' => $confidence,
            'reason' => implode('；', array_filter($reasons)),
            'details' => $details,
        ];
    }

    /**
     * 批量推荐 — 为多个商品一次性推荐佣金率
     */
    public function batchRecommend(array $productIds, ?string $campaignType = null): array
    {
        $results = [];
        foreach ($productIds as $pid) {
            $results[$pid] = $this->recommendCommissionRate(
                productId: $pid,
                campaignType: $campaignType,
            );
        }
        return $results;
    }

    /**
     * 获取活动创建时的预设建议
     */
    public function getCampaignPresets(): array
    {
        return [
            'type_presets' => $this->campaignTypeBenchmarks,
            'price_bands' => array_map(function ($band) {
                unset($band['default']);
                return $band;
            }, $this->priceBenchmarks),
            'seasonal_factor' => $this->getSeasonalAdjustment(),
            'recommended_types' => $this->getRecommendedCampaignTypes(),
        ];
    }

    /**
     * 获取推荐的活动类型
     */
    protected function getRecommendedCampaignTypes(): array
    {
        $recentTypes = AffiliateCampaign::select('type', DB::raw('COUNT(*) as cnt'))
            ->where('status', 'active')
            ->groupBy('type')
            ->orderByDesc('cnt')
            ->limit(3)
            ->pluck('cnt', 'type')
            ->toArray();

        $typeLabels = [
            'referral' => '推荐返佣',
            'commission' => '佣金加成',
            'reward' => '奖励计划',
            'rebate' => '返现活动',
        ];

        $result = [];
        foreach ($recentTypes as $type => $count) {
            $result[] = [
                'type' => $type,
                'label' => $typeLabels[$type] ?? $type,
                'count' => $count,
            ];
        }

        return $result;
    }

    /**
     * 根据价格区间获取基准佣金率
     */
    protected function getPriceBenchmark(float $price): ?array
    {
        foreach ($this->priceBenchmarks as $band => $config) {
            if ($price <= $config['max_price']) {
                return [
                    'band' => $band,
                    'price' => $price,
                    'min_rate' => $config['min_rate'],
                    'max_rate' => $config['max_rate'],
                    'default' => $config['default'],
                    'reason' => "商品价格 ¥{$price}，{$this->getPriceBandLabel($band)}佣金率建议 {$config['min_rate']}%-{$config['max_rate']}%",
                ];
            }
        }
        return null;
    }

    /**
     * 获取活动类型基准
     */
    protected function getCampaignTypeBenchmark(?string $campaignType): ?array
    {
        if (!$campaignType || !isset($this->campaignTypeBenchmarks[$campaignType])) {
            return null;
        }

        $config = $this->campaignTypeBenchmarks[$campaignType];
        return [
            'type' => $campaignType,
            'min_rate' => $config['min_rate'],
            'max_rate' => $config['max_rate'],
            'default' => $config['default'],
            'reason' => "{$this->getCampaignTypeLabel($campaignType)}活动行业基准佣金率 {$config['min_rate']}%-{$config['max_rate']}%",
        ];
    }

    /**
     * 从历史转化数据学习最优佣金率
     */
    protected function learnFromHistory(float $productPrice, ?string $campaignType): ?array
    {
        // 找出相似价格区间活动的佣金与转化关系
        $cacheKey = "commission_ai_history_{$productPrice}_{$campaignType}";
        $ttl = $campaignType ? 3600 : 7200; // 带类型的缓存更短

        return Cache::remember($cacheKey, $ttl, function () use ($productPrice, $campaignType) {
            $query = AffiliateClick::select(
                'affiliate_campaigns.type',
                'affiliate_creatives.commission_rate',
                DB::raw('COUNT(*) as total_clicks'),
                DB::raw('SUM(CASE WHEN affiliate_clicks.converted THEN 1 ELSE 0 END) as conversions'),
            )
                ->leftJoin('affiliate_creatives', 'affiliate_clicks.creative_id', '=', 'affiliate_creatives.id')
                ->join('affiliate_campaigns', 'affiliate_clicks.campaign_id', '=', 'affiliate_campaigns.id')
                ->whereNotNull('affiliate_creatives.commission_rate')
                ->where('affiliate_creatives.commission_rate', '>', 0)
                ->where('affiliate_clicks.created_at', '>=', now()->subDays(90));

            // 按价格范围过滤（价格±30%范围内）
            if ($productPrice > 0) {
                $minPrice = $productPrice * 0.7;
                $maxPrice = $productPrice * 1.3;
                $query->whereBetween('affiliate_campaigns.reward_first', [$minPrice, $maxPrice]);
            }

            if ($campaignType) {
                $query->where('affiliate_campaigns.type', $campaignType);
            }

            $results = $query->groupBy('affiliate_campaigns.type', 'affiliate_creatives.commission_rate')
                ->orderByDesc('conversions')
                ->limit(30)
                ->get();

            if ($results->isEmpty()) {
                return null;
            }

            // 计算加权平均佣金率（按转化率加权）
            $totalWeight = 0;
            $weightedSum = 0;
            $totalConversions = 0;
            $totalClicks = 0;

            foreach ($results as $r) {
                $clickCount = max($r->total_clicks, 1);
                $conversionRate = $r->conversions / $clickCount;
                $weight = $r->conversions * (1 + $conversionRate); // 转化次数+转化率加权
                $weightedSum += $r->commission_rate * $weight;
                $totalWeight += $weight;
                $totalConversions += $r->conversions;
                $totalClicks += $r->total_clicks;
            }

            $avgRate = $totalWeight > 0 ? $weightedSum / $totalWeight : 15;
            $overallConversionRate = $totalClicks > 0 ? round(($totalConversions / $totalClicks) * 100, 2) : 0;

            $sampleSize = $totalClicks;
            $conversionDesc = $overallConversionRate > 5
                ? "历史转化率{$overallConversionRate}%，表现良好"
                : "历史转化率{$overallConversionRate}%，建议参考行业基准";

            return [
                'avg_rate' => round($avgRate, 1),
                'sample_size' => $sampleSize,
                'total_conversions' => $totalConversions,
                'conversion_rate' => $overallConversionRate,
                'reason' => "基于{$sampleSize}次点击、{$totalConversions}次转化的历史数据，最优佣金率约{$avgRate}%。{$conversionDesc}",
            ];
        });
    }

    /**
     * 季节性调整因子
     */
    protected function getSeasonalAdjustment(): float
    {
        $month = (int) now()->format('n');

        // 旺季：双11(11月)、双12(12月)、春节前(1月)
        if (in_array($month, [1, 11, 12])) {
            return 1.15;
        }

        // 次旺季：618(6月)、开学季(9月)
        if (in_array($month, [6, 9])) {
            return 1.08;
        }

        // 淡季：春节后(2-3月)
        if (in_array($month, [2, 3])) {
            return 0.9;
        }

        return 1.0;
    }

    protected function getPriceBandLabel(string $band): string
    {
        return match ($band) {
            'low' => '低价位商品',
            'medium' => '中等价位商品',
            'high' => '高价位商品',
            'premium' => '高端商品',
            default => '',
        };
    }

    protected function getCampaignTypeLabel(string $type): string
    {
        return match ($type) {
            'referral' => '推荐返佣',
            'commission' => '佣金加成',
            'reward' => '奖励计划',
            'rebate' => '返现活动',
            default => $type,
        };
    }

    /**
     * 分析历史活动中的转化率-佣金关系
     */
    public function analyzeCommissionEfficiency(int $tenantId, int $days = 90): array
    {
        $query = AffiliateClick::select(
            'affiliate_campaigns.type',
            'affiliate_creatives.commission_rate',
            DB::raw('COUNT(*) as total_clicks'),
            DB::raw('SUM(CASE WHEN affiliate_clicks.converted THEN 1 ELSE 0 END) as conversions'),
            DB::raw('AVG(affiliate_clicks.commission_amount) as avg_amount'),
        )
            ->leftJoin('affiliate_creatives', 'affiliate_clicks.creative_id', '=', 'affiliate_creatives.id')
            ->join('affiliate_campaigns', 'affiliate_clicks.campaign_id', '=', 'affiliate_campaigns.id')
            ->where('affiliate_clicks.created_at', '>=', now()->subDays($days))
            ->where('affiliate_creatives.commission_rate', '>', 0);

        $results = $query->groupBy('affiliate_campaigns.type', 'affiliate_creatives.commission_rate')
            ->orderByDesc('conversions')
            ->get()
            ->toArray();

        // 按活动类型分组
        $byType = [];
        foreach ($results as $r) {
            $type = $r['type'] ?? 'unknown';
            if (!isset($byType[$type])) {
                $byType[$type] = ['clicks' => 0, 'conversions' => 0, 'rate_groups' => []];
            }
            $byType[$type]['clicks'] += $r['total_clicks'];
            $byType[$type]['conversions'] += $r['conversions'];
            $byType[$type]['rate_groups'][] = [
                'rate' => (float) $r['commission_rate'],
                'clicks' => (int) $r['total_clicks'],
                'conversions' => (int) $r['conversions'],
                'conversion_rate' => $r['total_clicks'] > 0
                    ? round(($r['conversions'] / $r['total_clicks']) * 100, 2) : 0,
                'avg_amount' => round((float) ($r['avg_amount'] ?? 0), 2),
            ];
        }

        // 寻找最优佣金率区间
        $optimalRates = [];
        foreach ($byType as $type => $data) {
            $bestRate = null;
            $bestConversionRate = 0;

            foreach ($data['rate_groups'] as $group) {
                if ($group['conversion_rate'] > $bestConversionRate && $group['clicks'] >= 10) {
                    $bestConversionRate = $group['conversion_rate'];
                    $bestRate = $group['rate'];
                }
            }

            $optimalRates[$type] = [
                'optimal_rate' => $bestRate,
                'conversion_rate' => $bestConversionRate,
                'total_clicks' => $data['clicks'],
                'total_conversions' => $data['conversions'],
                'overall_conversion_rate' => $data['clicks'] > 0
                    ? round(($data['conversions'] / $data['clicks']) * 100, 2) : 0,
            ];
        }

        return [
            'period_days' => $days,
            'total_clicks' => array_sum(array_column($byType, 'clicks')),
            'total_conversions' => array_sum(array_column($byType, 'conversions')),
            'by_type' => $byType,
            'optimal_rates' => $optimalRates,
        ];
    }
}
