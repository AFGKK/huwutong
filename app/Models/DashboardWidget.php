<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @mixin IdeHelperDashboardWidget
 */
class DashboardWidget extends Model
{
    protected $fillable = [
        'dashboard_id', 'type', 'title', 'description',
        'config', 'layout', 'data_source', 'visual_options',
        'sort_order', 'is_visible',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'layout' => 'array',
            'data_source' => 'array',
            'visual_options' => 'array',
            'is_visible' => 'boolean',
        ];
    }

    const TYPES = [
        'stat' => '统计数字',
        'chart' => '图表',
        'list' => '列表',
        'metric' => '指标卡',
        'table' => '数据表格',
        'iframe' => '嵌入页面',
        'html' => '自定义HTML',
        'alert' => '告警列表',
        'report' => '报表快照',
    ];

    public function dashboard(): BelongsTo { return $this->belongsTo(Dashboard::class); }
    public function cache(): HasOne { return $this->hasOne(DashboardWidgetCache::class, 'widget_id'); }
}
