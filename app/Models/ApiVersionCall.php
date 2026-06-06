<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * API 版本调用统计
 */
class ApiVersionCall extends Model
{
    protected $fillable = [
        'api_version_id', 'tenant_id', 'method', 'path',
        'call_count', 'call_date',
    ];

    protected function casts(): array
    {
        return [
            'call_count' => 'integer',
            'call_date' => 'date',
        ];
    }

    public function apiVersion(): BelongsTo
    {
        return $this->belongsTo(ApiVersion::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
