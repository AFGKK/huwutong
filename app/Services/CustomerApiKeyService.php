<?php

namespace App\Services;

use App\Models\CustomerApiKey;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class CustomerApiKeyService
{
    /** 获取用户的 API Key 列表 */
    public function getKeys(int $userId, array $params = []): LengthAwarePaginator
    {
        $query = CustomerApiKey::where('user_id', $userId);

        if (!empty($params['search'])) {
            $query->where(function ($q) use ($params) {
                $q->where('name', 'like', "%{$params['search']}%")
                  ->orWhere('key', 'like', "%{$params['search']}%");
            });
        }

        if (isset($params['is_active'])) {
            $query->where('is_active', $params['is_active']);
        }

        if (!empty($params['sort'])) {
            $dir = $params['sort'][0] === '-' ? 'desc' : 'asc';
            $col = ltrim($params['sort'], '-');
            $query->orderBy($col, $dir);
        } else {
            $query->orderByDesc('created_at');
        }

        $perPage = min((int)($params['per_page'] ?? 15), 100);
        return $query->paginate($perPage);
    }

    /** 管理端获取所有 Key */
    public function adminIndex(array $params = []): LengthAwarePaginator
    {
        $query = CustomerApiKey::with('user:id,name,email');

        if (!empty($params['user_id'])) {
            $query->where('user_id', $params['user_id']);
        }
        if (!empty($params['search'])) {
            $query->where(function ($q) use ($params) {
                $q->where('name', 'like', "%{$params['search']}%")
                  ->orWhere('prefix', 'like', "%{$params['search']}%");
            });
        }
        if (isset($params['is_active'])) {
            $query->where('is_active', $params['is_active']);
        }

        $query->orderByDesc('created_at');
        $perPage = min((int)($params['per_page'] ?? 15), 100);
        return $query->paginate($perPage);
    }

    /** 创建 API Key */
    public function create(int $userId, array $data): array
    {
        $prefix = config('api-key.api_key.prefix', 'hwt_');
        $key = CustomerApiKey::generateKey($prefix);

        $apiKey = CustomerApiKey::create([
            'user_id' => $userId,
            'tenant_id' => $data['tenant_id'] ?? null,
            'name' => $data['name'],
            'key' => hash('sha256', $key), // 存储哈希
            'prefix' => $prefix,
            'abilities' => $data['abilities'] ?? [],
            'ip_whitelist' => $data['ip_whitelist'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        // 返回明文 Key（仅此一次）
        return [
            'api_key' => $apiKey,
            'plain_text_key' => $key,
        ];
    }

    /** 更新 API Key（名称/权限/IP白名单） */
    public function update(int $userId, int $keyId, array $data): ?CustomerApiKey
    {
        $apiKey = CustomerApiKey::where('user_id', $userId)->findOrFail($keyId);

        if (isset($data['name'])) {
            $apiKey->name = $data['name'];
        }
        if (array_key_exists('abilities', $data)) {
            $apiKey->abilities = $data['abilities'];
        }
        if (array_key_exists('ip_whitelist', $data)) {
            $apiKey->ip_whitelist = $data['ip_whitelist'];
        }
        if (array_key_exists('expires_at', $data)) {
            $apiKey->expires_at = $data['expires_at'];
        }

        $apiKey->save();
        return $apiKey->fresh();
    }

    /** 删除 API Key */
    public function delete(int $userId, int $keyId): bool
    {
        return CustomerApiKey::where('user_id', $userId)
            ->where('id', $keyId)
            ->delete() > 0;
    }

    /** 切换启用/禁用 */
    public function toggle(int $userId, int $keyId): ?CustomerApiKey
    {
        $apiKey = CustomerApiKey::where('user_id', $userId)->findOrFail($keyId);
        $apiKey->is_active = !$apiKey->is_active;
        $apiKey->save();
        return $apiKey->fresh();
    }

    /** 记录使用时间 */
    public function touchLastUsed(string $key): void
    {
        CustomerApiKey::where('key', hash('sha256', $key))
            ->update(['last_used_at' => now()]);
    }

    /** 验证 Key 是否有效 */
    public function validate(string $plainKey, string $ability = null): ?CustomerApiKey
    {
        $hashed = hash('sha256', $plainKey);
        $apiKey = CustomerApiKey::where('key', $hashed)->active()->first();

        if (!$apiKey) {
            return null;
        }

        // IP 白名单检查
        if ($apiKey->ip_whitelist) {
            $ips = explode(',', $apiKey->ip_whitelist);
            $requestIp = request()->ip();
            if (!in_array($requestIp, array_map('trim', $ips))) {
                return null;
            }
        }

        // 权限检查
        if ($ability && !$apiKey->hasAbility($ability)) {
            return null;
        }

        return $apiKey;
    }

    /** 仪表盘统计 */
    public function getDashboard(int $userId): array
    {
        $keys = CustomerApiKey::where('user_id', $userId);
        $total = (clone $keys)->count();
        $active = (clone $keys)->where('is_active', true)->count();
        $expired = (clone $keys)->where('is_active', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->count();
        $recent = (clone $keys)->whereNotNull('last_used_at')
            ->where('last_used_at', '>=', now()->subDays(7))
            ->count();

        return compact('total', 'active', 'expired', 'recent');
    }

    /** 管理端统计 */
    public function adminDashboard(): array
    {
        $total = CustomerApiKey::count();
        $active = CustomerApiKey::where('is_active', true)->count();
        $usersWithKeys = CustomerApiKey::distinct('user_id')->count('user_id');
        $recentUsed = CustomerApiKey::whereNotNull('last_used_at')
            ->where('last_used_at', '>=', now()->subDays(7))
            ->count();

        $abilityStats = [];
        $abilities = config('api-key.api_key.allowed_abilities', []);
        foreach ($abilities as $key => $label) {
            $count = CustomerApiKey::whereJsonContains('abilities', $key)
                ->orWhereNull('abilities')
                ->count();
            $abilityStats[] = compact('key', 'label', 'count');
        }

        return compact('total', 'active', 'usersWithKeys', 'recentUsed', 'abilityStats');
    }
}
