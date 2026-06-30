<?php

namespace App\Services;

use App\Mail\RenewalEscalationNotification;
use App\Models\Coupon;
use App\Models\Invoice;
use App\Models\RenewalAttempt;
use App\Models\RenewalConfig;
use App\Models\RenewalEscalation;
use App\Models\Subscription;
use App\Models\User;
use App\Workflows\WorkflowEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * 自动续费失败处理流水线
 *
 * 处理策略：
 * 1次失败 → 3天后重试（换卡或换支付方式）
 * 2次失败 → 7天后重试
 * 3次失败 → 降级套餐 + 通知客户
 * 4次失败 → 人工介入 + 多渠道通知
 * 宽限期结束 → 停用
 */
class RenewalPipelineService
{
    // 重试间隔（秒）
    private const RETRY_INTERVALS = [
        1 => 259200,   // 第1次重试: 3天后
        2 => 604800,   // 第2次重试: 7天后
        3 => 604800,   // 第3次重试: 7天后
    ];

    // 降级策略：第N次失败后降级
    private const DOWNGRADE_AFTER_ATTEMPT = 3;

    // 人工介入：第N次失败后
    private const ESCALATE_AFTER_ATTEMPT = 4;

    // 最大重试次数
    private const MAX_ATTEMPTS = 5;

    public function __construct(
        protected BillingService $billingService,
        protected WorkflowEngine $workflowEngine,
        protected MultiChannelNotifier $notifier,
        protected ?RenewalConfig $config = null,
    ) {
        $this->config = $config ?? RenewalConfig::getActive();
    }

    /**
     * 获取实际配置值
     */
    protected function cfg(string $key, mixed $default = null): mixed
    {
        if ($this->config) {
            $getter = 'get' . Str::studly($key);
            if (method_exists($this->config, $getter)) {
                return $this->config->{$getter}();
            }
            return $this->config->{$key} ?? $default;
        }
        return $default;
    }

    /**
     * 获取最大重试次数
     */
    protected function getMaxAttempts(): int
    {
        return $this->config?->max_attempts ?? self::MAX_ATTEMPTS;
    }

    /**
     * 创建工作流驱动的续费流程
     *
     * 替代传统的 cron 轮询方式，使用 WorkflowEngine 进行编排。
     * 也支持从现有 BillingService 的 processRenewal 调用。
     */
    public function startWorkflowRenewal(Subscription $subscription): ?\App\Models\WorkflowInstance
    {
        try {
            $instance = $this->workflowEngine->start(
                'renewal_pipeline',
                $subscription,
                [
                    'subscription_id' => $subscription->id,
                    'customer_id' => $subscription->customer_id,
                    'amount' => $subscription->price,
                    'currency' => $subscription->currency ?? 'cny',
                    'previous_next_billing' => $subscription->next_billing_at?->toIso8601String(),
                ]
            );

            Log::info('RenewalPipeline: workflow started', [
                'subscription_id' => $subscription->id,
                'workflow_instance_id' => $instance->id,
            ]);

            return $instance;
        } catch (\Throwable $e) {
            Log::error('RenewalPipeline: failed to start workflow', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);

            // 降级到传统流程
            return null;
        }
    }

    /**
     * 处理续费失败（由 BillingService 在 payment failed 时调用）
     */
    public function handleRenewalFailure(
        Subscription $subscription,
        Invoice $invoice,
        string $failureReason,
        ?string $failureDetail = null
    ): RenewalAttempt {
        return DB::transaction(function () use ($subscription, $invoice, $failureReason, $failureDetail) {
            // 获取当前尝试次数
            $attemptCount = RenewalAttempt::where('subscription_id', $subscription->id)
                ->count();

            $attemptNumber = $attemptCount + 1;
            $maxAttempts = $this->getMaxAttempts();

            // 计算下次重试时间
            $nextRetryAt = $this->calculateNextRetry($attemptNumber, $maxAttempts);
            $retryPlan = $this->buildRetryPlan($attemptNumber, $maxAttempts);

            // 记录本次失败
            $attempt = RenewalAttempt::create([
                'subscription_id' => $subscription->id,
                'invoice_id' => $invoice->id,
                'attempt_number' => $attemptNumber,
                'payment_method' => $invoice->payment_method,
                'amount' => $invoice->amount,
                'currency' => $invoice->currency,
                'status' => 'failed',
                'failure_reason' => $failureReason,
                'failure_detail' => $failureDetail,
                'retry_plan' => $retryPlan,
                'escalated' => $attemptNumber >= ($this->cfg('escalate_after_attempt') ?? self::ESCALATE_AFTER_ATTEMPT),
                'attempted_at' => now(),
                'next_retry_at' => $nextRetryAt,
            ]);

            Log::warning('RenewalPipeline: payment failed', [
                'subscription_id' => $subscription->id,
                'attempt_number' => $attemptNumber,
                'reason' => $failureReason,
                'next_retry_at' => $nextRetryAt?->toIso8601String(),
            ]);

            // 发送多渠道通知给客户
            $this->notifyCustomerRenewalFailed($subscription, $attemptNumber, $failureReason);

            // 执行对应策略
            $this->executeFailureStrategy($subscription, $attemptNumber, $failureReason, $maxAttempts);

            // 尝试生成挽留优惠券
            if ($this->config?->retention_coupon_enabled && $attemptNumber >= 2) {
                $this->generateRetentionCoupon($subscription, $attemptNumber);
            }

            return $attempt;
        });
    }

    /**
     * 发送续费失败通知给客户（多渠道）
     */
    protected function notifyCustomerRenewalFailed(Subscription $subscription, int $attemptNumber, string $reason): void
    {
        $user = $subscription->customer?->user;
        if (! $user) return;

        $this->notifier->sendRenewalFailed(
            $user,
            $subscription->plan ?? 'N/A',
            $subscription->price ?? 0,
            $attemptNumber,
            $reason,
        );
    }

    /**
     * 生成挽留优惠券
     */
    protected function generateRetentionCoupon(Subscription $subscription, int $attemptNumber): ?Coupon
    {
        $config = $this->config;
        if (! $config?->retention_coupon_enabled) return null;

        $discountPercent = $config->retention_coupon_discount_percent ?? 10;
        $validDays = $config->retention_coupon_valid_days ?? 30;
        $maxDiscount = $config->retention_coupon_max_discount;

        $code = 'RETENTION-' . strtoupper(Str::random(8)) . '-' . $subscription->id;

        try {
            $coupon = Coupon::create([
                'code' => $code,
                'type' => 'percentage',
                'value' => $discountPercent,
                'max_discount_amount' => $maxDiscount,
                'max_uses' => $config->retention_coupon_max_uses ?? 1,
                'max_uses_per_user' => 1,
                'starts_at' => now(),
                'ends_at' => now()->addDays($validDays),
                'description' => "续费挽留优惠券 — 订阅 #{$subscription->id} 第 {$attemptNumber} 次失败后自动生成",
                'is_active' => true,
                'metadata' => [
                    'generated_by' => 'renewal_pipeline',
                    'subscription_id' => $subscription->id,
                    'attempt_number' => $attemptNumber,
                    'customer_id' => $subscription->customer_id,
                ],
            ]);

            Log::info('RenewalPipeline: retention coupon generated', [
                'coupon_id' => $coupon->id,
                'code' => $code,
                'subscription_id' => $subscription->id,
            ]);

            return $coupon;
        } catch (\Throwable $e) {
            Log::error('RenewalPipeline: failed to generate retention coupon', [
                'error' => $e->getMessage(),
                'subscription_id' => $subscription->id,
            ]);
            return null;
        }
    }

    /**
     * 处理待重试的续费（定时任务）
     */
    public function processRetries(): array
    {
        $stats = ['attempted' => 0, 'succeeded' => 0, 'failed' => 0, 'escalated' => 0];
        $maxAttempts = $this->getMaxAttempts();
        $escalateAfter = $this->cfg('escalate_after_attempt') ?? self::ESCALATE_AFTER_ATTEMPT;

        $pendingAttempts = RenewalAttempt::where('status', 'failed')
            ->where('escalated', false)
            ->whereNotNull('next_retry_at')
            ->where('next_retry_at', '<=', now())
            ->limit(50)
            ->get();

        foreach ($pendingAttempts as $attempt) {
            $stats['attempted']++;

            try {
                // 尝试更换支付方式（如果有其他支付方式）
                $switched = $this->switchPaymentMethod($attempt->subscription, $attempt);

                // 重新执行续费
                $result = $this->billingService->processRenewal($attempt->subscription);

                if ($result['success']) {
                    $attempt->update([
                        'status' => 'success',
                        'transaction_id' => $result['invoice']->metadata['transaction_id'] ?? null,
                        'attempted_at' => now(),
                    ]);
                    $stats['succeeded']++;

                    Log::info('RenewalPipeline: retry succeeded', [
                        'subscription_id' => $attempt->subscription_id,
                        'attempt_number' => $attempt->attempt_number,
                    ]);
                } else {
                    // 记录新的失败
                    $newAttemptCount = RenewalAttempt::where('subscription_id', $attempt->subscription_id)->count();
                    $nextRetry = $this->calculateNextRetry($newAttemptCount + 1, $maxAttempts);

                    $attempt->update([
                        'attempted_at' => now(),
                        'next_retry_at' => $nextRetry,
                        'failure_reason' => $result['error'] ?? 'retry_failed',
                        'retry_plan' => $this->buildRetryPlan($newAttemptCount + 1, $maxAttempts),
                        'escalated' => ($newAttemptCount + 1) >= $escalateAfter,
                    ]);

                    // 执行降级或升级
                    $this->executeFailureStrategy($attempt->subscription, $newAttemptCount + 1, $result['error'] ?? 'retry_failed', $maxAttempts);

                    $stats['failed']++;
                }
            } catch (\Throwable $e) {
                $stats['failed']++;
                Log::error('RenewalPipeline: retry processing error', [
                    'attempt_id' => $attempt->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 处理需要人工介入的
        $escalatedStats = $this->processEscalations();
        $stats['escalated'] = $escalatedStats;

        Log::info('RenewalPipeline: retries processed', $stats);
        return $stats;
    }

    /**
     * 执行失败策略
     */
    protected function executeFailureStrategy(
        Subscription $subscription,
        int $attemptNumber,
        string $failureReason,
        ?int $maxAttempts = null,
    ): void {
        $maxAttempts = $maxAttempts ?? $this->getMaxAttempts();
        $downgradeAfter = $this->cfg('downgrade_after_attempt') ?? self::DOWNGRADE_AFTER_ATTEMPT;
        $escalateAfter = $this->cfg('escalate_after_attempt') ?? self::ESCALATE_AFTER_ATTEMPT;

        // 第N次失败 → 降级套餐
        if ($attemptNumber === $downgradeAfter) {
            $this->downgradePlan($subscription, $failureReason);
        }

        // 第N次失败 → 人工介入
        if ($attemptNumber >= $escalateAfter) {
            $this->escalateToHuman($subscription, $attemptNumber, $failureReason);
        }

        // 超过最大重试 → 进入宽限期最终阶段
        if ($attemptNumber >= $maxAttempts) {
            $subscription->enterGracePeriod();

            // 发送紧急通知给客户
            $user = $subscription->customer?->user;
            if ($user) {
                $this->notifier->sendRenewalEscalated(
                    $user,
                    $subscription->plan ?? 'N/A',
                    $attemptNumber,
                );
            }
        }
    }

    /**
     * 降级套餐
     */
    protected function downgradePlan(Subscription $subscription, string $reason): void
    {
        // 根据当前套餐选择降级目标
        $downgradeTarget = $this->getDowngradeTarget($subscription->plan);

        if ($downgradeTarget) {
            DB::transaction(function () use ($subscription, $downgradeTarget, $reason) {
                $oldPlan = $subscription->plan;

                $subscription->update([
                    'plan' => $downgradeTarget['plan'],
                    'price' => $downgradeTarget['price'],
                    'metadata' => array_merge($subscription->metadata ?? [], [
                        'downgraded_at' => now()->toIso8601String(),
                        'downgrade_reason' => $reason,
                        'previous_plan' => $oldPlan,
                    ]),
                ]);

                Log::warning('RenewalPipeline: plan downgraded', [
                    'subscription_id' => $subscription->id,
                    'from' => $oldPlan,
                    'to' => $downgradeTarget['plan'],
                    'reason' => $reason,
                ]);
            });
        }
    }

    /**
     * 升级到人工处理（管理员 + 多渠道通知客户）
     */
    protected function escalateToHuman(Subscription $subscription, int $attemptNumber, string $reason): void
    {
        // 发邮件通知管理员
        $escalation = RenewalEscalation::create([
            'subscription_id' => $subscription->id,
            'channel' => 'email',
            'status' => 'pending',
            'message' => sprintf(
                '订阅 #%d（客户 #%d，套餐: %s）续费已失败 %d 次（%s），需要人工介入处理。',
                $subscription->id,
                $subscription->customer_id,
                $subscription->plan,
                $attemptNumber,
                $reason
            ),
        ]);

        // 发送通知给管理员
        try {
            $adminEmails = config('mail.admin_address', []);
            if (! empty($adminEmails)) {
                Mail::to($adminEmails)->send(
                    new RenewalEscalationNotification($escalation, $subscription)
                );
            }
        } catch (\Throwable $e) {
            Log::error('发送续费升级通知邮件失败', [
                'escalation_id' => $escalation->id,
                'error' => $e->getMessage(),
            ]);
        }

        // 多渠道通知客户
        $user = $subscription->customer?->user;
        if ($user) {
            $this->notifier->sendRenewalEscalated(
                $user,
                $subscription->plan ?? 'N/A',
                $attemptNumber,
            );
        }
    }

    /**
     * 处理人工介入
     */
    protected function processEscalations(): int
    {
        $count = 0;

        $pending = RenewalEscalation::where('status', 'pending')
            ->where('channel', 'email')
            ->get();

        foreach ($pending as $escalation) {
            // 发送通知邮件给管理员
            try {
                $subscription = $escalation->subscription;
                if ($subscription) {
                    Mail::to(config('mail.admin_address', []))->send(
                        new RenewalEscalationNotification($escalation, $subscription)
                    );
                }
            } catch (\Throwable $e) {
                Log::error('processEscalations: 发送升级通知失败', [
                    'escalation_id' => $escalation->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $escalation->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * 更换支付方式（模拟——实际需要集成支付网关）
     */
    protected function switchPaymentMethod(Subscription $subscription, RenewalAttempt $attempt): bool
    {
        $paymentInfo = $subscription->payment_info ?? [];

        // 如果有备选支付方式
        $availableMethods = $paymentInfo['available_methods'] ?? [];

        if (count($availableMethods) > 1) {
            $currentMethod = $attempt->payment_method;
            foreach ($availableMethods as $method) {
                if ($method !== $currentMethod) {
                    $subscription->update([
                        'payment_info' => array_merge($paymentInfo, [
                            'active_method' => $method,
                        ]),
                    ]);

                    Log::info('RenewalPipeline: switched payment method', [
                        'subscription_id' => $subscription->id,
                        'from' => $currentMethod,
                        'to' => $method,
                    ]);
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 计算下次重试时间（支持配置驱动）
     */
    protected function calculateNextRetry(int $attemptNumber, ?int $maxAttempts = null): ?\Carbon\Carbon
    {
        $maxAttempts = $maxAttempts ?? $this->getMaxAttempts();

        if ($attemptNumber >= $maxAttempts) {
            return null;
        }

        $intervals = $this->cfg('retry_intervals_days') ?? self::RETRY_INTERVALS;
        $days = $intervals[$attemptNumber] ?? ($intervals[count($intervals)] ?? 7);

        return now()->addDays((int) $days);
    }

    /**
     * 构建重试计划
     */
    protected function buildRetryPlan(int $currentAttempt, ?int $maxAttempts = null): array
    {
        $maxAttempts = $maxAttempts ?? $this->getMaxAttempts();
        $downgradeAfter = $this->cfg('downgrade_after_attempt') ?? self::DOWNGRADE_AFTER_ATTEMPT;
        $escalateAfter = $this->cfg('escalate_after_attempt') ?? self::ESCALATE_AFTER_ATTEMPT;
        $intervals = $this->cfg('retry_intervals_days') ?? self::RETRY_INTERVALS;

        $plan = [];
        for ($i = $currentAttempt + 1; $i <= $maxAttempts; $i++) {
            $days = $intervals[$i] ?? ($intervals[count($intervals)] ?? 7);
            $plan[] = [
                'attempt' => $i,
                'retry_in_days' => (int) $days,
                'retry_at' => now()->addDays((int) $days)->toIso8601String(),
                'action' => match (true) {
                    $i === $downgradeAfter => 'downgrade',
                    $i >= $escalateAfter => 'escalate',
                    default => 'retry',
                },
            ];
        }
        return $plan;
    }

    /**
     * 获取降级目标
     */
    protected function getDowngradeTarget(string $currentPlan): ?array
    {
        $plans = [
            'Enterprise' => ['plan' => 'Pro', 'price' => 299],
            'Pro' => ['plan' => 'Basic', 'price' => 99],
            'Basic' => ['plan' => 'Free', 'price' => 0],
        ];

        return $plans[$currentPlan] ?? null;
    }

    /**
     * 获取续费失败统计
     */
    public function getFailureStats(): array
    {
        $totalAttempts = RenewalAttempt::count();
        $totalFailures = RenewalAttempt::where('status', 'failed')->count();
        $pendingRetries = RenewalAttempt::where('status', 'failed')
            ->where('escalated', false)
            ->whereNotNull('next_retry_at')
            ->count();
        $escalated = RenewalEscalation::where('status', 'pending')->count();
        $recentFailures = RenewalAttempt::where('status', 'failed')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        return [
            'total_attempts' => $totalAttempts,
            'total_failures' => $totalFailures,
            'pending_retries' => $pendingRetries,
            'escalated_pending' => $escalated,
            'recent_7d_failures' => $recentFailures,
            'failure_rate' => $totalAttempts > 0
                ? round(($totalFailures / $totalAttempts) * 100, 2)
                : 0,
        ];
    }

    /**
     * 获取指定订阅的失败历史
     */
    public function getSubscriptionFailureHistory(Subscription $subscription): array
    {
        $attempts = RenewalAttempt::where('subscription_id', $subscription->id)
            ->orderBy('attempt_number', 'desc')
            ->get();

        $escalations = RenewalEscalation::where('subscription_id', $subscription->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return [
            'attempts' => $attempts,
            'escalations' => $escalations,
        ];
    }

    /**
     * 解决人工介入
     */
    public function resolveEscalation(int $escalationId, string $resolutionNote): bool
    {
        $escalation = RenewalEscalation::findOrFail($escalationId);

        return $escalation->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolution_note' => $resolutionNote,
        ]);
    }

    /**
     * 获取配置列表
     */
    public function getConfigs(): array
    {
        return RenewalConfig::orderBy('is_active', 'desc')
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    /**
     * 获取指定配置
     */
    public function getConfig(int $id): ?RenewalConfig
    {
        return RenewalConfig::find($id);
    }

    /**
     * 保存配置
     */
    public function saveConfig(array $data, ?int $id = null): RenewalConfig
    {
        if ($id) {
            $config = RenewalConfig::findOrFail($id);
            $config->update($data);
        } else {
            $config = RenewalConfig::create($data);
        }
        return $config->fresh();
    }

    /**
     * 切换配置激活状态
     */
    public function toggleConfigActive(int $id): bool
    {
        $config = RenewalConfig::findOrFail($id);

        if ($config->is_active) {
            return $config->update(['is_active' => false]);
        }

        // 激活时，先把其他配置设为非激活
        RenewalConfig::where('is_active', true)->update(['is_active' => false]);

        return $config->update(['is_active' => true]);
    }

    /**
     * 删除配置
     */
    public function deleteConfig(int $id): bool
    {
        return RenewalConfig::destroy($id) > 0;
    }
}
