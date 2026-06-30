<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WafAttackLog extends Model
{
    protected $fillable = [
        'event_id', 'ip', 'country', 'method', 'uri',
        'rule_category', 'rule_name', 'matched_pattern', 'matched_value',
        'target', 'severity', 'action_taken', 'user_agent',
        'headers', 'request_body', 'user_id', 'session_id',
        'is_whitelisted', 'is_trusted_ip',
    ];

    protected $casts = [
        'headers' => 'array',
        'is_whitelisted' => 'boolean',
        'is_trusted_ip' => 'boolean',
    ];

    const SEVERITIES = ['low', 'medium', 'high', 'critical'];
    const ACTIONS = ['block', 'challenge', 'log', 'allow', 'redirect'];

    public function scopeByIp($query, string $ip)
    {
        return $query->where('ip', $ip);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('rule_category', $category);
    }

    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    public function scopeRecent($query, int $minutes = 60)
    {
        return $query->where('created_at', '>=', now()->subMinutes($minutes));
    }

    public function scopeByDateRange($query, $start, $end)
    {
        return $query->whereBetween('created_at', [$start, $end]);
    }
}
