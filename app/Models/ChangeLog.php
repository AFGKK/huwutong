<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ChangeLog extends Model
{
    protected $table = 'change_logs';

    protected $fillable = [
        'changelogable_type',
        'changelogable_id',
        'user_id',
        'event',
        'field',
        'old_value',
        'new_value',
        'description',
        'context',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
        ];
    }

    public function changelogable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 记录变更日志
     */
    public static function record(
        Model   $subject,
        string  $event,
        ?string $field = null,
        mixed   $oldValue = null,
        mixed   $newValue = null,
        ?string $description = null,
        ?array  $context = [],
        ?int    $userId = null,
    ): self {
        return static::create([
            'changelogable_type' => $subject->getMorphClass(),
            'changelogable_id' => $subject->getKey(),
            'user_id' => $userId ?? auth()->id(),
            'event' => $event,
            'field' => $field,
            'old_value' => is_scalar($oldValue) ? (string) $oldValue : json_encode($oldValue, JSON_UNESCAPED_UNICODE),
            'new_value' => is_scalar($newValue) ? (string) $newValue : json_encode($newValue, JSON_UNESCAPED_UNICODE),
            'description' => $description,
            'context' => $context,
        ]);
    }
}
