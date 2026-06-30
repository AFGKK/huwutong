<?php

namespace Tests\Unit\Services;

use App\Models\Agent;
use App\Models\EarningsAccount;
use App\Services\CommissionRiskGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommissionRiskGuardTest extends TestCase
{
    // 使用 RefreshDatabase，但测试环境需要 MySQL 支持
    // 如果 SQLite 测试环境报错，请确保 MySQL 测试数据库可用
    use RefreshDatabase;

    protected CommissionRiskGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guard = $this->app->make(CommissionRiskGuard::class);
    }

    public function test_resolve_earnings_account_creates_if_not_exists()
    {
        $tenant = \App\Models\Tenant::factory()->create();
        $user = \App\Models\User::factory()->create(['tenant_id' => $tenant->id]);
        $agent = Agent::factory()->create(['user_id' => $user->id]);

        $account = $this->guard->resolveEarningsAccount($agent);

        $this->assertNotNull($account);
        $this->assertEquals($user->id, $account->user_id);
        $this->assertEquals('agent', $account->type);
        $this->assertEquals(0, (float) $account->pending_balance);
        $this->assertEquals(0, (float) $account->available_balance);
    }

    public function test_resolve_earnings_account_reuses_existing()
    {
        $user = \App\Models\User::factory()->create();
        $existing = EarningsAccount::factory()->create([
            'user_id' => $user->id,
            'type' => 'agent',
            'available_balance' => 999.99,
        ]);

        $agent = Agent::factory()->create(['user_id' => $user->id]);

        $account = $this->guard->resolveEarningsAccount($agent);

        $this->assertEquals($existing->id, $account->id);
        $this->assertEquals(999.99, (float) $account->available_balance);
    }

    public function test_pre_withdrawal_check_passes()
    {
        $user = \App\Models\User::factory()->create();
        $account = EarningsAccount::factory()->create([
            'user_id' => $user->id,
            'available_balance' => 1000,
        ]);
        $agent = Agent::factory()->create(['user_id' => $user->id, 'status' => 'active']);

        $result = $this->guard->preWithdrawalCheck($agent, 500, 'alipay');

        $this->assertTrue($result['passed']);
        $this->assertEmpty($result['reasons']);
        $this->assertFalse($result['needs_review']);
    }

    public function test_pre_withdrawal_check_insufficient_balance()
    {
        $user = \App\Models\User::factory()->create();
        $account = EarningsAccount::factory()->create([
            'user_id' => $user->id,
            'available_balance' => 100,
        ]);
        $agent = Agent::factory()->create(['user_id' => $user->id, 'status' => 'active']);

        $result = $this->guard->preWithdrawalCheck($agent, 500, 'alipay');

        $this->assertFalse($result['passed']);
        $this->assertStringContainsString('余额不足', implode('', $result['reasons']));
    }

    public function test_pre_withdrawal_check_below_minimum()
    {
        $user = \App\Models\User::factory()->create();
        $account = EarningsAccount::factory()->create([
            'user_id' => $user->id,
            'available_balance' => 1000,
        ]);
        $agent = Agent::factory()->create(['user_id' => $user->id, 'status' => 'active']);

        $result = $this->guard->preWithdrawalCheck($agent, 50, 'alipay');

        $this->assertFalse($result['passed']);
        $this->assertStringContainsString('最低提现金额', implode('', $result['reasons']));
    }

    public function test_pre_withdrawal_check_negative_balance()
    {
        $user = \App\Models\User::factory()->create();
        $account = EarningsAccount::factory()->create([
            'user_id' => $user->id,
            'available_balance' => 1000,
            'metadata' => ['negative_balance' => 200.00, 'negative_balance_since' => now()->toIso8601String()],
        ]);
        $agent = Agent::factory()->create(['user_id' => $user->id, 'status' => 'active']);

        $result = $this->guard->preWithdrawalCheck($agent, 500, 'alipay');

        $this->assertFalse($result['passed']);
        $this->assertStringContainsString('负余额', implode('', $result['reasons']));
    }

    public function test_pre_withdrawal_check_suspended_agent()
    {
        $user = \App\Models\User::factory()->create();
        $account = EarningsAccount::factory()->create([
            'user_id' => $user->id,
            'available_balance' => 1000,
        ]);
        $agent = Agent::factory()->create(['user_id' => $user->id, 'status' => 'suspended']);

        $result = $this->guard->preWithdrawalCheck($agent, 500, 'alipay');

        $this->assertFalse($result['passed']);
        $this->assertStringContainsString('状态异常', implode('', $result['reasons']));
    }

    public function test_pre_withdrawal_check_large_amount_needs_review()
    {
        $user = \App\Models\User::factory()->create();
        $account = EarningsAccount::factory()->create([
            'user_id' => $user->id,
            'available_balance' => 10000,
        ]);
        $agent = Agent::factory()->create(['user_id' => $user->id, 'status' => 'active']);

        $result = $this->guard->preWithdrawalCheck($agent, 6000, 'alipay');

        $this->assertTrue($result['passed']);
        $this->assertTrue($result['needs_review']);
    }

    public function test_pre_withdrawal_check_alipay_over_limit()
    {
        $user = \App\Models\User::factory()->create();
        $account = EarningsAccount::factory()->create([
            'user_id' => $user->id,
            'available_balance' => 100000,
        ]);
        $agent = Agent::factory()->create(['user_id' => $user->id, 'status' => 'active']);

        $result = $this->guard->preWithdrawalCheck($agent, 60000, 'alipay');

        $this->assertFalse($result['passed']);
        $this->assertStringContainsString('50,000', implode('', $result['reasons']));
    }

    // ──────────────── 冻结与释放 ────────────────

    public function test_freeze_commission_and_release_cycle()
    {
        $user = \App\Models\User::factory()->create();
        $account = EarningsAccount::factory()->create([
            'user_id' => $user->id,
        ]);
        $agent = Agent::factory()->create(['user_id' => $user->id, 'status' => 'active']);
        $settlement = \App\Models\CommissionSettlement::factory()->create([
            'agent_id' => $agent->id,
            'commission_amount' => 500.00,
            'status' => 'pending',
        ]);

        $this->guard->freezeCommission($account, $settlement);

        $fresh = $account->fresh();
        $this->assertEquals(500.00, (float) $fresh->pending_balance);
        $this->assertEquals(500.00, (float) $fresh->frozen_amount);
        $this->assertEquals(0, (float) $fresh->available_balance);

        // 调整冻结时间
        \App\Models\Commission::where('earnings_account_id', $account->id)
            ->update(['frozen_until' => now()->subDay()]);

        $count = $this->guard->releaseExpiredFreezes();
        $this->assertEquals(1, $count);

        $fresh = $account->fresh();
        $this->assertEquals(0, (float) $fresh->pending_balance);
        $this->assertEquals(500.00, (float) $fresh->available_balance);
    }

    // ──────────────── 负余额抵扣 ────────────────

    public function test_deduct_negative_balance()
    {
        $account = EarningsAccount::factory()->create([
            'metadata' => ['negative_balance' => 300.00, 'negative_balance_since' => now()->toIso8601String()],
        ]);

        // 1000 中有 300 抵扣负余额，返回 700
        $remaining = $this->guard->deductNegativeBalance($account, 1000.00);
        $this->assertEquals(700.00, $remaining);

        // 负余额应已清除
        $fresh = $account->fresh();
        $meta = $fresh->metadata ?? [];
        $this->assertEquals(0, (float) ($meta['negative_balance'] ?? 0));
    }

    public function test_deduct_negative_balance_partial()
    {
        $account = EarningsAccount::factory()->create([
            'metadata' => ['negative_balance' => 500.00, 'negative_balance_since' => now()->toIso8601String()],
        ]);

        // 200 全部抵扣负余额，剩余 0
        $remaining = $this->guard->deductNegativeBalance($account, 200.00);
        $this->assertEquals(0, $remaining);

        $fresh = $account->fresh();
        $meta = $fresh->metadata ?? [];
        $this->assertEquals(300.00, (float) ($meta['negative_balance'] ?? 0));
    }

    public function test_deduct_negative_no_balance()
    {
        $account = EarningsAccount::factory()->create();

        $remaining = $this->guard->deductNegativeBalance($account, 1000.00);
        $this->assertEquals(1000.00, $remaining);
    }
}
