<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 代理商等级分成比例
 *
 * @mixin IdeHelperAgentCommissionRate
 */
class AgentCommissionRate extends Model
{
    protected $fillable = [
        'level', 'product_type', 'rate', 'multi_level_rate', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'multi_level_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
