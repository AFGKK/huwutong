<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StagingEnvironment extends Model
{
    protected $fillable = [
        'tenant_id', 'name', 'subdomain', 'status',
        'api_base_url', 'rate_limit', 'config',
        'last_reset_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'rate_limit' => 'integer',
            'last_reset_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
