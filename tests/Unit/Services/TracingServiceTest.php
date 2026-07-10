<?php

namespace Tests\Unit\Services;

use App\Models\ApmRequest;
use App\Models\Tenant;
use App\Services\TracingService;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class TracingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TracingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TracingService::class);
    }

    /** @test */
    public function get_traces_returns_paginated_results()
    {
        $tenant = Tenant::factory()->create();
        ApmRequest::factory()->count(5)->create(['tenant_id' => $tenant->id]);

        $result = $this->service->getTraces($tenant->id);

        $this->assertEquals(5, $result->total());
    }

    /** @test */
    public function get_traces_filters_by_method()
    {
        $tenant = Tenant::factory()->create();
        ApmRequest::factory()->create(['tenant_id' => $tenant->id, 'method' => 'GET']);
        ApmRequest::factory()->create(['tenant_id' => $tenant->id, 'method' => 'POST']);
        ApmRequest::factory()->create(['tenant_id' => $tenant->id, 'method' => 'GET']);

        $result = $this->service->getTraces($tenant->id, ['method' => 'GET']);
        $this->assertEquals(2, $result->total());
    }

    /** @test */
    public function get_traces_filters_by_slow()
    {
        $tenant = Tenant::factory()->create();
        ApmRequest::factory()->create(['tenant_id' => $tenant->id, 'is_slow' => false, 'duration_ms' => 50]);
        ApmRequest::factory()->slow()->create(['tenant_id' => $tenant->id]);

        $result = $this->service->getTraces($tenant->id, ['is_slow' => 'true']);
        $this->assertEquals(1, $result->total());
    }

    /** @test */
    public function get_traces_filters_by_status_range()
    {
        $tenant = Tenant::factory()->create();
        ApmRequest::factory()->create(['tenant_id' => $tenant->id, 'status_code' => 200]);
        ApmRequest::factory()->create(['tenant_id' => $tenant->id, 'status_code' => 404]);
        ApmRequest::factory()->create(['tenant_id' => $tenant->id, 'status_code' => 500]);

        $result = $this->service->getTraces($tenant->id, ['status_range' => '400-500']);
        $this->assertEquals(2, $result->total());
    }

    /** @test */
    public function get_trace_stats_returns_aggregated_data()
    {
        $tenant = Tenant::factory()->create();
        ApmRequest::factory()->count(80)->create([
            'tenant_id' => $tenant->id,
            'duration_ms' => 50,
            'status_code' => 200,
            'is_slow' => false,
        ]);
        ApmRequest::factory()->count(10)->create([
            'tenant_id' => $tenant->id,
            'duration_ms' => 2000,
            'status_code' => 200,
            'is_slow' => true,
        ]);
        ApmRequest::factory()->count(10)->create([
            'tenant_id' => $tenant->id,
            'duration_ms' => 100,
            'status_code' => 500,
            'is_slow' => false,
        ]);

        $stats = $this->service->getTraceStats($tenant->id);

        $this->assertEquals(100, $stats['total']);
        $this->assertEquals(10, $stats['slow']);
        $this->assertEquals(10, $stats['errors']);
        $this->assertEquals(10.0, $stats['error_rate']);
        $this->assertGreaterThan(0, $stats['avg_duration_ms']);
        $this->assertGreaterThan(0, $stats['p95_duration_ms']);
        $this->assertNotEmpty($stats['top_paths']);
    }

    /** @test */
    public function get_trace_detail_returns_single_record()
    {
        $apm = ApmRequest::factory()->create();

        $detail = $this->service->getTraceDetail($apm->id);

        $this->assertEquals($apm->id, $detail->id);
    }
}
