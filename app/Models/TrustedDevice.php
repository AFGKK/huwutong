<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrustedDevice extends Model
{
    protected $fillable = [
        'user_id', 'device_fingerprint', 'device_name',
        'ip_address', 'user_agent', 'trusted_at', 'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'trusted_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
