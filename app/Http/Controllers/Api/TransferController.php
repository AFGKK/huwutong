<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\LicenseTransferRequest;
use App\Services\TransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransferController extends Controller
{
    public function __construct(
        protected TransferService $service,
    ) {}

    // ═══════════════ 管理端 ═══════════════

    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $data = $this->service->listRequestsByTenant(
            $tenantId,
            $request->only(['status', 'type', 'search']),
            $request->input('per_page', 20)
        );
        return ApiResponse::success($data);
    }

    public function show(LicenseTransferRequest $transfer): JsonResponse
    {
        $transfer->load(['license', 'requester:id,name', 'approver:id,name', 'targetCustomer:id,name', 'targetUser:id,name', 'targetDevice:id,name']);
        return ApiResponse::success($transfer);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:device_transfer,customer_transfer,user_transfer',
            'license_id' => 'required|integer|exists:licenses,id',
            'target_customer_id' => 'required_if:type,customer_transfer|nullable|integer|exists:customers,id',
            'target_user_id' => 'required_if:type,user_transfer|nullable|integer|exists:users,id',
            'target_device_fingerprint' => 'required_if:type,device_transfer|nullable|string|max:64',
            'target_device_name' => 'nullable|string|max:255',
            'target_device_id' => 'nullable|integer|exists:devices,id',
            'reason' => 'nullable|string|max:1000',
        ]);

        try {
            $transfer = $this->service->createRequest($validated);
            return ApiResponse::success($transfer, __("app.transfer.msg_04568f83"), 201);
        } catch (\RuntimeException $e) {
            return ApiResponse::error('TRANSFER_FAILED', $e->getMessage(), 400);
        }
    }

    public function approve(Request $request, LicenseTransferRequest $transfer): JsonResponse
    {
        $validated = $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $transfer = $this->service->approveRequest($transfer, $validated['notes'] ?? null);
            return ApiResponse::success($transfer, __("app.transfer.msg_bb7d1f22"));
        } catch (\RuntimeException $e) {
            return ApiResponse::error('APPROVE_FAILED', $e->getMessage(), 400);
        }
    }

    public function reject(Request $request, LicenseTransferRequest $transfer): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        try {
            $transfer = $this->service->rejectRequest($transfer, $validated['reason']);
            return ApiResponse::success($transfer, __("app.transfer.msg_8b87d4e2"));
        } catch (\RuntimeException $e) {
            return ApiResponse::error('REJECT_FAILED', $e->getMessage(), 400);
        }
    }

    public function cancel(LicenseTransferRequest $transfer): JsonResponse
    {
        try {
            $transfer = $this->service->cancelRequest($transfer);
            return ApiResponse::success($transfer, __("app.transfer.msg_89dea46a"));
        } catch (\RuntimeException $e) {
            return ApiResponse::error('CANCEL_FAILED', $e->getMessage(), 400);
        }
    }

    public function stats(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        return ApiResponse::success($this->service->getStatsByTenant($tenantId));
    }

    /**
     * 生成设备转移验证码
     */
    public function generateCode(Request $request, LicenseTransferRequest $transfer): JsonResponse
    {
        try {
            $code = $this->service->generateVerificationCode($transfer);
            return ApiResponse::success(['code' => $code], __("app.transfer.msg_b7a248eb"));
        } catch (\RuntimeException $e) {
            return ApiResponse::error('GENERATE_FAILED', $e->getMessage(), 400);
        }
    }

    /**
     * 验证设备转移验证码
     */
    public function verifyCode(Request $request, LicenseTransferRequest $transfer): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $verified = $this->service->verifyCode($transfer, $validated['code']);
        if ($verified) {
            return ApiResponse::success(['verified' => true], __("app.transfer.msg_d1ca6194"));
        }
        return ApiResponse::error('VERIFY_FAILED', __("app.transfer.msg_a2ba8390"), 400);
    }

    // ═══════════════ 客户门户 ═══════════════

    public function myRequests(Request $request): JsonResponse
    {
        $data = $this->service->myRequests(
            $request->user(),
            $request->only(['status']),
            $request->input('per_page', 20)
        );
        return ApiResponse::success($data);
    }

    public function myShow(LicenseTransferRequest $transfer): JsonResponse
    {
        if ($transfer->requested_by !== auth()->id()) {
            return ApiResponse::error('FORBIDDEN', __("app.transfer.msg_e914bec4"), 403);
        }
        $transfer->load(['license', 'targetCustomer:id,name', 'targetDevice:id,name']);
        return ApiResponse::success($transfer);
    }

    public function transferableLicenses(Request $request): JsonResponse
    {
        return ApiResponse::success($this->service->getTransferableLicenses($request->user()));
    }
}
