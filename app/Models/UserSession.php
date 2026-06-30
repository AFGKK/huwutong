<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSession extends Model
{
    protected $table = 'user_sessions';

    protected $fillable = [
        'user_id', 'tenant_id', 'session_id', 'ip_address',
        'user_agent', 'device_type', 'browser', 'os', 'location',
        'is_current', 'is_mfa_verified', 'last_activity_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
            'is_mfa_verified' => 'boolean',
            'last_activity_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
