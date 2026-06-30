<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApiKey extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'key_id',
        'name',
        'description',
        'secret',
        'permissions',        // read-only | read-write | admin
        'tier',               // free | standard | enterprise | custom
        'allowed_endpoints',
        'allowed_methods',
        'endpoint_permissions',
        'rate_limit',
        'usage_quota',
        'usage_count',
        'daily_quota',
        'daily_usage',
        'daily_reset_at',
        'allowed_ip',         // single IP (legacy)
        'allowed_ips',        // multiple IPs
        'allowed_referrers',
        'tags',
        'metadata',
        'is_active',
        'last_used_at',
        'rotated_at',
        'expires_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'allowed_endpoints' => 'array',
            'allowed_ips' => 'array',
            'allowed_referrers' => 'array',
            'tags' => 'array',
            'metadata' => 'array',
            'endpoint_permissions' => 'array',
            'rate_limit' => 'integer',
            'usage_quota' => 'integer',
            'usage_count' => 'integer',
            'daily_quota' => 'integer',
            'daily_usage' => 'integer',
            'daily_reset_at' => 'datetime',
            'last_used_at' => 'datetime',
            'rotated_at' => 'datetime',
            'expires_at' => 'datetime',
            'created_by' => 'integer',
        ];
    }

    const PERMISSIONS = [
        'read-only' => '只读 — 仅可调用 GET 请求',
        'read-write' => '读写 — 可调用所有 API（默认）',
        'admin' => '管理员 — 完全控制，含密钥管理',
    ];

    const TIERS = [
        'free' => '免费版 — 基础限流和配额',
        'standard' => '标准版 — 适中限流和配额',
        'enterprise' => '企业版 — 高限流和配额，IP白名单',
        'custom' => '自定义 — 完全自定义配置',
    ];

    const TIER_LIMITS = [
        'free' => [
            'max_keys' => 5,
            'rate_limit' => 30,
            'usage_quota' => 10000,
            'daily_quota' => 1000,
            'max_endpoints' => 5,
            'allowed_ips' => false,
            'allowed_referrers' => false,
        ],
        'standard' => [
            'max_keys' => 20,
            'rate_limit' => 300,
            'usage_quota' => 100000,
            'daily_quota' => 10000,
            'max_endpoints' => 20,
            'allowed_ips' => true,
            'allowed_referrers' => true,
        ],
        'enterprise' => [
            'max_keys' => 100,
            'rate_limit' => 3000,
            'usage_quota' => null, // unlimited
            'daily_quota' => 100000,
            'max_endpoints' => null, // unlimited
            'allowed_ips' => true,
            'allowed_referrers' => true,
        ],
        'custom' => [
            'max_keys' => 100,
            'rate_limit' => null,
            'usage_quota' => null,
            'daily_quota' => null,
            'max_endpoints' => null,
            'allowed_ips' => true,
            'allowed_referrers' => true,
        ],
    ];

    const PERMISSION_LEVEL_MAP = [
        'read-only' => 1,
        'read-write' => 2,
        'admin' => 3,
    ];

    // ─── 层级方法 ──────────────────────────────────────────

    /**
     * 当前权限级别数值
     */
    public function permissionLevel(): int
    {
        return self::PERMISSION_LEVEL_MAP[$this->permissions] ?? 1;
    }

    /**
     * 检查权限是否满足要求
     */
    public function hasMinimumPermission(string $required): bool
    {
        $levels = self::PERMISSION_LEVEL_MAP;
        return ($levels[$this->permissions] ?? 1) >= ($levels[$required] ?? 1);
    }

    /**
     * 获取当前等级的默认限制配置
     */
    public function tierLimits(): array
    {
        return self::TIER_LIMITS[$this->tier] ?? self::TIER_LIMITS['standard'];
    }

    /**
     * 检查是否超过当前等级的最大密钥数
     */
    public static function canCreateForTier(string $tier, int $tenantId): bool
    {
        $limits = self::TIER_LIMITS[$tier] ?? self::TIER_LIMITS['standard'];
        if ($limits['max_keys'] === null) {
            return true;
        }
        $count = self::where('tenant_id', $tenantId)->count();
        return $count < $limits['max_keys'];
    }

    // ─── HTTP 方法权限 ──────────────────────────────────────

    // 预定义的 SDK 端点元数据
    const SDK_ENDPOINTS = [
        [
            'endpoint' => 'activate',
            'methods' => ['POST'],
            'path' => '/api/activate',
            'description' => '激活 License',
            'required_permission' => 'read-write',
        ],
        [
            'endpoint' => 'validate',
            'methods' => ['GET'],
            'path' => '/api/validate',
            'description' => '验证 License 有效性',
            'required_permission' => 'read-only',
        ],
        [
            'endpoint' => 'revoke',
            'methods' => ['POST'],
            'path' => '/api/revoke',
            'description' => '吊销 License',
            'required_permission' => 'admin',
        ],
        [
            'endpoint' => 'check',
            'methods' => ['GET'],
            'path' => '/api/check',
            'description' => '检查 License 状态',
            'required_permission' => 'read-only',
        ],
    ];

    /**
     * 判断是否有权限执行指定 HTTP 方法
     */
    public function canMethod(string $method): bool
    {
        // admin 级别不受方法限制
        if ($this->permissions === 'admin') {
            return true;
        }

        // 检查 allowed_methods 限制
        if (! empty($this->allowed_methods)) {
            $methods = is_array($this->allowed_methods)
                ? $this->allowed_methods
                : explode(',', $this->allowed_methods);
            return in_array(strtoupper($method), array_map('strtoupper', $methods));
        }

        // 回退到 permissions 级别的方法限制
        return match ($this->permissions) {
            'read-write' => true,
            'read-only' => in_array(strtoupper($method), ['GET', 'HEAD', 'OPTIONS']),
            default => false,
        };
    }

    /**
     * 判断是否有权限访问指定端点
     */
    public function canAccess(string $path): bool
    {
        $allowed = $this->allowed_endpoints;

        if (empty($allowed)) {
            return true; // 未限制端点
        }

        foreach ($allowed as $pattern) {
            $regex = str_replace(['*', '/', '.'], ['.*', '\/', '\.'], $pattern);
            if (preg_match('/^' . $regex . '$/i', $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 细粒度端点权限检查：验证是否允许以指定 HTTP 方法访问指定端点
     * 优先检查 endpoint_permissions，其次检查 allowed_endpoints
     */
    public function canAccessEndpoint(string $endpoint, string $method): bool
    {
        // admin 级别不受端点限制
        if ($this->permissions === 'admin') {
            return true;
        }

        // 检查 endpoint_permissions 细粒度配置
        if (! empty($this->endpoint_permissions)) {
            $allowedMethods = $this->endpoint_permissions[$endpoint] ?? [];
            if (empty($allowedMethods)) {
                return false; // 此端点未在细粒度权限中允许
            }
            return in_array(strtoupper($method), array_map('strtoupper', $allowedMethods));
        }

        // 回退到 allowed_endpoints 宽泛匹配
        if (! empty($this->allowed_endpoints)) {
            foreach ($this->allowed_endpoints as $pattern) {
                if (str_contains($pattern, $endpoint)) {
                    // 端点匹配上了，再用方法权限校验
                    return $this->canMethod($method);
                }
            }
            return false;
        }

        // 未设置端点限制，默认允许
        return true;
    }

    /**
     * 获取此 Key 允许的端点及方法列表（供 SDK 自适应）
     */
    public function getAllowedEndpointsList(): array
    {
        if (! empty($this->endpoint_permissions)) {
            $result = [];
            foreach ($this->endpoint_permissions as $endpoint => $methods) {
                $result[] = [
                    'endpoint' => $endpoint,
                    'methods' => $methods,
                ];
            }
            return $result;
        }

        // 从 allowed_endpoints 模式推断
        if (! empty($this->allowed_endpoints)) {
            return array_map(fn ($p) => [
                'endpoint' => $p,
                'methods' => $this->allowed_methods ?? ['GET'],
            ], $this->allowed_endpoints);
        }

        // 未限制，返回所有 SDK 端点
        return array_map(fn ($ep) => [
            'endpoint' => $ep['endpoint'],
            'methods' => $ep['methods'],
        ], self::SDK_ENDPOINTS);
    }

    // ─── 配额检查 ──────────────────────────────────────────

    /**
     * 检查总配额是否耗尽
     */
    public function hasQuota(): bool
    {
        if ($this->usage_quota === null) {
            return true;
        }
        return $this->usage_count < $this->usage_quota;
    }

    /**
     * 检查每日配额
     */
    public function hasDailyQuota(): bool
    {
        if ($this->daily_quota === null) {
            return true;
        }
        $this->resetDailyUsageIfNeeded();
        return $this->daily_usage < $this->daily_quota;
    }

    /**
     * 如果每日配额需要重置则重置
     */
    public function resetDailyUsageIfNeeded(): void
    {
        if ($this->daily_reset_at === null || $this->daily_reset_at->isPast()) {
            $this->daily_usage = 0;
            $this->daily_reset_at = now()->endOfDay(); // 重置到当天结束
            $this->save();
        }
    }

    /**
     * 记录一次使用（包含每日配额递增）
     */
    public function recordUsage(): void
    {
        $this->increment('usage_count');
        $this->resetDailyUsageIfNeeded();
        $this->increment('daily_usage');
        $this->update(['last_used_at' => now()]);
    }

    // ─── IP/域名检查 ──────────────────────────────────────

    /**
     * 检查 IP 是否匹配（支持单IP和多IP模式）
     */
    public function matchesIp(?string $ip): bool
    {
        // 无限制
        if (empty($this->allowed_ip) && empty($this->allowed_ips)) {
            return true;
        }

        // 单 IP (legacy)
        if (! empty($this->allowed_ip) && $this->allowed_ip === $ip) {
            return true;
        }

        // 多 IP 模式
        if (! empty($this->allowed_ips) && is_array($this->allowed_ips)) {
            return in_array($ip, $this->allowed_ips);
        }

        return false;
    }

    /**
     * 检查 Referer 是否匹配
     */
    public function matchesReferrer(?string $referrer): bool
    {
        if (empty($this->allowed_referrers)) {
            return true;
        }

        foreach ($this->allowed_referrers as $pattern) {
            if (str_contains($referrer ?? '', $pattern)) {
                return true;
            }
            $regex = str_replace(['*', '.'], ['.*', '\.'], $pattern);
            if (preg_match('/^' . $regex . '$/i', $referrer ?? '')) {
                return true;
            }
        }

        return false;
    }

    // ─── 审计日志 ──────────────────────────────────────────

    /**
     * 记录审计日志
     */
    public function logAction(
        string $action,
        ?string $actorType = null,
        ?int $actorId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $remark = null,
    ): void {
        $this->auditLogs()->create([
            'tenant_id' => $this->tenant_id,
            'action' => $action,
            'actor_type' => $actorType ?? 'system',
            'actor_id' => $actorId,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'remark' => $remark,
        ]);
    }

    // ─── 关系 ──────────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(ApiKeyAuditLog::class, 'api_key_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ─── 作用域 ──────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfTier($query, string $tier)
    {
        return $query->where('tier', $tier);
    }

    public function scopeOfPermission($query, string $permission)
    {
        return $query->where('permissions', $permission);
    }

    public function scopeExpiringSoon($query, int $days = 7)
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays($days))
            ->where('expires_at', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }

    public function scopeQuotaExhausted($query)
    {
        return $query->whereNotNull('usage_quota')
            ->whereColumn('usage_count', '>=', 'usage_quota');
    }
}
