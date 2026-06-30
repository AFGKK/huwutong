<?php

namespace Tests\Feature\Api;

use App\Models\ApiVersion;
use App\Models\Commission;
use App\Models\EarningsAccount;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EarningsPortalApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private EarningsAccount $account;
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
        $this->account = EarningsAccount::factory()->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'available_balance' => 15000,
            'pending_balance' => 3000,
            'frozen_amount' => 2000,
            'total_withdrawn' => 5000,
            'status' => 'active',
        ]);
        $this->token = $this->user->createToken('portal-token', ['*'])->plainTextToken;
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    public function test_dashboard_returns_balance_overview(): void
    {
        Commission::create([
            'earnings_account_id' => $this->account->id,
            'order_id' => 1,
            'amount' => 500,
            'rate' => 10,
            'status' => 'released',
            'settled_at' => now(),
        ]);

        $response = $this->getJson('/api/portal/earnings/dashboard', $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.balance.available_balance', 15000)
            ->assertJsonPath('data.balance.pending_balance', 3000)
            ->assertJsonPath('data.balance.total_earned', 20000);
    }

    public function test_commissions_lists_paginated(): void
    {
        Commission::create([
            'earnings_account_id' => $this->account->id,
            'order_id' => 1,
            'amount' => 800,
            'rate' => 8,
            'status' => 'frozen',
            'settled_at' => now(),
            'frozen_until' => now()->addDays(30),
        ]);

        $response = $this->getJson('/api/portal/earnings/commissions?status=frozen', $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true);
        $this->assertGreaterThanOrEqual(1, count($response->json('data.data') ?? []));
    }

    public function test_withdrawal_channels_returns_options(): void
    {
        $response = $this->getJson('/api/portal/earnings/channels', $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.available_balance', 15000);
        $this->assertGreaterThanOrEqual(4, count($response->json('data.channels') ?? []));
    }

    public function test_save_and_delete_account(): void
    {
        $save = $this->postJson('/api/portal/earnings/channels/account', [
            'channel' => 'alipay',
            'account_info' => ['alipay_account' => 'agent@example.com'],
        ], $this->authHeaders());

        $save->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.channel', 'alipay');

        $this->deleteJson('/api/portal/earnings/channels/account/alipay', [], $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_preferences_get_and_update(): void
    {
        $this->getJson('/api/portal/earnings/preferences', $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.min_withdrawal_notify', 100);

        $this->putJson('/api/portal/earnings/preferences', [
            'min_withdrawal_notify' => 200,
            'auto_withdraw' => true,
            'auto_withdraw_channel' => 'alipay',
            'auto_withdraw_threshold' => 1500,
        ], $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson('/api/portal/earnings/preferences', $this->authHeaders())
            ->assertJsonPath('data.min_withdrawal_notify', 200)
            ->assertJsonPath('data.auto_withdraw', true);
    }

    public function test_creates_account_when_missing(): void
    {
        $newUser = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $token = $newUser->createToken('new-user', ['*'])->plainTextToken;

        $this->getJson('/api/portal/earnings/dashboard', [
            'Authorization' => 'Bearer ' . $token,
        ])
            ->assertOk()
            ->assertJsonPath('data.balance.available_balance', 0);

        $this->assertDatabaseHas('earnings_accounts', ['user_id' => $newUser->id]);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/portal/earnings/dashboard')->assertStatus(401);
    }
}
