<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperAuditLogAnnotation
 */
class AuditLogAnnotation extends Model
{
    protected $table = 'audit_log_annotations';

    protected $fillable = [
        'log_id', 'user_id', 'content',
    ];

    public function log(): BelongsTo
    {
        return $this->belongsTo(Log::class, 'log_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
