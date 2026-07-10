<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\TaxComplianceDocument;
use App\Models\TaxComplianceReport;
use App\Models\TaxComplianceRule;
use App\Models\TaxRate;
use App\Models\Tenant;
use App\Services\TaxComplianceService;
use App\Services\TaxCalculatorService;
use Tests\Concerns\RefreshDatabase;

class TaxComplianceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TaxComplianceService $service;
    protected TaxCalculatorService $calculator;
    protected Tenant $tenant;
    protected Customer $customer;
    protected TaxRate $taxRate;
    protected \App\Models\User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(TaxComplianceService::class);
        $this->calculator = $this->app->make(TaxCalculatorService::class);
        $this->tenant = Tenant::factory()->create();
        $this->user = \App\Models\User::factory()->create();
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        // 创建税率
        $this->taxRate = TaxRate::create([
            'country_code' => 'CN',
            'name' => 'Chinese VAT',
            'rate' => 0.1300,
            'type' => 'vat',
            'is_active' => true,
        ]);
    }

    // ─── TaxRate 补充方法 ───

    public function test_find_rate_returns_correct_rate()
    {
        $found = TaxRate::findRate('CN');
        $this->assertNotNull($found);
        $this->assertEquals(0.1300, (float) $found->rate);
    }

    public function test_find_rate_returns_null_for_missing_country()
    {
        $found = TaxRate::findRate('ZZ');
        $this->assertNull($found);
    }

    public function test_get_eu_countries()
    {
        // 创建 EU 国家税率
        TaxRate::create([
            'country_code' => 'DE',
            'name' => 'German VAT',
            'rate' => 0.1900,
            'type' => 'vat',
            'is_eu' => true,
            'is_active' => true,
        ]);
        TaxRate::create([
            'country_code' => 'FR',
            'name' => 'French VAT',
            'rate' => 0.2000,
            'type' => 'vat',
            'is_eu' => true,
            'is_active' => true,
        ]);

        $euCountries = TaxRate::getEuCountries();
        $this->assertContains('DE', $euCountries);
        $this->assertContains('FR', $euCountries);
    }

    // ─── 税务计算器 ───

    public function test_calculate_tax()
    {
        $result = $this->calculator->calculate(100, 'CN');
        $this->assertEquals(100, $result['taxable_amount']);
        $this->assertEquals(13.00, $result['tax_amount']);
        $this->assertEquals(113.00, $result['total']);
        $this->assertEquals('vat', $result['tax_type']);
    }

    public function test_calculate_tax_for_nonexistent_country_returns_no_tax()
    {
        $result = $this->calculator->calculate(100, 'ZZ');
        $this->assertEquals(0, $result['tax_amount']);
        $this->assertEquals('none', $result['tax_type']);
    }

    public function test_calculate_with_exemption()
    {
        $result = $this->calculator->calculate(100, 'CN', ['tenant_id' => $this->tenant->id]);
        // No exemption cert exists, so tax should apply
        $this->assertEquals(13.00, $result['tax_amount']);
    }

    // ─── 报告生成 ───

    public function test_generate_report()
    {
        // 创建一张发票
        $invoice = Invoice::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'invoice_no' => 'INV-TEST-001',
            'amount' => 100,
            'subtotal' => 100,
            'tax_amount' => 13,
            'tax_rate_applied' => 0.13,
            'tax_type' => 'vat',
            'billing_country' => 'CN',
            'status' => 'paid',
        ]);
        $invoice->forceFill(['created_at' => '2026-06-15 10:00:00', 'updated_at' => '2026-06-15 10:00:00'])->saveQuietly();

        $report = $this->service->generateReport($this->tenant->id, 'CN', '2026-06', 'vat_return');

        $this->assertEquals('CN', $report->country);
        $this->assertEquals('2026-06', $report->period);
        $this->assertEquals('draft', $report->status);
        $this->assertEquals(100, (float) $report->total_sales);
        $this->assertEquals(13, (float) $report->total_tax_payable);
    }

    public function test_generate_report_updates_existing()
    {
        TaxComplianceReport::create([
            'tenant_id' => $this->tenant->id,
            'country' => 'CN',
            'period' => '2026-06',
            'report_type' => 'vat_return',
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'total_sales' => 0,
            'total_tax_collected' => 0,
            'total_tax_payable' => 0,
        ]);

        // With invoice
        $invoice = Invoice::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'invoice_no' => 'INV-TEST-002',
            'amount' => 200,
            'subtotal' => 200,
            'tax_amount' => 26,
            'billing_country' => 'CN',
            'status' => 'paid',
        ]);
        $invoice->forceFill(['created_at' => '2026-06-20 10:00:00', 'updated_at' => '2026-06-20 10:00:00'])->saveQuietly();

        $report = $this->service->generateReport($this->tenant->id, 'CN', '2026-06', 'vat_return');
        $this->assertEquals(200, (float) $report->total_sales);
    }

    // ─── 报告管理 ───

    public function test_list_reports()
    {
        TaxComplianceReport::factory()->create(['tenant_id' => $this->tenant->id]);

        $result = $this->service->listReports($this->tenant->id);
        $this->assertCount(1, $result['data']);
    }

    public function test_file_report()
    {
        $report = TaxComplianceReport::factory()->create(['tenant_id' => $this->tenant->id]);

        $filed = $this->service->fileReport($this->tenant->id, $report->id);
        $this->assertEquals('filed', $filed->status);
        $this->assertNotNull($filed->filed_at);
    }

    // ─── 文档管理 ───

    public function test_create_document()
    {
        $doc = $this->service->createDocument($this->tenant->id, $this->user->id, [
            'document_type' => 'correspondence',
            'country' => 'CN',
            'title' => '税局询问函',
            'document_date' => now()->toDateString(),
        ]);

        $this->assertEquals('税局询问函', $doc->title);
        $this->assertEquals('pending', $doc->status);
    }

    public function test_list_documents()
    {
        $this->service->createDocument($this->tenant->id, $this->user->id, [
            'document_type' => 'tax_return',
            'country' => 'CN',
            'title' => '申报表',
            'document_date' => now()->toDateString(),
        ]);

        $result = $this->service->listDocuments($this->tenant->id);
        $this->assertCount(1, $result['data']);
    }

    public function test_update_document()
    {
        $doc = $this->service->createDocument($this->tenant->id, $this->user->id, [
            'document_type' => 'certificate',
            'country' => 'CN',
            'title' => '税务证明',
            'document_date' => now()->toDateString(),
        ]);

        $updated = $this->service->updateDocument($this->tenant->id, $doc->id, ['status' => 'completed']);
        $this->assertEquals('completed', $updated->status);
    }

    public function test_delete_document()
    {
        $doc = $this->service->createDocument($this->tenant->id, $this->user->id, [
            'document_type' => 'audit_letter',
            'country' => 'CN',
            'title' => '审计函',
            'document_date' => now()->toDateString(),
        ]);

        $this->service->deleteDocument($this->tenant->id, $doc->id);
        $this->assertNull(TaxComplianceDocument::find($doc->id));
    }

    // ─── 规则管理 ───

    public function test_create_rule()
    {
        $rule = $this->service->createRule($this->tenant->id, [
            'name' => '教育类减免',
            'rule_type' => 'reduced_rate',
            'action' => 'reduce_rate',
            'rate_modifier' => 0.50,
        ]);

        $this->assertEquals('教育类减免', $rule->name);
        $this->assertTrue($rule->is_active);
    }

    public function test_list_rules()
    {
        $this->service->createRule($this->tenant->id, [
            'name' => '测试规则', 'rule_type' => 'exemption', 'action' => 'exempt',
        ]);

        $result = $this->service->listRules($this->tenant->id);
        $this->assertCount(1, $result['data']);
    }

    public function test_delete_rule()
    {
        $rule = $this->service->createRule($this->tenant->id, [
            'name' => '待删除规则', 'rule_type' => 'threshold', 'action' => 'exempt',
        ]);

        $this->service->deleteRule($this->tenant->id, $rule->id);
        $this->assertNull(TaxComplianceRule::find($rule->id));
    }

    // ─── 仪表盘 ───

    public function test_get_dashboard()
    {
        // 创建1条报告
        TaxComplianceReport::factory()->create(['tenant_id' => $this->tenant->id]);
        // 创建1条规则
        $this->service->createRule($this->tenant->id, [
            'name' => '测试规则', 'rule_type' => 'exemption', 'action' => 'exempt',
        ]);

        $dashboard = $this->service->getDashboard($this->tenant->id);

        $this->assertArrayHasKey('pending_reports', $dashboard);
        $this->assertEquals(1, $dashboard['pending_reports']);
        $this->assertEquals(1, $dashboard['active_rules']);
    }
}
