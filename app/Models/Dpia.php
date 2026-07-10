<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * DPIA 数据保护影响评估 (PIPL Art.55-56)
 *
 * 高风险处理活动的合规评估
 *
 * @mixin IdeHelperDpia
 */
class Dpia extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    const STATUS_DRAFT = 'draft';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'tenant_id',
        'title',
        'status',
        'description',
        'necessity_assessment',
        'risk_assessment',
        'mitigation_measures',
        'conclusion',
        'involved_data_categories',
        'stakeholders',
        'completed_at',
        'next_review_at',
        'created_by',
    ];

    protected $casts = [
        'involved_data_categories' => 'array',
        'stakeholders' => 'array',
        'completed_at' => 'datetime',
        'next_review_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
