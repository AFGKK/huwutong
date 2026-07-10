<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperAlertSilenceRule
 */
class AlertSilenceRule extends Model
{
    protected $fillable = [
        'name', 'description', 'match_type', 'match_rules',
        'starts_at', 'ends_at', 'timezone', 'is_recurring',
        'recurrence_rule', 'created_by', 'reason', 'is_active',
    ];

    protected $casts = [
        'match_rules' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_recurring' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function isActive(): bool
    {
        return $this->is_active
            && now()->between($this->starts_at, $this->ends_at);
    }

    public function matchesRule(array $context): bool
    {
        if (!$this->isActive()) return false;
        $rules = $this->match_rules ?? [];

        foreach ($rules as $key => $value) {
            if ($this->match_type === 'wildcard' && $value === '*') continue;
            $contextValue = $context[$key] ?? null;
            if ($this->match_type === 'pattern') {
                if (!fnmatch($value, $contextValue ?? '')) return false;
            } else {
                if (($contextValue ?? '') != $value) return false;
            }
        }
        return true;
    }
}
