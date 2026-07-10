<?php

namespace Tests\Unit\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Tenant;
use App\Services\EcommerceAnalyticsService;
use Carbon\Carbon;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class EcommerceAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected EcommerceAnalyticsService $service;
    protected Tenant $tenant;
    protected Customer $customer1;
    protected Customer $customer2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new EcommerceAnalyticsService();
        $this->tenant = Tenant::factory()->create();
        $this->customer1 = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->customer2 = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        // 客户1有2张发票
        Invoice::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer1->id,
            'status' => 'paid',
            'amount' => 200,
            'payment_method' => 'alipay',
            'paid_at' => Carbon::now()->subDays(1),
        ]);

        // 客户2有1张发票 + 1张其他支付方式
        Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer2->id,
            'status' => 'paid',
            'amount' => 500,
            'payment_method' => 'wechat',
            'paid_at' => Carbon::now(),
        ]);

        // 老的发票（用于周期对比）
        Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer1->id,
            'status' => 'paid',
            'amount' => 150,
            'payment_method' => 'alipay',
            'paid_at' => Carbon::now()->subDays(35),
        ]);

        // 发票行项目
        $invoice1 = Invoice::where('customer_id', $this->customer1->id)->first();
        InvoiceLineItem::factory()->create([
            'invoice_id' => $invoice1->id,
            'tenant_id' => $this->tenant->id,
            'type' => 'subscription',
            'description' => '基础版订阅',
            'quantity' => 1,
            'unit_price' => 200,
            'amount' => 200,
        ]);
    }

    /** @test */
    public function gets_summary_stats()
    {
        $summary = $this->service->getSummary($this->tenant->id, 30);

        $this->assertEquals(900, $summary['total_revenue']); // 200+200+500=900
        $this->assertEquals(3, $summary['total_orders']);
        $this->assertEquals(2, $summary['total_customers']);
        $this->assertEquals(2, $summary['new_customers']);
        $this->assertEquals(300, $summary['avg_order_value']); // 900/3
    }

    /** @test */
    public function gets_sales_trend()
    {
        $trend = $this->service->getSalesTrend($this->tenant->id, 7);

        $this->assertNotEmpty($trend);
        $this->assertCount(8, $trend); // 7天 + today
        // 昨天的数据应该有2个订单（因为昨天1个客户1的2张发票在subDays(1)）
        $yesterday = Carbon::now()->subDays(1)->format('Y-m-d');
        $yesterdayData = collect($trend)->firstWhere('date', $yesterday);
        $this->assertNotNull($yesterdayData);
        $this->assertEquals(2, $yesterdayData['order_count']);
    }

    /** @test */
    public function gets_product_ranking()
    {
        $ranking = $this->service->getProductSalesRanking($this->tenant->id, '30d');

        $this->assertNotEmpty($ranking);
        $this->assertEquals('subscription', $ranking[0]['type']);
        $this->assertEquals(1, $ranking[0]['total_quantity']);
    }

    /** @test */
    public function gets_repurchase_rate()
    {
        $rate = $this->service->getRepurchaseRate($this->tenant->id, 90);

        // 客户1有3张发票（200+200+150）, 客户2有1张（500）
        // 总购买者2, 复购者1（客户1有>=2）, 多单者1（客户1有>=3）
        $this->assertEquals(2, $rate['total_buyers']);
        $this->assertEquals(50.0, $rate['repurchase_rate']); // 1/2
        $this->assertEquals(50.0, $rate['multi_purchase_rate']); // 1/2
        $this->assertEquals(2.0, $rate['avg_orders_per_buyer']); // (3+1)/2
        $this->assertEquals(525.0, $rate['avg_spent_per_buyer']); // (550+500)/2
    }

    /** @test */
    public function gets_payment_channel_breakdown()
    {
        $channels = $this->service->getPaymentChannelBreakdown($this->tenant->id, 30);

        $this->assertCount(2, $channels);
        $channelNames = array_column($channels, 'channel');
        $this->assertContains('alipay', $channelNames);
        $this->assertContains('wechat', $channelNames);
    }

    /** @test */
    public function gets_period_comparison()
    {
        $comparison = $this->service->getPeriodComparison($this->tenant->id, 30);

        $this->assertArrayHasKey('current', $comparison);
        $this->assertArrayHasKey('previous_period', $comparison);
        $this->assertArrayHasKey('chain_growth', $comparison);
        $this->assertArrayHasKey('yoy_growth', $comparison);

        // 当前期有900（3张发票在30天内），上期应该有150（1张35天前）
        $this->assertEquals(900, $comparison['current']['revenue']);
    }

    /** @test */
    public function gets_customer_metrics()
    {
        $metrics = $this->service->getCustomerMetrics($this->tenant->id, 30);

        $this->assertArrayHasKey('new_customers', $metrics);
        $this->assertArrayHasKey('active_customers', $metrics);
        $this->assertArrayHasKey('total_customers', $metrics);
        $this->assertEquals(2, $metrics['active_customers']);
        $this->assertEquals(2, $metrics['total_customers']);
    }

    /** @test */
    public function gets_sales_forecast()
    {
        // 先创建更多的数据以便回归分析有足够的点
        for ($i = 0; $i < 10; $i++) {
            Invoice::factory()->create([
                'tenant_id' => $this->tenant->id,
                'customer_id' => $this->customer1->id,
                'status' => 'paid',
                'amount' => 100 + $i * 10,
                'paid_at' => Carbon::now()->subDays($i * 3),
            ]);
        }

        $forecast = $this->service->getSalesForecast($this->tenant->id, 90, 7);

        $this->assertArrayHasKey('forecast', $forecast);
        $this->assertArrayHasKey('trend_direction', $forecast);
        $this->assertArrayHasKey('confidence', $forecast);
        $this->assertCount(7, $forecast['forecast']);
        $this->assertNotNull($forecast['total_predicted_revenue']);
        $this->assertGreaterThan(0, $forecast['total_predicted_revenue']);
    }

    /** @test */
    public function dashboard_returns_all_sections()
    {
        $dashboard = $this->service->getDashboard($this->tenant->id, '30d');

        $this->assertArrayHasKey('summary', $dashboard);
        $this->assertArrayHasKey('sales_trend', $dashboard);
        $this->assertArrayHasKey('product_ranking', $dashboard);
        $this->assertArrayHasKey('repurchase_rate', $dashboard);
        $this->assertArrayHasKey('payment_channels', $dashboard);
        $this->assertArrayHasKey('customer_metrics', $dashboard);
        $this->assertArrayHasKey('comparison', $dashboard);
    }

    /** @test */
    public function exports_csv_sales_trend()
    {
        $csv = $this->service->exportCsv($this->tenant->id, 'sales_trend', 7);

        $this->assertStringStartsWith('日期,', $csv);
        $this->assertStringContainsString('订单数', $csv);
        $lines = explode("\n", trim($csv));
        $this->assertGreaterThan(2, count($lines)); // 标题 + 至少一行数据
    }
}
