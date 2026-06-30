<?php

namespace Tests\Unit\Services;

use App\Models\Customer;
use App\Models\License;
use App\Models\LicenseTransferRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransferServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TransferService $service;
    protected User $admin;
    protected Customer $customer;
    protected License $license;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TransferService();

        Tenant::create(['id' => 1, 'name' => 'Default']);

        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);
        $this->actingAs($this->admin);

        $this->customer = Customer::create([
            'name' => 'Test Corp', 'email' => 'corp@test.com',
            'tenant_id' => 1,
        ]);

        $this->license = License::create([
            'license_key' => 'HWT-TEST-001',
            'customer_id' => $this->customer->id,
            'type' => 'enterprise',
            'status' => 'active',
            'tenant_id' => 1,
        ]);
    }

    public function test_can_create_device_transfer_request()
    {
        $request = $this->service->createRequest([
            'type' => 'device_transfer',
            'license_id' => $this->license->id,
            'target_device_fingerprint' => 'fp:abc123',
            'target_device_name' => 'New Server',
            'reason' => '更换服务器硬件',
        ]);

        $this->assertInstanceOf(LicenseTransferRequest::class, $request);
        $this->assertEquals('device_transfer', $request->type);
        $this->assertEquals('pending', $request->status);
        $this->assertNotNull($request->reference);
        $this->assertStringStartsWith('TX-', $request->reference);
        $this->assertEquals($this->admin->id, $request->requested_by);
    }

    public function test_can_create_customer_transfer_request()
    {
        $targetCustomer = Customer::create(['name' => 'Target Corp', 'email' => 'target@test.com', 'tenant_id' => 1]);

        $request = $this->service->createRequest([
            'type' => 'customer_transfer',
            'license_id' => $this->license->id,
            'target_customer_id' => $targetCustomer->id,
            'reason' => '公司重组，License 过户',
        ]);

        $this->assertEquals('customer_transfer', $request->type);
        $this->assertEquals($targetCustomer->id, $request->target_customer_id);
    }

    public function test_cannot_transfer_inactive_license()
    {
        $this->license->update(['status' => 'expired']);

        $this->expectException(\RuntimeException::class);
        $this->service->createRequest([
            'type' => 'device_transfer',
            'license_id' => $this->license->id,
            'target_device_fingerprint' => 'fp:test',
        ]);
    }

    public function test_can_approve_device_transfer()
    {
        $request = $this->service->createRequest([
            'type' => 'device_transfer',
            'license_id' => $this->license->id,
            'target_device_fingerprint' => 'fp:target-device',
            'reason' => '硬件升级',
        ]);

        $approved = $this->service->approveRequest($request->fresh(), '已批准');

        $this->assertEquals('completed', $approved->status);
        $this->assertEquals($this->admin->id, $approved->approved_by);
        $this->assertNotNull($approved->approved_at);
    }

    public function test_can_approve_customer_transfer()
    {
        $target = Customer::create(['name' => 'Target', 'email' => 't@t.com', 'tenant_id' => 1]);

        $request = $this->service->createRequest([
            'type' => 'customer_transfer',
            'license_id' => $this->license->id,
            'target_customer_id' => $target->id,
        ]);

        $approved = $this->service->approveRequest($request->fresh());

        $this->assertEquals('completed', $approved->status);
        $this->assertEquals($target->id, $this->license->fresh()->customer_id);
    }

    public function test_can_reject_request()
    {
        $request = $this->service->createRequest([
            'type' => 'device_transfer',
            'license_id' => $this->license->id,
            'target_device_fingerprint' => 'fp:test',
        ]);

        $rejected = $this->service->rejectRequest($request->fresh(), '不满足转移条件');
        $this->assertEquals('rejected', $rejected->status);
        $this->assertEquals('不满足转移条件', $rejected->admin_notes);
    }

    public function test_can_cancel_request()
    {
        $request = $this->service->createRequest([
            'type' => 'device_transfer',
            'license_id' => $this->license->id,
            'target_device_fingerprint' => 'fp:test',
        ]);

        $cancelled = $this->service->cancelRequest($request->fresh());
        $this->assertEquals('cancelled', $cancelled->status);
    }

    public function test_cannot_process_already_processed_request()
    {
        $this->expectException(\RuntimeException::class);

        $request = $this->service->createRequest([
            'type' => 'device_transfer',
            'license_id' => $this->license->id,
            'target_device_fingerprint' => 'fp:test',
        ]);

        $request->update(['status' => 'completed']);
        $this->service->approveRequest($request->fresh());
    }

    public function test_can_list_requests()
    {
        $this->service->createRequest(['type' => 'device_transfer', 'license_id' => $this->license->id, 'target_device_fingerprint' => 'fp:1']);
        $this->service->createRequest(['type' => 'customer_transfer', 'license_id' => $this->license->id, 'target_customer_id' => $this->customer->id]);

        $this->assertEquals(2, $this->service->listRequests()->total());
        $this->assertEquals(1, $this->service->listRequests(['type' => 'device_transfer'])->total());
    }

    public function test_can_get_stats()
    {
        $this->service->createRequest(['type' => 'device_transfer', 'license_id' => $this->license->id, 'target_device_fingerprint' => 'fp:1']);
        $this->service->createRequest(['type' => 'customer_transfer', 'license_id' => $this->license->id, 'target_customer_id' => $this->customer->id]);

        $stats = $this->service->getStats();
        $this->assertEquals(2, $stats['total']);
        $this->assertEquals(2, $stats['pending']);
        $this->assertArrayHasKey('by_type', $stats);
    }

    public function test_can_get_user_requests()
    {
        $this->service->createRequest(['type' => 'device_transfer', 'license_id' => $this->license->id, 'target_device_fingerprint' => 'fp:1']);

        $result = $this->service->myRequests($this->admin);
        $this->assertEquals(1, $result->total());
    }
}
