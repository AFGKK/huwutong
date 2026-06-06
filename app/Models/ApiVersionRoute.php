<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * API 版本路由映射
 */
class ApiVersionRoute extends Model
{
    protected $fillable = [
        'api_version_id', 'method', 'path',
        'route_name', 'controller', 'action',
        'is_deprecated',
    ];

    protected function casts(): array
    {
        return [
            'is_deprecated' => 'boolean',
        ];
    }

    public function apiVersion(): BelongsTo
    {
        return $this->belongsTo(ApiVersion::class);
    }
}
