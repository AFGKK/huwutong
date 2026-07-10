<?php

namespace Tests\Unit\Services;

use App\Models\Customer;
use App\Models\PromotionRule;
use App\Models\Tenant;
use App\Services\PromotionEngineService;
use Exception;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class PromotionEngineTest extends TestCase
{
    use RefreshDatabase;

    protected PromotionEngineService $service;
    protected Tenant $tenant;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PromotionEngineService();
        $this->tenant = Tenant::factory()->create();
        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /** @test */
    public function calculates_amount_off_discount()
    {
        $rule = PromotionRule::factory()->create([
            'type' => 'amount_off',
            'discount_value' => 50,
            'condition_type' => 'subtotal',
            'condition_value' => 200,
            'status' => 'active',
        ]);

        // 满200减50
        $result = $this->service->calculateDiscount($rule, 300);
        $this->assertEquals(50, $result['discount']);

        // 不满足条件
        $result = $this->service->calculateDiscount($rule, 100);
        $this->assertEquals(0, $result['discount']);
    }

    /** @test */
    public function calculates_percent_off_discount()
    {
        $rule = PromotionRule::factory()->create([
            'type' => 'percent_off',
            'discount_value' => 10, // 9折
            'condition_type' => 'subtotal',
            'condition_value' => 100,
            'status' => 'active',
        ]);

        $result = $this->service->calculateDiscount($rule, 500);
        $this->assertEquals(50, $result['discount']); // 500 * 10% = 50
    }

    /** @test */
    public function applies_max_discount_cap()
    {
        $rule = PromotionRule::factory()->create([
            'type' => 'percent_off',
            'discount_value' => 50, // 5折
            'max_discount' => 100,
            'condition_type' => 'subtotal',
            'condition_value' => 100,
            'status' => 'active',
        ]);

        $result = $this->service->calculateDiscount($rule, 500);
        $this->assertEquals(100, $result['discount']); // 500*50%=250, 封顶100
    }

    /** @test */
    public function calculates_tiered_discount()
    {
        $rule = PromotionRule::factory()->create([
            'type' => 'amount_off',
            'condition_type' => 'subtotal',
            'condition_value' => 100,
            'status' => 'active',
            'tiers' => [
                ['from' => 100, 'to' => 300, 'type' => 'amount_off', 'value' => 20],
                ['from' => 300, 'to' => 500, 'type' => 'amount_off', 'value' => 50],
                ['from' => 500, 'to' => null, 'type' => 'amount_off', 'value' => 100],
            ],
        ]);

        // 100-300 档
        $result = $this->service->calculateDiscount($rule, 200);
        $this->assertEquals(20, $result['discount']);
        $this->assertEquals(20, $result['tier_applied']['value']);

        // 300-500 档
        $result = $this->service->calculateDiscount($rule, 400);
        $this->assertEquals(50, $result['discount']);

        // 500+ 档
        $result = $this->service->calculateDiscount($rule, 1000);
        $this->assertEquals(100, $result['discount']);
    }

    /** @test */
    public function buy_x_get_y_returns_free_items()
    {
        $rule = PromotionRule::factory()->create([
            'type' => 'buy_x_get_y',
            'buy_quantity' => 3,
            'free_quantity' => 1,
            'condition_type' => 'quantity',
            'condition_value' => 3,
            'status' => 'active',
        ]);

        $result = $this->service->calculateDiscount($rule, 0, 3);
        $this->assertEquals(1, $result['free_items']);
        $this->assertEquals('买3送1（可享1件免费）', $result['description']);
    }

    /** @test */
    public function inactive_rule_returns_zero()
    {
        $rule = PromotionRule::factory()->create([
            'type' => 'amount_off',
            'discount_value' => 50,
            'status' => 'draft',
        ]);

        $result = $this->service->calculateDiscount($rule, 1000);
        $this->assertEquals(0, $result['discount']);
    }

    /** @test */
    public function rule_with_no_budget_returns_zero()
    {
        $rule = PromotionRule::factory()->create([
            'type' => 'amount_off',
            'discount_value' => 50,
            'status' => 'active',
            'budget' => 100,
            'budget_spent' => 100, // 已花完
        ]);

        $result = $this->service->calculateDiscount($rule, 1000);
        $this->assertEquals(0, $result['discount']);
    }

    /** @test */
    public function applies_promotion_and_records_usage()
    {
        $rule = PromotionRule::factory()->create([
            'type' => 'amount_off',
            'discount_value' => 50,
            'condition_type' => 'subtotal',
            'condition_value' => 200,
            'status' => 'active',
        ]);

        $result = $this->service->applyPromotion($rule, $this->customer, 300);

        $this->assertEquals(50, $result['discount']);
        $this->assertEquals(250, $result['final_amount']);
        $this->assertNotNull($result['redemption']);

        // 验证使用计数增加了
        $rule->refresh();
        $this->assertEquals(1, $rule->usage_count);
    }

    /** @test */
    public function enforces_usage_limit_per_customer()
    {
        $rule = PromotionRule::factory()->create([
            'type' => 'amount_off',
            'discount_value' => 50,
            'condition_type' => 'subtotal',
            'condition_value' => 0,
            'usage_limit_per_customer' => 1,
            'status' => 'active',
        ]);

        // 第一次使用
        $this->service->applyPromotion($rule, $this->customer, 300);

        // 第二次应该失败
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('已达到此促销的使用上限');
        $this->service->applyPromotion($rule, $this->customer, 300);
    }

    /** @test */
    public function finds_best_promotion()
    {
        $rule1 = PromotionRule::factory()->create([
            'type' => 'amount_off',
            'discount_value' => 30,
            'condition_type' => 'subtotal',
            'condition_value' => 0,
            'stackable_with_other_rules' => true,
            'priority' => 1,
            'status' => 'active',
        ]);

        $rule2 = PromotionRule::factory()->create([
            'type' => 'percent_off',
            'discount_value' => 20, // 8折
            'condition_type' => 'subtotal',
            'condition_value' => 0,
            'stackable_with_other_rules' => true,
            'priority' => 2,
            'status' => 'active',
        ]);

        $result = $this->service->findBestPromotion([$rule1, $rule2], 500);

        $this->assertNotNull($result['best_single']);
        $this->assertGreaterThan(0, $result['best_combined']['total_discount']);
        // 打折的折扣: 500*20%=100, 满减: 30, 所以best single应该是打折
        $this->assertEquals(100, $result['best_single']['discount']);
    }

    /** @test */
    public function checks_stackability_with_coupon()
    {
        $rule = PromotionRule::factory()->create([
            'stackable_with_coupon' => false,
        ]);

        $result = $this->service->checkStackability($rule, true);
        $this->assertFalse($result['can_stack']);
        $this->assertNotNull($result['reason']);

        // 允许叠加
        $rule->stackable_with_coupon = true;
        $result = $this->service->checkStackability($rule, true);
        $this->assertTrue($result['can_stack']);
    }

    /** @test */
    public function respects_min_order_amount()
    {
        $rule = PromotionRule::factory()->create([
            'type' => 'amount_off',
            'discount_value' => 50,
            'min_order_amount' => 500,
            'status' => 'active',
        ]);

        // 不满足最低订单金额
        $result = $this->service->calculateDiscount($rule, 300);
        $this->assertEquals(0, $result['discount']);

        // 满足
        $result = $this->service->calculateDiscount($rule, 600);
        $this->assertEquals(50, $result['discount']);
    }

    /** @test */
    public function respects_applicable_products()
    {
        $rule = PromotionRule::factory()->create([
            'type' => 'amount_off',
            'discount_value' => 50,
            'applicable_products' => [1, 2, 3],
            'condition_type' => 'subtotal',
            'condition_value' => 0,
            'status' => 'active',
        ]);

        // 订单中没有适用商品
        $result = $this->service->calculateDiscount($rule, 500, 0, [4, 5]);
        $this->assertEquals(0, $result['discount']);

        // 有适用商品
        $result = $this->service->calculateDiscount($rule, 500, 0, [2, 5]);
        $this->assertEquals(50, $result['discount']);
    }
}
