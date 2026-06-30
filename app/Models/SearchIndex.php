<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchIndex extends Model
{
    protected $table = 'search_index';

    protected $fillable = [
        'tenant_id', 'resource_type', 'resource_id',
        'title', 'content', 'status', 'identifier',
        'tags', 'metadata', 'url', 'weight',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'metadata' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * 支持的资源类型
     */
    const RESOURCE_TYPES = [
        'license', 'customer', 'product', 'ticket',
        'invoice', 'subscription', 'user', 'api_key',
        'webhook', 'log', 'device',
    ];
}
