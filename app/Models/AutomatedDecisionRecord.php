<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 自动决策/画像记录 (Art.22)
 *
 * @mixin IdeHelperAutomatedDecisionRecord
 */
class AutomatedDecisionRecord extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'type', 'description', 'input_data_categories',
        'output_decision', 'logic_explanation', 'significance',
        'human_intervention_possible', 'intervention_method',
        'is_active', 'last_reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'input_data_categories' => 'array',
            'output_decision' => 'array',
            'human_intervention_possible' => 'boolean',
            'is_active' => 'boolean',
            'last_reviewed_at' => 'datetime',
        ];
    }

    const TYPES = ['automated_decision', 'profiling'];
}
