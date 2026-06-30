<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OnCallMember extends Model
{
    protected $fillable = ['schedule_id', 'user_id', 'sort_order', 'weight', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function schedule(): BelongsTo { return $this->belongsTo(OnCallSchedule::class, 'schedule_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function entries(): HasMany { return $this->hasMany(OnCallEntry::class, 'member_id'); }
}
