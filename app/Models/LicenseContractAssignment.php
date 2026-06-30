<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LicenseContractAssignment extends Model
{
    use HasFactory;

    protected $table = 'license_contract_assignments';

    protected $fillable = [
        'tenant_id',
        'contract_id',
        'assignable_type',
        'assignable_id',
        'override_conditions',
        'override_actions',
        'override_grant',
        'parameters',
        'effective_from',
        'effective_until',
        'is_enabled',
        'priority',
    ];

    protected function casts(): array
    {
        return [
            'override_conditions' => 'array',
            'override_actions' => 'array',
            'override_grant' => 'array',
            'parameters' => 'array',
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
            'is_enabled' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(LicenseContract::class, 'contract_id');
    }

    public function assignable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    public function scopeEffective($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('effective_from')
              ->orWhere('effective_from', '<=', now());
        })->where(function ($q) {
            $q->whereNull('effective_until')
              ->orWhere('effective_until', '>=', now());
        });
    }
}
