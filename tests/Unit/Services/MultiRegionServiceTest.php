<?php

namespace Tests\Unit\Services;

use App\Models\DataCenter;
use App\Models\FailoverLog;
use App\Models\FailoverRule;
use App\Models\RegionDeployment;
use App\Models\RegionHealthLog;
use App\Models\RegionSyncLog;
use App\Models\Tenant;
use App\Services\MultiRegionService;
use Tests\Concerns\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class MultiRegionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected MultiRegionService $service;
    protected Tenant $tenant;
    protected DataCenter $dc1;
    protected DataCenter $dc2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MultiRegionService::class);
        $this->tenant = Tenant::factory()->create();
        $this->dc1 = DataCenter::factory()->create([
            'code' => 'ap-northeast-1',
            'name' => 'Tokyo',
            'region' => 'asia',
        ]);
        $this->dc2 = DataCenter::factory()->create([
            'code' => 'ap-southeast-1',
            'name' => 'Singapore',
            'region' => 'asia',
        ]);
    }

    // ═══════ 数据中心管理 ═══════

    /** @test */
    public function it_lists_data_centers()
    {
        $dcs = $this->service->listDataCenters();
        $this->assertCount(2, $dcs);
    }

    /** @test */
    public function it_creates_a_data_center()
    {
        $dc = $this->service->createDataCenter([
            'name' => 'AWS Frankfurt',
            'code' => 'eu-central-1',
            'region' => 'europe',
            'country_code' => 'DE',
            'city' => 'Frankfurt',
        ]);

        $this->assertDatabaseHas('data_centers', ['code' => 'eu-central-1']);
        $this->assertEquals('europe', $dc->region);
    }

    /** @test */
    public function it_updates_a_data_center()
    {
        $updated = $this->service->updateDataCenter($this->dc1->id, ['name' => 'Tokyo Updated']);

        $this->assertEquals('Tokyo Updated', $updated->name);
    }

    /** @test */
    public function it_deletes_a_data_center()
    {
        $this->service->deleteDataCenter($this->dc1->id);

        $this->assertDatabaseMissing('data_centers', ['id' => $this->dc1->id]);
    }

    /** @test */
    public function it_seeds_default_data_centers()
    {
        $created = $this->service->seedDefaultDataCenters();

        $this->assertCount(6, $created);
        $this->assertDatabaseHas('data_centers', ['code' => 'ap-northeast-1']);
    }

    /** @test */
    public function it_does_not_duplicate_on_seed()
    {
        $this->service->seedDefaultDataCenters();
        $this->service->seedDefaultDataCenters();

        $this->assertEquals(6, DataCenter::count());
    }

    // ═══════ 健康检查 ═══════

    /** @test */
    public function it_performs_a_health_check()
    {
        $log = $this->service->performHealthCheck($this->dc1);

        $this->assertDatabaseHas('region_health_logs', ['data_center_id' => $this->dc1->id]);
        $this->assertNotNull($log->latency_ms);
    }

    /** @test */
    public function it_performs_health_checks_on_all_dcs()
    {
        $results = $this->service->healthCheckAll();

        $this->assertCount(2, $results);
    }

    /** @test */
    public function it_gets_health_trend()
    {
        RegionHealthLog::factory()->count(5)->create([
            'data_center_id' => $this->dc1->id,
            'checked_at' => now()->subMinutes(10),
        ]);

        $trend = $this->service->getHealthTrend($this->dc1->id);

        $this->assertCount(5, $trend);
    }

    // ═══════ 故障切换规则管理 ═══════

    /** @test */
    public function it_creates_a_failover_rule()
    {
        $rule = $this->service->createFailoverRule($this->tenant->id, [
            'name' => 'Tokyo to Singapore',
            'primary_dc_id' => $this->dc1->id,
            'backup_dc_id' => $this->dc2->id,
            'trigger_type' => 'latency',
            'trigger_threshold_ms' => 300,
        ]);

        $this->assertDatabaseHas('failover_rules', ['name' => 'Tokyo to Singapore']);
        $this->assertEquals('active', $rule->status);
    }

    /** @test */
    public function it_lists_failover_rules()
    {
        FailoverRule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'primary_dc_id' => $this->dc1->id,
            'backup_dc_id' => $this->dc2->id,
        ]);

        $rules = $this->service->listFailoverRules($this->tenant->id);

        $this->assertCount(1, $rules);
    }

    /** @test */
    public function it_updates_a_failover_rule()
    {
        $rule = FailoverRule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'primary_dc_id' => $this->dc1->id,
            'backup_dc_id' => $this->dc2->id,
        ]);

        $updated = $this->service->updateFailoverRule($rule->id, ['name' => 'Updated Rule']);

        $this->assertEquals('Updated Rule', $updated->name);
    }

    /** @test */
    public function it_deletes_a_failover_rule()
    {
        $rule = FailoverRule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'primary_dc_id' => $this->dc1->id,
            'backup_dc_id' => $this->dc2->id,
        ]);

        $this->service->deleteFailoverRule($rule->id);

        $this->assertDatabaseMissing('failover_rules', ['id' => $rule->id]);
    }

    // ═══════ 故障切换执行 ═══════

    /** @test */
    public function it_executes_a_failover()
    {
        $rule = FailoverRule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'primary_dc_id' => $this->dc1->id,
            'backup_dc_id' => $this->dc2->id,
        ]);

        $log = $this->service->executeFailover($rule, 'Latency spike detected');

        $this->assertDatabaseHas('failover_logs', [
            'failover_rule_id' => $rule->id,
            'action' => 'manual_failover',
        ]);
        $this->assertEquals('failover', $rule->fresh()->status);
        $this->assertEquals('ap-southeast-1', $log->to_dc);
    }

    /** @test */
    public function it_executes_a_restore()
    {
        $rule = FailoverRule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'primary_dc_id' => $this->dc1->id,
            'backup_dc_id' => $this->dc2->id,
            'status' => 'failover',
        ]);

        $log = $this->service->executeRestore($rule, 'Primary DC recovered');

        $this->assertDatabaseHas('failover_logs', [
            'failover_rule_id' => $rule->id,
            'action' => 'restore',
        ]);
        $this->assertEquals('active', $rule->fresh()->status);
        $this->assertEquals('ap-northeast-1', $log->to_dc);
    }

    /** @test */
    public function it_executes_automatic_failover()
    {
        $rule = FailoverRule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'primary_dc_id' => $this->dc1->id,
            'backup_dc_id' => $this->dc2->id,
            'auto_failover' => true,
        ]);

        $log = $this->service->executeFailover($rule, 'Automatic failover', true);

        $this->assertEquals('failover', $log->action);
        $this->assertTrue($log->is_automatic);
    }

    // ═══════ 故障切换日志 ═══════

    /** @test */
    public function it_lists_failover_logs()
    {
        $rule = FailoverRule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'primary_dc_id' => $this->dc1->id,
            'backup_dc_id' => $this->dc2->id,
        ]);

        FailoverLog::factory()->count(3)->create([
            'failover_rule_id' => $rule->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $logs = $this->service->listFailoverLogs($this->tenant->id);

        $this->assertCount(3, $logs->items());
    }

    /** @test */
    public function it_filters_failover_logs_by_action()
    {
        $rule = FailoverRule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'primary_dc_id' => $this->dc1->id,
            'backup_dc_id' => $this->dc2->id,
        ]);

        FailoverLog::factory()->create([
            'failover_rule_id' => $rule->id,
            'tenant_id' => $this->tenant->id,
            'action' => 'failover',
        ]);
        FailoverLog::factory()->create([
            'failover_rule_id' => $rule->id,
            'tenant_id' => $this->tenant->id,
            'action' => 'restore',
        ]);

        $logs = $this->service->listFailoverLogs($this->tenant->id, ['action' => 'failover']);

        $this->assertCount(1, $logs->items());
    }

    // ═══════ 仪表盘 ═══════

    /** @test */
    public function it_returns_dashboard_data()
    {
        FailoverRule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'primary_dc_id' => $this->dc1->id,
            'backup_dc_id' => $this->dc2->id,
        ]);

        $dashboard = $this->service->getDashboard($this->tenant->id);

        $this->assertArrayHasKey('data_centers', $dashboard);
        $this->assertArrayHasKey('failover_rules', $dashboard);
        $this->assertArrayHasKey('stats', $dashboard);
        $this->assertEquals(2, $dashboard['stats']['total_dcs']);
        $this->assertEquals(1, $dashboard['stats']['total_rules']);
    }

    // ═══════ 自动故障切换检测 ═══════

    /** @test */
    public function it_does_not_failover_when_primary_is_healthy()
    {
        FailoverRule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'primary_dc_id' => $this->dc1->id,
            'backup_dc_id' => $this->dc2->id,
            'auto_failover' => true,
        ]);

        $results = $this->service->autoFailoverCheck($this->tenant->id);

        $this->assertEmpty($results);
    }

    // ═══════════ M3-52 区域部署管理 ═══════════

    /** @test */
    public function it_lists_region_deployments()
    {
        RegionDeployment::create([
            'region_key' => 'us-east',
            'name' => '美东',
            'provider' => 'aws',
            'api_url' => 'https://api-us.huwutong.com',
            'status' => 'active',
            'is_primary' => true,
            'weight' => 40,
        ]);

        $deployments = $this->service->listRegionDeployments();

        $this->assertCount(1, $deployments);
        $this->assertEquals('us-east', $deployments->first()->region_key);
    }

    /** @test */
    public function it_creates_a_region_deployment()
    {
        $deployment = $this->service->createRegionDeployment([
            'region_key' => 'eu-west',
            'name' => '欧洲',
            'provider' => 'aws',
            'api_url' => 'https://api-eu.huwutong.com',
            'weight' => 30,
        ]);

        $this->assertDatabaseHas('region_deployments', ['region_key' => 'eu-west']);
        $this->assertEquals('欧洲', $deployment->name);
    }

    /** @test */
    public function it_updates_a_region_deployment()
    {
        $dep = RegionDeployment::create([
            'region_key' => 'ap-southeast',
            'name' => '东南亚',
            'provider' => 'aws',
            'api_url' => 'https://api-ap.huwutong.com',
        ]);

        $updated = $this->service->updateRegionDeployment($dep->id, ['weight' => 50]);

        $this->assertEquals(50, $updated->weight);
    }

    /** @test */
    public function it_deletes_a_region_deployment()
    {
        $dep = RegionDeployment::create([
            'region_key' => 'us-east',
            'name' => '美东',
            'provider' => 'aws',
            'api_url' => 'https://api-us.huwutong.com',
        ]);

        $this->service->deleteRegionDeployment($dep->id);

        $this->assertDatabaseMissing('region_deployments', ['id' => $dep->id]);
    }

    /** @test */
    public function it_seeds_region_deployments_from_config()
    {
        Config::set('multi-region.regions', [
            'us-east' => ['name' => '美东', 'provider' => 'aws', 'api_url' => 'https://api-us.huwutong.com', 'weight' => 40],
            'eu-west' => ['name' => '欧洲', 'provider' => 'aws', 'api_url' => 'https://api-eu.huwutong.com', 'weight' => 30],
            'ap-southeast' => ['name' => '东南亚', 'provider' => 'aws', 'api_url' => 'https://api-ap.huwutong.com', 'weight' => 30],
        ]);

        $created = $this->service->seedRegionDeployments();

        $this->assertCount(3, $created);
        $this->assertDatabaseHas('region_deployments', ['region_key' => 'us-east']);
        $this->assertDatabaseHas('region_deployments', ['region_key' => 'eu-west']);
        $this->assertDatabaseHas('region_deployments', ['region_key' => 'ap-southeast']);
    }

    // ═══════════ M3-52 GeoDNS路由 ═══════════

    /** @test */
    public function it_returns_optimal_region_via_geo_dns()
    {
        Config::set('multi-region.routing.strategy', 'geo_dns');
        Config::set('multi-region.routing.fallback_region', 'us-east');

        RegionDeployment::create([
            'region_key' => 'us-east', 'name' => '美东', 'provider' => 'aws',
            'api_url' => 'https://api-us.huwutong.com', 'status' => 'active',
            'is_healthy' => true, 'weight' => 40, 'config' => ['latency_base_ms' => 5],
        ]);
        RegionDeployment::create([
            'region_key' => 'eu-west', 'name' => '欧洲', 'provider' => 'aws',
            'api_url' => 'https://api-eu.huwutong.com', 'status' => 'active',
            'is_healthy' => true, 'weight' => 30, 'config' => ['latency_base_ms' => 50],
        ]);

        $result = $this->service->getOptimalRegion('192.168.1.1');

        $this->assertArrayHasKey('region', $result);
        $this->assertArrayHasKey('strategy', $result);
        $this->assertEquals('geo_dns', $result['strategy']);
    }

    /** @test */
    public function it_returns_fallback_when_no_healthy_deployments()
    {
        Config::set('multi-region.routing.fallback_region', 'us-east');

        $result = $this->service->getOptimalRegion('10.0.0.1');

        $this->assertEquals('us-east', $result['region']);
        $this->assertEquals('fallback', $result['strategy']);
    }

    // ═══════════ M3-52 跨区域数据同步 ═══════════

    /** @test */
    public function it_creates_sync_log_and_starts_sync()
    {
        RegionDeployment::create([
            'region_key' => 'us-east', 'name' => '美东', 'provider' => 'aws',
            'api_url' => '', 'status' => 'active', 'is_healthy' => true,
        ]);
        RegionDeployment::create([
            'region_key' => 'eu-west', 'name' => '欧洲', 'provider' => 'aws',
            'api_url' => '', 'status' => 'active', 'is_healthy' => true,
        ]);

        $syncLog = $this->service->startDataSync('us-east', 'eu-west', 'license');

        $this->assertDatabaseHas('region_sync_logs', [
            'source_region' => 'us-east',
            'target_region' => 'eu-west',
            'data_type' => 'license',
        ]);
        $this->assertEquals('completed', $syncLog->status);
    }

    /** @test */
    public function it_lists_sync_logs()
    {
        RegionSyncLog::create([
            'source_region' => 'us-east',
            'target_region' => 'eu-west',
            'data_type' => 'license',
            'status' => 'completed',
            'items_count' => 10,
            'items_synced' => 10,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $logs = $this->service->listSyncLogs();

        $this->assertCount(1, $logs->items());
    }

    // ═══════════ M3-52 仪表盘包含区域部署 ═══════════

    /** @test */
    public function dashboard_includes_region_deployments()
    {
        RegionDeployment::create([
            'region_key' => 'us-east', 'name' => '美东', 'provider' => 'aws',
            'api_url' => 'https://api-us.huwutong.com', 'status' => 'active',
            'is_healthy' => true, 'weight' => 40,
        ]);

        $dashboard = $this->service->getDashboard($this->tenant->id);

        $this->assertArrayHasKey('region_deployments', $dashboard);
        $this->assertArrayHasKey('region_health_summaries', $dashboard);
        $this->assertArrayHasKey('total_region_deployments', $dashboard['stats']);
        $this->assertArrayHasKey('healthy_regions', $dashboard['stats']);
        $this->assertEquals(1, $dashboard['stats']['total_region_deployments']);
    }
}
