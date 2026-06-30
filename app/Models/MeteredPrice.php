<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeteredPrice extends Model
{
    use HasFactory;
    protected $fillable = [
        'tenant_id', 'metric_key', 'name', 'unit',
        'billing_period', 'tiers', 'base_fee',
        'included_quantity', 'max_quantity',
        'is_active', 'sort_order', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'tiers' => 'array',
            'metadata' => 'array',
            'base_fee' => 'decimal:2',
            'included_quantity' => 'decimal:2',
            'max_quantity' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * 计算给定用量对应的费用
     */
    public function calculateCost(float $totalQuantity): array
    {
        $tiers = $this->tiers ?? [];
        $billableQuantity = max(0, $totalQuantity - (float) $this->included_quantity);
        $remaining = $billableQuantity;
        $totalCost = 0.0;
        $tierDetails = [];

        foreach ($tiers as $i => $tier) {
            $from = (float) ($tier['from'] ?? 0);
            $to = isset($tier['to']) && $tier['to'] !== null ? (float) $tier['to'] : null;
            $unitPrice = (float) ($tier['unit_price'] ?? 0);

            if ($remaining <= 0) break;

            $tierCapacity = $to !== null ? ($to - $from) : PHP_FLOAT_MAX;
            $tierQuantity = min($remaining, $tierCapacity);
            $tierCost = round($tierQuantity * $unitPrice, 4);

            $totalCost += $tierCost;
            $remaining -= $tierQuantity;

            $tierDetails[] = [
                'tier' => $i + 1,
                'range' => $tier['from'] . ($to !== null ? "~{$to}" : '+'),
                'quantity' => $tierQuantity,
                'unit_price' => $unitPrice,
                'cost' => $tierCost,
            ];

            if ($to === null) break;
        }

        $totalCost += (float) $this->base_fee;
        $totalCost = round($totalCost, 2);

        return [
            'total_quantity' => $totalQuantity,
            'billable_quantity' => $billableQuantity,
            'included_quantity' => (float) $this->included_quantity,
            'base_fee' => (float) $this->base_fee,
            'total_cost' => round($totalCost, 2),
            'tier_details' => $tierDetails,
            'currency' => 'CNY',
        ];
    }
}
