<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperSecuritySopTemplate
 */
class SecuritySopTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'security_sop_templates';

    protected $fillable = [
        'tenant_id', 'name', 'slug', 'description', 'severity',
        'trigger_conditions', 'steps', 'status',
        'is_auto_execute', 'sort_order', 'metadata', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'trigger_conditions' => 'array',
            'steps' => 'array',
            'metadata' => 'array',
            'is_auto_execute' => 'boolean',
        ];
    }

    const STEP_TYPES = [
        'log_event', 'notify_admin', 'notify_user', 'block_ip',
        'terminate_sessions', 'disable_account', 'require_mfa',
        'send_alert_email', 'create_ticket', 'custom_webhook',
    ];

    public function executions(): HasMany
    {
        return $this->hasMany(SecuritySopExecution::class, 'sop_template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
