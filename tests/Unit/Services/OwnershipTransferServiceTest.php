<?php

namespace Tests\Unit\Services;

use App\Models\Customer;
use App\Models\License;
use App\Models\OwnershipTransferRequest;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\OwnershipTransferService;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class OwnershipTransferServiceTest extends TestCase
{
    use RefreshDatabase;

    private OwnershipTransferService $service;
    private Tenant $tenant;
    private User $user;
    private Customer $sourceCustomer;
    private Customer $targetCustomer;
    private License $license;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OwnershipTransferService::class);
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->actingAs($this->user);

        $this->sourceCustomer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);
        $this->targetCustomer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->license = License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->sourceCustomer->id,
            'status' => 'active',
        ]);

        $this->product = Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product-' . uniqid(),
            'version' => '1.0.0',
            'is_active' => true,
        ]);
    }

    public function test_can_create_ownership_transfer_request_for_license()
    {
        $request = $this->service->createRequest([
            'transferable_type' => 'license',
            'transferable_id' => $this->license->id,
            'target_customer_id' => $this->targetCustomer->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->assertInstanceOf(OwnershipTransferRequest::class, $request);
        $this->assertEquals('pending_source', $request->status);
        $this->assertEquals($this->sourceCustomer->id, $request->source_customer_id);
        $this->assertEquals($this->targetCustomer->id, $request->target_customer_id);
        $this->assertStringStartsWith('OT-', $request->reference);
        $this->assertNotEmpty($request->source_info);
    }

    public function test_can_create_ownership_transfer_request_for_product()
    {
        License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->sourceCustomer->id,
            'product_id' => $this->product->id,
            'status' => 'active',
        ]);

        $request = $this->service->createRequest([
            'transferable_type' => 'product',
            'transferable_id' => $this->product->id,
            'target_customer_id' => $this->targetCustomer->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->assertEquals('pending_source', $request->status);
        $this->assertArrayHasKey('product_name', $request->source_info);
    }

    public function test_rejects_same_source_and_target_customer()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('源客户和目标客户不能相同');

        $this->service->createRequest([
            'transferable_type' => 'license',
            'transferable_id' => $this->license->id,
            'target_customer_id' => $this->sourceCustomer->id,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_source_confirmation_changes_status()
    {
        $request = $this->service->createRequest([
            'transferable_type' => 'license',
            'transferable_id' => $this->license->id,
            'target_customer_id' => $this->targetCustomer->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $result = $this->service->confirmBySource($request);

        $this->assertEquals('pending_target', $result->status);
        $this->assertNotNull($result->source_confirmed_at);
    }

    public function test_target_confirmation_changes_status()
    {
        $request = $this->createTransferRequest('pending_target');

        $result = $this->service->confirmByTarget($request->fresh());

        $this->assertEquals('pending_approval', $result->status);
        $this->assertNotNull($result->target_confirmed_at);
    }

    public function test_approve_and_execute_migrates_license()
    {
        $request = $this->createTransferRequest('pending_approval', true);

        $result = $this->service->approveAndExecute($request->fresh());

        $this->assertEquals('completed', $result->status);
        $this->assertNotNull($result->approved_at);
        $this->assertEquals('completed', $result->migration_summary['status']);

        $this->license->refresh();
        $this->assertEquals($this->targetCustomer->id, $this->license->customer_id);

        $this->assertGreaterThan(0, $result->transferRecords->count());
        $this->assertEquals('license', $result->transferRecords->first()->entity_type);
    }

    public function test_reject_transfer()
    {
        $request = $this->createTransferRequest('pending_approval');

        $result = $this->service->reject($request->fresh(), '测试拒绝');

        $this->assertEquals('rejected', $result->status);
    }

    public function test_cancel_transfer()
    {
        $request = $this->createTransferRequest('pending_source');

        $result = $this->service->cancel($request->fresh());

        $this->assertEquals('cancelled', $result->status);
        $this->assertNotNull($result->cancelled_at);
    }

    public function test_list_requests()
    {
        $this->createTransferRequest('pending_source');
        $this->createTransferRequest('completed', true);

        $result = $this->service->listRequests($this->tenant->id);
        $this->assertGreaterThanOrEqual(2, $result->total());
    }

    public function test_get_stats()
    {
        $this->createTransferRequest('pending_source');
        $this->createTransferRequest('pending_source');
        $this->createTransferRequest('completed', true);

        $stats = $this->service->getStats($this->tenant->id);

        $this->assertEquals(3, $stats['total']);
        $this->assertEquals(2, $stats['pending']);
        $this->assertEquals(1, $stats['completed']);
    }

    public function test_get_transferables_licenses()
    {
        License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->sourceCustomer->id,
            'status' => 'active',
        ]);

        $result = $this->service->getTransferables('license', $this->tenant->id);
        $this->assertCount(2, $result);
    }

    public function test_get_transferables_products()
    {
        // Product 表没有 tenant_id，服务端查询时不会按tenant过滤，但会过 is_active
        Product::create([
            'name' => 'Another Product',
            'slug' => 'another-product-' . uniqid(),
            'version' => '1.0.0',
            'is_active' => true,
        ]);

        $result = $this->service->getTransferables('product', $this->tenant->id);
        $this->assertGreaterThanOrEqual(2, count($result));
    }

    public function test_search_customers()
    {
        $result = $this->service->searchCustomers($this->tenant->id, $this->user->email);
        $this->assertGreaterThanOrEqual(1, count($result));
    }

    private function createTransferRequest(string $status, bool $confirmed = false): OwnershipTransferRequest
    {
        $request = OwnershipTransferRequest::create([
            'reference' => 'OT-' . now()->format('Ymd') . '-' . strtoupper(\Illuminate\Support\Str::random(6)),
            'tenant_id' => $this->tenant->id,
            'transferable_type' => 'license',
            'transferable_id' => $this->license->id,
            'source_customer_id' => $this->sourceCustomer->id,
            'target_customer_id' => $this->targetCustomer->id,
            'status' => $status,
            'requested_by' => $this->user->id,
            'audit_log' => [['action' => 'created', 'by' => $this->user->id, 'at' => now()->toIso8601String()]],
        ]);

        if ($confirmed) {
            $request->update([
                'source_confirmed_by' => $this->user->id,
                'source_confirmed_at' => now(),
                'target_confirmed_by' => $this->user->id,
                'target_confirmed_at' => now(),
            ]);
        }

        return $request;
    }
}
