<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 子处理商评估
 *
 * @mixin IdeHelperSubProcessorAssessment
 */
class SubProcessorAssessment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'contact_email', 'jurisdiction',
        'processing_description', 'data_categories',
        'status', 'security_assessment', 'certification',
        'has_dpa_signed', 'dpa_signed_at', 'safeguards',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'data_categories' => 'array',
            'safeguards' => 'array',
            'has_dpa_signed' => 'boolean',
            'dpa_signed_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    const STATUSES = ['pending', 'approved', 'rejected', 'terminated'];
}
