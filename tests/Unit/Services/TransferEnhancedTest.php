<?php

namespace Tests\Unit\Services;

use App\Models\License;
use App\Models\LicenseTransferRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TransferService;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class TransferEnhancedTest extends TestCase
{
    use RefreshDatabase;

    protected TransferService $service;
    protected Tenant $tenant;
    protected License $license;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new TransferService();
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->license = License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
        ]);
    }

    protected function createPendingTransfer(): LicenseTransferRequest
    {
        $request = LicenseTransferRequest::factory()->create([
            'license_id' => $this->license->id,
            'type' => 'device_transfer',
            'status' => 'pending',
            'target_device_fingerprint' => 'v2:' . str_repeat('a', 61),
            'target_device_name' => 'New PC',
        ]);
        return $request;
    }

    /** @test */
    public function generates_verification_code_for_device_transfer()
    {
        $request = $this->createPendingTransfer();

        $code = $this->service->generateVerificationCode($request);

        $this->assertEquals(6, strlen($code));
        $this->assertIsNumeric($code);

        $request->refresh();
        $this->assertEquals($code, $request->verification_token);
        $this->assertNotNull($request->verification_expires_at);
    }

    /** @test */
    public function verifies_valid_code()
    {
        $request = $this->createPendingTransfer();

        $code = $this->service->generateVerificationCode($request);

        $verified = $this->service->verifyCode($request, $code);
        $this->assertTrue($verified);
    }

    /** @test */
    public function rejects_invalid_code()
    {
        $request = $this->createPendingTransfer();

        $this->service->generateVerificationCode($request);

        $verified = $this->service->verifyCode($request, '000000');
        $this->assertFalse($verified);
    }

    /** @test */
    public function rejects_code_for_non_device_transfer()
    {
        $request = LicenseTransferRequest::factory()->customerTransfer()->create([
            'license_id' => $this->license->id,
            'status' => 'pending',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->service->generateVerificationCode($request);
    }

    /** @test */
    public function lists_requests_by_tenant()
    {
        $this->createPendingTransfer();

        // 另一个租户
        $otherTenant = Tenant::factory()->create();
        $otherLicense = License::factory()->create(['tenant_id' => $otherTenant->id]);
        LicenseTransferRequest::factory()->create(['license_id' => $otherLicense->id]);

        $results = $this->service->listRequestsByTenant($this->tenant->id);
        $this->assertEquals(1, $results->total());

        $otherResults = $this->service->listRequestsByTenant($otherTenant->id);
        $this->assertEquals(1, $otherResults->total());
    }

    /** @test */
    public function lists_requests_with_filters_by_tenant()
    {
        $this->createPendingTransfer();

        $results = $this->service->listRequestsByTenant($this->tenant->id, ['status' => 'pending']);
        $this->assertEquals(1, $results->total());

        $results = $this->service->listRequestsByTenant($this->tenant->id, ['status' => 'completed']);
        $this->assertEquals(0, $results->total());
    }

    /** @test */
    public function gets_stats_by_tenant()
    {
        $request = $this->createPendingTransfer();

        $stats = $this->service->getStatsByTenant($this->tenant->id);

        $this->assertEquals(1, $stats['total']);
        $this->assertEquals(1, $stats['pending']);
        $this->assertArrayHasKey('by_type', $stats);
        $this->assertEquals(1, $stats['by_type']['device_transfer']);
    }

    /** @test */
    public function lists_requests_by_tenant_is_empty_for_unrelated_tenant()
    {
        $this->createPendingTransfer();

        $otherTenant = Tenant::factory()->create();
        $results = $this->service->listRequestsByTenant($otherTenant->id);
        $this->assertEquals(0, $results->total());
    }

    /** @test */
    public function factory_creates_valid_device_transfer_request()
    {
        $request = LicenseTransferRequest::factory()->deviceTransfer()->create([
            'license_id' => $this->license->id,
        ]);

        $this->assertDatabaseHas('license_transfer_requests', [
            'id' => $request->id,
            'type' => 'device_transfer',
            'status' => 'pending',
        ]);
        $this->assertNotNull($request->target_device_fingerprint);
    }

    /** @test */
    public function factory_creates_valid_customer_transfer_request()
    {
        $request = LicenseTransferRequest::factory()->customerTransfer()->create([
            'license_id' => $this->license->id,
        ]);

        $this->assertEquals('customer_transfer', $request->type);
    }
}
