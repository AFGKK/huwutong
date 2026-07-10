<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * API 版本定义
 *
 * @mixin IdeHelperApiVersion
 */
class ApiVersion extends Model
{
    const STATUS_ACTIVE = 'active';
    const STATUS_DEPRECATED = 'deprecated';
    const STATUS_SUNSET = 'sunset';
    const STATUS_RETIRED = 'retired';

    protected $fillable = [
        'version', 'base_path', 'name', 'status',
        'deprecated_at', 'sunset_at', 'retired_at',
        'changelog', 'migration_guide', 'deprecation_notice',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'deprecated_at' => 'datetime',
            'sunset_at' => 'datetime',
            'retired_at' => 'datetime',
            'is_default' => 'boolean',
        ];
    }

    public function routes(): HasMany
    {
        return $this->hasMany(ApiVersionRoute::class);
    }

    public function calls(): HasMany
    {
        return $this->hasMany(ApiVersionCall::class);
    }

    /**
     * 是否为活跃版本
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * 是否已废弃
     */
    public function isDeprecated(): bool
    {
        return $this->status === self::STATUS_DEPRECATED;
    }

    /**
     * 是否已停用
     */
    public function isRetired(): bool
    {
        return $this->status === self::STATUS_RETIRED;
    }

    /**
     * scope: 仅活跃版本
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * scope: 默认版本
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}
