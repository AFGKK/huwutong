<?php

namespace Tests\Unit\Services;

use App\Models\Coupon;
use App\Models\Customer;
use App\Models\EnterpriseContract;
use App\Models\Promotion;
use App\Models\PromotionRedemption;
use App\Models\User;
use App\Services\PromotionService;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class PromotionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PromotionService $service;
    protected User $admin;
    protected \App\Models\Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PromotionService();

        // Create a tenant for FK constraints
        $this->tenant = \App\Models\Tenant::factory()->create();

        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);
        $this->actingAs($this->admin);
    }

    protected function createCustomer(array $overrides = []): Customer
    {
        return Customer::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
        ], $overrides));
    }

    protected function createCoupon(array $overrides = []): Coupon
    {
        return Coupon::create(array_merge([
            'code' => strtoupper(substr(uniqid(), 0, 8)),
            'name' => 'Test Coupon',
            'type' => 'percentage',
            'value' => 10,
            'tenant_id' => $this->tenant->id,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDays(30),
        ], $overrides));
    }

    // ═══════════════ 促销活动 ═══════════════

    public function test_can_create_promotion()
    {
        $promo = $this->service->createPromotion([
            'name' => '双十一限时秒杀',
            'type' => 'flash_sale',
            'discount_type' => 'percentage',
            'discount_value' => 50,
            'max_discount' => 200,
            'starts_at' => now(),
            'ends_at' => now()->addDays(7),
        ]);

        $this->assertInstanceOf(Promotion::class, $promo);
        $this->assertEquals('双十一限时秒杀', $promo->name);

        $fresh = $promo->fresh();
        $this->assertEquals('draft', $fresh->status);
        $this->assertNotNull($promo->slug);
    }

    public function test_can_list_promotions()
    {
        $this->service->createPromotion(['name' => 'P1', 'type' => 'flash_sale', 'starts_at' => now()]);
        $this->service->createPromotion(['name' => 'P2', 'type' => 'bulk_discount', 'starts_at' => now()]);

        $this->assertEquals(2, $this->service->listPromotions()->total());
        $this->assertEquals(1, $this->service->listPromotions(['type' => 'flash_sale'])->total());
    }

    public function test_can_publish_and_pause_promotion()
    {
        $promo = $this->service->createPromotion(['name' => 'Test', 'type' => 'flash_sale', 'starts_at' => now()]);
        // Explicitly set status since SQLite may not return defaults
        $promo->update(['status' => 'draft']);

        $published = $this->service->publishPromotion($promo->fresh());
        $this->assertEquals('active', $published->status);
        $this->assertNotNull($published->published_at);

        $paused = $this->service->pausePromotion($published->fresh());
        $this->assertEquals('paused', $paused->status);
    }

    public function test_can_redeem_promotion()
    {
        $promo = $this->service->createPromotion([
            'name' => 'Test', 'type' => 'bulk_discount',
            'discount_type' => 'percentage', 'discount_value' => 10,
            'usage_limit' => 100, 'budget' => 1000,
            'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(7),
        ]);
        $promo->update(['status' => 'active']);

        $customer = $this->createCustomer();

        $result = $this->service->redeemPromotion($promo->fresh(), $customer, 1000);

        $this->assertEquals(1000, $result['original_amount']);
        $this->assertEquals(100, $result['discount']);
        $this->assertEquals(900, $result['final_amount']);

        $this->assertEquals(1, $promo->fresh()->usage_count);
        $this->assertEquals(100, $promo->fresh()->budget_spent);
        $this->assertEquals(1, PromotionRedemption::count());
    }

    public function test_cannot_redeem_inactive_promotion()
    {
        $this->expectException(\RuntimeException::class);
        $promo = $this->service->createPromotion(['name' => 'Inactive', 'type' => 'flash_sale', 'starts_at' => now()]);
        $customer = $this->createCustomer();
        $this->service->redeemPromotion($promo, $customer, 100);
    }

    public function test_cannot_redeem_exhausted_promotion()
    {
        $promo = $this->service->createPromotion([
            'name' => 'NoBudget', 'type' => 'flash_sale',
            'discount_type' => 'fixed_amount', 'discount_value' => 100,
            'budget' => 50, 'usage_limit' => 100,
            'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(7),
        ]);
        $promo->update(['status' => 'active', 'budget_spent' => 50]); // already exhausted

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('促销预算已用完');
        $customer = $this->createCustomer();
        $this->service->redeemPromotion($promo->fresh(), $customer, 100);
    }

    public function test_can_get_promotion_stats()
    {
        $this->service->createPromotion(['name' => 'P1', 'type' => 'flash_sale', 'starts_at' => now()]);
        $this->service->createPromotion(['name' => 'P2', 'type' => 'bulk_discount', 'starts_at' => now()]);

        $stats = $this->service->getPromotionStats();
        $this->assertEquals(2, $stats['total']);
        $this->assertArrayHasKey('active', $stats);
    }

    // ═══════════════ 企业年框合同 ═══════════════

    public function test_can_create_contract()
    {
        $customer = $this->createCustomer();
        $contract = $this->service->createContract([
            'name' => 'ACME 年度企业合同',
            'customer_id' => $customer->id,
            'total_value' => 100000,
            'discount_rate' => 15,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'licensed_items' => [
                ['type' => 'plan', 'name' => 'Enterprise', 'quantity' => 50, 'unit_price' => 2000],
            ],
        ]);

        $this->assertInstanceOf(EnterpriseContract::class, $contract);
        $this->assertEquals('ACME 年度企业合同', $contract->name);
        $this->assertNotNull($contract->contract_number);

        $fresh = $contract->fresh();
        $this->assertEquals('draft', $fresh->status);
    }

    public function test_can_approve_contract()
    {
        $customer = $this->createCustomer();
        $contract = $this->service->createContract([
            'name' => 'Test Contract', 'customer_id' => $customer->id,
            'total_value' => 50000, 'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'licensed_items' => [],
        ]);
        $contract->update(['status' => 'pending_approval']);

        $approved = $this->service->approveContract($contract->fresh(), 'approved', '符合条件');
        $this->assertEquals('active', $approved->status);
        $this->assertEquals('approved', $approved->approval_status);
        $this->assertEquals('符合条件', $approved->approval_notes);
    }

    public function test_can_list_contracts()
    {
        $customer = $this->createCustomer();
        $this->service->createContract(['name' => 'C1', 'customer_id' => $customer->id, 'total_value' => 10000, 'start_date' => now()->toDateString(), 'end_date' => now()->addYear()->toDateString(), 'licensed_items' => []]);
        $this->service->createContract(['name' => 'C2', 'customer_id' => $customer->id, 'total_value' => 20000, 'start_date' => now()->toDateString(), 'end_date' => now()->addYear()->toDateString(), 'licensed_items' => []]);

        $this->assertEquals(2, $this->service->listContracts()->total());
    }

    // ═══════════════ 优惠券 ═══════════════

    public function test_can_create_coupon()
    {
        $coupon = $this->createCoupon(['name' => '新用户优惠', 'value' => 20]);

        $this->assertInstanceOf(Coupon::class, $coupon);
        $this->assertEquals('新用户优惠', $coupon->name);
        $this->assertNotNull($coupon->code);
        $this->assertEquals(8, strlen($coupon->code));
    }

    public function test_can_validate_and_redeem_coupon()
    {
        $customer = $this->createCustomer();
        $coupon = $this->createCoupon([
            'code' => 'NEW20',
            'name' => 'New User 20%',
            'value' => 20,
            'usage_limit' => 100,
        ]);

        $result = $this->service->validateAndRedeemCoupon('NEW20', $customer, 500);

        $this->assertEquals(500, $result['original_amount']);
        $this->assertEquals(100, $result['discount']);
        $this->assertEquals(400, $result['final_amount']);
        $this->assertEquals('NEW20', $result['coupon_code']);
    }

    public function test_cannot_redeem_invalid_coupon()
    {
        $this->expectException(\RuntimeException::class);
        $customer = $this->createCustomer();
        $this->service->validateAndRedeemCoupon('DOESNOTEXIST', $customer, 100);
    }
}
