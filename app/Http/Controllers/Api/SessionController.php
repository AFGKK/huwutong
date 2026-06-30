<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\UserSession;
use App\Services\SessionManagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Session 管理 (M1.4-30)
 *
 * 管理后台用户活跃会话管理 API
 */
class SessionController extends Controller
{
    public function __construct(
        protected SessionManagerService $sessionService
    ) {}

    /**
     * 仪表盘
     * GET /api/v1/admin/sessions/dashboard
     */
    public function dashboard(): JsonResponse
    {
        return ApiResponse::success($this->sessionService->getDashboard(), '会话仪表盘获取成功');
    }

    /**
     * 会话列表
     * GET /api/v1/admin/sessions
     */
    public function index(Request $request): JsonResponse
    {
        $params = $request->only([
            'user_id', 'search', 'is_current', 'device_type',
            'date_from', 'date_to', 'per_page', 'page',
        ]);
        return ApiResponse::success($this->sessionService->getSessions($params), '会话列表获取成功');
    }

    /**
     * 会话详情
     * GET /api/v1/admin/sessions/{user_session}
     */
    public function show(UserSession $userSession): JsonResponse
    {
        $userSession->load('user');
        return ApiResponse::success($userSession, '会话详情获取成功');
    }

    /**
     * 踢出会话
     * POST /api/v1/admin/sessions/{user_session}/terminate
     */
    public function terminate(Request $request, UserSession $userSession): JsonResponse
    {
        $result = $this->sessionService->terminateSession(
            $userSession->id,
            $request->user()->id
        );

        if (!$result['success']) {
            return ApiResponse::success(null, $result['message'], false, 422);
        }

        return ApiResponse::success(null, $result['message']);
    }

    /**
     * 批量踢出
     * POST /api/v1/admin/sessions/batch-terminate
     */
    public function batchTerminate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:user_sessions,id',
        ]);

        $result = $this->sessionService->batchTerminate(
            $validated['ids'],
            $request->user()->id
        );

        return ApiResponse::success($result, "成功踢出 {$result['success']} 个会话" . ($result['failed'] > 0 ? "，{$result['failed']} 个失败" : ''));
    }

    /**
     * 踢出用户所有会话
     * POST /api/v1/admin/sessions/terminate-user/{user}
     */
    public function terminateUser(Request $request, int $userId): JsonResponse
    {
        $result = $this->sessionService->terminateUserSessions(
            $userId,
            $request->user()->id
        );

        return ApiResponse::success(null, $result['message']);
    }
}
