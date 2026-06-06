<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Models\AccountDeletionRequest;
use App\Models\InviteCode;
use App\Models\LegalConsent;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\AuthService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * 多方式登录注册控制器
 *
 * M1.4-23 ~ M1.4-34
 */
class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService,
        protected NotificationService $notificationService,
    ) {}

    // ─── 注册 / 登录 ───

    public function register(RegisterRequest $request): JsonResponse
    {
        // 检查邀请码（如果需要）
        $inviteCode = $request->input('invite_code');
        $whitelistOnly = config('auth.invite_only', false);

        if ($whitelistOnly) {
            if (! $inviteCode || ! $this->authService->consumeInviteCode($inviteCode)) {
                return ApiResponse::error('INVITE_REQUIRED', '需要有效的邀请码才能注册', 422);
            }
        }

        // 检查密码强度
        $passwordError = $this->authService->validatePasswordStrength($request->password);
        if ($passwordError) {
            return ApiResponse::validationError($passwordError);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'password_changed_at' => now(),
        ]);

        $this->authService->recordPasswordHistory($user, $request->password);

        $token = $user->createToken('auth-token', ['*'])->plainTextToken;

        $this->authService->recordLoginAudit(
            $user, 'register', $request->ip(), $request->userAgent(),
            'email', true,
        );

        return ApiResponse::created([
            'user' => $this->formatUser($user),
            'token' => $token,
            'pending_consents' => $this->authService->getPendingConsents($user),
        ], '注册成功');
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $email = $request->input('email');
        $phone = $request->input('phone');

        // 检查账号锁定
        if ($email && $this->authService->isAccountLocked($email)) {
            $minutes = $this->authService->getLockoutRemainingMinutes($email);
            return ApiResponse::error(
                'ACCOUNT_LOCKED',
                "账号已被锁定，请 {$minutes} 分钟后再试",
                429,
                ['lockout_minutes' => $minutes],
            );
        }

        $user = null;
        if ($email) {
            $user = User::where('email', $email)->first();
        } elseif ($phone) {
            $user = User::where('phone', $phone)->first();
        }

        if (! $user || ! Hash::check($request->password, $user->password)) {
            $identifier = $email ?? $phone ?? 'unknown';
            $attempts = $this->authService->recordFailedAttempt($identifier);

            $this->authService->recordLoginAudit(
                $user, 'login', $request->ip(), $request->userAgent(),
                'email', false, '密码错误',
            );

            $remaining = AuthService::LOCKOUT_MAX_ATTEMPTS - $attempts;
            $message = '邮箱/手机号或密码错误';
            if ($remaining > 0 && $remaining <= 3) {
                $message .= "，还剩 {$remaining} 次尝试机会";
            }

            return ApiResponse::error('AUTH_FAILED', $message, 401);
        }

        // 检查账号状态
        if ($user->status !== 'active') {
            return ApiResponse::error('ACCOUNT_DISABLED', '账号已被禁用', 403);
        }

        // 清除失败记录
        $this->authService->clearFailedAttempts($email ?? $phone);

        // 检查密码是否需要更改
        $passwordExpiring = $this->authService->isPasswordExpiringSoon($user);

        $token = $user->createToken('auth-token', ['*'])->plainTextToken;

        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        $this->authService->recordLoginAudit(
            $user, 'login', $request->ip(), $request->userAgent(),
            'email', true,
        );

        // 设备信任检查
        $deviceFingerprint = $request->input('device_fingerprint');
        $isTrustedDevice = $deviceFingerprint
            ? $this->authService->isDeviceTrusted($user, $deviceFingerprint)
            : null;

        $response = [
            'user' => $this->formatUser($user),
            'token' => $token,
            'password_expiring' => $passwordExpiring,
        ];

        if ($isTrustedDevice === false) {
            $response['is_new_device'] = true;
            $response['device_fingerprint'] = $deviceFingerprint;
        }

        return ApiResponse::success($response, '登录成功');
    }

    public function user(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $this->formatUser($user);

        // 附加待确认协议
        $data['pending_consents'] = $this->authService->getPendingConsents($user);

        // 密码过期提醒
        $data['password_expiring'] = $this->authService->isPasswordExpiringSoon($user);

        // 账号注销状态
        $data['deletion_request'] = optional($user->deletionRequest)->only([
            'id', 'status', 'reason', 'cooling_until', 'created_at',
        ]);

        return ApiResponse::success($data);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        $this->authService->recordLoginAudit(
            $user, 'logout', $request->ip(), $request->userAgent(),
        );

        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success(null, '已退出登录');
    }

    // ─── Token 刷新 ───

    /**
     * 刷新当前 Token
     *
     * POST /api/token/refresh
     * 删除当前 token 并颁发新 token，用于前端静默续期
     */
    public function refreshToken(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentToken = $user->currentAccessToken();

        $this->authService->recordLoginAudit(
            $user, 'token_refresh', $request->ip(), $request->userAgent(),
        );

        $currentToken->delete();

        $newToken = $user->createToken(
            $currentToken->name ?: 'api-token',
            $currentToken->abilities ?: ['*'],
        );

        return ApiResponse::success([
            'token' => $newToken->plainTextToken,
            'expires_at' => $newToken->accessToken->expires_at,
        ], 'Token 已刷新');
    }

    // ─── 邮箱验证 ───

    /**
     * 发送邮箱验证码
     */
    public function sendEmailVerification(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->email_verified_at) {
            return ApiResponse::error('ALREADY_VERIFIED', '邮箱已验证', 422);
        }

        $verification = $this->authService->sendEmailVerification($user);

        // 发送验证码邮件
        try {
            Mail::to($user->email)->send(new \App\Mail\EmailVerification($user, $verification->token));
        } catch (\Throwable $e) {
            Log::error('发送邮箱验证码邮件失败', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        return ApiResponse::success([
            'expires_at' => $verification->expires_at,
        ], '验证码已发送');
    }

    /**
     * 验证邮箱
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => 'required|string|size:6',
        ]);

        $user = $request->user();

        if ($this->authService->verifyEmail($user, $data['token'])) {
            return ApiResponse::success(null, '邮箱验证成功');
        }

        return ApiResponse::error('INVALID_TOKEN', '验证码无效或已过期', 422);
    }

    // ─── 忘记密码 / 重置密码 ───

    /**
     * 发送忘记密码验证码
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $token = $this->authService->generatePasswordResetToken($data['email']);

        if (! $token) {
            return ApiResponse::notFound('该邮箱未注册');
        }

        // 发送密码重置邮件
        try {
            Mail::to($data['email'])->send(new \App\Mail\PasswordReset($data['email'], $token));
        } catch (\Throwable $e) {
            Log::error('发送密码重置邮件失败', [
                'email' => $data['email'],
                'error' => $e->getMessage(),
            ]);
        }

        return ApiResponse::success(null, '验证码已发送到您的邮箱');
    }

    /**
     * 重置密码
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email|exists:users,email',
            'token' => 'required|string|size:6',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // 检查密码强度
        $passwordError = $this->authService->validatePasswordStrength($data['password']);
        if ($passwordError) {
            return ApiResponse::validationError($passwordError);
        }

        if ($this->authService->resetPassword($data['email'], $data['token'], $data['password'])) {
            return ApiResponse::success(null, '密码重置成功');
        }

        return ApiResponse::error('INVALID_TOKEN', '验证码无效或已过期', 422);
    }

    // ─── 手机验证码登录 ───

    /**
     * 发送手机验证码
     */
    public function sendPhoneCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => 'required|string|regex:/^1[3-9]\d{9}$/',
        ]);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // 存储到缓存（5分钟有效，60秒防刷）
        $cacheKey = 'phone_code:' . $data['phone'];
        $lastSent = Cache::get($cacheKey . '_sent');

        if ($lastSent && now()->diffInSeconds($lastSent) < 60) {
            return ApiResponse::error('TOO_FREQUENT', '发送太频繁，请稍后再试', 429);
        }

        Cache::put($cacheKey, $code, now()->addMinutes(5));
        Cache::put($cacheKey . '_sent', now(), now()->addMinutes(5));

        // 发送短信验证码
        try {
            app(\App\Services\SmsService::class)->sendVerificationCode($data['phone'], $code);
        } catch (\Throwable $e) {
            Log::error('发送短信验证码失败', [
                'phone' => $data['phone'],
                'error' => $e->getMessage(),
            ]);
        }

        return ApiResponse::success(null, '验证码已发送');
    }

    /**
     * 手机验证码登录
     */
    public function phoneLogin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => 'required|string|regex:/^1[3-9]\d{9}$/',
            'code' => 'required|string|size:6',
        ]);

        // 校验验证码
        $cacheKey = 'phone_code:' . $data['phone'];
        $storedCode = Cache::get($cacheKey);

        if (! $storedCode || $storedCode !== $data['code']) {
            return ApiResponse::error('INVALID_CODE', '验证码错误或已过期', 422);
        }

        Cache::forget($cacheKey);

        // 查找或创建用户
        $user = User::where('phone', $data['phone'])->first();

        if (! $user) {
            $user = User::create([
                'name' => '用户' . substr($data['phone'], -4),
                'phone' => $data['phone'],
                'password' => Hash::make(\Illuminate\Support\Str::random(32)),
                'status' => 'active',
                'phone_verified_at' => now(),
            ]);
        }

        if ($user->status !== 'active') {
            return ApiResponse::error('ACCOUNT_DISABLED', '账号已被禁用', 403);
        }

        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
            'phone_verified_at' => $user->phone_verified_at ?? now(),
        ]);

        $token = $user->createToken('phone-token', ['*'])->plainTextToken;

        $this->authService->recordLoginAudit(
            $user, 'login', $request->ip(), $request->userAgent(),
            'phone', true,
        );

        return ApiResponse::success([
            'user' => $this->formatUser($user),
            'token' => $token,
        ], '登录成功');
    }

    // ─── 密码修改 ───

    /**
     * 修改密码
     */
    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            return ApiResponse::error('INVALID_PASSWORD', '当前密码错误', 422);
        }

        // 检查密码强度
        $passwordError = $this->authService->validatePasswordStrength($data['new_password']);
        if ($passwordError) {
            return ApiResponse::validationError($passwordError);
        }

        // 检查密码历史
        if (! $this->authService->isPasswordAllowed($user, $data['new_password'])) {
            return ApiResponse::error('PASSWORD_REUSED', '不能使用最近使用过的密码', 422);
        }

        $user->update([
            'password' => Hash::make($data['new_password']),
            'password_changed_at' => now(),
        ]);

        $this->authService->recordPasswordHistory($user, $data['new_password']);

        // 吊销所有其他 token（强制重新登录）
        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        return ApiResponse::success(null, '密码修改成功');
    }

    // ─── Session 管理 ───

    /**
     * 获取活跃会话列表
     */
    public function sessions(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentTokenId = $user->currentAccessToken()->id;

        $sessions = $user->tokens()->get()->map(function ($token) use ($currentTokenId) {
            $isCurrent = $token->id === $currentTokenId;
            return [
                'id' => $token->id,
                'name' => $token->name,
                'is_current' => $isCurrent,
                'last_used_at' => $token->last_used_at,
                'created_at' => $token->created_at,
                'expires_at' => $token->expires_at,
                'ip_address' => $token->ip_address ?? null,
                'user_agent' => $token->user_agent ?? null,
            ];
        });

        return ApiResponse::success($sessions);
    }

    /**
     * 远程踢出指定会话
     */
    public function revokeSession(int $tokenId, Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $user->tokens()->findOrFail($tokenId);

        if ($token->id === $user->currentAccessToken()->id) {
            return ApiResponse::error('CANNOT_REVOKE_CURRENT', '不能吊销当前会话', 422);
        }

        $token->delete();

        return ApiResponse::success(null, '会话已吊销');
    }

    // ─── 设备信任 ───

    /**
     * 信任当前设备
     */
    public function trustDevice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_fingerprint' => 'required|string',
            'device_name' => 'nullable|string|max:255',
        ]);

        $user = $request->user();

        $this->authService->trustDevice(
            $user,
            $data['device_fingerprint'],
            $data['device_name'] ?? null,
            $request->ip(),
            $request->userAgent(),
        );

        return ApiResponse::success(null, '设备已信任');

    }

    /**
     * 获取信任设备列表
     */
    public function trustedDevices(Request $request): JsonResponse
    {
        $devices = $this->authService->getTrustedDevices($request->user());
        return ApiResponse::success($devices);
    }

    /**
     * 取消设备信任
     */
    public function removeTrustedDevice(int $deviceId, Request $request): JsonResponse
    {
        $device = $request->user()->trustedDevices()->findOrFail($deviceId);
        $device->delete();

        return ApiResponse::success(null, '设备信任已取消');
    }

    /**
     * 清除所有信任设备
     */
    public function clearTrustedDevices(Request $request): JsonResponse
    {
        $request->user()->trustedDevices()->delete();

        return ApiResponse::success(null, '已清除所有信任设备');
    }

    /**
     * 登录时检测设备状态，新设备触发通知
     */
    public function checkDevice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_fingerprint' => 'required|string',
            'device_name' => 'nullable|string|max:255',
        ]);

        $user = $request->user();
        $fingerprint = $data['device_fingerprint'];
        $isTrusted = $this->authService->isDeviceTrusted($user, $fingerprint);

        if (!$isTrusted) {
            $this->notificationService->sendNewDeviceNotification(
                $user,
                $data['device_name'] ?? '未知设备',
                $request->ip(),
                $request->userAgent(),
            );
        } else {
            TrustedDevice::where('user_id', $user->id)
                ->where('device_fingerprint', $fingerprint)
                ->update(['last_seen_at' => now()]);
        }

        return ApiResponse::success([
            'is_trusted' => $isTrusted,
        ]);
    }

    // ─── 邀请码管理 ───

    /**
     * 批量生成邀请码（管理员）
     */
    public function generateInviteCodes(Request $request): JsonResponse
    {
        $data = $request->validate([
            'count' => 'required|integer|min:1|max:100',
            'max_uses' => 'integer|min:1|max:1000',
            'expires_at' => 'nullable|date',
            'remarks' => 'nullable|string|max:500',
        ]);

        $codes = $this->authService->generateInviteCodes(
            $data['count'],
            $data['max_uses'] ?? 1,
            $data['expires_at'] ?? null,
            $data['remarks'] ?? null,
        );

        return ApiResponse::success([
            'codes' => collect($codes)->map(fn($c) => [
                'id' => $c->id,
                'code' => $c->code,
                'max_uses' => $c->max_uses,
                'expires_at' => $c->expires_at,
                'status' => $c->status,
                'created_at' => $c->created_at,
            ]),
            'stats' => $this->authService->getInviteCodeStats(),
        ], '邀请码已生成');
    }

    /**
     * 获取邀请码列表
     */
    public function inviteCodesList(Request $request): JsonResponse
    {
        $query = InviteCode::query();

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $codes = $query->latest()->paginate($request->input('per_page', 20));
        return ApiResponse::paginated($codes);
    }

    /**
     * 获取邀请码统计
     */
    public function inviteCodeStats(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->authService->getInviteCodeStats()
        );
    }

    // ─── 隐私协议 ───

    /**
     * 获取当前协议
     */
    public function getLegalConsents(Request $request): JsonResponse
    {
        $consents = LegalConsent::where('is_current', true)->get();
        return ApiResponse::success($consents);
    }

    /**
     * 确认协议
     */
    public function consentToLegal(Request $request): JsonResponse
    {
        $data = $request->validate([
            'legal_consent_id' => 'required|integer|exists:legal_consents,id',
        ]);

        $consent = LegalConsent::findOrFail($data['legal_consent_id']);

        if ($consent->isConsentedBy($request->user()->id)) {
            return ApiResponse::error('ALREADY_CONSENTED', '您已确认过此协议', 422);
        }

        $this->authService->consentTo(
            $request->user(),
            $consent->id,
            $request->ip(),
        );

        return ApiResponse::success(null, '协议确认成功');
    }

    // ─── 账号注销 ───

    /**
     * 提交注销申请
     */
    public function requestDeletion(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        try {
            $deletionRequest = $this->authService->requestDeletion(
                $request->user(),
                $data['reason'] ?? null,
            );
        } catch (\RuntimeException $e) {
            return ApiResponse::error('PENDING_REQUEST', $e->getMessage(), 422);
        }

        return ApiResponse::success([
            'cooling_until' => $deletionRequest->cooling_until,
            'cooling_days' => AuthService::COOLING_DAYS,
        ], '注销申请已提交，请在冷静期后确认');
    }

    /**
     * 取消注销申请
     */
    public function cancelDeletion(Request $request): JsonResponse
    {
        if ($this->authService->cancelDeletion($request->user())) {
            return ApiResponse::success(null, '注销申请已取消');
        }
        return ApiResponse::notFound('没有待处理的注销申请');
    }

    /**
     * 获取注销申请状态
     */
    public function deletionStatus(Request $request): JsonResponse
    {
        $deletionRequest = AccountDeletionRequest::where('user_id', $request->user()->id)
            ->latest()
            ->first();

        if (! $deletionRequest) {
            return ApiResponse::success(null);
        }

        return ApiResponse::success([
            'id' => $deletionRequest->id,
            'status' => $deletionRequest->status,
            'reason' => $deletionRequest->reason,
            'cooling_until' => $deletionRequest->cooling_until,
            'cooling_over' => $deletionRequest->isCoolingOver(),
            'processed_at' => $deletionRequest->processed_at,
            'created_at' => $deletionRequest->created_at,
        ]);
    }

    // ─── OAuth 绑定 ───

    /**
     * 绑定 OAuth 提供商
     */
    public function bindOAuth(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider' => 'required|string|in:wechat,google,github,qq,apple',
            'provider_id' => 'required|string',
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email',
            'avatar' => 'nullable|url',
            'metadata' => 'nullable|array',
        ]);

        $user = $request->user();

        // 检查是否已被其他账号绑定
        $existing = \App\Models\UserAuthProvider::where('provider', $data['provider'])
            ->where('provider_id', $data['provider_id'])
            ->first();

        if ($existing && $existing->user_id !== $user->id) {
            return ApiResponse::error('ALREADY_BOUND', '该第三方账号已被其他用户绑定', 422);
        }

        $authProvider = $user->authProviders()->updateOrCreate(
            ['provider' => $data['provider'], 'provider_id' => $data['provider_id']],
            [
                'avatar' => $data['avatar'] ?? null,
                'nickname' => $data['name'] ?? null,
                'metadata' => $data['metadata'] ?? null,
            ],
        );

        return ApiResponse::success($authProvider, '绑定成功');
    }

    /**
     * 解除 OAuth 绑定
     */
    public function unbindOAuth(int $authProviderId, Request $request): JsonResponse
    {
        try {
            $this->authService->unbindProvider($request->user(), $authProviderId);
            return ApiResponse::success(null, '解绑成功');
        } catch (\RuntimeException $e) {
            return ApiResponse::error('UNBIND_FAILED', $e->getMessage(), 422);
        }
    }

    /**
     * 获取已绑定的 OAuth 提供商列表
     */
    public function boundProviders(Request $request): JsonResponse
    {
        $user = $request->user();
        $providers = $user->authProviders()->get()->map(fn($p) => [
            'id' => $p->id,
            'provider' => $p->provider,
            'nickname' => $p->nickname,
            'avatar' => $p->avatar,
            'created_at' => $p->created_at,
        ]);

        // 同时返回支持的登录方式状态
        $hasPassword = ! empty($user->password);
        $hasPhone = ! empty($user->phone);

        return ApiResponse::success([
            'oauth_providers' => $providers,
            'has_password' => $hasPassword,
            'has_phone' => $hasPhone,
        ]);
    }

    /**
     * OAuth 登录回调
     */
    public function oauthLogin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider' => 'required|string|in:wechat,google,github,qq,apple',
            'provider_id' => 'required|string',
            'email' => 'nullable|email',
            'name' => 'nullable|string|max:255',
            'avatar' => 'nullable|url',
            'metadata' => 'nullable|array',
        ]);

        $user = $this->authService->findOrCreateOAuthUser(
            $data['provider'],
            $data['provider_id'],
            $data['email'] ?? '',
            $data['name'] ?? 'User',
            $data['avatar'] ?? null,
            $data['metadata'] ?? null,
        );

        if ($user->status !== 'active') {
            return ApiResponse::error('ACCOUNT_DISABLED', '账号已被禁用', 403);
        }

        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        $token = $user->createToken("{$data['provider']}-token", ['*'])->plainTextToken;

        $this->authService->recordLoginAudit(
            $user, 'login', $request->ip(), $request->userAgent(),
            $data['provider'], true,
        );

        return ApiResponse::success([
            'user' => $this->formatUser($user),
            'token' => $token,
            'is_new_user' => $user->wasRecentlyCreated,
        ], '登录成功');
    }

    // ─── 登录审计日志 ───

    /**
     * 获取登录历史
     */
    public function loginHistory(Request $request): JsonResponse
    {
        $logs = \App\Models\LoginAudit::where('user_id', $request->user()->id)
            ->latest()
            ->paginate($request->input('per_page', 20));

        return ApiResponse::paginated($logs);
    }

    // ─── 工具方法 ───

    protected function formatUser(User $user): array
    {
        $data = $user->toArray();

        // 移除敏感字段
        unset($data['password_history']);

        $tenants = $user->tenants()->get(['tenants.id', 'tenants.name', 'tenants.slug', 'tenants.logo']);
        $data['tenants'] = $tenants;
        $data['is_multi_tenant'] = $tenants->count() > 1;
        $data['active_tenant_id'] = $user->remember_tenant_id ?? $user->tenant_id;
        $data['has_password'] = ! empty($user->password);
        $data['has_phone'] = ! empty($user->phone);
        $data['email_verified'] = $user->email_verified_at !== null;
        $data['phone_verified'] = $user->phone_verified_at !== null;

        return $data;
    }
}
