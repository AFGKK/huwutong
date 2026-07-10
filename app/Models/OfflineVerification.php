<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperOfflineVerification
 */
class OfflineVerification extends Model
{
    protected $table = 'offline_verifications';

    protected $fillable = [
        'license_id',
        'license_key',
        'result',
        'detail',
        'client_ip',
        'client_version',
    ];

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }
}
