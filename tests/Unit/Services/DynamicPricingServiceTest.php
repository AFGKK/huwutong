<?php

namespace Tests\Unit\Services;

use App\Models\DynamicPricingRule;
use App\Models\PricingPlan;
use App\Models\PricingTier;
use App\Services\DynamicPricingService;
use Tests\Concerns\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DynamicPricingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected DynamicPricingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DynamicPricingService::class);
    }

    protected function createPlan(): int
    {
        $tenant = \App\Models\Tenant::factory()->create();
        return DB::table('pricing_plans')->insertGetId([
            'tenant_id' => $tenant->id,
            'slug' => 'test-plan-' . uniqid(),
            'name' => 'Test Plan',
            'billing_period' => 'monthly',
            'sort_order' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Create a mock PricingPlan that returns proper prices via getPrice()
     */
    protected function mockPlan(int $planId, array $prices = ['monthly' => 100]): PricingPlan
    {
        $mock = \Mockery::mock(PricingPlan::class)->makePartial();
        $mock->shouldReceive('getPrice')->andReturnUsing(function ($period) use ($prices) {
            return $prices[$period] ?? 0;
        });
        $mock->shouldReceive('getPrices')->andReturn($prices);
        $mock->shouldReceive('getAttribute')->with('slug')->andReturn('mock-plan');
        $mock->shouldReceive('getAttribute')->with('name')->andReturn('Mock Plan');
        $mock->shouldReceive('getAttribute')->with('currency')->andReturn('CNY');
        $mock->shouldReceive('getAttribute')->with('pricing_model')->andReturn('tiered');
        $mock->shouldReceive('getAttribute')->with('enable_dynamic_pricing')->andReturn(true);
        $mock->shouldReceive('getAttribute')->with('features')->andReturn([]);
        $mock->shouldReceive('__get')->with('slug')->andReturn('mock-plan');
        $mock->shouldReceive('__get')->with('name')->andReturn('Mock Plan');
        $mock->shouldReceive('__get')->with('currency')->andReturn('CNY');
        $mock->shouldReceive('__get')->with('pricing_model')->andReturn('tiered');
        $mock->shouldReceive('__get')->with('enable_dynamic_pricing')->andReturn(true);
        $mock->shouldReceive('__get')->with('features')->andReturn([]);
        $mock->shouldReceive('getKey')->andReturn($planId);
        $mock->shouldReceive('getRouteKey')->andReturn($planId);
        $mock->id = $planId;
        return $mock;
    }

    protected function createRule(array $overrides = []): DynamicPricingRule
    {
        return DynamicPricingRule::create(array_merge([
            'name' => 'Test Rule',
            'slug' => 'test-rule-' . uniqid(),
            'rule_type' => 'promotion',
            'target_type' => 'plan',
            'adjustment_type' => 'percentage',
            'adjustment_value' => 20,
            'priority' => 100,
            'stack_mode' => 'multiply',
            'is_active' => true,
        ], $overrides));
    }

    // ─── Tiered Pricing Tests ───

    public function test_calculate_tiered_price_no_tiers()
    {
        $planId = $this->createPlan();
        $plan = $this->mockPlan($planId, ['monthly' => 100]);

        $result = $this->service->calculateTieredPrice($plan, 5);

        $this->assertFalse($result['tiers_applied']);
        $this->assertEquals(500, $result['total_price']);
        $this->assertEquals(100, $result['unit_price']);
    }

    public function test_calculate_tiered_price_with_tiers()
    {
        $planId = $this->createPlan();
        $plan = $this->mockPlan($planId, ['monthly' => 100]);

        PricingTier::create([
            'pricing_plan_id' => $planId,
            'name' => '基础价', 'from_quantity' => 1, 'to_quantity' => 10,
            'unit_price' => 100, 'sort_order' => 1,
        ]);
        PricingTier::create([
            'pricing_plan_id' => $planId,
            'name' => '批量价', 'from_quantity' => 11, 'to_quantity' => 50,
            'unit_price' => 80, 'sort_order' => 2,
        ]);

        // Quantity 5 - first tier only
        $result = $this->service->calculateTieredPrice($plan, 5);
        $this->assertTrue($result['tiers_applied']);
        $this->assertEquals(500, $result['total_price']);
        $this->assertEquals(0, $result['saving']);

        // Quantity 20 - crosses tiers
        $result = $this->service->calculateTieredPrice($plan, 20);
        $this->assertTrue($result['tiers_applied']);
        $this->assertEquals(1800, $result['total_price']); // 10*100 + 10*80
        $this->assertEquals(200, $result['saving']); // 20*100 - 1800
        $this->assertEquals(10, round($result['saving_percent']));
    }

    public function test_calculate_tiered_price_with_flat_fee()
    {
        $planId = $this->createPlan();
        $plan = $this->mockPlan($planId, ['monthly' => 100]);

        PricingTier::create([
            'pricing_plan_id' => $planId,
            'name' => '基础价', 'from_quantity' => 1, 'to_quantity' => 10,
            'unit_price' => 90, 'flat_fee' => 50, 'sort_order' => 1,
        ]);

        $result = $this->service->calculateTieredPrice($plan, 5);
        $this->assertEquals(500, $result['total_price']); // 5*90 + 50
        $this->assertEquals(50, $result['flat_fee']);
    }

    // ─── Rule Evaluation Tests ───

    public function test_evaluate_rules_filters_by_schedule()
    {
        $planId = $this->createPlan();

        $this->createRule([
            'slug' => 'future-rule', 'target_id' => $planId,
            'priority' => 1, 'starts_at' => now()->addDays(30),
        ]);

        $rules = $this->service->evaluateRules('plan', $planId);
        $this->assertCount(0, $rules);
    }

    public function test_evaluate_rules_applies_active_rules()
    {
        $planId = $this->createPlan();

        $rule = $this->createRule([
            'slug' => 'active-discount', 'target_id' => $planId, 'priority' => 1,
        ]);

        $rules = $this->service->evaluateRules('plan', $planId);
        $this->assertCount(1, $rules);
        $this->assertEquals($rule->id, $rules->first()->id);
    }

    // ─── Rule Application Tests ───

    public function test_apply_rules_multiply_stack()
    {
        $rule = $this->createRule([
            'adjustment_value' => 20, 'priority' => 1, 'stack_mode' => 'multiply',
        ]);

        $result = $this->service->applyRules(100, collect([$rule]));

        $this->assertEquals(100, $result['original_price']);
        $this->assertEquals(80, $result['final_price']);
        $this->assertEquals(20, $result['total_discount']);
    }

    public function test_apply_rules_replace_stack()
    {
        $rule = $this->createRule([
            'adjustment_type' => 'override', 'adjustment_value' => 50,
            'priority' => 1, 'stack_mode' => 'replace',
        ]);

        $result = $this->service->applyRules(100, collect([$rule]));
        $this->assertEquals(50, $result['final_price']);
    }

    public function test_apply_rules_min_price_limit()
    {
        $rule = $this->createRule([
            'adjustment_value' => 90, 'min_price' => 20, 'priority' => 1,
        ]);

        $result = $this->service->applyRules(100, collect([$rule]));
        // 100 * (1-0.9) = 10, min_price = 20
        $this->assertEquals(20, $result['final_price']);
    }

    // ─── Integration Tests ───

    public function test_calculate_subscription_price_integration()
    {
        $planId = $this->createPlan();
        $plan = $this->mockPlan($planId, ['monthly' => 100]);

        PricingTier::create([
            'pricing_plan_id' => $planId,
            'name' => 'T1', 'from_quantity' => 1, 'to_quantity' => 10,
            'unit_price' => 100, 'sort_order' => 1,
        ]);
        PricingTier::create([
            'pricing_plan_id' => $planId,
            'name' => 'T2', 'from_quantity' => 11, 'to_quantity' => null,
            'unit_price' => 80, 'sort_order' => 2,
        ]);

        $this->createRule([
            'slug' => 'loyalty-discount', 'target_id' => $planId,
            'adjustment_value' => 10, 'priority' => 1,
        ]);

        $result = $this->service->calculateSubscriptionPrice(
            $plan, 'monthly', 15, null, ['use_tiered' => true]
        );

        $this->assertEquals(1400, $result['original_price']); // 10*100 + 5*80
        $this->assertEquals(1260, $result['final_price']); // 1400 * 0.9
        $this->assertEquals(140, $result['total_discount']);
        $this->assertCount(1, $result['applied_rules']);
        $this->assertCount(1, $result['breakdown']);
    }

    public function test_simulate_pricing_default_scenarios()
    {
        $planId = $this->createPlan();
        $plan = $this->mockPlan($planId, ['monthly' => 100, 'yearly' => 1000]);

        PricingTier::create([
            'pricing_plan_id' => $planId,
            'name' => 'T1', 'from_quantity' => 1, 'to_quantity' => 10,
            'unit_price' => 100, 'sort_order' => 1,
        ]);
        PricingTier::create([
            'pricing_plan_id' => $planId,
            'name' => 'T2', 'from_quantity' => 11, 'to_quantity' => null,
            'unit_price' => 80, 'sort_order' => 2,
        ]);

        $results = $this->service->simulatePricing($plan, [
            ['quantity' => 1, 'billing_period' => 'monthly'],
            ['quantity' => 50, 'billing_period' => 'monthly'],
        ]);

        $this->assertCount(2, $results);
        $this->assertEquals(100, $results[0]['original_price']); // 1*100
        $this->assertEquals(4200, $results[1]['original_price']); // 10*100 + 40*80
    }
}
