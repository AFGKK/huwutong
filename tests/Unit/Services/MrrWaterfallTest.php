<?php

namespace Tests\Unit\Services;

use App\Models\MonthlyRevenueSnapshot;
use App\Models\MrrChangeDetail;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MrrWaterfallTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        if (!Tenant::find(1)) {
            Tenant::factory()->create(['id' => 1]);
        }

        $this->user = User::factory()->create(['tenant_id' => 1]);
    }

    /** @test */
    public function can_create_mrr_snapshot()
    {
        $snapshot = MonthlyRevenueSnapshot::create([
            'tenant_id' => 1,
            'year_month' => '2026-06',
            'invoiced_revenue' => 50000,
            'recognized_revenue' => 45000,
            'deferred_revenue' => 5000,
            'refunds' => 1000,
            'net_new_arr' => 10000,
            'expansion_arr' => 5000,
            'contraction_arr' => -2000,
            'churned_arr' => -3000,
            'active_subscriptions' => 150,
        ]);

        $this->assertNotNull($snapshot->id);
        $this->assertEquals(45000.00, (float) $snapshot->recognized_revenue);
        $this->assertEquals(10000.00, (float) $snapshot->net_new_arr);
    }

    /** @test */
    public function can_create_mrr_change_detail()
    {
        $detail = MrrChangeDetail::create([
            'tenant_id' => 1,
            'year_month' => '2026-06',
            'change_type' => 'new_subscription',
            'subscription_id' => null,
            'customer_id' => null,
            'plan_id' => null,
            'previous_mrr' => 0,
            'new_mrr' => 5000,
            'mrr_impact' => 5000,
            'currency' => 'CNY',
            'reason' => '新客户订阅专业版',
            'occurred_at' => now(),
        ]);

        $this->assertNotNull($detail->id);
        $this->assertEquals('new_subscription', $detail->change_type);
        $this->assertEquals(5000.00, (float) $detail->mrr_impact);
    }

    /** @test */
    public function mrr_change_types_are_valid()
    {
        $validTypes = ['new_subscription', 'upgrade', 'downgrade', 'cancellation', 'reactivation', 'price_change'];

        foreach ($validTypes as $type) {
            $detail = MrrChangeDetail::create([
                'tenant_id' => 1,
                'year_month' => '2026-06',
                'change_type' => $type,
                'mrr_impact' => 1000,
                'previous_mrr' => 0,
                'new_mrr' => 1000,
                'currency' => 'CNY',
                'occurred_at' => now(),
            ]);
            $this->assertEquals($type, $detail->change_type);
        }
    }

    /** @test */
    public function can_query_snapshots_by_year_month()
    {
        MonthlyRevenueSnapshot::create([
            'tenant_id' => 1,
            'year_month' => '2026-04',
            'recognized_revenue' => 30000,
            'active_subscriptions' => 100,
        ]);
        MonthlyRevenueSnapshot::create([
            'tenant_id' => 1,
            'year_month' => '2026-05',
            'recognized_revenue' => 40000,
            'active_subscriptions' => 120,
        ]);
        MonthlyRevenueSnapshot::create([
            'tenant_id' => 1,
            'year_month' => '2026-06',
            'recognized_revenue' => 50000,
            'active_subscriptions' => 150,
        ]);

        $snapshots = MonthlyRevenueSnapshot::where('tenant_id', 1)
            ->where('year_month', '>=', '2026-05')
            ->orderBy('year_month')
            ->get();

        $this->assertCount(2, $snapshots);
        $this->assertEquals(40000.00, (float) $snapshots->first()->recognized_revenue);
    }

    /** @test */
    public function can_aggregate_mrr_changes_by_type()
    {
        MrrChangeDetail::create(['tenant_id' => 1, 'year_month' => '2026-06', 'change_type' => 'new_subscription', 'mrr_impact' => 10000, 'previous_mrr' => 0, 'new_mrr' => 10000, 'currency' => 'CNY', 'occurred_at' => now()]);
        MrrChangeDetail::create(['tenant_id' => 1, 'year_month' => '2026-06', 'change_type' => 'new_subscription', 'mrr_impact' => 5000, 'previous_mrr' => 0, 'new_mrr' => 5000, 'currency' => 'CNY', 'occurred_at' => now()]);
        MrrChangeDetail::create(['tenant_id' => 1, 'year_month' => '2026-06', 'change_type' => 'cancellation', 'mrr_impact' => -3000, 'previous_mrr' => 5000, 'new_mrr' => 0, 'currency' => 'CNY', 'occurred_at' => now()]);
        MrrChangeDetail::create(['tenant_id' => 1, 'year_month' => '2026-05', 'change_type' => 'upgrade', 'mrr_impact' => 2000, 'previous_mrr' => 3000, 'new_mrr' => 5000, 'currency' => 'CNY', 'occurred_at' => now()]);

        // Aggregate for June
        $aggregated = MrrChangeDetail::where('tenant_id', 1)
            ->where('year_month', '2026-06')
            ->selectRaw('change_type, COUNT(*) as count, SUM(mrr_impact) as total_impact')
            ->groupBy('change_type')
            ->pluck('total_impact', 'change_type');

        $this->assertEquals(15000, (float) $aggregated['new_subscription']);
        $this->assertEquals(-3000, (float) $aggregated['cancellation']);
        $this->assertArrayNotHasKey('upgrade', $aggregated->toArray());
    }

    /** @test */
    public function mrr_waterfall_calculations_are_correct()
    {
        // Create snapshots for 3 months
        MonthlyRevenueSnapshot::create([
            'tenant_id' => 1,
            'year_month' => '2026-04',
            'recognized_revenue' => 40000,
            'net_new_arr' => 8000,
            'expansion_arr' => 3000,
            'contraction_arr' => -1000,
            'churned_arr' => -2000,
            'active_subscriptions' => 100,
        ]);
        MonthlyRevenueSnapshot::create([
            'tenant_id' => 1,
            'year_month' => '2026-05',
            'recognized_revenue' => 48000,
            'net_new_arr' => 10000,
            'expansion_arr' => 4000,
            'contraction_arr' => -2000,
            'churned_arr' => -4000,
            'active_subscriptions' => 110,
        ]);
        MonthlyRevenueSnapshot::create([
            'tenant_id' => 1,
            'year_month' => '2026-06',
            'recognized_revenue' => 50000,
            'net_new_arr' => 6000,
            'expansion_arr' => 2000,
            'contraction_arr' => -3000,
            'churned_arr' => -3000,
            'active_subscriptions' => 115,
        ]);

        // Verify the net change calculation: new + expansion + contraction + churned
        $snapshot = MonthlyRevenueSnapshot::where('year_month', '2026-06')->first();
        $netChange = (float) $snapshot->net_new_arr + (float) $snapshot->expansion_arr
            + (float) $snapshot->contraction_arr + (float) $snapshot->churned_arr;

        // July: 6000 + 2000 - 3000 - 3000 = 2000 net increase
        $this->assertEquals(2000, $netChange);

        // Ending MRR (June) = 50000
        // Starting MRR (June) ≈ 50000 - 2000 = 48000
        $starting = (float) $snapshot->recognized_revenue - $netChange;
        $this->assertEquals(48000, $starting);
    }
}
