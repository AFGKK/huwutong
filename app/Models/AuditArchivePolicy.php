<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuditArchivePolicy extends Model
{
    protected $table = 'audit_archive_policies';

    protected $fillable = [
        'name', 'type', 'archive_after_days', 'delete_after_days',
        'archive_disk', 'compress_archive', 'is_active',
        'description', 'execution_count', 'last_executed_at',
    ];

    protected function casts(): array
    {
        return [
            'compress_archive' => 'boolean',
            'is_active' => 'boolean',
            'last_executed_at' => 'datetime',
        ];
    }

    const TYPES = ['audit', 'security', 'error', 'system'];

    public function archiveRecords(): HasMany { return $this->hasMany(AuditArchiveRecord::class, 'policy_id'); }
}
