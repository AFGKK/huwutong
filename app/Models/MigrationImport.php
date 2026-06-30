<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MigrationImport extends Model
{
    protected $table = 'migration_imports';

    protected $fillable = [
        'tenant_id', 'user_id', 'source', 'status',
        'total_rows', 'processed', 'success', 'failed', 'skipped',
        'field_mapping', 'options', 'error_message',
        'result_summary', 'file_path', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'field_mapping' => 'array',
            'options' => 'array',
            'result_summary' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function rows(): HasMany { return $this->hasMany(MigrationImportRow::class, 'migration_import_id'); }
}
