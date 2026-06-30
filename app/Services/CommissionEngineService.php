<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\CommissionPlan;
use App\Models\CommissionPlanItem;
use App\Models\CommissionSettlement;
use App\Models\EarningsAccount;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\SubscriptionAgent;
use App\Models\User;
use App\Services\CommissionRiskGuard;
use App\Services\EarningsNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 佣金结算引擎
 *
 * M2-127 核心服务，负责：
 * - 代理注册与归因
 * - 按发票自动结算佣金
 * - 多级佣金计划匹配
 * - 提现管理
 * - 结算统计
 */
class CommissionEngineService
{
    const COMMISSION_RELEASE_DAYS = 30; // 佣金结算后释放天数（防退款）

    public function __construct(
        protected CommissionRiskGuard $riskGuard,
        protected EarningsNotifier $earningsNotifier,
    ) {}

    /**
     * 为用户注册代理
     */
    public function registerAgent(User $user, array $data): Agent
    {
        return DB::transaction(function () use ($user, $data) {
            $agent = Agent::create([
                'user_id' => $user->id,
                'agent_code' => $this->generateAgentCode(),
                'level' => $data['level'] ?? 'regular',
                'status' => 'active',
                'commission_rate' => $data['commission_rate'] ?? null,
                'contact_name' => $data['contact_name'] ?? $user->name,
                'contact_phone' => $data['contact_phone'] ?? $user->phone,
                'company' => $data['company'] ?? null,
                'notes' => $data['notes'] ?? null,
                'approved_at' => now(),
            ]);

            // 在 User 上关联代理 ID
            $user->update(['agent_id' => $agent->id]);

            return $agent;
        });
    }

    /**
     * 归因订阅到代理
     *
     * @param Subscription $subscription
     * @param Agent $agent
     * @param string|null $referralCode 使用的推广码
     * @param string|null $source 归因来源
     */
    public function attributeSubscription(
        Subscription $subscription,
        Agent $agent,
        ?string $referralCode = null,
        ?string $source = null
    ): SubscriptionAgent {
        // 找到匹配的佣金计划
        $plan = $this->resolvePlanForProduct($subscription->product_id, $agent->level);

        return SubscriptionAgent::create([
            'subscription_id' => $subscription->id,
            'agent_id' => $agent->id,
            'commission_plan_id' => $plan?->id,
            'referral_code' => $referralCode,
            'attribution_source' => $source ?? 'link',
            'attributed_at' => now(),
        ]);
    }

    /**
     * 为已付款的发票结算佣金
     *
     * 由 BillingService 在发票标记为已付款时调用。
     */
    public function settleInvoice(Invoice $invoice): ?CommissionSettlement
    {
        if (! $invoice->subscription_id) {
            return null;
        }

        $subscription = $invoice->subscription;
        if (! $subscription) {
            return null;
        }

        // 查找订阅是否有关联代理
        $subAgent = SubscriptionAgent::where('subscription_id', $subscription->id)->first();
        if (! $subAgent) {
            return null;
        }

        $agent = $subAgent->agent;
        if (! $agent || $agent->status !== 'active') {
            return null;
        }

        // 防止重复结算
        $existing = CommissionSettlement::where('invoice_id', $invoice->id)->first();
        if ($existing) {
            return $existing;
        }

        // 计算佣金
        $rate = $this->calculateRate(
            $agent,
            $subscription->product_id,
            $subAgent->commission_plan_id,
            $subscription
        );

        if ($rate <= 0) {
            return null;
        }

        $commissionAmount = round($invoice->total * $rate / 100, 2);

        if ($commissionAmount <= 0) {
            return null;
        }

        return DB::transaction(function () use ($agent, $subscription, $invoice, $rate, $commissionAmount) {
            $settlement = CommissionSettlement::create([
                'agent_id' => $agent->id,
                'subscription_id' => $subscription->id,
                'invoice_id' => $invoice->id,
                'period' => now()->format('Y-m'),
                'status' => 'pending',
                'invoice_amount' => $invoice->total,
                'commission_rate' => $rate,
                'commission_amount' => $commissionAmount,
                'rate_type' => 'percentage',
                'settlement_type' => $this->determineSettlementType($invoice),
                'settled_at' => now(),
                'released_at' => now()->addDays(self::COMMISSION_RELEASE_DAYS),
            ]);

            // 更新代理累计收益
            $agent->increment('total_earned', $commissionAmount);

            // 更新用户佣金余额
            $agent->user?->increment('commission_balance', $commissionAmount);
            $agent->user?->increment('total_commission_earned', $commissionAmount);

            // ⭐ M2-127b 风控：通过 CommissionRiskGuard 冻结佣金（T+30 + 负余额抵扣）
            try {
                $account = $this->riskGuard->resolveEarningsAccount($agent);
                $this->riskGuard->freezeCommission($account, $settlement);
            } catch (\Throwable $e) {
                Log::warning('佣金风控冻结失败，使用默认流程', [
                    'settlement_id' => $settlement->id,
                    'error' => $e->getMessage(),
                ]);
                // 风控非致命，使用原逻辑（设置 released_at 但不走 pending_balance）
            }

            return $settlement;
        });
    }

    /**
     * 结算退款：扣除对应佣金（含风控保障）
     *
     * M2-127b 增强：通过 CommissionRiskGuard 处理退款回拨，
     * 根据退款时间点自动选择 pending / available / 负余额策略。
     */
    public function refundSettlement(Invoice $invoice): void
    {
        $settlements = CommissionSettlement::where('invoice_id', $invoice->id)
            ->whereIn('status', ['pending', 'pending_release', 'released'])
            ->get();

        if ($settlements->isEmpty()) {
            return;
        }

        // 检查是否有对应的 Refund 记录
        $refund = \App\Models\Refund::where('invoice_id', $invoice->id)->latest()->first();

        if ($refund) {
            // 走风控退款逻辑
            $this->riskGuard->handleRefund($invoice, $refund);
        } else {
            // 无 Refund 记录，使用原逻辑
            foreach ($settlements as $settlement) {
                DB::transaction(function () use ($settlement) {
                    $agent = $settlement->agent;
                    $agent->decrement('total_earned', $settlement->commission_amount);
                    $agent->user?->decrement('commission_balance', $settlement->commission_amount);

                    $settlement->update([
                        'status' => 'refunded',
                        'notes' => '发票退款，佣金已扣除',
                    ]);
                });
            }
        }
    }

    /**
     * 释放已过冷静期的结算
     *
     * M2-127b 增强：通过 CommissionRiskGuard 的 releaseExpiredFreezes 完成
     * pending_balance → available_balance 的转移和审计。
     */
    public function releasePendingSettlements(): int
    {
        return $this->riskGuard->releaseExpiredFreezes();
    }

    /**
     * 代理提现请求（含风控检查）
     *
     * M2-127b 增强：提现前执行 CommissionRiskGuard::preWithdrawalCheck
     * - 可用余额 + 最低门槛 + 负余额 + 争议订单 + 每日次数 + 异常模式
     * - 大额提现标记人工审核
     */
    public function requestPayout(Agent $agent, float $amount, string $method, array $accountInfo): CommissionPayout
    {
        // ⭐ 风控检查
        $riskCheck = $this->riskGuard->preWithdrawalCheck($agent, $amount, $method);

        if (! $riskCheck['passed']) {
            $reasonStr = implode('；', $riskCheck['reasons']);
            throw new \RuntimeException('提现风控未通过: ' . $reasonStr);
        }

        // 余额检查（兼容旧逻辑）
        if ($agent->available_balance < $amount) {
            throw new \RuntimeException('可提现余额不足');
        }

        if ($amount < 100) {
            throw new \RuntimeException('最低提现金额为 100 元');
        }

        $fee = $this->calculatePayoutFee($amount, $method);
        $netAmount = $amount - $fee;

        return DB::transaction(function () use ($agent, $amount, $fee, $netAmount, $method, $accountInfo, $riskCheck) {
            $payout = \App\Models\CommissionPayout::create([
                'agent_id' => $agent->id,
                'amount' => $amount,
                'fee' => $fee,
                'net_amount' => $netAmount,
                'status' => $riskCheck['needs_review'] ? 'pending_review' : 'pending',
                'payout_method' => $method,
                'account_info' => encrypt(json_encode($accountInfo)),
                'requested_at' => now(),
            ]);

            // 冻结余额
            $agent->decrement('total_earned', $amount);
            $agent->increment('total_withdrawn', $amount);
            $agent->user?->decrement('commission_balance', $amount);

            // ⭐ M2-127b 同步扣减 earnings_account.available_balance
            try {
                $account = $this->riskGuard->resolveEarningsAccount($agent);
                $account->decrement('available_balance', $amount);
                $account->increment('total_withdrawn', $amount);
            } catch (\Throwable $e) {
                Log::warning('提现同步扣减 earnings_account 失败', [
                    'agent_id' => $agent->id,
                    'amount' => $amount,
                    'error' => $e->getMessage(),
                ]);
            }

            // ⭐ M2-128 发送提现提交通知
            try {
                $this->earningsNotifier->notifyPayoutStatusChanged(
                    agent: $agent,
                    payout: $payout,
                    oldStatus: 'pending',
                    newStatus: $payout->status,
                );
            } catch (\Throwable $e) {
                Log::warning('提现通知发送失败', [
                    'agent_id' => $agent->id,
                    'payout_id' => $payout->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return $payout;
        });
    }

    /**
     * 计算佣金比例
     */
    public function calculateRate(
        Agent $agent,
        ?int $productId,
        ?int $planId = null,
        ?Subscription $subscription = null,
    ): float {
        // 1. 从佣金计划明细中查找精确匹配
        if ($planId) {
            $rate = $this->matchPlanRate($planId, $productId, $agent->level);
            if ($rate !== null) {
                return $rate;
            }
        }

        // 2. 查找激活的计划中匹配产品和等级
        $plans = CommissionPlan::active()->with('items')->get();
        foreach ($plans as $plan) {
            foreach ($plan->items as $item) {
                if ($item->matches($productId, null, $agent->level)) {
                    return $item->commission_rate;
                }
            }
        }

        // 3. 使用代理自身的佣金比例（或等级默认值）
        return $agent->effective_rate;
    }

    /**
     * 在指定计划中查找匹配的佣金率
     */
    protected function matchPlanRate(int $planId, ?int $productId, string $agentLevel): ?float
    {
        $items = CommissionPlanItem::where('commission_plan_id', $planId)
            ->orderBy('priority')
            ->orderBy('product_id', 'desc') // 空 product_id（通配）靠后
            ->get();

        foreach ($items as $item) {
            if ($item->matches($productId, null, $agentLevel)) {
                return $item->commission_rate;
            }
        }

        return null;
    }

    /**
     * 为产品+等级解析最佳佣金计划
     */
    public function resolvePlanForProduct(?int $productId, string $agentLevel): ?CommissionPlan
    {
        $plans = CommissionPlan::active()->with('items')->get();

        $bestPlan = null;
        $bestPriority = PHP_INT_MAX;

        foreach ($plans as $plan) {
            foreach ($plan->items as $item) {
                if ($item->matches($productId, null, $agentLevel)) {
                    if ($item->priority < $bestPriority) {
                        $bestPriority = $item->priority;
                        $bestPlan = $plan;
                    }
                }
            }
        }

        return $bestPlan;
    }

    /**
     * 根据代理推广码查找代理
     */
    public function findAgentByReferralCode(string $code): ?Agent
    {
        $link = \App\Models\ReferralLink::where('code', $code)
            ->where('is_active', true)
            ->first();

        return $link?->agent;
    }

    /**
     * 代理统计
     */
    public function getAgentStats(Agent $agent, ?string $period = null): array
    {
        $query = CommissionSettlement::where('agent_id', $agent->id);

        if ($period) {
            $query->where('period', $period);
        }

        $settlements = $query->get();

        return [
            'total_settled' => $settlements->sum('commission_amount'),
            'total_released' => $settlements->where('status', 'released')->sum('commission_amount'),
            'total_pending' => $settlements->whereIn('status', ['pending', 'pending_release'])->sum('commission_amount'),
            'total_refunded' => $settlements->where('status', 'refunded')->sum('commission_amount'),
            'settlement_count' => $settlements->count(),
            'active_subscriptions' => SubscriptionAgent::where('agent_id', $agent->id)->count(),
            'available_balance' => $agent->available_balance,
        ];
    }

    /**
     * 生成唯一代理编号
     */
    protected function generateAgentCode(): string
    {
        $prefix = 'AGT';
        do {
            $code = $prefix . strtoupper(Str::random(8));
        } while (Agent::where('agent_code', $code)->exists());

        return $code;
    }

    /**
     * 判断结算类型
     */
    protected function determineSettlementType(Invoice $invoice): string
    {
        if ($invoice->items && str_contains($invoice->items, 'renewal')) {
            return 'renewal';
        }
        if ($invoice->subscription && $invoice->subscription->trial_ends_at
            && $invoice->subscription->trial_ends_at->isFuture()) {
            return 'subscription';
        }
        return 'subscription';
    }

    /**
     * 计算提现手续费
     */
    protected function calculatePayoutFee(float $amount, string $method): float
    {
        return match ($method) {
            'alipay', 'wechat' => round($amount * 0.006, 2), // 0.6%
            default => round($amount * 0.01, 2), // 1%
        };
    }
}
