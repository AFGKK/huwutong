<?php

namespace App\Services;

use App\Models\PasswordPolicyConfig;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * 密码策略 + 账号锁定管理服务
 *
 * 职责：
 * - 读取/更新密码策略配置
 * - 验证密码强度（支持自定义策略）
 * - 密码过期检查
 * - 账号锁定/解锁管理
 */
class PasswordPolicyService
{
    const CACHE_KEY = 'password_policy_config';
    const CACHE_TTL = 3600;

    /**
     * 获取当前策略配置
     */
    public function getConfig(): PasswordPolicyConfig
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return PasswordPolicyConfig::getActive();
        });
    }

    /**
     * 更新策略配置
     */
    public function updateConfig(array $data): PasswordPolicyConfig
    {
        $config = PasswordPolicyConfig::getActive();
        $config->update($data);
        Cache::forget(self::CACHE_KEY);

        Log::info('密码策略已更新', ['policy_id' => $config->id]);

        return $config;
    }

    /**
     * 验证密码强度
     */
    public function validatePasswordStrength(string $password): ?string
    {
        $config = $this->getConfig();

        if (strlen($password) < $config->min_length) {
            return __('app.api.service_password_policy.min_length', ['min' => $config->min_length]);
        }
        if (strlen($password) > $config->max_length) {
            return __('app.api.service_password_policy.max_length', ['max' => $config->max_length]);
        }

        $checks = [];
        if ($config->require_uppercase && !preg_match('/[A-Z]/', $password)) {
            $checks[] = __('app.api.service_password_policy.uppercase');
        }
        if ($config->require_lowercase && !preg_match('/[a-z]/', $password)) {
            $checks[] = __('app.api.service_password_policy.lowercase');
        }
        if ($config->require_number && !preg_match('/[0-9]/', $password)) {
            $checks[] = __('app.api.service_password_policy.digits');
        }
        if ($config->require_special && !preg_match('/[!@#$%^&*()_+\-=\[\]{};\'",.<>?~`\\\\|\/]/', $password)) {
            $checks[] = __('app.api.service_password_policy.specials');
        }

        if (!empty($checks)) {
            return __('app.api.service_password_policy.requires_one_of') . implode('、', $checks);
        }

        return null;
    }

    /**
     * 检查密码是否允许使用（历史禁止重复）
     */
    public function isPasswordAllowed(User $user, string $newPassword): bool
    {
        $config = $this->getConfig();
        $history = $user->password_history ?? [];

        // 只检查最近 N 次
        $history = array_slice($history, 0, $config->history_count);

        foreach ($history as $oldHash) {
            if (Hash::check($newPassword, $oldHash)) {
                return false;
            }
        }
        return true;
    }

    /**
     * 检查密码是否需要过期提醒
     */
    public function isPasswordExpiringSoon(User $user): bool
    {
        $config = $this->getConfig();
        if ($config->expiry_days <= 0) {
            return false; // 永不过期
        }

        if (!$user->password_changed_at) {
            return true; // 从未改过密码
        }

        return $user->password_changed_at->addDays($config->expiry_days)->isPast();
    }

    /**
     * 检查密码是否已过期
     */
    public function isPasswordExpired(User $user): bool
    {
        $config = $this->getConfig();
        if ($config->expiry_days <= 0) {
            return false;
        }

        if (!$user->password_changed_at) {
            return true;
        }

        return $user->password_changed_at->addDays($config->expiry_days)->isPast();
    }

    /**
     * 检查账号是否被锁定
     */
    public function isAccountLocked(User $user): bool
    {
        if (!$user->locked_until) {
            return false;
        }

        if ($user->locked_until->isFuture()) {
            return true;
        }

        // 锁定时间已过，自动解锁
        $user->updateQuietly([
            'locked_until' => null,
            'login_attempts' => 0,
        ]);

        return false;
    }

    /**
     * 获取锁定剩余时间描述
     */
    public function getLockoutRemaining(User $user): ?string
    {
        if (!$user->locked_until || $user->locked_until->isPast()) {
            return null;
        }

        $minutes = now()->diffInMinutes($user->locked_until);
        if ($minutes < 1) {
            return __('app.api.service_password_policy.unlocking_soon');
        }
        return "{$minutes} 分钟后自动解锁";
    }

    /**
     * 管理员手动解锁账号
     */
    public function unlockAccount(User $user): void
    {
        $user->update([
            'locked_until' => null,
            'login_attempts' => 0,
        ]);

        Log::info('账号已被管理员解锁', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);
    }

    /**
     * 记录密码历史
     */
    public function recordPasswordHistory(User $user, string $password): void
    {
        $config = $this->getConfig();
        $history = $user->password_history ?? [];
        array_unshift($history, Hash::make($password));
        $history = array_slice($history, 0, $config->history_count);

        $user->updateQuietly(['password_history' => $history]);
    }

    /**
     * 获取所有被锁定的账号列表
     */
    public function getLockedAccounts(int $perPage = 20)
    {
        return User::where('locked_until', '>', now())
            ->orderBy('locked_until', 'desc')
            ->paginate($perPage);
    }
}
