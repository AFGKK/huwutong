<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperAlertChannel
 */
class AlertChannel extends Model
{
    protected $table = 'alert_channels';

    protected $fillable = [
        'tenant_id', 'name', 'type', 'config',
        'description', 'is_enabled', 'is_default', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_enabled' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    const TYPES = ['email', 'slack', 'webhook', 'sms', 'dingtalk', 'feishu', 'wechat', 'custom'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function rules(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(AlertRule::class, 'alert_channel_rule');
    }
}
