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
        return ApiResponse::success($this->sessionService->getDashboard(), __('app.session.dashboard_ok'));
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
        return ApiResponse::success($this->sessionService->getSessions($params), __('app.session.list_ok'));
    }

    /**
     * 会话详情
     * GET /api/v1/admin/sessions/{user_session}
     */
    public function show(UserSession $userSession): JsonResponse
    {
        $userSession->load('user');
        return ApiResponse::success($userSession, __('app.session.detail_ok'));
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

        $msg = __('app.session.kick_result', ['success' => $result['success']]);
        if ($result['failed'] > 0) {
            $msg .= __('app.session.kick_failed_extra', ['failed' => $result['failed']]);
        }
        return ApiResponse::success($result, $msg);
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
