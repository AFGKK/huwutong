<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 限流规则
 *
 * 可在管理后台动态配置，替代 EnhancedRateLimiter 中的硬编码默认规则。
 * 支持按路径、方法、HTTP 方法等条件匹配。
 *
 * @mixin IdeHelperRateLimitRule
 */
class RateLimitRule extends Model
{
    protected $fillable = [
        'name', 'slug', 'key_type', 'max_attempts', 'window_seconds',
        'decay_ms', 'is_active', 'priority', 'description', 'conditions',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'conditions' => 'array',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeBySlug($query, string $slug)
    {
        return $query->where('slug', $slug);
    }

    /**
     * 转换为 EnhancedRateLimiter 规则格式
     */
    public function toLimiterRule(): array
    {
        return [
            'key_type' => $this->key_type,
            'max_attempts' => $this->max_attempts,
            'window_seconds' => $this->window_seconds,
            'decay_ms' => $this->decay_ms,
        ];
    }
}
