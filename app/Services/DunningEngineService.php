<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\DunningLog;
use App\Models\DunningQueue;
use App\Models\DunningStrategy;
use App\Models\Invoice;
use App\Models\License;
use App\Models\Subscription;
use App\Models\PricingPlan;
use App\Support\DbSql;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 智能催缴引擎 (Dunning Engine)
 *
 * 管理催缴全流程：
 * 1. 发票逾期 → 入催缴队列
 * 2. 按策略阶段逐步升级（提醒 → 警告 → 重试支付 → 降级 → 暂停 → 人工）
 * 3. 每次操作记录日志
 */
class DunningEngineService
{
    public function __construct(
        protected BillingService $billingService,
        protected NotificationService $notificationService,
        protected PaymentManager $paymentManager,
    ) {}

    /**
     * 执行催缴运行 — 由定时任务调用
     * 处理所有到期的催缴队列项
     */
    public function processDunningRun(): array
    {
        $stats = [
            'processed' => 0,
            'reminders_sent' => 0,
            'warnings_sent' => 0,
            'payments_retried' => 0,
            'payments_succeeded' => 0,
            'downgraded' => 0,
            'suspended' => 0,
            'escalated' => 0,
            'resolved' => 0,
            'errors' => 0,
        ];

        // 获取所有到期的催缴项
        $dueItems = DunningQueue::whereIn('status', ['pending', 'in_progress'])
            ->where('next_action_at', '<=', now())
            ->with(['subscription', 'invoice', 'customer', 'strategy'])
            ->orderBy('next_action_at')
            ->limit(200)
            ->get();

        foreach ($dueItems as $item) {
            try {
                $result = $this->processQueueItem($item);
                foreach ($result as $key => $val) {
                    if (isset($stats[$key])) {
                        $stats[$key] += $val;
                    }
                }
                $stats['processed']++;
            } catch (\Throwable $e) {
                $stats['errors']++;
                Log::error('Dunning: failed to process queue item', [
                    'queue_id' => $item->id,
                    'subscription_id' => $item->subscription_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Dunning: run completed', $stats);
        return $stats;
    }

    /**
     * 处理单个队列项
     */
    protected function processQueueItem(DunningQueue $item): array
    {
        $stats = [];
        $strategy = $item->strategy ?? $this->getDefaultStrategy();
        $stages = $strategy->getStages();
        $stageIdx = min($item->current_stage, count($stages) - 1);
        $stage = $stages[$stageIdx] ?? null;

        if (!$stage) {
            // 已无更多阶段，标记为需人工介入
            $item->update(['status' => 'failed', 'notes' => __('app.api.service_dunning_engine.all_stages_used')]);
            $stats['escalated'] = 1;
            return $stats;
        }

        $action = $stage['action'] ?? 'send_reminder';
        $channel = $stage['channel'] ?? 'email';
        $subscription = $item->subscription;
        $invoice = $item->invoice;

        DB::beginTransaction();
        try {
            switch ($action) {
                case 'send_reminder':
                    $this->sendReminder($item, $stage, false);
                    $stats['reminders_sent'] = 1;
                    break;

                case 'send_warning':
                    $this->sendReminder($item, $stage, true);
                    $stats['warnings_sent'] = 1;
                    break;

                case 'retry_payment':
                    $paymentResult = $this->retryPayment($item);
                    if ($paymentResult['success']) {
                        $this->resolveItem($item, 'paid');
                        $stats['payments_succeeded'] = 1;
                        $stats['resolved'] = 1;
                        DB::commit();
                        return $stats;
                    }
                    $stats['payments_retried'] = 1;
                    break;

                case 'downgrade':
                    $this->downgradeSubscription($item);
                    $stats['downgraded'] = 1;
                    break;

                case 'suspend':
                    $this->suspendService($item);
                    $stats['suspended'] = 1;
                    break;

                case 'escalate':
                    $this->escalateToHuman($item);
                    $stats['escalated'] = 1;
                    break;
            }

            // 计算下次行动时间
            $nextStageIdx = $stageIdx + 1;
            $nextStage = $stages[$nextStageIdx] ?? null;
            $nextDayOffset = $nextStage['day'] ?? ($stage['day'] + 7);

            $nextActionAt = $item->enqueued_at
                ? $item->enqueued_at->copy()->addDays($nextDayOffset)
                : now()->addDays(7);

            // 记录日志
            $this->logAction($item, [
                'attempt_number' => $item->attempt_count + 1,
                'action_taken' => $action,
                'channel' => $channel,
                'success' => true,
            ]);

            // 推进到下一阶段
            $item->advanceToNextStage();
            $item->update([
                'next_action_at' => $nextActionAt,
                'last_action_at' => now(),
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            // 记录失败日志但不阻断队列
            $this->logAction($item, [
                'attempt_number' => $item->attempt_count + 1,
                'action_taken' => $action,
                'channel' => $channel,
                'success' => false,
                'error_message' => $e->getMessage(),
            ]);
            $item->increment('attempt_count');
            $item->update([
                'next_action_at' => now()->addDay(), // 明天再试
                'last_action_at' => now(),
            ]);

            Log::error("Dunning: action {$action} failed for queue #{$item->id}", [
                'error' => $e->getMessage(),
            ]);
        }

        return $stats;
    }

    /**
     * 发送催缴通知
     */
    protected function sendReminder(DunningQueue $item, array $stage, bool $isWarning): void
    {
        $subscription = $item->subscription;
        $customer = $item->customer;
        $channel = $stage['channel'] ?? 'email';

        $subject = $stage['subject'] ?? ($isWarning ? __('app.api.service_dunning_engine.overdue_warning') : __('app.api.service_dunning_engine.payment_reminder'));
        $template = $stage['template'] ?? 'dunning::default';

        $notificationData = [
            'subject' => $subject,
            'invoice_no' => $item->invoice?->invoice_no,
            'amount' => $item->amount_due,
            'currency' => $item->currency,
            'due_date' => $item->invoice?->due_at?->format('Y-m-d'),
            'days_overdue' => $item->invoice?->due_at ? now()->diffInDays($item->invoice->due_at) : 0,
            'subscription_plan' => $subscription?->plan,
            'attempt_count' => $item->attempt_count,
            'is_warning' => $isWarning,
            'next_action' => $stage['action'] ?? 'send_reminder',
        ];

        if ($channel === 'email_and_sms' || $channel === 'email') {
            $this->notificationService->sendEmail(
                $customer?->user?->email,
                $subject,
                $template,
                $notificationData,
            );
        }

        if ($channel === 'email_and_sms' || $channel === 'sms') {
            $this->notificationService->sendSms(
                $customer?->user?->phone,
                __('app.api.service_dunning_engine.reminder_body', ['invoice' => $item->invoice?->invoice_no, 'amount' => $item->amount_due, 'currency' => $item->currency]),
            );
        }

        // 应用内通知
        if ($customer?->user) {
            $this->notificationService->sendInAppNotification(
                $customer->user,
                $subject,
                $notificationData,
            );
        }
    }

    /**
     * 重试支付
     */
    protected function retryPayment(DunningQueue $item): array
    {
        $invoice = $item->invoice;
        if (!$invoice || $invoice->paid) {
            $this->resolveItem($item, 'paid');
            return ['success' => true, 'message' => __('app.api.service_dunning_engine.invoice_paid')];
        }

        try {
            // 尝试使用默认支付方式重试
            $paymentResult = $this->paymentManager->charge($invoice);

            if ($paymentResult['success']) {
                $invoice->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);

                // 如果是续费账单，延长订阅
                if ($invoice->billing_reason === 'subscription_renew' && $item->subscription) {
                    $newEndsAt = $item->subscription->calculateRenewalEndDate();
                    $item->subscription->update([
                        'ends_at' => $newEndsAt,
                        'next_billing_at' => $newEndsAt,
                        'last_billed_at' => now(),
                        'status' => 'active',
                        'billing_cycles_completed' => ($item->subscription->billing_cycles_completed ?? 0) + 1,
                        'total_paid' => ($item->subscription->total_paid ?? 0) + (float) $item->amount_due,
                    ]);
                }

                return ['success' => true, 'message' => __('app.api.service_dunning_engine.payment_success')];
            }

            return ['success' => false, 'error' => $paymentResult['error'] ?? 'payment_declined'];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 降级订阅（切换为免费/基础方案）
     */
    protected function downgradeSubscription(DunningQueue $item): void
    {
        $subscription = $item->subscription;
        if (!$subscription || $subscription->status !== 'active') return;

        // 查找基础/免费方案
        $basicPlan = PricingPlan::where('is_active', true)
            ->where(function ($q) {
                $q->where('price_monthly', 0)
                  ->orWhere('price_monthly', '<=', 0.01);
            })
            ->first();

        if ($basicPlan) {
            $subscription->update([
                'plan' => $basicPlan->slug,
                'price' => 0,
                'pricing_plan_slug' => $basicPlan->slug,
                'metadata' => array_merge($subscription->metadata ?? [], [
                    'downgraded_at' => now()->toIso8601String(),
                    'downgraded_from' => $subscription->plan,
                    'downgrade_reason' => 'payment_dunning',
                ]),
            ]);
        }

        // 限制关联 License 的功能
        License::where('subscription_id', $subscription->id)
            ->where('status', 'active')
            ->update([
                'metadata' => DB::raw(DbSql::jsonMerge('metadata', [
                    'downgraded' => true,
                    'downgraded_at' => now()->toIso8601String(),
                ])),
            ]);
    }

    /**
     * 暂停服务
     */
    protected function suspendService(DunningQueue $item): void
    {
        $subscription = $item->subscription;
        if (!$subscription) return;

        $subscription->suspend();

        // 暂停关联的 License
        License::where('subscription_id', $subscription->id)
            ->whereIn('status', ['active'])
            ->update(['status' => 'suspended']);

        // 通知客户
        if ($customer = $item->customer) {
            $this->notificationService->sendEmail(
                $customer->user?->email,
                __('app.api.service_dunning_engine.service_suspended'),
                'dunning::suspended',
                [
                    'subscription_plan' => $subscription->plan,
                    'amount_due' => $item->amount_due,
                    'invoice_no' => $item->invoice?->invoice_no,
                ],
            );
        }
    }

    /**
     * 升级到人工处理
     */
    protected function escalateToHuman(DunningQueue $item): void
    {
        $item->update([
            'status' => 'failed',
            'notes' => __('app.api.service_dunning_engine.awaiting_manual'),
        ]);

        // 通知管理员
        $this->notificationService->sendEmail(
            config('mail.admin_address'),
            __('app.api.service_dunning_engine.escalation_manual'),
            'dunning::escalated',
            [
                'customer_name' => $item->customer?->name,
                'customer_id' => $item->customer_id,
                'subscription_id' => $item->subscription_id,
                'invoice_no' => $item->invoice?->invoice_no,
                'amount_due' => $item->amount_due,
                'attempts' => $item->attempt_count,
                'admin_url' => url("/admin/billing?dunning={$item->id}"),
            ],
        );
    }

    /**
     * 标记催缴项为已解决
     */
    protected function resolveItem(DunningQueue $item, string $status = 'resolved'): void
    {
        $item->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'next_action_at' => null,
        ]);
    }

    /**
     * 记录催缴操作日志
     */
    protected function logAction(DunningQueue $item, array $data): DunningLog
    {
        return DunningLog::create(array_merge($data, [
            'dunning_queue_id' => $item->id,
            'subscription_id' => $item->subscription_id,
            'invoice_id' => $item->invoice_id,
        ]));
    }

    /**
     * 将逾期发票加入催缴队列
     */
    public function enqueueOverdueInvoice(Invoice $invoice): ?DunningQueue
    {
        if (!$invoice->subscription_id || $invoice->paid) {
            return null;
        }

        // 防止重复入队
        $existing = DunningQueue::where('invoice_id', $invoice->id)
            ->whereIn('status', ['pending', 'in_progress'])
            ->first();

        if ($existing) {
            return $existing;
        }

        $subscription = $invoice->subscription;
        $strategy = $this->resolveStrategy($subscription);

        return DunningQueue::create([
            'subscription_id' => $subscription->id,
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id ?? $subscription->customer_id,
            'tenant_id' => $invoice->tenant_id ?? $subscription->tenant_id,
            'dunning_strategy_id' => $strategy?->id,
            'attempt_count' => 0,
            'current_stage' => 0,
            'status' => 'pending',
            'amount_due' => (float) $invoice->amount,
            'currency' => $invoice->currency ?? 'CNY',
            'next_action_at' => now(), // 立即开始
            'enqueued_at' => now(),
        ]);
    }

    /**
     * 批量扫描逾期发票并入队列
     */
    public function enqueueAllOverdueInvoices(): array
    {
        $count = 0;

        Invoice::where('paid', false)
            ->where('status', 'pending')
            ->whereNotNull('due_at')
            ->where('due_at', '<=', now())
            ->whereNotNull('subscription_id')
            ->whereDoesntHave('dunningQueue', function ($q) {
                $q->whereIn('status', ['pending', 'in_progress']);
            })
            ->chunk(100, function ($invoices) use (&$count) {
                foreach ($invoices as $invoice) {
                    $this->enqueueOverdueInvoice($invoice);
                    $count++;
                }
            });

        return ['enqueued' => $count];
    }

    /**
     * 解析适合订阅的催缴策略
     */
    protected function resolveStrategy(?Subscription $subscription): ?DunningStrategy
    {
        if (!$subscription) return null;

        $plan = $subscription->plan;

        // 按排序找最匹配的策略
        return DunningStrategy::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->first(function ($strategy) use ($plan) {
                $plans = $strategy->applicable_plans;
                if (empty($plans)) return true; // 所有方案适用
                return in_array($plan, $plans);
            });
    }

    /**
     * 获取默认策略
     */
    public function getDefaultStrategy(): DunningStrategy
    {
        $strategy = DunningStrategy::where('slug', 'default')->first();
        if (!$strategy) {
            $strategy = DunningStrategy::create([
                'name' => __('app.api.service_dunning_engine.default_strategy'),
                'slug' => 'default',
                'description' => __('app.api.service_dunning_engine.default_strategy_desc'),
                'stages' => DunningStrategy::defaultStages(),
                'max_attempts' => 5,
                'is_active' => true,
            ]);
        }
        return $strategy;
    }

    /**
     * 管理员手动将发票加入催缴队列
     */
    public function manualEnqueue(int $subscriptionId, ?int $invoiceId = null): DunningQueue
    {
        $subscription = Subscription::findOrFail($subscriptionId);
        $invoice = $invoiceId ? Invoice::findOrFail($invoiceId) : null;

        if (!$invoice) {
            // 查找最新未付发票
            $invoice = Invoice::where('subscription_id', $subscriptionId)
                ->where('paid', false)
                ->whereIn('status', ['pending', 'overdue'])
                ->latest()
                ->first();
        }

        if (!$invoice) {
            throw new \RuntimeException(__('app.api.service_dunning_engine.invoice_not_found'));
        }

        return $this->enqueueOverdueInvoice($invoice);
    }

    /**
     * 从队列中移除（当客户支付后）
     */
    public function resolveByInvoice(int $invoiceId): bool
    {
        $items = DunningQueue::where('invoice_id', $invoiceId)
            ->whereIn('status', ['pending', 'in_progress'])
            ->get();

        foreach ($items as $item) {
            $this->resolveItem($item, 'paid');
            $this->logAction($item, [
                'attempt_number' => $item->attempt_count + 1,
                'action_taken' => 'resolve',
                'channel' => 'none',
                'success' => true,
            ]);
        }

        return $items->isNotEmpty();
    }

    /**
     * 获取催缴看板数据
     */
    public function getDashboardData(): array
    {
        $now = now();

        $totalActive = DunningQueue::whereIn('status', ['pending', 'in_progress'])->count();
        $totalResolved = DunningQueue::where('status', 'resolved')->count();
        $totalFailed = DunningQueue::where('status', 'failed')->count();

        $totalDue = DunningQueue::whereIn('status', ['pending', 'in_progress'])
            ->sum('amount_due');

        $byStage = DunningQueue::whereIn('status', ['pending', 'in_progress'])
            ->selectRaw('current_stage, count(*) as count, sum(amount_due) as total')
            ->groupBy('current_stage')
            ->orderBy('current_stage')
            ->get()
            ->toArray();

        $overdueTrend = DunningQueue::where('enqueued_at', '>=', $now->copy()->subDays(30))
            ->selectRaw('DATE(enqueued_at) as date, count(*) as count')
            ->groupByRaw('DATE(enqueued_at)')
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();

        // 填充日期
        $trend = [];
        for ($i = 0; $i < 30; $i++) {
            $date = $now->copy()->subDays(29 - $i)->format('Y-m-d');
            $trend[] = ['date' => $date, 'count' => (int) ($overdueTrend[$date] ?? 0)];
        }

        $actionDistribution = DunningLog::where('actioned_at', '>=', $now->copy()->subDays(30))
            ->selectRaw('action_taken, count(*) as count')
            ->groupBy('action_taken')
            ->pluck('count', 'action_taken')
            ->toArray();

        return [
            'total_active' => $totalActive,
            'total_resolved' => $totalResolved,
            'total_failed' => $totalFailed,
            'total_due_amount' => round((float) $totalDue, 2),
            'by_stage' => $byStage,
            'overdue_trend' => $trend,
            'action_distribution' => $actionDistribution,
        ];
    }
}
