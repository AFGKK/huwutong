<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\PromotionRule;
use App\Models\PromotionRuleRedemption;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * 满减/满折促销引擎
 *
 * 支持的促销类型：
 * - amount_off: 满减（固定金额减免）
 * - percent_off: 满折（百分比折扣）
 * - buy_x_get_y: 买N送N
 * - fixed_price: 一口价
 *
 * 支持多级阶梯规则（tiers）：
 * - [{"from": 200, "to": 500, "type": "amount_off", "value": 20}]
 * - [{"from": 500, "to": 1000, "type": "amount_off", "value": 80}]
 * - [{"from": 1000, "to": null, "type": "percent_off", "value": 15}]
 */
class PromotionEngineService
{
    /**
     * 计算促销折扣
     *
     * @param PromotionRule $rule 促销规则
     * @param float $subtotal 订单小计
     * @param int $itemCount 商品总件数
     * @param array $productIds 商品ID列表
     * @param array $categoryIds 分类ID列表
     * @return array {discount, tier_applied, description}
     */
    public function calculateDiscount(
        PromotionRule $rule,
        float $subtotal,
        int $itemCount = 0,
        array $productIds = [],
        array $categoryIds = [],
    ): array {
        if (!$rule->isActive() || !$rule->hasUsageLeft() || !$rule->hasBudgetLeft()) {
            return ['discount' => 0, 'tier_applied' => null, 'description' => null];
        }

        // 检查最低订单金额
        if ($rule->min_order_amount > 0 && $subtotal < $rule->min_order_amount) {
            return ['discount' => 0, 'tier_applied' => null, 'description' => null];
        }

        // 检查适用商品
        if (!empty($rule->applicable_products)) {
            $matched = array_intersect($productIds, $rule->applicable_products);
            if (empty($matched)) {
                return ['discount' => 0, 'tier_applied' => null, 'description' => null];
            }
        }

        // 检查排除商品
        if (!empty($rule->excluded_products)) {
            $excluded = array_intersect($productIds, $rule->excluded_products);
            if (!empty($excluded)) {
                return ['discount' => 0, 'tier_applied' => null, 'description' => null];
            }
        }

        // 检查条件门槛
        $conditionMet = $this->checkCondition($rule, $subtotal, $itemCount);
        if (!$conditionMet) {
            return ['discount' => 0, 'tier_applied' => null, 'description' => null];
        }

        // 多级阶梯处理
        if (!empty($rule->tiers)) {
            return $this->calculateTieredDiscount($rule, $subtotal);
        }

        // 买N送N
        if ($rule->type === 'buy_x_get_y' && $rule->buy_quantity && $rule->free_quantity) {
            return $this->calculateBuyXGetY($rule, $itemCount);
        }

        // 单级折扣
        return $this->calculateSimpleDiscount($rule, $subtotal);
    }

    /**
     * 检查条件门槛
     */
    protected function checkCondition(PromotionRule $rule, float $subtotal, int $itemCount): bool
    {
        return match ($rule->condition_type) {
            'subtotal' => $subtotal >= $rule->condition_value,
            'quantity' => $itemCount >= (int) $rule->condition_value,
            'items_count' => $itemCount >= (int) $rule->condition_value,
            default => $subtotal >= $rule->condition_value,
        };
    }

    /**
     * 计算多级阶梯折扣
     */
    protected function calculateTieredDiscount(PromotionRule $rule, float $subtotal): array
    {
        $tiers = $rule->tiers;
        usort($tiers, fn($a, $b) => ($a['from'] ?? 0) <=> ($b['from'] ?? 0));

        $appliedTier = null;

        foreach ($tiers as $tier) {
            $from = (float) ($tier['from'] ?? 0);
            $to = isset($tier['to']) ? (float) $tier['to'] : null;

            if ($subtotal >= $from && ($to === null || $subtotal < $to)) {
                $appliedTier = $tier;
                break;
            }
        }

        if (!$appliedTier) {
            return ['discount' => 0, 'tier_applied' => null, 'description' => null];
        }

        $discount = $this->calculateDiscountByType(
            $appliedTier['type'] ?? $rule->type,
            (float) ($appliedTier['value'] ?? 0),
            $subtotal,
            $rule->max_discount,
        );

        return [
            'discount' => $discount,
            'tier_applied' => $appliedTier,
            'description' => $this->buildTierDescription($appliedTier),
        ];
    }

    /**
     * 计算单级折扣
     */
    protected function calculateSimpleDiscount(PromotionRule $rule, float $subtotal): array
    {
        $discount = $this->calculateDiscountByType(
            $rule->type,
            (float) $rule->discount_value,
            $subtotal,
            $rule->max_discount,
        );

        return [
            'discount' => $discount,
            'tier_applied' => null,
            'description' => $this->buildSimpleDescription($rule, $discount),
        ];
    }

    /**
     * 按类型计算折扣
     */
    protected function calculateDiscountByType(string $type, float $value, float $subtotal, ?float $maxDiscount): float
    {
        return match ($type) {
            'amount_off', 'fixed_amount' => min($value, $subtotal),
            'percent_off', 'percentage' => $this->applyMaxDiscount($subtotal * ($value / 100), $maxDiscount),
            'fixed_price' => max(0, $subtotal - $value),
            default => 0,
        };
    }

    /**
     * 计算买N送N
     */
    protected function calculateBuyXGetY(PromotionRule $rule, int $itemCount): array
    {
        if ($itemCount < $rule->buy_quantity) {
            return ['discount' => 0, 'tier_applied' => null, 'description' => null];
        }

        $freeTimes = intdiv($itemCount, $rule->buy_quantity + $rule->free_quantity);
        $freeTimes = max(1, $freeTimes);
        $freeItems = min($freeTimes * $rule->free_quantity, $rule->free_quantity);

        return [
            'discount' => 0, // 具体金额需要在应用时根据商品价格计算
            'tier_applied' => null,
            'description' => "买{$rule->buy_quantity}送{$rule->free_quantity}（可享{$freeItems}件免费）",
            'free_items' => $freeItems,
            'free_products' => $rule->free_products,
        ];
    }

    /**
     * 应用最大折扣限制
     */
    protected function applyMaxDiscount(float $discount, ?float $maxDiscount): float
    {
        if ($maxDiscount !== null && $discount > $maxDiscount) {
            return $maxDiscount;
        }
        return round($discount, 2);
    }

    /**
     * 执行促销（验证 + 应用折扣 + 记录使用）
     */
    public function applyPromotion(
        PromotionRule $rule,
        Customer $customer,
        float $subtotal,
        int $itemCount = 0,
        array $productIds = [],
        array $categoryIds = [],
        ?array $context = [],
    ): array {
        // 1. 计算折扣
        $result = $this->calculateDiscount($rule, $subtotal, $itemCount, $productIds, $categoryIds);

        if ($result['discount'] <= 0 && !isset($result['free_items'])) {
            throw new Exception(__("app.promotion_engine.promotion_conditions_not_met"));
        }

        // 2. 验证客户限制
        if ($rule->usage_limit_per_customer) {
            $usageCount = PromotionRuleRedemption::where('promotion_rule_id', $rule->id)
                ->where('customer_id', $customer->id)
                ->count();
            if ($usageCount >= $rule->usage_limit_per_customer) {
                throw new Exception(__("app.promotion_engine.promotion_customer_usage_limit"));
            }
        }

        // 3. 记录使用（事务）
        return DB::transaction(function () use ($rule, $customer, $subtotal, $result, $context) {
            $discount = $result['discount'];

            $redemption = PromotionRuleRedemption::create([
                'promotion_rule_id' => $rule->id,
                'tenant_id' => $rule->tenant_id ?? $customer->tenant_id,
                'customer_id' => $customer->id,
                'original_amount' => $subtotal,
                'discount_amount' => $discount,
                'final_amount' => $subtotal - $discount,
                'currency' => 'CNY',
                'tier_applied' => $result['tier_applied'],
                'context' => $context,
            ]);

            $rule->increment('usage_count');
            if ($rule->budget !== null) {
                $rule->increment('budget_spent', $discount);
            }

            return [
                'redemption' => $redemption,
                'discount' => $discount,
                'final_amount' => $subtotal - $discount,
                'tier_applied' => $result['tier_applied'],
                'description' => $result['description'],
            ];
        });
    }

    /**
     * 批量计算多个促销规则的最佳组合
     */
    public function findBestPromotion(
        array $rules,
        float $subtotal,
        int $itemCount = 0,
        array $productIds = [],
        array $categoryIds = [],
    ): array {
        $results = [];

        foreach ($rules as $rule) {
            if (!($rule instanceof PromotionRule)) continue;

            // 检查叠加限制
            if (!empty($results) && !$rule->stackable_with_other_rules) {
                continue;
            }

            $result = $this->calculateDiscount($rule, $subtotal, $itemCount, $productIds, $categoryIds);
            if ($result['discount'] > 0) {
                $results[] = array_merge($result, [
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->name,
                    'rule_type' => $rule->type,
                ]);
            }
        }

        // 按折扣金额降序排序
        usort($results, fn($a, $b) => $b['discount'] <=> $a['discount']);

        // 检查叠加限制：第一个之后不再叠加
        $best = !empty($results) ? $results[0] : null;

        // 如果能叠加，选折扣最高的组合
        $combinedDiscount = 0;
        $combinedRules = [];
        foreach ($results as $r) {
            if (empty($combinedRules)) {
                $combinedDiscount = $r['discount'];
                $combinedRules[] = $r;
            } elseif ($r['stackable_with_other_rules'] ?? false) {
                $combinedDiscount += $r['discount'];
                $combinedRules[] = $r;
            } else {
                break;
            }
        }

        return [
            'best_single' => $best,
            'best_combined' => [
                'rules' => $combinedRules,
                'total_discount' => $combinedDiscount,
                'final_amount' => $subtotal - $combinedDiscount,
            ],
        ];
    }

    /**
     * 验证优惠券与促销规则是否可以叠加
     */
    public function checkStackability(PromotionRule $rule, bool $hasCoupon): array
    {
        $canStack = true;
        $reason = null;

        if ($hasCoupon && !$rule->stackable_with_coupon) {
            $canStack = false;
            $reason = '该促销规则不可与优惠券叠加';
        }

        return [
            'can_stack' => $canStack,
            'reason' => $reason,
        ];
    }

    /**
     * 构建描述文本
     */
    protected function buildSimpleDescription(PromotionRule $rule, float $discount): string
    {
        return match ($rule->type) {
            'amount_off' => "满减 ¥{$discount}",
            'percent_off' => "打{$rule->discount_value}折，省¥{$discount}",
            'fixed_price' => "一口价 ¥{$rule->discount_value}",
            default => $rule->name,
        };
    }

    protected function buildTierDescription(array $tier): string
    {
        $value = $tier['value'] ?? 0;
        $type = $tier['type'] ?? 'amount_off';
        $from = $tier['from'] ?? 0;
        $to = $tier['to'] ?? '∞';

        $discountStr = $type === 'percent_off' ? "{$value}%" : "¥{$value}";
        return "满¥{$from}减{$discountStr}（上限¥{$to}）";
    }

    /**
     * 获取活跃促销规则
     */
    public function getActiveRules(?int $tenantId = null, array $types = []): array
    {
        $query = PromotionRule::where('status', 'active');

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        if (!empty($types)) {
            $query->whereIn('type', $types);
        }

        return $query
            ->orderBy('priority')
            ->orderByDesc('created_at')
            ->get()
            ->toArray();
    }
}
