<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 代理商绩效评分配置
 *
 * @mixin IdeHelperAgentScoreConfig
 */
class AgentScoreConfig extends Model
{
    protected $fillable = [
        'metric', 'label', 'weight', 'description', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
