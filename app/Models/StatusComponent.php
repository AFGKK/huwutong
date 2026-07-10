<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * 监控组件
 *
 * @mixin IdeHelperStatusComponent
 */
class StatusComponent extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'group',
        'status', 'sort_order', 'is_public',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function incidents(): BelongsToMany
    {
        return $this->belongsToMany(StatusIncident::class, 'incident_component', 'component_id', 'incident_id');
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeByGroup($query)
    {
        return $query->orderBy('sort_order');
    }

    public function statusLabel(): string
    {
        return [
            'operational' => '正常运行',
            'degraded_performance' => '性能下降',
            'partial_outage' => '部分中断',
            'major_outage' => '重大中断',
            'unknown' => '未知',
        ][$this->status] ?? $this->status;
    }

    public function statusTagType(): string
    {
        return [
            'operational' => 'success',
            'degraded_performance' => 'warning',
            'partial_outage' => 'danger',
            'major_outage' => 'danger',
            'unknown' => 'info',
        ][$this->status] ?? 'info';
    }
}
