<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\CommissionSettlement;
use App\Models\EarningsAccount;
use App\Models\SubscriptionAgent;
use App\Models\Withdrawal;
use App\Services\CommissionRiskGuard;
use App\Services\WithdrawalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * 收益账户客户门户 API
 *
 * M3-74 面向最终用户的收益查看、提现操作和通知管理
 */
class EarningsPortalController extends Controller
{
    public function __construct(
        protected CommissionRiskGuard $riskGuard,
        protected WithdrawalService $withdrawalService,
    ) {}

    /**
     * 收益门户首页 - 账户总览
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $account = $this->resolveEarningsAccount($user);

        // 收益余额信息
        $balanceData = [
            'available_balance' => (float) $account->available_balance,
            'pending_balance' => (float) $account->pending_balance,
            'frozen_amount' => (float) $account->frozen_amount,
            'total_withdrawn' => (float) $account->total_withdrawn,
            'total_earned' => (float) $account->available_balance + (float) $account->total_withdrawn,
            'account_status' => $account->status,
        ];

        // 月度收益趋势（近12个月）
        $monthlyTrend = CommissionSettlement::whereIn('agent_id', function ($q) use ($user) {
            $q->select('id')->from('agents')->where('user_id', $user->id);
        })
            ->selectRaw("period, SUM(commission_amount) as amount, COUNT(*) as count")
            ->where('period', '>=', now()->subMonths(12)->format('Y-m'))
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        // 近期收益明细（最近10条）
        $recentCommissions = Commission::where('earnings_account_id', $account->id)
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'amount' => (float) $c->amount,
                'rate' => (float) $c->rate,
                'status' => $c->status,
                'status_label' => match ($c->status) {
                    'frozen' => '冻结中',
                    'released' => '可提现',
                    'refunded' => '已退回',
                    default => $c->status,
                },
                'frozen_until' => $c->frozen_until?->toDateString(),
                'settled_at' => $c->settled_at?->toIso8601String(),
            ]);

        // 近期提现记录（最近5条）
        $recentWithdrawals = Withdrawal::where('user_id', $user->id)
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn($w) => [
                'id' => $w->id,
                'amount' => (float) $w->amount,
                'fee' => (float) $w->fee,
                'net_amount' => (float) $w->net_amount,
                'channel' => $w->channel,
                'channel_display' => $w->channel_display,
                'channel_account_masked' => $w->channel_account_masked,
                'status' => $w->status,
                'status_label' => match ($w->status) {
                    'pending_review' => '待审核',
                    'pending' => '待打款',
                    'processing' => '处理中',
                    'completed' => '已到账',
                    'failed' => '打款失败',
                    'rejected' => '已驳回',
                    'cancelled' => '已取消',
                    default => $w->status,
                },
                'failure_reason' => $w->failure_reason,
                'created_at' => $w->created_at?->toIso8601String(),
                'completed_at' => $w->completed_at?->toIso8601String(),
            ]);

        // 推广统计数据
        $promoStats = $this->getPromotionStats($user);

        return response()->json([
            'success' => true,
            'data' => [
                'balance' => $balanceData,
                'monthly_trend' => $monthlyTrend,
                'recent_commissions' => $recentCommissions,
                'recent_withdrawals' => $recentWithdrawals,
                'promotion_stats' => $promoStats,
            ],
        ]);
    }

    /**
     * 收益明细列表
     */
    public function commissions(Request $request): JsonResponse
    {
        $user = $request->user();
        $account = $this->resolveEarningsAccount($user);

        $query = Commission::where('earnings_account_id', $account->id);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('settled_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('settled_at', '<=', $request->input('date_to'));
        }

        $commissions = $query->latest()->paginate($request->input('per_page', 20));

        $commissions->getCollection()->transform(fn($c) => [
            'id' => $c->id,
            'amount' => (float) $c->amount,
            'rate' => (float) $c->rate,
            'status' => $c->status,
            'status_label' => match ($c->status) {
                'frozen' => '冻结中',
                'released' => '可提现',
                'refunded' => '已退回',
                default => $c->status,
            },
            'frozen_until' => $c->frozen_until?->toDateString(),
            'settled_at' => $c->settled_at?->toIso8601String(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $commissions,
        ]);
    }

    /**
     * 获取可用的提现渠道（含用户已保存的账户信息）
     */
    public function withdrawalChannels(Request $request): JsonResponse
    {
        $user = $request->user();
        $account = $this->resolveEarningsAccount($user);
        $metadata = $account->metadata ?? [];

        $channels = [];
        foreach (WithdrawalService::CHANNELS as $ch) {
            $limits = WithdrawalService::CHANNEL_LIMITS[$ch];
            $savedAccount = $metadata['saved_accounts'][$ch] ?? null;

            $channel = [
                'id' => $ch,
                'name' => match ($ch) {
                    'bank' => '银行卡',
                    'alipay' => '支付宝',
                    'wechat' => '微信',
                    'paypal' => 'PayPal',
                },
                'icon' => match ($ch) {
                    'bank' => 'CreditCard',
                    'alipay' => 'Wallet',
                    'wechat' => 'ChatDotSquare',
                    'paypal' => 'Coin',
                },
                'min_amount' => $limits['min'],
                'max_amount' => $limits['max'],
                'fee_rate' => WithdrawalService::FEE_RATES[$ch],
                'daily_limit' => WithdrawalService::DAILY_LIMITS[$ch],
                'has_saved_account' => $savedAccount !== null,
                'saved_account' => $savedAccount ? $this->maskSavedAccount($ch, $savedAccount) : null,
            ];
            $channels[] = $channel;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'channels' => $channels,
                'available_balance' => (float) $account->available_balance,
                'pending_balance' => (float) $account->pending_balance,
            ],
        ]);
    }

    /**
     * 保存提现账户信息到 metadata
     */
    public function saveAccount(Request $request): JsonResponse
    {
        $user = $request->user();
        $account = $this->resolveEarningsAccount($user);

        $validated = $request->validate([
            'channel' => 'required|in:bank,alipay,wechat,paypal',
            'account_info' => 'required|array',
        ]);

        $channel = $validated['channel'];
        $accountInfo = $validated['account_info'];

        // 验证账户信息
        $errors = $this->withdrawalService->validateChannelAccount($channel, $accountInfo);
        if (!empty($errors)) {
            return response()->json(['success' => false, 'message' => implode('；', $errors)], 422);
        }

        $metadata = $account->metadata ?? [];
        $metadata['saved_accounts'][$channel] = $accountInfo;
        $account->update(['metadata' => $metadata]);

        return response()->json([
            'success' => true,
            'message' => '账户信息已保存',
            'data' => [
                'channel' => $channel,
                'saved_account' => $this->maskSavedAccount($channel, $accountInfo),
            ],
        ]);
    }

    /**
     * 删除保存的提现账户
     */
    public function deleteAccount(Request $request, string $channel): JsonResponse
    {
        $user = $request->user();
        $account = $this->resolveEarningsAccount($user);

        if (!in_array($channel, WithdrawalService::CHANNELS)) {
            return response()->json(['success' => false, 'message' => '无效的渠道'], 422);
        }

        $metadata = $account->metadata ?? [];
        unset($metadata['saved_accounts'][$channel]);
        $account->update(['metadata' => $metadata]);

        return response()->json([
            'success' => true,
            'message' => '账户信息已删除',
        ]);
    }

    /**
     * 获取/保存收益账户元数据中的偏好设置
     */
    public function preferences(Request $request): JsonResponse
    {
        $user = $request->user();
        $account = $this->resolveEarningsAccount($user);

        if ($request->isMethod('get')) {
            $metadata = $account->metadata ?? [];
            return response()->json([
                'success' => true,
                'data' => [
                    'min_withdrawal_notify' => $metadata['prefs']['min_withdrawal_notify'] ?? 100,
                    'auto_withdraw' => $metadata['prefs']['auto_withdraw'] ?? false,
                    'auto_withdraw_channel' => $metadata['prefs']['auto_withdraw_channel'] ?? null,
                    'auto_withdraw_threshold' => $metadata['prefs']['auto_withdraw_threshold'] ?? 1000,
                ],
            ]);
        }

        $validated = $request->validate([
            'min_withdrawal_notify' => 'nullable|numeric|min:1',
            'auto_withdraw' => 'nullable|boolean',
            'auto_withdraw_channel' => 'nullable|in:bank,alipay,wechat,paypal',
            'auto_withdraw_threshold' => 'nullable|numeric|min:100',
        ]);

        $metadata = $account->metadata ?? [];
        $metadata['prefs'] = array_merge($metadata['prefs'] ?? [], $validated);
        $account->update(['metadata' => $metadata]);

        return response()->json([
            'success' => true,
            'message' => '偏好设置已保存',
        ]);
    }

    /**
     * 推广业绩汇总
     */
    protected function getPromotionStats($user): array
    {
        $agent = \App\Models\Agent::where('user_id', $user->id)->first();
        if (!$agent) {
            return [
                'total_referrals' => 0,
                'active_subscriptions' => 0,
                'total_earned' => 0,
                'level' => null,
                'agent_code' => null,
            ];
        }

        return [
            'total_referrals' => (int) SubscriptionAgent::where('agent_id', $agent->id)->count(),
            'active_subscriptions' => (int) SubscriptionAgent::where('agent_id', $agent->id)
                ->whereHas('subscription', fn($q) => $q->where('status', 'active'))
                ->count(),
            'total_earned' => (float) $agent->total_earned,
            'level' => $agent->level,
            'level_label' => match ($agent->level) {
                'regular' => '普通合作伙伴',
                'silver' => '银牌合作伙伴',
                'gold' => '金牌合作伙伴',
                'platinum' => '铂金合作伙伴',
                default => $agent->level,
            },
            'agent_code' => $agent->agent_code,
        ];
    }

    /**
     * 解析或创建收益账户
     */
    protected function resolveEarningsAccount($user): EarningsAccount
    {
        return EarningsAccount::firstOrCreate(
            ['user_id' => $user->id],
            [
                'tenant_id' => $user->tenant_id,
                'type' => 'agent',
                'pending_balance' => 0,
                'available_balance' => 0,
                'total_withdrawn' => 0,
                'frozen_amount' => 0,
                'status' => 'active',
            ]
        );
    }

    /**
     * 脱敏显示已保存的账户
     */
    protected function maskSavedAccount(string $channel, array $account): array
    {
        $masked = $account;
        switch ($channel) {
            case 'bank':
                if (isset($masked['bank_account_no'])) {
                    $masked['bank_account_no'] = '****' . substr($masked['bank_account_no'], -4);
                }
                break;
            case 'alipay':
                if (isset($masked['alipay_account'])) {
                    $acc = $masked['alipay_account'];
                    $masked['alipay_account'] = substr($acc, 0, 3) . '****' . substr($acc, -3);
                }
                break;
            case 'wechat':
                if (isset($masked['wechat_account'])) {
                    $acc = $masked['wechat_account'];
                    $masked['wechat_account'] = substr($acc, 0, 2) . '****' . substr($acc, -2);
                }
                break;
            case 'paypal':
                if (isset($masked['paypal_email'])) {
                    $parts = explode('@', $masked['paypal_email']);
                    $masked['paypal_email'] = substr($parts[0], 0, 2) . '***@' . ($parts[1] ?? '');
                }
                break;
        }
        return $masked;
    }

    // ─── M3-74 补充功能 ───

    /**
     * 税务信息管理 (发票抬头)
     *
     * GET/POST /portal/earnings/tax-info
     */
    public function taxInfo(Request $request): JsonResponse
    {
        $user = $request->user();
        $account = $this->resolveEarningsAccount($user);

        if ($request->isMethod('get')) {
            $metadata = $account->metadata ?? [];
            return response()->json([
                'success' => true,
                'data' => [
                    'tax_id' => $metadata['tax']['tax_id'] ?? '',
                    'company_name' => $metadata['tax']['company_name'] ?? '',
                    'invoice_title' => $metadata['tax']['invoice_title'] ?? '',
                    'tax_authority' => $metadata['tax']['tax_authority'] ?? 'cn',
                    'tax_rate' => $metadata['tax']['tax_rate'] ?? 0,
                    'address' => $metadata['tax']['address'] ?? '',
                    'phone' => $metadata['tax']['phone'] ?? '',
                    'bank_name' => $metadata['tax']['bank_name'] ?? '',
                    'bank_account' => $metadata['tax']['bank_account'] ?? '',
                ],
            ]);
        }

        $validated = $request->validate([
            'tax_id' => 'nullable|string|max:50',
            'company_name' => 'nullable|string|max:200',
            'invoice_title' => 'nullable|string|max:200',
            'tax_authority' => 'nullable|in:cn,us,eu,other',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:30',
            'bank_name' => 'nullable|string|max:200',
            'bank_account' => 'nullable|string|max:50',
        ]);

        $metadata = $account->metadata ?? [];
        $metadata['tax'] = $validated;
        $account->update(['metadata' => $metadata]);

        return response()->json([
            'success' => true,
            'message' => '税务信息已保存',
        ]);
    }

    /**
     * 结算日历
     *
     * GET /portal/earnings/settlement-calendar
     */
    public function settlementCalendar(Request $request): JsonResponse
    {
        $user = $request->user();
        $account = $this->resolveEarningsAccount($user);

        // 即将解冻的佣金
        $upcomingReleases = Commission::where('earnings_account_id', $account->id)
            ->where('status', 'frozen')
            ->whereNotNull('frozen_until')
            ->orderBy('frozen_until')
            ->limit(30)
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'amount' => (float) $c->amount,
                'frozen_until' => $c->frozen_until?->toDateString(),
                'days_left' => now()->diffInDays($c->frozen_until, false),
                'order_id' => $c->order_id,
            ]);

        // 本月结算概括
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();
        $monthSettlements = [
            'total_frozen' => (float) Commission::where('earnings_account_id', $account->id)
                ->where('status', 'frozen')->sum('amount'),
            'will_release_this_month' => (float) Commission::where('earnings_account_id', $account->id)
                ->where('status', 'frozen')
                ->where('frozen_until', '<=', $monthEnd)
                ->sum('amount'),
            'released_this_month' => (float) Commission::where('earnings_account_id', $account->id)
                ->where('status', 'released')
                ->where('settled_at', '>=', $monthStart)
                ->sum('amount'),
        ];

        // 按月份汇总
        $byMonth = Commission::where('earnings_account_id', $account->id)
            ->where('status', 'frozen')
            ->selectRaw("strftime('%Y-%m', frozen_until) as month, SUM(amount) as total, COUNT(*) as count")
            ->whereNotNull('frozen_until')
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->toArray();

        return response()->json([
            'success' => true,
            'data' => [
                'upcoming_releases' => $upcomingReleases,
                'month_summary' => $monthSettlements,
                'by_month' => $byMonth,
                'current_pending' => (float) $account->pending_balance,
                'available_balance' => (float) $account->available_balance,
            ],
        ]);
    }

    /**
     * 导出佣金明细 CSV
     *
     * GET /portal/earnings/commissions/export
     */
    public function exportCommissions(Request $request): \Illuminate\Http\Response
    {
        $user = $request->user();
        $account = $this->resolveEarningsAccount($user);

        $query = Commission::where('earnings_account_id', $account->id);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('settled_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('settled_at', '<=', $request->input('date_to'));
        }

        $commissions = $query->latest()->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="commissions_export_' . now()->format('Ymd') . '.csv"',
        ];

        $callback = function () use ($commissions) {
            $handle = fopen('php://output', 'w');
            // BOM for Excel UTF-8
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, ['ID', '金额', '佣金率', '状态', '解冻日期', '入账时间']);

            foreach ($commissions as $c) {
                fputcsv($handle, [
                    $c->id,
                    (float) $c->amount,
                    (float) $c->rate . '%',
                    match ($c->status) {
                        'frozen' => '冻结中',
                        'released' => '可提现',
                        'refunded' => '已退回',
                        default => $c->status,
                    },
                    $c->frozen_until?->toDateString() ?? '',
                    $c->settled_at?->toIso8601String() ?? '',
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
