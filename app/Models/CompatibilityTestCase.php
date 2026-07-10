<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperCompatibilityTestCase
 */
class CompatibilityTestCase extends Model
{
    use HasFactory;

    protected $fillable = [
        'suite_id', 'name', 'description', 'expected_result',
        'sort_order', 'is_critical',
    ];

    protected function casts(): array
    {
        return [
            'is_critical' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function suite(): BelongsTo
    {
        return $this->belongsTo(CompatibilityTestSuite::class, 'suite_id');
    }
}
