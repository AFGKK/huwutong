<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTutorialProgress extends Model
{
    protected $table = 'user_tutorial_progress';

    protected $fillable = [
        'user_id', 'tutorial_id', 'current_step', 'is_completed', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_completed' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tutorial(): BelongsTo
    {
        return $this->belongsTo(Tutorial::class);
    }
}
