<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailVerification extends Model
{
    protected $fillable = [
        'user_id', 'email', 'token', 'expires_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 验证码是否有效
     */
    public function isValid(): bool
    {
        return $this->completed_at === null
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }
}
