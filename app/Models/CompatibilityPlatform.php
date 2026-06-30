<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompatibilityPlatform extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'name', 'category', 'version', 'label',
        'metadata', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function matrixResults(): HasMany
    {
        return $this->hasMany(CompatibilityMatrixResult::class, 'platform_id');
    }

    public function testResults(): HasMany
    {
        return $this->hasMany(CompatibilityTestResult::class, 'platform_id');
    }
}
