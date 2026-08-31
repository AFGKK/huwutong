<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\OwnershipTransferRequest;
use App\Services\OwnershipTransferService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OwnershipTransferController extends Controller
{
    public function __construct(
        protected OwnershipTransferService $ownershipTransferService,
    ) {}

    // ─── 管理端 ───

    public function index(Request $request)
    {
        return ApiResponse::success(
            $this->ownershipTransferService->listRequests(
                $request->user()->tenant_id,
                $request->only(['status', 'transferable_type', 'search', 'per_page']),
            )
        );
    }

    public function show(int $id)
    {
        return ApiResponse::success($this->ownershipTransferService->getRequestDetail($id));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'transferable_type' => 'required|string|in:license,product',
            'transferable_id' => 'required|integer',
            'target_customer_id' => 'required|integer|exists:customers,id',
            'transfer_fee' => 'nullable|numeric|min:0',
            'source_notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $data = $request->all();
        $data['tenant_id'] = $request->user()->tenant_id;

        try {
            $result = $this->ownershipTransferService->createRequest($data);
            return ApiResponse::success($result, 201);
        } catch (\RuntimeException $e) {
            return ApiResponse::error('TRANSFER_ERROR', $e->getMessage(), 422);
        }
    }

    public function stats(Request $request)
    {
        return ApiResponse::success(
            $this->ownershipTransferService->getStats($request->user()->tenant_id)
        );
    }

    public function getTransferables(Request $request, string $type)
    {
        if (!in_array($type, ['license', 'product'])) {
            return ApiResponse::error('INVALID_TYPE', __("app.ownership_transfer.unsupported_transfer_type"), 422);
        }

        return ApiResponse::success(
            $this->ownershipTransferService->getTransferables(
                $type,
                $request->user()->tenant_id,
                $request->input('search')
            )
        );
    }

    public function searchCustomers(Request $request)
    {
        $request->validate(['search' => 'required|string|min:1']);

        return ApiResponse::success(
            $this->ownershipTransferService->searchCustomers(
                $request->user()->tenant_id,
                $request->input('search')
            )
        );
    }

    // ─── 审批流 ───

    public function confirmBySource(OwnershipTransferRequest $ownershipTransferRequest)
    {
        try {
            return ApiResponse::success($this->ownershipTransferService->confirmBySource($ownershipTransferRequest));
        } catch (\RuntimeException $e) {
            return ApiResponse::error('CONFIRM_ERROR', $e->getMessage(), 422);
        }
    }

    public function confirmByTarget(OwnershipTransferRequest $ownershipTransferRequest)
    {
        try {
            return ApiResponse::success($this->ownershipTransferService->confirmByTarget($ownershipTransferRequest));
        } catch (\RuntimeException $e) {
            return ApiResponse::error('CONFIRM_ERROR', $e->getMessage(), 422);
        }
    }

    public function approve(OwnershipTransferRequest $ownershipTransferRequest, Request $request)
    {
        try {
            return ApiResponse::success(
                $this->ownershipTransferService->approveAndExecute(
                    $ownershipTransferRequest,
                    $request->input('notes')
                )
            );
        } catch (\RuntimeException $e) {
            return ApiResponse::error('APPROVE_ERROR', $e->getMessage(), 422);
        }
    }

    public function reject(OwnershipTransferRequest $ownershipTransferRequest, Request $request)
    {
        try {
            return ApiResponse::success(
                $this->ownershipTransferService->reject(
                    $ownershipTransferRequest,
                    $request->input('reason')
                )
            );
        } catch (\RuntimeException $e) {
            return ApiResponse::error('REJECT_ERROR', $e->getMessage(), 422);
        }
    }

    public function cancel(OwnershipTransferRequest $ownershipTransferRequest)
    {
        try {
            return ApiResponse::success($this->ownershipTransferService->cancel($ownershipTransferRequest));
        } catch (\RuntimeException $e) {
            return ApiResponse::error('CANCEL_ERROR', $e->getMessage(), 422);
        }
    }
}
