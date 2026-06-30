<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\SettlementBatch;
use App\Models\SettlementCycle;
use App\Services\SettlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettlementController extends Controller
{
    protected SettlementService $settlementService;

    public function __construct(SettlementService $settlementService)
    {
        $this->settlementService = $settlementService;
    }

    // ══════════════════════════════════════════
    //  结算仪表盘
    // ══════════════════════════════════════════

    public function dashboard(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        return ApiResponse::success(
            $this->settlementService->getDashboard($tenantId)
        );
    }

    // ══════════════════════════════════════════
    //  结算周期管理
    // ══════════════════════════════════════════

    public function cycles(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $perPage = min((int) $request->input('per_page', 20), 100);

        $cycles = $this->settlementService->getCycles($tenantId, $request->only([
            'status', 'date_from', 'date_to',
        ]), $perPage);

        return ApiResponse::paginated($cycles);
    }

    public function cycleStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'period_type' => 'required|in:weekly,bi-weekly,monthly',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'settlement_date' => 'required|date|after_or_equal:period_end',
            'payout_date' => 'nullable|date|after_or_equal:settlement_date',
            'notes' => 'nullable|string|max:1000',
        ]);

        $cycle = $this->settlementService->createCycle([
            'tenant_id' => $request->user()->tenant_id,
            'created_by' => $request->user()->id,
            ...$validated,
        ]);

        return ApiResponse::created($cycle, '结算周期创建成功');
    }

    public function cycleShow(int $id): JsonResponse
    {
        $cycle = SettlementCycle::with('batches.items.settleable', 'settlements')
            ->findOrFail($id);
        return ApiResponse::success($cycle);
    }

    public function cycleGenerate(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $cycle = $this->settlementService->generateMonthlyCycle($tenantId, $request->user()->id);
        return ApiResponse::success($cycle, '结算周期已生成');
    }

    // ══════════════════════════════════════════
    //  可结算佣金扫描
    // ══════════════════════════════════════════

    public function scanReleasable(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        return ApiResponse::success(
            $this->settlementService->scanReleasableCommissions($tenantId)
        );
    }

    // ══════════════════════════════════════════
    //  结算批次管理
    // ══════════════════════════════════════════

    public function batches(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $perPage = min((int) $request->input('per_page', 20), 100);

        $batches = $this->settlementService->getBatches($tenantId, $request->only([
            'status', 'channel', 'search',
        ]), $perPage);

        return ApiResponse::paginated($batches);
    }

    public function batchStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settlement_cycle_id' => 'nullable|exists:settlement_cycles,id',
            'channel' => 'required|in:bank,alipay,wechat,paypal,balance',
            'settlement_ids' => 'required|array|min:1',
            'settlement_ids.*' => 'integer|exists:commission_settlements,id',
            'notes' => 'nullable|string|max:500',
        ]);

        $batch = $this->settlementService->createBatch([
            'tenant_id' => $request->user()->tenant_id,
            'created_by' => $request->user()->id,
            ...$validated,
        ]);

        return ApiResponse::created($batch, '结算批次创建成功');
    }

    public function batchShow(int $id): JsonResponse
    {
        return ApiResponse::success(
            $this->settlementService->getBatchDetail($id)
        );
    }

    public function batchSubmit(int $id): JsonResponse
    {
        $batch = $this->settlementService->submitForApproval($id);
        return ApiResponse::success($batch, '已提交审核');
    }

    public function batchApprove(int $id, Request $request): JsonResponse
    {
        $batch = $this->settlementService->approveBatch($id, $request->user()->id);
        return ApiResponse::success($batch, '批次已审核通过');
    }

    public function batchComplete(int $id): JsonResponse
    {
        $batch = $this->settlementService->completeBatch($id);
        return ApiResponse::success($batch, '批次已完成');
    }

    public function batchCancel(int $id): JsonResponse
    {
        $batch = $this->settlementService->cancelBatch($id);
        return ApiResponse::success($batch, '批次已取消');
    }

    // ══════════════════════════════════════════
    //  平台费用统计
    // ══════════════════════════════════════════

    public function feeStats(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        return ApiResponse::success(
            $this->settlementService->getFeeStats($tenantId, $request->input('year_month'))
        );
    }
}
