<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ImpersonateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 模拟登录控制器
 *
 * 超管可以模拟任意用户登录以排查问题。
 */
class ImpersonateController extends Controller
{
    public function __construct(
        protected ImpersonateService $impersonateService,
    ) {}

    /**
     * 开始模拟
     */
    public function start(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'reason' => 'nullable|string|max:500',
        ]);

        $impersonator = $request->user();

        $target = User::findOrFail($request->input('user_id'));

        $token = $this->impersonateService->start(
            $impersonator,
            $target,
            $request->input('reason'),
        );

        return ApiResponse::success([
            'token' => $token,
            'target' => [
                'id' => $target->id,
                'name' => $target->name,
                'email' => $target->email,
            ],
            'expires_in' => ImpersonateService::SESSION_TTL,
        ], '模拟登录开始');
    }

    /**
     * 结束模拟
     */
    public function stop(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $this->impersonateService->stop(
            $request->input('token'),
            $request->user(),
        );

        return ApiResponse::success(null, '已退出模拟模式');
    }

    /**
     * 获取当前模拟会话信息
     */
    public function session(Request $request): JsonResponse
    {
        $token = $request->header('X-Impersonate-Token');

        if (empty($token)) {
            return ApiResponse::success([
                'is_impersonating' => false,
            ]);
        }

        $session = $this->impersonateService->getSession($token);

        if (!$session) {
            return ApiResponse::success([
                'is_impersonating' => false,
            ]);
        }

        return ApiResponse::success([
            'is_impersonating' => true,
            'impersonator' => [
                'id' => $session['impersonator_id'],
                'name' => $session['impersonator_name'],
                'email' => $session['impersonator_email'],
            ],
            'target' => [
                'id' => $session['target_id'],
                'name' => $session['target_name'],
                'email' => $session['target_email'],
            ],
            'started_at' => $session['started_at'],
            'reason' => $session['reason'] ?? null,
        ]);
    }

    /**
     * 获取模拟登录历史
     */
    public function history(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 20);

        $logs = $this->impersonateService->getHistory($perPage);

        return ApiResponse::success($logs);
    }

    /**
     * 获取可模拟的用户列表
     */
    public function candidates(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:100',
        ]);

        $search = $request->input('search');

        $query = User::whereDoesntHave('roles', function ($q) {
            $q->where('name', 'super-admin');
        });

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->paginate($request->input('per_page', 20));

        return ApiResponse::success($users);
    }
}
