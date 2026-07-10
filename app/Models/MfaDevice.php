<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperMfaDevice
 */
class MfaDevice extends Model
{
    protected $fillable = [
        'user_id', 'name', 'secret', 'type',
        'last_used_at', 'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
