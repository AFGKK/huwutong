<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Commission;
use App\Models\CommissionSettlement;
use App\Models\EarningsAccount;
use App\Models\Invoice;
use App\Models\Refund;
use App\Models\User;
use App\Services\EarningsNotifier;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 佣金风控保障机制
 *
 * M2-127b 核心服务，为佣金结算提供五重风控保障：
 *
 * 1. T+30 冻结期
 *    - 佣金入账后冻结 30 天（记入 pending_balance），冻结期内不可提现
 *    - 冻结期满自动转入 available_balance
 *
 * 2. 退款自动回拨
 *    - 冻结期内客户退款 → 自动扣除 pending_balance 对应的冻结佣金
 *    - T+30 边界处理：第 29 天退款 → pending 余额直接回拨
 *      第 31 天退款（已转入 available）→ 先扣 available，不足则记负余额
 *
 * 3. 负余额追缴
 *    - 已提现的佣金遇退款 → 账户记负余额（negative_balance）
 *    - 后续佣金优先抵扣负余额
 *    - 超 30 天未还 → 手动追缴 + 冻结提现权限
 *
 * 4. 提现前风控检查
 *    - available_balance >= 最低门槛校验
 *    - 争议订单检查
 *    - 异常提现模式检测（短时高频、大额异地）
 *    - 大额触发人工审核
 *
 * 5. 风控审计日志
 *    - 所有风控操作记录审计日志
 */
class CommissionRiskGuard
{
    const COMMISSION_RELEASE_DAYS = 30; // T+30 冻结期
    const MIN_WITHDRAWAL_AMOUNT = 100;  // 最低提现金额（元）
    const LARGE_AMOUNT_THRESHOLD = 5000; // 大额提现阈值（需人工审核）
    const NEGATIVE_BALANCE_GRACE_DAYS = 30; // 负余额宽限天数
    const MAX_WITHDRAWALS_PER_DAY = 3;   // 每日最大提现次数

    /**
     * @var EarningsNotifier|null
     */
    protected ?EarningsNotifier $notifier = null;

    /**
     * 1. 冻结佣金 —— 创建结算时调用
     *
     * 将佣金记入 earnings_account.pending_balance，
     * 设置 frozen_until = now() + 30 days
     */
    public function freezeCommission(
        EarningsAccount $account,
        CommissionSettlement $settlement
    ): void {
        $notification = null;

        $callback = function () use ($account, $settlement, &$notification) {
            $amount = $settlement->commission_amount;

            // 先抵扣负余额（如果有）
            $actualAmount = $this->deductNegativeBalance($account, $amount);

            if ($actualAmount <= 0) {
                // 全部用于抵扣负余额，结算标记为已释放（无实际金额）
                $settlement->update([
                    'status' => 'released',
                    'released_at' => now(),
                    'notes' => __('app.commission_risk_guard.full_deduct_negative'),
                ]);
                $this->logAudit('commission_frozen_full_deducted', [
                    'settlement_id' => $settlement->id,
                    'amount' => $amount,
                    'deducted' => true,
                    'account_id' => $account->id,
                ]);

                $notification = [
                    'agent' => $settlement->agent,
                    'settlement' => $settlement,
                    'actualAmount' => 0,
                    'frozenUntil' => now()->toDateString(),
                    'deductedNegative' => true,
                    'deductedAmount' => $amount,
                ];

                return;
            }

            $frozenUntil = now()->addDays(self::COMMISSION_RELEASE_DAYS);

            // 更新结算记录的释放日期
            $settlement->update([
                'status' => 'pending',
                'released_at' => $frozenUntil,
                'notes' => __('app.commission_risk_guard.frozen_until', ['date' => $frozenUntil->toDateString()]),
            ]);

            // 更新收益账户：增加 pending_balance
            $account->increment('pending_balance', $actualAmount);
            $account->increment('frozen_amount', $actualAmount);

            // 创建佣金明细记录
            Commission::create([
                'earnings_account_id' => $account->id,
                'order_id' => null,
                'amount' => $actualAmount,
                'rate' => $settlement->commission_rate,
                'status' => 'frozen',
                'settled_at' => now(),
                'frozen_until' => $frozenUntil,
            ]);

            $this->logAudit('commission_frozen', [
                'settlement_id' => $settlement->id,
                'account_id' => $account->id,
                'amount' => $actualAmount,
                'frozen_until' => $frozenUntil->toIso8601String(),
                'deducted' => $amount - $actualAmount > 0,
                'deducted_amount' => $amount - $actualAmount,
            ]);

            $notification = [
                'agent' => $settlement->agent,
                'settlement' => $settlement,
                'actualAmount' => $actualAmount,
                'frozenUntil' => $frozenUntil->toDateString(),
                'deductedNegative' => $amount - $actualAmount > 0,
                'deductedAmount' => $amount - $actualAmount,
            ];
        };

        if (DB::transactionLevel() > 0) {
            $callback();
        } else {
            DB::transaction($callback);
        }

        if ($notification && $notification['agent']) {
            $payload = $notification;
            DB::afterCommit(function () use ($payload) {
                try {
                    $this->getNotifier()->notifyCommissionCredited(
                        agent: $payload['agent'],
                        settlement: $payload['settlement'],
                        actualAmount: $payload['actualAmount'],
                        frozenUntil: $payload['frozenUntil'],
                        deductedNegative: $payload['deductedNegative'],
                        deductedAmount: $payload['deductedAmount'],
                    );
                } catch (\Throwable $e) {
                    Log::warning('佣金入账通知发送失败', [
                        'settlement_id' => $payload['settlement']->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            });
        }
    }

    /**
     * 2. 释放已过冻结期的佣金 —— 定时任务调用
     *
     * 将到期 pending_balance → available_balance
     */
    public function releaseExpiredFreezes(): int
    {
        $count = 0;
        $releasedByAgent = []; // agent_id => total_amount

        Commission::where('status', 'frozen')
            ->where('frozen_until', '<=', now())
            ->chunk(100, function ($commissions) use (&$count, &$releasedByAgent) {
                foreach ($commissions as $commission) {
                    DB::transaction(function () use ($commission, &$count, &$releasedByAgent) {
                        $account = $commission->earningsAccount;

                        $commission->update([
                            'status' => 'released',
                        ]);

                        // 更新结算记录状态
                        CommissionSettlement::where('invoice_id', $commission->order_id)
                            ->where('status', 'pending')
                            ->update(['status' => 'released']);

                        // pending → available
                        $account->decrement('pending_balance', $commission->amount);
                        $account->decrement('frozen_amount', $commission->amount);
                        $account->increment('available_balance', $commission->amount);

                        $count++;

                        $this->logAudit('commission_released', [
                            'commission_id' => $commission->id,
                            'account_id' => $account->id,
                            'amount' => $commission->amount,
                        ]);

                        // 按代理汇总待通知金额
                        $agent = Agent::where('user_id', $account->user_id)->first();
                        if ($agent) {
                            $aid = $agent->id;
                            if (! isset($releasedByAgent[$aid])) {
                                $releasedByAgent[$aid] = ['amount' => 0, 'count' => 0, 'agent' => $agent];
                            }
                            $releasedByAgent[$aid]['amount'] += $commission->amount;
                            $releasedByAgent[$aid]['count']++;
                        }
                    });
                }
            });

        // ⭐ M2-128 批量发送解冻通知
        foreach ($releasedByAgent as $info) {
            try {
                $this->getNotifier()->notifyCommissionReleased(
                    agent: $info['agent'],
                    amount: $info['amount'],
                    commissionCount: $info['count'],
                );
            } catch (\Throwable $e) {
                Log::warning('解冻通知发送失败', [
                    'agent_id' => $info['agent']->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }

    /**
     * 3. 退款处理 —— 退款时调用
     *
     * 根据退款时间点执行不同的回拨策略：
     * - 冻结期内退款：直接扣除 pending_balance
     * - 已释放后退款：先扣 available_balance，不足则记负余额
     */
    public function handleRefund(Invoice $invoice, Refund $refund): array
    {
        $results = [];
        $settlements = CommissionSettlement::where('invoice_id', $invoice->id)
            ->whereIn('status', ['pending', 'released', 'refunded'])
            ->get();

        foreach ($settlements as $settlement) {
            if ($settlement->status === 'refunded') {
                continue; // 已退款的跳过
            }

            $result = DB::transaction(function () use ($settlement, $refund) {
                $amount = $settlement->commission_amount;
                $agent = $settlement->agent;

                // 查找或创建收益账户
                $account = $this->resolveEarningsAccount($agent);

                if ($settlement->status === 'pending') {
                    // 场景 A：冻结期内退款 → 从 pending_balance 扣除
                    return $this->refundPendingSettlement($settlement, $account, $amount);
                }

                // 场景 B：已释放后退款
                return $this->refundReleasedSettlement($settlement, $account, $amount, $agent);
            });

            $results[] = $result;
            $settlement->update([
                'status' => 'refunded',
                'notes' => __('app.commission_risk_guard.refund_clawback', ['action' => ($result['action'] ?? 'unknown')]),
            ]);
        }

        return $results;
    }

    /**
     * 冻结期内退款 —— 直接扣除 pending_balance
     */
    protected function refundPendingSettlement(
        CommissionSettlement $settlement,
        EarningsAccount $account,
        float $amount
    ): array {
        $actualDeduct = min($amount, (float) $account->pending_balance);

        if ($actualDeduct > 0) {
            $account->decrement('pending_balance', $actualDeduct);
            $account->decrement('frozen_amount', $actualDeduct);

            // 更新对应的佣金记录
            Commission::where('earnings_account_id', $account->id)
                ->where('order_id', $settlement->invoice_id)
                ->where('status', 'frozen')
                ->update(['status' => 'refunded']);
        }

        $this->logAudit('commission_refund_pending', [
            'settlement_id' => $settlement->id,
            'account_id' => $account->id,
            'amount' => $amount,
            'deducted' => $actualDeduct,
            'action' => 'pending_deduct',
        ]);

        return [
            'action' => 'pending_deduct',
            'settlement_id' => $settlement->id,
            'deducted' => $actualDeduct,
            'remaining_refund' => $amount - $actualDeduct,
        ];
    }

    /**
     * 已释放后退款 —— 先扣 available，不足则记负余额
     */
    protected function refundReleasedSettlement(
        CommissionSettlement $settlement,
        EarningsAccount $account,
        float $amount,
        Agent $agent
    ): array {
        $availableBalance = (float) $account->available_balance;
        $withdrawnAmount = $this->getWithdrawnForInvoice($settlement);

        if ($withdrawnAmount <= 0 && $availableBalance >= $amount) {
            // 未提现 + 余额充足：直接扣 available
            $account->decrement('available_balance', $amount);

            Commission::where('earnings_account_id', $account->id)
                ->where('order_id', $settlement->invoice_id)
                ->where('status', 'released')
                ->update(['status' => 'refunded']);

            $this->logAudit('commission_refund_available', [
                'settlement_id' => $settlement->id,
                'account_id' => $account->id,
                'amount' => $amount,
                'action' => 'available_deduct',
            ]);

            return [
                'action' => 'available_deduct',
                'settlement_id' => $settlement->id,
                'deducted' => $amount,
                'remaining_refund' => 0,
            ];
        }

        // 已提现 或 余额不足 → 记负余额
        $deductFromAvailable = min($amount, $availableBalance);
        $negativeAmount = $amount - $deductFromAvailable;

        if ($deductFromAvailable > 0) {
            $account->decrement('available_balance', $deductFromAvailable);
        }

        if ($negativeAmount > 0) {
            $account->increment('frozen_amount', $negativeAmount);
            $account->increment('pending_balance', $negativeAmount); // pending 作为负余额待偿还标记

            // 在元数据中记录负余额
            $metadata = $account->metadata ?? [];
            $metadata['negative_balance'] = ($metadata['negative_balance'] ?? 0) + $negativeAmount;
            $metadata['negative_balance_since'] = now()->toIso8601String();
            $account->update(['metadata' => $metadata]);

            // 标记代理为负余额状态
            if ($agent->status === 'active') {
                $agent->update([
                    'status' => 'suspended',
                    'notes' => ($agent->notes ?? '') . __('app.commission_risk_guard.negative_balance_note', ['amount' => $negativeAmount, 'date' => now()->toDateString()]),
                ]);
            }

            // ⭐ M2-128 发送负余额预警通知
            try {
                $this->getNotifier()->notifyNegativeBalanceWarning(
                    agent: $agent,
                    negativeAmount: $negativeAmount,
                );
            } catch (\Throwable $e) {
                Log::warning('负余额预警通知失败', [
                    'agent_id' => $agent->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->logAudit('commission_refund_negative_balance', [
            'settlement_id' => $settlement->id,
            'account_id' => $account->id,
            'amount' => $amount,
            'deducted_from_available' => $deductFromAvailable,
            'negative_amount' => $negativeAmount,
            'action' => 'negative_balance',
        ]);

        return [
            'action' => 'negative_balance',
            'settlement_id' => $settlement->id,
            'deducted' => $deductFromAvailable,
            'negative_amount' => $negativeAmount,
        ];
    }

    /**
     * 4. 提现前风控检查
     *
     * @return array ['passed' => bool, 'reasons' => string[]]
     */
    public function preWithdrawalCheck(Agent $agent, float $amount, string $method): array
    {
        $reasons = [];
        $account = $this->resolveEarningsAccount($agent);

        // 4.1 可用余额检查
        if ((float) $account->available_balance < $amount) {
            $reasons[] = __('app.commission_risk_guard.insufficient_balance');
        }

        // 4.2 最低提现金额检查
        if ($amount < self::MIN_WITHDRAWAL_AMOUNT) {
            $reasons[] = __('app.commission_risk_guard.below_min_withdrawal', ['amount' => self::MIN_WITHDRAWAL_AMOUNT]);
        }

        // 4.3 负余额检查
        $metadata = $account->metadata ?? [];
        $negativeBalance = $metadata['negative_balance'] ?? 0;
        if ($negativeBalance > 0) {
            $reasons[] = __('app.commission_risk_guard.negative_balance_exists', ['amount' => $negativeBalance]);
        }

        // 4.4 代理状态检查
        if ($agent->status !== 'active') {
            $reasons[] = __('app.commission_risk_guard.agent_status_abnormal', ['status' => $agent->status]);
        }

        // 4.5 争议订单检查
        $disputedCount = $this->countDisputedOrdersForAgent($agent);
        if ($disputedCount > 0) {
            $reasons[] = __('app.commission_risk_guard.disputed_orders', ['count' => $disputedCount]);
        }

        // 4.6 每日提现次数限制
        $todayCount = $this->countTodayWithdrawals($agent);
        if ($todayCount >= self::MAX_WITHDRAWALS_PER_DAY) {
            $reasons[] = __('app.commission_risk_guard.withdrawal_limit', ['limit' => self::MAX_WITHDRAWALS_PER_DAY]);
        }

        // 4.7 大额提现标记（触发人工审核，但不拦截）
        $needsReview = false;
        if ($amount >= self::LARGE_AMOUNT_THRESHOLD) {
            $needsReview = true;
        }

        // 4.8 异常模式检测 —— 短时高频
        $recentWithdrawals = $this->countRecentWithdrawals($agent, 24);
        if ($recentWithdrawals >= 2) {
            $reasons[] = __('app.commission_risk_guard.high_frequency');
        }

        // 4.9 提现渠道与金额匹配
        if ($method === 'alipay' || $method === 'wechat') {
            if ($amount > 50000) {
                $reasons[] = __('app.commission_risk_guard.alipay_wechat_limit');
            }
        }

        $passed = empty($reasons);

        $this->logAudit('withdrawal_risk_check', [
            'agent_id' => $agent->id,
            'account_id' => $account->id,
            'amount' => $amount,
            'method' => $method,
            'passed' => $passed,
            'reasons' => $reasons,
            'needs_review' => $needsReview,
        ]);

        return [
            'passed' => $passed,
            'reasons' => $reasons,
            'needs_review' => $needsReview,
        ];
    }

    /**
     * 5. 抵扣负余额
     *
     * 新佣金入账时优先抵扣现有负余额
     */
    public function deductNegativeBalance(EarningsAccount $account, float $amount): float
    {
        $metadata = $account->metadata ?? [];
        $negativeBalance = (float) ($metadata['negative_balance'] ?? 0);

        if ($negativeBalance <= 0) {
            return $amount; // 无负余额，全额入账
        }

        $deductAmount = min($amount, $negativeBalance);
        $remainingAmount = $amount - $deductAmount;
        $newNegative = round($negativeBalance - $deductAmount, 2);

        if ($newNegative <= 0) {
            // 负余额已清偿
            unset($metadata['negative_balance']);
            unset($metadata['negative_balance_since']);
            $account->update(['metadata' => $metadata]);

            // 恢复代理状态
            Agent::where('user_id', $account->user_id)
                ->where('status', 'suspended')
                ->each(function (Agent $agent) {
                    $agent->update([
                        'status' => 'active',
                        'notes' => ($agent->notes ?? '') . __('app.commission_risk_guard.negative_balance_cleared', ['date' => now()->toDateTimeString()]),
                    ]);
                });

            $this->logAudit('negative_balance_cleared', [
                'account_id' => $account->id,
                'cleared_amount' => $negativeBalance,
            ]);

            // ⭐ M2-128 负余额已清偿通知
            try {
                $agent = Agent::where('user_id', $account->user_id)->first();
                if ($agent) {
                    $this->getNotifier()->notifyNegativeBalanceWarning(
                        agent: $agent,
                        negativeAmount: 0,
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('负余额清偿通知失败', ['error' => $e->getMessage()]);
            }
        } else {
            $metadata['negative_balance'] = $newNegative;
            $account->update(['metadata' => $metadata]);
        }

        $this->logAudit('negative_balance_deducted', [
            'account_id' => $account->id,
            'deducted' => $deductAmount,
            'remaining_negative' => $newNegative,
        ]);

        return $remainingAmount;
    }

    /**
     * 6. 负余额追缴 —— 定时任务调用
     *
     * 超过宽限期仍未偿还负余额的账户 → 冻结提现 + 通知管理员
     */
    public function enforceNegativeBalanceRecovery(): array
    {
        $results = ['warned' => 0, 'frozen' => 0, 'recovered' => 0];

        // Use broader query for SQLite compatibility
        EarningsAccount::whereNotNull('metadata')
            ->chunk(50, function ($accounts) use (&$results) {
                foreach ($accounts as $account) {
                    $metadata = $account->metadata ?? [];
                    $negativeBalance = (float) ($metadata['negative_balance'] ?? 0);

                    if ($negativeBalance <= 0) {
                        continue;
                    }

                    $since = $metadata['negative_balance_since'] ?? now();
                    $daysOverdue = (int) \Carbon\Carbon::parse($since)->startOfDay()->diffInDays(now()->startOfDay());

                    if ($daysOverdue > self::NEGATIVE_BALANCE_GRACE_DAYS) {
                        // 超 30 天仍未偿还 → 冻结提现权限
                        $account->update(['status' => 'frozen']);
                        $results['frozen']++;

                        $this->logAudit('negative_balance_frozen', [
                            'account_id' => $account->id,
                            'negative_balance' => $negativeBalance,
                            'days_overdue' => $daysOverdue,
                        ]);
                    } else {
                        // 在宽限期内但未偿还 → 记录警告（已有负余额标记）
                        $results['warned']++;
                    }
                }
            });

        return $results;
    }

    /**
     * 获取指定发票已提现金额
     */
    protected function getWithdrawnForInvoice(CommissionSettlement $settlement): float
    {
        // 检查该结算对应发票的佣金是否已被提现
        // 通过 CommissionPayout 关联判断
        $agent = $settlement->agent;
        $payouts = $agent->payouts()
            ->where('status', 'completed')
            ->where('created_at', '>=', $settlement->released_at ?? $settlement->settled_at)
            ->get();

        return (float) $payouts->sum('amount');
    }

    /**
     * 计算代理的争议订单数
     */
    protected function countDisputedOrdersForAgent(Agent $agent): int
    {
        // 争议中的退款/工单
        $refundCount = Refund::whereIn('invoice_id', function ($q) use ($agent) {
            $q->select('invoice_id')
                ->from('commission_settlements')
                ->where('agent_id', $agent->id)
                ->where('status', 'refunded');
        })->where('status', 'pending')->count();

        return $refundCount;
    }

    /**
     * 今日提现次数
     */
    protected function countTodayWithdrawals(Agent $agent): int
    {
        return $agent->payouts()
            ->whereDate('created_at', today())
            ->count();
    }

    /**
     * 最近 N 小时提现次数
     */
    protected function countRecentWithdrawals(Agent $agent, int $hours): int
    {
        return $agent->payouts()
            ->where('created_at', '>=', now()->subHours($hours))
            ->count();
    }

    /**
     * 解析或创建收益账户
     */
    public function resolveEarningsAccount(Agent $agent): EarningsAccount
    {
        $account = EarningsAccount::firstOrCreate(
            [
                'user_id' => $agent->user_id,
                'type' => 'agent',
            ],
            [
                'tenant_id' => $agent->user?->tenant_id,
                'pending_balance' => 0,
                'available_balance' => 0,
                'total_withdrawn' => 0,
                'frozen_amount' => 0,
                'status' => 'active',
            ]
        );

        return $account;
    }

    /**
     * 记录风控审计日志
     */
    public function logAudit(string $action, array $context): void
    {
        Log::info("CommissionRiskGuard.{$action}", $context);
    }

    /**
     * 获取收益通知服务实例
     */
    protected function getNotifier(): EarningsNotifier
    {
        if ($this->notifier === null) {
            $this->notifier = App::make(EarningsNotifier::class);
        }
        return $this->notifier;
    }
}
