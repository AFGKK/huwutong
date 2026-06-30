<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\UserAppeal;
use App\Services\AccountAppealService;
use Illuminate\Http\Request;

class AdminAppealController extends Controller
{
    public function __construct(protected AccountAppealService $appealService) {}

    /**
     * 申诉列表
     */
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = UserAppeal::with(['user:id,name,email,phone,status', 'reviewer:id,name']);

        // 按状态筛选
        if ($status = $request->input('status')) {
            $query->status($status);
        }

        // 按用户搜索
        if ($q = $request->input('q')) {
            $query->whereHas('user', function ($qry) use ($q) {
                $qry->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            });
        }

        return ApiResponse::success(
            $query->latest()->paginate(20)
        );
    }

    /**
     * 申诉详情
     */
    public function show(int $id): \Illuminate\Http\JsonResponse
    {
        $appeal = UserAppeal::with(['user', 'reviewer'])->findOrFail($id);
        return ApiResponse::success($appeal);
    }

    /**
     * 申诉统计
     */
    public function stats(): \Illuminate\Http\JsonResponse
    {
        $stats = [
            'total' => UserAppeal::count(),
            'pending' => UserAppeal::whereIn('status', ['pending', 'reviewing'])->count(),
            'approved' => UserAppeal::where('status', 'approved')->count(),
            'rejected' => UserAppeal::where('status', 'rejected')->count(),
            'today' => UserAppeal::whereDate('created_at', today())->count(),
        ];

        // 最近 7 天趋势
        $trend = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $trend[] = [
                'date' => $date->format('Y-m-d'),
                'total' => UserAppeal::whereDate('created_at', $date)->count(),
                'approved' => UserAppeal::whereDate('created_at', $date)->where('status', 'approved')->count(),
                'rejected' => UserAppeal::whereDate('created_at', $date)->where('status', 'rejected')->count(),
            ];
        }

        return ApiResponse::success([
            'stats' => $stats,
            'trend' => $trend,
        ]);
    }

    /**
     * 审核申诉
     */
    public function review(int $id, Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
            'comment' => 'nullable|string|max:2000',
        ]);

        try {
            $appeal = $this->appealService->reviewAppeal(
                $id,
                $request->user()->id,
                $validated['action'],
                $validated['comment'] ?? null,
            );

            $msg = $validated['action'] === 'approve' ? '申诉已通过，账号已恢复' : '申诉已驳回';
            return ApiResponse::success($appeal, $msg);
        } catch (\RuntimeException $e) {
            return ApiResponse::error('REVIEW_FAILED', $e->getMessage(), 422);
        }
    }

    /**
     * 封禁用户
     */
    public function banUser(int $userId, Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $user = $this->appealService->banUser(
                $userId,
                $request->user()->id,
                $validated['reason'],
            );
            return ApiResponse::success($user, '账号已封禁');
        } catch (\RuntimeException $e) {
            return ApiResponse::error('BAN_FAILED', $e->getMessage(), 422);
        }
    }

    /**
     * 解封用户
     */
    public function unbanUser(int $userId, Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $user = $this->appealService->unbanUser(
                $userId,
                $request->user()->id,
            );
            return ApiResponse::success($user, '账号已解封');
        } catch (\RuntimeException $e) {
            return ApiResponse::error('UNBAN_FAILED', $e->getMessage(), 422);
        }
    }
}
