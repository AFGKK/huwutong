<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperAiTrainingDataSource
 */
class AiTrainingDataSource extends Model
{
    protected $fillable = [
        'ai_system_id', 'source_name', 'source_type', 'description',
        'collection_method', 'has_pii', 'has_sensitive_data', 'license',
        'record_count', 'date_range_start', 'date_range_end',
        'preprocessing', 'notes',
    ];

    protected $casts = [
        'has_pii' => 'boolean',
        'has_sensitive_data' => 'boolean',
        'record_count' => 'integer',
    ];

    public function system(): BelongsTo { return $this->belongsTo(AiSystemRegistry::class, 'ai_system_id'); }
}
