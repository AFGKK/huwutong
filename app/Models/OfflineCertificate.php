<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperOfflineCertificate
 */
class OfflineCertificate extends Model
{
    protected $fillable = [
        'tenant_id',
        'key_version',
        'algorithm',
        'public_key',
        'seed_encrypted',
        'is_active',
        'is_revoked',
        'revoked_at',
        'revoked_reason',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_revoked' => 'boolean',
            'revoked_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function crlEntries(): HasMany
    {
        return $this->hasMany(OfflineCrlEntry::class, 'offline_certificate_id');
    }
}
