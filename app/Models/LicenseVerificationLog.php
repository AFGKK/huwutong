<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenseVerificationLog extends Model
{
    protected $table = 'license_verification_logs';

    protected $fillable = [
        'license_id', 'license_key',
        'verifier_ip', 'verifier_fingerprint',
        'result', 'detail', 'signature_algorithm',
        'verification_data', 'is_sdk_verified', 'sdk_version',
    ];

    protected function casts(): array
    {
        return [
            'verification_data' => 'array',
            'is_sdk_verified' => 'boolean',
        ];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }
}
