<?php

namespace Tests\Unit\Services;

use App\Enums\LicenseStatus;
use App\Models\License;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LicenseServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Product $product;
    private License $license;
    private LicenseService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(LicenseService::class);
        $this->tenant = Tenant::factory()->create();
        $this->product = Product::factory()->create();
        $this->license = License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'status' => LicenseStatus::Active->value,
            'expires_at' => now()->addYear(),
        ]);
    }

    public function test_create_license(): void
    {
        $license = $this->service->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'license_key' => 'HWT-TEST-0011223344-ABCD',
            'type' => 'standard',
            'expires_at' => now()->addYear(),
            'seats' => 5,
            'max_devices' => 3,
        ]);

        $this->assertDatabaseHas('licenses', [
            'id' => $license->id,
            'license_key' => 'HWT-TEST-0011223344-ABCD',
            'status' => LicenseStatus::Pending->value,
        ]);
    }

    public function test_activate_license(): void
    {
        $pendingLicense = License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'status' => LicenseStatus::Pending->value,
            'expires_at' => now()->addYear(),
        ]);

        $activated = $this->service->activate($pendingLicense);

        $this->assertEquals(LicenseStatus::Active->value, $activated->status);
        $this->assertNotNull($activated->activated_at);
    }

    public function test_suspend_license(): void
    {
        $suspended = $this->service->suspend($this->license, '测试挂起');

        $this->assertEquals(LicenseStatus::Suspended->value, $suspended->status);
    }

    public function test_freeze_license(): void
    {
        $frozen = $this->service->freeze($this->license, '风控冻结');

        $this->assertEquals(LicenseStatus::Frozen->value, $frozen->status);
    }

    public function test_restore_license(): void
    {
        $this->license->update(['status' => LicenseStatus::Frozen->value]);

        $restored = $this->service->restore($this->license, '已解冻');

        $this->assertEquals(LicenseStatus::Active->value, $restored->status);
    }

    public function test_expire_license(): void
    {
        $expired = $this->service->expire($this->license, '到期自动过期');

        $this->assertEquals(LicenseStatus::Expired->value, $expired->status);
    }

    public function test_renew_license(): void
    {
        $this->license->update(['status' => LicenseStatus::Expired->value]);

        $renewed = $this->service->renew($this->license, '客户续费');

        $this->assertEquals(LicenseStatus::Active->value, $renewed->status);
    }

    public function test_revoke_license(): void
    {
        $revoked = $this->service->revoke($this->license, '违规使用');

        $this->assertEquals(LicenseStatus::Revoked->value, $revoked->status);
    }

    public function test_refund_license(): void
    {
        $refunded = $this->service->refund($this->license, '客户申请退款');

        $this->assertEquals(LicenseStatus::Refunded->value, $refunded->status);
    }

    public function test_blacklist_license(): void
    {
        $blacklisted = $this->service->blacklist($this->license, '恶意破解');

        $this->assertEquals(LicenseStatus::Blacklisted->value, $blacklisted->status);
    }

    public function test_invalid_transition_throws_exception(): void
    {
        $this->expectException(ValidationException::class);

        // Pending 不能直接过期
        $pendingLicense = License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'status' => LicenseStatus::Pending->value,
        ]);

        $this->service->expire($pendingLicense);
    }

    public function test_validate_valid_license(): void
    {
        $result = $this->service->validate($this->license);

        $this->assertTrue($result['valid']);
    }

    public function test_validate_expired_license(): void
    {
        $this->license->update(['expires_at' => now()->subDay()]);

        $result = $this->service->validate($this->license);

        $this->assertFalse($result['valid']);
    }

    public function test_get_status_info(): void
    {
        $info = $this->service->getStatusInfo($this->license);

        $this->assertEquals(LicenseStatus::Active->value, $info['current_status']);
        $this->assertTrue($info['is_usable']);
        $this->assertFalse($info['is_terminal']);
        $this->assertFalse($info['is_expired']);
        $this->assertContains('suspended', $info['available_transitions']);
        $this->assertContains('expired', $info['available_transitions']);
    }

    public function test_status_change_triggers_event_and_audit_log(): void
    {
        $this->service->suspend($this->license, '审计测试');

        $this->assertDatabaseHas('logs', [
            'license_id' => $this->license->id,
            'action' => 'license.status_changed',
            'type' => 'audit',
        ]);
    }
}
