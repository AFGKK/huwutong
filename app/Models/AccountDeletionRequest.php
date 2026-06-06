<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountDeletionRequest extends Model
{
    protected $fillable = [
        'user_id', 'reason', 'status', 'cooling_until',
        'processed_at', 'admin_notes',
    ];

    protected function casts(): array
    {
        return [
            'cooling_until' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 冷静期是否已过（可执行注销）
     */
    public function isCoolingOver(): bool
    {
        return $this->cooling_until && $this->cooling_until->isPast();
    }
}
