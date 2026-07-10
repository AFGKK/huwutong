<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @mixin IdeHelperActivity
 */
class Activity extends Model
{
    protected $table = 'activities';

    protected $fillable = [
        'user_id',
        'tenant_id',
        'type',
        'description',
        'subject_type',
        'subject_id',
        'metadata',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * 记录活动
     */
    public static function record(
        Model  $subject,
        string $type,
        string $description,
        ?int   $userId = null,
        ?int   $tenantId = null,
        ?array $metadata = [],
    ): self {
        return static::create([
            'user_id' => $userId ?? auth()->id(),
            'tenant_id' => $tenantId ?? auth()->user()?->tenant_id,
            'type' => $type,
            'description' => $description,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'metadata' => $metadata,
            'ip_address' => request()->ip(),
        ]);
    }
}
