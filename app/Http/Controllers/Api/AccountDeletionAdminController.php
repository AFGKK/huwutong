<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AccountDeletionRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 账号注销管理（管理员端）
 *
 * 管理员可以：
 * - 查看所有待处理的注销申请
 * - 查看注销历史记录
 * - 审核通过/拒绝注销申请
 * - 手动处理过冷静期的注销
 */
class AccountDeletionAdminController extends Controller
{
    public function __construct(
        protected AuthService $authService,
    ) {}

    /**
     * 获取待处理的注销申请列表
     */
    public function pending(Request $request): JsonResponse
    {
        $query = AccountDeletionRequest::where('status', 'pending')
            ->with('user')
            ->latest();

        // 可按冷静期是否已过来筛选
        if ($request->boolean('cooling_over')) {
            $query->where('cooling_until', '<=', now());
        }

        $requests = $query->paginate($request->input('per_page', 20));

        return ApiResponse::success($requests);
    }

    /**
     * 获取所有注销记录
     */
    public function history(Request $request): JsonResponse
    {
        $query = AccountDeletionRequest::with('user')
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->latest();

        $requests = $query->paginate($request->input('per_page', 20));

        return ApiResponse::success($requests);
    }

    /**
     * 审核通过注销申请（管理员手动执行注销）
     */
    public function approve(Request $request): JsonResponse
    {
        $request->validate([
            'id' => 'required|integer|exists:account_deletion_requests,id',
            'admin_notes' => 'nullable|string|max:500',
        ]);

        $deletionRequest = AccountDeletionRequest::with('user')
            ->findOrFail($request->input('id'));

        if ($deletionRequest->status !== 'pending') {
            return ApiResponse::error('REQUEST_PROCESSED', '此申请已被处理', 422);
        }

        if (!$deletionRequest->isCoolingOver()) {
            return ApiResponse::error('COOLING_NOT_OVER', '冷静期尚未结束，无法执行注销', 422);
        }

        $deletionRequest->update([
            'admin_notes' => $request->input('admin_notes'),
        ]);

        $executed = $this->authService->executeDeletion($deletionRequest);

        if (!$executed) {
            return ApiResponse::error('EXECUTION_FAILED', '注销执行失败', 500);
        }

        // 审计日志
        app(\App\Services\AuditService::class)->log(
            action: 'account_deletion_approved',
            description: "管理员 {$request->user()->name} 审核通过了用户 {$deletionRequest->user->name} 的账号注销申请",
            userId: $request->user()->id,
            payload: ['deletion_request_id' => $deletionRequest->id, 'user_id' => $deletionRequest->user_id],
        );

        return ApiResponse::success(null, '账号已注销');
    }

    /**
     * 拒绝注销申请
     */
    public function reject(Request $request): JsonResponse
    {
        $request->validate([
            'id' => 'required|integer|exists:account_deletion_requests,id',
            'admin_notes' => 'nullable|string|max:500',
        ]);

        $deletionRequest = AccountDeletionRequest::with('user')
            ->findOrFail($request->input('id'));

        if ($deletionRequest->status !== 'pending') {
            return ApiResponse::error('REQUEST_PROCESSED', '此申请已被处理', 422);
        }

        $deletionRequest->update([
            'status' => 'rejected',
            'admin_notes' => $request->input('admin_notes'),
            'processed_at' => now(),
        ]);

        // 审计日志
        app(\App\Services\AuditService::class)->log(
            action: 'account_deletion_rejected',
            description: "管理员 {$request->user()->name} 拒绝了用户 {$deletionRequest->user->name} 的账号注销申请",
            userId: $request->user()->id,
            payload: ['deletion_request_id' => $deletionRequest->id, 'user_id' => $deletionRequest->user_id],
        );

        return ApiResponse::success(null, '注销申请已拒绝');
    }

    /**
     * 获取注销统计概览
     */
    public function stats(): JsonResponse
    {
        return ApiResponse::success([
            'pending' => AccountDeletionRequest::where('status', 'pending')->count(),
            'pending_cooling_over' => AccountDeletionRequest::where('status', 'pending')
                ->where('cooling_until', '<=', now())->count(),
            'completed' => AccountDeletionRequest::where('status', 'completed')->count(),
            'rejected' => AccountDeletionRequest::where('status', 'rejected')->count(),
            'cancelled' => AccountDeletionRequest::where('status', 'cancelled')->count(),
        ]);
    }
}
