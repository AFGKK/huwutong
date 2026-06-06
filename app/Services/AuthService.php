<?php

namespace App\Services;

use App\Models\AccountDeletionRequest;
use App\Models\EmailVerification;
use App\Models\InviteCode;
use App\Models\LegalConsent;
use App\Models\LoginAudit;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Models\UserAuthProvider;
use App\Models\UserConsent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * 多方式登录注册核心服务
 *
 * 职责：
 * - 邮箱+密码注册/登录/验证/找回密码
 * - 手机+验证码登录
 * - OAuth 登录绑定
 * - 密码策略与账号锁定
 * - Session/设备管理
 * - 邀请码机制
 * - 隐私协议确认
 * - 账号注销
 */
class AuthService
{
    // ─── 密码策略 ───

    const PASSWORD_MIN_LENGTH = 8;
    const PASSWORD_MAX_LENGTH = 128;
    const PASSWORD_HISTORY_COUNT = 5; // 禁止使用最近 N 次密码
    const PASSWORD_EXPIRY_DAYS = 90;   // 密码过期天数
    const LOCKOUT_MAX_ATTEMPTS = 5;
    const LOCKOUT_DURATION_MINUTES = 15;
    const COOLING_DAYS = 7; // 账号注销冷静期

    // ─── 邮箱验证 ───

    /**
     * 生成邮箱验证码并发送
     */
    public function sendEmailVerification(User $user): EmailVerification
    {
        // 使旧的验证码失效
        EmailVerification::where('user_id', $user->id)
            ->whereNull('completed_at')
            ->update(['completed_at' => now()]);

        $token = Str::random(6); // 6 位数字验证码

        return EmailVerification::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'token' => $token,
            'expires_at' => now()->addMinutes(30),
        ]);
    }

    /**
     * 验证邮箱验证码
     */
    public function verifyEmail(User $user, string $token): bool
    {
        $verification = EmailVerification::where('user_id', $user->id)
            ->where('token', $token)
            ->whereNull('completed_at')
            ->latest()
            ->first();

        if (! $verification || ! $verification->isValid()) {
            return false;
        }

        DB::transaction(function () use ($user, $verification) {
            $user->update(['email_verified_at' => now()]);
            $verification->update(['completed_at' => now()]);
        });

        return true;
    }

    // ─── 忘记密码 / 重置密码 ───

    /**
     * 生成密码重置令牌
     */
    public function generatePasswordResetToken(string $email): ?string
    {
        $user = User::where('email', $email)->first();
        if (! $user) {
            return null;
        }

        // 删除旧令牌
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        $token = Str::random(6); // 6 位验证码

        DB::table('password_reset_tokens')->insert([
            'email' => $email,
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        return $token;
    }

    /**
     * 重置密码
     */
    public function resetPassword(string $email, string $token, string $newPassword): bool
    {
        $record = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (! $record || ! Hash::check($token, $record->token)) {
            return false;
        }

        // 检查令牌是否过期（60分钟）
        if (now()->diffInMinutes($record->created_at) > 60) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            return false;
        }

        $user = User::where('email', $email)->first();
        if (! $user) {
            return false;
        }

        // 检查密码历史
        if (! $this->isPasswordAllowed($user, $newPassword)) {
            return false;
        }

        DB::transaction(function () use ($user, $newPassword, $email) {
            $user->update([
                'password' => Hash::make($newPassword),
                'password_changed_at' => now(),
            ]);
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            $this->recordPasswordHistory($user, $newPassword);
        });

        return true;
    }

    // ─── 密码策略 ───

    /**
     * 验证密码强度
     */
    public function validatePasswordStrength(string $password): ?string
    {
        if (strlen($password) < self::PASSWORD_MIN_LENGTH) {
            return "密码至少需要 " . self::PASSWORD_MIN_LENGTH . " 位字符";
        }
        if (strlen($password) > self::PASSWORD_MAX_LENGTH) {
            return "密码不能超过 " . self::PASSWORD_MAX_LENGTH . " 位字符";
        }
        if (! preg_match('/[A-Z]/', $password)) {
            return '密码需要包含至少一个大写字母';
        }
        if (! preg_match('/[a-z]/', $password)) {
            return '密码需要包含至少一个小写字母';
        }
        if (! preg_match('/[0-9]/', $password)) {
            return '密码需要包含至少一个数字';
        }
        if (! preg_match('/[!@#$%^&*()_+\-=\[\]{};:\'",.<>?~`\\\\|\/]/', $password)) {
            return '密码需要包含至少一个特殊字符';
        }
        return null;
    }

    /**
     * 检查密码是否允许（历史禁止重复）
     */
    public function isPasswordAllowed(User $user, string $newPassword): bool
    {
        $history = $this->getPasswordHistory($user);
        foreach ($history as $oldHash) {
            if (Hash::check($newPassword, $oldHash)) {
                return false;
            }
        }
        return true;
    }

    /**
     * 记录密码历史
     */
    public function recordPasswordHistory(User $user, string $password): void
    {
        $history = $this->getPasswordHistory($user);
        array_unshift($history, Hash::make($password));
        $history = array_slice($history, 0, self::PASSWORD_HISTORY_COUNT);

        $user->updateQuietly(['password_history' => $history]);
    }

    protected function getPasswordHistory(User $user): array
    {
        return $user->password_history ?? [];
    }

    /**
     * 检查密码是否需要过期提醒
     */
    public function isPasswordExpiringSoon(User $user): bool
    {
        if (! $user->password_changed_at) {
            return true;
        }
        return $user->password_changed_at->addDays(self::PASSWORD_EXPIRY_DAYS)->isPast();
    }

    // ─── 账号锁定 ───

    /**
     * 检查账号是否被锁定
     */
    public function isAccountLocked(string $email): bool
    {
        $key = 'login_lockout:' . $email;
        return Cache::has($key);
    }

    /**
     * 记录登录失败
     */
    public function recordFailedAttempt(string $email): int
    {
        $key = 'login_attempts:' . $email;
        $attempts = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $attempts, now()->addMinutes(self::LOCKOUT_DURATION_MINUTES));

        if ($attempts >= self::LOCKOUT_MAX_ATTEMPTS) {
            Cache::put('login_lockout:' . $email, true, now()->addMinutes(self::LOCKOUT_DURATION_MINUTES));
            Log::warning('账号已锁定', ['email' => $email, 'attempts' => $attempts]);
        }

        return $attempts;
    }

    /**
     * 清除登录失败记录
     */
    public function clearFailedAttempts(string $email): void
    {
        Cache::forget('login_attempts:' . $email);
        Cache::forget('login_lockout:' . $email);
    }

    /**
     * 获取剩余锁定时间（分钟）
     */
    public function getLockoutRemainingMinutes(string $email): int
    {
        $key = 'login_lockout:' . $email;
        $ttl = Cache::get($key) ? Cache::ttl($key) : 0;
        return max(0, (int) ceil($ttl / 60));
    }

    // ─── 登录审计 ───

    /**
     * 记录登录审计
     */
    public function recordLoginAudit(
        ?User   $user,
        string  $action,
        ?string $ipAddress,
        ?string $userAgent,
        string  $provider = 'email',
        bool    $success = true,
        ?string $failureReason = null,
    ): LoginAudit {
        return LoginAudit::create([
            'user_id' => $user?->id,
            'email' => $user?->email,
            'action' => $action,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'provider' => $provider,
            'success' => $success,
            'failure_reason' => $failureReason,
        ]);
    }

    // ─── 设备信任 ───

    /**
     * 检查设备是否被信任
     */
    public function isDeviceTrusted(User $user, string $fingerprint): bool
    {
        return TrustedDevice::where('user_id', $user->id)
            ->where('device_fingerprint', $fingerprint)
            ->exists();
    }

    /**
     * 信任设备
     */
    public function trustDevice(User $user, string $fingerprint, ?string $deviceName, ?string $ip, ?string $ua): TrustedDevice
    {
        return TrustedDevice::updateOrCreate(
            ['user_id' => $user->id, 'device_fingerprint' => $fingerprint],
            [
                'device_name' => $deviceName,
                'ip_address' => $ip,
                'user_agent' => $ua,
                'trusted_at' => now(),
                'last_seen_at' => now(),
            ],
        );
    }

    /**
     * 获取用户信任的设备列表
     */
    public function getTrustedDevices(User $user)
    {
        return $user->trustedDevices()->latest('last_seen_at')->get();
    }

    // ─── 邀请码 ───

    /**
     * 批量生成邀请码
     */
    public function generateInviteCodes(int $count, int $maxUses = 1, ?string $expiresAt = null, ?string $remarks = null): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $code = InviteCode::generateCode();
            $codes[] = InviteCode::create([
                'code' => $code,
                'max_uses' => $maxUses,
                'expires_at' => $expiresAt ? now()->parse($expiresAt) : null,
                'status' => 'active',
                'remarks' => $remarks,
            ]);
        }
        return $codes;
    }

    /**
     * 验证并使用邀请码
     */
    public function consumeInviteCode(string $code): bool
    {
        $invite = InviteCode::where('code', strtoupper($code))->first();
        if (! $invite || ! $invite->isValid()) {
            return false;
        }
        return $invite->consume();
    }

    /**
     * 获取邀请码统计
     */
    public function getInviteCodeStats(): array
    {
        return [
            'total' => InviteCode::count(),
            'active' => InviteCode::where('status', 'active')->count(),
            'expired' => InviteCode::where('status', 'expired')->count(),
            'total_uses' => InviteCode::sum('used_count'),
        ];
    }

    // ─── 隐私协议 ───

    /**
     * 获取待确认的协议列表
     */
    public function getPendingConsents(User $user): array
    {
        $pending = [];
        foreach (['privacy_policy', 'terms_of_service'] as $type) {
            $current = LegalConsent::getCurrent($type);
            if ($current && ! $current->isConsentedBy($user->id)) {
                $pending[] = $current;
            }
        }
        return $pending;
    }

    /**
     * 确认协议
     */
    public function consentTo(User $user, int $legalConsentId, ?string $ipAddress): UserConsent
    {
        return UserConsent::create([
            'user_id' => $user->id,
            'legal_consent_id' => $legalConsentId,
            'ip_address' => $ipAddress,
            'consented_at' => now(),
        ]);
    }

    // ─── 账号注销 ───

    /**
     * 提交注销申请
     */
    public function requestDeletion(User $user, ?string $reason): AccountDeletionRequest
    {
        // 检查是否有待处理的申请
        $pending = AccountDeletionRequest::where('user_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if ($pending) {
            throw new \RuntimeException('已有待处理的注销申请');
        }

        return AccountDeletionRequest::create([
            'user_id' => $user->id,
            'reason' => $reason,
            'status' => 'pending',
            'cooling_until' => now()->addDays(self::COOLING_DAYS),
        ]);
    }

    /**
     * 取消注销申请
     */
    public function cancelDeletion(User $user): bool
    {
        return (bool) AccountDeletionRequest::where('user_id', $user->id)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);
    }

    /**
     * 执行账号注销（取消申请后由系统或管理员执行）
     */
    public function executeDeletion(AccountDeletionRequest $request): bool
    {
        if ($request->status !== 'pending' || ! $request->isCoolingOver()) {
            return false;
        }

        $user = $request->user;

        DB::transaction(function () use ($request, $user) {
            // 吊销所有 API Token
            $user->tokens()->delete();

            // 清除个人数据（匿名化处理以保留统计价值）
            $user->update([
                'name' => 'User_' . $user->id,
                'email' => hash('sha256', $user->email) . '@anon.deleted',
                'phone' => null,
                'password' => Hash::make(Str::random(64)),
                'status' => 'deleted',
                'email_verified_at' => null,
                'mfa_secret' => null,
                'mfa_enabled' => false,
                'mfa_recovery_codes' => null,
                'mfa_recovery_used' => null,
                'remember_tenant_id' => null,
            ]);

            $request->update([
                'status' => 'completed',
                'processed_at' => now(),
            ]);

            Log::info('账号已注销', ['user_id' => $user->id]);
        });

        return true;
    }

    /**
     * 处理所有过冷静期的注销请求（由定时任务调用）
     */
    public function processPendingDeletions(): int
    {
        $count = 0;
        $requests = AccountDeletionRequest::where('status', 'pending')
            ->where('cooling_until', '<=', now())
            ->get();

        foreach ($requests as $request) {
            try {
                if ($this->executeDeletion($request)) {
                    $count++;
                }
            } catch (\Exception $e) {
                Log::error('注销执行失败', [
                    'request_id' => $request->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }

    // ─── OAuth 绑定 ───

    /**
     * 查找或创建 OAuth 用户
     */
    public function findOrCreateOAuthUser(
        string $provider,
        string $providerId,
        string $email,
        string $name,
        ?string $avatar = null,
        ?array $metadata = null,
    ): User {
        // 先按 provider + provider_id 查找
        $authProvider = UserAuthProvider::where('provider', $provider)
            ->where('provider_id', $providerId)
            ->first();

        if ($authProvider) {
            $authProvider->update(['metadata' => $metadata]);
            return $authProvider->user;
        }

        // 再按邮箱查找
        $user = User::where('email', $email)->first();

        if (! $user) {
            // 创建新用户
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make(Str::random(32)),
                'status' => 'active',
                'email_verified_at' => now(),
            ]);
        }

        // 绑定 OAuth 提供商
        $user->authProviders()->create([
            'provider' => $provider,
            'provider_id' => $providerId,
            'avatar' => $avatar,
            'nickname' => $name,
            'metadata' => $metadata,
        ]);

        return $user;
    }

    /**
     * 解除 OAuth 绑定
     */
    public function unbindProvider(User $user, int $authProviderId): bool
    {
        $provider = $user->authProviders()->findOrFail($authProviderId);

        // 不能解除最后的绑定方式
        $emailBound = $user->password && $user->email;
        $phoneBound = $user->phone;
        $otherProviders = $user->authProviders()->where('id', '!=', $authProviderId)->count();

        if (! $emailBound && ! $phoneBound && $otherProviders === 0) {
            throw new \RuntimeException('无法解除最后的登录方式，请先绑定其他方式');
        }

        return (bool) $provider->delete();
    }
}
