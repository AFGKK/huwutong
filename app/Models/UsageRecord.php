<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 用量记录
 *
 * @mixin IdeHelperUsageRecord
 */
class UsageRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'license_id',
        'customer_id',
        'metric_key',
        'action',
        'window_type',
        'quantity',
        'unit',
        'context',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'context' => 'array',
            'recorded_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
