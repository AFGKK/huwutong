<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PayoutBatch;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\WithdrawalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/**
 * 提现管理 API
 *
 * M3-72 多渠道提现管理
 * - 用户端：发起提现、查看记录、取消提现
 * - 管理员：审核、打款、批量处理、凭证上传、统计看板
 */
class WithdrawalController extends Controller
{
    public function __construct(
        protected WithdrawalService $withdrawalService,
    ) {}

    protected function ensureAdmin(): void
    {
        if (Gate::denies('admin')) {
            abort(403, __('app.api.withdrawal.admin_required'));
        }
    }

    protected function isAdmin(): bool
    {
        return Gate::allows('admin');
    }

    // ──────────────── 管理端 API ────────────────

    /**
     * 提现列表（管理端）
     */
    public function index(Request $request): JsonResponse
    {
        $this->ensureAdmin();

        $query = Withdrawal::with([
            'earningsAccount.user:id,name,email',
            'reviewer:id,name',
        ])->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('channel')) {
            $query->where('channel', $request->input('channel'));
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }
        if ($request->filled('batch_no')) {
            $query->where('batch_no', $request->input('batch_no'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate($request->input('per_page', 20)),
        ]);
    }

    /**
     * 提现详情
     */
    public function show(Withdrawal $withdrawal): JsonResponse
    {
        if (!$this->isAdmin() && $withdrawal->user_id !== auth()->id()) {
            abort(403);
        }

        $withdrawal->load([
            'earningsAccount.user:id,name,email',
            'reviewer:id,name',
            'payoutBatch',
        ]);

        return response()->json([
            'success' => true,
            'data' => $withdrawal,
        ]);
    }

    /**
     * 审核提现
     */
    public function review(Request $request, Withdrawal $withdrawal): JsonResponse
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
            'remark' => 'nullable|string|max:500',
        ]);

        try {
            $result = $this->withdrawalService->reviewWithdrawal(
                $withdrawal,
                $request->user(),
                $validated['action'],
                $validated['remark'] ?? null,
            );

            $msg = $validated['action'] === 'approve' ? __('app.api.withdrawal.approved') : __('app.api.withdrawal.rejected');
            return response()->json(['success' => true, 'message' => $msg, 'data' => $result]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * 标记打款完成
     */
    public function markCompleted(Request $request, Withdrawal $withdrawal): JsonResponse
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'transaction_id' => 'nullable|string|max:100',
            'proof' => 'nullable|image|max:5120', // 5MB
        ]);

        try {
            $data = ['transaction_id' => $validated['transaction_id'] ?? null];

            if ($request->hasFile('proof')) {
                $data['proof'] = $request->file('proof');
            }

            $result = $this->withdrawalService->markAsCompleted($withdrawal, $data);
            return response()->json(['success' => true, 'message' => __('app.api.withdrawal.paid'), 'data' => $result]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * 标记打款失败
     */
    public function markFailed(Request $request, Withdrawal $withdrawal): JsonResponse
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'failure_reason' => 'required|string|max:500',
        ]);

        try {
            $result = $this->withdrawalService->markAsFailed($withdrawal, $validated['failure_reason']);
            return response()->json(['success' => true, 'message' => __('app.api.withdrawal.marked_failed'), 'data' => $result]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * 上传打款凭证
     */
    public function uploadProof(Request $request, Withdrawal $withdrawal): JsonResponse
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'proof' => 'required|image|max:5120',
        ]);

        $result = $this->withdrawalService->uploadProof($withdrawal, $validated['proof']);

        return response()->json([
            'success' => true,
            'message' => __('app.api.withdrawal.proof_uploaded'),
            'data' => ['proof' => $result->proof],
        ]);
    }

    // ──────────────── 批次管理 ────────────────

    /**
     * 打款批次列表
     */
    public function batches(Request $request): JsonResponse
    {
        $this->ensureAdmin();

        $query = PayoutBatch::with('creator:id,name')
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('channel')) {
            $query->where('channel', $request->input('channel'));
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate($request->input('per_page', 20)),
        ]);
    }

    /**
     * 批次详情
     */
    public function showBatch(PayoutBatch $payoutBatch): JsonResponse
    {
        $this->ensureAdmin();

        $payoutBatch->load(['creator:id,name', 'withdrawals.earningsAccount.user:id,name,email']);

        return response()->json([
            'success' => true,
            'data' => $payoutBatch,
        ]);
    }

    /**
     * 创建打款批次
     */
    public function createBatch(Request $request): JsonResponse
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'channel' => 'required|in:bank,alipay,wechat,paypal',
            'title' => 'nullable|string|max:200',
            'withdrawal_ids' => 'nullable|array',
            'withdrawal_ids.*' => 'integer|exists:withdrawals,id',
        ]);

        try {
            $batch = $this->withdrawalService->createPayoutBatch(
                $validated['channel'],
                $validated['title'] ?? null,
                $validated['withdrawal_ids'] ?? null,
            );

            return response()->json([
                'success' => true,
                'message' => __('app.api.withdrawal.batch_created'),
                'data' => $batch,
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * 完成打款批次
     */
    public function completeBatch(Request $request, PayoutBatch $payoutBatch): JsonResponse
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'failed_ids' => 'nullable|array',
            'failed_ids.*' => 'integer|exists:withdrawals,id',
        ]);

        try {
            $batch = $this->withdrawalService->completePayoutBatch(
                $payoutBatch,
                $validated['failed_ids'] ?? [],
            );

            return response()->json(['success' => true, 'message' => __('app.api.withdrawal.batch_completed'), 'data' => $batch]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // ──────────────── 用户端 API ────────────────

    /**
     * 用户发起提现
     */
    public function requestWithdrawal(Request $request): JsonResponse
    {
        $user = $request->user();

        $rules = [
            'amount' => 'required|numeric|min:1',
            'channel' => 'required|in:bank,alipay,wechat,paypal',
        ];

        // 根据渠道动态验证
        $channel = $request->input('channel');
        switch ($channel) {
            case 'bank':
                $rules['bank_name'] = 'required|string|max:100';
                $rules['bank_account_name'] = 'required|string|max:100';
                $rules['bank_account_no'] = 'required|string|max:50';
                $rules['bank_branch'] = 'nullable|string|max:200';
                break;
            case 'alipay':
                $rules['alipay_account'] = 'required|string|max:100';
                break;
            case 'wechat':
                $rules['wechat_account'] = 'required|string|max:100';
                break;
            case 'paypal':
                $rules['paypal_email'] = 'required|email|max:200';
                break;
        }

        $validated = $request->validate($rules);

        try {
            $withdrawal = $this->withdrawalService->requestWithdrawal($user, $validated);
            return response()->json([
                'success' => true,
                'message' => ($withdrawal->status === 'pending_review' ? __('app.api.withdrawal.submitted_review') : __('app.api.withdrawal.submitted')),
                'data' => $withdrawal,
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * 用户取消提现
     */
    public function cancelWithdrawal(Request $request, Withdrawal $withdrawal): JsonResponse
    {
        try {
            $result = $this->withdrawalService->cancelWithdrawal($withdrawal, $request->user());
            return response()->json(['success' => true, 'message' => __('app.api.withdrawal.cancelled'), 'data' => $result]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * 用户提现记录
     */
    public function myWithdrawals(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Withdrawal::where('user_id', $user->id)
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate($request->input('per_page', 20)),
        ]);
    }

    /**
     * 用户提现统计
     */
    public function myStats(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => $this->withdrawalService->getUserStats($user),
        ]);
    }

    /**
     * 提现渠道列表
     */
    public function channels(): JsonResponse
    {
        $channels = [];
        foreach (WithdrawalService::CHANNELS as $ch) {
            $limits = WithdrawalService::CHANNEL_LIMITS[$ch];
            $channels[] = [
                'id' => $ch,
                'name' => match ($ch) {
                    'bank' => __('app.api.withdrawal.bank_card'),
                    'alipay' => __('app.api.withdrawal.alipay'),
                    'wechat' => __('app.api.withdrawal.wechat_pay'),
                    'paypal' => 'PayPal',
                },
                'min_amount' => $limits['min'],
                'max_amount' => $limits['max'],
                'fee_rate' => WithdrawalService::FEE_RATES[$ch],
                'daily_limit' => WithdrawalService::DAILY_LIMITS[$ch],
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $channels,
        ]);
    }

    // ──────────────── 统计看板 ────────────────

    /**
     * 提现统计看板
     */
    public function stats(): JsonResponse
    {
        $this->ensureAdmin();

        return response()->json([
            'success' => true,
            'data' => $this->withdrawalService->getStats(),
        ]);
    }

    // ──────────────── M3-72 增强功能 ────────────────

    /**
     * 重试失败提现
     *
     * POST /admin/withdrawals/{withdrawal}/retry
     */
    public function retry(Request $request, Withdrawal $withdrawal): JsonResponse
    {
        $this->ensureAdmin();

        try {
            $result = $this->withdrawalService->retryWithdrawal($withdrawal, $request->user());
            return response()->json(['success' => true, 'message' => __('app.api.withdrawal.reset_pending'), 'data' => $result]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * 批量重试失败提现
     *
     * POST /admin/withdrawals/batch-retry
     */
    public function batchRetry(Request $request): JsonResponse
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:withdrawals,id',
        ]);

        $results = $this->withdrawalService->batchRetryWithdrawals($validated['ids'], $request->user());

        $successCount = count(array_filter($results, fn($r) => $r['success']));

        return response()->json([
            'success' => true,
            'message' => __('app.api.withdrawal.retried', ['success' => $successCount, 'total' => count($results)]),
            'data' => $results,
        ]);
    }

    /**
     * T+30 手动解冻
     *
     * POST /admin/withdrawals/release-pending
     */
    public function releasePending(): JsonResponse
    {
        $this->ensureAdmin();

        $count = $this->withdrawalService->releasePendingBalances();

        return response()->json([
            'success' => true,
            'message' => __('app.api.withdrawal.frozen_processed', ['count' => $count]),
            'data' => ['released_count' => $count],
        ]);
    }

    /**
     * 提现风控检查详情
     *
     * GET /admin/withdrawals/risk-check
     */
    public function riskCheck(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'channel' => 'required|in:bank,alipay,wechat,paypal',
        ]);

        $detail = $this->withdrawalService->getWithdrawalRiskDetail(
            $request->user(),
            $validated['amount'],
            $validated['channel'],
        );

        return response()->json(['success' => true, 'data' => $detail]);
    }
}
