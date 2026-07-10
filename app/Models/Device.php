<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperDevice
 */
class Device extends Model
{
    use HasFactory, \App\Models\Concerns\TrackDataLineage;

    const LIFE_STAGES = [
        'new' => '首次出现',
        'onboarding' => '逐步信任',
        'stable' => '长期稳定',
        'suspicious' => '异常/可疑',
        'retired' => '已废弃',
    ];

    protected $fillable = [
        'tenant_id', 'license_id', 'fingerprint', 'platform',
        'os_version', 'trust_score', 'is_blacklisted', 'is_virtual',
        'lifecycle_stage', 'days_active', 'total_events',
        'first_seen_at', 'last_stage_change_at',
        'metadata', 'last_seen_at',
    ];

    /**
     * 数据血缘追踪配置
     */
    protected function lineageConfig(): array
    {
        return [
            'trackable_type' => 'device',
            'category' => 'device_fingerprint',
            'sensitivity' => 'confidential',
            'label' => fn($m) => '设备 #' . $m->id . ' (指纹: ' . ($m->fingerprint ? substr($m->fingerprint, 0, 16) . '...' : 'N/A') . ')',
            'fields' => [
                'fingerprint' => '设备指纹',
                'platform' => '平台',
                'os_version' => '系统版本',
                'trust_score' => '信任分',
                'is_blacklisted' => '黑名单',
                'is_virtual' => '虚拟环境',
                'lifecycle_stage' => '生命周期阶段',
            ],
        ];
    }

    protected function casts(): array
    {
        return [
            'trust_score' => 'integer',
            'is_blacklisted' => 'boolean',
            'is_virtual' => 'boolean',
            'days_active' => 'integer',
            'total_events' => 'integer',
            'metadata' => 'array',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'last_stage_change_at' => 'datetime',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function license()
    {
        return $this->belongsTo(License::class);
    }

    public function lifecycleEvents(): HasMany
    {
        return $this->hasMany(DeviceLifecycleEvent::class);
    }

    public function lifecycleEventsRecent(int $limit = 20): HasMany
    {
        return $this->lifecycleEvents()->orderByDesc('created_at')->limit($limit);
    }
}
