<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TpmBinding extends Model
{
    protected $fillable = [
        'license_id', 'device_id',
        'tpm_manufacturer', 'tpm_version',
        'ek_public_key', 'ek_certificate', 'ak_public_key', 'ak_name',
        'pcr_values',
        'binding_type', 'status', 'failed_attempts', 'locked_until',
        'last_verified_at', 'last_attestation_at',
        'sgx_quote', 'sgx_tcb_level',
        'metadata', 'bound_ip', 'bound_user_agent', 'bound_at',
        'revoked_at', 'revoked_reason',
    ];

    protected $casts = [
        'pcr_values' => 'array',
        'metadata' => 'array',
        'locked_until' => 'datetime',
        'last_verified_at' => 'datetime',
        'last_attestation_at' => 'datetime',
        'bound_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function license(): BelongsTo { return $this->belongsTo(License::class); }
    public function device(): BelongsTo { return $this->belongsTo(Device::class); }
    public function verificationLogs(): HasMany { return $this->hasMany(TpmVerificationLog::class, 'tpm_binding_id'); }

    public function scopeActive($q) { return $q->where('status', 'active'); }

    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    public function recordFailure(): void
    {
        $maxAttempts = config('tpm.verification.failed_attempts_before_lock', 5);
        $lockoutMinutes = config('tpm.verification.lockout_duration_minutes', 30);

        $this->increment('failed_attempts');
        if ($this->failed_attempts >= $maxAttempts) {
            $this->update([
                'status' => 'locked',
                'locked_until' => now()->addMinutes($lockoutMinutes),
            ]);
        }
        $this->save();
    }

    public function resetFailures(): void
    {
        $this->update(['failed_attempts' => 0, 'locked_until' => null]);
    }
}
