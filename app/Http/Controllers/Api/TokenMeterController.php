<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\TokenMeterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AI Token 用量计费追踪 (M2-77)
 */
class TokenMeterController extends Controller
{
    public function __construct(
        protected TokenMeterService $tokenMeterService
    ) {}

    public function dashboard(): JsonResponse
    {
        return ApiResponse::success($this->tokenMeterService->getDashboard(), '获取成功');
    }

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success($this->tokenMeterService->getRecords($request->all()), '获取成功');
    }

    /** 记录消耗（管理端手动录入） */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id' => 'nullable|integer|exists:tenants,id',
            'user_id' => 'nullable|integer|exists:users,id',
            'model' => 'required|string|max:50',
            'feature' => 'nullable|string|max:50',
            'input_tokens' => 'required|integer|min:0',
            'output_tokens' => 'required|integer|min:0',
            'session_id' => 'nullable|string|max:100',
            'request_id' => 'nullable|string|max:100',
            'cached' => 'boolean',
        ]);

        $record = $this->tokenMeterService->record($validated);
        return ApiResponse::success($record, '记录成功');
    }

    public function models(): JsonResponse
    {
        return ApiResponse::success($this->tokenMeterService->getModelPricing(), '获取成功');
    }

    public function features(): JsonResponse
    {
        return ApiResponse::success($this->tokenMeterService->getFeatures(), '获取成功');
    }

    // ─── 预算管理 ───

    public function budgets(Request $request): JsonResponse
    {
        $budgets = $this->tokenMeterService->getBudgets($request->get('tenant_id'));
        return ApiResponse::success($budgets, '获取成功');
    }

    public function upsertBudget(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id' => 'nullable|integer|exists:tenants,id',
            'period' => 'required|string|in:monthly,quarterly,yearly',
            'budget_limit' => 'required|numeric|min:0',
            'alert_threshold_1' => 'nullable|numeric|min:0|max:100',
            'alert_threshold_2' => 'nullable|numeric|min:0|max:100',
            'alert_threshold_3' => 'nullable|numeric|min:0|max:100',
            'hard_cap' => 'boolean',
            'is_active' => 'boolean',
        ]);

        return ApiResponse::success($this->tokenMeterService->upsertBudget($validated), '保存成功');
    }

    // ─── 告警 ───

    public function alerts(): JsonResponse
    {
        return ApiResponse::success($this->tokenMeterService->getAlerts(), '获取成功');
    }

    public function resolveAlert(int $id): JsonResponse
    {
        $this->tokenMeterService->resolveAlert($id);
        return ApiResponse::success(null, '已解决');
    }

    public function checkAlerts(): JsonResponse
    {
        $alerts = $this->tokenMeterService->checkBudgetAlerts(null);
        return ApiResponse::success($alerts, '检查完成');
    }

    // ─── 租户报告 ───

    public function tenantReport(Request $request, int $tenantId): JsonResponse
    {
        return ApiResponse::success(
            $this->tokenMeterService->getTenantReport($tenantId, $request->get('month')),
            '获取成功'
        );
    }

    // ─── 成本分摊 (M2-77) ───

    /** 成本分摊报告 */
    public function costAllocation(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->tokenMeterService->getCostAllocationReport($request->get('month')),
            '获取成功'
        );
    }

    /** 分摊摘要 */
    public function allocationSummary(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->tokenMeterService->getAllocationSummary($request->get('month')),
            '获取成功'
        );
    }

    /** 导出分摊 CSV */
    public function exportAllocation(Request $request): \Illuminate\Http\Response
    {
        $month = $request->get('month');
        $csv = $this->tokenMeterService->exportCostAllocationCsv($month);
        $period = $month ?: now()->format('Y-m');

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"token-cost-allocation-{$period}.csv\"",
        ]);
    }
}
