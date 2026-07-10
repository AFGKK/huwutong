<?php

namespace Tests\Feature\Api;

use App\Models\Agent;
use App\Models\ApiVersion;
use App\Models\CommissionSettlement;
use App\Models\EarningsAccount;
use App\Models\PlatformFee;
use App\Models\SettlementBatch;
use App\Models\SettlementCycle;
use App\Models\Tenant;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class SettlementApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

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
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->token = $this->user->createToken('test-token', ['*'])->plainTextToken;
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    protected function createAgentWithAccount(): Agent
    {
        $agentUser = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $agent = Agent::factory()->create([
            'user_id' => $agentUser->id,
            'status' => 'active',
        ]);

        EarningsAccount::factory()->create([
            'user_id' => $agentUser->id,
            'tenant_id' => $this->tenant->id,
            'available_balance' => 0,
            'pending_balance' => 500,
            'frozen_amount' => 0,
        ]);

        return $agent;
    }

    protected function createReleasableSettlement(Agent $agent, float $amount = 200.0): CommissionSettlement
    {
        return CommissionSettlement::factory()->create([
            'agent_id' => $agent->id,
            'status' => 'pending_settlement',
            'commission_amount' => $amount,
            'fee' => 10.00,
            'net_amount' => $amount - 10,
            'released_at' => now()->subDay(),
            'settlement_batch_id' => null,
        ]);
    }

    public function test_dashboard_returns_overview(): void
    {
        $agent = $this->createAgentWithAccount();
        $this->createReleasableSettlement($agent, 150);

        SettlementCycle::create([
            'tenant_id' => $this->tenant->id,
            'name' => '2026年6月结算周期',
            'period_type' => 'monthly',
            'period_start' => now()->subMonth()->startOfMonth(),
            'period_end' => now()->subMonth()->endOfMonth(),
            'settlement_date' => now()->addDays(7),
            'status' => 'pending',
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/settlement/dashboard', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'pending_settlements',
                    'releasable_count',
                    'pending_payouts',
                    'active_agents',
                    'monthly_settled',
                    'monthly_fees',
                    'recent_cycles',
                    'settlement_trend',
                    'pending_by_agent',
                ],
            ]);
    }

    public function test_cycles_list_paginated(): void
    {
        SettlementCycle::create([
            'tenant_id' => $this->tenant->id,
            'name' => '周期 A',
            'period_type' => 'monthly',
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'settlement_date' => '2026-06-15',
            'status' => 'pending',
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/settlement/cycles', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_cycle_store_creates_cycle(): void
    {
        $payload = [
            'name' => '2026年7月结算',
            'period_type' => 'monthly',
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'settlement_date' => '2026-08-15',
            'payout_date' => '2026-08-20',
            'notes' => '测试周期',
        ];

        $response = $this->postJson('/api/settlement/cycles', $payload, $this->authHeaders());

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonStructure(['data' => ['id', 'name', 'period_type', 'status']]);

        $this->assertDatabaseHas('settlement_cycles', [
            'tenant_id' => $this->tenant->id,
            'name' => '2026年7月结算',
            'status' => 'pending',
        ]);
    }

    public function test_cycle_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/settlement/cycles', [], $this->authHeaders());

        $response->assertStatus(422);
    }

    public function test_cycle_generate_creates_monthly_cycle(): void
    {
        $response = $this->postJson('/api/settlement/cycles/generate', [], $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.period_type', 'monthly');

        $this->assertDatabaseHas('settlement_cycles', [
            'tenant_id' => $this->tenant->id,
            'period_type' => 'monthly',
        ]);
    }

    public function test_cycle_show_returns_detail(): void
    {
        $cycle = SettlementCycle::create([
            'tenant_id' => $this->tenant->id,
            'name' => '详情测试周期',
            'period_type' => 'weekly',
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-07',
            'settlement_date' => '2026-06-10',
            'status' => 'pending',
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/settlement/cycles/{$cycle->id}", $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $cycle->id)
            ->assertJsonStructure(['data' => ['id', 'name', 'period_type', 'status', 'batches']]);
    }

    public function test_scan_releasable_finds_commissions(): void
    {
        $agent = $this->createAgentWithAccount();
        $this->createReleasableSettlement($agent, 300);
        $this->createReleasableSettlement($agent, 200);

        $response = $this->getJson('/api/settlement/releasable', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_count', 2)
            ->assertJsonPath('data.total_amount', 500);
    }

    public function test_batches_list_paginated(): void
    {
        SettlementBatch::create([
            'tenant_id' => $this->tenant->id,
            'batch_no' => 'STL' . now()->format('Ymd') . '0001',
            'channel' => 'alipay',
            'total_amount' => 1000,
            'total_fee' => 50,
            'net_amount' => 950,
            'item_count' => 1,
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/settlement/batches', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_batch_create_submit_approve_complete_workflow(): void
    {
        $agent = $this->createAgentWithAccount();
        $settlement = $this->createReleasableSettlement($agent, 500);

        $create = $this->postJson('/api/settlement/batches', [
            'channel' => 'alipay',
            'settlement_ids' => [$settlement->id],
            'notes' => '批次测试',
        ], $this->authHeaders());

        $create->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.total_amount', '500.00');

        $batchId = $create->json('data.id');

        $this->postJson("/api/settlement/batches/{$batchId}/submit", [], $this->authHeaders())
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'pending_approval');

        $this->postJson("/api/settlement/batches/{$batchId}/approve", [], $this->authHeaders())
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'approved');

        $this->postJson("/api/settlement/batches/{$batchId}/complete", [], $this->authHeaders())
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('commission_settlements', [
            'id' => $settlement->id,
            'status' => 'settled',
            'settlement_batch_id' => $batchId,
        ]);
    }

    public function test_batch_cancel_releases_settlements(): void
    {
        $agent = $this->createAgentWithAccount();
        $settlement = $this->createReleasableSettlement($agent, 400);

        $create = $this->postJson('/api/settlement/batches', [
            'channel' => 'balance',
            'settlement_ids' => [$settlement->id],
        ], $this->authHeaders());

        $batchId = $create->json('data.id');

        $this->postJson("/api/settlement/batches/{$batchId}/cancel", [], $this->authHeaders())
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('commission_settlements', [
            'id' => $settlement->id,
            'settlement_batch_id' => null,
        ]);
    }

    public function test_fee_stats_returns_data(): void
    {
        PlatformFee::create([
            'tenant_id' => $this->tenant->id,
            'fee_type' => 'platform',
            'name' => '平台服务费',
            'amount' => 120.50,
            'status' => 'collected',
            'collected_at' => now(),
        ]);

        $response = $this->getJson('/api/settlement/fees', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['total_fees', 'by_type']]);
    }

    public function test_unauthenticated_requests_fail(): void
    {
        $this->getJson('/api/settlement/dashboard')->assertStatus(401);
        $this->getJson('/api/settlement/cycles')->assertStatus(401);
        $this->getJson('/api/settlement/batches')->assertStatus(401);
    }
}
