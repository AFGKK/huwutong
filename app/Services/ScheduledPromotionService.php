<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\ProductSku;
use App\Models\Promotion;
use App\Models\PromotionCalendarEvent;
use App\Models\SkuSpecialPrice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 定时上下架+定时促销+白名单购买 (M2-151 🛒)
 *
 * 覆盖：
 * - 商品定时上架/下架
 * - 限时折扣/秒杀/首单折扣/会员专享价
 * - 活动日历管理
 * - 自动恢复原价
 * - 白名单客户可见/可购控制
 */
class ScheduledPromotionService
{
    /**
     * 创建定时促销活动
     */
    public function createPromotion(array $data): Promotion
    {
        return DB::transaction(function () use ($data) {
            $promotion = Promotion::create([
                'name' => $data['name'],
                'slug' => $data['slug'] ?? \Illuminate\Support\Str::slug($data['name']) . '-' . \Illuminate\Support\Str::random(4),
                'type' => $data['type'] ?? 'flash_sale',
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? 'draft',
                'discount_type' => $data['discount_type'] ?? 'percentage',
                'discount_value' => $data['discount_value'] ?? 0,
                'max_discount' => $data['max_discount'] ?? null,
                'min_order_amount' => $data['min_order_amount'] ?? null,
                'applicable_products' => $data['applicable_products'] ?? null,
                'applicable_skus' => $data['applicable_skus'] ?? null,
                'usage_limit' => $data['usage_limit'] ?? null,
                'usage_limit_per_customer' => $data['usage_limit_per_customer'] ?? null,
                'budget' => $data['budget'] ?? null,
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'] ?? null,
                'whitelist_customers' => $data['whitelist_customers'] ?? null,
                'is_first_order_only' => $data['is_first_order_only'] ?? false,
                'is_member_only' => $data['is_member_only'] ?? false,
                'member_tier' => $data['member_tier'] ?? null,
                'auto_recover' => $data['auto_recover'] ?? true,
                'display_config' => $data['display_config'] ?? null,
                'created_by' => auth()->id(),
            ]);

            // 创建日历事件
            $this->syncCalendarEvent($promotion);

            // 创建SKU专享价
            if (!empty($data['sku_special_prices'])) {
                $this->setSkuSpecialPrices($promotion, $data['sku_special_prices']);
            }

            return $promotion->fresh();
        });
    }

    /**
     * 更新促销活动
     */
    public function updatePromotion(Promotion $promotion, array $data): Promotion
    {
        return DB::transaction(function () use ($promotion, $data) {
            $promotion->update($data);

            $this->syncCalendarEvent($promotion);

            if (isset($data['sku_special_prices'])) {
                SkuSpecialPrice::where('promotion_id', $promotion->id)->delete();
                $this->setSkuSpecialPrices($promotion, $data['sku_special_prices']);
            }

            return $promotion->fresh();
        });
    }

    /**
     * 发布活动（到达开始时间自动激活）
     */
    public function publish(Promotion $promotion): Promotion
    {
        $now = now();
        $status = $promotion->starts_at <= $now ? 'active' : 'scheduled';
        $promotion->update([
            'status' => $status,
            'published_at' => $now,
        ]);

        // 如果已到开始时间，立即应用促销价
        if ($status === 'active') {
            $this->applyPromotionPrices($promotion);
        }

        return $promotion->fresh();
    }

    /**
     * 暂停活动
     */
    public function pause(Promotion $promotion): Promotion
    {
        $promotion->update(['status' => 'paused']);

        // 恢复原价
        if ($promotion->auto_recover) {
            $this->recoverOriginalPrices($promotion);
        }

        return $promotion->fresh();
    }

    /**
     * 检查并自动激活/过期活动（供定时任务调用）
     */
    public function processScheduledPromotions(): array
    {
        $now = now();
        $activated = 0;
        $expired = 0;

        // 激活已到开始时间的待开始活动
        $toActivate = Promotion::whereIn('status', ['scheduled', 'draft'])
            ->where('starts_at', '<=', $now)
            ->get();

        foreach ($toActivate as $promotion) {
            $promotion->update(['status' => 'active']);
            $this->applyPromotionPrices($promotion);
            $activated++;
        }

        // 过期已到结束时间的活动
        $toExpire = Promotion::where('status', 'active')
            ->where('ends_at', '<=', $now)
            ->get();

        foreach ($toExpire as $promotion) {
            $promotion->update(['status' => 'expired']);
            if ($promotion->auto_recover) {
                $this->recoverOriginalPrices($promotion);
            }
            $expired++;
        }

        if ($activated > 0 || $expired > 0) {
            Log::info('定时促销处理完成', [
                'activated' => $activated,
                'expired' => $expired,
            ]);
        }

        return compact('activated', 'expired');
    }

    /**
     * 检查客户是否有资格享受促销
     */
    public function checkCustomerEligibility(Promotion $promotion, int $customerId): array
    {
        $customer = Customer::find($customerId);
        if (!$customer) {
            return ['eligible' => false, 'reason' => '客户不存在'];
        }

        // 白名单检查
        $whitelist = $promotion->whitelist_customers;
        if (!empty($whitelist)) {
            $whitelistIds = is_array($whitelist) ? $whitelist : json_decode($whitelist, true);
            if (!in_array($customerId, $whitelistIds)) {
                return ['eligible' => false, 'reason' => '仅限白名单客户'];
            }
        }

        // 首单检查
        if ($promotion->is_first_order_only) {
            $orderCount = Order::where('customer_id', $customerId)->count();
            if ($orderCount > 0) {
                return ['eligible' => false, 'reason' => '仅限首单客户'];
            }
        }

        // 会员等级检查
        if ($promotion->is_member_only && $promotion->member_tier) {
            $customerTier = $customer->tier ?? 'regular';
            $tierHierarchy = ['regular' => 0, 'silver' => 1, 'gold' => 2, 'platinum' => 3];
            $requiredLevel = $tierHierarchy[$promotion->member_tier] ?? 1;
            $customerLevel = $tierHierarchy[$customerTier] ?? 0;
            if ($customerLevel < $requiredLevel) {
                return ['eligible' => false, 'reason' => "需要{$promotion->member_tier}及以上会员等级"];
            }
        }

        return ['eligible' => true, 'reason' => null];
    }

    /**
     * 获取客户可见的促销活动列表（前端展示用）
     */
    public function getVisiblePromotions(int $customerId): array
    {
        $now = now();
        $promotions = Promotion::active()
            ->where(function ($q) use ($customerId) {
                $q->whereNull('whitelist_customers')
                  ->orWhereJsonContains('whitelist_customers', $customerId);
            })
            ->orderBy('starts_at')
            ->get();

        return $promotions->filter(function ($promotion) use ($customerId) {
            $result = $this->checkCustomerEligibility($promotion, $customerId);
            return $result['eligible'];
        })->values()->toArray();
    }

    /**
     * 应用促销价格到SKU
     */
    protected function applyPromotionPrices(Promotion $promotion): void
    {
        $skuIds = $promotion->applicable_skus;
        if (empty($skuIds)) return;

        $skuIds = is_array($skuIds) ? $skuIds : json_decode($skuIds, true);
        $skus = ProductSku::whereIn('id', $skuIds)->get();

        foreach ($skus as $sku) {
            $discountedPrice = $promotion->calculateDiscount((float) $sku->price);

            // 保存原价到meta，记录促销价
            $meta = $sku->meta ?? [];
            $meta['promotion_id'] = $promotion->id;
            $meta['original_price'] = $sku->price;
            $meta['promotion_price'] = $discountedPrice;
            $meta['promotion_ends_at'] = $promotion->ends_at?->toIso8601String();

            $sku->updateQuietly([
                'price' => $discountedPrice,
                'meta' => $meta,
            ]);
        }
    }

    /**
     * 恢复SKU原价
     */
    protected function recoverOriginalPrices(Promotion $promotion): void
    {
        $skuIds = $promotion->applicable_skus;
        if (empty($skuIds)) return;

        $skuIds = is_array($skuIds) ? $skuIds : json_decode($skuIds, true);
        $skus = ProductSku::whereIn('id', $skuIds)->get();

        foreach ($skus as $sku) {
            $meta = $sku->meta ?? [];
            $originalPrice = $meta['original_price'] ?? null;
            if ($originalPrice !== null && ($meta['promotion_id'] ?? null) == $promotion->id) {
                unset($meta['promotion_id'], $meta['original_price'], $meta['promotion_price'], $meta['promotion_ends_at']);
                $sku->updateQuietly([
                    'price' => $originalPrice,
                    'meta' => $meta,
                ]);
            }
        }
    }

    /**
     * 设置SKU专享价
     */
    protected function setSkuSpecialPrices(Promotion $promotion, array $prices): void
    {
        foreach ($prices as $priceData) {
            SkuSpecialPrice::create([
                'sku_id' => $priceData['sku_id'],
                'promotion_id' => $promotion->id,
                'type' => $priceData['type'] ?? 'flash_sale',
                'tier' => $priceData['tier'] ?? null,
                'price' => $priceData['price'],
                'customer_id' => $priceData['customer_id'] ?? null,
                'starts_at' => $promotion->starts_at,
                'ends_at' => $promotion->ends_at,
            ]);
        }
    }

    /**
     * 同步日历事件
     */
    protected function syncCalendarEvent(Promotion $promotion): void
    {
        PromotionCalendarEvent::updateOrCreate(
            ['promotion_id' => $promotion->id],
            [
                'title' => $promotion->name,
                'color' => $this->getTypeColor($promotion->type),
                'start_at' => $promotion->starts_at,
                'end_at' => $promotion->ends_at,
                'status' => $promotion->status,
            ]
        );
    }

    /**
     * 获取促销类型对应颜色
     */
    protected function getTypeColor(string $type): string
    {
        return match ($type) {
            'flash_sale' => '#F56C6C',
            'bulk_discount' => '#0f172a',
            'bundle' => '#67C23A',
            'x_for_y' => '#E6A23C',
            'free_gift' => '#909399',
            'tiered' => '#9B59B6',
            default => '#0f172a',
        };
    }

    /**
     * 获取日历事件列表
     */
    public function getCalendarEvents(int $tenantId, ?string $month = null): array
    {
        $query = PromotionCalendarEvent::with('promotion')
            ->whereHas('promotion', fn($q) => $q->where('created_by', auth()->id()));

        if ($month) {
            $start = Carbon::parse($month)->startOfMonth();
            $end = $start->copy()->endOfMonth();
            $query->whereBetween('start_at', [$start, $end])
                  ->orWhereBetween('end_at', [$start, $end]);
        }

        return $query->orderBy('start_at')->get()->toArray();
    }

    /**
     * 获取活动日历统计数据
     */
    public function getStats(int $tenantId): array
    {
        $base = Promotion::query();

        return [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('status', 'active')->count(),
            'scheduled' => (clone $base)->where('status', 'scheduled')->count(),
            'expired' => (clone $base)->where('status', 'expired')->count(),
            'draft' => (clone $base)->where('status', 'draft')->count(),
            'total_budget' => (clone $base)->sum('budget'),
            'total_spent' => (clone $base)->sum('budget_spent'),
        ];
    }
}
