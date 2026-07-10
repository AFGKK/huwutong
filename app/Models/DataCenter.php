<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperDataCenter
 */
class DataCenter extends Model
{
    use HasFactory;

    protected $table = 'data_centers';

    protected $fillable = [
        'name', 'code', 'region', 'country_code', 'city',
        'is_active', 'sort_order', 'base_url', 'health_check_url',
        'capabilities', 'status', 'current_latency_ms', 'current_load',
        'last_health_check_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'capabilities' => 'array',
            'current_latency_ms' => 'decimal:2',
            'current_load' => 'decimal:2',
            'last_health_check_at' => 'datetime',
        ];
    }

    const REGIONS = ['asia', 'europe', 'us', 'oceania', 'africa', 'south_america'];
    const STATUSES = ['healthy', 'degraded', 'down', 'maintenance'];
    const CAPABILITIES = ['compute', 'storage', 'database', 'cache', 'queue'];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeHealthy($query)
    {
        return $query->whereIn('status', ['healthy', 'degraded']);
    }

    public function healthLogs(): HasMany
    {
        return $this->hasMany(RegionHealthLog::class, 'data_center_id');
    }

    public function latestHealthLog()
    {
        return $this->hasOne(RegionHealthLog::class, 'data_center_id')->latestOfMany('checked_at');
    }

    public function primaryFailoverRules(): HasMany
    {
        return $this->hasMany(FailoverRule::class, 'primary_dc_id');
    }

    public function backupFailoverRules(): HasMany
    {
        return $this->hasMany(FailoverRule::class, 'backup_dc_id');
    }
}
