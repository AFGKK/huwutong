<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperSlaContract
 */
class SlaContract extends Model
{
    use HasFactory;

    protected $table = 'sla_contracts';

    protected $fillable = [
        'tenant_id', 'customer_id', 'name', 'slug', 'description',
        'level', 'scope', 'terms', 'penalties', 'business_hours',
        'effective_date', 'expiry_date', 'is_active', 'is_template',
    ];

    protected function casts(): array
    {
        return [
            'scope' => 'array',
            'terms' => 'array',
            'penalties' => 'array',
            'business_hours' => 'array',
            'effective_date' => 'date',
            'expiry_date' => 'date',
            'is_active' => 'boolean',
            'is_template' => 'boolean',
        ];
    }

    const LEVELS = ['standard', 'premium', 'enterprise', 'custom'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function metrics(): HasMany { return $this->hasMany(SlaMetric::class, 'sla_contract_id'); }
    public function records(): HasMany { return $this->hasMany(SlaRecord::class, 'sla_contract_id'); }
    public function breaches(): HasMany { return $this->hasMany(SlaBreach::class, 'sla_contract_id'); }
}
