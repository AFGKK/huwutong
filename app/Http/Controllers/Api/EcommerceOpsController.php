<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\InventoryService;
use App\Services\PaymentSecurityGuard;
use App\Services\RefundWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 电商运营管理 (M2-150/153/155 🛒)
 */
class EcommerceOpsController extends Controller
{
    public function __construct(
        protected InventoryService $inventory,
        protected PaymentSecurityGuard $securityGuard,
        protected RefundWorkflowService $refundWorkflow,
    ) {}

    // ═══════════════ M2-150 库存管理 ═══════════════

    public function inventorySnapshot(Request $request): JsonResponse
    {
        return ApiResponse::success($this->inventory->getSnapshot($request->input('sku_id')));
    }

    public function inventoryAlerts(Request $request): JsonResponse
    {
        return ApiResponse::success($this->inventory->getAlerts((int) ($request->input('threshold', 10))));
    }

    public function inventoryLogs(Request $request, int $skuId): JsonResponse
    {
        return ApiResponse::success($this->inventory->getLogs($skuId));
    }

    public function inventoryAdjust(Request $request, int $skuId): JsonResponse
    {
        $validated = $request->validate([
            'delta' => 'required|integer',
            'remark' => 'nullable|string|max:500',
        ]);
        $sku = $this->inventory->adjust($skuId, $validated['delta'], $validated['remark'] ?? '', $request->user()->id);
        return ApiResponse::success($sku, '库存已调整');
    }

    public function inventoryInitialize(Request $request, int $skuId): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:0',
            'remark' => 'nullable|string|max:500',
        ]);
        $sku = $this->inventory->initializeStock($skuId, $validated['quantity'], $validated['remark'] ?? '');
        return ApiResponse::success($sku, '库存已初始化');
    }

    // ═══════════════ M2-153 支付安全 ═══════════════

    public function securityStats(): JsonResponse
    {
        return ApiResponse::success($this->securityGuard->getStats());
    }

    public function securityLogs(Request $request): JsonResponse
    {
        return ApiResponse::success($this->securityGuard->getSecurityLogs($request->all()));
    }

    public function prePaymentCheck(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'amount' => 'required|numeric',
            'currency' => 'nullable|string|size:3',
            'ip' => 'nullable|ip',
        ]);

        $result = $this->securityGuard->prePaymentCheck(
            $validated['order_id'],
            $validated['ip'] ?? $request->ip(),
            $validated['amount'],
            $validated['currency'] ?? 'CNY',
        );

        if ($result['passed']) {
            return ApiResponse::success($result, '安全校验通过');
        }
        return ApiResponse::error('SECURITY_CHECK_FAILED', '安全校验未通过', 400, $result['failures']);
    }

    // ═══════════════ M2-155 退款售后 ═══════════════

    public function refundStats(Request $request): JsonResponse
    {
        return ApiResponse::success($this->refundWorkflow->getStats($request->user()->tenant_id));
    }

    public function refundList(Request $request): JsonResponse
    {
        return ApiResponse::success($this->refundWorkflow->getRefunds($request->user()->tenant_id, $request->all()));
    }

    public function requestRefund(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'reason' => 'required|string|max:500',
            'notes' => 'nullable|string|max:2000',
            'attachments' => 'nullable|array',
            'attachments.*' => 'url',
            'refund_type' => 'nullable|string|in:full,partial',
        ]);

        try {
            $refund = $this->refundWorkflow->requestRefund(
                $request->user()->customer?->id ?? $request->user()->id,
                $validated['order_id'],
                $validated
            );
            return ApiResponse::created($refund, '退款申请已提交');
        } catch (\RuntimeException $e) {
            return ApiResponse::error('REFUND_REQUEST_FAILED', $e->getMessage(), 400);
        }
    }

    public function reviewRefund(Request $request, int $refundId): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|string|in:approve,reject',
            'reason' => 'required_if:action,reject|string|max:500',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $validated['operator_id'] = $request->user()->id;
            $validated['operator_name'] = $request->user()->name;
            $refund = $this->refundWorkflow->review($refundId, $validated['action'], $validated);
            $msg = $validated['action'] === 'approve' ? '退款已批准' : '退款已拒绝';
            return ApiResponse::success($refund, $msg);
        } catch (\RuntimeException $e) {
            return ApiResponse::error('REVIEW_FAILED', $e->getMessage(), 400);
        }
    }
}
