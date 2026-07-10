<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @mixin IdeHelperCiCdToken
 */
class CiCdToken extends Model
{
    protected $table = 'ci_cd_tokens';

    protected $fillable = [
        'tenant_id', 'user_id', 'name', 'token', 'description',
        'scopes', 'allowed_license_ids',
        'allowed_ip_range', 'max_uses', 'use_count',
        'status', 'expires_at', 'last_used_at',
        'revoked_at', 'revoked_reason',
    ];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'allowed_license_ids' => 'array',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
            'use_count' => 'integer',
            'max_uses' => 'integer',
        ];
    }

    const SCOPES = [
        'license_read'     => '读取 License 信息',
        'license_write'    => '创建/更新 License',
        'license_activate' => '激活 License',
        'all'              => '完全访问',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(CiCdUsageLog::class, 'ci_cd_token_id');
    }

    /**
     * 生成唯一令牌
     */
    public static function generateToken(): string
    {
        return 'hwt_ci_' . Str::random(60);
    }

    /**
     * 校验令牌是否可用
     */
    public function isValid(): bool
    {
        if ($this->status !== 'active') return false;
        if ($this->expires_at && $this->expires_at->isPast()) return false;
        if ($this->max_uses && $this->use_count >= $this->max_uses) return false;
        return true;
    }

    /**
     * 检查是否有指定作用域
     */
    public function hasScope(string $scope): bool
    {
        $scopes = $this->scopes ?? [];
        return in_array('all', $scopes) || in_array($scope, $scopes);
    }
}
