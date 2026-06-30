<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InviteChannel extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'type', 'status', 'tags',
        'is_public', 'max_codes', 'code_count',
        'registration_count', 'conversion_rate',
        'landing_config', 'utm_defaults',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'is_public' => 'boolean',
            'landing_config' => 'array',
            'utm_defaults' => 'array',
        ];
    }

    const TYPES = ['promotional', 'marketing', 'partner', 'event', 'social', 'internal'];

    public function inviteCodes(): HasMany { return $this->hasMany(InviteCode::class); }
    public function dailyStats(): HasMany { return $this->hasMany(InviteChannelDailyStat::class, 'channel_id'); }
    public function registrations(): HasMany { return $this->hasMany(RegistrationTracking::class, 'channel_id'); }
}
