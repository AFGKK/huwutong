<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 租户成员
 *
 * M2-129 增强：members 含 invited_via/permissions/joined_at 字段。
 * 角色体系：admin(管理员) / finance(财务) / developer(开发者) / readonly(只读)
 *
 * @mixin IdeHelperTenantMember
 */
class TenantMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'user_id', 'role',
        'invited_by', 'invited_via', 'status',
        'permissions', 'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'joined_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * 检查成员是否拥有指定角色
     */
    public function hasRole(string|array $roles): bool
    {
        return in_array($this->role, (array) $roles);
    }

    /**
     * 是否为管理员
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * 获取人类可读的角色名
     */
    public function getRoleLabelAttribute(): string
    {
        return match ($this->role) {
            'admin' => '管理员',
            'finance' => '财务',
            'developer' => '开发者',
            'readonly' => '只读',
            default => $this->role,
        };
    }

    /**
     * 获取加入方式的描述
     */
    public function getInvitedViaLabelAttribute(): string
    {
        return match ($this->invited_via) {
            'invitation' => '邀请',
            'direct_add' => '直接添加',
            'sso' => 'SSO',
            'signup' => '注册',
            default => $this->invited_via ?? '-',
        };
    }
}
