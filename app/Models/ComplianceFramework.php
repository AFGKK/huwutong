<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperComplianceFramework
 */
class ComplianceFramework extends Model
{
    protected $fillable = [
        'code', 'name', 'description', 'control_domains', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'control_domains' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ComplianceReport::class, 'framework_id');
    }
}
