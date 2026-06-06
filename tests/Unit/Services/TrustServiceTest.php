<?php

namespace Tests\Unit\Services;

use App\Models\Device;
use App\Models\License;
use App\Services\TrustService;
use Database\Factories\LicenseFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrustServiceTest extends TestCase
{
    use RefreshDatabase;

    private TrustService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TrustService::class);
    }

    private function makeDevice(array $overrides = []): Device
    {
        return Device::factory()->create(array_merge([
            'trust_score' => TrustService::DEFAULT_TRUST_SCORE,
        ], $overrides));
    }

    // ─── 信任分判断 ───

    public function test_new_device_not_fully_trusted(): void
    {
        $device = $this->makeDevice();
        $this->assertFalse($this->service->isFullyTrusted($device));
    }

    public function test_high_score_device_is_trusted(): void
    {
        $device = $this->makeDevice(['trust_score' => 85]);
        $this->assertTrue($this->service->isTrusted($device));
        $this->assertTrue($this->service->isFullyTrusted($device));
    }

    public function test_blacklisted_device_not_trusted(): void
    {
        $device = $this->makeDevice([
            'trust_score' => 100,
            'is_blacklisted' => true,
        ]);
        $this->assertFalse($this->service->isTrusted($device));
        $this->assertFalse($this->service->isFullyTrusted($device));
    }

    public function test_device_above_auto_threshold_is_trusted(): void
    {
        $device = $this->makeDevice(['trust_score' => TrustService::AUTO_TRUST_THRESHOLD]);
        $this->assertTrue($this->service->isTrusted($device));
    }

    // ─── MFA 判断 ───

    public function test_new_device_requires_mfa(): void
    {
        $device = $this->makeDevice();
        $this->assertTrue($this->service->requiresMfa($device));
    }

    public function test_trusted_device_skips_mfa(): void
    {
        $device = $this->makeDevice(['trust_score' => 75]);
        $this->assertFalse($this->service->requiresMfa($device));
    }

    public function test_blacklisted_device_does_not_require_mfa(): void
    {
        $device = $this->makeDevice([
            'trust_score' => 30,
            'is_blacklisted' => true,
        ]);
        $this->assertFalse($this->service->requiresMfa($device)); // 直接拒绝
    }

    // ─── 信任分更新 ───

    public function test_update_trust_score_increases(): void
    {
        $device = $this->makeDevice(['trust_score' => 50]);
        $newScore = $this->service->updateTrustScore($device, 20, '测试加分');
        $this->assertEquals(70, $newScore);
        $device->refresh();
        $this->assertEquals(70, $device->trust_score);
    }

    public function test_update_trust_score_decreases(): void
    {
        $device = $this->makeDevice(['trust_score' => 70]);
        $newScore = $this->service->updateTrustScore($device, -30, '测试减分');
        $this->assertEquals(40, $newScore);
        $device->refresh();
        $this->assertEquals(40, $device->trust_score);
    }

    public function test_trust_score_caps_at_100(): void
    {
        $device = $this->makeDevice(['trust_score' => 95]);
        $newScore = $this->service->updateTrustScore($device, 20);
        $this->assertEquals(100, $newScore);
    }

    public function test_trust_score_floor_at_0(): void
    {
        $device = $this->makeDevice(['trust_score' => 10]);
        $newScore = $this->service->updateTrustScore($device, -30);
        $this->assertEquals(0, $newScore);
    }

    // ─── 事件驱动 ───

    public function test_successful_activation_increases_score(): void
    {
        $device = $this->makeDevice(['trust_score' => 50]);
        $this->service->recordSuccessfulActivation($device);
        $device->refresh();
        $this->assertEquals(60, $device->trust_score);
    }

    public function test_failed_activation_decreases_score(): void
    {
        $device = $this->makeDevice(['trust_score' => 50]);
        $this->service->recordFailedActivation($device);
        $device->refresh();
        $this->assertEquals(35, $device->trust_score);
    }

    // ─── 白名单/黑名单 ───

    public function test_whitelist_sets_score_to_100(): void
    {
        $device = $this->makeDevice(['trust_score' => 30]);
        $this->service->whitelist($device);
        $device->refresh();
        $this->assertEquals(100, $device->trust_score);
        $this->assertFalse($device->is_blacklisted);
    }

    public function test_blacklist_sets_score_to_0(): void
    {
        $device = $this->makeDevice(['trust_score' => 80]);
        $this->service->blacklist($device, '违规操作');
        $device->refresh();
        $this->assertEquals(0, $device->trust_score);
        $this->assertTrue($device->is_blacklisted);
    }

    // ─── 信任等级 ───

    public function test_trust_level_blacklisted(): void
    {
        $device = $this->makeDevice(['trust_score' => 0, 'is_blacklisted' => true]);
        $this->assertEquals('blacklisted', $this->service->getTrustLevel($device));
    }

    public function test_trust_level_whitelist(): void
    {
        $device = $this->makeDevice(['trust_score' => 100]);
        $this->assertEquals('whitelist', $this->service->getTrustLevel($device));
    }

    public function test_trust_level_high(): void
    {
        $device = $this->makeDevice(['trust_score' => 90]);
        $this->assertEquals('high', $this->service->getTrustLevel($device));
    }

    public function test_trust_level_low(): void
    {
        $device = $this->makeDevice(['trust_score' => 20]);
        $this->assertEquals('low', $this->service->getTrustLevel($device));
    }

    // ─── MFA 验证码 ───

    public function test_generate_and_verify_mfa_code(): void
    {
        $device = $this->makeDevice();

        $code = $this->service->generateMfaCode($device);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        $this->assertTrue($this->service->verifyMfaCode($device, $code));
        $device->refresh();
        $this->assertGreaterThan(TrustService::DEFAULT_TRUST_SCORE, $device->trust_score);
    }

    public function test_verify_wrong_mfa_code(): void
    {
        $device = $this->makeDevice();
        $this->service->generateMfaCode($device);

        $this->assertFalse($this->service->verifyMfaCode($device, '000000'));
    }

    public function test_mfa_code_expires(): void
    {
        $device = $this->makeDevice();
        $this->service->generateMfaCode($device);

        // 删除验证码模拟过期
        \Illuminate\Support\Facades\Cache::forget("mfa_code:{$device->id}");

        $this->assertFalse($this->service->verifyMfaCode($device, '000000'));
    }

    // ─── 物理设备 vs 虚拟环境 ───

    public function test_vm_detected_reduces_score(): void
    {
        $device = $this->makeDevice(['trust_score' => 70]);
        $this->service->recordVmDetected($device);
        $device->refresh();
        $this->assertEquals(40, $device->trust_score);
    }

    public function test_geo_anomaly_reduces_score(): void
    {
        $device = $this->makeDevice(['trust_score' => 70]);
        $this->service->recordGeoAnomaly($device);
        $device->refresh();
        $this->assertEquals(45, $device->trust_score);
    }

    // ─── 获取信任设备 ───

    public function test_get_trusted_devices(): void
    {
        $license = License::factory()->create();
        $trustedDevice = Device::factory()->create([
            'license_id' => $license->id,
            'trust_score' => 80,
            'is_blacklisted' => false,
        ]);
        $untrustedDevice = Device::factory()->create([
            'license_id' => $license->id,
            'trust_score' => 30,
            'is_blacklisted' => false,
        ]);
        $blacklistedDevice = Device::factory()->create([
            'license_id' => $license->id,
            'trust_score' => 90,
            'is_blacklisted' => true,
        ]);

        $trusted = $this->service->getTrustedDevices($license);
        $this->assertTrue($trusted->contains($trustedDevice));
        $this->assertFalse($trusted->contains($untrustedDevice));
        $this->assertFalse($trusted->contains($blacklistedDevice));
    }

    // ─── 创建设备 ───

    public function test_create_device_sets_default_score(): void
    {
        $license = License::factory()->create();
        $device = $this->service->createDevice([
            'tenant_id' => $license->tenant_id,
            'license_id' => $license->id,
            'fingerprint' => 'fp-test-create',
            'platform' => 'test',
        ]);

        $this->assertEquals(TrustService::DEFAULT_TRUST_SCORE, $device->trust_score);
    }
}
