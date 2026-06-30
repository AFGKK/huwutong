<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SlaMetric extends Model
{
    use HasFactory;

    protected $table = 'sla_metrics';

    protected $fillable = [
        'sla_contract_id', 'metric_key', 'name', 'unit',
        'target_value', 'warning_threshold', 'measurement_window',
        'data_source', 'data_source_config', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'data_source_config' => 'array',
            'is_active' => 'boolean',
        ];
    }

    const METRIC_KEYS = [
        'response_time' => '响应时间',
        'resolution_time' => '解决时间',
        'uptime' => '正常运行时间',
        'availability' => '可用性',
        'ticket_backlog' => '工单积压',
    ];

    const UNITS = ['minutes', 'hours', 'percentage', 'count'];
    const WINDOWS = ['daily', 'weekly', 'monthly', 'quarterly'];

    public function contract(): BelongsTo { return $this->belongsTo(SlaContract::class, 'sla_contract_id'); }
    public function records(): HasMany { return $this->hasMany(SlaRecord::class, 'sla_metric_id'); }
}
