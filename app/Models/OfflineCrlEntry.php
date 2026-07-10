<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperOfflineCrlEntry
 */
class OfflineCrlEntry extends Model
{
    protected $fillable = [
        'offline_certificate_id',
        'license_key',
        'reason',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'revoked_at' => 'datetime',
        ];
    }

    public function certificate(): BelongsTo
    {
        return $this->belongsTo(OfflineCertificate::class, 'offline_certificate_id');
    }
}
