<?php

namespace Tests\Unit\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\License;
use App\Models\Refund;
use App\Models\RefundRiskRule;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RefundEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefundEngineServiceTest extends TestCase
{
    use RefreshDatabase;

    protected RefundEngineService $engine;
    protected Refund $refund;
    protected Refund $oldRefund;

    protected function setUp(): void
    {
        parent::setUp();

        if (!Tenant::find(1)) {
            Tenant::factory()->create(['id' => 1]);
        }

        $this->engine = app(RefundEngineService::class);

        $customer = Customer::create([
            'tenant_id' => 1,
            'name' => 'Test Customer',
            'email' => 'test@example.com',
        ]);

        $license = License::create([
            'license_key' => 'HWT-TEST-REFUND-' . strtoupper(substr(md5(uniqid()), 0, 10)),
            'customer_id' => $customer->id,
            'tenant_id' => 1,
            'type' => 'standard',
            'status' => 'active',
            'seats' => 5,
            'max_devices' => 3,
            'expires_at' => now()->addYear(),
        ]);

        // Create a paid invoice for time_window evaluation
        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'tenant_id' => 1,
            'invoice_no' => 'INV-TEST-' . uniqid(),
            'amount' => 500.00,
            'currency' => 'CNY',
            'status' => 'paid',
            'paid' => true,
            'paid_at' => now()->subDays(3),
            'billing_reason' => 'purchase',
        ]);

        $this->refund = Refund::create([
            'tenant_id' => 1,
            'license_id' => $license->id,
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'processed_by' => User::factory()->create()->id,
            'refund_no' => 'RF-TEST-' . uniqid(),
            'amount' => 100.00,
            'currency' => 'CNY',
            'reason' => '不满产品',
            'refund_type' => 'full',
            'status' => 'pending',
        ]);

        // Create an old refund for frequency testing
        $oldInvoice = Invoice::create([
            'customer_id' => $customer->id,
            'tenant_id' => 1,
            'invoice_no' => 'INV-OLD-' . uniqid(),
            'amount' => 1000.00,
            'currency' => 'CNY',
            'status' => 'paid',
            'paid' => true,
            'paid_at' => now()->subDays(90),
            'billing_reason' => 'purchase',
        ]);

        $this->oldRefund = Refund::create([
            'tenant_id' => 1,
            'license_id' => $license->id,
            'invoice_id' => $oldInvoice->id,
            'customer_id' => $customer->id,
            'processed_by' => User::factory()->create()->id,
            'refund_no' => 'RF-OLD-' . uniqid(),
            'amount' => 200.00,
            'currency' => 'CNY',
            'reason' => '功能不符',
            'refund_type' => 'full',
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    /** @test */
    public function assesses_low_risk_for_recent_purchase()
    {
        // Purchase was 3 days ago + $100 of $500 invoice = low risk
        $assessment = $this->engine->assess($this->refund);

        $this->assertNotNull($assessment);
        $this->assertLessThanOrEqual(30, $assessment->risk_score);
        $this->assertEquals('low', $assessment->risk_level);
        $this->assertEquals('auto_approve', $assessment->decision);
        $this->assertNotNull($assessment->factors);
    }

    /** @test */
    public function auto_approves_low_risk_refund()
    {
        $assessment = $this->engine->assess($this->refund);

        $result = $this->engine->executeDecision($this->refund);

        $this->assertTrue($result['executed']);
        $this->assertEquals('approved', $result['action']);

        $this->refund->refresh();
        $this->assertEquals('completed', $this->refund->status);
        $this->assertNotNull($this->refund->completed_at);
    }

    /** @test */
    public function creates_assessment_with_factors()
    {
        $assessment = $this->engine->assess($this->refund);

        $this->assertNotEmpty($assessment->factors);
        $this->assertIsArray($assessment->factors);
        $this->assertCount(6, $assessment->factors);
    }

    /** @test */
    public function links_assessment_to_refund()
    {
        $assessment = $this->engine->assess($this->refund);

        $this->refund->refresh();
        $this->assertEquals($assessment->id, $this->refund->risk_assessment_id);
        $this->assertNotNull($this->refund->auto_decision);
    }

    /** @test */
    public function require_human_review_for_high_amount()
    {
        $this->engine->seedDefaultRules();

        // Create invoice with large amount so the amount_vs_invoice rule doesn't kick in
        $bigInvoice = Invoice::create([
            'customer_id' => $this->refund->customer_id,
            'tenant_id' => 1,
            'invoice_no' => 'INV-BIG-' . uniqid(),
            'amount' => 10000.00,
            'currency' => 'CNY',
            'status' => 'paid',
            'paid' => true,
            'paid_at' => now()->subDays(15),
            'billing_reason' => 'purchase',
        ]);

        $refund = Refund::create([
            'tenant_id' => 1,
            'license_id' => $this->refund->license_id,
            'invoice_id' => $bigInvoice->id,
            'customer_id' => $this->refund->customer_id,
            'processed_by' => $this->refund->processed_by,
            'refund_no' => 'RF-BIG-' . uniqid(),
            'amount' => 6000.00,
            'currency' => 'CNY',
            'reason' => '大额退款',
            'refund_type' => 'full',
            'status' => 'pending',
        ]);

        $assessment = $this->engine->assess($refund);

        $this->assertEquals('require_review', $assessment->decision);
        $this->assertEquals('pending', $assessment->review_status);
    }

    /** @test */
    public function auto_rejects_amount_exceeding_invoice()
    {
        $this->engine->seedDefaultRules();

        $refund = Refund::create([
            'tenant_id' => 1,
            'license_id' => $this->refund->license_id,
            'invoice_id' => $this->refund->invoice_id,
            'processed_by' => $this->refund->processed_by,
            'refund_no' => 'RF-EXCEED-' . uniqid(),
            'amount' => 9999.00,
            'currency' => 'CNY',
            'reason' => '超额退款',
            'refund_type' => 'full',
            'status' => 'pending',
            'customer_id' => $this->refund->customer_id,
        ]);

        $assessment = $this->engine->assess($refund);
        $this->assertEquals('auto_reject', $assessment->decision);
    }

    /** @test */
    public function review_approves_refund()
    {
        // First assess to create an assessment
        $this->engine->assess($this->refund);

        // Now review with manual approval
        $user = User::factory()->create();
        $assessment = $this->engine->review($this->refund, 'approve', $user->id, '审核通过');

        $this->assertEquals('approved', $assessment->review_status);
        $this->assertEquals($user->id, $assessment->reviewed_by);
        $this->assertNotNull($assessment->reviewed_at);

        $this->refund->refresh();
        $this->assertEquals('completed', $this->refund->status);
    }

    /** @test */
    public function review_rejects_refund()
    {
        $this->engine->assess($this->refund);

        $user = User::factory()->create();
        $assessment = $this->engine->review($this->refund, 'reject', $user->id, '风险过高');

        $this->assertEquals('rejected', $assessment->review_status);

        $this->refund->refresh();
        $this->assertEquals('failed', $this->refund->status);
        $this->assertNotNull($this->refund->failure_reason);
    }

    /** @test */
    public function seeds_default_rules()
    {
        $this->engine->seedDefaultRules();
        $rules = RefundRiskRule::all();

        $this->assertGreaterThanOrEqual(8, $rules->count());

        // Check specific rules exist
        $this->assertNotNull(RefundRiskRule::where('name', '购买7天内退款自动批准')->first());
        $this->assertNotNull(RefundRiskRule::where('name', '大额退款需人工审核')->first());
    }

    /** @test */
    public function get_risk_stats()
    {
        $this->engine->assess($this->refund);
        $stats = $this->engine->getRiskStats();

        $this->assertArrayHasKey('total_assessments', $stats);
        $this->assertArrayHasKey('by_risk_level', $stats);
        $this->assertArrayHasKey('by_decision', $stats);
        $this->assertGreaterThanOrEqual(1, $stats['total_assessments']);
    }

    /** @test */
    public function assess_creates_matched_rules()
    {
        // Seed rules first
        $this->engine->seedDefaultRules();

        $assessment = $this->engine->assess($this->refund);

        $this->assertNotEmpty($assessment->matched_rules);
        // Should match "购买7天内退款自动批准" since purchase was 3 days ago
        $ruleNames = array_column($assessment->matched_rules, 'rule_name');
        $this->assertContains('购买7天内退款自动批准', $ruleNames);
    }
}
