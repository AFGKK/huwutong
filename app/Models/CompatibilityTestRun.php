<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompatibilityTestRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'reference', 'status',
        'total_tests', 'passed_tests', 'failed_tests', 'skipped_tests',
        'summary', 'triggered_by', 'triggered_by_user_id',
        'started_at', 'completed_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'total_tests' => 'integer',
            'passed_tests' => 'integer',
            'failed_tests' => 'integer',
            'skipped_tests' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    const STATUS_PENDING = 'pending';
    const STATUS_RUNNING = 'running';
    const STATUS_PASSED = 'passed';
    const STATUS_FAILED = 'failed';
    const STATUS_ERROR = 'error';
    const STATUS_CANCELLED = 'cancelled';

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function triggerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }

    public function matrixResults(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CompatibilityMatrixResult::class, 'test_run_id');
    }

    public function testResults(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CompatibilityTestResult::class, 'test_run_id');
    }

    public function isComplete(): bool
    {
        return in_array($this->status, [
            self::STATUS_PASSED, self::STATUS_FAILED,
            self::STATUS_ERROR, self::STATUS_CANCELLED,
        ]);
    }

    public function passRate(): float
    {
        if ($this->total_tests === 0) return 0;
        return round(($this->passed_tests / $this->total_tests) * 100, 1);
    }
}
