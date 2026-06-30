<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupFileInclude extends Model
{
    protected $fillable = [
        'backup_record_id', 'path', 'file_count',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(BackupRecord::class, 'backup_record_id');
    }
}
