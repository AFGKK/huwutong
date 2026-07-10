<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperApiEndpointSnapshot
 */
class ApiEndpointSnapshot extends Model
{
    protected $table = 'api_endpoint_snapshots';

    protected $fillable = [
        'api_version_id', 'endpoint_id',
        'method', 'path', 'group', 'tag', 'summary', 'status',
        'parameters_snapshot', 'responses_snapshot',
        'snapshot_version', 'snapshot_at',
    ];

    protected function casts(): array
    {
        return [
            'parameters_snapshot' => 'array',
            'responses_snapshot' => 'array',
            'snapshot_at' => 'datetime',
        ];
    }

    public function apiVersion(): BelongsTo
    {
        return $this->belongsTo(ApiVersion::class);
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(ApiDocEndpoint::class, 'endpoint_id');
    }
}
