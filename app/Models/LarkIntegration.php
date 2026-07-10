<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

/**
 * @mixin IdeHelperLarkIntegration
 */
class LarkIntegration extends Model
{
    use HasFactory;

    protected $table = 'lark_integrations';

    protected $fillable = [
        'tenant_id',
        'name',
        'is_enabled',
        'app_id',
        'app_secret',
        'encrypt_key',
        'verification_token',
        'bot_webhook_url',
        'notify_enabled',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'notify_enabled' => 'boolean',
            'tenant_token_expires_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * 获取解密后的 app_secret
     */
    public function getDecryptedAppSecret(): ?string
    {
        if (!$this->app_secret) return null;
        try {
            return Crypt::decryptString($this->app_secret);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 设置加密的 app_secret
     */
    public function setAppSecretAttribute(?string $value): void
    {
        $this->attributes['app_secret'] = $value ? Crypt::encryptString($value) : null;
    }

    /**
     * tenant_token 是否有效
     */
    public function isTenantTokenValid(): bool
    {
        return $this->tenant_token !== null
            && $this->tenant_token_expires_at !== null
            && $this->tenant_token_expires_at->isFuture();
    }
}
