<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SsoProvider extends Model
{
    protected $fillable = [
        'tenant_id', 'name', 'provider_type', 'is_active',
        'idp_entity_id', 'idp_login_url', 'idp_logout_url', 'idp_x509_certificate',
        'sp_entity_id', 'sp_acs_url',
        'client_id', 'client_secret', 'authorization_url', 'token_url',
        'userinfo_url', 'jwks_url', 'scopes',
        'attribute_mapping', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'attribute_mapping' => 'array',
            'metadata' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function connections(): HasMany
    {
        return $this->hasMany(SsoConnection::class);
    }
}
