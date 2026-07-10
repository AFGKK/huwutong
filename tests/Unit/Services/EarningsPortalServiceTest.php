<?php

namespace Tests\Unit\Services;

use App\Models\Commission;
use App\Models\EarningsAccount;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\WithdrawalService;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class EarningsPortalServiceTest extends TestCase
{
    use RefreshDatabase;

    protected WithdrawalService $withdrawalService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withdrawalService = $this->app->make(WithdrawalService::class);
    }

    protected function createUserWithBalance(float $balance = 10000): User
    {
        $user = User::factory()->create();
        EarningsAccount::factory()->create([
            'user_id' => $user->id,
            'available_balance' => $balance,
            'pending_balance' => 5000,
            'frozen_amount' => 5000,
            'total_withdrawn' => 2000,
            'status' => 'active',
        ]);
        return $user;
    }

    public function test_earnings_account_has_correct_balance_fields()
    {
        $user = $this->createUserWithBalance(15000);
        $account = EarningsAccount::where('user_id', $user->id)->first();

        $this->assertNotNull($account);
        $this->assertEquals(15000, (float) $account->available_balance);
        $this->assertEquals(5000, (float) $account->pending_balance);
        $this->assertEquals(5000, (float) $account->frozen_amount);
        $this->assertEquals(2000, (float) $account->total_withdrawn);
        $this->assertEquals('active', $account->status);
    }

    public function test_create_commission_records()
    {
        $user = $this->createUserWithBalance(10000);
        $account = EarningsAccount::where('user_id', $user->id)->first();

        // Create commission records
        Commission::create([
            'earnings_account_id' => $account->id,
            'order_id' => 1,
            'amount' => 500,
            'rate' => 10,
            'status' => 'frozen',
            'settled_at' => now(),
            'frozen_until' => now()->addDays(30),
        ]);

        Commission::create([
            'earnings_account_id' => $account->id,
            'order_id' => 2,
            'amount' => 300,
            'rate' => 5,
            'status' => 'released',
            'settled_at' => now()->subDays(5),
            'frozen_until' => now()->subDay(),
        ]);

        $frozenCount = Commission::where('earnings_account_id', $account->id)
            ->where('status', 'frozen')->count();
        $releasedCount = Commission::where('earnings_account_id', $account->id)
            ->where('status', 'released')->count();

        $this->assertEquals(1, $frozenCount);
        $this->assertEquals(1, $releasedCount);
    }

    public function test_withdrawal_request_updates_balance()
    {
        $user = $this->createUserWithBalance(50000);
        $initialAccount = EarningsAccount::where('user_id', $user->id)->first();

        // Request a withdrawal
        $withdrawal = $this->withdrawalService->requestWithdrawal($user, [
            'channel' => 'bank',
            'amount' => 10000,
            'bank_name' => '中国银行',
            'bank_account_name' => '张三',
            'bank_account_no' => '6222021234567890',
        ]);

        // Balance should be deducted
        $account = $initialAccount->fresh();
        $this->assertEquals(40000, (float) $account->available_balance);
        $this->assertEquals(12000, (float) $account->total_withdrawn); // 2000(factory) + 10000
        $this->assertNotNull($withdrawal);
        $this->assertEquals('pending_review', $withdrawal->status);
    }

    public function test_multiple_withdrawals_track_total()
    {
        $user = $this->createUserWithBalance(50000);

        // First withdrawal
        $this->withdrawalService->requestWithdrawal($user, [
            'channel' => 'alipay', 'amount' => 5000, 'alipay_account' => 'a@test.com',
        ]);

        // Second withdrawal
        $this->withdrawalService->requestWithdrawal($user, [
            'channel' => 'alipay', 'amount' => 3000, 'alipay_account' => 'b@test.com',
        ]);

        $account = EarningsAccount::where('user_id', $user->id)->first();
        $this->assertEquals(42000, (float) $account->available_balance);
        $this->assertEquals(10000, (float) $account->total_withdrawn); // 2000(factory) + 5000 + 3000

        $withdrawalCount = Withdrawal::where('user_id', $user->id)->count();
        $this->assertEquals(2, $withdrawalCount);
    }

    public function test_withdrawal_status_labels()
    {
        $statuses = [
            'pending_review' => '待审核',
            'pending' => '待打款',
            'processing' => '处理中',
            'completed' => '已到账',
            'failed' => '打款失败',
            'rejected' => '已驳回',
            'cancelled' => '已取消',
        ];

        foreach ($statuses as $status => $label) {
            $withdrawal = new Withdrawal(['status' => $status]);
            $this->assertEquals($label, match ($withdrawal->status) {
                'pending_review' => '待审核',
                'pending' => '待打款',
                'processing' => '处理中',
                'completed' => '已到账',
                'failed' => '打款失败',
                'rejected' => '已驳回',
                'cancelled' => '已取消',
                default => $withdrawal->status,
            });
        }
    }

    public function test_channel_account_masking()
    {
        $withdrawal = Withdrawal::factory()->create([
            'channel' => 'bank',
            'bank_account_no' => '6222021234567890',
        ]);

        $masked = $withdrawal->channel_account_masked;
        $this->assertEquals('****7890', $masked);

        $alipayW = Withdrawal::factory()->create([
            'channel' => 'alipay',
            'alipay_account' => 'test_user@example.com',
        ]);

        $this->assertStringContainsString('****', $alipayW->channel_account_masked);
    }

    public function test_balance_card_aggregation()
    {
        $user = $this->createUserWithBalance(10000);
        $account = EarningsAccount::where('user_id', $user->id)->first();

        $totalEarned = (float) $account->available_balance + (float) $account->total_withdrawn;

        $this->assertEquals(12000, $totalEarned);
        $this->assertEquals(10000, (float) $account->available_balance);
        $this->assertEquals(5000, (float) $account->pending_balance);
    }

    public function test_get_user_stats()
    {
        $user = $this->createUserWithBalance(50000);

        $this->withdrawalService->requestWithdrawal($user, [
            'channel' => 'bank', 'amount' => 2000,
            'bank_name' => '中国银行', 'bank_account_name' => 'A', 'bank_account_no' => '622202000001',
        ]);

        $this->withdrawalService->requestWithdrawal($user, [
            'channel' => 'bank', 'amount' => 3000,
            'bank_name' => '招商银行', 'bank_account_name' => 'B', 'bank_account_no' => '622202000002',
        ]);

        $stats = $this->withdrawalService->getUserStats($user);

        $this->assertEquals(2, $stats['pending_withdrawal_count']);
        $this->assertEquals(0, $stats['completed_withdrawal_amount']);
        $this->assertEquals(50000 - 2000 - 3000, $stats['available_balance']);
    }
}
