<?php

namespace Tests\Unit\Services;

use App\Models\BundlePlan;
use App\Models\PlanUpgradePath;
use App\Models\PricingPlan;
use App\Models\Subscription;
use App\Services\PlanService;
use Tests\Concerns\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlanServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PlanService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PlanService::class);
    }

    protected function createPlan(array $overrides = []): PricingPlan
    {
        $tenant = \App\Models\Tenant::factory()->create();
        $data = array_merge([
            'tenant_id' => $tenant->id,
            'slug' => 'plan-' . uniqid(),
            'name' => 'Test Plan',
            'description' => 'Description',
            'billing_period' => 'monthly',
        ], $overrides);

        $allowedColumns = ['tenant_id', 'slug', 'name', 'description', 'billing_period', 'is_active', 'sort_order'];
        $insert = array_intersect_key($data, array_flip($allowedColumns));
        $insert['is_active'] = $data['is_active'] ?? true;
        $insert['sort_order'] = $data['sort_order'] ?? 0;

        $id = DB::table('pricing_plans')->insertGetId(array_merge($insert, [
            'created_at' => now(), 'updated_at' => now(),
        ]));

        return PricingPlan::find($id);
    }

    public function test_can_list_plans(): void
    {
        $this->createPlan();
        $this->createPlan();
        $this->createPlan();
        $result = $this->service->listPlans([]);
        $this->assertCount(3, $result->items());
    }

    public function test_can_calculate_bundle_price(): void
    {
        $parent = $this->createPlan();
        $included = $this->createPlan();
        $bundle = BundlePlan::factory()->create([
            'parent_plan_id' => $parent->id,
            'included_plan_id' => $included->id,
            'discount_percent' => 20,
        ]);
        $price = $this->service->calculateBundlePrice($parent, $included, $bundle);
        $this->assertEquals(0, $price);
    }

    public function test_can_manage_bundle_rules(): void
    {
        $plan1 = $this->createPlan();
        $plan2 = $this->createPlan();

        $bundle = $this->service->createBundleRule([
            'parent_plan_id' => $plan1->id, 'included_plan_id' => $plan2->id,
            'type' => 'optional', 'discount_percent' => 15,
        ]);
        $this->assertNotNull($bundle->id);

        $this->service->updateBundleRule($bundle, ['discount_percent' => 20]);
        $this->assertEquals(20, $bundle->fresh()->discount_percent);

        $this->service->deleteBundleRule($bundle);
        $this->assertNull($bundle->fresh());
    }

    public function test_can_manage_upgrade_paths(): void
    {
        $plan1 = $this->createPlan();
        $plan2 = $this->createPlan();

        $path = $this->service->createUpgradePath([
            'from_plan_id' => $plan1->id, 'to_plan_id' => $plan2->id,
            'proration_ratio' => 0.5, 'allow_downgrade' => true,
        ]);
        $this->assertNotNull($path->id);

        $this->service->updateUpgradePath($path, ['proration_ratio' => 0.3]);
        $this->assertEquals(0.3, $path->fresh()->proration_ratio);

        $this->service->deleteUpgradePath($path);
        $this->assertNull($path->fresh());
    }

    public function test_downgrade_throws_exception_when_not_allowed(): void
    {
        $from = $this->createPlan();
        $to = $this->createPlan();
        $from->price_monthly = 100;
        $to->price_monthly = 50;

        PlanUpgradePath::factory()->create([
            'from_plan_id' => $from->id, 'to_plan_id' => $to->id,
            'allow_downgrade' => false,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('不允许降级到该套餐');
        $this->service->calculateUpgrade($from, $to, 'monthly');
    }

    public function test_can_calculate_upgrade_charge(): void
    {
        $from = $this->createPlan();
        $to = $this->createPlan();
        $from->price_monthly = 50;
        $to->price_monthly = 100;

        PlanUpgradePath::factory()->create([
            'from_plan_id' => $from->id, 'to_plan_id' => $to->id,
            'proration_ratio' => 0.5, 'allow_downgrade' => false,
        ]);

        $calc = $this->service->calculateUpgrade($from, $to, 'monthly');
        $this->assertEquals('upgrade', $calc['type']);
        $this->assertEquals(50, $calc['from_price']);
        $this->assertEquals(100, $calc['to_price']);
    }

    public function test_can_execute_upgrade(): void
    {
        $from = $this->createPlan(['slug' => 'basic']);
        $to = $this->createPlan(['slug' => 'pro']);
        $from->price_monthly = 50;
        $to->price_monthly = 100;

        PlanUpgradePath::factory()->create([
            'from_plan_id' => $from->id, 'to_plan_id' => $to->id,
            'proration_ratio' => 0.5, 'allow_downgrade' => false,
        ]);

        $subscription = Subscription::factory()->create([
            'pricing_plan_slug' => $from->slug, 'price' => 50,
            'billing_period' => 'monthly', 'status' => 'active',
        ]);

        $log = $this->service->executeUpgrade($subscription, $to);
        $this->assertEquals('upgrade', $log->type);
        $this->assertEquals('completed', $log->status);
        $subscription->refresh();
        $this->assertEquals($to->slug, $subscription->pricing_plan_slug);
    }

    public function test_can_get_plan_with_bundles(): void
    {
        $parent = $this->createPlan();
        $included = $this->createPlan();
        BundlePlan::factory()->create([
            'parent_plan_id' => $parent->id, 'included_plan_id' => $included->id,
            'discount_percent' => 10,
        ]);
        $result = $this->service->getPlanWithBundles($parent);
        $this->assertEquals($parent->slug, $result['plan']['slug']);
        $this->assertCount(1, $result['bundles']);
    }

    public function test_public_plans_returns_collection(): void
    {
        $this->createPlan();
        $this->createPlan();
        $this->createPlan();
        $publicPlans = $this->service->getPublicPlans();
        $this->assertIsObject($publicPlans);
    }
}
