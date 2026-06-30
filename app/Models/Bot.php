<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bot extends Model
{
    protected $fillable = [
        'user_id', 'name', 'avatar', 'description',
        'webhook_url', 'token', 'commands',
        'is_active', 'is_public', 'status',
    ];

    protected $casts = [
        'commands' => 'array',
        'is_active' => 'boolean',
        'is_public' => 'boolean',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
