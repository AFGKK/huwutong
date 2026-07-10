<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * DPIA — 数据保护影响评估
 *
 * @mixin IdeHelperDpiaRecord
 */
class DpiaRecord extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title', 'reference', 'status', 'processing_type',
        'description', 'data_categories', 'data_subjects',
        'processing_purposes', 'necessity_assessment',
        'proportionality_assessment', 'risks', 'risk_level',
        'mitigation_measures', 'controller_dpo',
        'reviewed_at', 'reviewed_by', 'review_notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'data_categories' => 'array',
            'data_subjects' => 'array',
            'processing_purposes' => 'array',
            'risks' => 'array',
            'risk_level' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    const STATUSES = ['draft', 'in_review', 'approved', 'rejected'];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
