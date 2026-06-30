<?php

namespace Tests\Feature\Api;

use App\Models\ApiVersion;
use App\Models\EarningsAccount;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class WithdrawalApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private User $admin;
    private string $userToken;
    private string $adminToken;

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
        $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id]);

        Permission::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);
        $this->admin->givePermissionTo('admin');

        $this->userToken = $this->user->createToken('user-token', ['*'])->plainTextToken;
        $this->adminToken = $this->admin->createToken('admin-token', ['admin'])->plainTextToken;
    }

    protected function userHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->userToken];
    }

    protected function adminHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->adminToken];
    }

    protected function createUserWithBalance(User $user, float $balance = 50000): EarningsAccount
    {
        return EarningsAccount::factory()->create([
            'user_id' => $user->id,
            'available_balance' => $balance,
            'pending_balance' => 0,
            'frozen_amount' => 0,
            'total_withdrawn' => 0,
        ]);
    }

    public function test_user_can_request_withdrawal(): void
    {
        $this->createUserWithBalance($this->user);

        $response = $this->postJson('/api/withdrawals', [
            'channel' => 'alipay',
            'amount' => 1000,
            'alipay_account' => 'user@example.com',
        ], $this->userHeaders());

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.channel', 'alipay');
        $this->assertEquals(1000, (float) $response->json('data.amount'));
    }

    public function test_user_my_withdrawals_list(): void
    {
        $account = $this->createUserWithBalance($this->user);
        Withdrawal::factory()->create([
            'earnings_account_id' => $account->id,
            'user_id' => $this->user->id,
            'channel' => 'alipay',
            'amount' => 500,
        ]);

        $response = $this->getJson('/api/withdrawals', $this->userHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true);
        $this->assertGreaterThanOrEqual(1, count($response->json('data.data') ?? []));
    }

    public function test_user_stats_returns_summary(): void
    {
        $this->createUserWithBalance($this->user);

        $response = $this->getJson('/api/withdrawals/stats', $this->userHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_admin_index_lists_withdrawals(): void
    {
        $account = $this->createUserWithBalance($this->user);
        Withdrawal::factory()->pendingReview()->create([
            'earnings_account_id' => $account->id,
            'user_id' => $this->user->id,
            'channel' => 'bank',
            'amount' => 10000,
        ]);

        $response = $this->getJson('/api/admin/withdrawals?status=pending_review', $this->adminHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true);
        $this->assertGreaterThanOrEqual(1, count($response->json('data.data') ?? []));
    }

    public function test_admin_review_approves_withdrawal(): void
    {
        $account = $this->createUserWithBalance($this->user);
        $withdrawal = Withdrawal::factory()->pendingReview()->create([
            'earnings_account_id' => $account->id,
            'user_id' => $this->user->id,
            'channel' => 'alipay',
            'amount' => 10000,
        ]);

        $response = $this->postJson(
            "/api/admin/withdrawals/{$withdrawal->id}/review",
            ['action' => 'approve', 'remark' => 'OK'],
            $this->adminHeaders()
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_admin_stats_returns_dashboard(): void
    {
        $response = $this->getJson('/api/admin/withdrawals/stats', $this->adminHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_admin_routes_require_admin_ability(): void
    {
        $this->getJson('/api/admin/withdrawals', $this->userHeaders())
            ->assertStatus(403);
    }
}
