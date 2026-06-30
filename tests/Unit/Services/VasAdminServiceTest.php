<?php

namespace Tests\Unit\Services;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\VasService;
use App\Models\VasSubscription;
use App\Services\VasAdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VasAdminServiceTest extends TestCase
{
    use RefreshDatabase;

    protected VasAdminService $service;
    protected Tenant $tenant;
    protected VasService $vasService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(VasAdminService::class);
        $this->tenant = Tenant::factory()->create();

        $this->vasService = VasService::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'premium_support',
            'name' => '高级技术支持',
            'category' => 'support',
            'price_monthly' => 299,
            'price_yearly' => 2990,
            'billing_mode' => 'flat',
            'is_public' => true,
            'is_active' => true,
        ]);
    }

    /** @test */
    public function it_creates_a_vas_service()
    {
        $service = $this->service->createService([
            'tenant_id' => $this->tenant->id,
            'code' => 'sso_audit',
            'name' => 'SSO 审计日志',
            'category' => 'feature',
            'price_monthly' => 199,
            'price_yearly' => 1990,
            'billing_mode' => 'flat',
            'is_public' => true,
            'is_active' => true,
        ]);

        $this->assertInstanceOf(VasService::class, $service);
        $this->assertEquals('sso_audit', $service->code);
        $this->assertEquals('SSO 审计日志', $service->name);
        $this->assertEquals(199, (int) $service->price_monthly);
    }

    /** @test */
    public function it_lists_services()
    {
        VasService::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $services = $this->service->listServices($this->tenant->id);

        $this->assertCount(4, $services); // 1 default + 3 new
    }

    /** @test */
    public function it_updates_a_service()
    {
        $updated = $this->service->updateService($this->vasService, [
            'name' => '白金技术支持',
            'price_monthly' => 599,
        ]);

        $this->assertEquals('白金技术支持', $updated->name);
        $this->assertEquals(599, (int) $updated->price_monthly);
        // code should remain unchanged
        $this->assertEquals('premium_support', $updated->code);
    }

    /** @test */
    public function it_deletes_service_and_related_subscriptions()
    {
        VasSubscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'vas_service_id' => $this->vasService->id,
        ]);

        $this->service->deleteService($this->vasService);

        $this->assertDatabaseMissing('vas_services', ['id' => $this->vasService->id]);
        $this->assertDatabaseMissing('vas_subscriptions', ['vas_service_id' => $this->vasService->id]);
    }

    /** @test */
    public function it_subscribes_to_a_service()
    {
        $sub = $this->service->subscribe($this->tenant->id, $this->vasService->id, []);

        $this->assertInstanceOf(VasSubscription::class, $sub);
        $this->assertEquals('active', $sub->status);
        $this->assertEquals($this->vasService->id, $sub->vas_service_id);
        $this->assertEquals(299, (int) $sub->price); // monthly price
        $this->assertEquals('monthly', $sub->billing_period);
    }

    /** @test */
    public function it_subscribes_yearly()
    {
        $sub = $this->service->subscribe($this->tenant->id, $this->vasService->id, [
            'billing_period' => 'yearly',
        ]);

        $this->assertEquals(2990, (int) $sub->price); // yearly price
        $this->assertEquals('yearly', $sub->billing_period);
    }

    /** @test */
    public function it_prevents_duplicate_subscription()
    {
        $this->service->subscribe($this->tenant->id, $this->vasService->id, []);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('该增值服务已开通');

        $this->service->subscribe($this->tenant->id, $this->vasService->id, []);
    }

    /** @test */
    public function it_cancels_subscription()
    {
        $sub = $this->service->subscribe($this->tenant->id, $this->vasService->id, []);

        $cancelled = $this->service->cancelSubscription($sub->id, '不再需要');

        $this->assertEquals('cancelled', $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertEquals('不再需要', $cancelled->cancel_reason);
    }

    /** @test */
    public function it_lists_subscriptions()
    {
        VasSubscription::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'vas_service_id' => $this->vasService->id,
        ]);

        $result = $this->service->listSubscriptions($this->tenant->id);

        $this->assertEquals(5, $result->total());
    }

    /** @test */
    public function it_returns_stats()
    {
        VasSubscription::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'vas_service_id' => $this->vasService->id,
            'status' => 'active',
            'price' => 299,
        ]);
        VasSubscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'vas_service_id' => $this->vasService->id,
            'status' => 'cancelled',
            'price' => 0,
        ]);

        $stats = $this->service->getStats($this->tenant->id);

        $this->assertGreaterThanOrEqual(1, $stats['total_services']);
        $this->assertEquals(4, $stats['total_subscriptions']);
        $this->assertEquals(3, $stats['active_subscriptions']);
        $this->assertEquals(897, (int) $stats['monthly_revenue']); // 3 * 299
    }

    /** @test */
    public function it_returns_marketplace()
    {
        // Create a private service that should NOT appear
        VasService::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'internal_tool',
            'name' => '内部工具',
            'is_public' => false,
            'is_active' => true,
        ]);

        // Subscribe to the public service
        $this->service->subscribe($this->tenant->id, $this->vasService->id, []);

        $marketplace = $this->service->getMarketplace($this->tenant->id);

        // Should include the public service only
        $codes = array_column($marketplace['services'], 'code');
        $this->assertContains('premium_support', $codes);
        $this->assertNotContains('internal_tool', $codes);

        // Active service IDs should include the subscribed one
        $this->assertContains($this->vasService->id, $marketplace['active_service_ids']);
    }
}
