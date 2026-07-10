<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperCompatibilityMatrixResult
 */
class CompatibilityMatrixResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'test_run_id', 'platform_id', 'result', 'notes',
    ];

    public function testRun(): BelongsTo
    {
        return $this->belongsTo(CompatibilityTestRun::class, 'test_run_id');
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(CompatibilityPlatform::class, 'platform_id');
    }
}
