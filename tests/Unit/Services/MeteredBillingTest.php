<?php

namespace Tests\Unit\Services;

use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\License;
use App\Models\MeteredPrice;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\Customer;
use App\Models\UsageAggregate;
use App\Models\UsageRecord;
use App\Services\MeteredBillingService;
use App\Services\UsageMeterService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeteredBillingTest extends TestCase
{
    use RefreshDatabase;

    protected MeteredBillingService $service;
    protected Tenant $tenant;
    protected Subscription $subscription;
    protected License $license;
    protected Carbon $periodStart;
    protected Carbon $periodEnd;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $product = Product::factory()->create();
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->periodStart = Carbon::now()->startOfMonth();
        $this->periodEnd = Carbon::now();

        $this->subscription = Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'customer_id' => $customer->id,
            'status' => 'active',
            'price' => 99.00,
            'starts_at' => $this->periodStart,
            'last_billed_at' => $this->periodStart,
            'metered_config' => [
                'enabled' => true,
                'billing_period' => 'monthly',
                'cap_type' => 'soft',
                'monthly_cap' => null,
            ],
        ]);

        $this->license = License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'customer_id' => $customer->id,
            'subscription_id' => $this->subscription->id,
            'seats' => 5,
        ]);

        // 创建价格配置
        MeteredPrice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'metric_key' => 'api_call.validate',
            'name' => 'License 验证',
            'unit' => 'count',
            'billing_period' => 'monthly',
            'tiers' => [
                ['from' => 0, 'to' => 1000, 'unit_price' => 0.01],
                ['from' => 1001, 'to' => 10000, 'unit_price' => 0.005],
                ['from' => 10001, 'to' => null, 'unit_price' => 0.001],
            ],
            'base_fee' => 10.00,
            'included_quantity' => 500,
            'is_active' => true,
        ]);

        $this->service = new MeteredBillingService(
            app(UsageMeterService::class)
        );
    }

    /** @test */
    public function calculates_cost_with_tiers_and_base_fee()
    {
        $meteredPrice = MeteredPrice::where('tenant_id', $this->tenant->id)
            ->where('metric_key', 'api_call.validate')
            ->first();

        // 2000 次调用，包含500免费，计费1500次
        $result = $meteredPrice->calculateCost(2000);

        $this->assertEquals(2000, $result['total_quantity']);
        $this->assertEquals(1500, $result['billable_quantity']);
        $this->assertEquals(500, $result['included_quantity']);
        $this->assertEquals(10.00, $result['base_fee']);

        // 第1阶梯: 0~1000，但扣除500免费后，计费1500次
        // 第1阶梯范围 0~1000，覆盖1000次/单位
        // 计费量=1500，在第一阶梯用完1000的容量，剩余500到第二阶梯
        // 第一阶梯 1000 × 0.01 = 10
        // 第二阶梯 500 × 0.005 = 2.5
        // 总费用: 10(base) + 10 + 2.5 = 22.5
        $this->assertEquals(22.50, $result['total_cost']);
    }

    /** @test */
    public function generates_metered_invoice()
    {
        // 先创建一些用量聚合数据
        $this->createUsageData('api_call.validate', 2500);

        $result = $this->service->generateMeteredInvoice(
            $this->subscription, null, null, false
        );

        $this->assertNotNull($result['invoice']);
        $this->assertCount(1, $result['line_items']);
        $this->assertGreaterThan(0, $result['totals']['amount']);

        // 验证发票
        $this->assertDatabaseHas('invoices', [
            'id' => $result['invoice']->id,
            'subscription_id' => $this->subscription->id,
            'billing_reason' => 'metered_usage',
        ]);
    }

    /** @test */
    public function dry_run_does_not_create_invoice()
    {
        $this->createUsageData('api_call.validate', 1000);

        $result = $this->service->generateMeteredInvoice(
            $this->subscription, null, null, true
        );

        $this->assertNull($result['invoice']);
        $this->assertNotEmpty($result['line_items']);
    }

    /** @test */
    public function skips_when_no_usage()
    {
        $result = $this->service->generateMeteredInvoice(
            $this->subscription, null, null, false
        );

        $this->assertNull($result['invoice']);
        $this->assertNotEmpty($result['errors']);
    }

    /** @test */
    public function creates_invoice_with_line_items()
    {
        $this->createUsageData('api_call.validate', 3000);

        $result = $this->service->generateMeteredInvoice(
            $this->subscription, null, null, false
        );

        $invoice = $result['invoice'];
        $lineItems = InvoiceLineItem::where('invoice_id', $invoice->id)->get();

        $this->assertCount(1, $lineItems);
        $this->assertEquals('metered_usage', $lineItems[0]->type);
        $this->assertNotNull($lineItems[0]['breakdown']);
    }

    /** @test */
    public function respects_hard_cap()
    {
        $this->subscription->update([
            'metered_config' => [
                'enabled' => true,
                'billing_period' => 'monthly',
                'cap_type' => 'hard',
                'monthly_cap' => 20.00,
            ]
        ]);

        $this->createUsageData('api_call.validate', 50000);

        $result = $this->service->generateMeteredInvoice(
            $this->subscription, null, null, false
        );

        $this->assertNotNull($result['invoice']);
        $this->assertEquals(20.00, $result['invoice']->amount);
    }

    /** @test */
    public function returns_overview_data()
    {
        $this->createUsageData('api_call.validate', 1000);
        $this->service->generateMeteredInvoice($this->subscription, null, null, false);

        $overview = $this->service->getOverview($this->tenant->id);

        $this->assertEquals(1, $overview['active_prices']);
        $this->assertEquals(1, $overview['active_subscriptions']);
        $this->assertGreaterThan(0, $overview['monthly_metered_amount']);
        $this->assertCount(1, $overview['recent_invoices']);
    }

    /** @test */
    public function gets_license_usage()
    {
        $this->createUsageData('api_call.validate', 1500);

        $usage = $this->service->getLicenseUsage(
            $this->license, 'api_call.validate'
        );

        $this->assertNotEmpty($usage['metrics'], 'Metrics should not be empty');
        $this->assertEquals(1500, $usage['metrics'][0]['total_quantity']);
        $this->assertGreaterThan(0, $usage['total_cost']);
    }

    /** @test */
    public function meterable_price_tier_handles_empty_tiers()
    {
        $price = MeteredPrice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'metric_key' => 'test.empty',
            'name' => '空阶梯测试',
            'unit' => 'count',
            'tiers' => [],
            'base_fee' => 0,
            'included_quantity' => 0,
        ]);

        $result = $price->calculateCost(100);

        $this->assertEquals(100, $result['total_quantity']);
        $this->assertEquals(0, $result['total_cost']);
    }

    /** @test */
    public function invoice_no_format_is_correct()
    {
        $this->createUsageData('api_call.validate', 1000);

        $result = $this->service->generateMeteredInvoice(
            $this->subscription, null, null, false
        );

        $this->assertNotNull($result['invoice']);
        $this->assertStringStartsWith('INV-MT-', $result['invoice']->invoice_no);
    }

    /** @test */
    public function subscription_without_licenses_returns_error()
    {
        $this->license->delete();

        $result = $this->service->generateMeteredInvoice(
            $this->subscription, null, null, false
        );

        $this->assertNull($result['invoice']);
        $this->assertContains('订阅下没有License', $result['errors']);
    }

    protected function createUsageData(string $metricKey, int $quantity): void
    {
        UsageRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'license_id' => $this->license->id,
            'customer_id' => $this->subscription->customer_id,
            'metric_key' => $metricKey,
            'action' => 'validate',
            'window_type' => 'monthly',
            'quantity' => $quantity,
            'unit' => 'count',
            'recorded_at' => Carbon::now(),
        ]);

        UsageAggregate::factory()->create([
            'tenant_id' => $this->tenant->id,
            'license_id' => $this->license->id,
            'customer_id' => $this->subscription->customer_id,
            'metric_key' => $metricKey,
            'period' => 'monthly',
            'period_start' => $this->periodStart,
            'period_end' => $this->periodEnd,
            'total_quantity' => $quantity,
            'record_count' => 1,
        ]);
    }
}
