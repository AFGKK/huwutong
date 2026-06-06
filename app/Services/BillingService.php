<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\License;
use App\Models\Product;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 订阅计费核心服务
 *
 * 管理订阅全生命周期：创建 → 激活 → 计费 → 续费 → 宽限期 → 停用 → 恢复
 * 支付成功自动调用 License 服务激活/延期
 */
class BillingService
{
    public function __construct(
        protected LicenseService $licenseService,
        protected PaymentManager $paymentManager,
    ) {}

    /**
     * 创建新订阅
     */
    public function createSubscription(
        Customer $customer,
        Product $product,
        string $plan,
        float $price,
        string $billingPeriod = 'monthly',
        array $options = []
    ): Subscription {
        return DB::transaction(function () use ($customer, $product, $plan, $price, $billingPeriod, $options) {
            $tenantId = $customer->tenant_id;
            $startsAt = $options['starts_at'] ?? now();
            $trialDays = $options['trial_days'] ?? 0;

            $endsAt = match ($billingPeriod) {
                'monthly' => $startsAt->copy()->addMonth(),
                'quarterly' => $startsAt->copy()->addMonths(3),
                'semi_annually' => $startsAt->copy()->addMonths(6),
                'yearly' => $startsAt->copy()->addYear(),
                default => $startsAt->copy()->addMonth(),
            };

            $subscription = Subscription::create([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'product_id' => $product->id,
                'status' => $trialDays > 0 ? 'active' : 'active',
                'plan' => $plan,
                'price' => $price,
                'currency' => $options['currency'] ?? 'CNY',
                'billing_period' => $billingPeriod,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'trial_ends_at' => $trialDays > 0 ? $startsAt->copy()->addDays($trialDays) : null,
                'grace_days' => $options['grace_days'] ?? 7,
                'auto_renew' => $options['auto_renew'] ?? true,
                'next_billing_at' => $endsAt,
                'pricing_plan_slug' => $options['pricing_plan_slug'] ?? null,
                'metadata' => $options['metadata'] ?? [],
            ]);

            // 创建第一张账单（试用期不需要）
            if ($trialDays === 0) {
                $this->createInvoice($subscription, 'subscription_create');
            }

            // 关联对应的 License 激活
            if (!empty($options['license_id'])) {
                $this->linkLicenseToSubscription($options['license_id'], $subscription);
            }

            Log::info('Billing: subscription created', [
                'subscription_id' => $subscription->id,
                'customer_id' => $customer->id,
                'product_id' => $product->id,
                'plan' => $plan,
                'price' => $price,
                'billing_period' => $billingPeriod,
            ]);

            return $subscription;
        });
    }

    /**
     * 处理自动续费
     * 由定时任务调用
     */
    public function processAutoRenewals(): array
    {
        $stats = ['processed' => 0, 'succeeded' => 0, 'failed' => 0, 'grace_period' => 0, 'pipeline_handled' => 0];

        // 查找需要续费的订阅
        $subscriptions = Subscription::where('auto_renew', true)
            ->where('status', 'active')
            ->whereNotNull('next_billing_at')
            ->where('next_billing_at', '<=', now())
            ->limit(100)
            ->get();

        foreach ($subscriptions as $subscription) {
            $stats['processed']++;

            try {
                $result = $this->processRenewal($subscription);

                if ($result['success']) {
                    $stats['succeeded']++;
                } else {
                    // 使用续费失败流水线处理
                    try {
                        $pipeline = app(RenewalPipelineService::class);
                        $pipeline->handleRenewalFailure(
                            $subscription,
                            $result['invoice'],
                            $result['error'] ?? 'payment_failed'
                        );
                        $stats['pipeline_handled']++;
                    } catch (\Throwable $pipelineError) {
                        // 回退到直接进入宽限期
                        $subscription->enterGracePeriod();
                        $stats['grace_period']++;
                        Log::error('Billing: pipeline failed, fell back to grace period', [
                            'subscription_id' => $subscription->id,
                            'error' => $pipelineError->getMessage(),
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                $stats['failed']++;
                Log::error('Billing: auto-renewal failed', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Billing: auto-renewals processed', $stats);
        return $stats;
    }

    /**
     * 处理宽限期结束的订阅
     * 由定时任务调用
     */
    public function processGracePeriodEnded(): array
    {
        $stats = ['suspended' => 0, 'expired' => 0];

        // 宽限期即将结束的订阅（最后1天发送警告）
        $endingGrace = Subscription::where('status', 'grace')
            ->whereNotNull('grace_ends_at')
            ->where('grace_ends_at', '<=', now()->addDay())
            ->where('grace_ends_at', '>', now())
            ->get();

        foreach ($endingGrace as $subscription) {
            // 发送宽期即将结束通知
            event(new \App\Events\SubscriptionGraceEnding($subscription));
        }

        // 已过宽限期的订阅 → 停用
        $expiredGrace = Subscription::where('status', 'grace')
            ->whereNotNull('grace_ends_at')
            ->where('grace_ends_at', '<=', now())
            ->get();

        foreach ($expiredGrace as $subscription) {
            DB::transaction(function () use ($subscription, &$stats) {
                $subscription->markExpired();

                // 停用关联的 License
                if ($subscription->relationLoaded('licenses') || true) {
                    License::where('subscription_id', $subscription->id)
                        ->whereNotIn('status', ['expired', 'revoked', 'blacklisted'])
                        ->update(['status' => 'suspended']);
                }

                $stats['suspended']++; // Actually expired

                Log::info('Billing: subscription expired after grace period', [
                    'subscription_id' => $subscription->id,
                ]);
            });
        }

        return $stats;
    }

    /**
     * 处理单个订阅的续费
     */
    public function processRenewal(Subscription $subscription): array
    {
        try {
            // 创建续费账单
            $invoice = $this->createInvoice($subscription, 'subscription_renew');

            // 尝试处理支付（模拟 — 实际集成支付网关）
            $paymentResult = $this->processPayment($invoice);

            if (!$paymentResult['success']) {
                return [
                    'success' => false,
                    'error' => $paymentResult['error'] ?? 'payment_failed',
                    'invoice' => $invoice,
                ];
            }

            // 更新订阅
            $newEndsAt = $subscription->calculateRenewalEndDate();

            $subscription->update([
                'ends_at' => $newEndsAt,
                'next_billing_at' => $newEndsAt,
                'last_billed_at' => now(),
                'billing_cycles_completed' => ($subscription->billing_cycles_completed ?? 0) + 1,
                'total_paid' => ($subscription->total_paid ?? 0) + (float) $subscription->price,
                'status' => 'active',
            ]);

            // 延长关联 License 有效期
            if ($subscription->relationLoaded('licenses') || true) {
                License::where('subscription_id', $subscription->id)
                    ->whereIn('status', ['active', 'suspended'])
                    ->each(function (License $license) use ($newEndsAt) {
                        $license->update([
                            'expires_at' => $newEndsAt,
                        ]);
                        // 如果被挂起则恢复
                        if ($license->status === 'suspended') {
                            $license->update(['status' => 'active']);
                        }
                    });
            }

            Log::info('Billing: renewal succeeded', [
                'subscription_id' => $subscription->id,
                'new_ends_at' => $newEndsAt->toIso8601String(),
                'invoice_id' => $invoice->id,
            ]);

            return ['success' => true, 'invoice' => $invoice];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 手动续费订阅
     */
    public function manualRenew(Subscription $subscription): array
    {
        return $this->processRenewal($subscription);
    }

    /**
     * 升级/降级订阅
     */
    public function changePlan(Subscription $subscription, string $newPlan, float $newPrice, ?string $newBillingPeriod = null): Subscription
    {
        return DB::transaction(function () use ($subscription, $newPlan, $newPrice, $newBillingPeriod) {
            $oldPlan = $subscription->plan;

            $subscription->update([
                'plan' => $newPlan,
                'price' => $newPrice,
                'billing_period' => $newBillingPeriod ?? $subscription->billing_period,
                'metadata' => array_merge($subscription->metadata ?? [], [
                    'plan_changed_at' => now()->toIso8601String(),
                    'previous_plan' => $oldPlan,
                ]),
            ]);

            // 创建变更账单（差价）
            $this->createInvoice($subscription, 'upgrade', $newPrice);

            Log::info('Billing: plan changed', [
                'subscription_id' => $subscription->id,
                'from' => $oldPlan,
                'to' => $newPlan,
                'new_price' => $newPrice,
            ]);

            return $subscription->fresh();
        });
    }

    /**
     * 取消订阅
     */
    public function cancelSubscription(Subscription $subscription, ?string $reason = null): Subscription
    {
        $subscription->cancel($reason);

        Log::info('Billing: subscription canceled', [
            'subscription_id' => $subscription->id,
            'reason' => $reason,
        ]);

        return $subscription->fresh();
    }

    /**
     * 恢复已取消的订阅
     */
    public function resumeSubscription(Subscription $subscription): Subscription
    {
        $subscription->update([
            'auto_renew' => true,
            'canceled_at' => null,
            'cancellation_reason' => null,
        ]);

        Log::info('Billing: subscription resumed', [
            'subscription_id' => $subscription->id,
        ]);

        return $subscription->fresh();
    }

    /**
     * 创建账单
     */
    public function createInvoice(Subscription $subscription, string $reason, ?float $amount = null): Invoice
    {
        $invoiceNo = 'INV-' . strtoupper(Str::random(12));

        return Invoice::create([
            'tenant_id' => $subscription->tenant_id,
            'customer_id' => $subscription->customer_id,
            'subscription_id' => $subscription->id,
            'invoice_no' => $invoiceNo,
            'amount' => $amount ?? $subscription->price,
            'currency' => $subscription->currency,
            'status' => 'pending',
            'billing_reason' => $reason,
            'due_at' => now()->addDays(7),
        ]);
    }

    /**
     * 处理支付
     *
     * 通过 PaymentManager 调用已配置的真实支付网关。
     * 开发环境默认使用 MockPaymentGateway。
     */
    public function processPayment(Invoice $invoice): array
    {
        $paymentResult = $this->paymentManager->charge($invoice);

        if ($paymentResult['success']) {
            $invoice->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            Log::info('Billing: payment processed', [
                'invoice_id' => $invoice->id,
                'gateway' => $this->paymentManager->gatewayName(),
                'transaction_id' => $paymentResult['transaction_id'] ?? null,
            ]);

            return [
                'success' => true,
                'transaction_id' => $paymentResult['transaction_id'] ?? null,
                'redirect_url' => $paymentResult['redirect_url'] ?? null,
            ];
        }

        Log::warning('Billing: payment failed', [
            'invoice_id' => $invoice->id,
            'error' => $paymentResult['error'] ?? 'unknown',
        ]);

        return ['success' => false, 'error' => $paymentResult['error'] ?? 'payment_declined'];
    }

    /**
     * 标记发票为已支付（支付回调使用）
     */
    public function markInvoiceAsPaid(Invoice $invoice, string $transactionId): bool
    {
        return DB::transaction(function () use ($invoice, $transactionId) {
            $invoice->update([
                'status' => 'paid',
                'paid_at' => now(),
                'metadata' => array_merge($invoice->metadata ?? [], [
                    'transaction_id' => $transactionId,
                ]),
            ]);

            // 如果是续费账单，延长订阅
            if ($invoice->billing_reason === 'subscription_renew' && $invoice->subscription) {
                $this->processRenewal($invoice->subscription);
            }

            // 如果是首次创建，激活 License
            if ($invoice->billing_reason === 'subscription_create' && $invoice->subscription) {
                $this->activateLicensesForSubscription($invoice->subscription);
            }

            return true;
        });
    }

    /**
     * 将 License 与订阅关联
     */
    protected function linkLicenseToSubscription(int $licenseId, Subscription $subscription): void
    {
        $license = License::find($licenseId);
        if ($license) {
            $license->update([
                'subscription_id' => $subscription->id,
                'expires_at' => $subscription->ends_at,
            ]);
        }
    }

    /**
     * 激活订阅关联的所有 License
     */
    protected function activateLicensesForSubscription(Subscription $subscription): void
    {
        License::where('subscription_id', $subscription->id)
            ->where('status', 'pending')
            ->update(['status' => 'active']);
    }

    /**
     * 获取订阅统计概览
     */
    public function getStats(): array
    {
        $now = now();

        $total = Subscription::count();
        $active = Subscription::where('status', 'active')->count();
        $inGrace = Subscription::where('status', 'grace')->count();
        $expiringSoon = Subscription::where('status', 'active')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', $now->copy()->addDays(7))
            ->where('ends_at', '>', $now)
            ->count();

        $monthlyMrr = Subscription::whereIn('status', ['active', 'grace'])
            ->where('billing_period', 'monthly')
            ->sum('price');

        $annualMrr = Subscription::whereIn('status', ['active', 'grace'])
            ->where('billing_period', 'yearly')
            ->sum('price');

        $totalMrr = round((float) $monthlyMrr + ((float) $annualMrr / 12), 2);

        return [
            'total' => $total,
            'active' => $active,
            'in_grace_period' => $inGrace,
            'expiring_soon_7d' => $expiringSoon,
            'mrr' => $totalMrr,
            'estimated_arr' => round($totalMrr * 12, 2),
        ];
    }
}
