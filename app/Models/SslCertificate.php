<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperSslCertificate
 */
class SslCertificate extends Model
{
    protected $fillable = [
        'custom_domain_id', 'issuer', 'certificate', 'private_key',
        'certificate_chain', 'issued_at', 'expires_at', 'status',
        'acme_challenge_token', 'acme_challenge_content', 'auto_renew',
        'last_renewed_at', 'renewal_alert_sent_at', 'error_message',
    ];

    protected $hidden = [
        'certificate', 'private_key', 'certificate_chain',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'auto_renew' => 'boolean',
            'last_renewed_at' => 'datetime',
            'renewal_alert_sent_at' => 'datetime',
        ];
    }

    public function customDomain()
    {
        return $this->belongsTo(CustomDomain::class);
    }

    /**
     * 证书是否有效
     */
    public function isValid(): bool
    {
        return $this->status === 'issued'
            && $this->expires_at
            && $this->expires_at->isFuture()
            && $this->issued_at
            && $this->issued_at->isPast();
    }

    /**
     * 是否需要续期（到期前 30 天）
     */
    public function needsRenewal(): bool
    {
        if (! $this->expires_at || in_array($this->status, ['expired', 'revoked'])) {
            return true;
        }
        return $this->expires_at->copy()->subDays(30)->isPast();
    }

    /**
     * 是否即将到期（到期前 7 天，需要告警）
     */
    public function isExpiringSoon(): bool
    {
        if (! $this->expires_at || $this->status === 'revoked') {
            return false;
        }
        return $this->expires_at->copy()->subDays(7)->isPast();
    }
}
