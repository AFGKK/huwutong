<?php

namespace App\Services;

use App\Models\TokenBlacklist;
use App\Models\User;
use App\Models\UserTokenVersion;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Token 内省与吊销服务
 *
 * 提供 OAuth2 Token Introspection (RFC 7662) 兼容的能力：
 * - 实时检查 Token 是否被吊销（Cache + DB 双重检查）
 * - Token 版本号机制（密码修改/权限变更时批量失效）
 * - 内省端点格式输出
 */
class TokenIntrospectionService
{
    const BLACKLIST_CACHE_TTL = 86400; // 黑名单缓存 24 小时
    const BLACKLIST_CACHE_PREFIX = 'token_blacklist_';
    const VERSION_CACHE_PREFIX = 'token_user_version_';

    /**
     * 内省 Token
     *
     * @return array{active: bool, token_id?: string, user_id?: int, ...}
     */
    public function introspect(string $token): array
    {
        $accessToken = PersonalAccessToken::findToken($token);

        if (! $accessToken) {
            return [
                'active' => false,
                'error' => 'token_not_found',
            ];
        }

        $tokenId = (string) $accessToken->getKey();
        $userId = $accessToken->tokenable_id;

        // 1. 检查黑名单
        if ($this->isBlacklisted($tokenId)) {
            return [
                'active' => false,
                'token_id' => $tokenId,
                'error' => 'token_revoked',
            ];
        }

        // 2. 检查版本号
        if (! $this->checkTokenVersion($tokenId, $userId)) {
            return [
                'active' => false,
                'token_id' => $tokenId,
                'error' => 'token_version_expired',
            ];
        }

        // 3. 用户状态检查
        $user = User::find($userId);
        if (! $user || $user->status !== 'active') {
            return [
                'active' => false,
                'token_id' => $tokenId,
                'error' => 'user_inactive',
            ];
        }

        // 4. 清理过期缓存的黑名单条目（仅当 token 有效时做后台清理）
        $this->maybeCleanExpiredCache();

        // 返回内省结果
        return [
            'active' => true,
            'token_id' => $tokenId,
            'token_type' => 'Bearer',
            'user_id' => $userId,
            'user_status' => $user->status,
            'abilities' => $accessToken->abilities ?? ['*'],
            'exp' => $accessToken->expires_at?->timestamp,
            'iat' => $accessToken->created_at?->timestamp,
            'client_id' => $accessToken->name ?? 'api',
        ];
    }

    /**
     * 吊销单个 Token
     */
    public function revokeToken(string $tokenId, string $reason = 'revoked', ?int $userId = null): void
    {
        // 持久化到黑名单表
        TokenBlacklist::firstOrCreate([
            'token_id' => $tokenId,
        ], [
            'user_id' => $userId,
            'reason' => $reason,
            'revoked_at' => now(),
        ]);

        // 写入缓存（快速检查）
        $cacheKey = self::BLACKLIST_CACHE_PREFIX . $tokenId;
        Cache::put($cacheKey, true, now()->addSeconds(self::BLACKLIST_CACHE_TTL));

        // 从 personal_access_tokens 删除（可选——仍保留黑名单记录用于审计）
        PersonalAccessToken::where('id', $tokenId)->delete();
    }

    /**
     * 吊销用户的所有 Token
     */
    public function revokeAllUserTokens(int $userId, string $reason = 'logout_all'): int
    {
        $tokens = PersonalAccessToken::where('tokenable_id', $userId)
            ->where('tokenable_type', get_class(new User))
            ->get();

        $count = 0;
        foreach ($tokens as $token) {
            $this->revokeToken((string) $token->getKey(), $reason, $userId);
            $count++;
        }

        // 递增版本号，确保后续新 token 请求使用新版本
        UserTokenVersion::bumpVersion($userId);

        return $count;
    }

    /**
     * 递增用户 Token 版本号（密码修改/权限变更后调用）
     */
    public function bumpUserVersion(int $userId): int
    {
        $version = UserTokenVersion::bumpVersion($userId);

        // 使缓存失效
        $cacheKey = self::VERSION_CACHE_PREFIX . $userId;
        Cache::forget($cacheKey);

        return $version;
    }

    /**
     * 获取用户当前 Token 版本
     */
    public function getCurrentUserVersion(int $userId): int
    {
        $cacheKey = self::VERSION_CACHE_PREFIX . $userId;

        return Cache::remember($cacheKey, 3600, function () use ($userId) {
            return UserTokenVersion::getCurrentVersion($userId);
        });
    }

    /**
     * 检查 Token 版本
     */
    public function checkTokenVersion(string $tokenId, int $userId): bool
    {
        $currentVersion = $this->getCurrentUserVersion($userId);

        $token = PersonalAccessToken::find($tokenId);
        if (! $token) {
            return false;
        }

        $tokenVersion = $token->token_version ?? 1;

        return $tokenVersion >= $currentVersion;
    }

    /**
     * 检查 Token 是否在黑名单中
     */
    public function isBlacklisted(string $tokenId): bool
    {
        $cacheKey = self::BLACKLIST_CACHE_PREFIX . $tokenId;

        // 缓存检查
        if (Cache::has($cacheKey)) {
            return true;
        }

        // DB 检查（缓存未命中时）
        $blacklisted = TokenBlacklist::where('token_id', $tokenId)->exists();

        if ($blacklisted) {
            Cache::put($cacheKey, true, now()->addSeconds(self::BLACKLIST_CACHE_TTL));
        }

        return $blacklisted;
    }

    /**
     * 清理过期黑名单记录
     */
    protected function maybeCleanExpiredCache(): void
    {
        if (mt_rand(1, 100) <= 5) { // 5% 概率触发清理
            TokenBlacklist::where('revoked_at', '<', now()->subDays(30))->delete();
        }
    }
}
