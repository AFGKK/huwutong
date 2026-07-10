<?php

namespace Tests\Unit\Services;

use App\Models\Customer;
use App\Models\License;
use App\Models\LicenseWatermark;
use App\Models\TamperEvent;
use App\Models\TamperProtectionConfig;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WatermarkTraceAudit;
use App\Services\WatermarkTamperService;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class WatermarkTamperServiceTest extends TestCase
{
    use RefreshDatabase;

    protected WatermarkTamperService $service;
    protected License $license;

    protected function setUp(): void
    {
        parent::setUp();

        // Tenant must exist for foreign key constraints
        if (!Tenant::find(1)) {
            Tenant::factory()->create(['id' => 1]);
        }

        $this->service = app(WatermarkTamperService::class);

        $customer = Customer::create([
            'tenant_id' => 1,
            'name' => 'Test Customer',
            'email' => 'test@example.com',
        ]);

        $this->license = License::create([
            'license_key' => 'HWT-TEST-' . strtoupper(substr(md5(uniqid()), 0, 16)),
            'customer_id' => $customer->id,
            'tenant_id' => 1,
            'type' => 'enterprise',
            'status' => 'active',
            'seats' => 10,
            'max_devices' => 5,
            'expires_at' => now()->addYear(),
        ]);
    }

    /** @test */
    public function can_embed_watermark()
    {
        $watermark = $this->service->embedWatermark($this->license, ['source' => 'test']);

        $this->assertNotNull($watermark);
        $this->assertEquals('active', $watermark->status);
        $this->assertEquals('stealth', $watermark->algorithm);
        $this->assertArrayHasKey('source', $watermark->watermark_data);
    }

    /** @test */
    public function can_embed_forensic_watermark()
    {
        $watermark = $this->service->embedForensicWatermark($this->license, [
            'fingerprint' => 'dev_fp_abc123',
            'embed_location' => 'integrity_hash',
        ]);

        $this->assertNotNull($watermark);
        $this->assertEquals('forensic_stealth', $watermark->algorithm);
        $this->assertEquals('integrity_hash', $watermark->embed_type);
        $this->assertNotNull($watermark->forensic_data);
        $this->assertArrayHasKey('signature', $watermark->forensic_data);

        // Verify license was updated
        $this->license->refresh();
        $this->assertNotNull($this->license->watermark_key);
        $this->assertNotNull($this->license->integrity_hash);
    }

    /** @test */
    public function can_extract_watermark()
    {
        $this->service->embedWatermark($this->license);

        $result = $this->service->extractAndVerify($this->license);

        $this->assertTrue($result['found']);
        $this->assertTrue($result['valid']);
        $this->assertArrayHasKey('watermark_id', $result);
    }

    /** @test */
    public function can_trace_by_watermark()
    {
        $watermark = $this->service->embedWatermark($this->license, ['extra' => 'data']);

        $trace = $this->service->traceByWatermark($watermark->watermark_key);

        $this->assertNotNull($trace);
        $this->assertArrayHasKey('watermark', $trace);
        $this->assertArrayHasKey('license', $trace);

        // Nonexistent key returns null
        $this->assertNull($this->service->traceByWatermark('nonexistent_key'));
    }

    /** @test */
    public function can_revoke_watermark()
    {
        $watermark = $this->service->embedWatermark($this->license);

        $this->service->revokeWatermark($watermark);
        $watermark->refresh();

        $this->assertEquals('revoked', $watermark->status);
    }

    /** @test */
    public function can_list_watermarks()
    {
        $this->service->embedWatermark($this->license);
        $this->service->embedWatermark($this->license, ['source' => 'second']);

        $paginated = $this->service->listWatermarks();
        $this->assertGreaterThanOrEqual(2, $paginated->total());
    }

    /** @test */
    public function can_create_trace_audit()
    {
        $watermark = $this->service->embedWatermark($this->license);

        $trace = $this->service->createTrace([
            'watermark_id' => $watermark->id,
            'license_id' => $this->license->id,
            'trace_type' => 'manual',
            'source' => 'github_leak',
            'leak_url' => 'https://github.com/example/leak',
            'confidence' => 'high',
            'notes' => 'Discovered in public repo',
            'operator_id' => null,
        ]);

        $this->assertNotNull($trace);
        $this->assertEquals('manual', $trace->trace_type);
        $this->assertEquals('github_leak', $trace->source);
    }

    /** @test */
    public function can_search_watermarks()
    {
        $this->service->embedWatermark($this->license);

        $results = $this->service->searchWatermarks($this->license->license_key);
        $this->assertNotEmpty($results);
    }

    /** @test */
    public function can_get_dashboard_data()
    {
        $this->service->embedWatermark($this->license);
        $this->service->embedForensicWatermark($this->license);

        $dashboard = $this->service->getDashboardData();

        $this->assertArrayHasKey('active_watermarks', $dashboard);
        $this->assertArrayHasKey('total_watermarks', $dashboard);
        $this->assertArrayHasKey('total_events', $dashboard);
        $this->assertArrayHasKey('verification_stats', $dashboard);
        $this->assertGreaterThanOrEqual(2, $dashboard['total_watermarks']);
    }

    /** @test */
    public function can_get_dashboard_with_default_policies()
    {
        // Calling getDashboardData should seed default policies
        $dashboard = $this->service->getDashboardData();

        $this->assertNotEmpty($dashboard['policies']);
        $this->assertCount(4, $dashboard['policies']);
    }

    /** @test */
    public function can_list_trace_audits()
    {
        $watermark = $this->service->embedWatermark($this->license);
        $this->service->createTrace([
            'watermark_id' => $watermark->id,
            'license_id' => $this->license->id,
            'trace_type' => 'auto',
            'confidence' => 'medium',
            'notes' => 'Auto detected leak',
            'operator_id' => null,
        ]);

        $paginated = $this->service->listTraces();
        $this->assertGreaterThanOrEqual(1, $paginated->total());
    }

    /** @test */
    public function can_record_tamper_event()
    {
        $event = $this->service->recordTamperEvent([
            'license_id' => $this->license->id,
            'license_key' => $this->license->license_key,
            'event_type' => 'signature',
            'severity' => 'high',
            'event_data' => ['reason' => 'hash_mismatch'],
        ]);

        $this->assertNotNull($event);
        $this->assertEquals('signature', $event->event_type);
        $this->assertEquals('high', $event->severity);
    }

    /** @test */
    public function can_resolve_tamper_event()
    {
        $event = $this->service->recordTamperEvent([
            'license_id' => $this->license->id,
            'license_key' => $this->license->license_key,
            'event_type' => 'device',
            'severity' => 'medium',
            'event_data' => ['reason' => 'burst_activation'],
        ]);

        // Need a user for auth()->id() in resolveTamperEvent
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->service->resolveTamperEvent($event, 'False positive - legitimate bulk activation');

        $event->refresh();
        $this->assertTrue($event->is_resolved);
        $this->assertNotNull($event->resolved_at);
    }
}
