<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id', 'channels', 'types',
    ];

    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'types' => 'array',
        ];
    }
}
