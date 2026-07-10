<?php

namespace Tests\Unit\Services;

use App\Models\CrossBorderMonthlyReport;
use App\Models\CrossBorderPayment;
use App\Models\CurrencyConversionLog;
use App\Models\Tenant;
use App\Services\CrossBorderService;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class CrossBorderServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CrossBorderService $service;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CrossBorderService::class);
        $this->tenant = Tenant::factory()->create();
    }

    // ═══ 货币转换审计 ═══

    /** @test */
    public function it_logs_conversion()
    {
        $log = $this->service->logConversion(
            $this->tenant->id, 'USD', 'CNY', 100, 720, 7.2
        );

        $this->assertEquals('USD', $log->from_currency);
        $this->assertEquals('CNY', $log->to_currency);
        $this->assertEquals(720, $log->to_amount);
        $this->assertEquals(7.2, $log->rate_used);
    }

    /** @test */
    public function it_logs_conversion_with_extra_fields()
    {
        $log = $this->service->logConversion(
            $this->tenant->id, 'USD', 'CNY', 100, 720, 7.2,
            ['source' => 'invoice', 'conversion_type' => 'checkout', 'rate_markup' => 0.02]
        );

        $this->assertEquals('invoice', $log->source);
        $this->assertEquals('checkout', $log->conversion_type);
        $this->assertEquals(0.02, $log->rate_markup);
    }

    /** @test */
    public function it_returns_conversion_logs()
    {
        CurrencyConversionLog::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $result = $this->service->getConversionLogs($this->tenant->id);

        $this->assertGreaterThanOrEqual(3, $result['total']);
    }

    // ═══ 跨境支付 ═══

    /** @test */
    public function it_records_cross_border_payment()
    {
        $payment = $this->service->recordCrossBorderPayment($this->tenant->id, [
            'currency' => 'USD',
            'amount' => 500,
            'amount_cny' => 3600,
            'exchange_rate' => 7.2,
            'payment_gateway' => 'stripe',
            'customer_country' => 'US',
        ]);

        $this->assertEquals('USD', $payment->currency);
        $this->assertEquals(500, $payment->amount);
        $this->assertEquals(3600, $payment->amount_cny);
        $this->assertNotNull($payment->compliance_info);
    }

    /** @test */
    public function it_performs_compliance_check_on_payment()
    {
        $payment = $this->service->recordCrossBorderPayment($this->tenant->id, [
            'currency' => 'USD',
            'amount' => 200000, // > 100000 CNY equivalent
            'amount_cny' => 1440000,
            'exchange_rate' => 7.2,
            'payment_gateway' => 'stripe',
            'customer_country' => 'US',
        ]);

        $compliance = $payment->compliance_info;
        $this->assertFalse($compliance['passed']); // large amount warning
        $this->assertNotEmpty($compliance['checks']);
    }

    /** @test */
    public function it_returns_payments()
    {
        CrossBorderPayment::factory()->count(5)->create(['tenant_id' => $this->tenant->id]);

        $result = $this->service->getCrossBorderPayments($this->tenant->id);

        $this->assertGreaterThanOrEqual(5, $result['total']);
    }

    // ═══ 合规检查 ═══

    /** @test */
    public function it_checks_large_amount_compliance()
    {
        $result = $this->service->performComplianceCheck([
            'currency' => 'USD',
            'amount_cny' => 150000,
            'customer_country' => 'US',
        ]);

        $this->assertFalse($result['passed']);
        $warningTypes = array_column($result['checks'], 'type');
        $this->assertContains('large_amount', $warningTypes);
    }

    /** @test */
    public function it_passes_compliance_for_small_normal_payment()
    {
        $result = $this->service->performComplianceCheck([
            'currency' => 'USD',
            'amount_cny' => 5000,
            'customer_country' => 'US',
        ]);

        $this->assertTrue($result['passed']);
    }

    /** @test */
    public function it_warns_on_cross_border_refund()
    {
        $result = $this->service->performComplianceCheck([
            'currency' => 'USD',
            'amount_cny' => 60000,
            'customer_country' => 'US',
            'transaction_type' => 'refund',
        ]);

        $this->assertFalse($result['passed']);
        $warningTypes = array_column($result['checks'], 'type');
        $this->assertContains('cross_border_refund', $warningTypes);
    }

    // ═══ 月度报表 ═══

    /** @test */
    public function it_generates_monthly_report()
    {
        CrossBorderPayment::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'currency' => 'USD',
            'status' => 'completed',
            'transaction_type' => 'payment',
        ]);
        CrossBorderPayment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'currency' => 'EUR',
            'status' => 'completed',
            'transaction_type' => 'payment',
        ]);

        $thisMonth = now()->format('Y-m');
        $this->service->generateMonthlyReport($this->tenant->id, $thisMonth);

        $reports = CrossBorderMonthlyReport::where('tenant_id', $this->tenant->id)->get();
        $this->assertGreaterThanOrEqual(2, $reports->count());

        $usdReport = $reports->firstWhere('currency', 'USD');
        $this->assertNotNull($usdReport);
        $this->assertGreaterThan(0, $usdReport->total_revenue);
    }

    /** @test */
    public function it_returns_monthly_reports()
    {
        CrossBorderMonthlyReport::create([
            'tenant_id' => $this->tenant->id,
            'report_month' => now()->format('Y-m'),
            'currency' => 'USD',
            'total_revenue' => 5000,
            'total_revenue_cny' => 36000,
        ]);

        $reports = $this->service->getMonthlyReports($this->tenant->id);
        $this->assertCount(1, $reports);
    }

    // ═══ 仪表盘 ═══

    /** @test */
    public function it_returns_dashboard_stats()
    {
        CrossBorderPayment::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'completed',
        ]);
        CurrencyConversionLog::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);

        $stats = $this->service->getDashboardStats($this->tenant->id);

        $this->assertGreaterThanOrEqual(0, $stats['monthly_revenue_cny']);
        $this->assertGreaterThanOrEqual(0, $stats['monthly_transactions']);
        $this->assertGreaterThanOrEqual(1, $stats['active_currencies']);
    }
}
