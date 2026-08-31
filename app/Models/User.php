<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @mixin IdeHelperUser
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name', 'email', 'phone', 'avatar', 'password', 'tenant_id', 'user_type',
        'remember_tenant_id', 'status', 'last_login_at', 'last_login_ip',
        'mfa_secret', 'mfa_enabled', 'mfa_recovery_codes', 'mfa_recovery_used',
        'password_history', 'password_changed_at', 'login_attempts', 'locked_until',
        'banned_at', 'banned_reason', 'banned_by',
        'phone_verified_at',
        'onboarding_completed', 'onboarding_skipped_at', 'onboarding_skip_reason', 'preferences',
        'fcm_token', 'fcm_platform', 'fcm_device_name', 'fcm_token_updated_at',
        'wechat_openid', 'wechat_unionid', 'source',
    ];

    protected $appends = ['avatar_url'];

    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar) {
            // 如果是完整 URL 直接返回，否则拼接 storage 路径
            if (str_starts_with($this->avatar, 'http')) {
                return $this->avatar;
            }
            return '/storage/' . $this->avatar;
        }
        // 无头像时返回默认头像（基于名字首字母的 SVG URL）
        return $this->defaultAvatarUrl();
    }

    public function defaultAvatarUrl(): string
    {
        $name = urlencode($this->name ?: '?');
        // 使用 ui-avatars.com 生成首字母头像（纯前端方案，无外部依赖泄漏）
        return "https://ui-avatars.com/api/?name={$name}&background=2563eb&color=fff&size=128";
    }

    protected $hidden = [
        'password', 'remember_token', 'password_history',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'locked_until' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'mfa_enabled' => 'boolean',
            'mfa_recovery_codes' => 'array',
            'mfa_recovery_used' => 'array',
            'remember_tenant_id' => 'integer',
            'password_history' => 'array',
            'login_attempts' => 'integer',
            'agent_status_changed_at' => 'datetime',
            'max_concurrent_chats' => 'integer',
            'onboarding_completed' => 'boolean',
            'onboarding_skipped_at' => 'datetime',
            'preferences' => 'array',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * 用户有权限访问的租户列表（多租户支持）
     * 通过 tenant_members 关联表定义
     */
    public function tenants(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_members')
            ->withPivot('role', 'status')
            ->wherePivot('status', 'active')
            ->withTimestamps();
    }

    public function customer()
    {
        return $this->hasOne(Customer::class);
    }

    public function authProviders()
    {
        return $this->hasMany(UserAuthProvider::class);
    }

    public function tenantMembers()
    {
        return $this->hasMany(TenantMember::class);
    }

    public function earningsAccounts()
    {
        return $this->hasMany(EarningsAccount::class);
    }

    public function logs()
    {
        return $this->hasMany(Log::class);
    }

    public function ssoConnections()
    {
        return $this->hasMany(\App\Models\SsoConnection::class);
    }

    public function trustedDevices()
    {
        return $this->hasMany(\App\Models\TrustedDevice::class);
    }

    public function emailVerifications()
    {
        return $this->hasMany(\App\Models\EmailVerification::class);
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function assignedTickets()
    {
        return $this->hasMany(Ticket::class, 'assigned_to');
    }

    public function activeHandoffs()
    {
        return $this->hasMany(HandoffRequest::class, 'assigned_to');
    }

    public function deletionRequest()
    {
        return $this->hasOne(\App\Models\AccountDeletionRequest::class)
            ->where('status', 'pending');
    }

    public function onboardingProgress()
    {
        return $this->hasOne(\App\Models\UserOnboardingProgress::class);
    }

    public function quickStartItems()
    {
        return $this->hasMany(\App\Models\QuickStartItem::class);
    }

    public function tutorialProgress()
    {
        return $this->hasMany(\App\Models\UserTutorialProgress::class);
    }
}
