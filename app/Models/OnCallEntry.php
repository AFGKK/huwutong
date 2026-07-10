<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperOnCallEntry
 */
class OnCallEntry extends Model
{
    protected $fillable = [
        'schedule_id', 'member_id', 'user_id',
        'starts_at', 'ends_at', 'role', 'status', 'source',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function schedule(): BelongsTo { return $this->belongsTo(OnCallSchedule::class, 'schedule_id'); }
    public function member(): BelongsTo { return $this->belongsTo(OnCallMember::class, 'member_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function scopeActive($q)
    {
        return $q->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->where('status', 'scheduled');
    }

    public function scopeUpcoming($q)
    {
        return $q->where('starts_at', '>', now())->where('status', 'scheduled');
    }

    public function scopeByUser($q, int $userId)
    {
        return $q->where('user_id', $userId);
    }

    public function isActive(): bool
    {
        return $this->starts_at <= now() && $this->ends_at >= now() && $this->status === 'scheduled';
    }
}
