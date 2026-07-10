<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperServerlessFunction
 */
class ServerlessFunction extends Model
{
    use SoftDeletes;

    protected $table = 'serverless_functions';

    protected $fillable = [
        'tenant_id', 'name', 'function_id', 'runtime',
        'qps_limit', 'monthly_invocation_limit', 'invocations_used',
        'timeout_seconds', 'status', 'auth_config', 'last_invoked_at',
    ];

    protected function casts(): array
    {
        return [
            'auth_config' => 'array',
            'last_invoked_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
