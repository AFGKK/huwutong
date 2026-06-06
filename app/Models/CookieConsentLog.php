<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CookieConsentLog extends Model
{
    protected $table = 'cookie_consent_logs';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'ip',
        'action',
        'selected_categories',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'selected_categories' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
