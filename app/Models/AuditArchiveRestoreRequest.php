<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * M2-73 审计归档取回请求
 *
 * @mixin IdeHelperAuditArchiveRestoreRequest
 */
class AuditArchiveRestoreRequest extends Model
{
    protected $table = 'audit_archive_restore_requests';

    protected $fillable = [
        'archive_record_id', 'requester_type', 'reason', 'status',
        'requested_at', 'available_until', 'expires_at',
        'temp_file_path', 'error_message', 'requested_by',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'available_until' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public const STATUSES = ['pending', 'restoring', 'available', 'expired', 'failed', 'cancelled'];

    public function archiveRecord(): BelongsTo { return $this->belongsTo(AuditArchiveRecord::class, 'archive_record_id'); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }

    public function scopePending($q) { return $q->where('status', 'pending'); }
    public function scopeAvailable($q) { return $q->where('status', 'available'); }
}
