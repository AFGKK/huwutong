<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OnCallSchedule extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'description', 'rotation_type', 'rotation_length',
        'time_restriction', 'escalation_rules', 'channels', 'status', 'color', 'created_by',
    ];

    protected $casts = [
        'time_restriction' => 'array',
        'escalation_rules' => 'array',
        'channels' => 'array',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function members(): HasMany { return $this->hasMany(OnCallMember::class, 'schedule_id'); }
    public function entries(): HasMany { return $this->hasMany(OnCallEntry::class, 'schedule_id'); }
    public function overrides(): HasMany { return $this->hasMany(OnCallOverride::class, 'schedule_id'); }

    public function scopeActive($q) { return $q->where('status', 'active'); }
}
