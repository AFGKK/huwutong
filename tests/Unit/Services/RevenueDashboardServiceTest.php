<?php

namespace Tests\Unit\Services;

use App\Models\Agent;
use App\Models\CommissionPayout;
use App\Models\CommissionSettlement;
use App\Models\Invoice;
use App\Models\Refund;
use App\Models\Subscription;
use App\Models\SubscriptionAgent;
use App\Services\RevenueDashboardService;
use Carbon\Carbon;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class RevenueDashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    protected RevenueDashboardService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(RevenueDashboardService::class);
    }

    /** @test */
    public function platform_overview_returns_all_keys()
    {
        $overview = $this->service->platformOverview();

        $this->assertArrayHasKey('revenue', $overview);
        $this->assertArrayHasKey('refunds', $overview);
        $this->assertArrayHasKey('commissions', $overview);
        $this->assertArrayHasKey('payouts', $overview);
        $this->assertArrayHasKey('net_revenue', $overview);
        $this->assertArrayHasKey('mrr', $overview);
        $this->assertArrayHasKey('arr', $overview);
        $this->assertArrayHasKey('subscriptions', $overview);
        $this->assertArrayHasKey('active_agents', $overview);
    }

    /** @test */
    public function platform_overview_shows_correct_revenue_totals()
    {
        // Create paid invoices
        Invoice::factory()->count(3)->create([
            'amount' => 100.00,
            'status' => 'paid',
            'paid_at' => now(),
        ]);
        Invoice::factory()->create([
            'amount' => 50.00,
            'status' => 'pending',
        ]);

        $overview = $this->service->platformOverview();

        $this->assertEquals(300.00, $overview['revenue']['total']);
        $this->assertEquals(300.00, $overview['revenue']['month']);
    }

    /** @test */
    public function platform_overview_calculates_refunds()
    {
        $invoice = Invoice::factory()->create(['amount' => 500.00, 'status' => 'paid', 'paid_at' => now()]);
        Refund::factory()->create([
            'invoice_id' => $invoice->id,
            'amount' => 50.00,
            'status' => 'completed',
        ]);

        $overview = $this->service->platformOverview();

        $this->assertEquals(50.00, $overview['refunds']['total']);
        $this->assertEquals(50.00, $overview['refunds']['month']);
        $this->assertEquals(10.0, $overview['refunds']['refund_rate']); // 50/500
    }

    /** @test */
    public function platform_overview_calculates_net_revenue()
    {
        $invoice = Invoice::factory()->create(['amount' => 1000.00, 'status' => 'paid', 'paid_at' => now()]);
        Refund::factory()->create([
            'invoice_id' => $invoice->id,
            'amount' => 100.00,
            'status' => 'completed',
        ]);
        CommissionSettlement::factory()->create([
            'invoice_id' => $invoice->id,
            'subscription_id' => $invoice->subscription_id,
            'status' => 'released',
            'commission_amount' => 80.00,
            'period' => now()->format('Y-m'),
        ]);

        $overview = $this->service->platformOverview();

        // 1000 - 100 - 80 = 820
        $this->assertEquals(820.00, $overview['net_revenue']['total']);
        $this->assertEquals(820.00, $overview['net_revenue']['month']);
    }

    /** @test */
    public function platform_overview_calculates_mrr()
    {
        Subscription::factory()->create([
            'status' => 'active',
            'billing_period' => 'monthly',
            'price' => 100,
        ]);
        Subscription::factory()->create([
            'status' => 'active',
            'billing_period' => 'yearly',
            'price' => 1200,
        ]);
        Subscription::factory()->create([
            'status' => 'active',
            'billing_period' => 'quarterly',
            'price' => 300,
        ]);

        $overview = $this->service->platformOverview();

        // 100 + 1200/12 + 300/3 = 100 + 100 + 100 = 300
        $this->assertEquals(300.00, $overview['mrr']);
        $this->assertEquals(3600.00, $overview['arr']);
    }

    /** @test */
    public function platform_overview_counts_subscriptions()
    {
        Subscription::factory()->create(['status' => 'active']);
        Subscription::factory()->create(['status' => 'active']);
        Subscription::factory()->create(['status' => 'grace']);
        Subscription::factory()->create(['status' => 'expired']);

        $overview = $this->service->platformOverview();

        $this->assertEquals(3, $overview['subscriptions']['active']);
    }

    /** @test */
    public function channel_roi_returns_correct_structure()
    {
        $result = $this->service->channelRoi();

        $this->assertArrayHasKey('channels', $result);
        $this->assertArrayHasKey('overall', $result);
        $this->assertArrayHasKey('definitions', $result);
        $this->assertCount(5, $result['channels']); // link, code, direct, api, organic
    }

    /** @test */
    public function channel_roi_calculates_roi_for_link_channel()
    {
        // Create subscription with link attribution
        $subscription = Subscription::factory()->create();
        SubscriptionAgent::create([
            'subscription_id' => $subscription->id,
            'agent_id' => Agent::factory()->create()->id,
            'attribution_source' => 'link',
            'attributed_at' => now(),
        ]);

        // Create paid invoice
        Invoice::factory()->create([
            'subscription_id' => $subscription->id,
            'amount' => 1000.00,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        // Create commission settlement
        CommissionSettlement::factory()->create([
            'subscription_id' => $subscription->id,
            'status' => 'released',
            'commission_amount' => 100.00,
            'period' => now()->format('Y-m'),
        ]);

        $result = $this->service->channelRoi();
        $linkChannel = collect($result['channels'])->firstWhere('name', '推广链接');

        $this->assertNotNull($linkChannel);
        $this->assertEquals(1000.00, $linkChannel['revenue']);
        $this->assertEquals(100.00, $linkChannel['commission']);
        $this->assertEquals(900.00, $linkChannel['net_revenue']);
    }

    /** @test */
    public function channel_quality_has_expected_keys()
    {
        $qualities = $this->service->channelQuality();

        $this->assertIsArray($qualities);
        $this->assertCount(5, $qualities);

        foreach ($qualities as $q) {
            $this->assertArrayHasKey('channel', $q);
            $this->assertArrayHasKey('channel_name', $q);
            $this->assertArrayHasKey('total_customers', $q);
            $this->assertArrayHasKey('avg_ltv', $q);
            $this->assertArrayHasKey('churn_rate', $q);
            $this->assertArrayHasKey('renewal_rate', $q);
            $this->assertArrayHasKey('avg_subscription_days', $q);
        }
    }

    /** @test */
    public function payment_method_distribution_returns_methods()
    {
        // Create invoices with different payment methods
        Invoice::factory()->create([
            'amount' => 200.00,
            'status' => 'paid',
            'paid_at' => now(),
            'payment_method' => 'alipay',
        ]);
        Invoice::factory()->create([
            'amount' => 300.00,
            'status' => 'paid',
            'paid_at' => now(),
            'payment_method' => 'wechat',
        ]);

        $result = $this->service->paymentMethodDistribution();

        $this->assertArrayHasKey('methods', $result);
        $this->assertCount(2, $result['methods']);
        $this->assertEquals(500.00, $result['total_amount']);
    }

    /** @test */
    public function revenue_trend_returns_monthly_data()
    {
        // Create invoices in different months
        Invoice::factory()->create([
            'amount' => 500.00,
            'status' => 'paid',
            'paid_at' => Carbon::parse('2026-01-15'),
        ]);
        Invoice::factory()->create([
            'amount' => 300.00,
            'status' => 'paid',
            'paid_at' => Carbon::parse('2026-02-10'),
        ]);

        $trend = $this->service->revenueTrend(24);

        $this->assertGreaterThan(0, count($trend));
        $janEntry = collect($trend)->firstWhere('period', '2026-01');
        $febEntry = collect($trend)->firstWhere('period', '2026-02');

        $this->assertNotNull($janEntry);
        $this->assertNotNull($febEntry);
        $this->assertEquals(500.00, $janEntry['revenue']);
        $this->assertEquals(300.00, $febEntry['revenue']);
    }

    /** @test */
    public function agent_level_distribution_returns_levels()
    {
        $agent = Agent::factory()->create(['level' => 'gold']);
        $agent2 = Agent::factory()->create(['level' => 'regular']);

        CommissionSettlement::factory()->create([
            'agent_id' => $agent->id,
            'status' => 'released',
            'commission_amount' => 200.00,
        ]);
        CommissionSettlement::factory()->create([
            'agent_id' => $agent2->id,
            'status' => 'released',
            'commission_amount' => 50.00,
        ]);

        $levels = $this->service->agentLevelDistribution();

        $goldLevel = collect($levels)->firstWhere('level', 'gold');
        $regularLevel = collect($levels)->firstWhere('level', 'regular');

        $this->assertNotNull($goldLevel);
        $this->assertEquals(200.00, $goldLevel['total_commission']);
        $this->assertEquals(50.00, $regularLevel['total_commission']);
    }

    /** @test */
    public function channel_trend_returns_trend_data_by_channel()
    {
        $subscription = Subscription::factory()->create();
        SubscriptionAgent::create([
            'subscription_id' => $subscription->id,
            'agent_id' => Agent::factory()->create()->id,
            'attribution_source' => 'link',
            'attributed_at' => now(),
        ]);

        Invoice::factory()->create([
            'subscription_id' => $subscription->id,
            'amount' => 500.00,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $trend = $this->service->channelTrend(12);

        $this->assertArrayHasKey('link', $trend);
        $linkTrend = $trend['link'];
        $this->assertGreaterThan(0, count($linkTrend));
        $this->assertEquals(500.00, $linkTrend[count($linkTrend) - 1]['revenue']);
    }
}
