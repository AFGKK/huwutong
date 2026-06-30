<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxComplianceRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'name', 'rule_type',
        'country', 'region_code',
        'condition_type', 'condition_value',
        'rate_modifier', 'action',
        'description', 'is_active', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'rate_modifier' => 'decimal:4',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    const RULE_TYPES = [
        'reduced_rate' => '减免税率',
        'exemption' => '免税规则',
        'threshold' => '阈值规则',
        'special_zone' => '特殊区域规则',
    ];

    const ACTIONS = [
        'apply_rate' => '应用税率',
        'exempt' => '免税',
        'reduce_rate' => '减免税率',
        'reverse_charge' => '反向征收',
    ];
}
