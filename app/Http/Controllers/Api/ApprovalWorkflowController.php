<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\LicenseChangeApproval;
use App\Services\ApprovalWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ApprovalWorkflowController extends Controller
{
    public function __construct(protected ApprovalWorkflowService $approvalService) {}

    /**
     * 审批列表
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->approvalService->getApprovals($request->user()->tenant_id, $request->all())
        );
    }

    /**
     * 仪表盘统计
     */
    public function dashboard(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->approvalService->getDashboard($request->user()->tenant_id)
        );
    }

    /**
     * 创建审批请求
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'license_id' => 'required|integer|exists:licenses,id',
            'action'     => 'required|string|in:upgrade,downgrade,transfer,seat_change,type_change,early_renewal',
            'request_data' => 'required|array',
            'reason'     => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('参数验证失败', $validator->errors()->toArray());
        }

        $license = License::findOrFail($request->input('license_id'));

        $approval = $this->approvalService->createRequest(
            $license,
            $request->input('action'),
            $request->input('request_data'),
            $request->user(),
            $request->input('reason'),
        );

        return ApiResponse::created($approval, '审批请求已提交');
    }

    /**
     * 审批通过
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $approval = LicenseChangeApproval::findOrFail($id);
        $success = $this->approvalService->approve($approval, $request->user());

        if (!$success) {
            return ApiResponse::error('审批失败：该请求已处理或已过期', 400);
        }

        return ApiResponse::success($approval->fresh(), '已批准');
    }

    /**
     * 拒绝
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('请填写拒绝原因', $validator->errors()->toArray());
        }

        $approval = LicenseChangeApproval::findOrFail($id);
        $success = $this->approvalService->reject($approval, $request->user(), $request->input('reason'));

        if (!$success) {
            return ApiResponse::error('拒绝失败：该请求已处理或已过期', 400);
        }

        return ApiResponse::success(null, '已拒绝');
    }

    /**
     * 取消
     */
    public function cancel(int $id): JsonResponse
    {
        $approval = LicenseChangeApproval::findOrFail($id);
        // 使用 request() 获取当前用户
        $success = $this->approvalService->cancel($approval, request()->user());

        if (!$success) {
            return ApiResponse::error('取消失败：仅申请人或管理员可取消', 400);
        }

        return ApiResponse::success(null, '已取消');
    }

    /**
     * 查看详情
     */
    public function show(int $id): JsonResponse
    {
        $approval = LicenseChangeApproval::with(['license', 'requester', 'approver'])->findOrFail($id);
        return ApiResponse::success($approval);
    }

    /**
     * 检查某操作是否需要审批
     */
    public function check(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|string|in:upgrade,downgrade,transfer,seat_change,type_change,early_renewal',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('参数验证失败', $validator->errors()->toArray());
        }

        return ApiResponse::success([
            'requires_approval' => $this->approvalService->requiresApproval($request->input('action')),
        ]);
    }
}
