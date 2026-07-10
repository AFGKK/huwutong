<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperImportMappingTemplate
 */
class ImportMappingTemplate extends Model
{
    protected $table = 'import_mapping_templates';

    protected $fillable = [
        'user_id', 'name', 'entity_type',
        'mappings', 'default_options', 'description', 'is_system', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'mappings' => 'array',
            'default_options' => 'array',
            'is_system' => 'boolean',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
