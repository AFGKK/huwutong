<?php

namespace App\Services;

use App\Models\CrossSellRecommendation;
use App\Models\Customer;
use App\Models\Device;
use App\Models\License;
use App\Models\LicenseAnalyticsEvent;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * M3-35 AI 交叉销售推荐引擎
 *
 * 基于使用模式+相似客户+Feature Flag使用率→推荐升级套餐/附加模块/增购产品
 */
class CrossSellService
{
    /**
     * 为指定客户生成推荐
     */
    public function generateRecommendations(int $customerId, int $limit = 6): array
    {
        $customer = Customer::with('licenses.product')->findOrFail($customerId);
        $tenantId = $customer->tenant_id;
        $recommendations = [];

        // 各策略并行打分
        $usageRecs = $this->usageBasedRecommendations($customer);
        $similarRecs = $this->similarCustomerRecommendations($customer);
        $popularRecs = $this->popularRecommendations($tenantId, $customer);
        $complementaryRecs = $this->complementaryRecommendations($customer);

        // 合并评分
        $allRecs = array_merge($usageRecs, $similarRecs, $popularRecs, $complementaryRecs);

        // 按评分排序去重
        $seen = [];
        usort($allRecs, fn($a, $b) => $b['score'] <=> $a['score']);

        foreach ($allRecs as $rec) {
            $key = $rec['recommendable_type'] . ':' . $rec['recommendable_id'];
            if (isset($seen[$key])) continue;
            $seen[$key] = true;

            if (count($recommendations) >= $limit) break;
            $recommendations[] = $rec;
        }

        // 存储推荐记录
        foreach ($recommendations as &$rec) {
            $model = CrossSellRecommendation::create([
                'tenant_id' => $tenantId,
                'customer_id' => $customerId,
                'strategy' => $rec['strategy'],
                'recommendation_type' => $rec['recommendation_type'],
                'recommendable_type' => $rec['recommendable_type'],
                'recommendable_id' => $rec['recommendable_id'],
                'score' => $rec['score'],
                'confidence' => $rec['confidence'],
                'reason' => $rec['reason'],
                'context' => $rec['context'] ?? null,
            ]);
            $rec['id'] = $model->id;
        }

        return $recommendations;
    }

    /**
     * 基于使用模式的升级推荐
     */
    protected function usageBasedRecommendations(Customer $customer): array
    {
        $recs = [];
        $licenses = $customer->licenses;

        foreach ($licenses as $license) {
            $context = $this->analyzeUsageContext($license);

            // 设备数接近上限→推荐升级
            $deviceCount = Device::where('license_id', $license->id)->count();
            $maxDevices = $license->max_devices ?: 1;
            $deviceRatio = $maxDevices > 0 ? $deviceCount / $maxDevices : 0;

            if ($deviceRatio >= 0.8 && $license->product?->upgradable_product_id) {
                $recs[] = [
                    'strategy' => 'usage_based',
                    'recommendation_type' => 'upgrade',
                    'recommendable_type' => Product::class,
                    'recommendable_id' => $license->product->upgradable_product_id,
                    'score' => 0.85,
                    'confidence' => 0.75,
                    'reason' => "设备使用率达{$deviceRatio}%，建议升级以获取更多设备授权",
                    'context' => ['current_devices' => $deviceCount, 'max_devices' => $maxDevices],
                ];
            }

            // 临近到期→推荐续费套餐
            if ($license->expires_at && $license->expires_at->diffInDays(now()) <= 30) {
                $recs[] = [
                    'strategy' => 'usage_based',
                    'recommendation_type' => 'upgrade',
                    'recommendable_type' => Product::class,
                    'recommendable_id' => $license->product_id,
                    'score' => 0.75,
                    'confidence' => 0.9,
                    'reason' => "License即将在{$license->expires_at->diffInDays(now())}天后到期，建议提前续费",
                    'context' => ['expires_at' => $license->expires_at->toIso8601String()],
                ];
            }

            // 高激活率→推荐扩容
            if ($deviceRatio >= 0.7) {
                $recs[] = [
                    'strategy' => 'usage_based',
                    'recommendation_type' => 'add_on',
                    'recommendable_type' => 'device_slot',
                    'recommendable_id' => 0,
                    'score' => 0.7,
                    'confidence' => 0.65,
                    'reason' => '设备激活率较高，推荐购买额外设备授权',
                    'context' => ['device_ratio' => $deviceRatio],
                ];
            }
        }

        return $recs;
    }

    /**
     * 基于相似客户的推荐
     */
    protected function similarCustomerRecommendations(Customer $customer): array
    {
        $recs = [];

        // 找到同行业/同规模的客户购买了哪些额外产品
        $similarCustomerIds = Customer::where('tenant_id', $customer->tenant_id)
            ->where('id', '!=', $customer->id)
            ->where(function ($q) use ($customer) {
                if ($customer->industry) {
                    $q->where('industry', $customer->industry);
                }
            })
            ->limit(50)
            ->pluck('id');

        if ($similarCustomerIds->isEmpty()) return $recs;

        $customerProductIds = $customer->licenses()->pluck('product_id')->toArray();

        $popularAddons = License::whereIn('customer_id', $similarCustomerIds)
            ->whereNotIn('product_id', $customerProductIds)
            ->select('product_id', DB::raw('COUNT(*) as count'))
            ->groupBy('product_id')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        foreach ($popularAddons as $addon) {
            $product = Product::find($addon->product_id);
            if (!$product) continue;

            $recs[] = [
                'strategy' => 'similar_customers',
                'recommendation_type' => 'product',
                'recommendable_type' => Product::class,
                'recommendable_id' => $product->id,
                'score' => min(0.6 + ($addon->count * 0.02), 0.9),
                'confidence' => 0.5,
                'reason' => "同行业{$addon->count}家客户已选用「{$product->name}」",
                'context' => ['similar_count' => $addon->count],
            ];
        }

        return $recs;
    }

    /**
     * 热销排行推荐
     */
    protected function popularRecommendations(int $tenantId, Customer $customer): array
    {
        $recs = [];
        $ownedProductIds = $customer->licenses()->pluck('product_id')->toArray();

        $popular = License::where('tenant_id', $tenantId)
            ->whereNotIn('product_id', $ownedProductIds)
            ->select('product_id', DB::raw('COUNT(*) as total'))
            ->groupBy('product_id')
            ->orderByDesc('total')
            ->limit(3)
            ->get();

        foreach ($popular as $p) {
            $product = Product::find($p->product_id);
            if (!$product) continue;

            $recs[] = [
                'strategy' => 'popular',
                'recommendation_type' => 'product',
                'recommendable_type' => Product::class,
                'recommendable_id' => $product->id,
                'score' => 0.5,
                'confidence' => 0.4,
                'reason' => "热销产品「{$product->name}」已售{$p->total}份",
                'context' => ['sales_count' => $p->total],
            ];
        }

        return $recs;
    }

    /**
     * 互补产品推荐
     */
    protected function complementaryRecommendations(Customer $customer): array
    {
        $recs = [];
        $ownedProductIds = $customer->licenses()->pluck('product_id')->toArray();

        // 互补产品映射（规则引擎）
        $complementMap = [
            // API产品→推荐SDK缓存
            'api' => ['offline', 'sdk_cache'],
            // 核心授权→推荐高可用
            'core' => ['offline', 'redundancy'],
            // 基础版→推荐高级模块
            'basic' => ['analytics', 'audit', 'sso'],
        ];

        foreach ($ownedProductIds as $productId) {
            $product = Product::find($productId);
            if (!$product) continue;

            $productKey = strtolower($product->name ?? $product->code ?? '');
            foreach ($complementMap as $key => $complementary) {
                if (str_contains($productKey, $key)) {
                    foreach ($complementary as $compKey) {
                        $compProduct = Product::where('code', $compKey)
                            ->orWhere('name', 'like', "%{$compKey}%")
                            ->first();
                        if ($compProduct && !in_array($compProduct->id, $ownedProductIds)) {
                            $recs[] = [
                                'strategy' => 'complementary',
                                'recommendation_type' => 'add_on',
                                'recommendable_type' => Product::class,
                                'recommendable_id' => $compProduct->id,
                                'score' => 0.6,
                                'confidence' => 0.55,
                                'reason' => "配合「{$product->name}」使用「{$compProduct->name}」效果更佳",
                                'context' => ['complement_to' => $product->name],
                            ];
                        }
                    }
                }
            }
        }

        return $recs;
    }

    /**
     * 分析使用上下文
     */
    protected function analyzeUsageContext(License $license): array
    {
        $thirtyDaysAgo = now()->subDays(30);

        $activationCount = LicenseAnalyticsEvent::where('license_id', $license->id)
            ->where('event_type', 'activation')
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->count();

        $deviceCount = Device::where('license_id', $license->id)->count();
        $moduleUsage = LicenseAnalyticsEvent::where('license_id', $license->id)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->distinct('event_type')
            ->count('event_type');

        return compact('activationCount', 'deviceCount', 'moduleUsage');
    }

    /**
     * 记录推荐事件
     */
    public function recordEvent(int $recommendationId, string $eventType, array $data = []): void
    {
        $rec = CrossSellRecommendation::find($recommendationId);
        if (!$rec) return;

        $rec->events()->create([
            'event_type' => $eventType,
            'event_data' => $data,
        ]);

        $timestampField = match ($eventType) {
            'shown' => 'shown_at',
            'clicked' => 'clicked_at',
            'converted' => 'converted_at',
            default => null,
        };

        if ($timestampField) {
            $rec->update([$timestampField => now(), 'status' => $eventType]);
        }
    }

    /**
     * 获取仪表盘
     */
    public function getDashboard(int $tenantId): array
    {
        $totalRecs = CrossSellRecommendation::where('tenant_id', $tenantId)->count();
        $shown = CrossSellRecommendation::where('tenant_id', $tenantId)->whereNotNull('shown_at')->count();
        $clicked = CrossSellRecommendation::where('tenant_id', $tenantId)->whereNotNull('clicked_at')->count();
        $converted = CrossSellRecommendation::where('tenant_id', $tenantId)->whereNotNull('converted_at')->count();

        $byStrategy = CrossSellRecommendation::where('tenant_id', $tenantId)
            ->selectRaw('strategy, COUNT(*) as count')
            ->groupBy('strategy')
            ->pluck('count', 'strategy')
            ->toArray();

        $conversionRate = $shown > 0 ? round(($converted / $shown) * 100, 2) : 0;

        return compact('totalRecs', 'shown', 'clicked', 'converted', 'conversionRate', 'byStrategy');
    }
}
