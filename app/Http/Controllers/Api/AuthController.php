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
use App\Services\MfaService;
use App\Services\NotificationService;
use App\Services\StoreAffiliateService;
use App\Services\TokenIntrospectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * ??????????
 *
 * M1.4-23 ~ M1.4-34
 */
class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService,
        protected NotificationService $notificationService,
        protected TokenIntrospectionService $tokenIntrospection,
        protected StoreAffiliateService $storeAffiliateService,
        protected MfaService $mfaService,
    ) {}

    // ??? ?? / ?? ???

    public function register(RegisterRequest $request): JsonResponse
    {
        if ((string) site_setting('registration_enabled', '1') === '0') {
            return ApiResponse::error('REGISTRATION_DISABLED', __('app.auth.api.registration_disabled'), 403);
        }

        // ???????????
        $inviteCode = $request->input('invite_code');
        $whitelistOnly = (bool) config('auth.invite_only', false)
            || (string) site_setting('registration_require_invite_code', '0') === '1';

        if ($whitelistOnly) {
            if (! $inviteCode || ! $this->authService->consumeInviteCode($inviteCode)) {
                return ApiResponse::error('INVITE_REQUIRED', __('app.auth.api.invite_required'), 422);
            }
        }

        // ??????
        $passwordError = $this->authService->validatePasswordStrength($request->password);
        if ($passwordError) {
            return ApiResponse::validationError($passwordError, ['password' => [$passwordError]]);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email ?: null,
            'phone' => $request->phone ?: null,
            'password' => Hash::make($request->password),
            'password_changed_at' => now(),
            'phone_verified_at' => null,
        ]);

        $this->authService->recordPasswordHistory($user, $request->password);

        $requireEmailVerify = $user->email
            && (string) site_setting('registration_require_email_verify', '0') === '1';

        if ($requireEmailVerify) {
            try {
                $verification = $this->authService->sendEmailVerification($user);
                Mail::to($user->email)->send(new \App\Mail\EmailVerification($user, $verification->token));
            } catch (\Throwable $e) {
                Log::error('???????????', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $this->authService->recordLoginAudit(
                $user, 'register', $request->ip(), $request->userAgent(),
                'email', true,
            );

            if ($inviteCode) {
                $this->storeAffiliateService->autoBuildAgentRelationshipOnRegistration($user, $inviteCode);
            }

            return ApiResponse::created([
                'user' => $this->formatUser($user),
                'token' => null,
                'requires_verification' => true,
                'pending_consents' => $this->authService->getPendingConsents($user),
            ], __('app.auth.api.register_verify_email'));
        }

        $token = $user->createToken('auth-token', $user->tokenAbilities())->plainTextToken;

        // ?? Token ??
        $version = $this->tokenIntrospection->getCurrentUserVersion($user->id);
        $user->tokens()->latest()->first()?->update(['token_version' => $version]);

        $this->authService->recordLoginAudit(
            $user, 'register', $request->ip(), $request->userAgent(),
            'email', true,
        );

        // ??????????????????
        if ($inviteCode) {
            $this->storeAffiliateService->autoBuildAgentRelationshipOnRegistration($user, $inviteCode);
        }

        return ApiResponse::created([
            'user' => $this->formatUser($user),
            'token' => $token,
            'requires_verification' => false,
            'pending_consents' => $this->authService->getPendingConsents($user),
        ], __('app.auth.api.register_ok'));
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $email = $request->input('email');
        $phone = $request->input('phone');

        // ??????
        if ($email && $this->authService->isAccountLocked($email)) {
            $minutes = $this->authService->getLockoutRemainingMinutes($email);
            return ApiResponse::error(
                'ACCOUNT_LOCKED',
                __('app.auth.api.account_locked', ['minutes' => $minutes]),
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
                'email', false, __('app.auth.api.password_wrong'),
            );

            $remaining = $this->authService->lockoutMaxAttempts() - $attempts;
            $message = __('app.auth.api.credentials_wrong');
            if ($remaining > 0 && $remaining <= 3) {
                $message .= __('app.auth.api.attempts_left', ['n' => $remaining]);
            }

            return ApiResponse::error('AUTH_FAILED', $message, 401);
        }

        // ??????
        if ($user->status !== 'active') {
            $msg = __('app.auth.api.account_disabled');
            if ($user->banned_at) {
                $msg = __('app.auth.api.account_banned');
            }
            return ApiResponse::error('ACCOUNT_DISABLED', $msg, 403);
        }

        // ???????????????? 1.1?
        if ($this->isPrivilegedUser($user) && $this->isKnownWeakPassword($request->password)) {
            return ApiResponse::error(
                'WEAK_PASSWORD_FORBIDDEN',
                __('app.auth.api.weak_password_forbidden'),
                403,
                ['must_change_password' => true],
            );
        }

        // ??????
        $this->authService->clearFailedAttempts($email ?? $phone);

        // ??????????
        $passwordExpiring = $this->authService->isPasswordExpiringSoon($user);
        $mustChangePassword = $passwordExpiring && $this->isPrivilegedUser($user)
            && empty($user->password_changed_at);

        // MFA???? ? ??? /api/mfa/login????????? ? ?? mfa-setup ?? token
        $requiresMfa = $this->mfaService->requiresMfa($user);
        if ($requiresMfa && $user->mfa_enabled) {
            return ApiResponse::error(
                'MFA_REQUIRED',
                __('app.auth.api.mfa_required'),
                403,
                [
                    'mfa_required' => true,
                    'mfa_setup_required' => false,
                    'password_expiring' => $passwordExpiring,
                    'must_change_password' => $mustChangePassword,
                ],
            );
        }

        if ($mustChangePassword) {
            return ApiResponse::error(
                'PASSWORD_CHANGE_REQUIRED',
                __('app.auth.api.password_change_required'),
                403,
                ['must_change_password' => true, 'password_expiring' => true],
            );
        }

        if ($requiresMfa && ! $user->mfa_enabled) {
            // ???? setup token?????
            $user->tokens()->where('name', 'mfa-setup')->delete();
            $setupToken = $user->createToken('mfa-setup', ['mfa-setup'])->plainTextToken;

            return ApiResponse::error(
                'MFA_SETUP_REQUIRED',
                __('app.auth.api.mfa_setup_required'),
                403,
                [
                    'mfa_required' => true,
                    'mfa_setup_required' => true,
                    'setup_token' => $setupToken,
                    'user' => $this->formatUser($user),
                ],
            );
        }

        $token = $user->createToken('auth-token', $user->tokenAbilities())->plainTextToken;

        // ?? Token ??
        $version = $this->tokenIntrospection->getCurrentUserVersion($user->id);
        $user->tokens()->latest()->first()?->update(['token_version' => $version]);

        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        $this->authService->recordLoginAudit(
            $user, 'login', $request->ip(), $request->userAgent(),
            'email', true,
        );

        // ??????
        $deviceFingerprint = $request->input('device_fingerprint');
        $isTrustedDevice = $deviceFingerprint
            ? $this->authService->isDeviceTrusted($user, $deviceFingerprint)
            : null;

        $response = [
            'user' => $this->formatUser($user),
            'token' => $token,
            'password_expiring' => $passwordExpiring,
            'mfa_setup_required' => false,
        ];

        if ($isTrustedDevice === false) {
            $response['is_new_device'] = true;
            $response['device_fingerprint'] = $deviceFingerprint;
        }

        return ApiResponse::success($response, __('app.auth.api.login_ok'));
    }

    protected function isPrivilegedUser(User $user): bool
    {
        // Spatie teams?????????????? hasRole ?? false????????
        app(\Spatie\Permission\PermissionRegistrar::class)
            ->setPermissionsTeamId($user->tenant_id ?? 1);
        $user->unsetRelation('roles');

        return $user->hasRole('super-admin')
            || $user->hasRole('admin')
            || $user->hasRole('tenant-admin');
    }

    protected function isKnownWeakPassword(string $password): bool
    {
        $weak = [
            'admin123', 'admin1234', 'password', 'password123',
            '12345678', '123456789', 'huwutong', 'huwutong123',
        ];

        return in_array(strtolower($password), $weak, true);
    }

    /**
     * WebAuthn rpId 必须与浏览器当前主机一致，优先取请求 Host。
     */
    protected function resolveWebauthnRpId(Request $request): string
    {
        $host = $request->getHost();
        if ($host && ! in_array($host, ['localhost', '127.0.0.1'], true)) {
            return $host;
        }

        // 本地开发：用当前 Host（含 localhost），避免 APP_URL 指向生产域名导致静默失败
        if ($host) {
            return $host;
        }

        return (string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost');
    }

    public function user(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $this->formatUser($user);

        // ???????
        $data['pending_consents'] = $this->authService->getPendingConsents($user);

        // ??????
        $data['password_expiring'] = $this->authService->isPasswordExpiringSoon($user);

        // ??????
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

        $token = $user->currentAccessToken();
        if ($token) {
            $this->tokenIntrospection->revokeToken(
                (string) $token->getKey(),
                'user_logout',
                $user->id,
            );
        }

        return response()->json(['success' => true, 'message' => __('app.auth.api.logged_out')]);
    }

    // ??? Token ?? ???

    /**
     * ???? Token
     *
     * POST /api/token/refresh
     * ???? token ???? token?????????
     */
    public function refreshToken(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentToken = $user->currentAccessToken();

        $this->authService->recordLoginAudit(
            $user, 'token_refresh', $request->ip(), $request->userAgent(),
        );

        // ??? Token
        if ($currentToken) {
            $this->tokenIntrospection->revokeToken(
                (string) $currentToken->getKey(),
                'token_refresh',
                $user->id,
            );
        }

        $newToken = $user->createToken(
            $currentToken ? $currentToken->name : 'api-token',
            $user->tokenAbilities(),
        );

        // ??? Token ??
        $version = $this->tokenIntrospection->getCurrentUserVersion($user->id);
        $user->tokens()->latest()->first()?->update(['token_version' => $version]);

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $newToken->plainTextToken,
                'expires_at' => $newToken->accessToken->expires_at,
            ],
            'message' => __('app.auth.api.token_refreshed'),
        ]);
    }

    // ??? ???? ???

    /**
     * ???????
     */
    public function sendEmailVerification(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->email_verified_at) {
            return ApiResponse::error('ALREADY_VERIFIED', __('app.auth.api.already_verified'), 422);
        }

        $verification = $this->authService->sendEmailVerification($user);

        // ???????
        try {
            Mail::to($user->email)->send(new \App\Mail\EmailVerification($user, $verification->token));
        } catch (\Throwable $e) {
            Log::error('???????????', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        return ApiResponse::success([
            'expires_at' => $verification->expires_at,
        ], __('app.auth.api.code_sent'));
    }

    /**
     * ????
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => 'required|string|size:6',
        ]);

        $user = $request->user();

        if ($this->authService->verifyEmail($user, $data['token'])) {
            return ApiResponse::success(null, __('app.auth.api.email_verified'));
        }

        return ApiResponse::error('INVALID_TOKEN', __('app.auth.api.invalid_token'), 422);
    }

    // ??? ???? / ???? ???

    /**
     * ?????????
     *
     * ???????????????????????????? exists ??????
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email|max:255',
        ]);

        $email = strtolower(trim($data['email']));
        $userExists = User::where('email', $email)->exists();

        if ($userExists) {
            $token = $this->authService->generatePasswordResetToken($email);

            if ($token) {
                try {
                    Mail::to($email)->send(new \App\Mail\PasswordReset($email, $token));
                } catch (\Throwable $e) {
                    Log::error('??????????', [
                        'email' => $email,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } else {
            // ??????????????
            usleep(random_int(80_000, 180_000));
        }

        return ApiResponse::success(null, __('app.auth.api.code_sent_email'));
    }

    /**
     * ????
     *
     * ???????????? token ????????????
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email|max:255',
            'token' => 'required|string|size:6',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // ??????
        $passwordError = $this->authService->validatePasswordStrength($data['password']);
        if ($passwordError) {
            return ApiResponse::validationError($passwordError);
        }

        $email = strtolower(trim($data['email']));
        if (! User::where('email', $email)->exists()) {
            usleep(random_int(80_000, 180_000));

            return ApiResponse::error('INVALID_TOKEN', __('app.auth.api.invalid_token'), 422);
        }

        if ($this->authService->resetPassword($email, $data['token'], $data['password'])) {
            return ApiResponse::success(null, __('app.auth.api.password_reset_ok'));
        }

        return ApiResponse::error('INVALID_TOKEN', __('app.auth.api.invalid_token'), 422);
    }

    // ??? ??????? / ?? ???

    /**
     * ???????
     *
     * scene=login|register??? login?
     */
    public function sendPhoneCode(Request $request): JsonResponse
    {
        if ((string) site_setting('sms_phone_auth_enabled', '1') === '0') {
            return ApiResponse::error('PHONE_AUTH_DISABLED', __('app.auth.api.phone_auth_disabled'), 403);
        }

        $data = $request->validate([
            'phone' => 'required|string|regex:/^1[3-9]\d{9}$/',
            'scene' => 'sometimes|in:login,register',
        ]);

        $scene = $data['scene'] ?? 'login';
        $exists = User::where('phone', $data['phone'])->exists();

        if ($scene === 'register' && $exists) {
            return ApiResponse::error('PHONE_EXISTS', __('app.auth.api.phone_exists'), 422);
        }

        $cacheKey = 'phone_code:' . $data['phone'];
        $lastSent = Cache::get($cacheKey . '_sent');

        if ($lastSent && now()->diffInSeconds($lastSent) < 60) {
            return ApiResponse::error('TOO_FREQUENT', __('app.auth.api.too_frequent'), 429);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put($cacheKey, $code, now()->addMinutes(5));
        Cache::put($cacheKey . '_scene', $scene, now()->addMinutes(5));
        Cache::put($cacheKey . '_sent', now(), now()->addMinutes(5));

        try {
            $result = app(\App\Services\SmsService::class)->sendVerificationCode($data['phone'], $code);
            if (! ($result['success'] ?? false)) {
                Cache::forget($cacheKey);
                Cache::forget($cacheKey . '_scene');
                Cache::forget($cacheKey . '_sent');

                return ApiResponse::error('SMS_FAILED', $result['message'] ?? __('app.auth.api.sms_failed'), 502);
            }
        } catch (\Throwable $e) {
            Cache::forget($cacheKey);
            Cache::forget($cacheKey . '_scene');
            Cache::forget($cacheKey . '_sent');
            Log::error('?????????', [
                'phone' => $data['phone'],
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('SMS_FAILED', __('app.auth.api.sms_failed'), 502);
        }

        return ApiResponse::success([
            'expires_in' => 300,
            'scene' => $scene,
        ], __('app.auth.api.code_sent'));
    }

    /**
     * ?????????????????
     */
    public function phoneLogin(Request $request): JsonResponse
    {
        if ((string) site_setting('sms_phone_auth_enabled', '1') === '0') {
            return ApiResponse::error('PHONE_AUTH_DISABLED', __('app.auth.api.phone_auth_disabled'), 403);
        }

        $data = $request->validate([
            'phone' => 'required|string|regex:/^1[3-9]\d{9}$/',
            'code' => 'required|string|size:6',
        ]);

        $cacheKey = 'phone_code:' . $data['phone'];
        $storedCode = Cache::get($cacheKey);

        if (! $storedCode || $storedCode !== $data['code']) {
            return ApiResponse::error('INVALID_CODE', __('app.auth.api.invalid_code'), 422);
        }

        Cache::forget($cacheKey);
        Cache::forget($cacheKey . '_scene');
        Cache::forget($cacheKey . '_sent');

        $user = User::where('phone', $data['phone'])->first();
        $isNew = false;

        if (! $user) {
            if ((string) site_setting('registration_enabled', '1') === '0') {
                return ApiResponse::error('REGISTRATION_DISABLED', __('app.auth.api.registration_disabled'), 403);
            }

            $user = User::create([
                'name' => __('app.auth.api.user_prefix') . substr($data['phone'], -4),
                'email' => null,
                'phone' => $data['phone'],
                'password' => Hash::make(\Illuminate\Support\Str::random(32)),
                'status' => 'active',
                'phone_verified_at' => now(),
            ]);
            $isNew = true;
        }

        if ($user->status !== 'active') {
            return ApiResponse::error('ACCOUNT_DISABLED', __('app.auth.api.account_disabled'), 403);
        }

        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
            'phone_verified_at' => $user->phone_verified_at ?? now(),
        ]);

        $token = $user->createToken('phone-token', $user->tokenAbilities())->plainTextToken;

        $version = $this->tokenIntrospection->getCurrentUserVersion($user->id);
        $user->tokens()->latest()->first()?->update(['token_version' => $version]);

        $this->authService->recordLoginAudit(
            $user, $isNew ? 'register' : 'login', $request->ip(), $request->userAgent(),
            'phone', true,
        );

        return ApiResponse::success([
            'user' => $this->formatUser($user),
            'token' => $token,
            'is_new' => $isNew,
        ], __('app.auth.api.login_ok'));
    }

    /**
     * ??? + ??????????????
     */
    public function phoneRegister(Request $request): JsonResponse
    {
        if ((string) site_setting('sms_phone_auth_enabled', '1') === '0') {
            return ApiResponse::error('PHONE_AUTH_DISABLED', __('app.auth.api.phone_auth_disabled'), 403);
        }

        if ((string) site_setting('registration_enabled', '1') === '0') {
            return ApiResponse::error('REGISTRATION_DISABLED', __('app.auth.api.registration_disabled'), 403);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|regex:/^1[3-9]\d{9}$/|unique:users,phone',
            'code' => 'required|string|size:6',
            'password' => 'required|string|min:8|confirmed',
            'invite_code' => 'sometimes|nullable|string|max:64',
        ]);

        $inviteCode = $data['invite_code'] ?? null;
        $whitelistOnly = (bool) config('auth.invite_only', false)
            || (string) site_setting('registration_require_invite_code', '0') === '1';

        if ($whitelistOnly) {
            if (! $inviteCode || ! $this->authService->consumeInviteCode($inviteCode)) {
                return ApiResponse::error('INVITE_REQUIRED', __('app.auth.api.invite_required'), 422);
            }
        }

        $cacheKey = 'phone_code:' . $data['phone'];
        $storedCode = Cache::get($cacheKey);

        if (! $storedCode || $storedCode !== $data['code']) {
            return ApiResponse::error('INVALID_CODE', __('app.auth.api.invalid_code'), 422);
        }

        $passwordError = $this->authService->validatePasswordStrength($data['password']);
        if ($passwordError) {
            return ApiResponse::validationError($passwordError, ['password' => [$passwordError]]);
        }

        Cache::forget($cacheKey);
        Cache::forget($cacheKey . '_scene');
        Cache::forget($cacheKey . '_sent');

        $user = User::create([
            'name' => $data['name'],
            'email' => null,
            'phone' => $data['phone'],
            'password' => Hash::make($data['password']),
            'password_changed_at' => now(),
            'status' => 'active',
            'phone_verified_at' => now(),
        ]);

        $this->authService->recordPasswordHistory($user, $data['password']);

        $token = $user->createToken('auth-token', $user->tokenAbilities())->plainTextToken;
        $version = $this->tokenIntrospection->getCurrentUserVersion($user->id);
        $user->tokens()->latest()->first()?->update(['token_version' => $version]);

        $this->authService->recordLoginAudit(
            $user, 'register', $request->ip(), $request->userAgent(),
            'phone', true,
        );

        if ($inviteCode) {
            $this->storeAffiliateService->autoBuildAgentRelationshipOnRegistration($user, $inviteCode);
        }

        return ApiResponse::created([
            'user' => $this->formatUser($user),
            'token' => $token,
            'requires_verification' => false,
            'pending_consents' => $this->authService->getPendingConsents($user),
        ], __('app.auth.api.register_ok'));
    }

    // ??? ???? ???

    /**
     * ????
     */
    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            return ApiResponse::error('INVALID_PASSWORD', __('app.auth.api.invalid_password'), 422);
        }

        // ??????
        $passwordError = $this->authService->validatePasswordStrength($data['new_password']);
        if ($passwordError) {
            return ApiResponse::validationError($passwordError);
        }

        // ??????
        if (! $this->authService->isPasswordAllowed($user, $data['new_password'])) {
            return ApiResponse::error('PASSWORD_REUSED', __('app.auth.api.password_reused'), 422);
        }

        $user->update([
            'password' => Hash::make($data['new_password']),
            'password_changed_at' => now(),
        ]);

        $this->authService->recordPasswordHistory($user, $data['new_password']);

        // ?????? token????????
        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        // ?? Token ????????? Token ??
        $this->tokenIntrospection->bumpUserVersion($user->id);

        return ApiResponse::success(null, __('app.auth.api.password_changed'));
    }

    // ??? Session ?? ???

    /**
     * ????????
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
     * ????????
     */
    public function revokeSession(int $tokenId, Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $user->tokens()->findOrFail($tokenId);

        if ((string) $token->id === (string) $user->currentAccessToken()->id) {
            return ApiResponse::error('CANNOT_REVOKE_CURRENT', __('app.auth.api.cannot_revoke_current'), 422);
        }

        $this->tokenIntrospection->revokeToken(
            (string) $token->id,
            'user_revoke_session',
            $user->id,
        );

        return ApiResponse::success(null, __('app.auth.api.session_revoked'));
    }

    // ??? Admin Session ?? ???

    /**
     * Admin ?????
     */
    public function adminSessionDashboard(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $users = \App\Models\User::where('tenant_id', $tenantId)->pluck('id');

        $totalTokens = \Laravel\Sanctum\PersonalAccessToken::whereIn('tokenable_id', $users)
            ->where('tokenable_type', \App\Models\User::class)
            ->count();

        $activeTokens = \Laravel\Sanctum\PersonalAccessToken::whereIn('tokenable_id', $users)
            ->where('tokenable_type', \App\Models\User::class)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->count();

        return ApiResponse::success([
            'total_sessions' => $totalTokens,
            'active_sessions' => $activeTokens,
            'tenant_users' => $users->count(),
        ]);
    }

    /**
     * Admin ????
     */
    public function adminSessions(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $users = \App\Models\User::where('tenant_id', $tenantId)->pluck('id');

        $query = \Laravel\Sanctum\PersonalAccessToken::whereIn('tokenable_id', $users)
            ->where('tokenable_type', \App\Models\User::class)
            ->with('tokenable:id,name,email');

        if ($request->filled('user_id')) {
            $query->where('tokenable_id', $request->user_id);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhereHas('tokenable', fn($uq) => $uq->where('name', 'like', "%{$s}%")
                      ->orWhere('email', 'like', "%{$s}%"));
            });
        }

        $perPage = min((int) $request->get('per_page', 20), 100);
        return ApiResponse::paginated($query->orderByDesc('id')->paginate($perPage));
    }

    /**
     * Admin ????
     */
    public function adminSessionDetail(int $tokenId, Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $users = \App\Models\User::where('tenant_id', $tenantId)->pluck('id');

        $token = \Laravel\Sanctum\PersonalAccessToken::whereIn('tokenable_id', $users)
            ->where('tokenable_type', \App\Models\User::class)
            ->with('tokenable:id,name,email')
            ->findOrFail($tokenId);

        return ApiResponse::success($token);
    }

    /**
     * Admin ??????
     */
    public function adminTerminateSession(int $tokenId, Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $users = \App\Models\User::where('tenant_id', $tenantId)->pluck('id');

        $token = \Laravel\Sanctum\PersonalAccessToken::whereIn('tokenable_id', $users)
            ->where('tokenable_type', \App\Models\User::class)
            ->findOrFail($tokenId);

        $token->delete();

        return ApiResponse::success(null, __('app.auth.api.session_terminated'));
    }

    /**
     * Admin ??????
     */
    public function adminBatchTerminate(Request $request): JsonResponse
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);

        $tenantId = $request->user()->tenant_id;
        $users = \App\Models\User::where('tenant_id', $tenantId)->pluck('id');

        $count = \Laravel\Sanctum\PersonalAccessToken::whereIn('tokenable_id', $users)
            ->where('tokenable_type', \App\Models\User::class)
            ->whereIn('id', $request->ids)
            ->delete();

        return ApiResponse::success(null, __('app.auth.api.sessions_terminated_n', ['count' => $count]));
    }

    /**
     * Admin ????????
     */
    public function adminTerminateUserSessions(int $userId, Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $targetUser = \App\Models\User::where('tenant_id', $tenantId)->findOrFail($userId);

        $count = \Laravel\Sanctum\PersonalAccessToken::where('tokenable_id', $targetUser->id)
            ->where('tokenable_type', \App\Models\User::class)
            ->delete();

        return ApiResponse::success(null, __('app.auth.api.sessions_terminated_user', ['name' => $targetUser->name, 'count' => $count]));
    }

    // ??? ???? ???

    /**
     * ??????
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

        return ApiResponse::success(null, __('app.auth.api.device_trusted'));

    }

    /**
     * ????????
     */
    public function trustedDevices(Request $request): JsonResponse
    {
        $devices = $this->authService->getTrustedDevices($request->user());
        return ApiResponse::success($devices);
    }

    /**
     * ??????
     */
    public function removeTrustedDevice(int $deviceId, Request $request): JsonResponse
    {
        $device = $request->user()->trustedDevices()->findOrFail($deviceId);
        $device->delete();

        return ApiResponse::success(null, __('app.auth.api.device_untrusted'));
    }

    /**
     * ????????
     */
    public function clearTrustedDevices(Request $request): JsonResponse
    {
        $request->user()->trustedDevices()->delete();

        return ApiResponse::success(null, __('app.auth.api.devices_cleared'));
    }

    /**
     * ?????????????????
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
                $data['device_name'] ?? __('app.auth.api.unknown_device'),
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

    // ??? ????? ???

    /**
     * ????????????
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
        ], __('app.auth.api.invite_generated'));
    }

    /**
     * ???????
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
     * ???????
     */
    public function inviteCodeStats(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->authService->getInviteCodeStats()
        );
    }

    // ??? ???? ???

    /**
     * ??????
     */
    public function getLegalConsents(Request $request): JsonResponse
    {
        $consents = LegalConsent::where('is_current', true)->get();
        return ApiResponse::success($consents);
    }

    /**
     * ????
     */
    public function consentToLegal(Request $request): JsonResponse
    {
        $data = $request->validate([
            'legal_consent_id' => 'required|integer|exists:legal_consents,id',
        ]);

        $consent = LegalConsent::findOrFail($data['legal_consent_id']);

        if ($consent->isConsentedBy($request->user()->id)) {
            return ApiResponse::error('ALREADY_CONSENTED', __('app.auth.api.already_consented'), 422);
        }

        $this->authService->consentTo(
            $request->user(),
            $consent->id,
            $request->ip(),
        );

        return ApiResponse::success(null, __('app.auth.api.consent_ok'));
    }

    // ??? ???? ???

    /**
     * ??????
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
        ], __('app.auth.api.deletion_submitted'));
    }

    /**
     * ??????
     */
    public function cancelDeletion(Request $request): JsonResponse
    {
        if ($this->authService->cancelDeletion($request->user())) {
            return ApiResponse::success(null, __('app.auth.api.deletion_cancelled'));
        }
        return ApiResponse::notFound(__('app.auth.api.no_pending_deletion'));
    }

    /**
     * ????????
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

    // ??? OAuth ?? ???

    /**
     * ?? OAuth ???
     */
    public function bindOAuth(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider' => 'required|string|in:wechat,google,github,qq,apple,alipay',
            'provider_id' => 'required|string',
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email',
            'avatar' => 'nullable|url',
            'metadata' => 'nullable|array',
        ]);

        $user = $request->user();

        // ????????????
        $existing = \App\Models\UserAuthProvider::where('provider', $data['provider'])
            ->where('provider_id', $data['provider_id'])
            ->first();

        if ($existing && $existing->user_id !== $user->id) {
            return ApiResponse::error('ALREADY_BOUND', __('app.auth.api.already_bound'), 422);
        }

        $authProvider = $user->authProviders()->updateOrCreate(
            ['provider' => $data['provider'], 'provider_id' => $data['provider_id']],
            [
                'avatar' => $data['avatar'] ?? null,
                'nickname' => $data['name'] ?? null,
                'metadata' => $data['metadata'] ?? null,
            ],
        );

        return ApiResponse::success($authProvider, __('app.auth.api.bind_ok'));
    }

    /**
     * ?? OAuth ??
     */
    public function unbindOAuth(int $authProviderId, Request $request): JsonResponse
    {
        try {
            $this->authService->unbindProvider($request->user(), $authProviderId);
            return ApiResponse::success(null, __('app.auth.api.unbind_ok'));
        } catch (\RuntimeException $e) {
            return ApiResponse::error('UNBIND_FAILED', $e->getMessage(), 422);
        }
    }

    /**
     * ?????? OAuth ?????
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

        $hasPassword = ! empty($user->password);
        $hasPhone = ! empty($user->phone);

        return ApiResponse::success([
            'oauth_providers' => $providers,
            'has_password' => $hasPassword,
            'has_phone' => $hasPhone,
        ]);
    }

    /**
     * ????? OAuth ???????????
     */
    public function availableOauthProviders(): JsonResponse
    {
        $settings = \App\Models\SiteSetting::where('group', 'oauth')
            ->where('key', 'like', '%_enabled')
            ->get()
            ->keyBy('key');

        $config = config('oauth.providers', []);
        $oauthRedirect = app(\App\Services\OAuthRedirectService::class);
        $available = [];

        foreach ($config as $key => $cfg) {
            // ????????????????????
            if (! in_array($key, \App\Services\OAuthRedirectService::SUPPORTED, true)) {
                continue;
            }

            $dbKey = "oauth_{$key}_enabled";
            $enabled = isset($settings[$dbKey])
                ? $settings[$dbKey]->value === '1'
                : ($cfg['enabled'] ?? false);

            if ($enabled) {
                $available[] = [
                    'provider' => $key,
                    'name' => $cfg['name'] ?? $key,
                    'icon' => $cfg['icon'] ?? null,
                    'color' => $cfg['color'] ?? null,
                    'configured' => $oauthRedirect->isConfigured($key),
                ];
            }
        }

        return ApiResponse::success($available);
    }

    /**
     * ?? OAuth ?? URL?JSON????? / ??????
     * GET /api/oauth/authorize-url/{provider}?intent=login|bind&return_to=...
     */
    public function oauthAuthorizeUrl(string $provider, Request $request): JsonResponse
    {
        try {
            // Bearer Token ????????????????????
            if (! $request->user() && $request->bearerToken()) {
                $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($request->bearerToken());
                if ($accessToken?->tokenable) {
                    auth()->setUser($accessToken->tokenable);
                    $request->setUserResolver(fn () => $accessToken->tokenable);
                }
            }

            $intent = $request->input('intent', 'login');
            $userId = null;
            if ($intent === 'bind') {
                $user = $request->user();
                if (! $user) {
                    return ApiResponse::error('UNAUTHORIZED', __('app.auth.api.bind_login_required'), 401);
                }
                $userId = $user->id;
            }

            $result = app(\App\Services\OAuthRedirectService::class)->buildAuthorizeUrl(
                $provider,
                $intent,
                $request->input('return_to'),
                $userId,
            );

            return ApiResponse::success([
                'authorize_url' => $result['authorize_url'],
                'provider' => $provider,
                'intent' => $intent,
            ]);
        } catch (\Throwable $e) {
            return ApiResponse::error('OAUTH_NOT_CONFIGURED', $e->getMessage(), 422);
        }
    }

    /**
     * ?? OAuth ????????????????
     * GET /api/oauth/redirect/{provider}?intent=login&return_to=/build/...
     */
    public function oauthRedirect(string $provider, Request $request)
    {
        $intent = $request->input('intent', 'login');
        try {
            if ($intent === 'bind') {
                return ApiResponse::error('USE_AUTHORIZE_URL', __('app.auth.api.use_authorize_url'), 400);
            }

            $result = app(\App\Services\OAuthRedirectService::class)->buildAuthorizeUrl(
                $provider,
                'login',
                $request->input('return_to'),
                null,
            );

            return redirect()->away($result['authorize_url']);
        } catch (\Throwable $e) {
            $msg = urlencode($e->getMessage());

            return redirect('/build/login?oauth_error='.$msg);
        }
    }

    /**
     * OAuth ????? ? ??/?? ? ?? SPA
     * GET /api/oauth/callback/{provider}
     */
    public function oauthCallback(string $provider, Request $request)
    {
        try {
            $code = (string) $request->input('code', '');
            $state = (string) $request->input('state', '');
            if ($code === '' || $state === '') {
                throw new \RuntimeException(__('app.auth.api.oauth_missing_code'));
            }

            $profile = app(\App\Services\OAuthRedirectService::class)->handleCallback($provider, $code, $state);
            $intent = $profile['intent'] ?? 'login';
            $returnTo = $profile['return_to'] ?? null;

            if ($intent === 'bind') {
                $userId = $profile['user_id'] ?? null;
                $user = $userId ? User::find($userId) : null;
                if (! $user) {
                    throw new \RuntimeException(__('app.auth.api.oauth_bind_session_lost'));
                }

                $existing = \App\Models\UserAuthProvider::where('provider', $profile['provider'])
                    ->where('provider_id', $profile['provider_id'])
                    ->first();
                if ($existing && $existing->user_id !== $user->id) {
                    throw new \RuntimeException(__('app.auth.api.already_bound'));
                }

                $user->authProviders()->updateOrCreate(
                    ['provider' => $profile['provider'], 'provider_id' => $profile['provider_id']],
                    [
                        'avatar' => $profile['avatar'] ?? null,
                        'nickname' => $profile['name'] ?? null,
                        'metadata' => null,
                    ],
                );

                $target = $returnTo ?: '/build/account/binding';
                $sep = str_contains($target, '?') ? '&' : '?';

                return redirect($target.$sep.'oauth_bound=1&provider='.urlencode($provider));
            }

            $user = $this->authService->findOrCreateOAuthUser(
                $profile['provider'],
                $profile['provider_id'],
                $profile['email'] ?? '',
                $profile['name'] ?? 'User',
                $profile['avatar'] ?? null,
                null,
            );

            if ($user->status !== 'active') {
                throw new \RuntimeException(__('app.auth.api.account_disabled'));
            }

            $user->update([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ]);

            $token = $user->createToken("{$profile['provider']}-token", $user->tokenAbilities())->plainTextToken;
            $version = $this->tokenIntrospection->getCurrentUserVersion($user->id);
            $user->tokens()->latest()->first()?->update(['token_version' => $version]);

            $this->authService->recordLoginAudit(
                $user, 'login', $request->ip(), $request->userAgent(),
                $profile['provider'], true,
            );

            $target = $returnTo ?: '/build/dashboard';
            $sep = str_contains($target, '?') ? '&' : '?';

            return redirect($target.$sep.'oauth_token='.urlencode($token).'&oauth_provider='.urlencode($provider));
        } catch (\Throwable $e) {
            $msg = urlencode($e->getMessage());

            return redirect('/build/login?oauth_error='.$msg);
        }
    }

    /**
     * OAuth ??????? SDK / ?????????????
     */
    public function oauthLogin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider' => 'required|string|in:wechat,google,github,qq,apple,alipay',
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
            return ApiResponse::error('ACCOUNT_DISABLED', __('app.auth.api.account_disabled'), 403);
        }

        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        $token = $user->createToken("{$data['provider']}-token", $user->tokenAbilities())->plainTextToken;

        // ?? Token ??
        $version = $this->tokenIntrospection->getCurrentUserVersion($user->id);
        $user->tokens()->latest()->first()?->update(['token_version' => $version]);

        $this->authService->recordLoginAudit(
            $user, 'login', $request->ip(), $request->userAgent(),
            $data['provider'], true,
        );

        return ApiResponse::success([
            'user' => $this->formatUser($user),
            'token' => $token,
            'is_new_user' => $user->wasRecentlyCreated,
        ], __('app.auth.api.login_ok'));
    }

    // ??? ?????? ???

    /**
     * ??????
     */
    public function loginHistory(Request $request): JsonResponse
    {
        $logs = \App\Models\LoginAudit::where('user_id', $request->user()->id)
            ->latest()
            ->paginate($request->input('per_page', 20));

        return ApiResponse::paginated($logs);
    }

    // ??? ???? ???

    protected function formatUser(User $user): array
    {
        // ????/???????????????
        app(\Spatie\Permission\PermissionRegistrar::class)
            ->setPermissionsTeamId($user->tenant_id ?? 1);

        $data = $user->toArray();

        // ??????
        unset($data['password_history']);

        $tenants = $user->tenants()->get(['tenants.id', 'tenants.name', 'tenants.slug', 'tenants.logo']);
        $data['tenants'] = $tenants;
        $data['is_multi_tenant'] = $tenants->count() > 1;
        $data['active_tenant_id'] = $user->remember_tenant_id ?? $user->tenant_id;
        $data['has_password'] = ! empty($user->password);
        $data['has_phone'] = ! empty($user->phone);
        $data['email_verified'] = $user->email_verified_at !== null;
        $data['phone_verified'] = $user->phone_verified_at !== null;
        $data['roles'] = $user->getRoleNames();
        $data['mvp_mode_enabled'] = (string) site_setting('mvp_mode_enabled', '0') === '1';

        return $data;
    }

    /**
     * ?????????????
     */
    public function sendMagicLink(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'redirect_url' => 'nullable|url',
        ]);

        $email = $data['email'];

        // ????????????????????
        $user = User::where('email', $email)->first();

        // ????
        $token = \Str::random(64);
        \App\Models\MagicLinkToken::create([
            'email' => $email,
            'token' => hash('sha256', $token),
            'expires_at' => now()->addMinutes(10),
        ]);

        // ???????????
        if ($user) {
            $loginUrl = url('/auth/magic-link/verify?token=' . $token . '&email=' . urlencode($email));
            if (!empty($data['redirect_url'])) {
                $loginUrl .= '&redirect=' . urlencode($data['redirect_url']);
            }

            \Illuminate\Support\Facades\Mail::to($email)->queue(new \App\Mail\MagicLink(
                $email,
                $token,
                $loginUrl,
            ));
        }

        return ApiResponse::success(null, __('app.auth.api.magic_link_sent'));
    }

    /**
     * ?????????
     */
    public function verifyMagicLink(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
        ]);

        $hashedToken = hash('sha256', $data['token']);

        $record = \App\Models\MagicLinkToken::where('email', $data['email'])
            ->where('token', $hashedToken)
            ->valid()
            ->first();

        if (!$record) {
            return ApiResponse::error('INVALID_TOKEN', __('app.auth.api.magic_link_invalid'), 400);
        }

        // ?????
        $record->update(['used' => true, 'used_at' => now()]);

        $user = User::where('email', $data['email'])->first();

        if (!$user || $user->status !== 'active') {
            return ApiResponse::error('ACCOUNT_DISABLED', __('app.auth.api.account_missing_or_disabled'), 403);
        }

        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        $token = $user->createToken('magic-link-token', $user->tokenAbilities())->plainTextToken;

        $this->authService->recordLoginAudit(
            $user, 'magic_link_login', $request->ip(), $request->userAgent(),
            'magic_link', true,
        );

        return ApiResponse::success([
            'user' => $this->formatUser($user),
            'token' => $token,
        ], __('app.auth.api.login_ok'));
    }

    /**
     * ?????????PC??
     */
    public function createQrSession(Request $request): JsonResponse
    {
        $sessionId = \Str::random(40);

        $session = \App\Models\QrLoginSession::create([
            'session_id' => $sessionId,
            'status' => 'pending',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'expires_at' => now()->addMinutes(5),
        ]);

        return ApiResponse::success([
            'session_id' => $session->session_id,
            'expires_at' => $session->expires_at,
        ], __('app.auth.api.qr_session_created'));
    }

    /**
     * ?????????PC????
     */
    public function pollQrSession(string $sessionId, Request $request): JsonResponse
    {
        $session = \App\Models\QrLoginSession::where('session_id', $sessionId)->first();

        if (!$session) {
            return ApiResponse::error('SESSION_NOT_FOUND', __('app.auth.api.session_not_found'), 404);
        }

        if ($session->status === 'expired' || $session->expires_at < now()) {
            $session->update(['status' => 'expired']);
            return ApiResponse::error('SESSION_EXPIRED', __('app.auth.api.qr_expired'), 410);
        }

        if ($session->status === 'confirmed' && $session->user_id) {
            $user = \App\Models\User::find($session->user_id);
            if (!$user || $user->status !== 'active') {
                return ApiResponse::error('ACCOUNT_DISABLED', __('app.auth.api.account_disabled'), 403);
            }

            $token = $user->createToken('qr-login-token', $user->tokenAbilities())->plainTextToken;

            $this->authService->recordLoginAudit(
                $user, 'qr_login', $request->ip(), $request->userAgent(),
                'qr_code', true,
            );

            return ApiResponse::success([
                'user' => $this->formatUser($user),
                'token' => $token,
            ], __('app.auth.api.qr_login_ok'));
        }

        return ApiResponse::success([
            'status' => $session->status,
        ]);
    }

    /**
     * ????????????
     */
    public function confirmQrSession(Request $request): JsonResponse
    {
        $data = $request->validate([
            'session_id' => 'required|string',
        ]);

        $session = \App\Models\QrLoginSession::where('session_id', $data['session_id'])
            ->pending()
            ->first();

        if (!$session) {
            return ApiResponse::error('SESSION_INVALID', __('app.auth.api.qr_invalid'), 400);
        }

        $user = $request->user();

        $session->update([
            'status' => 'confirmed',
            'user_id' => $user->id,
            'confirmed_at' => now(),
            'confirmed_token' => \Str::random(64),
        ]);

        return ApiResponse::success(null, __('app.auth.api.qr_confirm_ok'));
    }

    /**
     * Passkey/WebAuthn � ???????????????
     */
    public function webauthnRegisterOptions(Request $request): JsonResponse
    {
        $user = $request->user();
        $challenge = \Str::random(32);

        // ????
        \App\Models\WebauthnChallenge::create([
            'challenge' => hash('sha256', $challenge),
            'type' => 'registration',
            'user_id' => $user->id,
            'expires_at' => now()->addMinutes(5),
        ]);

        // ??????????ID????????
        $excludeCredentials = \App\Models\WebauthnCredential::where('user_id', $user->id)
            ->where('is_active', true)
            ->pluck('credential_id')
            ->map(fn($id) => ['id' => base64_encode($id), 'type' => 'public-key'])
            ->toArray();

        return ApiResponse::success([
            'challenge' => base64_encode($challenge),
            'rp' => [
                'name' => config('app.name', 'HWT License'),
                'id' => $this->resolveWebauthnRpId($request),
            ],
            'user' => [
                'id' => base64_encode((string) $user->id),
                'name' => $user->email,
                'displayName' => $user->name,
            ],
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],  // ES256
                ['type' => 'public-key', 'alg' => -257], // RS256
            ],
            'timeout' => 300000,
            'attestation' => 'none',
            'excludeCredentials' => $excludeCredentials,
        ]);
    }

    /**
     * Passkey/WebAuthn � ?????????
     */
    public function webauthnRegisterVerify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => 'required|string',
            'rawId' => 'required|string',
            'response' => 'required|array',
            'response.clientDataJSON' => 'required|string',
            'response.attestationObject' => 'required|string',
            'response.transports' => 'nullable|array',
            'device_name' => 'nullable|string|max:255',
        ]);

        $user = $request->user();

        // ????
        $clientData = json_decode(base64_decode($data['response']['clientDataJSON']), true);
        if (!$clientData || !isset($clientData['challenge'])) {
            return ApiResponse::error('INVALID_CLIENT_DATA', __('app.auth.api.invalid_client_data'), 400);
        }

        $receivedChallenge = base64_decode($clientData['challenge']);
        $hashedChallenge = hash('sha256', $receivedChallenge);

        $storedChallenge = \App\Models\WebauthnChallenge::where('challenge', $hashedChallenge)
            ->where('type', 'registration')
            ->where('user_id', $user->id)
            ->where('expires_at', '>', now())
            ->first();

        if (!$storedChallenge) {
            return ApiResponse::error('INVALID_CHALLENGE', __('app.auth.api.invalid_challenge'), 400);
        }

        // ????????
        $storedChallenge->delete();

        // ?????????
        $existing = \App\Models\WebauthnCredential::where('credential_id', $data['id'])->first();
        if ($existing) {
            return ApiResponse::error('CREDENTIAL_EXISTS', __('app.auth.api.credential_exists'), 409);
        }

        // ????
        $credential = \App\Models\WebauthnCredential::create([
            'user_id' => $user->id,
            'credential_id' => $data['id'],
            'public_key' => $data['response']['attestationObject'], // ????????
            'type' => 'public-key',
            'transport' => json_encode($data['response']['transports'] ?? []),
            'device_name' => $data['device_name'] ?? null,
        ]);

        return ApiResponse::success([
            'credential_id' => $credential->id,
        ], __('app.auth.api.passkey_registered'));
    }

    /**
     * Passkey/WebAuthn � ????????????
     */
    public function webauthnLoginOptions(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'nullable|email',
        ]);

        $challenge = \Str::random(32);

        \App\Models\WebauthnChallenge::create([
            'challenge' => hash('sha256', $challenge),
            'type' => 'authentication',
            'expires_at' => now()->addMinutes(5),
        ]);

        $allowCredentials = [];
        if (!empty($data['email'])) {
            $user = \App\Models\User::where('email', $data['email'])->first();
            if ($user) {
                $allowCredentials = \App\Models\WebauthnCredential::where('user_id', $user->id)
                    ->where('is_active', true)
                    ->get()
                    ->map(fn($c) => [
                        'id' => base64_encode($c->credential_id),
                        'type' => 'public-key',
                        'transports' => json_decode($c->transport ?? '[]') ?: ['internal'],
                    ])
                    ->toArray();
            }
        }

        return ApiResponse::success([
            'challenge' => base64_encode($challenge),
            'timeout' => 300000,
            'rpId' => $this->resolveWebauthnRpId($request),
            'allowCredentials' => $allowCredentials,
            'userVerification' => 'preferred',
        ]);
    }

    /**
     * Passkey/WebAuthn � ?????????
     */
    public function webauthnLoginVerify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => 'required|string',
            'rawId' => 'required|string',
            'response' => 'required|array',
            'response.clientDataJSON' => 'required|string',
            'response.authenticatorData' => 'required|string',
            'response.signature' => 'required|string',
            'response.userHandle' => 'nullable|string',
        ]);

        // ????
        $clientData = json_decode(base64_decode($data['response']['clientDataJSON']), true);
        if (!$clientData || !isset($clientData['challenge'])) {
            return ApiResponse::error('INVALID_CLIENT_DATA', __('app.auth.api.invalid_client_data'), 400);
        }

        $receivedChallenge = base64_decode($clientData['challenge']);
        $hashedChallenge = hash('sha256', $receivedChallenge);

        $storedChallenge = \App\Models\WebauthnChallenge::where('challenge', $hashedChallenge)
            ->where('type', 'authentication')
            ->where('expires_at', '>', now())
            ->first();

        if (!$storedChallenge) {
            return ApiResponse::error('INVALID_CHALLENGE', __('app.auth.api.invalid_challenge'), 400);
        }

        $storedChallenge->delete();

        // ????
        $credential = \App\Models\WebauthnCredential::where('credential_id', $data['id'])
            ->where('is_active', true)
            ->first();

        if (!$credential) {
            return ApiResponse::error('CREDENTIAL_NOT_FOUND', __('app.auth.api.credential_not_found'), 404);
        }

        $user = $credential->user;
        if (!$user || $user->status !== 'active') {
            return ApiResponse::error('ACCOUNT_DISABLED', __('app.auth.api.account_disabled'), 403);
        }

        // ?????
        $credential->update([
            'counter' => $credential->counter + 1,
            'last_used_at' => now(),
        ]);

        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        $token = $user->createToken('passkey-token', $user->tokenAbilities())->plainTextToken;

        $this->authService->recordLoginAudit(
            $user, 'passkey_login', $request->ip(), $request->userAgent(),
            'passkey', true,
        );

        return ApiResponse::success([
            'user' => $this->formatUser($user),
            'token' => $token,
        ], __('app.auth.api.login_ok'));
    }

    /**
     * ?????Passkey????
     */
    public function webauthnCredentials(Request $request): JsonResponse
    {
        $credentials = \App\Models\WebauthnCredential::where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->get(['id', 'device_name', 'counter', 'last_used_at', 'created_at']);

        return ApiResponse::success($credentials);
    }

    /**
     * ??Passkey??
     */
    public function webauthnDeleteCredential(int $credentialId, Request $request): JsonResponse
    {
        $credential = \App\Models\WebauthnCredential::where('id', $credentialId)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$credential) {
            return ApiResponse::error('NOT_FOUND', __('app.auth.api.credential_not_found'), 404);
        }

        $credential->update(['is_active' => false]);

        return ApiResponse::success(null, __('app.auth.api.passkey_deleted'));
    }

    // ??? ???? ???

    /**
     * ??/????
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,gif,webp|max:2048',
        ]);

        $user = $request->user();
        $file = $request->file('avatar');

        // ?????
        if ($user->avatar && !str_starts_with($user->avatar, 'http')) {
            $oldPath = public_path('storage/' . $user->avatar);
            if (file_exists($oldPath)) {
                @unlink($oldPath);
            }
        }

        $path = $file->store('avatars/' . $user->id, 'public');

        if (!$path) {
            return ApiResponse::error(__('app.auth.api.avatar_upload_fail'), 500);
        }

        $user->update(['avatar' => $path]);

        return ApiResponse::success([
            'avatar' => $user->avatar,
            'avatar_url' => $user->avatar_url,
        ], __('app.auth.api.avatar_updated'));
    }

    /**
     * ??????????
     */
    public function deleteAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->avatar && !str_starts_with($user->avatar, 'http')) {
            $oldPath = public_path('storage/' . $user->avatar);
            if (file_exists($oldPath)) {
                @unlink($oldPath);
            }
        }

        $user->update(['avatar' => null]);

        return ApiResponse::success([
            'avatar_url' => $user->avatar_url,
        ], __('app.auth.api.avatar_reset'));
    }

    /**
     * ????????? + ?????
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'phone' => 'sometimes|nullable|string|max:20',
        ]);

        $request->user()->update($validated);

        return ApiResponse::success($request->user()->only(['id', 'name', 'email', 'phone', 'avatar', 'avatar_url']), __('app.auth.api.profile_updated'));
    }
}
