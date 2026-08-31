<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Customer;
use App\Models\BillingCycle;
use App\Models\Invoice;
use App\Models\License;
use App\Models\PricingPlan;
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
 * 支持定价方案（PricingPlan）、优惠券（Coupon）、自动续费流水线。
 * 支付成功自动调用 License 服务激活/延期
 */
class BillingService
{
    public function __construct(
        protected LicenseService $licenseService,
        protected PaymentManager $paymentManager,
        protected CommissionEngineService $commissionEngine,
        protected PrepaidBalanceService $prepaidBalanceService,
    ) {}

    /**
     * 使用定价方案创建新订阅
     *
     * @param PricingPlan|string $plan  PricingPlan 实例或 slug
     */
    public function createSubscription(
        Customer $customer,
        Product $product,
        PricingPlan|string $plan,
        string $billingPeriod = 'monthly',
        array $options = []
    ): Subscription {
        // 解析定价方案
        $pricingPlan = is_string($plan)
            ? PricingPlan::where('slug', $plan)->where('is_active', true)->firstOrFail()
            : $plan;

        $price = $pricingPlan->getPrice($billingPeriod);
        if ($price <= 0 && empty($options['force_zero'])) {
            throw new \InvalidArgumentException("Pricing plan {$pricingPlan->slug} has no price for {$billingPeriod}");
        }

        return DB::transaction(function () use ($customer, $product, $pricingPlan, $price, $billingPeriod, $options) {
            $tenantId = $customer->tenant_id;
            $startsAt = $options['starts_at'] ?? now();
            $trialDays = $options['trial_days'] ?? $pricingPlan->trial_days;

            $endsAt = BillingCycle::calculateEndDate($billingPeriod, $startsAt);

            $subscription = Subscription::create([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'product_id' => $product->id,
                'status' => $trialDays > 0 ? 'active' : 'active',
                'plan' => $pricingPlan->slug,
                'price' => $price,
                'currency' => $pricingPlan->currency,
                'billing_period' => $billingPeriod,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'trial_ends_at' => $trialDays > 0 ? $startsAt->copy()->addDays($trialDays) : null,
                'grace_days' => $options['grace_days'] ?? 7,
                'auto_renew' => $options['auto_renew'] ?? true,
                'next_billing_at' => $endsAt,
                'pricing_plan_slug' => $pricingPlan->slug,
                'metadata' => $options['metadata'] ?? [],
            ]);

            // 处理优惠券
            if (!empty($options['coupon_code'])) {
                $this->applyCouponToSubscription($subscription, $options['coupon_code']);
            }

            // 创建第一张账单（试用期不需要）
            if ($trialDays === 0) {
                $invoice = $this->createInvoice($subscription, 'subscription_create');
                $this->applyCouponToInvoice($invoice, $subscription);
            }

            // 关联对应的 License 激活
            if (!empty($options['license_id'])) {
                $this->linkLicenseToSubscription($options['license_id'], $subscription);
            }

            Log::info('Billing: subscription created', [
                'subscription_id' => $subscription->id,
                'customer_id' => $customer->id,
                'product_id' => $product->id,
                'plan' => $pricingPlan->slug,
                'price' => $price,
                'billing_period' => $billingPeriod,
                'coupon' => $options['coupon_code'] ?? null,
            ]);

            return $subscription;
        });
    }

    /**
     * 对订阅应用优惠券
     */
    public function applyCouponToSubscription(Subscription $subscription, string $couponCode): ?array
    {
        $coupon = Coupon::where('code', $couponCode)->first();

        if (!$coupon) {
throw new \RuntimeException(__("app.billing.coupon_not_found", ['code' => $couponCode]));
        }

        if (!$coupon->isValid(
            amount: (float) $subscription->price,
            plan: $subscription->plan,
            productId: $subscription->product_id
        )) {
throw new \RuntimeException(__("app.billing.coupon_expired", ['code' => $couponCode]));
        }

        if (isset($subscription->customer_id) && $coupon->hasReachedUserLimit((int) $subscription->customer_id)) {
throw new \RuntimeException(__("app.billing.coupon_usage_limit_exceeded"));
        }

        $originalAmount = (float) $subscription->price;
        $discountAmount = $coupon->calculateDiscount($originalAmount);
        $finalAmount = round(max(0, $originalAmount - $discountAmount), 2);

        // 如果是免费试用类型，设置试用天数
        if ($coupon->type === 'free_trial' && !$subscription->trial_ends_at) {
            $trialDays = $coupon->value > 0 ? (int) $coupon->value : 30;
            $subscription->update([
                'trial_ends_at' => now()->addDays($trialDays),
            ]);
        }

        // 记录优惠金额到 metadata
        $subscription->update([
            'metadata' => array_merge($subscription->metadata ?? [], [
                'coupon_code' => $couponCode,
                'coupon_id' => $coupon->id,
                'coupon_discount' => $discountAmount,
                'coupon_original_price' => $originalAmount,
                'coupon_final_price' => $finalAmount,
                'coupon_applied_at' => now()->toIso8601String(),
            ]),
        ]);

        // 记录使用
        $redemption = CouponRedemption::create([
            'coupon_id' => $coupon->id,
            'subscription_id' => $subscription->id,
            'customer_id' => $subscription->customer_id,
            'discount_amount' => $discountAmount,
            'currency' => $subscription->currency,
            'original_amount' => $originalAmount,
            'final_amount' => $finalAmount,
        ]);

        $coupon->recordRedemption($redemption);

        return [
            'coupon' => $coupon,
            'discount_amount' => $discountAmount,
            'original_amount' => $originalAmount,
            'final_amount' => $finalAmount,
        ];
    }

    /**
     * 对账单应用优惠券折扣
     */
    protected function applyCouponToInvoice(Invoice $invoice, Subscription $subscription): void
    {
        $meta = $subscription->metadata ?? [];
        $couponDiscount = $meta['coupon_discount'] ?? 0;
        $couponCode = $meta['coupon_code'] ?? null;
        $couponId = $meta['coupon_id'] ?? null;
        $originalPrice = $meta['coupon_original_price'] ?? $invoice->amount;

        if ($couponDiscount > 0) {
            $finalAmount = round(max(0, (float) $originalPrice - (float) $couponDiscount), 2);
            $invoice->update([
                'amount' => $finalAmount,
                'subtotal' => $originalPrice,
                'discount_amount' => $couponDiscount,
                'coupon_code' => $couponCode,
                'coupon_id' => $couponId,
            ]);

            // 关联使用记录
            CouponRedemption::where('subscription_id', $subscription->id)
                ->whereNull('invoice_id')
                ->latest()
                ->first()
                ?->update(['invoice_id' => $invoice->id]);
        }
    }

    /**
     * 校验并计算优惠券折扣（不下单，用于预览）
     */
    public function previewCoupon(string $couponCode, float $amount, ?string $plan = null, ?int $productId = null): array
    {
        $coupon = Coupon::where('code', $couponCode)->first();

        if (!$coupon) {
            return ['valid' => false, 'error' => __('app.billing_service.billing_service_d49d927c88')];
        }

        if (!$coupon->isValid(amount: $amount, plan: $plan, productId: $productId)) {
            return ['valid' => false, 'error' => __('app.billing_service.billing_service_e54aeac478')];
        }

        $discount = $coupon->calculateDiscount($amount);
        $finalAmount = round(max(0, $amount - $discount), 2);

        return [
            'valid' => true,
            'coupon' => [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'name' => $coupon->name,
                'type' => $coupon->type,
                'value' => (float) $coupon->value,
            ],
            'original_amount' => $amount,
            'discount_amount' => $discount,
            'final_amount' => $finalAmount,
        ];
    }

    /**
     * 获取公开定价方案列表
     */
    public function getPublicPlans(): array
    {
        return PricingPlan::active()->public()->ordered()->get()
            ->map(fn (PricingPlan $p) => $p->toSummary())
            ->values()
            ->toArray();
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

            // processPayment → markInvoicePaid 已延长订阅并结算佣金
            Log::info('Billing: renewal succeeded', [
                'subscription_id' => $subscription->id,
                'invoice_id' => $invoice->id,
            ]);

            return ['success' => true, 'invoice' => $invoice->fresh()];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 将续费结果应用到订阅与关联 License（不再创建账单/发起支付）
     */
    protected function applyRenewalToSubscription(Subscription $subscription): void
    {
        $newEndsAt = $subscription->calculateRenewalEndDate();

        $subscription->update([
            'ends_at' => $newEndsAt,
            'next_billing_at' => $newEndsAt,
            'last_billed_at' => now(),
            'billing_cycles_completed' => ($subscription->billing_cycles_completed ?? 0) + 1,
            'total_paid' => ($subscription->total_paid ?? 0) + (float) $subscription->price,
            'status' => 'active',
        ]);

        License::where('subscription_id', $subscription->id)
            ->whereIn('status', ['active', 'suspended'])
            ->each(function (License $license) use ($newEndsAt) {
                $license->update(['expires_at' => $newEndsAt]);
                if ($license->status === 'suspended') {
                    $license->update(['status' => 'active']);
                }
            });
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
     *
     * 支持优惠券折扣：如果订阅有已应用的优惠券，自动计算折扣金额。
     */
    public function createInvoice(Subscription $subscription, string $reason, ?float $amount = null): Invoice
    {
        $invoiceNo = 'INV-' . strtoupper(Str::random(12));
        $baseAmount = $amount ?? $subscription->price;
        $meta = $subscription->metadata ?? [];

        // 检查订阅有没有关联的优惠券
        $discountAmount = 0;
        $couponCode = null;
        $couponId = null;
        $subtotal = $baseAmount;

        if (!empty($meta['coupon_code']) && $reason !== 'upgrade') {
            $couponCode = $meta['coupon_code'];
            $couponId = $meta['coupon_id'] ?? null;
            $discountAmount = $meta['coupon_discount'] ?? 0;
        }

        $finalAmount = round(max(0, $baseAmount - $discountAmount), 2);

        return Invoice::create([
            'tenant_id' => $subscription->tenant_id,
            'customer_id' => $subscription->customer_id,
            'subscription_id' => $subscription->id,
            'invoice_no' => $invoiceNo,
            'amount' => $finalAmount,
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'coupon_code' => $couponCode,
            'coupon_id' => $couponId,
            'currency' => $subscription->currency,
            'status' => 'pending',
            'billing_reason' => $reason,
            'due_at' => now()->addDays(7),
            'paid' => $finalAmount <= 0,
        ]);
    }

    /**
     * 处理支付（M3-56 增强：支持余额优先支付）
     *
     * 优先级：预付余额 → 信用额度 → 支付网关
     */
    public function processPayment(Invoice $invoice): array
    {
        // M3-56: 如果客户有足够余额，先用余额支付
        $customer = $invoice->customer;
        if ($customer && $customer->billing_method === 'prepaid') {
            $balanceResult = $this->prepaidBalanceService->payInvoiceWithBalance($invoice);
            if ($balanceResult['success']) {
                Log::info('Billing: payment processed via prepaid balance', [
                    'invoice_id' => $invoice->id,
                    'method' => $balanceResult['method'],
                ]);
                return [
                    'success' => true,
                    'transaction_id' => 'prepaid_' . $invoice->id,
                    'method' => $balanceResult['method'],
                ];
            }

            // 余额不足时，回退到网关支付
            Log::info('Billing: insufficient balance, falling back to gateway', [
                'invoice_id' => $invoice->id,
                'balance_error' => $balanceResult['error'] ?? 'unknown',
            ]);
        }

        $paymentResult = $this->paymentManager->charge($invoice);

        if ($paymentResult['success']) {
            $gateway = $this->paymentManager->gatewayName();
            $transactionId = $paymentResult['transaction_id'] ?? null;

            if ($this->paymentManager->isAsyncGateway($gateway)) {
                $invoice->update([
                    'metadata' => array_merge($invoice->metadata ?? [], [
                        'pending_transaction_id' => $transactionId,
                        'pending_gateway' => $gateway,
                        'payment_initiated_at' => now()->toIso8601String(),
                    ]),
                ]);

                Log::info('Billing: async payment initiated', [
                    'invoice_id' => $invoice->id,
                    'gateway' => $gateway,
                    'transaction_id' => $transactionId,
                ]);

                return [
                    'success' => true,
                    'async' => true,
                    'transaction_id' => $transactionId,
                    'redirect_url' => $paymentResult['redirect_url'] ?? null,
                    'client_secret' => $paymentResult['client_secret'] ?? null,
                    'payment_form' => $paymentResult['payment_form'] ?? null,
                    'method' => $gateway,
                ];
            }

            $this->markInvoicePaid($invoice, [
                'transaction_id' => $transactionId,
                'payment_method' => $gateway,
                'paid_via' => 'sync',
            ]);

            Log::info('Billing: payment processed', [
                'invoice_id' => $invoice->id,
                'gateway' => $gateway,
                'transaction_id' => $transactionId,
            ]);

            return [
                'success' => true,
                'async' => false,
                'transaction_id' => $transactionId,
                'redirect_url' => $paymentResult['redirect_url'] ?? null,
                'method' => $gateway,
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
                'gateway_charge_id' => $transactionId,
                'metadata' => array_merge($invoice->metadata ?? [], [
                    'transaction_id' => $transactionId,
                ]),
            ]);

            // 如果是续费账单，延长订阅
            if ($invoice->billing_reason === 'subscription_renew' && $invoice->subscription) {
                $this->applyRenewalToSubscription($invoice->subscription);
            }

            // 如果是首次创建，激活 License
            if ($invoice->billing_reason === 'subscription_create' && $invoice->subscription) {
                $this->activateLicensesForSubscription($invoice->subscription);
            }

            // 结算佣金
            $this->commissionEngine->settleInvoice($invoice);

            app(InvoicePaymentSettlementService::class)->settle($invoice->fresh(), [
                'transaction_id' => $transactionId,
                'payment_method' => $invoice->metadata['payment_method'] ?? 'gateway',
            ]);

            return true;
        });
    }

    /**
     * 标记发票已支付（Webhook 用，支持支付渠道信息）
     */
    public function markInvoicePaid(Invoice $invoice, array $paymentInfo): bool
    {
        if ($invoice->status === 'paid') {
            app(InvoicePaymentSettlementService::class)->settle($invoice, $paymentInfo);

            return true;
        }

        return DB::transaction(function () use ($invoice, $paymentInfo) {
            $invoice->update([
                'status' => 'paid',
                'paid_at' => now(),
                'gateway_charge_id' => $paymentInfo['transaction_id'] ?? $invoice->gateway_charge_id,
                'metadata' => array_merge($invoice->metadata ?? [], [
                    'charge_id' => $paymentInfo['charge_id'] ?? $paymentInfo['transaction_id'],
                    'payment_method' => $paymentInfo['payment_method'] ?? 'unknown',
                    'paid_via' => 'webhook',
                ]),
            ]);

            // 激活订阅关联 License
            if ($invoice->subscription) {
                if ($invoice->billing_reason === 'subscription_renew') {
                    $this->applyRenewalToSubscription($invoice->subscription);
                } elseif ($invoice->billing_reason === 'subscription_create' || $invoice->billing_reason === 'subscription_update') {
                    $this->activateLicensesForSubscription($invoice->subscription);
                }
            }

            // 结算佣金
            $this->commissionEngine->settleInvoice($invoice);

            app(InvoicePaymentSettlementService::class)->settle($invoice->fresh(), $paymentInfo);

            return true;
        });
    }

    /**
     * 标记发票已退款
     */
    public function markInvoiceRefunded(Invoice $invoice, array $refundInfo): bool
    {
        return DB::transaction(function () use ($invoice, $refundInfo) {
            $invoice->update([
                'status' => 'refunded',
                'gateway_refund_id' => $refundInfo['refund_id'] ?? $invoice->gateway_refund_id,
                'metadata' => array_merge($invoice->metadata ?? [], [
                    'refund_id' => $refundInfo['refund_id'],
                    'refunded_at' => now()->toIso8601String(),
                ]),
            ]);

            // ⭐ M2-127b 退款时处理佣金回拨
            try {
                $this->commissionEngine->refundSettlement($invoice);
            } catch (\Throwable $e) {
                Log::warning('退款佣金回拨失败', [
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return true;
        });
    }

    /**
     * 续期订阅（Webhook 触发）
     */
    public function renewSubscription(Subscription $subscription): bool
    {
        $this->processRenewal($subscription);
        return true;
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

        // 定价方案统计
        $planDistribution = Subscription::whereIn('status', ['active', 'grace'])
            ->selectRaw('plan, count(*) as count')
            ->groupBy('plan')
            ->pluck('count', 'plan')
            ->toArray();

        // 近期收入
        $recentRevenue = Invoice::where('status', 'paid')
            ->where('paid_at', '>=', $now->copy()->startOfMonth())
            ->sum('amount');

        // 优惠券使用统计
        $couponUsage = CouponRedemption::where('created_at', '>=', $now->copy()->subDays(30))
            ->count();
        $couponSavings = CouponRedemption::where('created_at', '>=', $now->copy()->subDays(30))
            ->sum('discount_amount');

        return [
            'total' => $total,
            'active' => $active,
            'in_grace_period' => $inGrace,
            'expiring_soon_7d' => $expiringSoon,
            'mrr' => $totalMrr,
            'estimated_arr' => round($totalMrr * 12, 2),
            'plan_distribution' => $planDistribution,
            'recent_revenue' => (float) $recentRevenue,
            'coupon_usage_30d' => $couponUsage,
            'coupon_savings_30d' => (float) $couponSavings,
            'total_plans' => PricingPlan::active()->count(),
            'active_coupons' => Coupon::active()->count(),
        ];
    }
}
