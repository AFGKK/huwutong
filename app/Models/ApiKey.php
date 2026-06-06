<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'key_id',
        'name',
        'secret',
        'permissions',
        'allowed_endpoints',
        'rate_limit',
        'usage_quota',
        'usage_count',
        'allowed_ip',
        'is_active',
        'last_used_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'allowed_endpoints' => 'array',
            'rate_limit' => 'integer',
            'usage_quota' => 'integer',
            'usage_count' => 'integer',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    const PERMISSIONS = [
        'read-only' => '只读 — 仅可调用 GET 请求',
        'read-write' => '读写 — 可调用所有 API（默认）',
        'admin' => '管理员 — 完全控制，含密钥管理',
    ];

    /**
     * 判断是否有权限执行指定 HTTP 方法
     */
    public function canMethod(string $method): bool
    {
        return match ($this->permissions) {
            'admin' => true,
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
            $regex = str_replace(['*', '/'], ['.*', '\/'], $pattern);
            if (preg_match('/^' . $regex . '$/', $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 检查配额是否耗尽
     */
    public function hasQuota(): bool
    {
        if ($this->usage_quota === null) {
            return true;
        }

        return $this->usage_count < $this->usage_quota;
    }

    /**
     * 检查 IP 是否匹配
     */
    public function matchesIp(?string $ip): bool
    {
        if (empty($this->allowed_ip)) {
            return true;
        }

        return $this->allowed_ip === $ip;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
