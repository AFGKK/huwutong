<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperRenewalReminderTemplate
 */
class RenewalReminderTemplate extends Model
{
    use HasFactory;

    protected $attributes = [
        'is_active' => true,
    ];

    protected $table = 'renewal_reminder_templates';

    protected $fillable = [
        'tenant_id', 'name', 'channel', 'days_before',
        'subject', 'content', 'sms_content', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'days_before' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    const CHANNELS = [
        'mail' => '邮件',
        'sms' => '短信',
        'in_app' => '站内信',
    ];
}
