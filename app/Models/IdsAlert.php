<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdsAlert extends Model
{
    use HasFactory;

    protected $table = 'ids_alerts';

    protected $fillable = [
        'tenant_id',
        'ids_rule_id',
        'rule_slug',
        'rule_name',
        'detection_type',
        'severity',
        'source_ip',
        'source_user_id',
        'target_resource',
        'evidence',
        'matched_conditions',
        'status',
        'mitigated_at',
        'closed_at',
        'sop_execution_id',
    ];

    protected function casts(): array
    {
        return [
            'evidence' => 'array',
            'matched_conditions' => 'array',
            'mitigated_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    const STATUSES = [
        'open' => '待处理',
        'investigating' => '调查中',
        'mitigated' => '已缓解',
        'false_positive' => '误报',
        'closed' => '已关闭',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(IdsRule::class, 'ids_rule_id');
    }

    public function sopExecution(): BelongsTo
    {
        return $this->belongsTo(SecuritySopExecution::class, 'sop_execution_id');
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['open', 'investigating']);
    }
}
