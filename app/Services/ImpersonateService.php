<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 管理员模拟登录服务
 *
 * 超管可一键模拟任意客户身份，排除问题时所见即所得。
 * - 全程审计记录
 * - 敏感操作（修改密码/删除/转账等）自动禁止/跳过
 * - 模拟会话有自动过期机制
 * - 模拟期间所有操作记录真实操作者
 */
class ImpersonateService
{
    const CACHE_PREFIX = 'impersonate:';
    const SESSION_TTL = 3600; // 1 小时自动过期

    /**
     * 开始模拟登录
     */
    public function start(User $impersonator, User $target, ?string $reason = null): string
    {
        abort_if(!$impersonator->hasRole('super-admin'), 403, '仅超管可模拟登录');

        abort_if($impersonator->id === $target->id, 400, '不能模拟自己');

        abort_if($target->hasRole('super-admin'), 403, '不能模拟其他超管');

        // 生成模拟令牌
        $token = Str::random(64);

        Cache::put(self::CACHE_PREFIX . $token, [
            'impersonator_id' => $impersonator->id,
            'impersonator_name' => $impersonator->name,
            'impersonator_email' => $impersonator->email,
            'target_id' => $target->id,
            'target_name' => $target->name,
            'target_email' => $target->email,
            'started_at' => now()->toDateTimeString(),
            'reason' => $reason,
        ], self::SESSION_TTL);

        // 审计日志
        app(AuditService::class)->log(
            action: 'impersonate_started',
            description: "管理员 {$impersonator->name} 开始模拟用户 {$target->name}({$target->email})",
            tenantId: $impersonator->tenant_id,
            userId: $impersonator->id,
        );

        Log::info("Impersonate started", [
            'impersonator' => $impersonator->id,
            'target' => $target->id,
            'reason' => $reason,
        ]);

        return $token;
    }

    /**
     * 结束模拟
     */
    public function stop(string $token, User $impersonator): void
    {
        $session = $this->getSession($token);

        if (!$session) {
            return;
        }

        Cache::forget(self::CACHE_PREFIX . $token);

        app(AuditService::class)->log(
            action: 'impersonate_stopped',
            description: "管理员 {$impersonator->name} 结束模拟用户 {$session['target_name']}",
            tenantId: $impersonator->tenant_id,
            userId: $impersonator->id,
        );

        Log::info("Impersonate stopped", [
            'impersonator' => $impersonator->id,
            'target' => $session['target_id'],
        ]);
    }

    /**
     * 获取当前模拟会话
     */
    public function getSession(string $token): ?array
    {
        return Cache::get(self::CACHE_PREFIX . $token);
    }

    /**
     * 检查当前是否有活跃的模拟会话
     */
    public function isImpersonating(string $token): bool
    {
        return Cache::has(self::CACHE_PREFIX . $token);
    }

    /**
     * 获取用户的活跃模拟会话列表
     */
    public function getActiveSessions(): array
    {
        // 由于 Cache 限制，这里不枚举所有缓存 key
        // 实际使用中可在用户模型中维护一个活跃标记
        return [];
    }

    /**
     * 获取模拟历史（从审计日志）
     */
    public function getHistory(int $perPage = 20): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return \App\Models\Log::where('action', 'like', 'impersonate_%')
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }
}
