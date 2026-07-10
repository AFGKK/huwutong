<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperAlertIntegration
 */
class AlertIntegration extends Model
{
    protected $table = 'alert_integrations';

    protected $fillable = [
        'name', 'type', 'webhook_url', 'description',
        'config', 'severity_filter', 'is_active', 'last_test_at',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_active' => 'boolean',
            'last_test_at' => 'datetime',
        ];
    }

    /**
     * 检查该集成是否接受指定严重等级的告警
     */
    public function acceptsSeverity(string $severity): bool
    {
        if ($this->severity_filter === 'all') return true;

        $order = ['info' => 0, 'warning' => 1, 'critical' => 2];
        $filterLevel = $order[$this->severity_filter] ?? 0;
        $eventLevel = $order[$severity] ?? 0;

        return $eventLevel >= $filterLevel;
    }
}
