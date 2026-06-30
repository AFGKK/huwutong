<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportLog extends Model
{
    protected $table = 'import_logs';

    protected $fillable = [
        'import_task_id', 'row_number', 'level',
        'action', 'original_data', 'processed_data', 'message',
    ];

    protected function casts(): array
    {
        return [
            'original_data' => 'array',
            'processed_data' => 'array',
        ];
    }

    public function task(): BelongsTo { return $this->belongsTo(ImportTask::class, 'import_task_id'); }
}
