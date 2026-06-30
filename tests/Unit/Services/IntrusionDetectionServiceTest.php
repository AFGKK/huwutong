<?php

namespace Tests\Unit\Services;

use App\Models\IdsAlert;
use App\Models\IdsRule;
use App\Models\SecurityEvent;
use App\Models\Tenant;
use App\Services\IntrusionDetectionService;
use App\Services\SecurityCenterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntrusionDetectionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected IntrusionDetectionService $service;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $securityService = $this->createMock(SecurityCenterService::class);
        $this->service = new IntrusionDetectionService($securityService);
        $this->tenant = Tenant::factory()->create();
    }

    // ─── 规则管理 ───

    /** @test */
    public function it_can_create_a_rule()
    {
        $rule = $this->service->createRule([
            'tenant_id' => $this->tenant->id,
            'name' => '测试规则',
            'slug' => 'test-rule',
            'description' => '测试用规则',
            'detection_type' => 'brute_force',
            'severity' => 'critical',
            'threshold_count' => 5,
            'threshold_window_minutes' => 5,
            'conditions' => ['event_type' => 'login_failed', 'group_by' => 'ip_address'],
            'actions' => [['type' => 'block_ip', 'duration_minutes' => 30]],
        ]);

        $this->assertInstanceOf(IdsRule::class, $rule);
        $this->assertEquals('测试规则', $rule->name);
        $this->assertEquals('test-rule', $rule->slug);
        $this->assertEquals('brute_force', $rule->detection_type);
    }

    /** @test */
    public function it_auto_generates_slug_if_not_provided()
    {
        $rule = $this->service->createRule([
            'tenant_id' => $this->tenant->id,
            'name' => 'Brute Force Detection',
            'detection_type' => 'brute_force',
            'severity' => 'critical',
            'conditions' => ['event_type' => 'login_failed'],
            'actions' => [['type' => 'notify_admin']],
        ]);

        $this->assertEquals('brute_force_detection', $rule->slug);
    }

    /** @test */
    public function it_can_list_rules_with_filters()
    {
        IdsRule::factory()->count(3)->create(['tenant_id' => $this->tenant->id, 'detection_type' => 'brute_force']);
        IdsRule::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'detection_type' => 'geo_anomaly']);

        $result = $this->service->getRules(['tenant_id' => $this->tenant->id]);

        $this->assertCount(5, $result['data']);
        $this->assertEquals(5, $result['total']);
    }

    /** @test */
    public function it_can_filter_rules_by_detection_type()
    {
        IdsRule::factory()->count(3)->create(['tenant_id' => $this->tenant->id, 'detection_type' => 'brute_force']);
        IdsRule::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'detection_type' => 'geo_anomaly']);

        $result = $this->service->getRules(['tenant_id' => $this->tenant->id, 'detection_type' => 'geo_anomaly']);

        $this->assertCount(2, $result['data']);
    }

    /** @test */
    public function it_can_filter_rules_by_active_status()
    {
        IdsRule::factory()->count(3)->create(['tenant_id' => $this->tenant->id, 'is_active' => true]);
        IdsRule::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'is_active' => false]);

        $result = $this->service->getRules(['tenant_id' => $this->tenant->id, 'is_active' => true]);

        $this->assertCount(3, $result['data']);
    }

    /** @test */
    public function it_can_get_a_single_rule()
    {
        $rule = IdsRule::factory()->create(['tenant_id' => $this->tenant->id]);

        $found = $this->service->getRule($rule->id);

        $this->assertNotNull($found);
        $this->assertEquals($rule->id, $found->id);
    }

    /** @test */
    public function it_can_update_a_rule()
    {
        $rule = IdsRule::factory()->create(['tenant_id' => $this->tenant->id, 'name' => '旧名称']);

        $this->service->updateRule($rule, ['name' => '新名称']);

        $this->assertEquals('新名称', $rule->fresh()->name);
    }

    /** @test */
    public function it_cannot_update_system_rule_name()
    {
        $rule = IdsRule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => '系统规则',
            'is_system' => true,
        ]);

        $this->service->updateRule($rule, ['name' => '新名称', 'is_active' => false]);

        $this->assertEquals('系统规则', $rule->fresh()->name);
        $this->assertFalse($rule->fresh()->is_active);
    }

    /** @test */
    public function it_can_delete_a_non_system_rule()
    {
        $rule = IdsRule::factory()->create(['tenant_id' => $this->tenant->id, 'is_system' => false]);

        $result = $this->service->deleteRule($rule);

        $this->assertTrue($result);
        $this->assertNull(IdsRule::find($rule->id));
    }

    /** @test */
    public function it_cannot_delete_a_system_rule()
    {
        $rule = IdsRule::factory()->create(['tenant_id' => $this->tenant->id, 'is_system' => true]);

        $result = $this->service->deleteRule($rule);

        $this->assertFalse($result);
        $this->assertNotNull(IdsRule::find($rule->id));
    }

    /** @test */
    public function it_can_seed_system_rules()
    {
        $count = $this->service->seedSystemRules($this->tenant->id);

        $this->assertEquals(5, $count);
        $this->assertDatabaseHas('ids_rules', ['slug' => 'brute-force-login', 'tenant_id' => $this->tenant->id]);
        $this->assertDatabaseHas('ids_rules', ['slug' => 'credential-stuffing', 'tenant_id' => $this->tenant->id]);

        $count2 = $this->service->seedSystemRules($this->tenant->id);
        $this->assertEquals(0, $count2);
    }

    /** @test */
    public function it_returns_rule_stats()
    {
        IdsRule::factory()->count(3)->create(['tenant_id' => $this->tenant->id, 'is_active' => true, 'is_system' => false, 'detection_type' => 'brute_force']);
        IdsRule::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'is_active' => false, 'is_system' => true, 'detection_type' => 'geo_anomaly']);

        $stats = $this->service->getRuleStats($this->tenant->id);

        $this->assertEquals(5, $stats['total']);
        $this->assertEquals(3, $stats['active']);
        $this->assertEquals(2, $stats['system']);
    }

    // ─── 告警管理 ───

    /** @test */
    public function it_can_create_and_list_alerts()
    {
        $rule = IdsRule::factory()->create(['tenant_id' => $this->tenant->id]);

        IdsAlert::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'ids_rule_id' => $rule->id,
            'status' => 'open',
        ]);

        $result = $this->service->getAlerts(['tenant_id' => $this->tenant->id]);

        $this->assertCount(5, $result['data']);
    }

    /** @test */
    public function it_can_filter_alerts_by_status()
    {
        IdsAlert::factory()->count(3)->create(['tenant_id' => $this->tenant->id, 'status' => 'open']);
        IdsAlert::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'status' => 'mitigated']);

        $open = $this->service->getAlerts(['tenant_id' => $this->tenant->id, 'status' => 'open']);
        $this->assertCount(3, $open['data']);

        $mitigated = $this->service->getAlerts(['tenant_id' => $this->tenant->id, 'status' => 'mitigated']);
        $this->assertCount(2, $mitigated['data']);
    }

    /** @test */
    public function it_can_filter_alerts_by_severity()
    {
        IdsAlert::factory()->count(3)->create(['tenant_id' => $this->tenant->id, 'severity' => 'critical']);
        IdsAlert::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'severity' => 'warning']);

        $result = $this->service->getAlerts(['tenant_id' => $this->tenant->id, 'severity' => 'warning']);
        $this->assertCount(2, $result['data']);
    }

    /** @test */
    public function it_can_update_alert_status()
    {
        $alert = IdsAlert::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'open']);

        $this->service->updateAlertStatus($alert, 'mitigated');

        $alert->refresh();
        $this->assertEquals('mitigated', $alert->status);
        $this->assertNotNull($alert->mitigated_at);
    }

    /** @test */
    public function it_sets_closed_at_when_closing_alert()
    {
        $alert = IdsAlert::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'open']);

        $this->service->updateAlertStatus($alert, 'closed');

        $alert->refresh();
        $this->assertEquals('closed', $alert->status);
        $this->assertNotNull($alert->closed_at);
    }

    /** @test */
    public function it_throws_on_invalid_status()
    {
        $alert = IdsAlert::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->updateAlertStatus($alert, 'invalid_status');
    }

    // ─── 仪表盘 ───

    /** @test */
    public function it_returns_dashboard_data()
    {
        $rule = IdsRule::factory()->create(['tenant_id' => $this->tenant->id]);
        IdsAlert::factory()->count(5)->create(['tenant_id' => $this->tenant->id, 'ids_rule_id' => $rule->id, 'status' => 'open', 'severity' => 'warning']);
        IdsAlert::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'ids_rule_id' => $rule->id, 'status' => 'open', 'severity' => 'critical']);
        IdsAlert::factory()->count(3)->create(['tenant_id' => $this->tenant->id, 'ids_rule_id' => $rule->id, 'status' => 'mitigated']);

        $dashboard = $this->service->getDashboard($this->tenant->id);

        $this->assertEquals(7, $dashboard['open_alerts']);
        $this->assertEquals(10, $dashboard['total_alerts']);
        $this->assertEquals(2, $dashboard['critical_alerts']);
        $this->assertArrayHasKey('recent_alerts', $dashboard);
        $this->assertArrayHasKey('top_sources', $dashboard);
        $this->assertArrayHasKey('rule_stats', $dashboard);
    }

    /** @test */
    public function it_returns_alert_trends()
    {
        IdsAlert::factory()->create([
            'tenant_id' => $this->tenant->id,
            'severity' => 'critical',
            'created_at' => now(),
        ]);
        IdsAlert::factory()->create([
            'tenant_id' => $this->tenant->id,
            'severity' => 'warning',
            'created_at' => now()->subDay(),
        ]);

        $trends = $this->service->getAlertTrends($this->tenant->id, 7);

        $this->assertCount(7, $trends);
        $todayEntry = collect($trends)->firstWhere('date', now()->format('Y-m-d'));
        $this->assertNotNull($todayEntry);
        $this->assertEquals(1, $todayEntry['critical']);
    }

    /** @test */
    public function it_can_clear_old_alerts()
    {
        IdsAlert::factory()->count(3)->create(['tenant_id' => $this->tenant->id, 'created_at' => now()->subDays(60)]);
        IdsAlert::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'created_at' => now()->subDays(5)]);

        $deleted = $this->service->clearAlerts($this->tenant->id, '30 days');

        $this->assertEquals(3, $deleted);
        $this->assertEquals(2, IdsAlert::where('tenant_id', $this->tenant->id)->count());
    }

    // ─── 安全事件处理 ───

    /** @test */
    public function it_does_not_create_alert_when_no_rules_match()
    {
        $event = SecurityEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'event_type' => 'login_failed',
            'ip_address' => '192.168.1.1',
        ]);

        $result = $this->service->processSecurityEvent($event);

        $this->assertNull($result);
        $this->assertEquals(0, IdsAlert::count());
    }

    /** @test */
    public function it_creates_alert_for_matching_rule()
    {
        IdsRule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'detection_type' => 'brute_force',
            'is_active' => true,
            'threshold_count' => 1,
            'threshold_window_minutes' => 0,
        ]);

        $event = SecurityEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'event_type' => 'login_failed',
            'ip_address' => '192.168.1.100',
        ]);

        $alert = $this->service->processSecurityEvent($event);

        $this->assertNotNull($alert);
        $this->assertEquals('open', $alert->status);
        $this->assertEquals('192.168.1.100', $alert->source_ip);
    }

    /** @test */
    public function it_increments_rule_hit_count_on_alert()
    {
        $rule = IdsRule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'detection_type' => 'brute_force',
            'is_active' => true,
            'threshold_count' => 1,
        ]);

        $event = SecurityEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'event_type' => 'login_failed',
        ]);

        $this->service->processSecurityEvent($event);

        $this->assertEquals(1, $rule->fresh()->hit_count);
        $this->assertNotNull($rule->fresh()->last_hit_at);
    }

    /** @test */
    public function it_detects_maps_event_type_to_detection_type()
    {
        IdsRule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'detection_type' => 'geo_anomaly',
            'is_active' => true,
            'threshold_count' => 1,
        ]);

        $event = SecurityEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'event_type' => 'geo_anomaly',
        ]);

        $alert = $this->service->processSecurityEvent($event);

        $this->assertNotNull($alert);
        $this->assertEquals('geo_anomaly', $alert->detection_type);
    }

    // ─── 辅助方法 ───

    /** @test */
    public function it_builds_ip_block_reason()
    {
        $rule = IdsRule::factory()->create(['tenant_id' => $this->tenant->id, 'slug' => 'test-rule', 'name' => '测试规则']);
        $alert = IdsAlert::factory()->create([
            'tenant_id' => $this->tenant->id,
            'ids_rule_id' => $rule->id,
            'rule_slug' => 'test-rule',
            'rule_name' => '测试规则',
            'severity' => 'critical',
            'source_ip' => '10.0.0.5',
        ]);

        $reason = $this->service->buildIpBlockReason($alert);

        $this->assertStringContainsString('严重', $reason);
        $this->assertStringContainsString('测试规则', $reason);
        $this->assertStringContainsString('10.0.0.5', $reason);
    }
}
