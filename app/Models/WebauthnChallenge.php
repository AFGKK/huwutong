<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebauthnChallenge extends Model
{
    protected $fillable = [
        'challenge', 'type', 'user_id', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }
}
