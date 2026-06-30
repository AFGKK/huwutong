<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    use HasFactory;
    protected $fillable = [
        'name', 'logo', 'domain', 'subscription_plan',
        'status', 'data_region', 'branding',
        'mfa_policy', 'allowed_ips',
        // 隔离增强字段
        'quota_plan_id', 'quota_overrides', 'isolation_level',
        'allowed_origins', 'feature_flags', 'usage_metrics',
        'max_users', 'max_licenses', 'max_devices', 'max_api_keys',
        'storage_limit_mb', 'monthly_api_limit', 'data_retention_days',
        'notify_quota_at', 'quota_last_notified_at',
        'quota_check_enabled', 'over_quota_since', 'over_quota_action',
    ];

    protected function casts(): array
    {
        return [
            'branding' => 'array',
            'allowed_ips' => 'array',
            'allowed_origins' => 'array',
            'feature_flags' => 'array',
            'usage_metrics' => 'array',
            'quota_overrides' => 'array',
            'quota_last_notified_at' => 'datetime',
            'over_quota_since' => 'datetime',
            'quota_check_enabled' => 'boolean',
        ];
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function licenses()
    {
        return $this->hasMany(License::class);
    }

    public function devices()
    {
        return $this->hasMany(Device::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function members()
    {
        return $this->hasMany(TenantMember::class);
    }

    /**
     * 租户的待处理邀请
     */
    public function pendingInvitations()
    {
        return $this->hasMany(TenantInvitation::class)
            ->where('status', 'pending');
    }

    public function webhookEvents()
    {
        return $this->hasMany(WebhookEvent::class);
    }

    public function customDomains()
    {
        return $this->hasMany(\App\Models\CustomDomain::class);
    }

    public function quotaPlan()
    {
        return $this->belongsTo(\App\Models\QuotaPlan::class);
    }

    public function isolationLogs()
    {
        return $this->hasMany(\App\Models\IsolationAuditLog::class);
    }

    public function crossTenantShares()
    {
        return $this->hasMany(\App\Models\CrossTenantShare::class, 'source_tenant_id');
    }

    public function usageSnapshots()
    {
        return $this->hasMany(\App\Models\TenantUsageSnapshot::class);
    }
}
