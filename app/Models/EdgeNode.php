<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperEdgeNode
 */
class EdgeNode extends Model
{
    use SoftDeletes;

    protected $table = 'edge_nodes';

    protected $fillable = [
        'tenant_id', 'name', 'node_id', 'node_type', 'region',
        'api_key', 'status', 'geo_allowed', 'config', 'last_heartbeat_at',
    ];

    protected function casts(): array
    {
        return [
            'geo_allowed' => 'array',
            'config' => 'array',
            'last_heartbeat_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
