<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RfmScore extends Model
{
    use HasFactory;    protected $fillable = [
        'customer_id', 'tenant_id',
        'recency_days', 'recency_score',
        'frequency_count', 'frequency_score',
        'monetary_total', 'monetary_score',
        'rfm_total', 'rfm_segment',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'recency_days' => 'integer',
            'recency_score' => 'integer',
            'frequency_count' => 'integer',
            'frequency_score' => 'integer',
            'monetary_total' => 'decimal:2',
            'monetary_score' => 'integer',
            'rfm_total' => 'integer',
            'calculated_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
