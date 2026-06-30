<?php

namespace Tests\Feature\Api;

use App\Models\ApiVersion;
use App\Models\MonthlyRevenueSnapshot;
use App\Models\MrrChangeDetail;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MrrWaterfallApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $admin;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        ApiVersion::create([
            'version' => 'v1',
            'base_path' => '/api/v1',
            'name' => 'v1',
            'status' => 'active',
            'is_default' => true,
        ]);

        $this->tenant = Tenant::factory()->create();
        $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->token = $this->admin->createToken('admin-token', ['admin'])->plainTextToken;
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    public function test_waterfall_returns_monthly_data(): void
    {
        MonthlyRevenueSnapshot::create([
            'tenant_id' => $this->tenant->id,
            'year_month' => now()->subMonth()->format('Y-m'),
            'recognized_revenue' => 40000,
            'net_new_arr' => 5000,
            'expansion_arr' => 2000,
            'contraction_arr' => -1000,
            'churned_arr' => -2000,
            'active_subscriptions' => 100,
        ]);
        MonthlyRevenueSnapshot::create([
            'tenant_id' => $this->tenant->id,
            'year_month' => now()->format('Y-m'),
            'recognized_revenue' => 44000,
            'net_new_arr' => 3000,
            'expansion_arr' => 1000,
            'contraction_arr' => -500,
            'churned_arr' => -500,
            'active_subscriptions' => 110,
        ]);

        $response = $this->getJson('/api/admin/revenue/mrr-waterfall?months=2', $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_summary_returns_aggregates(): void
    {
        $ym = now()->format('Y-m');
        MonthlyRevenueSnapshot::create([
            'tenant_id' => $this->tenant->id,
            'year_month' => $ym,
            'recognized_revenue' => 50000,
            'active_subscriptions' => 120,
        ]);
        MrrChangeDetail::create([
            'tenant_id' => $this->tenant->id,
            'year_month' => $ym,
            'change_type' => 'new_subscription',
            'mrr_impact' => 8000,
            'previous_mrr' => 0,
            'new_mrr' => 8000,
            'currency' => 'CNY',
            'occurred_at' => now(),
        ]);

        $response = $this->getJson("/api/admin/revenue/mrr-summary?year_month={$ym}", $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.mrr', 50000)
            ->assertJsonPath('data.new_mrr', 8000);
    }

    public function test_drilldown_returns_paginated_changes(): void
    {
        $ym = now()->format('Y-m');
        MrrChangeDetail::create([
            'tenant_id' => $this->tenant->id,
            'year_month' => $ym,
            'change_type' => 'new_subscription',
            'mrr_impact' => 5000,
            'previous_mrr' => 0,
            'new_mrr' => 5000,
            'currency' => 'CNY',
            'occurred_at' => now(),
        ]);

        $response = $this->getJson(
            "/api/admin/revenue/mrr-drilldown?year_month={$ym}&change_type=new_subscription",
            $this->authHeaders()
        );

        $response->assertOk()
            ->assertJsonPath('success', true);
        $this->assertGreaterThanOrEqual(1, count($response->json('data.data') ?? $response->json('data')));
    }

    public function test_breakdown_by_product_returns_data(): void
    {
        $ym = now()->format('Y-m');

        $response = $this->getJson(
            "/api/admin/revenue/mrr-breakdown/product?year_month={$ym}",
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_requires_admin_ability(): void
    {
        $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $token = $user->createToken('user-token', ['read'])->plainTextToken;

        $this->getJson('/api/admin/revenue/mrr-waterfall', [
            'Authorization' => 'Bearer ' . $token,
        ])->assertStatus(403);
    }
}
