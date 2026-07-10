<?php

namespace Tests\Unit\Services;

use App\Models\BundlePurchase;
use App\Models\Customer;
use App\Models\ProductBundle;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BundleService;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class BundleServiceTest extends TestCase
{
    use RefreshDatabase;

    private BundleService $service;
    private Tenant $tenant;
    private User $user;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BundleService::class);
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->user->id]);
        $this->actingAs($this->user);
    }

    public function test_can_create_bundle()
    {
        $bundle = $this->service->createBundle([
            'name' => '基础版套餐',
            'bundle_price' => 299.00,
            'items' => [
                ['name' => '基础License x1', 'original_price' => 199.00, 'quantity' => 1, 'type' => 'plan'],
                ['name' => '技术支持 x1', 'original_price' => 150.00, 'quantity' => 1, 'type' => 'addon'],
            ],
        ]);

        $this->assertInstanceOf(ProductBundle::class, $bundle);
        $this->assertEquals('基础版套餐', $bundle->name);
        $this->assertEquals(299.00, (float)$bundle->bundle_price);
        $this->assertCount(2, $bundle->items);
    }

    public function test_calculates_original_price()
    {
        $bundle = $this->service->createBundle([
            'name' => '折扣套餐',
            'bundle_price' => 300.00,
            'items' => [
                ['name' => 'Item A', 'original_price' => 100.00, 'quantity' => 1, 'type' => 'plan'],
                ['name' => 'Item B', 'original_price' => 200.00, 'quantity' => 1, 'type' => 'plan'],
            ],
        ]);

        $this->assertEquals(300.00, (float)$bundle->original_price);
    }

    public function test_calculates_discount_percent()
    {
        $bundle = $this->service->createBundle([
            'name' => '优惠套餐',
            'bundle_price' => 200.00,
            'items' => [
                ['name' => 'Item A', 'original_price' => 200.00, 'quantity' => 1, 'type' => 'plan'],
                ['name' => 'Item B', 'original_price' => 200.00, 'quantity' => 1, 'type' => 'plan'],
            ],
        ]);

        // original_price = 400, bundle_price = 200, discount = 50%
        $this->assertEquals(400, (float)$bundle->original_price);
        $this->assertEquals(50.0, $bundle->discount_percent);
        $this->assertEquals(200, $bundle->savings);
    }

    public function test_can_update_bundle()
    {
        $bundle = $this->service->createBundle([
            'name' => '原套餐',
            'bundle_price' => 100.00,
            'items' => [
                ['name' => 'Item 1', 'original_price' => 50.00, 'quantity' => 1, 'type' => 'plan'],
            ],
        ]);

        $updated = $this->service->updateBundle($bundle, [
            'name' => '已更新套餐',
            'bundle_price' => 150.00,
            'items' => [
                ['name' => 'Item New', 'original_price' => 100.00, 'quantity' => 2, 'type' => 'plan'],
            ],
        ]);

        $this->assertEquals('已更新套餐', $updated->name);
        $this->assertEquals(150.00, (float)$updated->bundle_price);
        $this->assertCount(1, $updated->items);
    }

    public function test_can_delete_bundle()
    {
        $bundle = ProductBundle::create([
            'name' => 'Test Bundle',
            'slug' => 'test-bundle',
            'bundle_price' => 100.00,
        ]);

        $this->service->deleteBundle($bundle);
        $this->assertSoftDeleted($bundle);
    }

    public function test_can_purchase_bundle()
    {
        $bundle = $this->service->createBundle([
            'name' => '可购买套餐',
            'bundle_price' => 299.00,
            'is_active' => true,
            'items' => [
                ['name' => 'Item', 'original_price' => 299.00, 'quantity' => 1, 'type' => 'plan'],
            ],
        ]);

        $purchase = $this->service->purchase($bundle->id, $this->customer->id, $this->tenant->id);

        $this->assertInstanceOf(BundlePurchase::class, $purchase);
        $this->assertEquals('completed', $purchase->status);
        $this->assertEquals(299.00, (float)$purchase->paid_amount);
        $this->assertStringStartsWith('BND-', $purchase->order_no);
    }

    public function test_purchase_inactive_bundle_fails()
    {
        $bundle = ProductBundle::create([
            'name' => '下架套餐',
            'slug' => 'inactive-bundle',
            'bundle_price' => 100.00,
            'is_active' => false,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('套餐已下架');
        $this->service->purchase($bundle->id, $this->customer->id, $this->tenant->id);
    }

    public function test_purchase_out_of_stock_fails()
    {
        $bundle = ProductBundle::create([
            'name' => '无库存套餐',
            'slug' => 'out-of-stock',
            'bundle_price' => 100.00,
            'is_active' => true,
            'stock' => 0,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('库存不足');
        $this->service->purchase($bundle->id, $this->customer->id, $this->tenant->id);
    }

    public function test_purchase_exceeds_limit_fails()
    {
        $bundle = ProductBundle::create([
            'name' => '限购套餐',
            'slug' => 'limited',
            'bundle_price' => 100.00,
            'is_active' => true,
            'max_purchase_per_user' => 1,
        ]);

        // 第一次购买成功
        $this->service->purchase($bundle->id, $this->customer->id, $this->tenant->id);

        // 第二次购买应失败
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('限购');
        $this->service->purchase($bundle->id, $this->customer->id, $this->tenant->id);
    }

    public function test_get_published_bundles()
    {
        ProductBundle::create(['name' => 'Active', 'slug' => 'active', 'bundle_price' => 100, 'is_active' => true, 'sort_order' => 1]);
        ProductBundle::create(['name' => 'Inactive', 'slug' => 'inactive', 'bundle_price' => 200, 'is_active' => false]);

        $published = $this->service->getPublishedBundles();
        $this->assertCount(1, $published);
        $this->assertEquals('Active', $published->first()->name);
    }

    public function test_get_stats()
    {
        ProductBundle::create(['name' => 'B1', 'slug' => 'b1', 'bundle_price' => 100, 'is_active' => true]);
        ProductBundle::create(['name' => 'B2', 'slug' => 'b2', 'bundle_price' => 200, 'is_active' => true]);
        ProductBundle::create(['name' => 'B3', 'slug' => 'b3', 'bundle_price' => 300, 'is_active' => false]);

        $bundle = ProductBundle::where('slug', 'b1')->firstOrFail();

        BundlePurchase::create([
            'product_bundle_id' => $bundle->id,
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'order_no' => 'BND-TEST-001',
            'paid_amount' => 100.00,
            'status' => 'completed',
            'purchased_at' => now(),
        ]);

        $stats = $this->service->getStats();

        $this->assertEquals(3, $stats['total_bundles']);
        $this->assertEquals(2, $stats['active_bundles']);
        $this->assertEquals(1, $stats['total_purchases']);
        $this->assertEquals(100.00, $stats['total_revenue']);
    }

    public function test_stock_decrements_on_purchase()
    {
        $bundle = ProductBundle::create([
            'name' => '库存套餐',
            'slug' => 'stock-test',
            'bundle_price' => 100.00,
            'is_active' => true,
            'stock' => 5,
        ]);

        $this->service->purchase($bundle->id, $this->customer->id, $this->tenant->id);

        $bundle->refresh();
        $this->assertEquals(4, $bundle->stock);
    }

    public function test_unlimited_stock_returns_true()
    {
        $bundle = ProductBundle::create([
            'name' => '无限库存',
            'slug' => 'unlimited',
            'bundle_price' => 100,
            'is_active' => true,
            'stock' => null,
        ]);

        $this->assertTrue($bundle->hasStock());
    }

    public function test_list_bundles_with_filters()
    {
        ProductBundle::create(['name' => 'Active Bundle', 'slug' => 'ab1', 'bundle_price' => 100, 'is_active' => true]);
        ProductBundle::create(['name' => 'Inactive Bundle', 'slug' => 'ib1', 'bundle_price' => 200, 'is_active' => false]);

        $results = $this->service->listBundles(['is_active' => 'true']);
        $this->assertEquals(1, $results->total());

        $searched = $this->service->listBundles(['search' => 'Inactive']);
        $this->assertEquals(1, $searched->total());
    }
}
