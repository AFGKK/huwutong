<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class UserReport extends Model
{
    protected $fillable = [
        'reporter_id',
        'reportable_type',
        'reportable_id',
        'reason',
        'description',
        'status',
        'admin_note',
        'handled_by',
        'handled_at',
    ];

    protected $casts = [
        'handled_at' => 'datetime',
    ];

    const REASONS = [
        'spam' => '垃圾广告',
        'harassment' => '骚扰谩骂',
        'pornographic' => '色情低俗',
        'illegal' => '违法违规',
        'impersonation' => '冒充他人',
        'copyright' => '侵权',
        'other' => '其他',
    ];

    const STATUSES = ['pending', 'investigating', 'resolved', 'dismissed'];

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function scopePending($q)
    {
        return $q->where('status', 'pending');
    }

    public function scopeByStatus($q, $status)
    {
        return $q->where('status', $status);
    }
}
