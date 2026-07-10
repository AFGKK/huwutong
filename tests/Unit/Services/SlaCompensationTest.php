<?php

namespace Tests\Unit\Services;

use App\Models\SlaBreach;
use App\Models\SlaCompensation;
use App\Models\SlaContract;
use App\Models\SlaMetric;
use App\Services\SlaService;
use Carbon\Carbon;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class SlaCompensationTest extends TestCase
{
    use RefreshDatabase;

    protected SlaService $service;
    protected SlaContract $contract;
    protected SlaMetric $metric;
    protected SlaBreach $breach;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SlaService::class);

        $tenant = \App\Models\Tenant::factory()->create();

        $this->contract = SlaContract::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Test Premium Contract',
            'level' => 'premium',
            'is_active' => true,
            'penalties' => [
                'compensation_type' => 'credit',
                'auto_approve' => false,
                'currency' => 'CNY',
                'amounts' => [
                    'minor' => 100,
                    'major' => 300,
                    'critical' => 1000,
                ],
            ],
        ]);

        $this->metric = SlaMetric::factory()->create([
            'sla_contract_id' => $this->contract->id,
            'metric_key' => 'response_time',
            'name' => '响应时间',
            'target_value' => 30,
            'measurement_window' => 'daily',
        ]);

        $this->breach = SlaBreach::factory()->create([
            'sla_contract_id' => $this->contract->id,
            'sla_metric_id' => $this->metric->id,
            'breach_type' => 'response_time',
            'severity' => 'major',
            'description' => '响应时间超过 SLA 阈值',
            'status' => 'open',
        ]);
    }

    /** @test */
    public function it_auto_generates_compensation_for_breach()
    {
        $comp = $this->service->autoGenerateCompensation($this->breach);

        $this->assertInstanceOf(SlaCompensation::class, $comp);
        $this->assertEquals($this->contract->id, $comp->sla_contract_id);
        $this->assertEquals($this->breach->id, $comp->sla_breach_id);
        $this->assertEquals('major', $comp->severity);
        $this->assertEquals('credit', $comp->compensation_type);
        $this->assertEquals(300, (int) $comp->amount);
        $this->assertEquals('CNY', $comp->currency);
        $this->assertEquals('pending', $comp->status);
        $this->assertEquals('automatic', $comp->calculation_method);
        $this->assertStringContainsString('SLA违约自动补偿', $comp->reason);
    }

    /** @test */
    public function it_uses_default_amount_when_penalties_not_configured()
    {
        $contract = SlaContract::factory()->create([
            'tenant_id' => $this->contract->tenant_id,
            'name' => 'No Penalties Contract',
            'penalties' => null,
            'is_active' => true,
        ]);
        $breach = SlaBreach::factory()->create([
            'sla_contract_id' => $contract->id,
            'sla_metric_id' => $this->metric->id,
            'severity' => 'critical',
            'description' => 'Critical breach',
            'status' => 'open',
        ]);

        $comp = $this->service->autoGenerateCompensation($breach);

        $this->assertEquals('critical', $comp->severity);
        $this->assertEquals(500, (int) $comp->amount);
        $this->assertEquals('credit', $comp->compensation_type);
    }

    /** @test */
    public function it_auto_approves_when_configured()
    {
        $this->contract->update(['penalties' => array_merge(
            $this->contract->penalties ?? [],
            ['auto_approve' => true]
        )]);

        $breach = SlaBreach::factory()->create([
            'sla_contract_id' => $this->contract->id,
            'sla_metric_id' => $this->metric->id,
            'severity' => 'minor',
            'status' => 'open',
        ]);

        $comp = $this->service->autoGenerateCompensation($breach);
        $this->assertEquals('approved', $comp->status);
    }

    /** @test */
    public function it_batch_generates_for_open_breaches()
    {
        SlaBreach::factory()->count(3)->create([
            'sla_contract_id' => $this->contract->id,
            'sla_metric_id' => $this->metric->id,
            'severity' => 'minor',
            'status' => 'open',
        ]);

        // One resolved breach should NOT be included
        SlaBreach::factory()->create([
            'sla_contract_id' => $this->contract->id,
            'sla_metric_id' => $this->metric->id,
            'severity' => 'major',
            'status' => 'resolved',
        ]);

        $result = $this->service->autoGenerateForOpenBreaches($this->contract->tenant_id);

        // 1 existing + 3 new = 4 open breaches generated
        $this->assertCount(4, $result);
        foreach ($result as $comp) {
            $this->assertEquals('pending', $comp->status);
        }
    }

    /** @test */
    public function it_does_not_duplicate_compensation()
    {
        // First generation
        $this->service->autoGenerateCompensation($this->breach);

        // Second generation should not create a new one (doesntHave check)
        $result = $this->service->autoGenerateForOpenBreaches($this->contract->tenant_id);
        $this->assertCount(0, $result);
    }

    /** @test */
    public function it_lists_compensations()
    {
        SlaCompensation::factory()->count(5)->create([
            'sla_contract_id' => $this->contract->id,
            'tenant_id' => $this->contract->tenant_id,
            'customer_id' => $this->contract->customer_id,
        ]);

        $result = $this->service->getCompensations($this->contract->tenant_id);

        $this->assertEquals(5, $result->total());
    }

    /** @test */
    public function it_filters_compensations_by_status()
    {
        SlaCompensation::factory()->create([
            'sla_contract_id' => $this->contract->id,
            'tenant_id' => $this->contract->tenant_id,
            'status' => 'pending',
        ]);
        SlaCompensation::factory()->count(3)->create([
            'sla_contract_id' => $this->contract->id,
            'tenant_id' => $this->contract->tenant_id,
            'status' => 'issued',
        ]);

        $result = $this->service->getCompensations($this->contract->tenant_id, ['status' => 'issued']);
        $this->assertEquals(3, $result->total());
    }

    /** @test */
    public function it_filters_compensations_by_severity()
    {
        SlaCompensation::factory()->count(2)->create([
            'sla_contract_id' => $this->contract->id,
            'tenant_id' => $this->contract->tenant_id,
            'severity' => 'critical',
        ]);
        SlaCompensation::factory()->count(3)->create([
            'sla_contract_id' => $this->contract->id,
            'tenant_id' => $this->contract->tenant_id,
            'severity' => 'minor',
        ]);

        $result = $this->service->getCompensations($this->contract->tenant_id, ['severity' => 'critical']);
        $this->assertEquals(2, $result->total());
    }

    /** @test */
    public function it_approves_compensation()
    {
        $comp = SlaCompensation::factory()->create([
            'sla_contract_id' => $this->contract->id,
            'tenant_id' => $this->contract->tenant_id,
            'status' => 'pending',
        ]);

        $user = \App\Models\User::factory()->create(['tenant_id' => $this->contract->tenant_id]);

        $approved = $this->service->approveCompensation($comp->id, $user->id);

        $this->assertEquals('approved', $approved->status);
        $this->assertNotNull($approved->approved_at);
        $this->assertEquals($user->id, $approved->approved_by);
    }

    /** @test */
    public function it_issues_compensation()
    {
        $comp = SlaCompensation::factory()->create([
            'sla_contract_id' => $this->contract->id,
            'tenant_id' => $this->contract->tenant_id,
            'status' => 'approved',
        ]);

        $issued = $this->service->issueCompensation($comp->id);

        $this->assertEquals('issued', $issued->status);
        $this->assertNotNull($issued->issued_at);
    }

    /** @test */
    public function it_rejects_compensation()
    {
        $comp = SlaCompensation::factory()->create([
            'sla_contract_id' => $this->contract->id,
            'tenant_id' => $this->contract->tenant_id,
            'status' => 'pending',
        ]);

        $rejected = $this->service->rejectCompensation($comp->id, '不符合补偿条件');

        $this->assertEquals('rejected', $rejected->status);
        $this->assertEquals('不符合补偿条件', $rejected->notes);
    }

    /** @test */
    public function it_returns_compensation_stats()
    {
        SlaCompensation::factory()->count(3)->create([
            'sla_contract_id' => $this->contract->id,
            'tenant_id' => $this->contract->tenant_id,
            'status' => 'approved',
            'amount' => 100,
            'compensation_type' => 'credit',
        ]);
        SlaCompensation::factory()->create([
            'sla_contract_id' => $this->contract->id,
            'tenant_id' => $this->contract->tenant_id,
            'status' => 'pending',
            'amount' => 200,
            'compensation_type' => 'credit',
        ]);
        SlaCompensation::factory()->create([
            'sla_contract_id' => $this->contract->id,
            'tenant_id' => $this->contract->tenant_id,
            'status' => 'issued',
            'amount' => 500,
            'compensation_type' => 'extension',
        ]);

        $stats = $this->service->getCompensationStats($this->contract->tenant_id);

        $this->assertEquals(5, $stats['total_count']);
        $this->assertEquals(1, $stats['pending_count']);
        // approved(300) + issued(500) = 800
        $this->assertEquals(800, $stats['total_amount']);
        $this->assertCount(2, $stats['by_type']);
    }
}
