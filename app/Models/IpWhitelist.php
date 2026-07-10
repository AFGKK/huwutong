<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperIpWhitelist
 */
class IpWhitelist extends Model
{
    protected $fillable = [
        'tenant_id', 'ip_address', 'label', 'type',
        'is_active', 'hit_count', 'last_hit_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_hit_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
