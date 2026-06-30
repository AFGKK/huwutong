<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * 租户邀请记录
 *
 * M2-129 团队协作：企业管理员通过邮箱邀请员工加入租户，
 * 接受后自动创建 TenantMember 记录。
 */
class TenantInvitation extends Model
{
    protected $fillable = [
        'tenant_id',
        'email',
        'role',
        'invited_by',
        'token',
        'expires_at',
        'accepted_at',
        'declined_at',
        'status',
        'message',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * 邀请是否有效
     */
    public function isValid(): bool
    {
        return $this->status === 'pending'
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * 接受邀请
     */
    public function accept(?string $joinedAt = null): bool
    {
        if (! $this->isValid()) {
            return false;
        }

        $this->update([
            'status' => 'accepted',
            'accepted_at' => $joinedAt ?: now(),
        ]);

        return true;
    }

    /**
     * 拒绝邀请
     */
    public function decline(): bool
    {
        if ($this->status !== 'pending') {
            return false;
        }

        $this->update([
            'status' => 'declined',
            'declined_at' => now(),
        ]);

        return true;
    }

    /**
     * 标记为已过期
     */
    public function markExpired(): bool
    {
        if ($this->status !== 'pending') {
            return false;
        }

        return $this->update(['status' => 'expired']);
    }

    /**
     * 取消邀请
     */
    public function cancel(): bool
    {
        if (! in_array($this->status, ['pending'])) {
            return false;
        }

        return $this->update(['status' => 'cancelled']);
    }

    /**
     * 生成唯一邀请令牌
     */
    public static function generateToken(): string
    {
        return hash('sha256', Str::random(40) . microtime());
    }

    /**
     * 通过 token 查找有效邀请
     */
    public static function findValid(string $token): ?self
    {
        return self::where('token', $token)
            ->where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->first();
    }
}
