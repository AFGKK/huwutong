<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditBatchOperation extends Model
{
    protected $table = 'audit_batch_operations';

    protected $fillable = [
        'operation_type', 'log_ids', 'params', 'user_id', 'status', 'result_message',
    ];

    protected function casts(): array
    {
        return [
            'log_ids' => 'array',
            'params' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
