<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 代理商等级定义 (M3-04)
 *
 * regular → silver → gold → platinum
 */
class AgentTierDefinition extends Model
{
    protected $fillable = [
        'level', 'name', 'sort_order', 'default_rate',
        'benefits', 'color', 'icon', 'description', 'is_active',
    ];

    protected $casts = [
        'benefits' => 'array',
        'default_rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
