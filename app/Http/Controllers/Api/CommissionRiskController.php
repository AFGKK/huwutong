<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\CommissionSettlement;
use App\Models\EarningsAccount;
use App\Models\CommissionPayout;
use App\Services\CommissionRiskGuard;
use App\Services\EarningsNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 佣金风控管理 API
 *
 * M2-127b 佣金风控保障机制 — 风控管理后台端点
 * 提供负余额追缴、风控状态查询、手动干预等功能。
 */
class CommissionRiskController extends Controller
{
    public function __construct(
        protected CommissionRiskGuard $riskGuard,
        protected EarningsNotifier $earningsNotifier,
    ) {}

    /**
     * 风控仪表盘总览
     */
    public function dashboard(): JsonResponse
    {
        // 负余额统计
        $negativeAccounts = EarningsAccount::where('metadata->negative_balance', '>', 0)->get();
        $totalNegativeBalance = $negativeAccounts->sum(function ($a) {
            return (float) ($a->metadata['negative_balance'] ?? 0);
        });

        // 待释放冻结佣金
        $pendingFrozenAmount = CommissionSettlement::where('status', 'pending')
            ->sum('commission_amount');

        // 待审核提现
        $pendingReviewPayouts = CommissionPayout::where('status', 'pending_review')
            ->count();
        $pendingReviewAmount = CommissionPayout::where('status', 'pending_review')
            ->sum('amount');

        // 今日风控事件数
        $todayRiskEvents = CommissionSettlement::where('status', 'refunded')
            ->whereDate('updated_at', today())
            ->count();

        // 冻结账户数
        $frozenAccounts = EarningsAccount::where('status', 'frozen')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'negative_balance_accounts' => $negativeAccounts->count(),
                'total_negative_balance' => round($totalNegativeBalance, 2),
                'pending_frozen_amount' => round($pendingFrozenAmount, 2),
                'pending_review_payouts' => $pendingReviewPayouts,
                'pending_review_amount' => round($pendingReviewAmount, 2),
                'today_risk_events' => $todayRiskEvents,
                'frozen_accounts' => $frozenAccounts,
            ],
        ]);
    }

    /**
     * 负余额账户列表
     */
    public function negativeBalanceAccounts(Request $request): JsonResponse
    {
        $query = EarningsAccount::where('metadata->negative_balance', '>', 0)
            ->with('user:id,name,email');

        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%"));
        }

        $accounts = $query->paginate($request->input('per_page', 20))
            ->through(function (EarningsAccount $account) {
                $metadata = $account->metadata ?? [];
                return [
                    'id' => $account->id,
                    'user' => $account->user,
                    'negative_balance' => (float) ($metadata['negative_balance'] ?? 0),
                    'negative_balance_since' => $metadata['negative_balance_since'] ?? null,
                    'available_balance' => (float) $account->available_balance,
                    'pending_balance' => (float) $account->pending_balance,
                    'status' => $account->status,
                    'days_overdue' => isset($metadata['negative_balance_since'])
                        ? now()->diffInDays($metadata['negative_balance_since'])
                        : 0,
                ];
            });

        return response()->json(['success' => true, 'data' => $accounts]);
    }

    /**
     * 负余额账户详情
     */
    public function negativeBalanceDetail(EarningsAccount $earningsAccount): JsonResponse
    {
        $metadata = $earningsAccount->metadata ?? [];
        $negativeBalance = (float) ($metadata['negative_balance'] ?? 0);

        // 关联的结算记录
        $settlements = CommissionSettlement::whereIn('agent_id', function ($q) use ($earningsAccount) {
            $q->select('id')->from('agents')->where('user_id', $earningsAccount->user_id);
        })
            ->where('status', 'refunded')
            ->latest()
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'account' => $earningsAccount->load('user:id,name,email'),
                'negative_balance' => $negativeBalance,
                'negative_balance_since' => $metadata['negative_balance_since'] ?? null,
                'days_overdue' => isset($metadata['negative_balance_since'])
                    ? now()->diffInDays($metadata['negative_balance_since'])
                    : 0,
                'related_refunds' => $settlements,
            ],
        ]);
    }

    /**
     * 手动释放负余额（管理员操作）
     */
    public function clearNegativeBalance(Request $request, EarningsAccount $earningsAccount): JsonResponse
    {
        $validator = validator($request->all(), [
            'amount' => 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $amount = (float) $request->input('amount');
        $metadata = $earningsAccount->metadata ?? [];
        $currentNegative = (float) ($metadata['negative_balance'] ?? 0);

        if ($amount > $currentNegative) {
            return response()->json([
                'success' => false,
                'message' => "清除金额不能超过当前负余额 ¥{$currentNegative}",
            ], 422);
        }

        $newNegative = round($currentNegative - $amount, 2);

        if ($newNegative <= 0) {
            unset($metadata['negative_balance']);
            unset($metadata['negative_balance_since']);
            $earningsAccount->update(['metadata' => $metadata]);

            // 恢复代理状态
            Agent::where('user_id', $earningsAccount->user_id)
                ->where('status', 'suspended')
                ->update(['status' => 'active']);
        } else {
            $metadata['negative_balance'] = $newNegative;
            $earningsAccount->update(['metadata' => $metadata]);
        }

        return response()->json([
            'success' => true,
            'message' => "已清除负余额 ¥{$amount}，剩余 ¥{$newNegative}",
            'data' => ['remaining_negative' => $newNegative],
        ]);
    }

    /**
     * 提现审批列表（待审核）
     */
    public function pendingReviewPayouts(Request $request): JsonResponse
    {
        $query = CommissionPayout::where('status', 'pending_review')
            ->with('agent.user:id,name,email')
            ->orderBy('created_at', 'asc');

        return response()->json([
            'success' => true,
            'data' => $query->paginate($request->input('per_page', 20)),
        ]);
    }

    /**
     * 提现审批操作
     */
    public function reviewPayout(Request $request, CommissionPayout $commissionPayout): JsonResponse
    {
        $validator = validator($request->all(), [
            'action' => 'required|in:approve,reject',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $action = $request->input('action');
        $notes = $request->input('notes');

        if ($action === 'approve') {
            $commissionPayout->update([
                'status' => 'pending',
                'notes' => ($commissionPayout->notes ?? '') . ' | 审批通过: ' . ($notes ?? ''),
            ]);
            $message = '提现已审批通过，进入处理流程';

            // ⭐ M2-128 发送审批通过通知
            try {
                $agent = $commissionPayout->agent;
                if ($agent) {
                    $this->earningsNotifier->notifyPayoutStatusChanged(
                        agent: $agent,
                        payout: $commissionPayout->fresh(),
                        oldStatus: 'pending_review',
                        newStatus: 'pending',
                    );
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('审批通过通知发送失败', ['payout_id' => $commissionPayout->id]);
            }
        } else {
            // 拒绝：退还余额
            $agent = $commissionPayout->agent;
            $agent->increment('total_earned', $commissionPayout->amount);
            $agent->decrement('total_withdrawn', $commissionPayout->amount);
            $agent->user?->increment('commission_balance', $commissionPayout->amount);

            // 退还 earnings_account
            try {
                $account = $this->riskGuard->resolveEarningsAccount($agent);
                $account->increment('available_balance', $commissionPayout->amount);
                $account->decrement('total_withdrawn', $commissionPayout->amount);
            } catch (\Throwable $e) {
                // 非致命
            }

            $commissionPayout->update([
                'status' => 'cancelled',
                'notes' => ($commissionPayout->notes ?? '') . ' | 审批拒绝: ' . ($notes ?? ''),
            ]);
            $message = '提现已拒绝，余额已退还';

            // ⭐ M2-128 发送拒绝通知
            try {
                $agent = $commissionPayout->agent;
                if ($agent) {
                    $this->earningsNotifier->notifyPayoutStatusChanged(
                        agent: $agent,
                        payout: $commissionPayout->fresh(),
                        oldStatus: 'pending_review',
                        newStatus: 'rejected',
                    );
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('拒绝通知发送失败', ['payout_id' => $commissionPayout->id]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $commissionPayout->fresh(),
        ]);
    }

    /**
     * 执行风控任务（手动触发）
     */
    public function runRiskTasks(Request $request): JsonResponse
    {
        $validator = validator($request->all(), [
            'task' => 'required|in:release_freezes,enforce_recovery',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $task = $request->input('task');
        $result = [];

        if ($task === 'release_freezes') {
            $count = $this->riskGuard->releaseExpiredFreezes();
            $result = ['released_count' => $count];
        } elseif ($task === 'enforce_recovery') {
            $result = $this->riskGuard->enforceNegativeBalanceRecovery();
        }

        return response()->json([
            'success' => true,
            'message' => '风控任务已执行',
            'data' => $result,
        ]);
    }
}
