<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistrationTracking extends Model
{
    protected $table = 'registration_tracking';

    protected $fillable = [
        'user_id', 'invite_code', 'channel_id',
        'source', 'referrer_url', 'landing_page',
        'ip_address', 'user_agent', 'utm_params',
        'converted', 'converted_at', 'conversion_type',
    ];

    protected function casts(): array
    {
        return [
            'utm_params' => 'array',
            'converted' => 'boolean',
            'converted_at' => 'datetime',
        ];
    }

    const SOURCES = ['invite', 'direct', 'social', 'oauth', 'trial'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function channel(): BelongsTo { return $this->belongsTo(InviteChannel::class, 'channel_id'); }
}
