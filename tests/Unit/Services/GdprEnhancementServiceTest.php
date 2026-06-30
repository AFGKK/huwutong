<?php

namespace Tests\Unit\Services;

use App\Models\AutomatedDecisionRecord;
use App\Models\DataBreachNotification;
use App\Models\DpiaRecord;
use App\Models\ProcessingActivityRecord;
use App\Models\SubProcessorAssessment;
use App\Models\User;
use App\Services\GdprEnhancementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GdprEnhancementServiceTest extends TestCase
{
    use RefreshDatabase;

    protected GdprEnhancementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GdprEnhancementService();

        // Create an admin user and authenticate
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);
        $this->actingAs($admin);
    }

    // ═══════════════ DPIA ═══════════════

    public function test_can_create_dpia()
    {
        $dpia = $this->service->createDpia([
            'title' => 'Customer Data Processing DPIA',
            'processing_type' => 'customer_data',
            'description' => 'Processing customer data for licensing',
            'data_categories' => ['name', 'email'],
            'data_subjects' => ['customers'],
            'processing_purposes' => ['license_management'],
            'controller_dpo' => 'dpo@example.com',
        ]);

        $this->assertInstanceOf(DpiaRecord::class, $dpia);
        $this->assertEquals('Customer Data Processing DPIA', $dpia->title);
        $this->assertNotNull($dpia->reference);
        $this->assertStringStartsWith('DPIA-', $dpia->reference);
        $this->assertEquals(['name', 'email'], $dpia->data_categories);

        // Reload from DB to verify default values
        $fresh = $dpia->fresh();
        $this->assertEquals('draft', $fresh->status);
    }

    public function test_can_list_dpias()
    {
        $this->service->createDpia(['title' => 'DPIA 1', 'processing_type' => 'payment']);
        $this->service->createDpia(['title' => 'DPIA 2', 'processing_type' => 'analytics']);

        $result = $this->service->listDpias();
        $this->assertEquals(2, $result->total());
    }

    public function test_can_filter_dpias_by_status()
    {
        $this->service->createDpia(['title' => 'Draft DPIA', 'processing_type' => 'payment']);

        $dpia = DpiaRecord::first();
        $dpia->update(['status' => 'approved', 'reviewed_by' => auth()->id(), 'reviewed_at' => now()]);

        $this->assertEquals(1, $this->service->listDpias(['status' => 'approved'])->total());
        $this->assertEquals(0, $this->service->listDpias(['status' => 'rejected'])->total());
    }

    public function test_can_review_dpia()
    {
        $dpia = $this->service->createDpia([
            'title' => 'DPIA for Review',
            'processing_type' => 'analytics',
        ]);
        $dpia->update(['status' => 'in_review']);

        $result = $this->service->reviewDpia($dpia->fresh(), 'approved', 'Looks good');

        $this->assertEquals('approved', $result->status);
        $this->assertEquals('Looks good', $result->review_notes);
        $this->assertNotNull($result->reviewed_at);
        $this->assertEquals(auth()->id(), $result->reviewed_by);
    }

    public function test_cannot_review_dpia_with_invalid_status()
    {
        $this->expectException(\RuntimeException::class);
        $dpia = $this->service->createDpia(['title' => 'Test', 'processing_type' => 'test']);
        $this->service->reviewDpia($dpia, 'invalid_status');
    }

    public function test_can_get_dpia_stats()
    {
        $this->service->createDpia(['title' => 'A', 'processing_type' => 'x']);
        $this->service->createDpia(['title' => 'B', 'processing_type' => 'x']);
        $dpia = DpiaRecord::first();
        $dpia->update(['status' => 'approved', 'reviewed_by' => 1, 'reviewed_at' => now()]);

        $stats = $this->service->getDpiaStats();
        $this->assertEquals(2, $stats['total']);
        $this->assertEquals(1, $stats['approved']);
        $this->assertArrayHasKey('draft', $stats);
    }

    // ═══════════════ 数据泄露通知 ═══════════════

    public function test_can_create_breach()
    {
        $breach = $this->service->createBreach([
            'severity' => 'high',
            'detected_at' => now()->toIso8601String(),
            'description' => 'Unauthorized access detected',
        ]);

        $this->assertInstanceOf(DataBreachNotification::class, $breach);
        $this->assertEquals('high', $breach->severity);
        $this->assertNotNull($breach->reference);
        $this->assertStringStartsWith('BR-', $breach->reference);

        $fresh = $breach->fresh();
        $this->assertEquals('detected', $fresh->status);
    }

    public function test_can_list_breaches()
    {
        $this->service->createBreach(['severity' => 'high', 'detected_at' => now(), 'description' => 'B1']);
        $this->service->createBreach(['severity' => 'low', 'detected_at' => now(), 'description' => 'B2']);

        $this->assertEquals(2, $this->service->listBreaches()->total());
        $this->assertEquals(1, $this->service->listBreaches(['severity' => 'high'])->total());
    }

    public function test_can_update_breach_status()
    {
        $breach = $this->service->createBreach(['severity' => 'critical', 'detected_at' => now(), 'description' => 'Test']);
        $this->service->updateBreach($breach, ['status' => 'assessing', 'root_cause' => 'SQL injection attempt']);

        $fresh = $breach->fresh();
        $this->assertEquals('assessing', $fresh->status);
        $this->assertEquals('SQL injection attempt', $fresh->root_cause);
    }

    public function test_can_get_breach_stats()
    {
        $this->service->createBreach(['severity' => 'critical', 'detected_at' => now(), 'description' => 'C1']);
        $this->service->createBreach(['severity' => 'low', 'detected_at' => now(), 'description' => 'L1']);

        $stats = $this->service->getBreachStats();
        $this->assertEquals(2, $stats['total']);
        $this->assertArrayHasKey('critical', $stats['by_severity']);
    }

    // ═══════════════ ROPA ═══════════════

    public function test_can_create_ropa()
    {
        $ropa = $this->service->createRopa([
            'controller_name' => '88 HuWuTong',
            'processing_type' => 'customer_management',
            'processing_description' => 'Customer account management',
            'processing_purposes' => ['account_creation', 'support'],
            'data_categories' => ['name', 'email', 'phone'],
            'data_subjects' => ['customers'],
        ]);

        $this->assertInstanceOf(ProcessingActivityRecord::class, $ropa);
        $this->assertEquals('88 HuWuTong', $ropa->controller_name);
        $this->assertNotNull($ropa->reference);
        $this->assertStringStartsWith('ROPA-', $ropa->reference);

        $fresh = $ropa->fresh();
        $this->assertEquals('active', $fresh->status);
    }

    public function test_can_list_ropas()
    {
        $this->service->createRopa(['controller_name' => 'CN Company', 'processing_type' => 'billing', 'processing_description' => 'Billing', 'processing_purposes' => [''], 'data_categories' => [''], 'data_subjects' => ['']]);
        $this->service->createRopa(['controller_name' => 'US Company', 'processing_type' => 'analytics', 'processing_description' => 'Analytics', 'processing_purposes' => [''], 'data_categories' => [''], 'data_subjects' => ['']]);

        $this->assertEquals(2, $this->service->listRopas()->total());
        $this->assertNotNull($this->service->getRopaStats());
    }

    // ═══════════════ 子处理商 ═══════════════

    public function test_can_create_sub_processor()
    {
        $sp = $this->service->createSubProcessor([
            'name' => 'AWS EU West',
            'jurisdiction' => 'EU',
            'processing_description' => 'Cloud infrastructure hosting',
            'certification' => 'ISO27001',
        ]);

        $this->assertInstanceOf(SubProcessorAssessment::class, $sp);
        $this->assertEquals('AWS EU West', $sp->name);
        $this->assertEquals('EU', $sp->jurisdiction);

        $fresh = $sp->fresh();
        $this->assertEquals('pending', $fresh->status);
    }

    public function test_can_update_sub_processor_status()
    {
        $sp = $this->service->createSubProcessor(['name' => 'Test SP', 'jurisdiction' => 'US', 'processing_description' => 'Testing']);
        $this->service->updateSubProcessor($sp, ['status' => 'approved', 'has_dpa_signed' => true]);

        $this->assertEquals('approved', $sp->fresh()->status);
        $this->assertTrue($sp->fresh()->has_dpa_signed);
    }

    // ═══════════════ 自动决策 ── ═══════════════

    public function test_can_create_automated_decision()
    {
        $ad = $this->service->createAutoDecision([
            'name' => 'License Activation Check',
            'type' => 'automated_decision',
            'description' => 'Automatically validates license keys',
            'input_data_categories' => ['license_key', 'domain'],
            'output_decision' => ['approved', 'rejected'],
            'human_intervention_possible' => true,
            'intervention_method' => 'Manual review dashboard',
        ]);

        $this->assertInstanceOf(AutomatedDecisionRecord::class, $ad);
        $this->assertEquals('License Activation Check', $ad->name);

        $fresh = $ad->fresh();
        $this->assertTrue($fresh->is_active);
        $this->assertTrue($fresh->human_intervention_possible);
    }

    public function test_can_create_profiling_record()
    {
        $ad = $this->service->createAutoDecision([
            'name' => 'Customer Segmentation',
            'type' => 'profiling',
            'description' => 'Groups customers by usage patterns',
            'input_data_categories' => ['usage_hours', 'features_used'],
            'output_decision' => ['segment_a', 'segment_b'],
        ]);

        $this->assertEquals('profiling', $ad->type);
    }

    public function test_can_list_and_update_auto_decisions()
    {
        $this->service->createAutoDecision(['name' => 'AD1', 'type' => 'automated_decision', 'description' => 'D1', 'input_data_categories' => [''], 'output_decision' => ['']]);
        $this->service->createAutoDecision(['name' => 'AD2', 'type' => 'profiling', 'description' => 'D2', 'input_data_categories' => [''], 'output_decision' => ['']]);

        $this->assertEquals(2, $this->service->listAutoDecisions()->total());
        $this->assertEquals(1, $this->service->listAutoDecisions(['type' => 'profiling'])->total());

        $ad = AutomatedDecisionRecord::where('type', 'automated_decision')->first();
        $this->service->updateAutoDecision($ad, ['is_active' => false]);
        $this->assertFalse($ad->fresh()->is_active);
    }

    // ═══════════════ 编号生成 ═══════════════

    public function test_reference_numbers_are_unique_and_sequential()
    {
        $dpia1 = $this->service->createDpia(['title' => 'A', 'processing_type' => 't']);
        $dpia2 = $this->service->createDpia(['title' => 'B', 'processing_type' => 't']);

        $this->assertNotEquals($dpia1->reference, $dpia2->reference);
        $this->assertLessThan($dpia2->reference, $dpia1->reference);
    }
}
