<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MagicLinkToken extends Model
{
    protected $fillable = [
        'email', 'token', 'expires_at', 'used', 'used_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used' => 'boolean',
            'used_at' => 'datetime',
        ];
    }

    public function scopeValid($query)
    {
        return $query->where('used', false)
            ->where('expires_at', '>', now());
    }
}
