<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityEvent extends Model
{
    use HasFactory;
    protected $table = 'security_events';

    protected $fillable = [
        'user_id', 'tenant_id', 'event_type', 'severity',
        'ip_address', 'user_agent', 'metadata', 'description',
        'sop_execution_id', 'resolution_status', 'resolved_at', 'resolution_notes',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    const EVENT_TYPES = [
        'login_failed', 'login_success', 'logout', 'session_expired',
        'ip_blocked', 'mfa_challenge', 'password_changed',
        'suspicious_activity', 'geo_anomaly',
    ];

    const SEVERITIES = ['info', 'warning', 'critical'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function sopExecution(): BelongsTo
    {
        return $this->belongsTo(SecuritySopExecution::class, 'sop_execution_id');
    }
}
