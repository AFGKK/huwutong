<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperTpmVerificationLog
 */
class TpmVerificationLog extends Model
{
    protected $fillable = [
        'tpm_binding_id', 'result', 'quote_data', 'error_message',
        'duration_ms', 'ip_address', 'verified_at',
    ];

    protected $casts = [
        'quote_data' => 'array',
        'duration_ms' => 'float',
        'verified_at' => 'datetime',
    ];

    public function binding(): BelongsTo { return $this->belongsTo(TpmBinding::class, 'tpm_binding_id'); }
}
