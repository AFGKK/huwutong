<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PasswordPolicyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 密码策略 + 账号锁定管理
 */
class PasswordPolicyController extends Controller
{
    public function __construct(
        protected PasswordPolicyService $passwordPolicyService,
    ) {}

    /**
     * 获取当前密码策略
     */
    public function getConfig(): JsonResponse
    {
        $config = $this->passwordPolicyService->getConfig();

        return ApiResponse::success($config);
    }

    /**
     * 更新密码策略
     */
    public function updateConfig(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'min_length' => 'integer|min:4|max:256',
            'max_length' => 'integer|min:8|max:256',
            'require_uppercase' => 'boolean',
            'require_lowercase' => 'boolean',
            'require_number' => 'boolean',
            'require_special' => 'boolean',
            'history_count' => 'integer|min:0|max:50',
            'expiry_days' => 'integer|min:0|max:365',
            'lockout_max_attempts' => 'integer|min:1|max:100',
            'lockout_duration_minutes' => 'integer|min:1|max:1440',
            'is_active' => 'boolean',
        ]);

        $config = $this->passwordPolicyService->updateConfig($validated);

        return ApiResponse::success($config, __('app.api.password_policy_api.policy_updated'));
    }

    /**
     * 获取所有被锁定的账号
     */
    public function lockedAccounts(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 20);
        $accounts = $this->passwordPolicyService->getLockedAccounts($perPage);

        return ApiResponse::success($accounts);
    }

    /**
     * 管理员手动解锁账号
     */
    public function unlockAccount(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $user = User::findOrFail($request->input('user_id'));

        $this->passwordPolicyService->unlockAccount($user);

        // 审计日志
        app(\App\Services\AuditService::class)->log(
            action: 'account_unlocked',
            description: "管理员 {$request->user()->name} 解锁了账号 {$user->name}({$user->email})",
            tenantId: $request->user()->tenant_id,
            userId: $request->user()->id,
        );

        return ApiResponse::success(null, __('app.api.password_policy_api.account_unlocked'));
    }
}
