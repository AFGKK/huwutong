<?php

namespace Tests\Unit\Services;

use App\Models\MonthlyRevenueSnapshot;
use App\Models\MrrChangeDetail;
use App\Models\Tenant;
use App\Services\MrrWaterfallService;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class MrrWaterfallServiceTest extends TestCase
{
    use RefreshDatabase;

    protected MrrWaterfallService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MrrWaterfallService::class);

        if (!Tenant::find(1)) {
            Tenant::factory()->create(['id' => 1]);
        }
    }

    public function test_get_waterfall_returns_monthly_data(): void
    {
        MonthlyRevenueSnapshot::create([
            'tenant_id' => 1,
            'year_month' => now()->subMonth()->format('Y-m'),
            'recognized_revenue' => 40000,
            'net_new_arr' => 5000,
            'expansion_arr' => 2000,
            'contraction_arr' => -1000,
            'churned_arr' => -2000,
            'active_subscriptions' => 100,
        ]);
        MonthlyRevenueSnapshot::create([
            'tenant_id' => 1,
            'year_month' => now()->format('Y-m'),
            'recognized_revenue' => 44000,
            'net_new_arr' => 3000,
            'expansion_arr' => 1000,
            'contraction_arr' => -500,
            'churned_arr' => -500,
            'active_subscriptions' => 110,
        ]);

        $waterfall = $this->service->getWaterfall(1, 2);

        $this->assertCount(2, $waterfall);
        $this->assertArrayHasKey('ending_mrr', $waterfall[1]);
        $this->assertEquals(44000, $waterfall[1]['ending_mrr']);
    }

    public function test_get_summary_aggregates_change_types(): void
    {
        $ym = '2026-06';
        MonthlyRevenueSnapshot::create([
            'tenant_id' => 1,
            'year_month' => $ym,
            'recognized_revenue' => 50000,
            'active_subscriptions' => 120,
        ]);
        MrrChangeDetail::create([
            'tenant_id' => 1, 'year_month' => $ym, 'change_type' => 'new_subscription',
            'mrr_impact' => 8000, 'previous_mrr' => 0, 'new_mrr' => 8000, 'currency' => 'CNY', 'occurred_at' => now(),
        ]);
        MrrChangeDetail::create([
            'tenant_id' => 1, 'year_month' => $ym, 'change_type' => 'cancellation',
            'mrr_impact' => -2000, 'previous_mrr' => 2000, 'new_mrr' => 0, 'currency' => 'CNY', 'occurred_at' => now(),
        ]);

        $summary = $this->service->getSummary(1, $ym);

        $this->assertEquals(50000, $summary['mrr']);
        $this->assertEquals(8000, $summary['new_mrr']);
        $this->assertEquals(2000, $summary['churned_mrr']);
        $this->assertEquals(6000, $summary['net_mrr_change']);
    }

    public function test_get_breakdown_by_region_groups_metadata(): void
    {
        $ym = '2026-06';
        MrrChangeDetail::create([
            'tenant_id' => 1, 'year_month' => $ym, 'change_type' => 'new_subscription',
            'mrr_impact' => 5000, 'previous_mrr' => 0, 'new_mrr' => 5000, 'currency' => 'CNY',
            'metadata' => ['region' => '华东'], 'occurred_at' => now(),
        ]);
        MrrChangeDetail::create([
            'tenant_id' => 1, 'year_month' => $ym, 'change_type' => 'upgrade',
            'mrr_impact' => 2000, 'previous_mrr' => 3000, 'new_mrr' => 5000, 'currency' => 'CNY',
            'metadata' => ['region' => '华东'], 'occurred_at' => now(),
        ]);

        $breakdown = $this->service->getBreakdownByRegion(1, $ym);

        $this->assertCount(1, $breakdown);
        $this->assertEquals('华东', $breakdown[0]['region']);
        $this->assertEquals(7000, $breakdown[0]['total_impact']);
    }
}
