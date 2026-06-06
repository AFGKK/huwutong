<?php

namespace Tests\Unit\Services;

use App\Enums\LicenseStatus;
use App\Models\License;
use App\Services\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicenseStatusMachineTest extends TestCase
{
    use RefreshDatabase;

    private LicenseService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(LicenseService::class);
    }

    private function makeLicense(string $status = 'pending'): License
    {
        return License::factory()->create(['status' => $status]);
    }

    // ─── 状态转移矩阵测试 ───

    public function test_pending_can_activate(): void
    {
        $license = $this->makeLicense('pending');
        $result = $this->service->activate($license);
        $this->assertEquals('active', $result->status);
    }

    public function test_pending_cannot_transition_to_expired(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $license = $this->makeLicense('pending');
        $this->service->expire($license);
    }

    public function test_active_can_be_suspended(): void
    {
        $license = $this->makeLicense('active');
        $result = $this->service->suspend($license);
        $this->assertEquals('suspended', $result->status);
    }

    public function test_active_can_be_frozen(): void
    {
        $license = $this->makeLicense('active');
        $result = $this->service->freeze($license);
        $this->assertEquals('frozen', $result->status);
    }

    public function test_active_can_expire(): void
    {
        $license = $this->makeLicense('active');
        $result = $this->service->expire($license);
        $this->assertEquals('expired', $result->status);
    }

    public function test_active_can_be_revoked(): void
    {
        $license = $this->makeLicense('active');
        $result = $this->service->revoke($license);
        $this->assertEquals('revoked', $result->status);
    }

    public function test_active_can_be_refunded(): void
    {
        $license = $this->makeLicense('active');
        $result = $this->service->refund($license);
        $this->assertEquals('refunded', $result->status);
    }

    public function test_active_can_be_blacklisted(): void
    {
        $license = $this->makeLicense('active');
        $result = $this->service->blacklist($license);
        $this->assertEquals('blacklisted', $result->status);
    }

    public function test_expired_can_renew(): void
    {
        $license = $this->makeLicense('expired');
        $result = $this->service->renew($license);
        $this->assertEquals('active', $result->status);
    }

    public function test_revoked_cannot_go_back_to_active(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $license = $this->makeLicense('revoked');
        $this->service->restore($license);
    }

    public function test_revoked_can_only_go_to_blacklisted(): void
    {
        $license = $this->makeLicense('revoked');
        $result = $this->service->blacklist($license);
        $this->assertEquals('blacklisted', $result->status);
    }

    public function test_blacklisted_is_terminal(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $license = $this->makeLicense('blacklisted');
        $this->service->restore($license);
    }

    // ─── 全状态转移矩阵验证 ───

    public function test_all_transition_rules_are_strict(): void
    {
        $allStatuses = LicenseStatus::cases();
        $transitions = LicenseStatus::transitions();

        foreach ($allStatuses as $from) {
            $allowed = $transitions[$from->value] ?? [];

            foreach ($allStatuses as $to) {
                $canTransition = $from->canTransitionTo($to);

                if (in_array($to->value, $allowed, true)) {
                    $this->assertTrue(
                        $canTransition,
                        "{$from->value} → {$to->value} 应允许转移但实际不允许"
                    );
                } else {
                    $this->assertFalse(
                        $canTransition,
                        "{$from->value} → {$to->value} 应不允许转移但实际允许"
                    );
                }
            }
        }
    }

    // ─── 验证测试 ───

    public function test_validate_returns_valid_for_active_license(): void
    {
        $license = License::factory()->create([
            'status' => 'active',
            'expires_at' => now()->addYear(),
            'max_devices' => 5,
        ]);

        $result = $this->service->validate($license);
        $this->assertTrue($result['valid']);
    }

    public function test_validate_returns_invalid_for_expired_license(): void
    {
        $license = License::factory()->create([
            'status' => 'active',
            'expires_at' => now()->subDay(),
        ]);

        $result = $this->service->validate($license);
        $this->assertFalse($result['valid']);
    }

    public function test_validate_returns_invalid_for_pending_license(): void
    {
        $license = $this->makeLicense('pending');
        $result = $this->service->validate($license);
        $this->assertFalse($result['valid']);
    }
}
