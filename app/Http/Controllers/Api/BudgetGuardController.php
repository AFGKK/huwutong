<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\BudgetLimit;
use App\Models\BudgetOverride;
use App\Services\BudgetGuardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 消费预警+预算上限控制器 (M2-79)
 */
class BudgetGuardController extends Controller
{
    public function __construct(
        protected BudgetGuardService $budgetGuard,
    ) {
    }

    /**
     * 获取预算配置列表
     */
    public function index(Request $request): JsonResponse
    {
        $query = BudgetLimit::with('budgetable')->orderBy('created_at', 'desc');

        if ($request->filled('budgetable_type')) {
            $query->where('budgetable_type', $request->input('budgetable_type'));
        }
        if ($request->filled('budgetable_id')) {
            $query->where('budgetable_id', $request->input('budgetable_id'));
        }
        if ($request->filled('period')) {
            $query->where('period', $request->input('period'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $budgets = $query->paginate($request->input('per_page', 20));
        return ApiResponse::success($budgets);
    }

    /**
     * 获取单个预算详情
     */
    public function show(int $id): JsonResponse
    {
        $budget = BudgetLimit::with(['alerts' => fn($q) => $q->latest()->limit(10), 'overrides' => fn($q) => $q->latest()->limit(10)])
            ->findOrFail($id);

        return ApiResponse::success([
            'budget' => $budget,
            'usage_percentage' => $budget->usagePercentage(),
            'remaining' => $budget->remaining(),
            'is_exceeded' => $budget->isExceeded(),
        ]);
    }

    /**
     * 保存预算配置
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'budgetable_type' => 'required|string|in:customer,tenant',
            'budgetable_id' => 'required|integer',
            'period' => 'required|string|in:monthly,quarterly,yearly',
            'budget_amount' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'status' => 'nullable|string|in:active,paused',
            'notifications_enabled' => 'boolean',
            'notes' => 'nullable|string|max:500',
            'period_start_at' => 'nullable|date',
            'period_end_at' => 'nullable|date|after:period_start_at',
        ]);

        $userId = $request->user()?->id;
        $budget = $this->budgetGuard->saveBudget(
            $data['budgetable_type'],
            $data['budgetable_id'],
            $data,
            $userId
        );

        return ApiResponse::success(['budget' => $budget->fresh()], '预算已保存');
    }

    /**
     * 更新预算
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $budget = BudgetLimit::findOrFail($id);

        $data = $request->validate([
            'budget_amount' => 'numeric|min:0',
            'currency' => 'string|size:3',
            'period' => 'string|in:monthly,quarterly,yearly',
            'status' => 'string|in:active,paused,expired',
            'notifications_enabled' => 'boolean',
            'notes' => 'nullable|string|max:500',
            'period_start_at' => 'nullable|date',
            'period_end_at' => 'nullable|date|after:period_start_at',
        ]);

        $budget->update($data);
        return ApiResponse::success(['budget' => $budget->fresh()], '预算已更新');
    }

    /**
     * 删除预算
     */
    public function destroy(int $id): JsonResponse
    {
        $this->budgetGuard->deleteBudget($id);
        return ApiResponse::success(null, '预算已删除');
    }

    /**
     * 仪表盘
     */
    public function dashboard(Request $request): JsonResponse
    {
        $data = $request->validate([
            'budgetable_type' => 'required|string|in:customer,tenant',
            'budgetable_id' => 'required|integer',
        ]);

        return ApiResponse::success(
            $this->budgetGuard->getDashboard($data['budgetable_type'], $data['budgetable_id'])
        );
    }

    /**
     * 检查消费
     */
    public function checkSpend(Request $request): JsonResponse
    {
        $data = $request->validate([
            'budgetable_type' => 'required|string|in:customer,tenant',
            'budgetable_id' => 'required|integer',
            'amount' => 'required|numeric|min:0',
        ]);

        $result = $this->budgetGuard->checkSpend($data['budgetable_type'], $data['budgetable_id'], $data['amount']);

        return $result['allowed']
            ? ApiResponse::success($result, $result['reason'] ?? '消费允许')
            : ApiResponse::error($result['reason'] ?? '预算不足', 402, $result);
    }

    /**
     * 请求审批
     */
    public function requestOverride(Request $request): JsonResponse
    {
        $data = $request->validate([
            'budget_id' => 'required|integer|exists:budget_limits,id',
            'requested_amount' => 'required|numeric|min:0',
            'reason' => 'required|string|max:500',
        ]);

        $userId = $request->user()?->id;
        $override = $this->budgetGuard->requestOverride(
            $data['budget_id'],
            $data['requested_amount'],
            $data['reason'],
            $userId
        );

        return ApiResponse::success(['override' => $override], '审批请求已提交');
    }

    /**
     * 审批通过
     */
    public function approveOverride(Request $request, int $overrideId): JsonResponse
    {
        $override = $this->budgetGuard->approveOverride($overrideId, $request->user()?->id);
        return ApiResponse::success(['override' => $override], '审批已通过');
    }

    /**
     * 拒绝审批
     */
    public function rejectOverride(Request $request, int $overrideId): JsonResponse
    {
        $override = $this->budgetGuard->rejectOverride($overrideId, $request->user()?->id);
        return ApiResponse::success(['override' => $override], '已拒绝');
    }

    /**
     * 待审批列表
     */
    public function pendingOverrides(): JsonResponse
    {
        return ApiResponse::success([
            'overrides' => $this->budgetGuard->getPendingOverviews(),
        ]);
    }

    /**
     * 预警历史
     */
    public function alertHistory(int $budgetId): JsonResponse
    {
        return ApiResponse::success([
            'alerts' => $this->budgetGuard->getAlertHistory($budgetId),
        ]);
    }
}
