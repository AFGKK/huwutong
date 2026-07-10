<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Models\CreditLimit;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PrepaidBalance;
use App\Models\PrepaidTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 预付余额 + 信用额度核心服务（M3-56）
 *
 * 管理客户预付余额的全生命周期：
 * - 余额充值（recharge）：通过网关充值或管理员手动充值
 * - 余额消费（consume）：用余额抵扣订阅/续费等费用
 * - 余额退款（refund）：退款到余额
 * - 信用额度管理：授信、使用、偿还
 * - 自动充值：余额低于阈值时自动通过默认支付方式充值
 * - 余额支付集成 BillingService
 */
class PrepaidBalanceService
{
    public function __construct(
        protected PaymentManager $paymentManager,
    ) {}

    // ═══════════════════════════════════════════
    // 余额查询
    // ═══════════════════════════════════════════

    /**
     * 获取客户余额（支持懒创建）
     */
    public function getBalance(Customer $customer, string $currency = 'CNY'): PrepaidBalance
    {
        $balance = PrepaidBalance::where('customer_id', $customer->id)
            ->where('currency', $currency)
            ->first();

        if (! $balance) {
            $balance = PrepaidBalance::create([
                'tenant_id' => $customer->tenant_id,
                'customer_id' => $customer->id,
                'currency' => $currency,
                'balance' => 0,
                'total_recharged' => 0,
                'total_consumed' => 0,
                'status' => 'active',
            ]);
        }

        return $balance;
    }

    /**
     * 获取客户可用余额（余额 + 信用额度）
     */
    public function getAvailableFunds(Customer $customer, string $currency = 'CNY'): array
    {
        $balance = $this->getBalance($customer, $currency);
        $credit = CreditLimit::where('customer_id', $customer->id)->first();

        $balanceAmount = (float) $balance->balance;
        $creditLimit = $credit ? (float) $credit->credit_limit : 0;
        $usedCredit = $credit ? (float) $credit->used_credit : 0;
        $availableCredit = max(0, $creditLimit - $usedCredit);

        return [
            'balance' => $balanceAmount,
            'credit_limit' => $creditLimit,
            'credit_used' => $usedCredit,
            'available_credit' => $availableCredit,
            'total_available' => $balanceAmount + $availableCredit,
            'currency' => $currency,
            'status' => $balance->status,
        ];
    }

    // ═══════════════════════════════════════════
    // 充值
    // ═══════════════════════════════════════════

    /**
     * 客户自行充值（通过支付网关）
     */
    public function recharge(
        Customer $customer,
        float $amount,
        string $paymentMethod = 'alipay',
        string $currency = 'CNY',
        ?string $description = null,
    ): array {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('充值金额必须大于 0');
        }

        $balance = $this->getBalance($customer, $currency);
        $transactionNumber = 'TX-' . strtoupper(\Illuminate\Support\Str::random(16));

        // 通过 PaymentManager 调用支付网关
        // 构建一个零金额 Invoice 用于网关调用
        $paymentInvoice = $this->buildRechargeInvoice($customer, $amount, $currency, $paymentMethod);

        try {
            $paymentResult = $this->paymentManager->charge($paymentInvoice);

            $transaction = DB::transaction(function () use ($customer, $balance, $amount, $currency, $paymentMethod, $paymentResult, $transactionNumber, $description) {
                $before = (float) $balance->balance;
                $after = $before + $amount;

                $balance->increment('balance', $amount);
                $balance->increment('total_recharged', $amount);

                return PrepaidTransaction::create([
                    'tenant_id' => $customer->tenant_id,
                    'customer_id' => $customer->id,
                    'type' => 'recharge',
                    'amount' => $amount,
                    'balance_before' => $before,
                    'balance_after' => $after,
                    'currency' => $currency,
                    'payment_method' => $paymentMethod,
                    'gateway_transaction_id' => $paymentResult['transaction_id'] ?? $transactionNumber,
                    'status' => 'completed',
                    'description' => $description ?? "在线充值 {$amount} {$currency}",
                    'completed_at' => now(),
                ]);
            });

            // 同步更新 Customer 快照
            $customer->updateQuietly(['prepaid_balance' => $after ?? $balance->fresh()->balance]);

            Log::info('PrepaidBalance: recharge completed', [
                'customer_id' => $customer->id,
                'amount' => $amount,
                'currency' => $currency,
                'transaction_id' => $transaction->id,
            ]);

            return [
                'success' => true,
                'transaction' => $transaction,
                'balance_after' => $after ?? $balance->fresh()->balance,
            ];
        } catch (\Exception $e) {
            Log::error('PrepaidBalance: recharge failed', [
                'customer_id' => $customer->id,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'balance_after' => (float) $balance->fresh()->balance,
            ];
        }
    }

    /**
     * 管理员手动充值（离线/调账）
     */
    public function adminRecharge(
        Customer $customer,
        float $amount,
        string $currency = 'CNY',
        ?string $description = null,
        ?int $adminUserId = null,
    ): PrepaidTransaction {
        $balance = $this->getBalance($customer, $currency);
        $before = (float) $balance->balance;
        $after = $before + $amount;

        DB::transaction(function () use ($balance, $amount, $customer, $currency, $description, $before, $after) {
            $balance->increment('balance', $amount);
            $balance->increment('total_recharged', $amount);
        });

        $transaction = PrepaidTransaction::create([
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
            'type' => 'recharge',
            'amount' => $amount,
            'balance_before' => $before,
            'balance_after' => $after,
            'currency' => $currency,
            'payment_method' => 'admin',
            'status' => 'completed',
            'description' => $description ?? "管理员手动充值 {$amount} {$currency}",
            'completed_at' => now(),
            'metadata' => ['admin_user_id' => $adminUserId],
        ]);

        $customer->updateQuietly(['prepaid_balance' => $after]);

        Log::info('PrepaidBalance: admin recharge completed', [
            'customer_id' => $customer->id,
            'amount' => $amount,
            'admin_user_id' => $adminUserId,
        ]);

        return $transaction;
    }

    // ═══════════════════════════════════════════
    // 余额扣款（消费）
    // ═══════════════════════════════════════════

    /**
     * 从余额扣款（用于支付订阅/续费等）
     *
     * @return array ['success' => bool, 'transaction' => ?PrepaidTransaction, 'balance_after' => float, 'error' => ?string]
     */
    public function consume(
        Customer $customer,
        float $amount,
        string $currency = 'CNY',
        ?Invoice $invoice = null,
        ?string $description = null,
    ): array {
        $balance = $this->getBalance($customer, $currency);

        if ((float) $balance->balance < $amount) {
            return [
                'success' => false,
                'error' => '余额不足',
                'balance_after' => (float) $balance->balance,
            ];
        }

        $before = (float) $balance->balance;
        $after = $before - $amount;

        DB::transaction(function () use ($balance, $amount, $customer, $before, $after, $invoice, $currency, $description) {
            $balance->decrement('balance', $amount);
            $balance->increment('total_consumed', $amount);
        });

        $transaction = PrepaidTransaction::create([
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
            'invoice_id' => $invoice?->id,
            'type' => 'consume',
            'amount' => -$amount,
            'balance_before' => $before,
            'balance_after' => $after,
            'currency' => $currency,
            'status' => 'completed',
            'description' => $description ?? "余额消费 {$amount} {$currency}",
            'completed_at' => now(),
        ]);

        $customer->updateQuietly(['prepaid_balance' => $after]);

        return [
            'success' => true,
            'transaction' => $transaction,
            'balance_after' => $after,
        ];
    }

    // ═══════════════════════════════════════════
    // 退款到余额
    // ═══════════════════════════════════════════

    /**
     * 退款到客户余额
     */
    public function refund(
        Customer $customer,
        float $amount,
        string $currency = 'CNY',
        ?Invoice $invoice = null,
        ?string $description = null,
    ): PrepaidTransaction {
        $balance = $this->getBalance($customer, $currency);
        $before = (float) $balance->balance;
        $after = $before + $amount;

        DB::transaction(function () use ($balance, $amount) {
            $balance->increment('balance', $amount);
        });

        $transaction = PrepaidTransaction::create([
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
            'invoice_id' => $invoice?->id,
            'type' => 'refund',
            'amount' => $amount,
            'balance_before' => $before,
            'balance_after' => $after,
            'currency' => $currency,
            'status' => 'completed',
            'description' => $description ?? "退款到余额 {$amount} {$currency}",
            'completed_at' => now(),
        ]);

        $customer->updateQuietly(['prepaid_balance' => $after]);

        return $transaction;
    }

    // ═══════════════════════════════════════════
    // 余额调账（管理员）
    // ═══════════════════════════════════════════

    /**
     * 管理员调账（正=增加，负=扣减）
     */
    public function adjust(
        Customer $customer,
        float $amount,
        string $currency = 'CNY',
        ?string $description = null,
        ?int $adminUserId = null,
    ): array {
        $balance = $this->getBalance($customer, $currency);
        $before = (float) $balance->balance;
        $after = $before + $amount;

        if ($after < 0) {
            return [
                'success' => false,
                'error' => '调账后余额不能为负数',
            ];
        }

        DB::transaction(function () use ($balance, $amount) {
            $balance->increment('balance', $amount);
        });

        $transaction = PrepaidTransaction::create([
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
            'type' => 'adjust',
            'amount' => $amount,
            'balance_before' => $before,
            'balance_after' => $after,
            'currency' => $currency,
            'status' => 'completed',
            'description' => $description ?? ($amount >= 0 ? "管理员调账 +{$amount}" : "管理员调账 {$amount}"),
            'completed_at' => now(),
            'metadata' => ['admin_user_id' => $adminUserId],
        ]);

        $customer->updateQuietly(['prepaid_balance' => $after]);

        return [
            'success' => true,
            'transaction' => $transaction,
            'balance_after' => $after,
        ];
    }

    // ═══════════════════════════════════════════
    // 信用额度
    // ═══════════════════════════════════════════

    /**
     * 获取/创建信用额度
     */
    public function getCreditLimit(Customer $customer): CreditLimit
    {
        $credit = CreditLimit::where('customer_id', $customer->id)->first();

        if (! $credit) {
            $credit = CreditLimit::create([
                'tenant_id' => $customer->tenant_id,
                'customer_id' => $customer->id,
                'credit_limit' => 0,
                'used_credit' => 0,
                'grace_days' => 0,
                'status' => 'active',
            ]);
        }

        return $credit;
    }

    /**
     * 设置信用额度
     */
    public function setCreditLimit(
        Customer $customer,
        float $limit,
        int $graceDays = 0,
    ): CreditLimit {
        $credit = $this->getCreditLimit($customer);
        $credit->update([
            'credit_limit' => $limit,
            'grace_days' => $graceDays,
        ]);

        $customer->updateQuietly([
            'credit_limit' => $limit,
        ]);

        Log::info('PrepaidBalance: credit limit updated', [
            'customer_id' => $customer->id,
            'new_limit' => $limit,
            'grace_days' => $graceDays,
        ]);

        return $credit;
    }

    /**
     * 使用信用额度（当余额不足时）
     */
    public function useCredit(
        Customer $customer,
        float $amount,
        ?string $description = null,
    ): array {
        $credit = $this->getCreditLimit($customer);
        $available = $credit->available_credit;

        if ($available < $amount) {
            return [
                'success' => false,
                'error' => '信用额度不足',
                'available_credit' => $available,
            ];
        }

        $usedBefore = (float) $credit->used_credit;
        $usedAfter = $usedBefore + $amount;

        DB::transaction(function () use ($credit, $amount) {
            $credit->increment('used_credit', $amount);
        });

        PrepaidTransaction::create([
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
            'type' => 'credit_use',
            'amount' => -$amount,
            'balance_before' => 0,
            'balance_after' => 0,
            'currency' => 'CNY',
            'status' => 'completed',
            'description' => $description ?? "使用信用额度 {$amount} CNY",
            'completed_at' => now(),
        ]);

        $customer->updateQuietly(['credit_used' => $usedAfter]);

        return [
            'success' => true,
            'used_credit' => $usedAfter,
            'available_credit' => $credit->fresh()->available_credit,
        ];
    }

    /**
     * 偿还信用额度
     */
    public function repayCredit(
        Customer $customer,
        float $amount,
        ?string $description = null,
    ): array {
        $credit = $this->getCreditLimit($customer);
        $usedBefore = (float) $credit->used_credit;
        $usedAfter = max(0, $usedBefore - $amount);

        DB::transaction(function () use ($credit, $amount, $usedAfter) {
            $credit->decrement('used_credit', $amount);
        });

        PrepaidTransaction::create([
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
            'type' => 'credit_repay',
            'amount' => $amount,
            'balance_before' => 0,
            'balance_after' => 0,
            'currency' => 'CNY',
            'status' => 'completed',
            'description' => $description ?? "偿还信用额度 {$amount} CNY",
            'completed_at' => now(),
        ]);

        $customer->updateQuietly(['credit_used' => $usedAfter]);

        return [
            'success' => true,
            'used_credit' => $usedAfter,
            'available_credit' => $credit->fresh()->available_credit,
        ];
    }

    // ═══════════════════════════════════════════
    // 交易记录
    // ═══════════════════════════════════════════

    /**
     * 获取交易流水
     */
    public function getTransactions(
        Customer $customer,
        array $filters = [],
        int $perPage = 20,
    ): \Illuminate\Contracts\Pagination\LengthAwarePaginator {
        $query = PrepaidTransaction::where('customer_id', $customer->id);

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    // ═══════════════════════════════════════════
    // 自动充值
    // ═══════════════════════════════════════════

    /**
     * 检查是否需要自动充值（余额低于阈值时通过默认支付方式充值固定金额）
     */
    public function checkAutoRecharge(Customer $customer, string $currency = 'CNY'): ?array
    {
        $settings = $this->getAutoRechargeSettings($customer, $currency);
        if (! $settings || ! $settings['enabled']) {
            return null;
        }

        $balance = $this->getBalance($customer, $currency);
        if ((float) $balance->balance >= (float) $settings['threshold']) {
            return null;
        }

        // 触发自动充值
        $result = $this->recharge(
            $customer,
            (float) $settings['amount'],
            $settings['payment_method'] ?? 'alipay',
            $currency,
            '自动充值（余额低于阈值）',
        );

        return [
            'auto_recharged' => $result['success'],
            'threshold' => $settings['threshold'],
            'amount' => $settings['amount'],
            'result' => $result,
        ];
    }

    /**
     * 保存自动充值设置（存储在 metadata 中）
     */
    public function saveAutoRechargeSettings(
        Customer $customer,
        bool $enabled,
        float $threshold,
        float $amount,
        string $paymentMethod = 'alipay',
        string $currency = 'CNY',
    ): PrepaidBalance {
        $balance = $this->getBalance($customer, $currency);
        $meta = $balance->metadata ?? [];
        $meta['auto_recharge'] = [
            'enabled' => $enabled,
            'threshold' => $threshold,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'updated_at' => now()->toIso8601String(),
        ];
        $balance->update(['metadata' => $meta]);
        return $balance;
    }

    /**
     * 获取自动充值设置
     */
    public function getAutoRechargeSettings(Customer $customer, string $currency = 'CNY'): ?array
    {
        $balance = $this->getBalance($customer, $currency);
        return $balance->metadata['auto_recharge'] ?? null;
    }

    // ═══════════════════════════════════════════
    // 余额支付（直接在 BillingService 中调用）
    // ═══════════════════════════════════════════

    /**
     * 使用余额支付发票（BillingService 集成点）
     *
     * 优先级：余额 > 信用额度 > 余额+信用额度
     */
    public function payInvoiceWithBalance(Invoice $invoice): array
    {
        $customer = $invoice->customer;
        $amount = (float) $invoice->amount;

        // 获取实时余额（而非 Customer 模型上的快照字段）
        $balance = $this->getBalance($customer, 'CNY');
        $availableBalance = (float) $balance->balance;

        // 1. 先用余额支付
        $actualConsume = min($amount, $availableBalance);
        $consumeResult = $this->consume($customer, $actualConsume, 'CNY', $invoice);

        if ($consumeResult['success']) {
            $consumedAmount = abs((float) ($consumeResult['transaction']->amount ?? 0));
            if ($consumedAmount >= $amount) {
                // 全额余额支付
                $invoice->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                    'payment_method' => 'prepaid',
                ]);
                return ['success' => true, 'method' => 'prepaid'];
            }
        }

        // 2. 余额不够时，检查信用额度
        $remaining = $amount - (float) ($consumeResult['success'] ? abs($consumeResult['transaction']->amount ?? 0) : 0);
        if ($remaining <= 0) {
            return ['success' => true, 'method' => 'prepaid'];
        }

        $credit = $this->getCreditLimit($customer);
        if ($credit->available_credit >= $remaining) {
            $this->useCredit($customer, $remaining, "支付发票 {$invoice->invoice_no}");
            $invoice->update([
                'status' => 'paid',
                'paid_at' => now(),
                'payment_method' => 'credit',
            ]);
            return ['success' => true, 'method' => 'credit'];
        }

        // 3. 都不够，返回失败
        return [
            'success' => false,
            'error' => '余额和信用额度均不足',
            'balance_shortfall' => $remaining,
        ];
    }

    // ═══════════════════════════════════════════
    // 统计
    // ═══════════════════════════════════════════

    /**
     * 获取余额统计（管理后台）
     */
    public function getStats(int $tenantId): array
    {
        $totalBalance = PrepaidBalance::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->sum('balance');

        $totalRecharged = PrepaidTransaction::where('tenant_id', $tenantId)
            ->where('type', 'recharge')
            ->where('status', 'completed')
            ->sum('amount');

        $totalConsumed = PrepaidTransaction::where('tenant_id', $tenantId)
            ->where('type', 'consume')
            ->where('status', 'completed')
            ->sum(\DB::raw('ABS(amount)'));

        $activeAccounts = PrepaidBalance::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where('balance', '>', 0)
            ->count();

        $totalCustomers = Customer::where('tenant_id', $tenantId)->count();

        $autoRechargeCount = PrepaidBalance::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where('metadata->auto_recharge->enabled', true)
            ->count();

        $lowBalanceCount = PrepaidBalance::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where('balance', '>', 0)
            ->where('balance', '<', 50)
            ->count();

        // 近30天充值趋势
        $recentRecharges = PrepaidTransaction::where('tenant_id', $tenantId)
            ->where('type', 'recharge')
            ->where('status', 'completed')
            ->where('created_at', '>=', now()->subDays(30))
            ->sum('amount');

        // 信用额度统计
        $creditStats = \DB::table('credit_limits')
            ->where('tenant_id', $tenantId)
            ->selectRaw('COUNT(*) as total_credits')
            ->selectRaw('SUM(credit_limit) as total_credit_limit')
            ->selectRaw('SUM(used_credit) as total_credit_used')
            ->first();

        return [
            'total_balance' => (float) $totalBalance,
            'total_recharged' => (float) $totalRecharged,
            'total_consumed' => (float) $totalConsumed,
            'active_accounts' => $activeAccounts,
            'total_customers' => $totalCustomers,
            'penetration_rate' => $totalCustomers > 0 ? round($activeAccounts / $totalCustomers * 100, 1) : 0,
            'auto_recharge_users' => $autoRechargeCount,
            'low_balance_accounts' => $lowBalanceCount,
            'recent_30d_recharges' => (float) $recentRecharges,
            'credit' => [
                'total_accounts' => (int) ($creditStats->total_credits ?? 0),
                'total_limit' => (float) ($creditStats->total_credit_limit ?? 0),
                'total_used' => (float) ($creditStats->total_credit_used ?? 0),
                'utilization_rate' => ($creditStats->total_credit_limit ?? 0) > 0
                    ? round(($creditStats->total_credit_used ?? 0) / ($creditStats->total_credit_limit ?? 0) * 100, 1)
                    : 0,
            ],
        ];
    }

    // ═══════════════════════════════════════════
    // 辅助方法
    // ═══════════════════════════════════════════

    /**
     * 构建充值用的"发票"（用于 PaymentManager 调用）
     */
    protected function buildRechargeInvoice(Customer $customer, float $amount, string $currency, string $paymentMethod): Invoice
    {
        $invoice = new Invoice();
        $invoice->tenant_id = $customer->tenant_id;
        $invoice->customer_id = $customer->id;
        $invoice->amount = $amount;
        $invoice->subtotal = $amount;
        $invoice->currency = $currency;
        $invoice->payment_method = $paymentMethod;
        $invoice->billing_reason = 'prepaid_recharge';
        $invoice->status = 'pending';
        return $invoice;
    }
}
