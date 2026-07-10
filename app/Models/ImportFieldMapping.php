<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperImportFieldMapping
 */
class ImportFieldMapping extends Model
{
    protected $table = 'import_field_mappings';

    protected $fillable = [
        'import_task_id', 'source_field', 'target_field', 'target_label',
        'default_value', 'transform_rules', 'is_required', 'is_identifier', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'transform_rules' => 'array',
            'is_required' => 'boolean',
            'is_identifier' => 'boolean',
        ];
    }

    public function task(): BelongsTo { return $this->belongsTo(ImportTask::class, 'import_task_id'); }
}
