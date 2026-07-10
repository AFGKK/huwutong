<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperLicenseDomainWhitelist
 */
class LicenseDomainWhitelist extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'license_id',
        'domain',
        'is_wildcard',
        'scope',
        'status',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'is_wildcard' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeByScope($query, string $scope)
    {
        return $query->where(function ($q) use ($scope) {
            $q->where('scope', $scope)->orWhere('scope', 'both');
        });
    }
}
