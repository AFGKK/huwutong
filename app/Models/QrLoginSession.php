<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QrLoginSession extends Model
{
    protected $fillable = [
        'session_id', 'status',
        'user_id', 'confirmed_token',
        'ip_address', 'user_agent',
        'expires_at', 'scanned_at', 'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'scanned_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending')
            ->where('expires_at', '>', now());
    }
}
