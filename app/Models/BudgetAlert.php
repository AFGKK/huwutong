<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetAlert extends Model
{
    protected $fillable = [
        'budget_limit_id',
        'level',
        'usage_percentage',
        'spent_at_alert',
        'channel',
        'notified',
        'notified_at',
    ];

    protected function casts(): array
    {
        return [
            'usage_percentage' => 'decimal:2',
            'spent_at_alert' => 'decimal:2',
            'notified' => 'boolean',
            'notified_at' => 'datetime',
        ];
    }

    public function budgetLimit(): BelongsTo
    {
        return $this->belongsTo(BudgetLimit::class);
    }
}
