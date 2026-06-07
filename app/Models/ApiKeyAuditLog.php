<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiKeyAuditLog extends Model
{
    use HasFactory;

    protected $table = 'api_key_audit_logs';

    protected $fillable = [
        'api_key_id',
        'tenant_id',
        'action',
        'actor_type',
        'actor_id',
        'ip_address',
        'user_agent',
        'old_values',
        'new_values',
        'remark',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }

    public function scopeOfAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    public function scopeRecent($query, int $limit = 50)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }
}
