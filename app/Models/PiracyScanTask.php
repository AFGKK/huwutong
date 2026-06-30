<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PiracyScanTask extends Model
{
    protected $table = 'piracy_scan_tasks';

    protected $fillable = [
        'tenant_id', 'source', 'query', 'status',
        'urls_found', 'matches_found', 'confirmed',
        'started_at', 'completed_at', 'error_message',
        'result_summary', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'result_summary' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function evidence(): HasMany { return $this->hasMany(PiracyEvidence::class, 'piracy_scan_task_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
