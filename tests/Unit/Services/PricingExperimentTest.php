<?php

namespace Tests\Unit\Services;

use App\Models\Customer;
use App\Models\PricingExperiment;
use App\Models\PricingExperimentEvent;
use App\Models\PricingExperimentParticipant;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DynamicPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingExperimentTest extends TestCase
{
    use RefreshDatabase;

    protected DynamicPricingService $service;
    protected Tenant $tenant;
    protected User $user;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(DynamicPricingService::class);
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    /** @test */
    public function creates_experiment_as_draft()
    {
        $experiment = PricingExperiment::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->assertEquals('draft', $experiment->status);
        $this->assertEquals($this->tenant->id, $experiment->tenant_id);
    }

    /** @test */
    public function assigns_participant_to_treatment_group_based_on_traffic_split()
    {
        $experiment = PricingExperiment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'running',
            'traffic_split' => 50,
        ]);

        $participant = $this->service->assignToExperiment(
            $experiment,
            $this->customer->id,
            null,
            100.00
        );

        $this->assertInstanceOf(PricingExperimentParticipant::class, $participant);
        $this->assertContains($participant->group, ['control', 'treatment']);
        $this->assertEquals($this->customer->id, $participant->customer_id);
        $this->assertEquals(100.00, $participant->original_price);
    }

    /** @test */
    public function records_experiment_event()
    {
        $experiment = PricingExperiment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'running',
        ]);

        $event = $this->service->recordExperimentEvent(
            $experiment,
            'viewed',
            null,
            ['source' => 'pricing_page']
        );

        $this->assertInstanceOf(PricingExperimentEvent::class, $event);
        $this->assertEquals('viewed', $event->event_type);
        $this->assertEquals($experiment->id, $event->experiment_id);
    }

    /** @test */
    public function calculates_experiment_results()
    {
        $experiment = PricingExperiment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'running',
            'traffic_split' => 50,
            'minimum_sample_size' => 10,
        ]);

        // Assign participants and record conversion events
        $controlIds = [];
        $treatmentIds = [];

        // Simulate 50 assignments
        for ($i = 1; $i <= 50; $i++) {
            $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
            $p = $this->service->assignToExperiment($experiment, $customer->id, null, 100.00);
            if ($p->group === 'control') {
                $controlIds[] = $p->id;
            } else {
                $treatmentIds[] = $p->id;
            }
        }

        // Record conversions: 10% control, 20% treatment
        foreach ($controlIds as $pid) {
            if (fake()->boolean(10)) {
                $this->service->recordExperimentEvent($experiment, 'converted', $pid);
            }
        }
        foreach ($treatmentIds as $pid) {
            if (fake()->boolean(20)) {
                $this->service->recordExperimentEvent($experiment, 'converted', $pid);
            }
        }

        $updated = $this->service->calculateExperimentResults($experiment);

        $this->assertNotNull($updated->results);
        $this->assertArrayHasKey('control', $updated->results);
        $this->assertArrayHasKey('treatment', $updated->results);
        $this->assertArrayHasKey('improvement', $updated->results);
        $this->assertArrayHasKey('significance', $updated->results);
        $this->assertArrayHasKey('calculated_at', $updated->results);
        $this->assertArrayHasKey('converted', $updated->results['control']);
        $this->assertArrayHasKey('converted', $updated->results['treatment']);
    }

    /** @test */
    public function lists_experiments_by_tenant()
    {
        PricingExperiment::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $otherTenant = Tenant::factory()->create();
        PricingExperiment::factory()->count(2)->create(['tenant_id' => $otherTenant->id]);

        $results = $this->service->listExperiments($this->tenant->id, [], 20);
        $this->assertEquals(3, $results->total());
    }

    /** @test */
    public function filters_experiments_by_status()
    {
        PricingExperiment::factory()->draft()->create(['tenant_id' => $this->tenant->id]);
        PricingExperiment::factory()->running()->create(['tenant_id' => $this->tenant->id]);
        PricingExperiment::factory()->completed()->create(['tenant_id' => $this->tenant->id]);

        $draftResults = $this->service->listExperiments($this->tenant->id, ['status' => 'draft']);
        $this->assertEquals(1, $draftResults->total());
    }

    /** @test */
    public function determines_group_consistently()
    {
        $experiment = PricingExperiment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'traffic_split' => 50,
        ]);

        $customerId = 12345;

        // Use reflection to test protected method
        $refMethod = new \ReflectionMethod($this->service, 'determineGroup');
        $refMethod->setAccessible(true);

        $group1 = $refMethod->invoke($this->service, $experiment, $customerId);
        $group2 = $refMethod->invoke($this->service, $experiment, $customerId);

        // Same customer always gets same group
        $this->assertEquals($group1, $group2);
    }

    /** @test */
    public function applies_treatment_price_correctly()
    {
        $experiment = PricingExperiment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'treatment_config' => ['adjustment_type' => 'percentage', 'adjustment_value' => -10],
        ]);

        $refMethod = new \ReflectionMethod($this->service, 'applyTreatmentPrice');
        $refMethod->setAccessible(true);

        // 10% discount on $100
        $price = $refMethod->invoke($this->service, $experiment, 100.00);
        $this->assertEquals(90.00, $price);

        // Fixed adjustment
        $experiment2 = PricingExperiment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'treatment_config' => ['adjustment_type' => 'fixed', 'adjustment_value' => -20],
        ]);
        $price2 = $refMethod->invoke($this->service, $experiment2, 100.00);
        $this->assertEquals(80.00, $price2);

        // Override price
        $experiment3 = PricingExperiment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'treatment_config' => ['adjustment_type' => 'override', 'override_price' => 150],
        ]);
        $price3 = $refMethod->invoke($this->service, $experiment3, 100.00);
        $this->assertEquals(150.00, $price3);
    }

    /** @test */
    public function experiment_status_lifecycle()
    {
        $experiment = PricingExperiment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'draft',
        ]);

        $this->assertEquals('draft', $experiment->status);

        // Start
        $experiment->update(['status' => 'running', 'starts_at' => now()]);
        $this->assertEquals('running', $experiment->refresh()->status);

        // Pause
        $experiment->update(['status' => 'paused']);
        $this->assertEquals('paused', $experiment->refresh()->status);

        // Resume
        $experiment->update(['status' => 'running']);
        $this->assertEquals('running', $experiment->refresh()->status);

        // Complete
        $experiment->update(['status' => 'completed', 'ends_at' => now()]);
        $this->assertEquals('completed', $experiment->refresh()->status);
    }

    /** @test */
    public function participant_revenue_impact_tracks_correctly()
    {
        $experiment = PricingExperiment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'running',
            'traffic_split' => 50,
        ]);

        $participant = $this->service->assignToExperiment(
            $experiment,
            $this->customer->id,
            null,
            100.00
        );

        // Calculate revenue impact
        if ($participant->group === 'treatment' && $participant->experiment_price) {
            $expectedImpact = $participant->experiment_price - $participant->original_price;
            $this->assertEquals($expectedImpact, $participant->revenue_impact);
        } else {
            $this->assertEquals(0, $participant->revenue_impact);
        }
    }

    // ═══════════════ M3-26 增强测试 ═══════════════

    /** @test */
    public function auto_assigns_customer_to_matching_experiment_by_segment()
    {
        // 创建一个带区域筛选的实验
        $experiment = PricingExperiment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'running',
            'traffic_split' => 50,
            'segment_filters' => ['region' => ['china']],
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(30),
        ]);

        // 客户在中国区域
        $this->customer->region = 'china';
        $this->customer->save();

        $assigned = $this->service->autoAssignCustomerToExperiments($this->customer);

        $this->assertCount(1, $assigned);
        $this->assertEquals($experiment->id, $assigned[0]->experiment_id);
    }

    /** @test */
    public function does_not_assign_customer_to_non_matching_experiment()
    {
        PricingExperiment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'running',
            'traffic_split' => 50,
            'segment_filters' => ['region' => ['europe']],
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(30),
        ]);

        // 客户在亚洲，不匹配欧洲区域
        $this->customer->region = 'asia';
        $this->customer->save();

        $assigned = $this->service->autoAssignCustomerToExperiments($this->customer);

        $this->assertCount(0, $assigned);
    }

    /** @test */
    public function does_not_reassign_existing_participant()
    {
        $experiment = PricingExperiment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'running',
            'traffic_split' => 50,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(30),
        ]);

        // 客户已被分配
        $this->service->assignToExperiment($experiment, $this->customer->id);

        $assigned = $this->service->autoAssignCustomerToExperiments($this->customer);

        $this->assertCount(0, $assigned);
    }

    /** @test */
    public function matches_customer_by_channel_segment()
    {
        PricingExperiment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'running',
            'traffic_split' => 50,
            'segment_filters' => ['channel' => ['seo', 'direct']],
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(30),
        ]);

        $this->customer->channel = 'seo';
        $this->customer->save();

        $assigned = $this->service->autoAssignCustomerToExperiments($this->customer);

        $this->assertCount(1, $assigned);
    }

    /** @test */
    public function applies_winning_treatment_on_completed_experiment()
    {
        $experiment = PricingExperiment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'completed',
            'traffic_split' => 50,
            'treatment_config' => ['adjustment_type' => 'percentage', 'adjustment_value' => -15],
            'control_config' => ['price' => 100],
        ]);

        // 模拟实验有显著结果
        $experiment->update(['results' => [
            'control' => ['count' => 100, 'converted' => 10, 'conversion_rate' => 10.00, 'avg_revenue' => 100.00, 'churned' => 5, 'churn_rate' => 5.00],
            'treatment' => ['count' => 100, 'converted' => 20, 'conversion_rate' => 20.00, 'avg_revenue' => 85.00, 'churned' => 3, 'churn_rate' => 3.00],
            'improvement' => ['conversion_rate' => 10.00, 'avg_revenue' => -15.00, 'churn_rate' => -2.00],
            'significance' => ['z_score' => 2.5, 'p_value' => 0.0124, 'significant' => true],
            'calculated_at' => now()->toIso8601String(),
        ]]);

        $recommendation = $this->service->applyWinningTreatment($experiment);

        $this->assertNotNull($recommendation['winning_config']);
        $this->assertTrue($recommendation['is_significant']);
        $this->assertStringContainsString('显著', $recommendation['reason']);
    }

    /** @test */
    public function generates_recommendations_from_completed_experiments()
    {
        // 创建一些已完成的实验
        PricingExperiment::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'completed',
        ]);

        $result = $this->service->generateExperimentRecommendations($this->tenant->id);

        $this->assertArrayHasKey('recommendations', $result);
        $this->assertArrayHasKey('total_analyzed', $result);
        $this->assertEquals(3, $result['total_analyzed']);
    }

    /** @test */
    public function customer_matches_experiment_by_customer_tier()
    {
        PricingExperiment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'running',
            'traffic_split' => 50,
            'segment_filters' => ['customer_tier' => ['pro', 'enterprise']],
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(30),
        ]);

        $this->customer->level = 'pro';
        $this->customer->save();

        $assigned = $this->service->autoAssignCustomerToExperiments($this->customer);

        $this->assertCount(1, $assigned);
    }

    /** @test */
    public function customer_does_not_match_wrong_tier()
    {
        PricingExperiment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'running',
            'traffic_split' => 50,
            'segment_filters' => ['customer_tier' => ['enterprise']],
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(30),
        ]);

        $this->customer->level = 'free';
        $this->customer->save();

        $assigned = $this->service->autoAssignCustomerToExperiments($this->customer);

        $this->assertCount(0, $assigned);
    }
}
