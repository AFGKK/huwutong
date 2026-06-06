<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name', 'email', 'phone', 'password', 'tenant_id',
        'remember_tenant_id', 'status', 'last_login_at', 'last_login_ip',
        'mfa_secret', 'mfa_enabled', 'mfa_recovery_codes', 'mfa_recovery_used',
        'password_history', 'password_changed_at', 'login_attempts', 'locked_until',
        'phone_verified_at',
    ];

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

    public function deletionRequest()
    {
        return $this->hasOne(\App\Models\AccountDeletionRequest::class)
            ->where('status', 'pending');
    }
}
