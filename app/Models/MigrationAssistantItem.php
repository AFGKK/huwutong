<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperMigrationAssistantItem
 */
class MigrationAssistantItem extends Model
{
    protected $table = 'migration_assistant_items';

    protected $fillable = [
        'migration_assistant_job_id', 'item_index',
        'original_data', 'mapped_data', 'cleaned_data',
        'validation_errors', 'ai_suggestions', 'status',
        'created_license_id', 'created_customer_id',
    ];

    protected function casts(): array
    {
        return [
            'original_data' => 'array',
            'mapped_data' => 'array',
            'cleaned_data' => 'array',
            'validation_errors' => 'array',
            'ai_suggestions' => 'array',
        ];
    }

    public function job(): BelongsTo { return $this->belongsTo(MigrationAssistantJob::class, 'migration_assistant_job_id'); }
}
