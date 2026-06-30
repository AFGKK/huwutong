<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnCallOverride extends Model
{
    protected $fillable = [
        'schedule_id', 'original_user_id', 'replacement_user_id',
        'starts_at', 'ends_at', 'reason', 'status',
        'approved_by', 'approved_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function schedule(): BelongsTo { return $this->belongsTo(OnCallSchedule::class, 'schedule_id'); }
    public function originalUser(): BelongsTo { return $this->belongsTo(User::class, 'original_user_id'); }
    public function replacementUser(): BelongsTo { return $this->belongsTo(User::class, 'replacement_user_id'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
}
