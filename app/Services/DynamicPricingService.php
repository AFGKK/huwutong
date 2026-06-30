<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerSegment;
use App\Models\DynamicPricingRule;
use App\Models\PricingPlan;
use App\Models\PricingTier;
use App\Models\PricingExperiment;
use App\Models\PricingExperimentParticipant;
use App\Models\PricingExperimentEvent;
use App\Models\PricingRuleApplicationLog;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 动态定价引擎
 *
 * 支持：
 * - 阶梯定价 (Tiered): 根据购买数量自动适用阶梯价
 * - 客户细分定价 (Segment): 根据客户分群应用不同价格
 * - 季节性定价 (Seasonal): 根据日期/时段动态调整
 * - 促销定价 (Promotion): 限时折扣
 * - LLM 辅助优化 (LLM Optimized): AI 建议最优价格
 * - 规则叠加: 多规则可配置叠加方式
 */
class DynamicPricingService
{
    // ─── 阶梯定价 ──────────────────────────────────────────────

    /**
     * 计算阶梯定价
     */
    public function calculateTieredPrice(PricingPlan $plan, int $quantity, string $billingPeriod = 'monthly'): array
    {
        $basePrice = (float) $plan->getPrice($billingPeriod);
        $tiers = PricingTier::where('pricing_plan_id', $plan->id)
            ->where('is_active', true)
            ->orderBy('from_quantity')
            ->get();

        if ($tiers->isEmpty()) {
            return [
                'unit_price' => $basePrice,
                'total_price' => round($basePrice * $quantity, 2),
                'flat_fee' => 0,
                'tier_name' => null,
                'quantity' => $quantity,
                'saving' => 0,
                'tiers_applied' => false,
            ];
        }

        $totalFlatFee = 0;
        $totalPrice = 0;
        $remainingQuantity = $quantity;
        $appliedTiers = [];
        $saving = round($basePrice * $quantity, 2);

        foreach ($tiers as $tier) {
            if ($remainingQuantity <= 0) break;

            $tierMax = $tier->to_quantity ?? PHP_INT_MAX;
            $tierQty = min($remainingQuantity, $tierMax - $tier->from_quantity + 1);

            if ($tierQty <= 0) continue;

            $tierPrice = round($tier->unit_price * $tierQty, 2);
            $totalPrice += $tierPrice;
            $totalFlatFee += $tier->flat_fee;

            $appliedTiers[] = [
                'name' => $tier->name,
                'from' => $tier->from_quantity,
                'to' => $tier->to_quantity,
                'quantity_in_tier' => $tierQty,
                'unit_price' => (float) $tier->unit_price,
                'flat_fee' => (float) $tier->flat_fee,
                'subtotal' => $tierPrice,
            ];

            $remainingQuantity -= $tierQty;
        }

        $totalPrice = round($totalPrice + $totalFlatFee, 2);

        // 如果剩余数量没有阶梯覆盖，使用 base price
        if ($remainingQuantity > 0) {
            $extraPrice = round($basePrice * $remainingQuantity, 2);
            $totalPrice += $extraPrice;
            $appliedTiers[] = [
                'name' => '标准价格',
                'from' => $quantity - $remainingQuantity + 1,
                'to' => $quantity,
                'quantity_in_tier' => $remainingQuantity,
                'unit_price' => $basePrice,
                'flat_fee' => 0,
                'subtotal' => $extraPrice,
            ];
        }

        $saving = round($saving - $totalPrice, 2);
        $effectiveUnitPrice = $quantity > 0 ? round($totalPrice / $quantity, 4) : 0;

        return [
            'unit_price' => $effectiveUnitPrice,
            'total_price' => $totalPrice,
            'flat_fee' => $totalFlatFee,
            'tier_name' => $tiers->first()->name,
            'quantity' => $quantity,
            'saving' => $saving,
            'saving_percent' => $saving > 0 && ($basePrice * $quantity) > 0
                ? round(($saving / ($basePrice * $quantity)) * 100, 1)
                : 0,
            'tiers_applied' => true,
            'tiers' => $appliedTiers,
        ];
    }

    // ─── 动态定价规则引擎 ──────────────────────────────────────

    /**
     * 对指定目标对象计算所有适用的动态定价规则
     */
    public function evaluateRules(
        string $targetType,
        ?int $targetId,
        array $context = []
    ): Collection {
        $rules = DynamicPricingRule::active()
            ->forTarget($targetType, $targetId)
            ->ordered()
            ->get();

        // 也获取全局规则（target_type=plan, target_id=null 的通用规则）
        if ($targetType !== 'plan') {
            $globalRules = DynamicPricingRule::active()
                ->where('target_type', 'plan')
                ->whereNull('target_id')
                ->whereNull('target_ids')
                ->ordered()
                ->get();
            $rules = $rules->merge($globalRules);
        }

        $applicableRules = collect();
        $now = now();

        foreach ($rules as $rule) {
            // 检查有效期
            if ($rule->starts_at && $now->lt($rule->starts_at)) continue;
            if ($rule->ends_at && $now->gt($rule->ends_at)) continue;

            // 检查时间排期
            if (!$this->checkSchedule($rule, $context)) continue;

            // 检查条件
            if (!$this->checkConditions($rule, $context)) continue;

            $applicableRules->push($rule);
        }

        return $applicableRules;
    }

    /**
     * 计算应用规则后的价格
     */
    public function applyRules(
        float $basePrice,
        Collection $rules,
        array $context = []
    ): array {
        if ($rules->isEmpty()) {
            return [
                'original_price' => $basePrice,
                'final_price' => $basePrice,
                'total_discount' => 0,
                'applied_rules' => [],
                'breakdown' => [],
            ];
        }

        $currentPrice = $basePrice;
        $appliedRules = [];
        $breakdown = [];

        // 分组：高优先级规则先执行
        $sortedRules = $rules->sortBy('priority');

        foreach ($sortedRules as $rule) {
            $beforePrice = $currentPrice;

            switch ($rule->stack_mode) {
                case 'replace':
                    $currentPrice = (float) $rule->adjustment_value;
                    break;

                case 'add':
                    $currentPrice += (float) $rule->adjustment_value;
                    break;

                case 'compound':
                    $currentPrice = $currentPrice * (1 + (float) $rule->adjustment_value / 100);
                    break;

                case 'multiply':
                default:
                    if ($rule->adjustment_type === 'percentage') {
                        $currentPrice = $currentPrice * (1 - (float) $rule->adjustment_value / 100);
                    } elseif ($rule->adjustment_type === 'fixed') {
                        $currentPrice -= (float) $rule->adjustment_value;
                    } elseif ($rule->adjustment_type === 'override') {
                        $currentPrice = (float) $rule->adjustment_value;
                    }
                    break;
            }

            // 价格限制
            if ($rule->min_price && $currentPrice < $rule->min_price) {
                $currentPrice = $rule->min_price;
            }
            if ($rule->max_price && $currentPrice > $rule->max_price) {
                $currentPrice = $rule->max_price;
            }

            $currentPrice = round(max(0, $currentPrice), 2);

            $stepDiscount = round($beforePrice - $currentPrice, 2);
            $breakdown[] = [
                'rule_id' => $rule->id,
                'rule_name' => $rule->name,
                'rule_slug' => $rule->slug,
                'rule_type' => $rule->rule_type,
                'adjustment_type' => $rule->adjustment_type,
                'adjustment_value' => (float) $rule->adjustment_value,
                'stack_mode' => $rule->stack_mode,
                'price_before' => $beforePrice,
                'price_after' => $currentPrice,
                'step_discount' => $stepDiscount,
            ];

            $appliedRules[] = [
                'id' => $rule->id,
                'name' => $rule->name,
                'slug' => $rule->slug,
                'type' => $rule->rule_type,
                'discount' => $stepDiscount,
            ];

            // 更新规则应用计数
            $rule->increment('applied_count');
            $rule->update(['last_applied_at' => now()]);
        }

        $totalDiscount = round($basePrice - $currentPrice, 2);

        return [
            'original_price' => $basePrice,
            'final_price' => $currentPrice,
            'total_discount' => $totalDiscount,
            'discount_percent' => $basePrice > 0
                ? round(($totalDiscount / $basePrice) * 100, 1)
                : 0,
            'applied_rules' => $appliedRules,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * 计算订阅的最终价格（整合阶梯定价 + 动态规则）
     */
    public function calculateSubscriptionPrice(
        PricingPlan $plan,
        string $billingPeriod = 'monthly',
        int $quantity = 1,
        ?Customer $customer = null,
        array $options = []
    ): array {
        $basePrice = (float) $plan->getPrice($billingPeriod);

        // 1. 阶梯定价
        $tierResult = null;
        if ($plan->pricing_model === 'tiered' || !empty($options['use_tiered'])) {
            $tierResult = $this->calculateTieredPrice($plan, $quantity, $billingPeriod);
            $basePrice = $tierResult['total_price'];
        } elseif ($quantity > 1) {
            $basePrice = round($basePrice * $quantity, 2);
        }

        $context = array_merge($options, [
            'billing_period' => $billingPeriod,
            'quantity' => $quantity,
            'plan_slug' => $plan->slug,
        ]);

        if ($customer) {
            $context['customer_id'] = $customer->id;
            $context['customer_type'] = $customer->type;
            $context['customer_level'] = $customer->level;
        }

        // 2. 评估动态规则
        $rules = $this->evaluateRules('plan', $plan->id, $context);

        // 如果指定了客户，也评估客户细分规则
        if ($customer) {
            $segmentRules = $this->evaluateRules('customer', $customer->id, $context);
            $rules = $rules->merge($segmentRules);
        }

        $ruleResult = $this->applyRules($basePrice, $rules, $context);

        return array_merge($ruleResult, [
            'tier_result' => $tierResult,
            'plan' => $plan->slug,
            'billing_period' => $billingPeriod,
            'quantity' => $quantity,
        ]);
    }

    // ─── 时间排期检查 ──────────────────────────────────────────

    protected function checkSchedule(DynamicPricingRule $rule, array $context): bool
    {
        $schedule = $rule->schedule;
        if (empty($schedule)) return true;

        $now = now($rule->timezone ?? 'UTC');

        // 星期几限制
        if (!empty($schedule['days_of_week'])) {
            $dayOfWeek = $now->dayOfWeek; // 0=Sun, 6=Sat
            if (!in_array($dayOfWeek, $schedule['days_of_week'])) return false;
        }

        // 日期范围内
        if (!empty($schedule['date_from'])) {
            $from = Carbon::parse($schedule['date_from'], $rule->timezone ?? 'UTC');
            if ($now->lt($from)) return false;
        }
        if (!empty($schedule['date_to'])) {
            $to = Carbon::parse($schedule['date_to'], $rule->timezone ?? 'UTC');
            if ($now->gt($to)) return false;
        }

        // 时间段
        if (!empty($schedule['time_from'])) {
            $timeFrom = Carbon::parse($schedule['time_from'], $rule->timezone ?? 'UTC');
            if ($now->format('H:i') < $timeFrom->format('H:i')) return false;
        }
        if (!empty($schedule['time_to'])) {
            $timeTo = Carbon::parse($schedule['time_to'], $rule->timezone ?? 'UTC');
            if ($now->format('H:i') > $timeTo->format('H:i')) return false;
        }

        // 节假日排除
        if (!empty($schedule['exclude_holidays']) && $this->isHoliday($now)) return false;

        return true;
    }

    // ─── 条件检查 ──────────────────────────────────────────────

    protected function checkConditions(DynamicPricingRule $rule, array $context): bool
    {
        $conditions = $rule->conditions;
        if (empty($conditions)) return true;

        foreach ($conditions as $field => $condition) {
            $contextValue = $context[$field] ?? null;
            $match = $this->evaluateCondition($condition, $contextValue);
            if (!$match) return false;
        }

        return true;
    }

    protected function evaluateCondition(mixed $condition, mixed $contextValue): bool
    {
        if (is_array($condition)) {
            $operator = $condition['operator'] ?? 'eq';
            $value = $condition['value'] ?? null;

            return match ($operator) {
                'eq' => $contextValue == $value,
                'neq', 'ne' => $contextValue != $value,
                'gt' => $contextValue > $value,
                'gte', 'ge' => $contextValue >= $value,
                'lt' => $contextValue < $value,
                'lte', 'le' => $contextValue <= $value,
                'in' => is_array($value) && in_array($contextValue, $value),
                'not_in' => is_array($value) && !in_array($contextValue, $value),
                'between' => is_array($value) && count($value) === 2
                    && $contextValue >= $value[0] && $contextValue <= $value[1],
                'contains' => is_string($contextValue) && str_contains($contextValue, (string) $value),
                'regex' => is_string($contextValue) && preg_match((string) $value, $contextValue),
                default => $contextValue == $value,
            };
        }

        // 简单值直接比较
        return $contextValue == $condition;
    }

    // ─── 节假日判断 ────────────────────────────────────────────

    protected function isHoliday(Carbon $date): bool
    {
        // 常见的中国节假日（简版）
        $monthDay = $date->format('m-d');
        $holidays = [
            '01-01', // 元旦
            '05-01', // 劳动节
            '10-01', '10-02', '10-03', '10-04', '10-05', '10-06', '10-07', // 国庆
        ];

        if (in_array($monthDay, $holidays)) return true;

        // 周末
        if ($date->isWeekend()) return true;

        return false;
    }

    // ─── LLM 定价优化 ──────────────────────────────────────────

    /**
     * 使用 LLM 分析并生成定价优化建议
     */
    public function generateOptimizationSuggestions(PricingPlan $plan, array $marketData = []): array
    {
        $llmService = app(LlmService::class);

        $currentPrices = $plan->getPrices();
        $tiers = PricingTier::where('pricing_plan_id', $plan->id)
            ->where('is_active', true)
            ->get();

        $prompt = "你是一个 SaaS 定价策略专家。请分析以下定价方案并给出优化建议。

定价方案名称: {$plan->name}
方案 Slug: {$plan->slug}
当前价格:
- 月付: {$currentPrices['monthly']} {$plan->currency}
- 季付: {$currentPrices['quarterly']} {$plan->currency}
- 半年付: {$currentPrices['semi_annually']} {$plan->currency}
- 年付: {$currentPrices['yearly']} {$plan->currency}
当前定价模型: {$plan->pricing_model}
功能特点: " . ($plan->features ? implode(', ', array_keys($plan->features)) : '无')

            . ($tiers->isNotEmpty() ? "\n\n阶梯定价:\n" . $tiers->map(fn($t) => "- {$t->from_quantity}-{$t->to_quantity}: ¥{$t->unit_price}/个 + {$t->flat_fee} 固定费")->implode("\n") : '')

            . ($marketData ? "\n\n市场参考数据: " . json_encode($marketData, JSON_UNESCAPED_UNICODE) : '')

            . "\n\n请以JSON格式返回，包含以下字段:
1. price_suggestions: 各周期的建议价格
2. tier_suggestions: 阶梯定价的调整建议
3. bundling_opportunities: 可能的打包销售机会
4. risk_warnings: 价格风险提示
5. seasonal_strategy: 季节性定价策略建议
6. overall_score: 当前定价的健康度评分(0-100)";

        try {
            $llmResponse = $llmService->chat($prompt, ['temperature' => 0.3]);
            $content = $llmResponse['content'] ?? $llmResponse['message']['content'] ?? '';

            // 尝试从 LLM 回复中提取 JSON
            preg_match('/\{[\s\S]*\}/', $content, $matches);
            $suggestions = !empty($matches)
                ? json_decode($matches[0], true)
                : ['raw_suggestion' => $content];

            // 记录到审计日志
            $this->logOptimization($plan, $suggestions);

            return [
                'plan_slug' => $plan->slug,
                'suggestions' => $suggestions,
                'generated_at' => now()->toIso8601String(),
            ];
        } catch (\Exception $e) {
            Log::warning('Pricing optimization LLM call failed', [
                'plan' => $plan->slug,
                'error' => $e->getMessage(),
            ]);

            return [
                'plan_slug' => $plan->slug,
                'suggestions' => null,
                'error' => $e->getMessage(),
                'generated_at' => now()->toIso8601String(),
            ];
        }
    }

    protected function logOptimization(PricingPlan $plan, mixed $suggestions): void
    {
        try {
            $log = \App\Models\ChangeLog::create([
                'model_type' => PricingPlan::class,
                'model_id' => $plan->id,
                'action' => 'pricing_optimization',
                'old_values' => $plan->getPrices(),
                'new_values' => $suggestions['price_suggestions'] ?? [],
                'changed_by' => 'llm_optimizer',
            ]);
        } catch (\Exception $e) {
            // 非关键操作
        }
    }

    // ─── 批量定价计算 ──────────────────────────────────────────

    /**
     * 模拟不同数量/条件下的价格
     */
    public function simulatePricing(PricingPlan $plan, array $scenarios = []): array
    {
        $results = [];

        // 默认场景
        if (empty($scenarios)) {
            $scenarios = [
                ['quantity' => 1, 'billing_period' => 'monthly'],
                ['quantity' => 10, 'billing_period' => 'monthly'],
                ['quantity' => 50, 'billing_period' => 'monthly'],
                ['quantity' => 100, 'billing_period' => 'monthly'],
                ['quantity' => 1, 'billing_period' => 'yearly'],
                ['quantity' => 10, 'billing_period' => 'yearly'],
                ['quantity' => 50, 'billing_period' => 'yearly'],
            ];
        }

        foreach ($scenarios as $scenario) {
            $qty = $scenario['quantity'] ?? 1;
            $period = $scenario['billing_period'] ?? 'monthly';

            $results[] = $this->calculateSubscriptionPrice(
                $plan,
                $period,
                $qty,
                null,
                ['use_tiered' => true]
            );
        }

        return $results;
    }

    // ═══════════════ 定价实验 (M3-26) ═══════════════

    /**
     * 获取当前有效的实验
     */
    public function getActiveExperiments(): \Illuminate\Support\Collection
    {
        return PricingExperiment::where('status', 'running')
            ->where('starts_at', '<=', now())
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->get();
    }

    /**
     * 根据客户自动匹配运行中的分群实验并分配
     * - 支持按 region / channel / customer_tier / industry 等维度筛选
     * - 同一客户一致性哈希确保始终分到同一组
     */
    public function autoAssignCustomerToExperiments(Customer $customer, ?float $currentPrice = null): array
    {
        $assigned = [];
        $activeExperiments = $this->getActiveExperiments();

        foreach ($activeExperiments as $experiment) {
            // 检查客户是否已参与此实验
            $alreadyParticipant = PricingExperimentParticipant::where('experiment_id', $experiment->id)
                ->where('customer_id', $customer->id)
                ->exists();
            if ($alreadyParticipant) {
                continue;
            }

            // 检查分群筛选条件
            if (!$this->customerMatchesExperimentSegment($customer, $experiment)) {
                continue;
            }

            // 分配到实验组
            $participant = $this->assignToExperiment(
                $experiment,
                $customer->id,
                null,
                $currentPrice
            );

            $assigned[] = $participant;

            $this->recordExperimentEvent(
                $experiment,
                'viewed',
                $participant->id,
                ['customer_id' => $customer->id, 'auto_assigned' => true]
            );
        }

        return $assigned;
    }

    /**
     * 判断客户是否匹配实验的分群筛选条件
     */
    protected function customerMatchesExperimentSegment(Customer $customer, PricingExperiment $experiment): bool
    {
        $filters = $experiment->segment_filters;
        if (empty($filters)) {
            return true; // 无分群筛选则全员参与
        }

        // region 区域
        if (!empty($filters['region'])) {
            $regions = (array) $filters['region'];
            $customerRegion = $customer->region ?? $customer->country ?? '';
            if (!in_array($customerRegion, $regions)) {
                return false;
            }
        }

        // channel 渠道来源
        if (!empty($filters['channel'])) {
            $channels = (array) $filters['channel'];
            $customerChannel = $customer->channel ?? $customer->source ?? '';
            if (!in_array($customerChannel, $channels)) {
                return false;
            }
        }

        // customer_tier 客户等级
        if (!empty($filters['customer_tier'])) {
            $tiers = (array) $filters['customer_tier'];
            $customerTier = $customer->level ?? $customer->tier ?? '';
            if (!in_array($customerTier, $tiers)) {
                return false;
            }
        }

        // industry 行业
        if (!empty($filters['industry'])) {
            $industries = (array) $filters['industry'];
            $customerIndustry = $customer->industry ?? '';
            if (!in_array($customerIndustry, $industries)) {
                return false;
            }
        }

        // device_type 设备类型
        if (!empty($filters['device_type'])) {
            $devices = (array) $filters['device_type'];
            // 从客户关联的设备中获取
            $customerDevices = $customer->devices()->distinct()->pluck('platform')->toArray();
            if (!array_intersect($devices, $customerDevices)) {
                return false;
            }
        }

        // new_vs_returning 新客vs老客
        if (!empty($filters['new_vs_returning'])) {
            $isNew = $customer->created_at && $customer->created_at->gt(now()->subDays(30));
            $isReturning = !$isNew;
            $wantedNew = in_array('new', (array) $filters['new_vs_returning']);
            $wantedReturning = in_array('returning', (array) $filters['new_vs_returning']);
            if (($isNew && !$wantedNew) || ($isReturning && !$wantedReturning)) {
                return false;
            }
        }

        // custom_tags 自定义标签
        if (!empty($filters['custom_tags'])) {
            $wantedTags = (array) $filters['custom_tags'];
            $customerTags = $customer->tags()->pluck('name')->toArray();
            if (!array_intersect($wantedTags, $customerTags)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 分配客户到实验组
     */
    public function assignToExperiment(PricingExperiment $experiment, int $customerId, ?int $subscriptionId = null, ?float $originalPrice = null): PricingExperimentParticipant
    {
        $group = $this->determineGroup($experiment, $customerId);

        $experimentPrice = null;
        if ($originalPrice !== null && $group === 'treatment') {
            $experimentPrice = $this->applyTreatmentPrice($experiment, $originalPrice);
        }

        $participant = PricingExperimentParticipant::create([
            'experiment_id' => $experiment->id,
            'customer_id' => $customerId,
            'subscription_id' => $subscriptionId,
            'group' => $group,
            'original_price' => $originalPrice,
            'experiment_price' => $experimentPrice,
            'assigned_at' => now(),
        ]);

        $experiment->increment('sample_size');

        return $participant;
    }

    /**
     * 记录实验事件
     */
    public function recordExperimentEvent(PricingExperiment $experiment, string $eventType, ?int $participantId = null, ?array $eventData = null): PricingExperimentEvent
    {
        return PricingExperimentEvent::create([
            'experiment_id' => $experiment->id,
            'participant_id' => $participantId,
            'event_type' => $eventType,
            'event_data' => $eventData ? json_encode($eventData) : null,
            'occurred_at' => now(),
        ]);
    }

    /**
     * 计算实验结果统计
     */
    public function calculateExperimentResults(PricingExperiment $experiment): PricingExperiment
    {
        $control = $experiment->participants()->where('group', 'control')->get();
        $treatment = $experiment->participants()->where('group', 'treatment')->get();

        $controlCount = $control->count();
        $treatmentCount = $treatment->count();

        // 转化率
        $controlConverted = $experiment->events()
            ->whereIn('participant_id', $control->pluck('id'))
            ->where('event_type', 'converted')
            ->count();
        $treatmentConverted = $experiment->events()
            ->whereIn('participant_id', $treatment->pluck('id'))
            ->where('event_type', 'converted')
            ->count();

        $controlConversionRate = $controlCount > 0 ? round($controlConverted / $controlCount * 100, 2) : 0;
        $treatmentConversionRate = $treatmentCount > 0 ? round($treatmentConverted / $treatmentCount * 100, 2) : 0;

        // 平均收入
        $controlRevenue = $control->avg('revenue_impact') ?? 0;
        $treatmentRevenue = $treatment->avg('revenue_impact') ?? 0;

        // 流失率
        $controlChurned = $experiment->events()
            ->whereIn('participant_id', $control->pluck('id'))
            ->where('event_type', 'churned')
            ->count();
        $treatmentChurned = $experiment->events()
            ->whereIn('participant_id', $treatment->pluck('id'))
            ->where('event_type', 'churned')
            ->count();

        $controlChurnRate = $controlCount > 0 ? round($controlChurned / $controlCount * 100, 2) : 0;
        $treatmentChurnRate = $treatmentCount > 0 ? round($treatmentChurned / $treatmentCount * 100, 2) : 0;

        // 统计显著性计算（Z-test for proportions）
        $significance = $this->calculateSignificance(
            $controlConverted, $controlCount,
            $treatmentConverted, $treatmentCount
        );

        $results = [
            'control' => [
                'count' => $controlCount,
                'converted' => $controlConverted,
                'conversion_rate' => $controlConversionRate,
                'avg_revenue' => round($controlRevenue, 2),
                'churned' => $controlChurned,
                'churn_rate' => $controlChurnRate,
            ],
            'treatment' => [
                'count' => $treatmentCount,
                'converted' => $treatmentConverted,
                'conversion_rate' => $treatmentConversionRate,
                'avg_revenue' => round($treatmentRevenue, 2),
                'churned' => $treatmentChurned,
                'churn_rate' => $treatmentChurnRate,
            ],
            'improvement' => [
                'conversion_rate' => round($treatmentConversionRate - $controlConversionRate, 2),
                'avg_revenue' => round($treatmentRevenue - $controlRevenue, 2),
                'churn_rate' => round($treatmentChurnRate - $controlChurnRate, 2),
            ],
            'significance' => $significance,
            'calculated_at' => now()->toIso8601String(),
        ];

        $experiment->update(['results' => $results]);

        return $experiment->fresh();
    }

    /**
     * 获取实验列表（分页+筛选）
     */
    public function listExperiments(int $tenantId, array $filters = [], int $perPage = 20)
    {
        $query = PricingExperiment::where('tenant_id', $tenantId)
            ->with('creator:id,name')
            ->withCount('participants')
            ->orderBy('created_at', 'desc');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['experiment_type'])) {
            $query->where('experiment_type', $filters['experiment_type']);
        }
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                  ->orWhere('slug', 'like', "%{$filters['search']}%");
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * 实验完成后自动应用优胜方案
     * 当实验完成且统计显著时，将 treatment_config 提升为推荐定价方案
     */
    public function applyWinningTreatment(PricingExperiment $experiment): array
    {
        if ($experiment->status !== 'completed') {
            throw new \RuntimeException('只有已完成实验可以应用优胜方案');
        }

        $results = $experiment->results;
        if (!$results) {
            // 先计算结果
            $experiment = $this->calculateExperimentResults($experiment);
            $results = $experiment->results;
        }

        $winningConfig = null;
        $reason = '';
        $recommendations = [];

        $treatmentRate = $results['treatment']['conversion_rate'] ?? 0;
        $controlRate = $results['control']['conversion_rate'] ?? 0;
        $treatmentRevenue = $results['treatment']['avg_revenue'] ?? 0;
        $controlRevenue = $results['control']['avg_revenue'] ?? 0;
        $isSignificant = $results['significance']['significant'] ?? false;
        $pValue = $results['significance']['p_value'] ?? 1;

        if ($isSignificant && $treatmentRate > $controlRate) {
            // 实验组在转化率上显著优于对照组 → 应用 treatment_config
            $winningConfig = $experiment->treatment_config;
            $reason = sprintf(
                '实验组转化率(%s%%) 显著优于对照组(%s%%), P值=%.4f, 提升幅度 %.2f%%',
                $treatmentRate, $controlRate, $pValue,
                $results['improvement']['conversion_rate'] ?? 0
            );
        } elseif ($isSignificant && $treatmentRevenue > $controlRevenue && $treatmentRate >= $controlRate * 0.9) {
            // 实验组收入显著更高，且转化率未显著下降 → 应用 treatment_config
            $winningConfig = $experiment->treatment_config;
            $reason = sprintf(
                '实验组平均收入(¥%s) 显著高于对照组(¥%s), 转化率未明显下降',
                number_format($treatmentRevenue, 2),
                number_format($controlRevenue, 2)
            );
        } elseif ($isSignificant && $treatmentRate < $controlRate) {
            // 实验组显著更差 → 保留对照组配置
            $winningConfig = $experiment->control_config;
            $reason = sprintf(
                '实验组转化率(%s%%) 显著低于对照组(%s%%), P值=%.4f, 建议保留原定价',
                $treatmentRate, $controlRate, $pValue
            );
        } else {
            // 无显著差异 → 建议延长实验或收集更多数据
            $winningConfig = null;
            $reason = sprintf(
                '对照组(%s%%) 与实验组(%s%%) 无统计学显著差异(P值=%.4f), 建议增加样本量或延长实验',
                $controlRate, $treatmentRate, $pValue
            );
        }

        $recommendation = [
            'experiment_id' => $experiment->id,
            'experiment_name' => $experiment->name,
            'winning_config' => $winningConfig,
            'reason' => $reason,
            'is_significant' => $isSignificant,
            'p_value' => $pValue,
            'confidence_level' => $experiment->confidence_level,
            'applied_at' => now()->toIso8601String(),
        ];

        // 记录到 metadata
        $metadata = $experiment->metadata ?? [];
        $metadata['winning_recommendation'] = $recommendation;
        $metadata['winning_applied_at'] = now()->toIso8601String();
        $experiment->update(['metadata' => $metadata]);

        return $recommendation;
    }

    /**
     * 基于完成的实验生成数据驱动的定价优化建议
     */
    public function generateExperimentRecommendations(int $tenantId): array
    {
        $completedExperiments = PricingExperiment::where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereNotNull('results')
            ->orderBy('updated_at', 'desc')
            ->limit(20)
            ->get();

        $recommendations = [];

        foreach ($completedExperiments as $experiment) {
            $results = $experiment->results;
            if (!$results) continue;

            $isSignificant = $results['significance']['significant'] ?? false;
            $treatmentRate = $results['treatment']['conversion_rate'] ?? 0;
            $controlRate = $results['control']['conversion_rate'] ?? 0;
            $treatmentRevenue = $results['treatment']['avg_revenue'] ?? 0;
            $controlRevenue = $results['control']['avg_revenue'] ?? 0;

            $improvementRate = $controlRate > 0
                ? round(($treatmentRate - $controlRate) / $controlRate * 100, 2)
                : 0;
            $revenueChange = $treatmentRevenue - $controlRevenue;

            $rec = [
                'experiment_id' => $experiment->id,
                'experiment_name' => $experiment->name,
                'experiment_type' => $experiment->experiment_type,
                'target_metric' => $experiment->target_metric,
                'status' => $experiment->status,
                'is_significant' => $isSignificant,
                'control_rate' => $controlRate,
                'treatment_rate' => $treatmentRate,
                'improvement_rate' => $improvementRate,
                'revenue_impact' => round($revenueChange, 2),
                'sample_size' => $experiment->sample_size,
                'completed_at' => $experiment->updated_at?->toIso8601String(),
            ];

            // 生成建议
            if ($isSignificant && $improvementRate > 0) {
                $rec['action'] = 'apply_treatment';
                $rec['priority'] = $improvementRate > 20 ? 'high' : ($improvementRate > 10 ? 'medium' : 'low');
                $rec['suggestion'] = sprintf(
                    '实验组转化率提升 %s%%，建议将 treatment_config 应用为新的定价方案',
                    $improvementRate
                );
            } elseif ($isSignificant && $improvementRate < 0) {
                $rec['action'] = 'keep_control';
                $rec['priority'] = 'high';
                $rec['suggestion'] = sprintf(
                    '实验组表现差于对照组 %s%%，建议放弃实验组方案',
                    abs($improvementRate)
                );
            } else {
                $rec['action'] = 'need_more_data';
                $rec['priority'] = 'low';
                $rec['suggestion'] = sprintf(
                    '无显著差异(P=%.4f)，建议增加样本量或调整实验参数后重试',
                    $results['significance']['p_value'] ?? 1
                );
            }

            // 自动应用优胜方案的元数据
            $metadata = $experiment->metadata;
            $rec['has_winning_recommendation'] = !empty($metadata['winning_recommendation'] ?? null);
            $rec['winning_applied'] = !empty($metadata['winning_applied_at'] ?? null);

            $recommendations[] = $rec;
        }

        return [
            'recommendations' => $recommendations,
            'total_analyzed' => $completedExperiments->count(),
            'significant_count' => count(array_filter($recommendations, fn($r) => $r['is_significant'])),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * 确定客户所属实验组（基于一致性哈希确保同一客户始终分到同一组）
     */
    protected function determineGroup(PricingExperiment $experiment, int $customerId): string
    {
        $hash = crc32("{$experiment->id}_{$customerId}");
        $normalized = abs($hash) % 100;

        return $normalized < $experiment->traffic_split ? 'treatment' : 'control';
    }

    /**
     * 应用实验组定价
     */
    protected function applyTreatmentPrice(PricingExperiment $experiment, float $originalPrice): float
    {
        $config = $experiment->treatment_config;
        if (!$config) return $originalPrice;

        $type = $config['adjustment_type'] ?? 'percentage';
        $value = $config['adjustment_value'] ?? 0;

        return match ($type) {
            'percentage' => round($originalPrice * (1 + $value / 100), 2),
            'fixed' => round($originalPrice + $value, 2),
            'override' => (float)($config['override_price'] ?? $originalPrice),
            default => $originalPrice,
        };
    }

    /**
     * Z-test for two proportions
     */
    protected function calculateSignificance(int $conv1, int $total1, int $conv2, int $total2): array
    {
        if ($total1 === 0 || $total2 === 0) {
            return ['z_score' => 0, 'p_value' => 1, 'significant' => false];
        }

        $p1 = $conv1 / $total1;
        $p2 = $conv2 / $total2;
        $pPool = ($conv1 + $conv2) / ($total1 + $total2);

        $se = sqrt($pPool * (1 - $pPool) * (1/$total1 + 1/$total2));

        if ($se == 0) {
            return ['z_score' => 0, 'p_value' => 1, 'significant' => false];
        }

        $z = ($p2 - $p1) / $se;

        // 简化的 p-value 计算（正态分布近似）
        $pValue = 2 * (1 - $this->normalCdf(abs($z)));

        return [
            'z_score' => round($z, 4),
            'p_value' => round($pValue, 6),
            'significant' => $pValue < 0.05,
        ];
    }

    /**
     * 标准正态分布 CDF 近似 (Abramowitz & Stegun approximation)
     */
    protected function normalCdf(float $x): float
    {
        // Polynomial approximation for the CDF of standard normal
        if ($x < 0) {
            return 1 - $this->normalCdf(-$x);
        }

        $b0 = 0.2316419;
        $b1 = 0.319381530;
        $b2 = -0.356563782;
        $b3 = 1.781477937;
        $b4 = -1.821255978;
        $b5 = 1.330274429;

        $t = 1 / (1 + $b0 * $x);
        $phi = exp(-$x * $x / 2) / sqrt(2 * M_PI);

        return 1 - $phi * ($b1 * $t + $b2 * $t**2 + $b3 * $t**3 + $b4 * $t**4 + $b5 * $t**5);
    }
}
