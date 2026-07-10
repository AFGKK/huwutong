<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperEnterpriseIdp
 */
class EnterpriseIdp extends Model
{
    protected $table = 'enterprise_idps';

    protected $fillable = [
        'tenant_id', 'sso_provider_id', 'name', 'provider_type', 'is_active',
        'idp_metadata_xml', 'idp_entity_id', 'idp_sso_url', 'idp_slo_url', 'idp_x509_certificate',
        'sp_entity_id', 'sp_acs_url', 'sp_audience_uri', 'name_id_format',
        'encrypt_assertion', 'sign_authn_requests', 'authn_request_timeout', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'encrypt_assertion' => 'boolean',
            'sign_authn_requests' => 'boolean',
            'metadata' => 'array',
        ];
    }

    const PROVIDER_TYPES = [
        'okta' => 'Okta',
        'azure_ad' => 'Azure AD',
        'onelogin' => 'OneLogin',
        'generic_saml' => 'Generic SAML 2.0',
    ];

    const NAME_ID_FORMATS = [
        'email' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress',
        'unspecified' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified',
        'persistent' => 'urn:oasis:names:tc:SAML:2.0:nameid-format:persistent',
        'transient' => 'urn:oasis:names:tc:SAML:2.0:nameid-format:transient',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function ssoProvider(): BelongsTo { return $this->belongsTo(SsoProvider::class, 'sso_provider_id'); }
    public function domainRoutes(): HasMany { return $this->hasMany(IdpDomainRoute::class, 'idp_id'); }
    public function groupMappings(): HasMany { return $this->hasMany(IdpGroupMapping::class, 'idp_id'); }
    public function jitRules(): HasMany { return $this->hasMany(JitProvisioningRule::class, 'idp_id'); }
    public function healthLogs(): HasMany { return $this->hasMany(IdpHealthLog::class, 'idp_id'); }
}
