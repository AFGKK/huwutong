<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\Refund;
use App\Models\RefundRiskAssessment;
use App\Models\RefundRiskRule;
use App\Services\CommissionEngineService;
use App\Services\RefundEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class RefundController extends Controller
{
    public function __construct(
        protected CommissionEngineService $commissionEngine,
        protected RefundEngineService $refundEngine,
    ) {}
    /**
     * 退款列表
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Refund::class);

        $query = Refund::with([
            'license:id,license_key',
            'customer:id,name',
            'processor:id,name',
            'invoice:id,invoice_no',
        ]);

        // 租户隔离
        if (!$request->user()->hasRole('super-admin')) {
            $query->where('tenant_id', $request->user()->tenant_id);
        }

        // 搜索
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('refund_no', 'like', "%{$search}%")
                  ->orWhereHas('license', fn($sq) => $sq->where('license_key', 'like', "%{$search}%"))
                  ->orWhereHas('customer', fn($sq) => $sq->where('name', 'like', "%{$search}%"));
            });
        }

        // 筛选
        if ($status = $request->input('filter.status')) {
            $query->where('status', $status);
        }
        if ($dateFrom = $request->input('filter.date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo = $request->input('filter.date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $sortField = $request->input('sort_field', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        if (in_array($sortField, ['id', 'amount', 'status', 'created_at', 'completed_at'])) {
            $query->orderBy($sortField, $sortOrder === 'asc' ? 'asc' : 'desc');
        }

        $perPage = min((int) $request->input('per_page', 20), 100);

        return ApiResponse::paginated($query->paginate($perPage));
    }

    /**
     * 退款详情
     */
    public function show(Refund $refund): JsonResponse
    {
        $this->authorize('view', $refund);

        $refund->load([
            'license:id,license_key,type',
            'customer:id,name,email',
            'processor:id,name',
            'invoice:id,invoice_no,amount',
        ]);

        return ApiResponse::success($refund);
    }

    /**
     * 发起退款（从 License 退款操作）
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Refund::class);

        $validator = Validator::make($request->all(), [
            'license_id' => 'required|integer|exists:licenses,id',
            'invoice_id' => 'nullable|integer|exists:invoices,id',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'nullable|string|size:3',
            'reason' => 'nullable|string|max:500',
            'payment_method' => 'nullable|string|in:original,balance,other',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $data = $validator->validated();
        $license = License::findOrFail($data['license_id']);

        // 租户检查
        if (!$request->user()->hasRole('super-admin')
            && $license->tenant_id !== $request->user()->tenant_id) {
            return ApiResponse::error('FORBIDDEN', __('app.api.license.forbidden_op'), 403);
        }

        $refund = DB::transaction(function () use ($data, $request, $license) {
            // 生成退款单号
            $refundNo = 'RF' . now()->format('YmdHis') . strtoupper(substr(uniqid(), -6));

            /** @var Refund $refund */
            $refund = Refund::create([
                'tenant_id' => $request->user()->tenant_id ?? $license->tenant_id,
                'license_id' => $license->id,
                'invoice_id' => $data['invoice_id'] ?? null,
                'customer_id' => $license->customer_id,
                'processed_by' => $request->user()->id,
                'refund_no' => $refundNo,
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'CNY',
                'reason' => $data['reason'] ?? null,
                'status' => 'completed',
                'payment_method' => $data['payment_method'] ?? 'original',
                'completed_at' => now(),
            ]);

            // 同步更新 License 状态
            $license->status = 'refunded';
            $license->save();

            // ⭐ M2-127b 退款时处理佣金回拨（含风控保障）
            if (!empty($data['invoice_id'])) {
                try {
                    $invoice = \App\Models\Invoice::find($data['invoice_id']);
                    if ($invoice) {
                        $this->commissionEngine->refundSettlement($invoice);
                    }
                } catch (\Throwable $e) {
                    Log::warning('退款佣金回拨失败', [
                        'invoice_id' => $data['invoice_id'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return $refund;
        });

        $refund->load([
            'license:id,license_key',
            'customer:id,name',
            'processor:id,name',
        ]);

        return ApiResponse::created($refund, __('app.api.refund.processed'));
    }

    /**
     * 退款统计
     */
    public function stats(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Refund::class);

        $query = Refund::query();

        if (!$request->user()->hasRole('super-admin')) {
            $query->where('tenant_id', $request->user()->tenant_id);
        }

        $totalRefunds = (clone $query)->count();
        $totalAmount = (clone $query)->where('status', 'completed')->sum('amount');
        $completedCount = (clone $query)->where('status', 'completed')->count();
        $pendingCount = (clone $query)->where('status', 'pending')->count();
        $failedCount = (clone $query)->where('status', 'failed')->count();

        // 本月退款
        $monthAmount = (clone $query)
            ->where('status', 'completed')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');

        // 今日退款
        $todayAmount = (clone $query)
            ->where('status', 'completed')
            ->whereDate('created_at', now()->today())
            ->sum('amount');

        // 按原因分组
        $byReason = (clone $query)
            ->where('status', 'completed')
            ->select('reason', DB::raw('count(*) as count'), DB::raw('sum(amount) as total'))
            ->groupBy('reason')
            ->get()
            ->toArray();

        return ApiResponse::success([
            'total_refunds' => $totalRefunds,
            'total_amount' => $totalAmount,
            'completed_count' => $completedCount,
            'pending_count' => $pendingCount,
            'failed_count' => $failedCount,
            'today_amount' => $todayAmount,
            'month_amount' => $monthAmount,
            'by_reason' => $byReason,
        ]);
    }

    // ═══════════════ 退款风控引擎 (M3-11) ═══════════════

    /**
     * 风控评估
     */
    public function assessRisk(Refund $refund): JsonResponse
    {
        $this->authorize('view', $refund);
        $assessment = $this->refundEngine->assess($refund);
        return ApiResponse::success($assessment->load('reviewer'));
    }

    /**
     * 执行决策
     */
    public function executeDecision(Refund $refund): JsonResponse
    {
        $this->authorize('view', $refund);
        $result = $this->refundEngine->executeDecision($refund);

        if ($result['action'] === 'approved') {
            $refund->refresh();
        }

        return ApiResponse::success([
            'refund' => $refund->fresh()->load(['riskAssessment', 'license', 'customer', 'processor']),
            'decision_result' => $result,
        ]);
    }

    /**
     * 人工审核退款
     */
    public function reviewRefund(Request $request, Refund $refund): JsonResponse
    {
        $this->authorize('update', $refund);

        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
            'note' => 'nullable|string|max:500',
        ]);

        $assessment = $this->refundEngine->review(
            $refund,
            $validated['action'],
            $request->user()->id,
            $validated['note'] ?? null,
        );

        return ApiResponse::success(
            $assessment->load('reviewer'),
            $validated['action'] === 'approve' ? __('app.api.refund.approved') : __('app.api.refund.rejected')
        );
    }

    /**
     * 风控统计
     */
    public function riskStats(): JsonResponse
    {
        return ApiResponse::success($this->refundEngine->getRiskStats());
    }

    /**
     * 退款引擎仪表盘 (M3-11)
     */
    public function refundDashboard(): JsonResponse
    {
        return ApiResponse::success($this->refundEngine->getDashboard());
    }

    /**
     * 推荐部分退款金额
     */
    public function recommendedPartial(Refund $refund): JsonResponse
    {
        return ApiResponse::success([
            'recommended_amount' => $this->refundEngine->getRecommendedPartialAmount($refund),
            'refund_amount' => $refund->amount,
        ]);
    }

    /**
     * 风控规则列表
     */
    public function riskRules(): JsonResponse
    {
        $this->refundEngine->seedDefaultRules();
        $rules = RefundRiskRule::orderBy('priority')->get();
        return ApiResponse::success($rules);
    }

    /**
     * 更新风控规则
     */
    public function updateRiskRule(Request $request, RefundRiskRule $rule): JsonResponse
    {
        $validated = $request->validate([
            'is_active' => 'boolean',
            'priority' => 'nullable|integer|min:1',
            'conditions' => 'nullable|array',
            'actions' => 'nullable|array',
        ]);

        $rule->update($validated);
        return ApiResponse::success($rule, __('app.api.refund.rule_updated'));
    }

    /**
     * 带风控评估的退款创建流程
     */
    public function storeWithRisk(Request $request): JsonResponse
    {
        $this->authorize('create', Refund::class);

        $validated = $request->validate([
            'license_id' => 'required|integer|exists:licenses,id',
            'invoice_id' => 'nullable|integer|exists:invoices,id',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'nullable|string|size:3',
            'reason' => 'nullable|string|max:500',
            'payment_method' => 'nullable|string|in:original,balance,other',
            'refund_type' => 'nullable|in:full,partial',
        ]);

        $license = License::findOrFail($validated['license_id']);

        if (!$request->user()->hasRole('super-admin')
            && $license->tenant_id !== $request->user()->tenant_id) {
            return ApiResponse::error('FORBIDDEN', __('app.api.license.forbidden_op'), 403);
        }

        $refund = DB::transaction(function () use ($validated, $request, $license) {
            $refundNo = 'RF' . now()->format('YmdHis') . strtoupper(substr(uniqid(), -6));

            $refund = Refund::create([
                'tenant_id' => $request->user()->tenant_id ?? $license->tenant_id,
                'license_id' => $license->id,
                'invoice_id' => $validated['invoice_id'] ?? null,
                'customer_id' => $license->customer_id,
                'processed_by' => $request->user()->id,
                'refund_no' => $refundNo,
                'amount' => $validated['amount'],
                'currency' => $validated['currency'] ?? 'CNY',
                'reason' => $validated['reason'] ?? null,
                'refund_type' => $validated['refund_type'] ?? 'full',
                'status' => 'pending',
                'payment_method' => $validated['payment_method'] ?? 'original',
            ]);

            return $refund;
        });

        // 风控评估
        $refund->load(['license:id,license_key', 'customer:id,name', 'invoice:id,invoice_no,amount']);
        $assessment = $this->refundEngine->assess($refund);
        $result = $this->refundEngine->executeDecision($refund);

        return ApiResponse::success([
            'refund' => $refund->fresh()->load(['riskAssessment', 'license', 'customer', 'processor']),
            'risk_assessment' => $assessment->load('reviewer'),
            'decision_result' => $result,
        ], __('app.api.refund.risk_submitted'));
    }
}