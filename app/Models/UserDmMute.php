<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDmMute extends Model
{
    protected $fillable = ['user_id', 'muted_until', 'reason'];

    protected function casts(): array
    {
        return ['muted_until' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->muted_until && $this->muted_until->isFuture();
    }
}
