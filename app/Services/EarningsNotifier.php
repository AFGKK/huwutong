<?php

namespace App\Services;

use App\Mail\CommissionNotification;
use App\Models\Agent;
use App\Models\CommissionPayout;
use App\Models\CommissionSettlement;
use App\Models\EarningsAccount;
use App\Models\SubscriptionAgent;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * 收益通知服务 M2-128
 *
 * 为佣金结算系统提供六类通知：
 * 1. 新佣金入账通知
 * 2. 冻结期满解冻通知
 * 3. 提现状态变更通知
 * 4. 月度收益报告邮件
 * 5. 收益阈值告警
 * 6. 负余额预警通知
 */
class EarningsNotifier
{
    public function __construct(
        protected NotificationService $notificationService,
        protected MultiChannelNotifier $multiChannelNotifier,
    ) {}

    /**
     * 1. 新佣金入账通知
     *
     * 当佣金结算成功并冻结后触发，告知代理入账金额和预计解冻日期。
     */
    public function notifyCommissionCredited(
        Agent $agent,
        CommissionSettlement $settlement,
        float $actualAmount,
        string $frozenUntil,
        bool $deductedNegative = false,
        float $deductedAmount = 0,
    ): void {
        $user = $agent->user;
        if (! $user) {
            return;
        }

        $title = __('app.earnings_notifier.new_commission_credited');
        $content = $deductedNegative
            ? __('app.earnings_notifier.commission_credited_with_deduction', ['amount' => $actualAmount, 'deducted' => $deductedAmount, 'frozen_until' => $frozenUntil])
            : __('app.earnings_notifier.commission_credited_normal', ['amount' => $actualAmount, 'frozen_until' => $frozenUntil]);

        $payload = [
            'type' => 'commission_credited',
            'agent_id' => $agent->id,
            'settlement_id' => $settlement->id,
            'amount' => $actualAmount,
            'deducted_negative' => $deductedNegative,
            'deducted_amount' => $deductedAmount,
            'frozen_until' => $frozenUntil,
            'action_url' => '/agent/commission',
            'action_text' => __('app.earnings_notifier.view_commission_details'),
        ];

        $this->sendEarningNotification(
            $user, $title, $content, 'commission_credited', $payload
        );
    }

    /**
     * 2. 冻结期满解冻通知
     *
     * 佣金从 pending → available 后告知代理佣金已可提现。
     */
    public function notifyCommissionReleased(
        Agent $agent,
        float $amount,
        int $commissionCount,
    ): void {
        $user = $agent->user;
        if (! $user) {
            return;
        }

        $title = __('app.earnings_notifier.commission_released');
        $content = __('app.earnings_notifier.commission_released_content', ['count' => $commissionCount, 'amount' => $amount]);

        $payload = [
            'type' => 'commission_released',
            'agent_id' => $agent->id,
            'amount' => $amount,
            'commission_count' => $commissionCount,
            'action_url' => '/agent/commission',
            'action_text' => __('app.earnings_notifier.go_withdraw'),
        ];

        $this->sendEarningNotification(
            $user, $title, $content, 'commission_released', $payload
        );
    }

    /**
     * 3. 提现状态变更通知
     *
     * 提现申请状态变化时通知代理（提交/审批通过/打款完成/拒绝/失败）。
     */
    public function notifyPayoutStatusChanged(
        Agent $agent,
        CommissionPayout $payout,
        string $oldStatus,
        string $newStatus,
    ): void {
        $user = $agent->user;
        if (! $user) {
            return;
        }

        $statusLabels = [
            'pending' => __('app.earnings_notifier.status_pending'),
            'pending_review' => __('app.earnings_notifier.status_pending_review'),
            'approved' => __('app.earnings_notifier.status_approved'),
            'processing' => __('app.earnings_notifier.status_processing'),
            'completed' => __('app.earnings_notifier.status_completed'),
            'failed' => __('app.earnings_notifier.status_failed'),
            'cancelled' => __('app.earnings_notifier.status_cancelled'),
            'rejected' => __('app.earnings_notifier.status_rejected'),
        ];

        $oldLabel = $statusLabels[$oldStatus] ?? $oldStatus;
        $newLabel = $statusLabels[$newStatus] ?? $newStatus;

        $title = __('app.earnings_notifier.payout_status_changed');
        $content = __('app.earnings_notifier.payout_status_generic', ['amount' => $payout->amount, 'old' => $oldLabel, 'new' => $newLabel]);

        if ($newStatus === 'completed') {
            $content = __('app.earnings_notifier.payout_completed', ['amount' => $payout->net_amount]);
        } elseif ($newStatus === 'failed') {
            $content = __('app.earnings_notifier.payout_failed', ['amount' => $payout->amount]);
        } elseif ($newStatus === 'rejected') {
            $content = $payout->notes ? __('app.earnings_notifier.payout_rejected', ['amount' => $payout->amount, 'reason' => $payout->notes]) : __('app.earnings_notifier.payout_rejected_no_reason', ['amount' => $payout->amount]);
        }

        $payload = [
            'type' => 'payout_status_changed',
            'agent_id' => $agent->id,
            'payout_id' => $payout->id,
            'amount' => $payout->amount,
            'net_amount' => $payout->net_amount,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'action_url' => '/agent/payouts',
            'action_text' => __('app.earnings_notifier.view_payout_records'),
        ];

        $this->sendEarningNotification(
            $user, $title, $content, 'payout_status', $payload
        );
    }

    /**
     * 4. 月度收益报告
     *
     * 发送上月收益汇总通知，包括入账、解冻、提现等统计。
     */
    public function sendMonthlyEarningReport(
        Agent $agent,
        array $stats,
    ): void {
        $user = $agent->user;
        if (! $user) {
            return;
        }

        $period = $stats['period'] ?? now()->subMonth()->format('Y-m');
        $title = __('app.earnings_notifier.monthly_report_title', ['period' => $period]);
            $content = __('app.earnings_notifier.monthly_report_content', ['period' => $period, 'credited' => $stats['total_credited'], 'released' => $stats['total_released'], 'withdrawn' => $stats['total_withdrawn'], 'available' => $stats['available_balance'], 'pending' => $stats['pending_balance']]);

        $payload = [
            'type' => 'monthly_report',
            'agent_id' => $agent->id,
            'period' => $period,
            'stats' => $stats,
            'action_url' => '/agent/commission/report?period=' . $period,
            'action_text' => __('app.earnings_notifier.view_detailed_report'),
        ];

        $this->sendEarningNotification(
            $user, $title, $content, 'monthly_report', $payload
        );
    }

    /**
     * 5. 收益阈值告警
     *
     * 当月收益达到设定阈值时发送告警通知（高收益激励）。
     */
    public function notifyEarningThresholdReached(
        Agent $agent,
        float $currentMonthEarning,
        float $threshold,
    ): void {
        $user = $agent->user;
        if (! $user) {
            return;
        }

        $title = __('app.earnings_notifier.threshold_title');
        $content = __('app.earnings_notifier.threshold_content', ['earning' => $currentMonthEarning, 'threshold' => $threshold]);

        $payload = [
            'type' => 'threshold_reached',
            'agent_id' => $agent->id,
            'current_month_earning' => $currentMonthEarning,
            'threshold' => $threshold,
            'action_url' => '/agent/commission',
            'action_text' => __('app.earnings_notifier.view_earnings'),
        ];

        $this->sendEarningNotification(
            $user, $title, $content, 'threshold_reached', $payload, true
        );
    }

    /**
     * 6. 负余额预警通知
     *
     * 账户产生负余额时及时通知代理尽快偿还。
     */
    public function notifyNegativeBalanceWarning(
        Agent $agent,
        float $negativeAmount,
        int $daysOverdue = 0,
    ): void {
        $user = $agent->user;
        if (! $user) {
            return;
        }

        $title = __('app.earnings_notifier.negative_balance_title');
            $content = $daysOverdue > 0
                ? __('app.earnings_notifier.negative_balance_overdue', ['amount' => $negativeAmount, 'days' => $daysOverdue])
                : __('app.earnings_notifier.negative_balance_normal', ['amount' => $negativeAmount]);

        $payload = [
            'type' => 'negative_balance_warning',
            'agent_id' => $agent->id,
            'negative_amount' => $negativeAmount,
            'days_overdue' => $daysOverdue,
            'action_url' => '/agent/commission',
            'action_text' => __('app.earnings_notifier.view_details'),
        ];

        $this->sendEarningNotification(
            $user, $title, $content, 'negative_balance', $payload
        );
    }

    /**
     * 统一发送收益类通知
     *
     * 走站内信 + 邮件双渠道，通过 MultiChannelNotifier 编排。
     */
    protected function sendEarningNotification(
        User $user,
        string $title,
        string $content,
        string $type,
        array $payload = [],
        bool $forceMail = false,
    ): void {
        // 1. 站内信通知（始终发送）
        $this->notificationService->send(
            $user->id,
            $type,
            $title,
            $content,
            $payload,
            $user->tenant_id,
        );

        // 2. 邮件通知（根据用户偏好或强制发送）
        $channels = ['database'];
        if ($forceMail || $this->shouldSendMail($user, $type)) {
            $channels[] = 'mail';
        }

        // 使用 MultiChannelNotifier 发送邮件（站内信已在上面单独发了，这里只发 mail）
        if (in_array('mail', $channels)) {
            try {
                $this->sendMailNotification($user, $title, $content, $payload);
            } catch (\Throwable $e) {
                Log::error("收益通知邮件发送失败", [
                    'user_id' => $user->id,
                    'type' => $type,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * 发送邮件通知（使用专用模板）
     */
    protected function sendMailNotification(User $user, string $title, string $content, array $payload = []): void
    {
        if (! $user->email || ! filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        Mail::to($user->email)->queue(new CommissionNotification(
            title: $title,
            content: $content,
            payload: $payload,
            userName: $user->name,
        ));
    }

    /**
     * 判断是否应发送邮件
     */
    protected function shouldSendMail(User $user, string $type): bool
    {
        // 默认佣金类通知都发送邮件
        $earningTypes = [
            'commission_credited',
            'commission_released',
            'payout_status',
            'monthly_report',
            'threshold_reached',
            'negative_balance',
        ];

        if (! in_array($type, $earningTypes)) {
            return false;
        }

        // 检查用户偏好
        if ($user->relationLoaded('notificationPreference') && $user->notificationPreference) {
            $prefs = $user->notificationPreference->channels ?? [];
            if (! empty($prefs) && isset($prefs[$type])) {
                return in_array('mail', (array) $prefs[$type]);
            }
        }

        // 默认发送邮件
        return true;
    }

    /**
     * 批量发送月度收益报告给所有活跃代理
     *
     * @return int 发送数量
     */
    public function sendBulkMonthlyReports(string $period): int
    {
        $count = 0;

        Agent::where('status', 'active')
            ->whereHas('user', function ($q) {
                $q->whereNotNull('email');
            })
            ->chunk(100, function ($agents) use ($period, &$count) {
                foreach ($agents as $agent) {
                    try {
                        $stats = $this->buildMonthlyStats($agent, $period);
                        $this->sendMonthlyEarningReport($agent, $stats);
                        $count++;
                    } catch (\Throwable $e) {
                        Log::error("月度报告生成失败 agent_id={$agent->id}", [
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        return $count;
    }

    /**
     * 检查并发送收益阈值告警
     *
     * @return int 触发的告警数
     */
    public function checkAndNotifyThresholds(): int
    {
        $thresholds = [1000, 5000, 10000, 50000, 100000]; // 里程碑阈值
        $count = 0;

        Agent::where('status', 'active')
            ->chunk(100, function ($agents) use ($thresholds, &$count) {
                foreach ($agents as $agent) {
                    $monthEarning = CommissionSettlement::where('agent_id', $agent->id)
                        ->where('period', now()->format('Y-m'))
                        ->whereIn('status', ['pending', 'released'])
                        ->sum('commission_amount');

                    foreach ($thresholds as $threshold) {
                        if ($monthEarning >= $threshold && ! $this->isThresholdNotified($agent->id, $threshold)) {
                            $this->notifyEarningThresholdReached($agent, $monthEarning, $threshold);
                            $this->markThresholdNotified($agent->id, $threshold);
                            $count++;
                        }
                    }
                }
            });

        return $count;
    }

    /**
     * 检查并发送负余额预警
     *
     * @return int 发送的预警数
     */
    public function checkAndNotifyNegativeBalances(): int
    {
        $count = 0;

        // Use a broader query that works across MySQL and SQLite
        // Instead of relying on JSON path comparison, get all accounts with metadata
        EarningsAccount::whereNotNull('metadata')
            ->chunk(50, function ($accounts) use (&$count) {
                foreach ($accounts as $account) {
                    $metadata = $account->metadata ?? [];
                    $negativeBalance = (float) ($metadata['negative_balance'] ?? 0);

                    if ($negativeBalance <= 0) {
                        continue;
                    }

                    $since = $metadata['negative_balance_since'] ?? now();
                    $daysOverdue = (int) \Carbon\Carbon::parse($since)->startOfDay()->diffInDays(now()->startOfDay());

                    $agent = Agent::where('user_id', $account->user_id)->first();
                    if (! $agent) {
                        continue;
                    }

                    // 仅在关键时间点发送：第1天、第7天、第15天、第30天
                    $notifyDays = [1, 7, 15, 30];
                    if (in_array($daysOverdue, $notifyDays)) {
                        $this->notifyNegativeBalanceWarning($agent, $negativeBalance, $daysOverdue);
                        $count++;
                    }
                }
            });

        return $count;
    }

    /**
     * 构建代理的月度收益统计
     */
    protected function buildMonthlyStats(Agent $agent, string $period): array
    {
        return [
            'period' => $period,
            'total_credited' => CommissionSettlement::where('agent_id', $agent->id)
                ->where('period', $period)
                ->sum('commission_amount'),
            'total_released' => CommissionSettlement::where('agent_id', $agent->id)
                ->where('period', $period)
                ->where('status', 'released')
                ->sum('commission_amount'),
            'total_withdrawn' => $agent->payouts()
                ->whereYear('created_at', substr($period, 0, 4))
                ->whereMonth('created_at', substr($period, 5, 2))
                ->where('status', 'completed')
                ->sum('amount'),
            'available_balance' => $agent->available_balance,
            'pending_balance' => $agent->pending_balance ?? 0,
        ];
    }

    /**
     * 检查是否已通知过该阈值
     */
    protected function isThresholdNotified(int $agentId, float $threshold): bool
    {
        $notification = \App\Models\Notification::where('user_id', function ($q) use ($agentId) {
            $q->select('user_id')->from('agents')->where('id', $agentId);
        })
            ->where('type', 'threshold_reached')
            ->where('payload->threshold', $threshold)
            ->exists();

        return $notification;
    }

    /**
     * 标记阈值已通知
     */
    protected function markThresholdNotified(int $agentId, float $threshold): void
    {
        // 通知本身已存库，不需要额外标记
    }
}
