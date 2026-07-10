<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperMigrationAssistantJob
 */
class MigrationAssistantJob extends Model
{
    protected $table = 'migration_assistant_jobs';

    protected $fillable = [
        'tenant_id', 'user_id', 'source', 'status',
        'config', 'field_mapping', 'summary',
        'validation_results', 'ai_suggestions',
        'total_items', 'valid_items', 'imported_items',
        'failed_items', 'skipped_items',
        'error_message', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'field_mapping' => 'array',
            'summary' => 'array',
            'validation_results' => 'array',
            'ai_suggestions' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function items(): HasMany { return $this->hasMany(MigrationAssistantItem::class, 'migration_assistant_job_id'); }
}
