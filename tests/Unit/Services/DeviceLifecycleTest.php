<?php

namespace Tests\Unit\Services;

use App\Models\Device;
use App\Models\DeviceLifecycleEvent;
use App\Models\License;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DeviceLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected DeviceLifecycleService $service;
    protected Tenant $tenant;
    protected Device $device;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $license = License::factory()->create();

        $this->device = Device::create([
            'tenant_id' => $this->tenant->id,
            'license_id' => $license->id,
            'fingerprint' => 'test-fp-' . uniqid(),
            'platform' => 'windows',
            'os_version' => '10.0',
            'trust_score' => 0,
            'lifecycle_stage' => 'new',
            'first_seen_at' => now(),
        ]);

        $this->service = app(DeviceLifecycleService::class);
    }

    /** @test */
    public function records_lifecycle_event()
    {
        $event = $this->service->recordEvent(
            $this->device,
            '首次出现',
            'onboarding',
            '设备首次激活',
            ['ip' => '192.168.1.1'],
        );

        $this->assertNotNull($event->id);
        $this->assertEquals($this->device->id, $event->device_id);
        $this->assertEquals('onboarding', $event->stage);
        $this->assertEquals('onboarding', $this->device->fresh()->lifecycle_stage);
        $this->assertEquals(1, $this->device->fresh()->total_events);
    }

    /** @test */
    public function adjusts_trust_score()
    {
        $event = $this->service->adjustTrustScore($this->device, 50, '首次激活信任分');

        $this->assertEquals(50, $event->trust_score_after);
        $this->assertEquals(0, $event->trust_score_before);

        // Trust score should update on device
        $this->assertEquals(50, $this->device->fresh()->trust_score);
    }

    /** @test */
    public function determines_stage_from_score()
    {
        $this->device->update(['trust_score' => 0, 'license_id' => null, 'is_blacklisted' => false]);
        $this->assertEquals('new', $this->service->determineStageFromScore($this->device->fresh()));

        $this->device->update(['trust_score' => 0, 'license_id' => 1, 'is_blacklisted' => false]);
        $this->assertEquals('onboarding', $this->service->determineStageFromScore($this->device->fresh()));

        $this->device->update(['trust_score' => 30, 'is_blacklisted' => false]);
        $this->assertEquals('suspicious', $this->service->determineStageFromScore($this->device->fresh()));

        $this->device->update(['trust_score' => 65]);
        $this->assertEquals('onboarding', $this->service->determineStageFromScore($this->device->fresh()));

        $this->device->update(['trust_score' => 90]);
        $this->assertEquals('stable', $this->service->determineStageFromScore($this->device->fresh()));

        $this->device->update(['trust_score' => 0, 'is_blacklisted' => true, 'license_id' => null]);
        $this->assertEquals('retired', $this->service->determineStageFromScore($this->device->fresh()));
    }

    /** @test */
    public function trust_adjustment_triggers_stage_change()
    {
        // Start at new, trust_score=0
        $this->service->adjustTrustScore($this->device, 80, '快速建立信任');
        $this->assertEquals('stable', $this->device->fresh()->lifecycle_stage);
        $this->assertEquals(80, $this->device->fresh()->trust_score);
    }

    /** @test */
    public function detects_anomaly()
    {
        $this->device->update(['trust_score' => 80]);
        $event = $this->service->detectAnomaly($this->device, 'IP频繁变化');

        $this->assertNotNull($event);
        $this->assertEquals(60, $event->trust_score_after);
        $this->assertEquals(-20, $event->trust_score_change);
        $this->assertEquals('auto_detect', $event->triggered_by);
    }

    /** @test */
    public function marks_suspicious()
    {
        $this->device->update(['trust_score' => 80]);
        $event = $this->service->markSuspicious($this->device, '管理员标记可疑', $this->user->id);

        $this->assertEquals('suspicious', $event->stage);
        $this->assertLessThanOrEqual(30, $this->device->fresh()->trust_score);
    }

    /** @test */
    public function retires_device()
    {
        $event = $this->service->retireDevice($this->device, '设备废弃', $this->user->id);

        $this->assertEquals('retired', $event->stage);
        $device = $this->device->fresh();
        $this->assertEquals(0, $device->trust_score);
        $this->assertTrue($device->is_blacklisted);
        $this->assertNull($device->license_id);
    }

    /** @test */
    public function builds_timeline()
    {
        // Simulate a full lifecycle
        $this->device->update(['trust_score' => 0, 'lifecycle_stage' => 'new']);

        $this->service->adjustTrustScore($this->device->fresh(), 30, '初始激活');
        $this->service->adjustTrustScore($this->device->fresh(), 50, '持续使用');
        $this->service->detectAnomaly($this->device->fresh(), '异常检测');
        $this->service->markSuspicious($this->device->fresh(), '标记可疑', $this->user->id);

        $timeline = $this->service->buildTimeline($this->device->fresh());

        $this->assertNotEmpty($timeline);
        $this->assertContains('new', array_column($timeline, 'stage'));
        $this->assertContains('onboarding', array_column($timeline, 'stage'));
        $this->assertContains('suspicious', array_column($timeline, 'stage'));
    }

    /** @test */
    public function gets_profile_stats()
    {
        // Create some devices in different stages
        Device::create([
            'tenant_id' => $this->tenant->id,
            'fingerprint' => 'fp-stable-' . uniqid(),
            'trust_score' => 95,
            'lifecycle_stage' => 'stable',
            'first_seen_at' => now()->subDays(30),
        ]);
        Device::create([
            'tenant_id' => $this->tenant->id,
            'fingerprint' => 'fp-retired-' . uniqid(),
            'trust_score' => 0,
            'lifecycle_stage' => 'retired',
            'first_seen_at' => now(),
        ]);

        $stats = $this->service->getProfileStats($this->tenant->id);

        $this->assertArrayHasKey('stage_distribution', $stats);
        $this->assertArrayHasKey('trust_distribution', $stats);
        $this->assertArrayHasKey('avg_trust_score', $stats);
    }

    /** @test */
    public function gets_full_device_profile()
    {
        // Add some events first
        $this->service->adjustTrustScore($this->device, 60, '初始激活');
        $this->service->detectAnomaly($this->device->fresh(), 'IP变化');
        $this->service->detectAnomaly($this->device->fresh(), '异地登录');

        $profile = $this->service->getProfile($this->device->fresh());

        $this->assertArrayHasKey('profile', $profile);
        $this->assertArrayHasKey('recent_events', $profile);
        $this->assertArrayHasKey('timeline', $profile);
        $this->assertCount(3, $profile['recent_events']);
    }
}
