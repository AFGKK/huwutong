<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\GdprDataRequest;
use App\Models\User;
use App\Services\AccountDeletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * 数据匿名化与账号注销控制器 (M3-62)
 *
 * 提供：
 * - 用户自助账号注销及数据匿名化
 * - 管理员手动数据匿名化
 * - 数据匿名化状态和记录查询
 */
class DeletionController extends Controller
{
    public function __construct(
        protected AccountDeletionService $deletionService,
    ) {}

    /**
     * 检查当前用户是否可注销
     */
    public function checkDeletability(): JsonResponse
    {
        $user = Auth::user();
        $result = $this->deletionService->checkDeletability($user);

        return ApiResponse::success($result);
    }

    /**
     * 用户提交账号注销请求
     */
    public function requestDeletion(Request $request): JsonResponse
    {
        $validator = validator($request->all(), [
            'reason' => 'required|string|in:' . implode(',', array_keys(AccountDeletionService::CANCELLATION_REASONS)),
            'reason_detail' => 'nullable|string|max:1000',
            'confirm' => 'required|accepted',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__("app.deletion.msg_e441b11e"), $validator->errors()->toArray());
        }

        $user = Auth::user();

        $deletable = $this->deletionService->checkDeletability($user);
        if (! $deletable['can_delete']) {
            return ApiResponse::error(
                __('app.deletion.cannot_delete_now'),
                409,
                ['reasons' => $deletable['reasons']]
            );
        }

        try {
            $result = $this->deletionService->deleteAccount(
                $user,
                $request->input('reason'),
                $request->input('reason_detail')
            );

            return ApiResponse::success($result, __("app.deletion.msg_a34ad1c9"));
        } catch (\Throwable $e) {
            return ApiResponse::error(__("app.deletion.msg_67631aa4") . $e->getMessage(), 500);
        }
    }

    /**
     * 管理员手动匿名化指定用户
     */
    public function adminAnonymize(Request $request): JsonResponse
    {
        $validator = validator($request->all(), [
            'user_id' => 'required|exists:users,id',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.common.validation_failed'), $validator->errors()->toArray());
        }

        $targetUser = User::findOrFail($request->input('user_id'));
        $admin = Auth::user();

        try {
            $result = $this->deletionService->adminAnonymizeUser(
                $targetUser,
                $admin->id,
                $request->input('notes')
            );

            return ApiResponse::success($result, __("app.deletion.msg_63327be7"));
        } catch (\Throwable $e) {
            return ApiResponse::error(__("app.deletion.msg_efda8370") . $e->getMessage(), 500);
        }
    }

    /**
     * 获取所有数据匿名化（GDPR删除类型）请求记录
     */
    public function deletionRecords(Request $request): JsonResponse
    {
        $query = GdprDataRequest::where('type', GdprDataRequest::TYPE_ERASURE)
            ->with(['user:id,name,email', 'processor:id,name,email'])
            ->orderByDesc('created_at');

        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%"));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage = min((int) ($request->input('per_page') ?? 20), 100);
        return ApiResponse::success($query->paginate($perPage));
    }

    /**
     * 获取注销原因选项
     */
    public function cancellationReasons(): JsonResponse
    {
        $reasons = [];
        foreach (AccountDeletionService::CANCELLATION_REASONS as $key => $label) {
            $reasons[] = ['value' => $key, 'label' => $label];
        }

        return ApiResponse::success($reasons);
    }

    /**
     * 数据匿名化状态统计
     */
    public function stats(): JsonResponse
    {
        $totalDeletions = GdprDataRequest::where('type', GdprDataRequest::TYPE_ERASURE)->count();
        $completedDeletions = GdprDataRequest::where('type', GdprDataRequest::TYPE_ERASURE)
            ->where('status', GdprDataRequest::STATUS_COMPLETED)->count();
        $recentDeletions = GdprDataRequest::where('type', GdprDataRequest::TYPE_ERASURE)
            ->where('created_at', '>=', now()->subDays(30))->count();

        $reasonsBreakdown = GdprDataRequest::where('type', GdprDataRequest::TYPE_ERASURE)
            ->whereNotNull('reason')
            ->selectRaw('reason, count(*) as total')
            ->groupBy('reason')
            ->pluck('total', 'reason')
            ->toArray();

        return ApiResponse::success([
            'total_deletions' => $totalDeletions,
            'completed_deletions' => $completedDeletions,
            'recent_30_days' => $recentDeletions,
            'reasons_breakdown' => $reasonsBreakdown,
        ]);
    }
}
