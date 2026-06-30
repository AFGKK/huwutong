<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $license_id
 * @property string $action
 * @property string|null $status_before
 * @property string|null $status_after
 * @property array $license_data
 * @property array|null $diff
 * @property int|null $created_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class LicenseSnapshot extends Model
{
    protected $fillable = [
        'tenant_id',
        'license_id',
        'action',
        'status_before',
        'status_after',
        'license_data',
        'diff',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'license_data' => 'array',
            'diff' => 'array',
        ];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function scopeByTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeByLicense($query, int $licenseId)
    {
        return $query->where('license_id', $licenseId);
    }

    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }
}
