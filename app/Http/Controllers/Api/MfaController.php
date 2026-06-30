<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\MfaDevice;
use App\Models\MfaRecoveryAudit;
use App\Models\User;
use App\Services\MfaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * MFA 控制器
 *
 * 提供 MFA 配置、验证、设备管理和恢复码功能的 API。
 */
class MfaController extends Controller
{
    public function __construct(
        protected MfaService $mfaService,
    ) {}

    // ─── 获取 TOTP 配置 ───

    /**
     * 获取 TOTP 设置信息（密钥 + 二维码 URI）
     *
     * GET /api/mfa/setup
     */
    public function setup(Request $request): JsonResponse
    {
        $user = $request->user();
        $secret = $this->mfaService->generateSecret();

        return ApiResponse::success(
            $this->mfaService->getTOTPConfig($secret, $user->email),
        );
    }

    /**
     * 确认并启用 MFA
     *
     * POST /api/mfa/confirm
     */
    public function confirm(Request $request): JsonResponse
    {
        $data = $request->validate([
            'secret' => 'required|string|size:32',
            'code' => 'required|string|size:6',
            'device_name' => 'nullable|string|max:100',
        ]);

        $user = $request->user();

        // 先验证 TOTP 码
        if (! $this->mfaService->verifyTOTP($data['secret'], $data['code'])) {
            return ApiResponse::error('MFA_CODE_INVALID', '验证码无效，请重试', 400);
        }

        $deviceName = $data['device_name'] ?? '默认设备 (' . now()->format('Y-m-d') . ')';

        // 启用 MFA
        $device = $this->mfaService->enableMfa($user, $deviceName, $data['secret']);

        // 生成恢复码
        $recoveryCodes = $this->mfaService->generateRecoveryCodes($user);

        return ApiResponse::success([
            'device' => $device,
            'recovery_codes' => $recoveryCodes,
            'message' => '请立即保存恢复码，关闭后将无法再次查看',
        ], 'MFA 已启用');
    }

    // ─── 验证 MFA ───

    /**
     * 验证 MFA 码（用于登录后的 MFA 验证步骤）
     *
     * POST /api/mfa/verify
     */
    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => 'required|string',
        ]);

        $user = $request->user();
        $result = $this->mfaService->verifyMfa($user, $data['code']);

        if (! $result['verified']) {
            return ApiResponse::error('MFA_CODE_INVALID', 'MFA 验证码无效', 401);
        }

        // 更新设备最后使用时间
        MfaDevice::where('user_id', $user->id)
            ->where('type', 'totp')
            ->latest()
            ->first()
            ?->update(['last_used_at' => now()]);

        return ApiResponse::success([
            'verified' => true,
            'method' => $result['method'],
        ], 'MFA 验证成功');
    }

    // ─── 设备管理 ───

    /**
     * 获取用户绑定的 MFA 设备列表
     *
     * GET /api/mfa/devices
     */
    public function devices(Request $request): JsonResponse
    {
        $devices = $this->mfaService->getUserDevices($request->user());

        return ApiResponse::success($devices->map(fn($d) => [
            'id' => $d->id,
            'name' => $d->name,
            'type' => $d->type,
            'last_used_at' => $d->last_used_at?->diffForHumans(),
            'confirmed_at' => $d->confirmed_at?->toDateString(),
            'created_at' => $d->created_at->toDateString(),
        ]));
    }

    /**
     * 重命名 MFA 设备
     *
     * PUT /api/mfa/devices/{device}/rename
     */
    public function renameDevice(Request $request, int $deviceId): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $device = MfaDevice::where('id', $deviceId)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $device) {
            return ApiResponse::notFound('MFA 设备不存在');
        }

        $this->mfaService->renameDevice($device, $data['name']);

        return ApiResponse::success(['device' => $device], '设备已重命名');
    }

    /**
     * 解绑 MFA 设备
     *
     * DELETE /api/mfa/devices/{device}
     */
    public function deleteDevice(Request $request, int $deviceId): JsonResponse
    {
        $device = MfaDevice::where('id', $deviceId)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $device) {
            return ApiResponse::notFound('MFA 设备不存在');
        }

        $this->mfaService->deleteDevice($device);

        return ApiResponse::success(null, '设备已解绑');
    }

    // ─── 恢复码管理 ───

    /**
     * 获取恢复码状态（剩余数量，不返回具体码）
     *
     * GET /api/mfa/recovery-codes
     */
    public function recoveryCodesStatus(Request $request): JsonResponse
    {
        $user = $request->user();

        return ApiResponse::success([
            'total' => MfaService::RECOVERY_CODES_COUNT,
            'remaining' => $this->mfaService->countRemainingCodes($user),
            'has_codes' => ! empty($user->mfa_recovery_codes),
        ]);
    }

    /**
     * 重新生成恢复码（旧的立即失效）
     *
     * POST /api/mfa/recovery-codes/regenerate
     */
    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $user = $request->user();
        $codes = $this->mfaService->generateRecoveryCodes($user);

        return ApiResponse::success([
            'recovery_codes' => $codes,
        ], '恢复码已重新生成，请立即保存');
    }

    // ─── 禁用 MFA ───

    /**
     * 禁用 MFA（需要当前有效的 MFA 码验证）
     *
     * POST /api/mfa/disable
     */
    public function disable(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => 'required|string',
        ]);

        $user = $request->user();
        $result = $this->mfaService->verifyMfa($user, $data['code']);

        if (! $result['verified']) {
            return ApiResponse::error('MFA_CODE_INVALID', 'MFA 验证码无效', 401);
        }

        $this->mfaService->disableMfa($user);

        return ApiResponse::success(null, 'MFA 已禁用');
    }

    // ─── 管理员接口 ───

    /**
     * 管理员强制重置用户 MFA
     *
     * POST /api/admin/users/{user}/reset-mfa
     */
    public function adminResetUserMfa(User $user): JsonResponse
    {
        $this->mfaService->adminResetMfa($user);

        return ApiResponse::success(null, '用户 MFA 已重置');
    }

    /**
     * 获取 MFA 审计日志
     *
     * GET /api/admin/mfa-audit
     */
    public function auditLog(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => 'nullable|integer',
            'action' => 'nullable|string|in:generated,used,reset',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = MfaRecoveryAudit::with('user')->latest();

        if (! empty($data['user_id'])) {
            $query->where('user_id', $data['user_id']);
        }
        if (! empty($data['action'])) {
            $query->where('action', $data['action']);
        }

        return ApiResponse::paginated($query->paginate(min($data['per_page'] ?? 20, 100)));
    }

    // ─── 登录时 MFA 验证 ───

    /**
     * 登录后 MFA 验证（获取临时 token 后进行第二步验证）
     *
     * POST /api/mfa/login
     */
    public function mfaLogin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required_without:phone|email',
            'phone' => 'required_without:email|string',
            'password' => 'required|string',
            'mfa_code' => 'required|string',
        ]);

        $user = User::where('email', $data['email'] ?? null)
            ->orWhere('phone', $data['phone'] ?? null)
            ->first();

        if (! $user || ! \Illuminate\Support\Facades\Hash::check($data['password'], $user->password)) {
            return ApiResponse::error('AUTH_FAILED', '账号或密码错误', 401);
        }

        // 验证 MFA
        $result = $this->mfaService->verifyMfa($user, $data['mfa_code']);

        if (! $result['verified']) {
            return ApiResponse::error('MFA_CODE_INVALID', 'MFA 验证码无效', 401);
        }

        // 登录成功
        $token = $user->createToken('auth-token', ['*'])->plainTextToken;

        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        return ApiResponse::success([
            'user' => $user,
            'token' => $token,
            'mfa_method' => $result['method'],
        ], '登录成功');
    }

    /**
     * 登录时检查是否需要 MFA（密码验证通过后调用）
     *
     * POST /api/mfa/check-required
     */
    public function checkRequired(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required_without:phone|email',
            'phone' => 'required_without:email|string',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $data['email'] ?? null)
            ->orWhere('phone', $data['phone'] ?? null)
            ->first();

        if (! $user || ! \Illuminate\Support\Facades\Hash::check($data['password'], $user->password)) {
            return ApiResponse::error('AUTH_FAILED', '账号或密码错误', 401);
        }

        $requiresMfa = $this->mfaService->requiresMfa($user);

        return ApiResponse::success([
            'mfa_required' => $requiresMfa,
            'mfa_enabled' => $user->mfa_enabled,
            'mfa_setup_required' => $requiresMfa && ! $user->mfa_enabled,
        ]);
    }
}
