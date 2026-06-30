<?php

namespace Tests\Unit\Services;

use App\Models\EarningsAccount;
use App\Models\PayoutBatch;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\WithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class WithdrawalServiceTest extends TestCase
{
    use RefreshDatabase;

    protected WithdrawalService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(WithdrawalService::class);
    }

    protected function createUserWithBalance(float $balance = 10000): User
    {
        $user = User::factory()->create();
        EarningsAccount::factory()->create([
            'user_id' => $user->id,
            'available_balance' => $balance,
            'pending_balance' => 0,
            'frozen_amount' => 0,
            'total_withdrawn' => 0,
        ]);
        return $user;
    }

    public function test_request_bank_withdrawal_success()
    {
        $user = $this->createUserWithBalance(50000);

        $withdrawal = $this->service->requestWithdrawal($user, [
            'channel' => 'bank',
            'amount' => 10000,
            'bank_name' => '中国银行',
            'bank_branch' => '北京分行',
            'bank_account_name' => '张三',
            'bank_account_no' => '6222021234567890',
        ]);

        $this->assertNotNull($withdrawal);
        $this->assertEquals('bank', $withdrawal->channel);
        $this->assertEquals(10000, (float) $withdrawal->amount);
        $this->assertEquals(100, (float) $withdrawal->fee); // 1%
        $this->assertEquals(9900, (float) $withdrawal->net_amount);
        $this->assertEquals('pending_review', $withdrawal->status); // >= 5000 => needs review
        $this->assertEquals('中国银行', $withdrawal->bank_name);
        $this->assertEquals('张三', $withdrawal->bank_account_name);
        $this->assertEquals('6222021234567890', $withdrawal->bank_account_no);

        // Balance deducted
        $account = $withdrawal->earningsAccount->fresh();
        $this->assertEquals(40000, (float) $account->available_balance);
        $this->assertEquals(10000, (float) $account->total_withdrawn);
    }

    public function test_request_alipay_withdrawal_under_review_threshold()
    {
        $user = $this->createUserWithBalance(50000);

        $withdrawal = $this->service->requestWithdrawal($user, [
            'channel' => 'alipay',
            'amount' => 1000,
            'alipay_account' => 'test@example.com',
        ]);

        $this->assertNotNull($withdrawal);
        $this->assertEquals('alipay', $withdrawal->channel);
        $this->assertEquals(1000, (float) $withdrawal->amount);
        $this->assertEquals(6, (float) $withdrawal->fee); // 0.6%
        $this->assertEquals(994, (float) $withdrawal->net_amount);
        $this->assertEquals('pending', $withdrawal->status); // < 5000 => no review
        $this->assertEquals('test@example.com', $withdrawal->alipay_account);
    }

    public function test_request_wechat_withdrawal()
    {
        $user = $this->createUserWithBalance(50000);

        $withdrawal = $this->service->requestWithdrawal($user, [
            'channel' => 'wechat',
            'amount' => 2000,
            'wechat_account' => 'wechat_user_001',
        ]);

        $this->assertEquals('wechat', $withdrawal->channel);
        $this->assertEquals(2000, (float) $withdrawal->amount);
        $this->assertEquals('wechat_user_001', $withdrawal->wechat_account);
    }

    public function test_request_paypal_withdrawal()
    {
        $user = $this->createUserWithBalance(50000);

        $withdrawal = $this->service->requestWithdrawal($user, [
            'channel' => 'paypal',
            'amount' => 500,
            'paypal_email' => 'user@paypal.com',
        ]);

        $this->assertEquals('paypal', $withdrawal->channel);
        $this->assertEquals(500, (float) $withdrawal->amount);
        // fee: 500 * 0.044 + 0.39 = 22 + 0.39 = 22.39
        $this->assertEquals(22.39, (float) $withdrawal->fee);
        $this->assertEquals(477.61, (float) $withdrawal->net_amount);
        $this->assertEquals('user@paypal.com', $withdrawal->paypal_email);
    }

    public function test_request_withdrawal_insufficient_balance()
    {
        $user = $this->createUserWithBalance(100);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('可提现余额不足');

        $this->service->requestWithdrawal($user, [
            'channel' => 'bank',
            'amount' => 500,
            'bank_name' => '中国银行',
            'bank_account_name' => '张三',
            'bank_account_no' => '6222021234567890',
        ]);
    }

    public function test_request_withdrawal_exceeds_channel_limit()
    {
        $user = $this->createUserWithBalance(1000000);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('单笔提现上限');

        $this->service->requestWithdrawal($user, [
            'channel' => 'alipay',
            'amount' => 100000, // alipay max is 50000
            'alipay_account' => 'test@example.com',
        ]);
    }

    public function test_review_approve_withdrawal()
    {
        $user = $this->createUserWithBalance(50000);
        $reviewer = User::factory()->create();

        $withdrawal = $this->service->requestWithdrawal($user, [
            'channel' => 'bank',
            'amount' => 5000,
            'bank_name' => '中国银行',
            'bank_account_name' => '张三',
            'bank_account_no' => '6222021234567890',
        ]);

        $this->assertEquals('pending_review', $withdrawal->status);

        $result = $this->service->reviewWithdrawal($withdrawal, $reviewer, 'approve', '审核通过');

        $this->assertEquals('pending', $result->status);
        $this->assertEquals($reviewer->id, $result->reviewed_by);
        $this->assertNotNull($result->reviewed_at);
    }

    public function test_review_reject_withdrawal_refunds_balance()
    {
        $user = $this->createUserWithBalance(50000);
        $reviewer = User::factory()->create();

        $withdrawal = $this->service->requestWithdrawal($user, [
            'channel' => 'bank',
            'amount' => 5000,
            'bank_name' => '中国银行',
            'bank_account_name' => '张三',
            'bank_account_no' => '6222021234567890',
        ]);

        $result = $this->service->reviewWithdrawal($withdrawal, $reviewer, 'reject', '信息不完整');

        $this->assertEquals('rejected', $result->status);
        $this->assertEquals($reviewer->id, $result->reviewed_by);
        $this->assertNotNull($result->reviewed_at);
        $this->assertEquals('信息不完整', $result->remark);

        // Balance refunded
        $account = $result->earningsAccount->fresh();
        $this->assertEquals(50000, (float) $account->available_balance);
    }

    public function test_mark_as_completed()
    {
        $user = $this->createUserWithBalance(50000);
        $reviewer = User::factory()->create();

        $withdrawal = $this->service->requestWithdrawal($user, [
            'channel' => 'bank',
            'amount' => 1000,
            'bank_name' => '中国银行',
            'bank_account_name' => '张三',
            'bank_account_no' => '6222021234567890',
        ]);

        $result = $this->service->markAsCompleted($withdrawal, [
            'transaction_id' => 'TXN123456',
        ]);

        $this->assertEquals('completed', $result->status);
        $this->assertEquals('TXN123456', $result->transaction_id);
        $this->assertNotNull($result->completed_at);
    }

    public function test_mark_as_failed_refunds_balance()
    {
        $user = $this->createUserWithBalance(50000);
        $reviewer = User::factory()->create();

        $withdrawal = $this->service->requestWithdrawal($user, [
            'channel' => 'bank',
            'amount' => 1000,
            'bank_name' => '中国银行',
            'bank_account_name' => '张三',
            'bank_account_no' => '6222021234567890',
        ]);

        $result = $this->service->markAsFailed($withdrawal, '账户信息错误');

        $this->assertEquals('failed', $result->status);
        $this->assertEquals('账户信息错误', $result->failure_reason);

        // Balance refunded
        $account = $result->earningsAccount->fresh();
        $this->assertEquals(50000, (float) $account->available_balance);
    }

    public function test_cancel_withdrawal_refunds_balance()
    {
        $user = $this->createUserWithBalance(50000);

        $withdrawal = $this->service->requestWithdrawal($user, [
            'channel' => 'bank',
            'amount' => 1000,
            'bank_name' => '中国银行',
            'bank_account_name' => '张三',
            'bank_account_no' => '6222021234567890',
        ]);

        $result = $this->service->cancelWithdrawal($withdrawal, $user);

        $this->assertEquals('cancelled', $result->status);

        // Balance refunded
        $account = $result->earningsAccount->fresh();
        $this->assertEquals(50000, (float) $account->available_balance);
    }

    public function test_cancel_withdrawal_wrong_user_throws()
    {
        $user = $this->createUserWithBalance(50000);
        $otherUser = User::factory()->create();

        $withdrawal = $this->service->requestWithdrawal($user, [
            'channel' => 'bank',
            'amount' => 1000,
            'bank_name' => '中国银行',
            'bank_account_name' => '张三',
            'bank_account_no' => '6222021234567890',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('无权操作');

        $this->service->cancelWithdrawal($withdrawal, $otherUser);
    }

    public function test_create_payout_batch()
    {
        $user = $this->createUserWithBalance(50000);

        // Create 3 pending withdrawals
        for ($i = 0; $i < 3; $i++) {
            $this->service->requestWithdrawal($user, [
                'channel' => 'bank',
                'amount' => 1000,
                'bank_name' => '中国银行',
                'bank_account_name' => "用户{$i}",
                'bank_account_no' => "622202000000{$i}",
            ]);
        }

        // Admin approves them
        $reviewer = User::factory()->create();
        Withdrawal::where('status', 'pending_review')->each(function ($w) use ($reviewer) {
            $this->service->reviewWithdrawal($w, $reviewer, 'approve');
        });

        // Create batch for bank channel
        $batch = $this->service->createPayoutBatch('bank', '银行打款测试');

        $this->assertNotNull($batch);
        $this->assertEquals('bank', $batch->channel);
        $this->assertEquals(3, $batch->total_count);
        $this->assertEquals(2970, (float) $batch->total_amount); // 3 * 1000 - 3 * 10 (fee)
        $this->assertStringStartsWith('PO' . now()->format('Ymd'), $batch->batch_no);
        $this->assertEquals('pending', $batch->status);

        // Withdrawals should now be 'processing'
        $processingCount = Withdrawal::where('channel', 'bank')->where('status', 'processing')->count();
        $this->assertEquals(3, $processingCount);
    }

    public function test_complete_payout_batch_all_success()
    {
        $user = $this->createUserWithBalance(50000);
        $reviewer = User::factory()->create();

        $this->service->requestWithdrawal($user, [
            'channel' => 'alipay', 'amount' => 2000, 'alipay_account' => 'test@test.com',
        ]);
        $this->service->requestWithdrawal($user, [
            'channel' => 'alipay', 'amount' => 3000, 'alipay_account' => 'test2@test.com',
        ]);

        Withdrawal::where('status', 'pending_review')->each(fn($w) => $this->service->reviewWithdrawal($w, $reviewer, 'approve'));

        $batch = $this->service->createPayoutBatch('alipay');

        $completed = $this->service->completePayoutBatch($batch, []);

        $this->assertEquals('completed', $completed->status);
        $this->assertNotNull($completed->processed_at);

        $this->assertEquals(2, Withdrawal::where('batch_no', $batch->batch_no)->where('status', 'completed')->count());
    }

    public function test_complete_payout_batch_partial_failed()
    {
        $user = $this->createUserWithBalance(50000);
        $reviewer = User::factory()->create();

        $w1 = $this->service->requestWithdrawal($user, [
            'channel' => 'bank', 'amount' => 2000, 'bank_name' => '中国银行',
            'bank_account_name' => 'A', 'bank_account_no' => '622202111111',
        ]);
        $w2 = $this->service->requestWithdrawal($user, [
            'channel' => 'bank', 'amount' => 3000, 'bank_name' => '中国银行',
            'bank_account_name' => 'B', 'bank_account_no' => '622202222222',
        ]);

        Withdrawal::where('status', 'pending_review')->each(fn($w) => $this->service->reviewWithdrawal($w, $reviewer, 'approve'));

        $batch = $this->service->createPayoutBatch('bank');

        $completed = $this->service->completePayoutBatch($batch, [$w2->id]);

        $this->assertEquals('partial_failed', $completed->status);

        // w1 should be completed
        $this->assertEquals('completed', $w1->fresh()->status);
        // w2 should be failed, balance refunded
        $this->assertEquals('failed', $w2->fresh()->status);
        $account = $w2->earningsAccount->fresh();
        $this->assertEquals(48000, (float) $account->available_balance); // 50000 - 2000(w1) - 3000(w2) + 3000(refund w2)
    }

    public function test_validate_channel_account_bank()
    {
        $errors = $this->service->validateChannelAccount('bank', []);

        $this->assertContains('银行名称不能为空', $errors);
        $this->assertContains('开户姓名不能为空', $errors);
        $this->assertContains('银行卡号不能为空', $errors);

        $errors = $this->service->validateChannelAccount('bank', [
            'bank_name' => '中国银行',
            'bank_account_name' => '张三',
            'bank_account_no' => '6222021234567890',
        ]);

        $this->assertEmpty($errors);
    }

    public function test_validate_channel_account_paypal_email()
    {
        $errors = $this->service->validateChannelAccount('paypal', ['paypal_email' => 'invalid']);

        $this->assertNotEmpty($errors);

        $errors = $this->service->validateChannelAccount('paypal', ['paypal_email' => 'valid@paypal.com']);

        $this->assertEmpty($errors);
    }

    public function test_get_stats()
    {
        $user = $this->createUserWithBalance(50000);

        // Create some withdrawals with different channels
        $this->service->requestWithdrawal($user, [
            'channel' => 'bank', 'amount' => 5000,
            'bank_name' => '中国银行', 'bank_account_name' => 'A', 'bank_account_no' => '622202000001',
        ]);
        $this->service->requestWithdrawal($user, [
            'channel' => 'alipay', 'amount' => 1000, 'alipay_account' => 'a@test.com',
        ]);

        $stats = $this->service->getStats();

        $this->assertArrayHasKey('pending_review_count', $stats);
        $this->assertArrayHasKey('pending_amount', $stats);
        $this->assertArrayHasKey('channel_stats', $stats);
        $this->assertArrayHasKey('bank', $stats['channel_stats']);
        $this->assertArrayHasKey('alipay', $stats['channel_stats']);
    }

    public function test_get_user_stats()
    {
        $user = $this->createUserWithBalance(50000);

        $this->service->requestWithdrawal($user, [
            'channel' => 'bank', 'amount' => 1000,
            'bank_name' => '中国银行', 'bank_account_name' => 'A', 'bank_account_no' => '622202000001',
        ]);

        $stats = $this->service->getUserStats($user);

        $this->assertArrayHasKey('available_balance', $stats);
        $this->assertArrayHasKey('pending_withdrawal_count', $stats);
        $this->assertArrayHasKey('completed_withdrawal_amount', $stats);
        $this->assertEquals(1, $stats['pending_withdrawal_count']);
    }
}
