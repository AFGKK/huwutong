<?php

namespace Tests\Unit\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\InvoiceReconciliation;
use App\Models\InvoiceSplit;
use App\Models\InvoiceTemplate;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\InvoiceEnhancementService;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class InvoiceEnhancementServiceTest extends TestCase
{
    use RefreshDatabase;

    protected InvoiceEnhancementService $service;
    protected Tenant $tenant;
    protected Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(InvoiceEnhancementService::class);
        $this->tenant = Tenant::factory()->create();

        $this->invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'invoice_no' => 'INV-2026-00001',
            'amount' => 1000,
            'subtotal' => 1000,
            'status' => 'pending',
            'paid' => false,
        ]);
    }

    // ═══ 发票模板 ═══

    /** @test */
    public function it_creates_invoice_template()
    {
        $template = $this->service->createTemplate([
            'tenant_id' => $this->tenant->id,
            'name' => '标准发票模板',
            'code' => 'standard_inv',
            'color_scheme' => 'blue',
            'is_active' => true,
        ]);

        $this->assertInstanceOf(InvoiceTemplate::class, $template);
        $this->assertEquals('标准发票模板', $template->name);
        $this->assertEquals('standard_inv', $template->code);
    }

    /** @test */
    public function it_only_has_one_default_template()
    {
        $this->service->createTemplate([
            'tenant_id' => $this->tenant->id,
            'name' => 'Template A',
            'code' => 'tpl_a',
            'is_default' => true,
        ]);

        $tplB = $this->service->createTemplate([
            'tenant_id' => $this->tenant->id,
            'name' => 'Template B',
            'code' => 'tpl_b',
            'is_default' => true,
        ]);

        $this->assertTrue($tplB->is_default);

        $tplA = InvoiceTemplate::where('code', 'tpl_a')->first();
        $this->assertFalse($tplA->is_default);
    }

    /** @test */
    public function it_lists_templates()
    {
        InvoiceTemplate::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $templates = $this->service->listTemplates($this->tenant->id);

        $this->assertCount(3, $templates);
    }

    /** @test */
    public function it_updates_template()
    {
        $template = InvoiceTemplate::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => '旧模板',
        ]);

        $updated = $this->service->updateTemplate($template, ['name' => '新模板', 'is_default' => true]);

        $this->assertEquals('新模板', $updated->name);
        $this->assertTrue($updated->is_default);
    }

    /** @test */
    public function it_deletes_template()
    {
        $template = InvoiceTemplate::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->service->deleteTemplate($template);

        $this->assertDatabaseMissing('invoice_templates', ['id' => $template->id]);
    }

    /** @test */
    public function it_gets_default_template()
    {
        InvoiceTemplate::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'default_tpl',
            'is_default' => true,
            'is_active' => true,
        ]);

        $default = $this->service->getDefaultTemplate($this->tenant->id);

        $this->assertNotNull($default);
        $this->assertEquals('default_tpl', $default->code);
    }

    // ═══ 账单对账 ═══

    /** @test */
    public function it_creates_reconciliation_with_match()
    {
        $rec = $this->service->createReconciliation([
            'tenant_id' => $this->tenant->id,
            'invoice_id' => $this->invoice->id,
            'invoice_amount' => 1000,
            'actual_amount' => 1000,
            'reconciliation_type' => 'manual',
        ]);

        $this->assertEquals('matched', $rec->status);
        $this->assertEquals(0, (int) $rec->difference);
        $this->assertNotNull($rec->matched_at);
    }

    /** @test */
    public function it_creates_reconciliation_with_mismatch()
    {
        $rec = $this->service->createReconciliation([
            'tenant_id' => $this->tenant->id,
            'invoice_id' => $this->invoice->id,
            'invoice_amount' => 1000,
            'actual_amount' => 950,
            'reconciliation_type' => 'manual',
        ]);

        $this->assertEquals('unmatched', $rec->status);
        $this->assertEquals(-50, (int) $rec->difference);
    }

    /** @test */
    public function it_lists_reconciliations()
    {
        InvoiceReconciliation::factory()->count(5)->create(['tenant_id' => $this->tenant->id]);

        $result = $this->service->listReconciliations($this->tenant->id);

        $this->assertEquals(5, $result->total());
    }

    /** @test */
    public function it_resolves_reconciliation()
    {
        $rec = InvoiceReconciliation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'unmatched',
            'difference' => -50,
        ]);

        $resolved = $this->service->resolveReconciliation($rec->id, 'manual', '银行手续费差异');

        $this->assertEquals('resolved', $resolved->status);
        $this->assertNotNull($resolved->resolved_at);
        $this->assertStringContainsString('银行手续费差异', $resolved->notes);
    }

    /** @test */
    public function it_returns_reconciliation_stats()
    {
        InvoiceReconciliation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'matched',
            'difference' => 0,
        ]);
        InvoiceReconciliation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'unmatched',
            'difference' => -100,
        ]);
        InvoiceReconciliation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
            'difference' => 50,
        ]);

        $stats = $this->service->getReconciliationStats($this->tenant->id);

        $this->assertEquals(3, $stats['total_count']);
        $this->assertEquals(1, $stats['pending_count']);
        $this->assertEquals(1, $stats['unmatched_count']);
        // pending(50) + unmatched(100) = 150
        $this->assertEquals(150, $stats['total_difference']);
    }

    // ═══ 账单拆分 ═══

    /** @test */
    public function it_splits_invoice()
    {
        InvoiceLineItem::factory()->create([
            'invoice_id' => $this->invoice->id,
            'tenant_id' => $this->tenant->id,
            'amount' => 1000,
            'type' => 'subscription',
            'description' => '月度订阅',
        ]);

        $result = $this->service->splitInvoice($this->tenant->id, $this->invoice->id, 300, '部分服务拆分');

        $this->assertArrayHasKey('split', $result);
        $this->assertArrayHasKey('split_invoice', $result);

        // Original invoice should have 700 remaining
        $original = $result['original'];
        $this->assertEquals(700, (int) $original->amount);

        // New invoice should have 300
        $splitInvoice = $result['split_invoice'];
        $this->assertEquals(300, (int) $splitInvoice->amount);
        $this->assertEquals('INV-2026-00001-S1', $splitInvoice->invoice_no);
    }

    /** @test */
    public function it_rejects_invalid_split_amount()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('拆分金额无效');

        $this->service->splitInvoice($this->tenant->id, $this->invoice->id, -100);
    }

    /** @test */
    public function it_rejects_split_exceeding_amount()
    {
        $this->expectException(\RuntimeException::class);

        $this->service->splitInvoice($this->tenant->id, $this->invoice->id, 2000);
    }

    /** @test */
    public function it_lists_splits()
    {
        // Create the second invoice for the split target
        $splitTarget = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'invoice_no' => 'INV-2026-00001-S1',
            'amount' => 300,
        ]);

        InvoiceSplit::factory()->create([
            'tenant_id' => $this->tenant->id,
            'original_invoice_id' => $this->invoice->id,
            'split_invoice_id' => $splitTarget->id,
            'amount' => 300,
        ]);

        $result = $this->service->listSplits($this->tenant->id);

        $this->assertEquals(1, $result->total());
    }

    // ═══ 自动对账 ═══

    /** @test */
    public function it_auto_reconciles_paid_invoices()
    {
        // Create paid invoices without reconciliation
        Invoice::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'paid' => true,
            'amount' => 500,
            'gateway_charge_id' => 'ch_test_' . uniqid(),
            'paid_at' => now(),
            'status' => 'paid',
        ]);

        $result = $this->service->autoReconcile($this->tenant->id);

        $this->assertGreaterThanOrEqual(3, $result['processed']);
        $this->assertEquals(0, $result['errors']);
    }

    // ═══ 增强统计 ═══

    /** @test */
    public function it_returns_enhanced_stats()
    {
        Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'amount' => 500,
            'paid' => true,
            'created_at' => now(),
        ]);

        InvoiceReconciliation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'unmatched',
            'difference' => -50,
        ]);

        $stats = $this->service->getEnhancedStats($this->tenant->id);

        $this->assertArrayHasKey('pending_reconciliations', $stats);
        $this->assertArrayHasKey('monthly_invoice_count', $stats);
        $this->assertArrayHasKey('monthly_invoice_total', $stats);
    }
}
