<?php

namespace Tests\Unit\Services;

use App\Models\SlaBreach;
use App\Models\SlaContract;
use App\Models\SlaMetric;
use App\Models\SlaProbe;
use App\Models\SlaProbeResult;
use App\Models\SlaProbeUptime;
use App\Models\Tenant;
use App\Services\SlaProbeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SlaProbeTest extends TestCase
{
    use RefreshDatabase;

    protected SlaProbeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SlaProbeService::class);
    }

    /** @test */
    public function creates_probe()
    {
        $tenant = Tenant::factory()->create();

        $probe = $this->service->createProbe([
            'tenant_id' => $tenant->id,
            'name' => 'API健康检查',
            'url' => 'https://example.com/health',
            'method' => 'GET',
            'timeout_seconds' => 10,
            'interval_minutes' => 5,
            'sla_targets' => ['max_response_time' => 500],
        ]);

        $this->assertInstanceOf(SlaProbe::class, $probe);
        $this->assertEquals('API健康检查', $probe->name);
    }

    /** @test */
    public function updates_probe()
    {
        $probe = SlaProbe::factory()->create();

        $updated = $this->service->updateProbe($probe, [
            'name' => '更新名称',
            'interval_minutes' => 10,
        ]);

        $this->assertEquals('更新名称', $updated->name);
        $this->assertEquals(10, $updated->interval_minutes);
    }

    /** @test */
    public function toggles_probe_active_state()
    {
        $probe = SlaProbe::factory()->create(['is_active' => true]);

        $this->service->toggleProbe($probe);
        $this->assertFalse($probe->fresh()->is_active);

        $this->service->toggleProbe($probe->fresh());
        $this->assertTrue($probe->fresh()->is_active);
    }

    /** @test */
    public function lists_probes_with_filters()
    {
        $tenant = Tenant::factory()->create();
        SlaProbe::factory()->healthy()->count(3)->create(['tenant_id' => $tenant->id]);
        SlaProbe::factory()->unhealthy()->count(2)->create(['tenant_id' => $tenant->id]);

        $all = $this->service->listProbes($tenant->id);
        $this->assertEquals(5, $all->total());

        $unhealthy = $this->service->listProbes($tenant->id, ['status' => 'unhealthy']);
        $this->assertEquals(2, $unhealthy->total());
    }

    /** @test */
    public function gets_probe_with_recent_results()
    {
        $probe = SlaProbe::factory()->create();

        $this->service->getProbe($probe->id);

        $this->assertDatabaseHas('sla_probes', ['id' => $probe->id]);
    }

    /** @test */
    public function deletes_probe_and_related_records()
    {
        $probe = SlaProbe::factory()->create();
        SlaProbeResult::create([
            'sla_probe_id' => $probe->id,
            'tenant_id' => $probe->tenant_id,
            'status' => 'up',
            'response_time_ms' => 100,
            'probed_at' => now(),
        ]);

        $this->service->deleteProbe($probe);

        $this->assertSoftDeleted($probe);
        $this->assertDatabaseMissing('sla_probe_results', ['sla_probe_id' => $probe->id]);
    }

    /** @test */
    public function is_expected_status_works_correctly()
    {
        $probe = SlaProbe::factory()->create(['expected_status' => '200-299']);

        $this->assertTrue($probe->isExpectedStatus(200));
        $this->assertTrue($probe->isExpectedStatus(201));
        $this->assertTrue($probe->isExpectedStatus(299));
        $this->assertFalse($probe->isExpectedStatus(300));
        $this->assertFalse($probe->isExpectedStatus(400));
        $this->assertFalse($probe->isExpectedStatus(500));
    }

    /** @test */
    public function should_probe_returns_true_when_not_probed_before()
    {
        $probe = SlaProbe::factory()->create([
            'is_active' => true,
            'last_probed_at' => null,
        ]);

        $this->assertTrue($probe->shouldProbe());
    }

    /** @test */
    public function should_probe_returns_false_when_inactive()
    {
        $probe = SlaProbe::factory()->inactive()->create();

        $this->assertFalse($probe->shouldProbe());
    }

    /** @test */
    public function should_probe_returns_false_when_recently_probed()
    {
        $probe = SlaProbe::factory()->create([
            'is_active' => true,
            'interval_minutes' => 60,
            'last_probed_at' => now()->subMinutes(30),
        ]);

        $this->assertFalse($probe->shouldProbe());
    }

    /** @test */
    public function should_probe_returns_true_when_interval_passed()
    {
        $probe = SlaProbe::factory()->create([
            'is_active' => true,
            'interval_minutes' => 5,
            'last_probed_at' => now()->subMinutes(10),
        ]);

        $this->assertTrue($probe->shouldProbe());
    }

    /** @test */
    public function get_dashboard_returns_correct_stats()
    {
        $tenant = Tenant::factory()->create();
        SlaProbe::factory()->healthy()->count(3)->create(['tenant_id' => $tenant->id]);
        SlaProbe::factory()->unhealthy()->count(1)->create(['tenant_id' => $tenant->id]);
        SlaProbe::factory()->inactive()->count(2)->create(['tenant_id' => $tenant->id]);

        $dashboard = $this->service->getDashboard($tenant->id);

        $this->assertEquals(6, $dashboard['total_probes']);
        $this->assertEquals(4, $dashboard['active_probes']);
        $this->assertEquals(3, $dashboard['healthy_probes']);
        $this->assertEquals(1, $dashboard['unhealthy_probes']);
    }

    /** @test */
    public function get_uptime_stats_returns_aggregated_data()
    {
        $probe = SlaProbe::factory()->create();

        // 创建几天的 uptime 记录
        for ($i = 0; $i < 5; $i++) {
            SlaProbeUptime::create([
                'sla_probe_id' => $probe->id,
                'tenant_id' => $probe->tenant_id,
                'record_date' => now()->subDays($i)->format('Y-m-d'),
                'period' => 'daily',
                'total_checks' => 10,
                'success_checks' => 9,
                'failure_checks' => 1,
                'uptime_percentage' => 90.0,
                'avg_response_time_ms' => 200,
                'max_response_time_ms' => 500,
                'min_response_time_ms' => 50,
            ]);
        }

        $stats = $this->service->getUptimeStats($probe->id);

        $this->assertEquals(5, $stats['daily']->count());
        $this->assertEquals(50, $stats['aggregate']['total_checks']);
        $this->assertEquals(90.0, $stats['aggregate']['avg_uptime']);
        $this->assertEquals(200, $stats['aggregate']['avg_response_time']);
    }

    /** @test */
    public function creates_sla_breach_when_consecutive_failures_exceed_threshold()
    {
        $tenant = Tenant::factory()->create();

        // 先创建一个 SlaContract 让 triggerBreach 能找到
        SlaContract::create([
            'tenant_id' => $tenant->id,
            'name' => '默认SLA',
            'slug' => 'default-sla',
            'level' => 'standard',
            'is_active' => true,
            'effective_date' => now()->format('Y-m-d'),
        ]);

        $probe = SlaProbe::factory()->create([
            'tenant_id' => $tenant->id,
            'consecutive_failures' => 2, // 已有2次连续失败
        ]);

        // 模拟一次失败拨测
        SlaProbeResult::create([
            'sla_probe_id' => $probe->id,
            'tenant_id' => $tenant->id,
            'status' => 'down',
            'error_message' => 'Connection timeout',
            'probed_at' => now(),
        ]);

        $probe->update([
            'last_status' => 'down',
            'consecutive_failures' => 3,
        ]);

        // 手动触发 breach
        $ref = new \ReflectionClass($this->service);
        $method = $ref->getMethod('triggerBreach');
        $method->setAccessible(true);

        $lastResult = SlaProbeResult::where('sla_probe_id', $probe->id)->latest()->first();
        $method->invoke($this->service, $probe, $lastResult);

        $this->assertDatabaseHas('sla_breaches', [
            'breach_type' => 'probe_failure',
            'breachable_type' => SlaProbe::class,
            'breachable_id' => $probe->id,
            'severity' => 'minor',
        ]);
    }

    /** @test */
    public function get_results_filters_by_status()
    {
        $probe = SlaProbe::factory()->create();

        SlaProbeResult::create(['sla_probe_id' => $probe->id, 'tenant_id' => $probe->tenant_id, 'status' => 'up', 'probed_at' => now()]);
        SlaProbeResult::create(['sla_probe_id' => $probe->id, 'tenant_id' => $probe->tenant_id, 'status' => 'down', 'probed_at' => now()]);
        SlaProbeResult::create(['sla_probe_id' => $probe->id, 'tenant_id' => $probe->tenant_id, 'status' => 'up', 'probed_at' => now()]);

        $up = $this->service->getResults($probe->id, ['status' => 'up']);
        $this->assertEquals(2, $up->total());

        $down = $this->service->getResults($probe->id, ['status' => 'down']);
        $this->assertEquals(1, $down->total());
    }
}
