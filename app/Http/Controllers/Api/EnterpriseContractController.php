<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\EnterpriseContract;
use App\Services\EnterpriseContractService;
use Illuminate\Http\Request;

class EnterpriseContractController extends Controller
{
    public function __construct(
        protected EnterpriseContractService $service
    ) {}

    /**
     * 合同仪表盘
     */
    public function dashboard(Request $request)
    {
        return ApiResponse::success(
            $this->service->getDashboard($request->user()->tenant_id)
        );
    }

    /**
     * 即将到期的合同
     */
    public function expiring(Request $request)
    {
        $days = $request->input('within_days', 30);
        return ApiResponse::success(
            $this->service->getExpiringContracts($request->user()->tenant_id, (int) $days)
        );
    }

    /**
     * 合同列表
     */
    public function index(Request $request)
    {
        return ApiResponse::success(
            $this->service->listContracts(
                $request->user()->tenant_id,
                $request->only(['status', 'approval_status', 'search', 'expiring_within_days', 'overdue', 'per_page'])
            )
        );
    }

    /**
     * 合同详情
     */
    public function show(Request $request, int $contractId)
    {
        $contract = EnterpriseContract::with(['customer', 'creator:id,name', 'approver:id,name', 'renewedContract'])
            ->where('id', $contractId)
            ->firstOrFail();

        return ApiResponse::success($contract);
    }

    /**
     * 创建合同
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'customer_id' => 'required|integer|exists:customers,id',
            'total_value' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'discount_rate' => 'nullable|numeric|min:0|max:100',
            'negotiated_amount' => 'nullable|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'billing_cycle_days' => 'nullable|integer|min:1',
            'auto_renew' => 'nullable|boolean',
            'renewal_notice_days' => 'nullable|integer|min:1',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
            'special_terms' => 'nullable|string',
            'licensed_items' => 'nullable|string',
        ]);

        return ApiResponse::success(
            $this->service->createContract(
                $request->user()->tenant_id,
                $request->user()->id,
                $validated
            )
        );
    }

    /**
     * 更新合同
     */
    public function update(Request $request, int $contractId)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:200',
            'total_value' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'discount_rate' => 'nullable|numeric|min:0|max:100',
            'negotiated_amount' => 'nullable|numeric|min:0',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date',
            'billing_cycle_days' => 'nullable|integer|min:1',
            'auto_renew' => 'nullable|boolean',
            'renewal_notice_days' => 'nullable|integer|min:1',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
            'special_terms' => 'nullable|string',
            'licensed_items' => 'nullable|string',
        ]);

        return ApiResponse::success(
            $this->service->updateContract(
                $request->user()->tenant_id,
                $contractId,
                $validated
            )
        );
    }

    /**
     * 删除合同
     */
    public function destroy(Request $request, int $contractId)
    {
        $this->service->deleteContract($request->user()->tenant_id, $contractId);
        return ApiResponse::success(['deleted' => true]);
    }

    /**
     * 提交审批
     */
    public function submitForApproval(Request $request, int $contractId)
    {
        return ApiResponse::success(
            $this->service->submitForApproval($request->user()->tenant_id, $contractId)
        );
    }

    /**
     * 审批合同
     */
    public function approve(Request $request, int $contractId)
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|string|in:approved,rejected',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        return ApiResponse::success(
            $this->service->approveContract(
                $request->user()->tenant_id,
                $contractId,
                $request->user()->id,
                $request->action,
                $request->notes
            )
        );
    }

    /**
     * 终止合同
     */
    public function terminate(Request $request, int $contractId)
    {
        return ApiResponse::success(
            $this->service->terminateContract($request->user()->tenant_id, $contractId)
        );
    }

    /**
     * 续签合同
     */
    public function renew(Request $request, int $contractId)
    {
        return ApiResponse::success(
            $this->service->renewContract(
                $request->user()->tenant_id,
                $contractId,
                $request->only(['terms', 'licensed_items'])
            )
        );
    }
}
