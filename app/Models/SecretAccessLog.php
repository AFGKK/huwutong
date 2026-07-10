<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperSecretAccessLog
 */
class SecretAccessLog extends Model
{
    protected $fillable = [
        'secret_id', 'tenant_id', 'action', 'accessed_by',
        'ip_address', 'context',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
        ];
    }

    public function secret(): BelongsTo
    {
        return $this->belongsTo(TenantSecret::class, 'secret_id');
    }
}
