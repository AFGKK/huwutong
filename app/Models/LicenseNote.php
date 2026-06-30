<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LicenseNote extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'license_id',
        'user_id',
        'content',
        'mentions',
    ];

    protected function casts(): array
    {
        return [
            'mentions' => 'array',
        ];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 被 @提及 的用户
     */
    public function mentionedUsers(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentions', 'id');
    }
}
