<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class McpServer extends Model
{
    use SoftDeletes;

    protected $table = 'mcp_servers';

    protected $fillable = [
        'tenant_id', 'name', 'server_id', 'protocol', 'endpoint',
        'capabilities', 'api_key', 'status', 'metadata', 'last_active_at',
    ];

    protected function casts(): array
    {
        return [
            'capabilities' => 'array',
            'metadata' => 'array',
            'last_active_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
