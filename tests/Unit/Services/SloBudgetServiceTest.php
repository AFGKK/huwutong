<?php

namespace Tests\Unit\Services;

use App\Models\ApmRequest;
use App\Models\SloBudgetEvent;
use App\Models\SloDailyRecord;
use App\Models\SloDefinition;
use App\Models\Tenant;
use App\Services\SloBudgetService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SloBudgetServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SloBudgetService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SloBudgetService::class);
    }

    /** @test */
    public function creates_slo_definition()
    {
        $tenant = Tenant::factory()->create();

        $slo = $this->service->createDefinition([
            'tenant_id' => $tenant->id,
            'name' => 'API可用性SLO',
            'service_name' => 'api',
            'sli_type' => 'availability',
            'target' => 99.9,
            'window_days' => 30,
        ]);

        $this->assertInstanceOf(SloDefinition::class, $slo);
        $this->assertEquals('API可用性SLO', $slo->name);
        $this->assertNotNull($slo->slug);
        $this->assertTrue($slo->is_active);
        // 错误预算 = 30天 * 24h * 60min * (1-99.9%) = 43.2 minutes
        $this->assertNotNull($slo->remaining_budget);
    }

    /** @test */
    public function updates_slo_definition()
    {
        $slo = SloDefinition::factory()->create();
        $updated = $this->service->updateDefinition($slo, ['name' => '更新名称']);
        $this->assertEquals('更新名称', $updated->name);
    }

    /** @test */
    public function deletes_slo_and_related_records()
    {
        $slo = SloDefinition::factory()->create();
        SloDailyRecord::create([
            'slo_definition_id' => $slo->id,
            'record_date' => now()->format('Y-m-d'),
            'total_requests' => 100,
            'good_requests' => 99,
            'bad_requests' => 1,
        ]);

        $this->service->deleteDefinition($slo);

        $this->assertDatabaseMissing('slo_definitions', ['id' => $slo->id]);
        $this->assertDatabaseMissing('slo_daily_records', ['slo_definition_id' => $slo->id]);
    }

    /** @test */
    public function lists_definitions_with_filters()
    {
        $tenant = Tenant::factory()->create();
        SloDefinition::factory()->latency()->count(3)->create(['tenant_id' => $tenant->id]);
        SloDefinition::factory()->availability()->count(2)->create(['tenant_id' => $tenant->id]);

        $all = $this->service->listDefinitions($tenant->id);
        $this->assertEquals(5, $all->total());

        $latency = $this->service->listDefinitions($tenant->id, ['sli_type' => 'latency']);
        $this->assertEquals(3, $latency->total());
    }

    /** @test */
    public function calculate_budget_uses_apm_data()
    {
        $tenant = Tenant::factory()->create();
        $slo = $this->service->createDefinition([
            'tenant_id' => $tenant->id,
            'name' => '可用性SLO',
            'service_name' => 'api',
            'sli_type' => 'availability',
            'target' => 99.0,
            'window_days' => 30,
        ]);

        // 创建APM数据：90%成功
        for ($i = 0; $i < 90; $i++) {
            ApmRequest::factory()->create([
                'tenant_id' => $tenant->id,
                'status_code' => 200,
                'created_at' => now()->subHours(rand(1, 48)),
            ]);
        }
        for ($i = 0; $i < 10; $i++) {
            ApmRequest::factory()->create([
                'tenant_id' => $tenant->id,
                'status_code' => 500,
                'created_at' => now()->subHours(rand(1, 48)),
            ]);
        }

        $result = $this->service->calculateBudget($slo);

        $this->assertEquals(100, $result['total_requests']);
        $this->assertEquals(90, $result['good_requests']);
        $this->assertEquals(10, $result['bad_requests']);
        $this->assertEquals(90.0, $result['current_sli']);
        $this->assertGreaterThan(0, $result['total_budget_minutes']);
        $this->assertGreaterThan(0, $result['consumed_budget']);
        $this->assertGreaterThan(0, $result['burn_rate']);
    }

    /** @test */
    public function total_budget_minutes_correct_for_30_day_window()
    {
        $slo = SloDefinition::factory()->make([
            'target' => 99.9,
            'window_days' => 30,
        ]);

        $budget = $slo->totalBudgetMinutes();
        // 30天 * 24h * 60min * (1-99.9%) = 43,200 * 0.001 = 43.2
        $this->assertEquals(43.2, $budget);
    }

    /** @test */
    public function total_budget_minutes_correct_for_7_day_window()
    {
        $slo = SloDefinition::factory()->make([
            'target' => 99.0,
            'window_days' => 7,
        ]);

        $budget = $slo->totalBudgetMinutes();
        // 7天 * 24h * 60min * (1-99%) = 100.8
        $this->assertEquals(100.8, $budget);
    }

    /** @test */
    public function calculate_all_budgets_processes_active_definitions()
    {
        $tenant = Tenant::factory()->create();
        SloDefinition::factory()->active()->count(3)->create(['tenant_id' => $tenant->id]);
        SloDefinition::factory()->inactive()->count(2)->create(['tenant_id' => $tenant->id]);

        $count = $this->service->calculateAllBudgets();

        $this->assertEquals(3, $count);
    }

    /** @test */
    public function get_dashboard_returns_correct_stats()
    {
        $tenant = Tenant::factory()->create();
        SloDefinition::factory()->active()->count(3)->create(['tenant_id' => $tenant->id]);
        SloDefinition::factory()->exhausted()->count(1)->create(['tenant_id' => $tenant->id]);
        SloDefinition::factory()->inactive()->count(2)->create(['tenant_id' => $tenant->id]);

        $dashboard = $this->service->getDashboard($tenant->id);

        $this->assertEquals(6, $dashboard['total_slo']);
        $this->assertEquals(4, $dashboard['active_slo']);
        $this->assertEquals(3, $dashboard['healthy_slo']);
        $this->assertEquals(1, $dashboard['exhausted_slo']);
    }

    /** @test */
    public function budget_exhausted_event_is_created_when_budget_depleted()
    {
        $tenant = Tenant::factory()->create();
        $slo = $this->service->createDefinition([
            'tenant_id' => $tenant->id,
            'name' => '耗尽测试',
            'service_name' => 'api',
            'sli_type' => 'availability',
            'target' => 50.0, // 50% SLO - 很容易耗尽预算
            'window_days' => 1,
        ]);

        // 创建大量失败请求
        for ($i = 0; $i < 100; $i++) {
            ApmRequest::factory()->create([
                'tenant_id' => $tenant->id,
                'status_code' => 500,
                'created_at' => now()->subHours(rand(1, 12)),
            ]);
        }

        $this->service->calculateBudget($slo);

        $events = SloBudgetEvent::where('slo_definition_id', $slo->id)->get();
        $this->assertGreaterThanOrEqual(1, $events->count());

        $exhaustedEvents = $events->where('event_type', 'budget_exhausted');
        $this->assertEquals(1, $exhaustedEvents->count());
    }

    /** @test */
    public function budget_warning_event_created_when_below_20_percent()
    {
        $tenant = Tenant::factory()->create();
        $slo = $this->service->createDefinition([
            'tenant_id' => $tenant->id,
            'name' => '警告测试',
            'service_name' => 'api',
            'sli_type' => 'availability',
            'target' => 95.0,
            'window_days' => 7,
        ]);

        // 大量失败请求触发预算消耗
        for ($i = 0; $i < 50; $i++) {
            ApmRequest::factory()->create([
                'tenant_id' => $tenant->id,
                'status_code' => 500,
                'created_at' => now()->subHours(rand(1, 12)),
            ]);
        }

        $this->service->calculateBudget($slo);

        $this->assertDatabaseHas('slo_budget_events', [
            'slo_definition_id' => $slo->id,
        ]);
    }
}
