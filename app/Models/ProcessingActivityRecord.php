<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ROPA — 处理活动记录
 */
class ProcessingActivityRecord extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'reference', 'status', 'controller_name', 'controller_contact',
        'controller_dpo', 'processing_type', 'processing_description',
        'processing_purposes', 'data_categories', 'data_subjects',
        'recipients', 'transfers', 'retention_period',
        'technical_measures', 'organizational_measures',
        'has_dpia', 'dpia_id', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'processing_purposes' => 'array',
            'data_categories' => 'array',
            'data_subjects' => 'array',
            'recipients' => 'array',
            'transfers' => 'array',
            'technical_measures' => 'array',
            'organizational_measures' => 'array',
            'has_dpia' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function dpia()
    {
        return $this->belongsTo(DpiaRecord::class, 'dpia_id');
    }
}
