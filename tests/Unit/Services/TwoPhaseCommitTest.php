<?php

namespace Tests\Unit\Services;

use App\Enums\LicenseStatus;
use App\Models\AuthorizationReservation;
use App\Models\License;
use App\Services\TwoPhaseCommitService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TwoPhaseCommitTest extends TestCase
{
    use RefreshDatabase;

    protected TwoPhaseCommitService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TwoPhaseCommitService::class);
    }

    /** @test */
    public function reserve_creates_reservation_for_active_license()
    {
        $license = License::factory()->create([
            'status' => LicenseStatus::Pending->value,
            'max_devices' => 5,
        ]);

        $result = $this->service->reserve($license, [
            'fingerprint' => 'fp-test-001',
            'ip_address' => '192.168.1.1',
            'payload' => ['platform' => 'windows'],
        ]);

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['reservation']);
        $this->assertEquals('reserved', $result['reservation']->status);
        $this->assertNotNull($result['reservation']->reservation_token);
        $this->assertTrue($result['reservation']->expires_at->isFuture());
        $this->assertCount(1, $result['reservation']->logs);
        $this->assertEquals('reserve', $result['reservation']->logs->first()->action);
    }

    /** @test */
    public function reserve_returns_existing_token_when_duplicate_fingerprint_reservation_exists()
    {
        $license = License::factory()->create([
            'status' => LicenseStatus::Pending->value,
            'max_devices' => 5,
        ]);

        $first = $this->service->reserve($license, [
            'fingerprint' => 'fp-duplicate',
            'ip_address' => '192.168.1.1',
        ]);
        $token = $first['reservation']->reservation_token;

        $second = $this->service->reserve($license, [
            'fingerprint' => 'fp-duplicate',
            'ip_address' => '192.168.1.2',
        ]);

        $this->assertTrue($second['success']);
        $this->assertTrue($second['is_existing']);
        $this->assertEquals($token, $second['reservation']->reservation_token);
    }

    /** @test */
    public function reserve_fails_when_device_limit_exceeded()
    {
        $license = License::factory()->create([
            'status' => LicenseStatus::Active->value,
            'max_devices' => 1,
        ]);

        // 占用一个设备
        $license->devices()->create([
            'tenant_id' => $license->tenant_id,
            'fingerprint' => 'existing-device',
        ]);

        $result = $this->service->reserve($license, [
            'fingerprint' => 'new-device',
            'ip_address' => '192.168.1.1',
        ]);

        $this->assertFalse($result['success']);
        $this->assertEquals('DEVICE_LIMIT_EXCEEDED', $result['error']);
    }

    /** @test */
    public function commit_completes_reservation_and_activates_license()
    {
        $license = License::factory()->create([
            'status' => LicenseStatus::Pending->value,
            'max_devices' => 5,
        ]);

        $reserveResult = $this->service->reserve($license, [
            'fingerprint' => 'fp-commit-test',
            'ip_address' => '192.168.1.1',
        ]);
        $token = $reserveResult['reservation']->reservation_token;

        $commitResult = $this->service->commit($token);

        $this->assertTrue($commitResult['success']);
        $this->assertEquals('committed', $commitResult['reservation']->status);
        $this->assertNotNull($commitResult['reservation']->committed_at);
        $this->assertEquals(LicenseStatus::Active->value, $commitResult['license']->status);

        // 验证日志
        $reservation = $commitResult['reservation']->fresh();
        $this->assertCount(2, $reservation->logs);
        $this->assertEquals('commit', $reservation->logs->last()->action);
    }

    /** @test */
    public function commit_fails_for_expired_reservation()
    {
        $license = License::factory()->create([
            'status' => LicenseStatus::Pending->value,
            'max_devices' => 5,
        ]);

        // 创建一条 status=reserved 但 expires_at 已过期的预留
        $reservation = AuthorizationReservation::factory()->create([
            'license_id' => $license->id,
            'tenant_id' => $license->tenant_id,
            'status' => 'reserved',
            'expires_at' => now()->subMinute(),
        ]);

        $result = $this->service->commit($reservation->reservation_token);

        $this->assertFalse($result['success']);
        $this->assertEquals('RESERVATION_EXPIRED', $result['error']);
        $this->assertEquals('expired', $reservation->fresh()->status);
    }

    /** @test */
    public function commit_fails_for_already_committed_reservation()
    {
        $reservation = AuthorizationReservation::factory()->committed()->create();

        $result = $this->service->commit($reservation->reservation_token);

        $this->assertFalse($result['success']);
        $this->assertEquals('RESERVATION_NOT_FOUND', $result['error']);
    }

    /** @test */
    public function cancel_releases_reservation()
    {
        $license = License::factory()->create([
            'status' => LicenseStatus::Pending->value,
            'max_devices' => 5,
        ]);

        $reserveResult = $this->service->reserve($license, [
            'fingerprint' => 'fp-cancel-test',
            'ip_address' => '192.168.1.1',
        ]);
        $token = $reserveResult['reservation']->reservation_token;

        $cancelResult = $this->service->cancel($token);

        $this->assertTrue($cancelResult['success']);
        $this->assertEquals('cancelled', $reserveResult['reservation']->fresh()->status);
        $this->assertNotNull($reserveResult['reservation']->fresh()->cancelled_at);
    }

    /** @test */
    public function get_status_returns_correct_info()
    {
        $license = License::factory()->create([
            'status' => LicenseStatus::Pending->value,
            'max_devices' => 5,
        ]);

        $reserveResult = $this->service->reserve($license, [
            'fingerprint' => 'fp-status-test',
            'ip_address' => '192.168.1.1',
        ]);
        $token = $reserveResult['reservation']->reservation_token;

        $statusResult = $this->service->getStatus($token);

        $this->assertTrue($statusResult['success']);
        $this->assertTrue($statusResult['is_valid']);
        $this->assertFalse($statusResult['is_expired']);
        $this->assertGreaterThan(0, $statusResult['seconds_remaining']);
        $this->assertEquals('reserved', $statusResult['reservation']->status);
    }

    /** @test */
    public function get_status_returns_not_found_for_nonexistent_token()
    {
        $result = $this->service->getStatus('non-existent-token');

        $this->assertFalse($result['success']);
        $this->assertEquals('RESERVATION_NOT_FOUND', $result['error']);
    }

    /** @test */
    public function cleanup_expired_marks_all_expired_reservations()
    {
        // 创建多个过期预留
        AuthorizationReservation::factory()->count(3)->create([
            'status' => 'reserved',
            'expires_at' => now()->subMinutes(10),
        ]);

        // 创建一个未过期的
        AuthorizationReservation::factory()->reserved()->create();

        $count = $this->service->cleanupExpired();

        $this->assertEquals(3, $count);
        $this->assertEquals(1, AuthorizationReservation::where('status', 'reserved')->count());
        $this->assertEquals(3, AuthorizationReservation::where('status', 'expired')->count());
    }

    /** @test */
    public function get_reservation_stats_returns_correct_counts()
    {
        $license = License::factory()->create([
            'status' => LicenseStatus::Pending->value,
            'max_devices' => 10,
        ]);

        AuthorizationReservation::factory()->reserved()->create(['license_id' => $license->id, 'tenant_id' => $license->tenant_id]);
        AuthorizationReservation::factory()->committed()->create(['license_id' => $license->id, 'tenant_id' => $license->tenant_id]);
        AuthorizationReservation::factory()->expired()->create(['license_id' => $license->id, 'tenant_id' => $license->tenant_id]);

        $stats = $this->service->getReservationStats($license);

        $this->assertEquals(3, $stats['total']);
        $this->assertEquals(1, $stats['active_reservations']);
        $this->assertEquals(1, $stats['committed']);
        $this->assertEquals(1, $stats['expired_cancelled']);
    }

    /** @test */
    public function get_active_reservations_returns_only_valid_reservations_by_tenant()
    {
        $tenant = \App\Models\Tenant::factory()->create(['id' => 999]);
        $product = \App\Models\Product::factory()->create();
        $license = License::factory()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'status' => LicenseStatus::Active->value,
        ]);
        $license2 = License::factory()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'status' => LicenseStatus::Active->value,
        ]);

        // 创建 active 预留
        AuthorizationReservation::factory()->reserved()->create([
            'license_id' => $license->id,
            'tenant_id' => $tenant->id,
        ]);
        AuthorizationReservation::factory()->reserved()->create([
            'license_id' => $license2->id,
            'tenant_id' => $tenant->id,
        ]);
        // 创建 committed（不应该被返回）
        AuthorizationReservation::factory()->committed()->create([
            'license_id' => $license->id,
            'tenant_id' => $tenant->id,
        ]);

        $reservations = $this->service->getActiveReservations($tenant->id);

        $this->assertEquals(2, $reservations->total());
    }

    /** @test */
    public function get_reservation_history_filters_by_status()
    {
        $tenant = \App\Models\Tenant::factory()->create(['id' => 998]);
        $product = \App\Models\Product::factory()->create();
        $license = License::factory()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'status' => LicenseStatus::Active->value,
        ]);

        AuthorizationReservation::factory()->reserved()->create([
            'license_id' => $license->id,
            'tenant_id' => $tenant->id,
        ]);
        AuthorizationReservation::factory()->committed()->create([
            'license_id' => $license->id,
            'tenant_id' => $tenant->id,
        ]);
        AuthorizationReservation::factory()->expired()->create([
            'license_id' => $license->id,
            'tenant_id' => $tenant->id,
        ]);

        $committed = $this->service->getReservationHistory($tenant->id, ['status' => 'committed']);
        $this->assertEquals(1, $committed->total());
    }

    /** @test */
    public function reserve_fails_for_expired_license()
    {
        $license = License::factory()->create([
            'status' => LicenseStatus::Pending->value,
            'max_devices' => 5,
            'expires_at' => now()->subDay(),
        ]);

        $result = $this->service->reserve($license, [
            'fingerprint' => 'fp-expired-license',
            'ip_address' => '192.168.1.1',
        ]);

        $this->assertFalse($result['success']);
        $this->assertEquals('LICENSE_EXPIRED', $result['error']);
    }

    /** @test */
    public function exists_reservation_is_power_idempotent()
    {
        $license = License::factory()->create([
            'status' => LicenseStatus::Pending->value,
            'max_devices' => 5,
        ]);

        // 预留同一个 fingerprint 两次
        $first = $this->service->reserve($license, ['fingerprint' => 'fp-idempotent', 'ip_address' => '1.1.1.1']);
        $second = $this->service->reserve($license, ['fingerprint' => 'fp-idempotent', 'ip_address' => '1.1.1.1']);

        $this->assertTrue($first['success']);
        $this->assertTrue($second['success']);
        $this->assertEquals($first['reservation']->reservation_token, $second['reservation']->reservation_token);
        $this->assertEquals(1, AuthorizationReservation::where('fingerprint', 'fp-idempotent')->count());
    }
}
