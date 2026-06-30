<?php

namespace App\Services;

use App\Models\EarningsAccount;
use App\Models\PayoutBatch;
use App\Models\Agent;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * 提现管理服务
 *
 * M3-72 多渠道提现管理，支持：
 * - 用户发起提现（银行卡/支付宝/微信/PayPal）
 * - 管理员审核/驳回
 * - 批量打款（同渠道合并）
 * - 打款凭证上传
 * - 多渠道日限额校验
 * - 提现统计看板
 */
class WithdrawalService
{
    const CHANNELS = ['bank', 'alipay', 'wechat', 'paypal'];
    const STATUSES = ['pending_review', 'pending', 'processing', 'completed', 'failed', 'rejected', 'cancelled'];

    // 各渠道单笔限额
    const CHANNEL_LIMITS = [
        'bank' => ['min' => 100, 'max' => 500000],
        'alipay' => ['min' => 1, 'max' => 50000],
        'wechat' => ['min' => 1, 'max' => 50000],
        'paypal' => ['min' => 1, 'max' => 10000],
    ];

    // 各渠道每日限额
    const DAILY_LIMITS = [
        'bank' => 500000,
        'alipay' => 50000,
        'wechat' => 50000,
        'paypal' => 10000,
    ];

    // 各渠道手续费率
    const FEE_RATES = [
        'bank' => 0.01,    // 1%
        'alipay' => 0.006, // 0.6%
        'wechat' => 0.006, // 0.6%
        'paypal' => 0.044, // 4.4% + fixed fee handled separately
    ];

    // 需要人工审核的最低金额
    const REVIEW_THRESHOLD = 5000;

    public function __construct(
        protected CommissionRiskGuard $riskGuard,
    ) {}

    /**
     * 用户发起提现
     *
     * @throws \RuntimeException
     */
    public function requestWithdrawal(User $user, array $data): Withdrawal
    {
        $channel = $data['channel'];

        if (!in_array($channel, self::CHANNELS)) {
            throw new \RuntimeException('不支持的提现渠道');
        }

        $amount = (float) $data['amount'];
        $limits = self::CHANNEL_LIMITS[$channel];

        if ($amount < $limits['min']) {
            throw new \RuntimeException("{$channel}渠道最低提现金额为 {$limits['min']} 元");
        }
        if ($amount > $limits['max']) {
            throw new \RuntimeException("{$channel}渠道单笔提现上限为 {$limits['max']} 元");
        }

        // 检查每日限额
        $dailyUsed = $this->getDailyWithdrawnByUser($user, $channel);
        $dailyLimit = self::DAILY_LIMITS[$channel];
        if (($dailyUsed + $amount) > $dailyLimit) {
            throw new \RuntimeException("{$channel}渠道今日已用 ¥{$dailyUsed}，剩余可用 ¥" . max(0, $dailyLimit - $dailyUsed));
        }

        // 提现前风控检查 (M2-127b)
        $agent = Agent::firstOrCreate(
            ['user_id' => $user->id],
            [
                'agent_code' => 'USR' . strtoupper(Str::random(8)),
                'level' => 'regular',
                'status' => 'active',
            ]
        );
        $riskCheck = $this->riskGuard->preWithdrawalCheck($agent, $amount, $channel);
        if (!$riskCheck['passed']) {
            throw new \RuntimeException('风控检查未通过：' . implode('；', $riskCheck['reasons']));
        }

        // 获取收益账户
        $account = $this->resolveEarningsAccount($user);
        if ((float) $account->available_balance < $amount) {
            throw new \RuntimeException('可提现余额不足');
        }

        // 计算手续费
        $fee = $this->calculateFee($amount, $channel);
        $netAmount = round($amount - $fee, 2);

        // 检查是否需要审核
        $needsReview = $amount >= self::REVIEW_THRESHOLD;

        return DB::transaction(function () use ($user, $account, $data, $amount, $fee, $netAmount, $channel, $needsReview) {
            $withdrawalData = [
                'earnings_account_id' => $account->id,
                'user_id' => $user->id,
                'amount' => $amount,
                'fee' => $fee,
                'net_amount' => $netAmount,
                'channel' => $channel,
                'status' => $needsReview ? 'pending_review' : 'pending',
            ];

            // 填充渠道特定字段
            $withdrawalData = $this->fillChannelFields($withdrawalData, $data);

            $withdrawal = Withdrawal::create($withdrawalData);

            // 扣减可用余额
            $account->decrement('available_balance', $amount);
            $account->increment('total_withdrawn', $amount);

            // 风控审计
            $this->riskGuard->logAudit('withdrawal_requested', [
                'withdrawal_id' => $withdrawal->id,
                'user_id' => $user->id,
                'amount' => $amount,
                'channel' => $channel,
                'needs_review' => $needsReview,
            ]);

            return $withdrawal;
        });
    }

    /**
     * 管理员审核提现
     */
    public function reviewWithdrawal(Withdrawal $withdrawal, User $reviewer, string $action, ?string $remark = null): Withdrawal
    {
        if (!in_array($withdrawal->status, ['pending_review', 'pending'])) {
            throw new \RuntimeException('当前状态不可审核');
        }

        return DB::transaction(function () use ($withdrawal, $reviewer, $action, $remark) {
            if ($action === 'approve') {
                $withdrawal->update([
                    'status' => 'pending',
                    'reviewed_by' => $reviewer->id,
                    'reviewed_at' => now(),
                    'remark' => $remark ?? $withdrawal->remark,
                ]);
            } elseif ($action === 'reject') {
                // 驳回：退还余额
                $account = $withdrawal->earningsAccount;
                $account->increment('available_balance', $withdrawal->amount);
                $account->decrement('total_withdrawn', $withdrawal->amount);

                $withdrawal->update([
                    'status' => 'rejected',
                    'reviewed_by' => $reviewer->id,
                    'reviewed_at' => now(),
                    'remark' => $remark,
                ]);
            } else {
                throw new \RuntimeException('审核操作无效');
            }

            $this->riskGuard->logAudit('withdrawal_reviewed', [
                'withdrawal_id' => $withdrawal->id,
                'reviewer_id' => $reviewer->id,
                'action' => $action,
                'remark' => $remark,
            ]);

            return $withdrawal->fresh();
        });
    }

    /**
     * 管理员标记打款（单笔）
     */
    public function markAsCompleted(Withdrawal $withdrawal, array $data): Withdrawal
    {
        if (!in_array($withdrawal->status, ['pending', 'processing'])) {
            throw new \RuntimeException('当前状态不可标记为完成');
        }

        $updateData = [
            'status' => 'completed',
            'transaction_id' => $data['transaction_id'] ?? null,
            'completed_at' => now(),
        ];

        if (isset($data['proof'])) {
            $updateData['proof'] = $this->storeProof($data['proof'], $withdrawal->id);
        }

        $withdrawal->update($updateData);

        $this->riskGuard->logAudit('withdrawal_completed', [
            'withdrawal_id' => $withdrawal->id,
            'transaction_id' => $updateData['transaction_id'],
        ]);

        return $withdrawal->fresh();
    }

    /**
     * 管理员标记打款失败
     */
    public function markAsFailed(Withdrawal $withdrawal, string $reason): Withdrawal
    {
        if (!in_array($withdrawal->status, ['pending', 'processing'])) {
            throw new \RuntimeException('当前状态不可标记为失败');
        }

        return DB::transaction(function () use ($withdrawal, $reason) {
            // 退还余额
            $account = $withdrawal->earningsAccount;
            $account->increment('available_balance', $withdrawal->amount);
            $account->decrement('total_withdrawn', $withdrawal->amount);

            $withdrawal->update([
                'status' => 'failed',
                'failure_reason' => $reason,
            ]);

            $this->riskGuard->logAudit('withdrawal_failed', [
                'withdrawal_id' => $withdrawal->id,
                'reason' => $reason,
            ]);

            return $withdrawal->fresh();
        });
    }

    /**
     * 取消提现（用户自行取消）
     */
    public function cancelWithdrawal(Withdrawal $withdrawal, User $user): Withdrawal
    {
        if ($withdrawal->user_id !== $user->id) {
            throw new \RuntimeException('无权操作');
        }

        if (!in_array($withdrawal->status, ['pending_review', 'pending'])) {
            throw new \RuntimeException('当前状态不可取消');
        }

        return DB::transaction(function () use ($withdrawal) {
            $account = $withdrawal->earningsAccount;
            $account->increment('available_balance', $withdrawal->amount);
            $account->decrement('total_withdrawn', $withdrawal->amount);

            $withdrawal->update(['status' => 'cancelled']);

            return $withdrawal->fresh();
        });
    }

    /**
     * 批量打款
     */
    public function createPayoutBatch(string $channel, ?string $title = null, ?array $withdrawalIds = null): PayoutBatch
    {
        $query = Withdrawal::where('channel', $channel)
            ->whereIn('status', ['pending'])
            ->whereNull('batch_no');

        if ($withdrawalIds) {
            $query->whereIn('id', $withdrawalIds);
        }

        $withdrawals = $query->get();

        if ($withdrawals->isEmpty()) {
            throw new \RuntimeException('没有可打款的提现记录');
        }

        return DB::transaction(function () use ($channel, $title, $withdrawals) {
            $batchNo = PayoutBatch::generateBatchNo();

            $batch = PayoutBatch::create([
                'batch_no' => $batchNo,
                'title' => $title ?? "{$channel}打款-" . now()->format('Ymd'),
                'channel' => $channel,
                'total_count' => $withdrawals->count(),
                'total_amount' => $withdrawals->sum('net_amount'),
                'total_fee' => $withdrawals->sum('fee'),
                'status' => 'pending',
                'created_by' => auth()->id(),
            ]);

            // 更新提现记录关联批次
            Withdrawal::whereIn('id', $withdrawals->pluck('id'))
                ->update([
                    'batch_no' => $batchNo,
                    'status' => 'processing',
                ]);

            $this->riskGuard->logAudit('payout_batch_created', [
                'batch_no' => $batchNo,
                'channel' => $channel,
                'count' => $withdrawals->count(),
                'total_amount' => $withdrawals->sum('net_amount'),
            ]);

            return $batch->fresh();
        });
    }

    /**
     * 完成打款批次
     */
    public function completePayoutBatch(PayoutBatch $batch, array $failedIds = []): PayoutBatch
    {
        return DB::transaction(function () use ($batch, $failedIds) {
            $failedIds = array_map('intval', $failedIds);

            if (!empty($failedIds)) {
                // 部分失败：失败的标记为 failed，其余标记为 completed
                Withdrawal::where('batch_no', $batch->batch_no)
                    ->whereIn('id', $failedIds)
                    ->update(['status' => 'failed', 'failure_reason' => '打款失败']);

                Withdrawal::where('batch_no', $batch->batch_no)
                    ->whereNotIn('id', $failedIds)
                    ->update(['status' => 'completed', 'completed_at' => now()]);

                // 失败的退还余额
                foreach (Withdrawal::whereIn('id', $failedIds)->get() as $w) {
                    $account = $w->earningsAccount;
                    $account->increment('available_balance', $w->amount);
                    $account->decrement('total_withdrawn', $w->amount);
                }

                $batch->update([
                    'status' => 'partial_failed',
                    'processed_at' => now(),
                ]);
            } else {
                // 全部成功
                Withdrawal::where('batch_no', $batch->batch_no)
                    ->update([
                        'status' => 'completed',
                        'completed_at' => now(),
                    ]);

                $batch->update([
                    'status' => 'completed',
                    'processed_at' => now(),
                ]);
            }

            // 刷新批次统计
            $batch->refresh();
            $batch->update([
                'total_count' => Withdrawal::where('batch_no', $batch->batch_no)->count(),
            ]);

            $this->riskGuard->logAudit('payout_batch_completed', [
                'batch_no' => $batch->batch_no,
                'failed_count' => count($failedIds),
                'status' => $batch->status,
            ]);

            return $batch->fresh();
        });
    }

    /**
     * 上传打款凭证
     */
    public function uploadProof(Withdrawal $withdrawal, UploadedFile $file): Withdrawal
    {
        $path = $this->storeProof($file, $withdrawal->id);

        $withdrawal->update(['proof' => $path]);

        return $withdrawal->fresh();
    }

    /**
     * 获取提现统计数据
     */
    public function getStats(): array
    {
        $today = now()->startOfDay();
        $monthStart = now()->startOfMonth();

        $pendingReview = Withdrawal::where('status', 'pending_review')->count();
        $pendingAmount = (float) Withdrawal::whereIn('status', ['pending_review', 'pending'])->sum('amount');
        $processingCount = Withdrawal::where('status', 'processing')->count();

        $todayCompleted = (float) Withdrawal::where('status', 'completed')
            ->where('completed_at', '>=', $today)
            ->sum('amount');

        $monthCompleted = (float) Withdrawal::where('status', 'completed')
            ->where('completed_at', '>=', $monthStart)
            ->sum('amount');

        $channelStats = [];
        foreach (self::CHANNELS as $ch) {
            $channelStats[$ch] = [
                'pending_count' => Withdrawal::where('channel', $ch)->whereIn('status', ['pending_review', 'pending'])->count(),
                'today_amount' => (float) Withdrawal::where('channel', $ch)->where('status', 'completed')
                    ->where('completed_at', '>=', $today)->sum('amount'),
                'month_amount' => (float) Withdrawal::where('channel', $ch)->where('status', 'completed')
                    ->where('completed_at', '>=', $monthStart)->sum('amount'),
                'total_amount' => (float) Withdrawal::where('channel', $ch)->where('status', 'completed')->sum('amount'),
            ];
        }

        return [
            'pending_review_count' => $pendingReview,
            'pending_amount' => $pendingAmount,
            'processing_count' => $processingCount,
            'today_completed_amount' => $todayCompleted,
            'month_completed_amount' => $monthCompleted,
            'channel_stats' => $channelStats,
        ];
    }

    /**
     * 获取用户提现统计数据
     */
    public function getUserStats(User $user): array
    {
        $account = $this->resolveEarningsAccount($user);

        $pendingCount = Withdrawal::where('user_id', $user->id)
            ->whereIn('status', ['pending_review', 'pending', 'processing'])
            ->count();

        $completedAmount = (float) Withdrawal::where('user_id', $user->id)
            ->where('status', 'completed')
            ->sum('amount');

        return [
            'available_balance' => (float) $account->available_balance,
            'pending_balance' => (float) $account->pending_balance,
            'total_withdrawn' => (float) $account->total_withdrawn,
            'frozen_amount' => (float) $account->frozen_amount,
            'pending_withdrawal_count' => $pendingCount,
            'completed_withdrawal_amount' => $completedAmount,
        ];
    }

    /**
     * 验证渠道账户信息
     */
    public function validateChannelAccount(string $channel, array $accountInfo): array
    {
        $errors = [];

        switch ($channel) {
            case 'bank':
                if (empty($accountInfo['bank_name'])) $errors[] = '银行名称不能为空';
                if (empty($accountInfo['bank_account_name'])) $errors[] = '开户姓名不能为空';
                if (empty($accountInfo['bank_account_no'])) $errors[] = '银行卡号不能为空';
                break;
            case 'alipay':
                if (empty($accountInfo['alipay_account'])) $errors[] = '支付宝账号不能为空';
                break;
            case 'wechat':
                if (empty($accountInfo['wechat_account'])) $errors[] = '微信账号不能为空';
                break;
            case 'paypal':
                if (empty($accountInfo['paypal_email'])) $errors[] = 'PayPal邮箱不能为空';
                if (!empty($accountInfo['paypal_email']) && !filter_var($accountInfo['paypal_email'], FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'PayPal邮箱格式不正确';
                }
                break;
        }

        return $errors;
    }

    // ── Protected Helpers ──

    protected function fillChannelFields(array $data, array $input): array
    {
        $channel = $data['channel'];

        switch ($channel) {
            case 'bank':
                $data['bank_name'] = $input['bank_name'] ?? null;
                $data['bank_branch'] = $input['bank_branch'] ?? null;
                $data['bank_account_name'] = $input['bank_account_name'] ?? null;
                $data['bank_account_no'] = $input['bank_account_no'] ?? null;
                $data['channel_account'] = $input['bank_account_no'] ?? null;
                break;
            case 'alipay':
                $data['alipay_account'] = $input['alipay_account'] ?? null;
                $data['channel_account'] = $input['alipay_account'] ?? null;
                break;
            case 'wechat':
                $data['wechat_account'] = $input['wechat_account'] ?? null;
                $data['channel_account'] = $input['wechat_account'] ?? null;
                break;
            case 'paypal':
                $data['paypal_email'] = $input['paypal_email'] ?? null;
                $data['channel_account'] = $input['paypal_email'] ?? null;
                break;
        }

        return $data;
    }

    protected function calculateFee(float $amount, string $channel): float
    {
        $rate = self::FEE_RATES[$channel] ?? 0.01;

        // PayPal has additional fixed fee
        $fee = round($amount * $rate, 2);
        if ($channel === 'paypal') {
            $fee += 0.39; // PayPal fixed fee per transaction
        }

        return round($fee, 2);
    }

    protected function storeProof(UploadedFile $file, int $withdrawalId): string
    {
        $path = $file->storeAs(
            "payout-proofs/{$withdrawalId}",
            time() . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension(),
            'public'
        );

        return $path;
    }

    protected function resolveEarningsAccount(User $user): EarningsAccount
    {
        return $this->riskGuard->resolveEarningsAccount(
            \App\Models\Agent::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'agent_code' => 'USR' . strtoupper(Str::random(8)),
                    'level' => 'regular',
                    'status' => 'active',
                ]
            )
        );
    }

    protected function getDailyWithdrawnByUser(User $user, string $channel): float
    {
        return (float) Withdrawal::where('user_id', $user->id)
            ->where('channel', $channel)
            ->where('created_at', '>=', now()->startOfDay())
            ->whereIn('status', ['pending_review', 'pending', 'processing', 'completed'])
            ->sum('amount');
    }

    // ─── M3-72 增强功能 ───

    /**
     * 重试失败的提现记录
     * 将失败/驳回的提现重置为 pending 状态，退还余额恢复可用
     */
    public function retryWithdrawal(Withdrawal $withdrawal, User $operator): Withdrawal
    {
        if (!in_array($withdrawal->status, ['failed', 'rejected'])) {
            throw new \RuntimeException('只有失败或驳回的提现可以重试');
        }

        return DB::transaction(function () use ($withdrawal, $operator) {
            // 恢复可用余额（如果之前被扣减过）
            if ($withdrawal->status === 'failed') {
                $account = $withdrawal->earningsAccount;
                // 检查是否已退还（失败时会退还，reject也会）
            }

            // 重置状态
            $withdrawal->update([
                'status' => 'pending',
                'failure_reason' => null,
                'batch_no' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ]);

            $this->riskGuard->logAudit('withdrawal_retried', [
                'withdrawal_id' => $withdrawal->id,
                'operator_id' => $operator->id,
            ]);

            return $withdrawal->fresh();
        });
    }

    /**
     * 批量重试失败提现
     */
    public function batchRetryWithdrawals(array $withdrawalIds, User $operator): array
    {
        $results = [];
        foreach ($withdrawalIds as $id) {
            try {
                $w = Withdrawal::findOrFail($id);
                $results[] = [
                    'id' => $id,
                    'success' => true,
                    'withdrawal' => $this->retryWithdrawal($w, $operator),
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'id' => $id,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }
        return $results;
    }

    /**
     * T+30 自动解冻：将所有已过冻结期的 pending_balance 释放到 available_balance
     * 调用 CommissionRiskGuard 的 releaseCommission 方法
     */
    public function releasePendingBalances(): int
    {
        // 委托 CommissionRiskGuard 处理 T+30 解冻
        // releaseExpiredFreezes() 自动查找所有到期的 frozen Commission
        // 将 pending_balance → available_balance
        return $this->riskGuard->releaseExpiredFreezes();
    }

    /**
     * 提现风控检查详情（供前端展示）
     */
    public function getWithdrawalRiskDetail(User $user, float $amount, string $channel): array
    {
        $agent = Agent::firstOrCreate(
            ['user_id' => $user->id],
            [
                'agent_code' => 'USR' . strtoupper(Str::random(8)),
                'level' => 'regular',
                'status' => 'active',
            ]
        );

        $check = $this->riskGuard->preWithdrawalCheck($agent, $amount, $channel);
        $account = $this->resolveEarningsAccount($user);

        return [
            'risk_passed' => $check['passed'],
            'risk_reasons' => $check['reasons'] ?? [],
            'needs_review' => $amount >= self::REVIEW_THRESHOLD,
            'available_balance' => (float) $account->available_balance,
            'pending_balance' => (float) $account->pending_balance,
            'daily_used' => $this->getDailyWithdrawnByUser($user, $channel),
            'daily_limit' => self::DAILY_LIMITS[$channel] ?? 0,
            'fee' => $this->calculateFee($amount, $channel),
            'net_amount' => round($amount - $this->calculateFee($amount, $channel), 2),
        ];
    }
}
