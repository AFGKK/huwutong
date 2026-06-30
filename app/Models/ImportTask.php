<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportTask extends Model
{
    protected $fillable = [
        'user_id', 'tenant_id', 'name', 'entity_type',
        'file_type', 'original_filename', 'stored_filename', 'file_size',
        'total_rows', 'processed_rows', 'success_rows', 'error_rows', 'warning_rows',
        'status', 'preview_data', 'validation_errors', 'import_result', 'options',
        'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'preview_data' => 'array',
            'validation_errors' => 'array',
            'import_result' => 'array',
            'options' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    const ENTITY_TYPES = [
        'licenses' => 'License',
        'customers' => '客户',
        'subscriptions' => '订阅',
        'products' => '产品',
        'tickets' => '工单',
    ];

    const STATUSES = [
        'uploaded', 'mapping', 'preview', 'validated',
        'importing', 'completed', 'failed', 'cancelled',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function mappings(): HasMany { return $this->hasMany(ImportFieldMapping::class, 'import_task_id'); }
    public function logs(): HasMany { return $this->hasMany(ImportLog::class, 'import_task_id'); }
}
