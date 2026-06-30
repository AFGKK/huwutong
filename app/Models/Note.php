<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Note extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'notable_type',
        'notable_id',
        'user_id',
        'content',
        'mentions',
        'attachments',
        'is_pinned',
        'is_internal',
    ];

    protected function casts(): array
    {
        return [
            'mentions' => 'array',
            'attachments' => 'array',
            'is_pinned' => 'boolean',
            'is_internal' => 'boolean',
        ];
    }

    public function notable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mentionedUsers(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentions', 'id');
    }
}
