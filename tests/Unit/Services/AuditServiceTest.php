<?php

namespace Tests\Unit\Services;

use App\Enums\LicenseStatus;
use App\Models\Customer;
use App\Models\Device;
use App\Models\License;
use App\Models\Log;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private License $license;
    private AuditService $auditService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auditService = app(AuditService::class);
        $this->tenant = Tenant::factory()->create();
        $this->license = License::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_log_license_status_changed(): void
    {
        $log = $this->auditService->licenseStatusChanged(
            tenantId: $this->tenant->id,
            licenseId: $this->license->id,
            licenseKey: $this->license->license_key,
            oldStatus: LicenseStatus::Active->value,
            newStatus: LicenseStatus::Expired->value,
            reason: '测试过期',
        );

        $this->assertInstanceOf(Log::class, $log);
        $this->assertDatabaseHas('logs', [
            'id' => $log->id,
            'tenant_id' => $this->tenant->id,
            'license_id' => $this->license->id,
            'action' => 'license.status_changed',
            'type' => 'audit',
        ]);

        $this->assertEquals('expired', $log->payload['new_status']);
        $this->assertEquals('测试过期', $log->payload['reason']);
    }

    public function test_log_license_created(): void
    {
        $log = $this->auditService->licenseCreated(
            tenantId: $this->tenant->id,
            licenseId: $this->license->id,
            licenseKey: $this->license->license_key,
            licenseType: 'standard',
        );

        $this->assertDatabaseHas('logs', [
            'id' => $log->id,
            'action' => 'license.created',
        ]);
    }

    public function test_log_device_activated(): void
    {
        $device = Device::factory()->create([
            'tenant_id' => $this->tenant->id,
            'license_id' => $this->license->id,
        ]);

        $log = $this->auditService->deviceActivated(
            tenantId: $this->tenant->id,
            licenseId: $this->license->id,
            licenseKey: $this->license->license_key,
            deviceId: $device->id,
            fingerprint: $device->fingerprint,
        );

        $this->assertDatabaseHas('logs', [
            'id' => $log->id,
            'device_id' => $device->id,
            'license_id' => $this->license->id,
            'action' => 'device.activated',
        ]);
    }

    public function test_log_device_deactivated(): void
    {
        $device = Device::factory()->create([
            'tenant_id' => $this->tenant->id,
            'license_id' => $this->license->id,
        ]);

        $log = $this->auditService->deviceDeactivated(
            tenantId: $this->tenant->id,
            licenseId: $this->license->id,
            licenseKey: $this->license->license_key,
            deviceId: $device->id,
            fingerprint: $device->fingerprint,
        );

        $this->assertDatabaseHas('logs', [
            'id' => $log->id,
            'action' => 'device.deactivated',
        ]);
    }

    public function test_log_user_action(): void
    {
        $log = $this->auditService->userAction(
            action: 'user.login',
            description: '用户登录成功',
            tenantId: $this->tenant->id,
            payload: ['ip' => '127.0.0.1'],
        );

        $this->assertDatabaseHas('logs', [
            'id' => $log->id,
            'action' => 'user.login',
            'type' => 'audit',
        ]);
    }

    public function test_log_security_event(): void
    {
        $log = $this->auditService->securityEvent(
            action: 'auth.failed',
            description: '登录失败: 密码错误',
            tenantId: $this->tenant->id,
            payload: ['attempts' => 3],
        );

        $this->assertDatabaseHas('logs', [
            'id' => $log->id,
            'action' => 'auth.failed',
            'type' => 'security',
        ]);
    }

    public function test_log_error(): void
    {
        $log = $this->auditService->error(
            action: 'payment.failed',
            description: '支付回调处理失败',
            tenantId: $this->tenant->id,
            payload: ['error' => 'timeout'],
        );

        $this->assertDatabaseHas('logs', [
            'id' => $log->id,
            'action' => 'payment.failed',
            'type' => 'error',
        ]);
    }

    public function test_audit_log_can_filter_by_license_id(): void
    {
        $this->auditService->licenseStatusChanged(
            tenantId: $this->tenant->id,
            licenseId: $this->license->id,
            licenseKey: $this->license->license_key,
            oldStatus: 'active',
            newStatus: 'expired',
        );

        $logs = Log::where('license_id', $this->license->id)->get();

        $this->assertCount(1, $logs);
    }

    public function test_audit_log_has_tenant_isolation(): void
    {
        $otherTenant = Tenant::factory()->create();

        $this->auditService->licenseStatusChanged(
            tenantId: $this->tenant->id,
            licenseId: $this->license->id,
            licenseKey: $this->license->license_key,
            oldStatus: 'active',
            newStatus: 'expired',
        );

        $otherLogs = Log::where('tenant_id', $otherTenant->id)->get();
        $this->assertCount(0, $otherLogs);
    }
}
