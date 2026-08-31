<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MiniprogramExpirySubscription extends Model
{
    protected $fillable = [
        'user_id',
        'wechat_openid',
        'license_key',
        'license_id',
        'license_expires_at',
        'remind_days',
        'status',
        'last_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'license_expires_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'remind_days' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }
}
