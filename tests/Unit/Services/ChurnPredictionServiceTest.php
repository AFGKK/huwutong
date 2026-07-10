<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\ChurnIntervention;
use App\Models\ChurnPrediction;
use App\Models\Customer;
use App\Models\Tenant;
use App\Services\ChurnPredictionService;
use Tests\Concerns\RefreshDatabase;

class ChurnPredictionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ChurnPredictionService $service;
    protected Tenant $tenant;
    protected Customer $customer1;
    protected Customer $customer2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(ChurnPredictionService::class);
        $this->tenant = Tenant::factory()->create();
        $this->customer1 = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->customer2 = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    // ─── 流失预测清单 ───

    public function test_returns_empty_churn_list_when_no_predictions()
    {
        $result = $this->service->getChurnList($this->tenant->id);
        $this->assertCount(0, $result['data']);
    }

    // ─── 干预管理 ───

    public function test_creates_intervention()
    {
        $int = $this->service->createIntervention([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer1->id,
            'type' => 'renewal_call',
            'title' => '续费跟进电话',
            'assigned_to' => '张三',
        ]);

        $this->assertEquals('续费跟进电话', $int->title);
        $this->assertEquals('pending', $int->status);
    }

    public function test_lists_interventions()
    {
        ChurnIntervention::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer1->id,
            'type' => 'renewal_call',
            'status' => 'pending',
        ]);
        ChurnIntervention::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer2->id,
            'type' => 'coupon_offer',
            'status' => 'completed',
        ]);

        $all = $this->service->listInterventions($this->tenant->id);
        $this->assertCount(2, $all['data']);

        $pending = $this->service->listInterventions($this->tenant->id, ['status' => 'pending']);
        $this->assertCount(1, $pending['data']);
    }

    public function test_updates_intervention_to_completed()
    {
        $int = ChurnIntervention::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer1->id,
            'status' => 'in_progress',
        ]);

        $updated = $this->service->updateIntervention($int, [
            'status' => 'completed',
            'result' => '客户同意续费',
            'outcome' => 'positive',
        ]);

        $this->assertEquals('completed', $updated->status);
        $this->assertEquals('positive', $updated->outcome);
        $this->assertNotNull($updated->completed_at);
    }

    public function test_deletes_intervention()
    {
        $int = ChurnIntervention::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer1->id,
        ]);

        $this->service->deleteIntervention($int);

        $this->assertDatabaseMissing('churn_interventions', ['id' => $int->id]);
    }

    // ─── 仪表盘 ───

    public function test_get_dashboard_returns_stats()
    {
        // 创建流失预测
        ChurnPrediction::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer1->id,
            'risk_level' => 'high',
            'churn_probability' => 0.75,
            'predicted_at' => now(),
        ]);
        ChurnPrediction::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer2->id,
            'risk_level' => 'low',
            'churn_probability' => 0.1,
            'predicted_at' => now(),
        ]);

        // 创建干预
        ChurnIntervention::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer1->id,
            'type' => 'renewal_call',
            'status' => 'completed',
            'outcome' => 'positive',
        ]);

        $stats = $this->service->getDashboard($this->tenant->id);

        $this->assertArrayHasKey('churn_by_risk', $stats);
        $this->assertArrayHasKey('interventions', $stats);
        $this->assertEquals(1, $stats['interventions']['total']);
        $this->assertGreaterThan(0, $stats['positive_rate']);
    }

    public function test_get_trend_returns_monthly_data()
    {
        ChurnPrediction::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer1->id,
            'risk_level' => 'high',
            'churn_probability' => 0.7,
            'predicted_at' => now()->subDays(5),
        ]);
        ChurnPrediction::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer2->id,
            'risk_level' => 'low',
            'churn_probability' => 0.1,
            'predicted_at' => now()->subMonths(2),
        ]);

        $trend = $this->service->getTrend($this->tenant->id);

        $this->assertNotEmpty($trend);
        // Should return data grouped by month
        $this->assertArrayHasKey('month', $trend[0]);
        $this->assertArrayHasKey('high', $trend[0]);
    }

    // ─── 干预类型过滤 ───

    public function test_filters_interventions_by_type()
    {
        ChurnIntervention::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer1->id,
            'type' => 'renewal_call',
        ]);
        ChurnIntervention::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer2->id,
            'type' => 'survey',
        ]);

        $renewals = $this->service->listInterventions($this->tenant->id, ['type' => 'renewal_call']);

        $this->assertCount(1, $renewals['data']);
        $this->assertEquals('renewal_call', $renewals['data'][0]['type']);
    }
}
