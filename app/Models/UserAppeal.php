<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperUserAppeal
 */
class UserAppeal extends Model
{
    protected $fillable = [
        'user_id',
        'status',
        'reason',
        'explanation',
        'attachments',
        'contact_email',
        'contact_phone',
        'reviewed_at',
        'reviewed_by',
        'review_comment',
        'appealed_at',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'reviewed_at' => 'datetime',
            'appealed_at' => 'datetime',
        ];
    }

    // ── 关联 ──

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // ── 范围 ──

    public function scopePending($q)
    {
        return $q->whereIn('status', ['pending', 'reviewing']);
    }

    public function scopeStatus($q, $status)
    {
        return $q->where('status', $status);
    }
}
