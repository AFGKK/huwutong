<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperCompatibilityTestResult
 */
class CompatibilityTestResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'test_run_id', 'platform_id', 'test_case_id',
        'result', 'error_message', 'actual_output',
        'execution_time_ms', 'tester_user_id',
    ];

    protected function casts(): array
    {
        return [
            'execution_time_ms' => 'decimal:2',
        ];
    }

    public function testRun(): BelongsTo
    {
        return $this->belongsTo(CompatibilityTestRun::class, 'test_run_id');
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(CompatibilityPlatform::class, 'platform_id');
    }

    public function testCase(): BelongsTo
    {
        return $this->belongsTo(CompatibilityTestCase::class, 'test_case_id');
    }

    public function tester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tester_user_id');
    }
}
