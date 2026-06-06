<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * SLA 等级定义
 *
 * 定义不同客户等级对应的：API限流、验证性能、设备上限、客服SLA、安全合规
 */
class SlaTier extends Model
{
    protected $fillable = [
        'tenant_id', 'slug', 'name', 'description', 'priority', 'is_default',
        'api_rate_limit', 'api_burst_limit', 'api_concurrent_limit',
        'verify_rate_limit', 'verify_timeout_seconds',
        'max_active_licenses', 'max_devices_per_license',
        'sla_response_hours', 'sla_resolution_hours',
        'support_priority_queue', 'support_dedicated_manager',
        'support_phone', 'support_24_7',
        'audit_retention_days', 'require_mfa', 'allowed_ip_ranges',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'priority' => 'integer',
            'api_rate_limit' => 'integer',
            'api_burst_limit' => 'integer',
            'api_concurrent_limit' => 'integer',
            'verify_rate_limit' => 'integer',
            'verify_timeout_seconds' => 'integer',
            'max_active_licenses' => 'integer',
            'max_devices_per_license' => 'integer',
            'sla_response_hours' => 'integer',
            'sla_resolution_hours' => 'integer',
            'support_priority_queue' => 'boolean',
            'support_dedicated_manager' => 'boolean',
            'support_phone' => 'boolean',
            'support_24_7' => 'boolean',
            'audit_retention_days' => 'integer',
            'require_mfa' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(CustomerSlaAssignment::class);
    }

    /**
     * 预设 SLA 等级
     */
    public static function defaultTiers(int $tenantId): array
    {
        return [
            [
                'slug' => 'enterprise',
                'name' => '企业版',
                'description' => '最高优先级 SLA，适合大型企业客户',
                'priority' => 100,
                'is_default' => false,
                'api_rate_limit' => 600,
                'api_burst_limit' => 1200,
                'api_concurrent_limit' => 100,
                'verify_rate_limit' => 600,
                'verify_timeout_seconds' => 2,
                'max_active_licenses' => 0,
                'max_devices_per_license' => 0,
                'sla_response_hours' => 1,
                'sla_resolution_hours' => 8,
                'support_priority_queue' => true,
                'support_dedicated_manager' => true,
                'support_phone' => true,
                'support_24_7' => true,
                'audit_retention_days' => 1095,
                'require_mfa' => true,
                'allowed_ip_ranges' => null,
            ],
            [
                'slug' => 'professional',
                'name' => '专业版',
                'description' => '高优先级 SLA，适合中型企业客户',
                'priority' => 50,
                'is_default' => false,
                'api_rate_limit' => 300,
                'api_burst_limit' => 500,
                'api_concurrent_limit' => 50,
                'verify_rate_limit' => 300,
                'verify_timeout_seconds' => 3,
                'max_active_licenses' => 50,
                'max_devices_per_license' => 10,
                'sla_response_hours' => 4,
                'sla_resolution_hours' => 24,
                'support_priority_queue' => true,
                'support_dedicated_manager' => false,
                'support_phone' => true,
                'support_24_7' => false,
                'audit_retention_days' => 730,
                'require_mfa' => false,
                'allowed_ip_ranges' => null,
            ],
            [
                'slug' => 'standard',
                'name' => '标准版',
                'description' => '标准 SLA，适合小型企业客户',
                'priority' => 10,
                'is_default' => true,
                'api_rate_limit' => 120,
                'api_burst_limit' => 200,
                'api_concurrent_limit' => 20,
                'verify_rate_limit' => 120,
                'verify_timeout_seconds' => 5,
                'max_active_licenses' => 10,
                'max_devices_per_license' => 5,
                'sla_response_hours' => 24,
                'sla_resolution_hours' => 72,
                'support_priority_queue' => false,
                'support_dedicated_manager' => false,
                'support_phone' => false,
                'support_24_7' => false,
                'audit_retention_days' => 365,
                'require_mfa' => false,
                'allowed_ip_ranges' => null,
            ],
            [
                'slug' => 'free',
                'name' => '免费版',
                'description' => '基础 SLA，适合免费/试用客户',
                'priority' => 0,
                'is_default' => false,
                'api_rate_limit' => 30,
                'api_burst_limit' => 60,
                'api_concurrent_limit' => 5,
                'verify_rate_limit' => 30,
                'verify_timeout_seconds' => 10,
                'max_active_licenses' => 3,
                'max_devices_per_license' => 2,
                'sla_response_hours' => 72,
                'sla_resolution_hours' => 168,
                'support_priority_queue' => false,
                'support_dedicated_manager' => false,
                'support_phone' => false,
                'support_24_7' => false,
                'audit_retention_days' => 90,
                'require_mfa' => false,
                'allowed_ip_ranges' => null,
            ],
        ];
    }

    /**
     * scope: 默认等级
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}
