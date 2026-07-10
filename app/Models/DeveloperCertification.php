<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperDeveloperCertification
 */
class DeveloperCertification extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'user_id', 'certification_level_id',
        'certificate_number', 'status', 'score', 'total_points',
        'attempts', 'max_attempts',
        'badge_issued', 'badge_url',
        'exam_started_at', 'exam_completed_at',
        'certificate_issued_at', 'expires_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'total_points' => 'integer',
            'attempts' => 'integer',
            'max_attempts' => 'integer',
            'badge_issued' => 'boolean',
            'exam_started_at' => 'datetime',
            'exam_completed_at' => 'datetime',
            'certificate_issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    // ─── 状态常量 ───
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_PASSED = 'passed';
    const STATUS_FAILED = 'failed';
    const STATUS_EXPIRED = 'expired';
    const STATUS_REVOKED = 'revoked';

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function certificationLevel(): BelongsTo
    {
        return $this->belongsTo(CertificationLevel::class, 'certification_level_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ExamAnswer::class, 'developer_certification_id');
    }

    /**
     * 是否通过
     */
    public function isPassed(): bool
    {
        return $this->status === self::STATUS_PASSED;
    }

    /**
     * 是否有效（未过期/未被吊销）
     */
    public function isValid(): bool
    {
        return $this->isPassed() && !$this->isExpired();
    }

    /**
     * 是否已过期
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * 是否还可以重考
     */
    public function canRetake(): bool
    {
        return $this->attempts < $this->max_attempts
            && in_array($this->status, [self::STATUS_FAILED, self::STATUS_EXPIRED]);
    }
}
