<?php

namespace App\Services;

use App\Models\EnterpriseIdp;
use App\Models\SsoProvider;
use App\Models\JitProvisioningRule;
use App\Models\IdpGroupMapping;
use App\Models\IdpHealthLog;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EnterpriseSsoService
{
    /**
     * 解析 IdP Metadata XML → 提取 IdP 配置
     */
    public function parseIdpMetadata(string $xml): array
    {
        $dom = new \DOMDocument();
        $loaded = @$dom->loadXML($xml);
        if (!$loaded) {
            throw new \InvalidArgumentException('Invalid IdP Metadata XML');
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('md', 'urn:oasis:names:tc:SAML:2.0:metadata');
        $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

        $result = [];

        // Entity ID
        $entityNodes = $xpath->query('//md:EntityDescriptor');
        if ($entityNodes->length > 0) {
            $result['idp_entity_id'] = $entityNodes->item(0)->getAttribute('entityID');
        }

        // SSO URL
        $ssoNodes = $xpath->query('//md:SingleSignOnService[@Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect"]');
        if ($ssoNodes->length > 0) {
            $result['idp_sso_url'] = $ssoNodes->item(0)->getAttribute('Location');
        }

        // SLO URL
        $sloNodes = $xpath->query('//md:SingleLogoutService[@Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect"]');
        if ($sloNodes->length > 0) {
            $result['idp_slo_url'] = $sloNodes->item(0)->getAttribute('Location');
        }

        // X.509 Certificate
        $certNodes = $xpath->query('//ds:X509Certificate');
        if ($certNodes->length > 0) {
            $pem = "-----BEGIN CERTIFICATE-----\n" .
                chunk_split($certNodes->item(0)->textContent, 64, "\n") .
                "-----END CERTIFICATE-----";
            $result['idp_x509_certificate'] = $pem;
        }

        return $result;
    }

    /**
     * 生成 SP Metadata XML (供 IdP 导入)
     */
    public function generateSpMetadata(EnterpriseIdp $idp): string
    {
        $entityId = $idp->sp_entity_id ?? "https://{$idp->tenant->slug}.huwutong.com/saml/metadata";
        $acsUrl = $idp->sp_acs_url ?? "https://{$idp->tenant->slug}.huwutong.com/saml/acs";
        $audience = $idp->sp_audience_uri ?? $entityId;

        $xml = <<<XML
<?xml version="1.0"?>
<md:EntityDescriptor xmlns:md="urn:oasis:names:tc:SAML:2.0:metadata"
    entityID="{$entityId}">
    <md:SPSSODescriptor AuthnRequestsSigned="{$this->boolToString($idp->sign_authn_requests)}"
        protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol">
        <md:NameIDFormat>{$this->getNameIdFormatUrn($idp->name_id_format)}</md:NameIDFormat>
        <md:AssertionConsumerService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST"
            Location="{$acsUrl}" index="0" isDefault="true"/>
    </md:SPSSODescriptor>
    <md:Organization>
        <md:OrganizationName xml:lang="en">{$this->escapeXml($idp->name)}</md:OrganizationName>
        <md:OrganizationDisplayName xml:lang="en">{$this->escapeXml($idp->name)}</md:OrganizationDisplayName>
    </md:Organization>
</md:EntityDescriptor>
XML;

        return $xml;
    }

    /**
     * JIT 创建/更新用户
     */
    public function jitProvisionUser(EnterpriseIdp $idp, array $samlAttributes, string $externalId): User
    {
        $rule = JitProvisioningRule::where('idp_id', $idp->id)->where('is_active', true)->first();
        if (!$rule || !$rule->auto_create_users) {
            throw new \RuntimeException('JIT provisioning is not enabled for this IdP');
        }

        $mapping = $rule->attribute_mapping ?? [
            'email' => 'email',
            'firstName' => 'name',
            'lastName' => 'last_name',
        ];

        // 从 SAML 断言中提取属性
        $email = $samlAttributes[$mapping['email'] ?? 'email'] ?? null;
        $name = $samlAttributes[$mapping['firstName'] ?? 'firstName'] ?? $externalId;

        if (!$email) {
            throw new \RuntimeException('Email attribute is required for JIT provisioning');
        }

        // 域名过滤
        if ($rule->email_domain_filter) {
            $domain = substr(strrchr($email, '@'), 1);
            if ($domain !== $rule->email_domain_filter) {
                throw new \RuntimeException("Email domain {$domain} not allowed");
            }
        }

        $user = User::where('email', $email)->first();

        if (!$user) {
            // 自动创建用户
            $user = User::create([
                'tenant_id' => $idp->tenant_id,
                'name' => $name,
                'email' => $email,
                'password' => bcrypt(Str::random(32)),
                'email_verified_at' => now(),
            ]);

            // 分配默认角色
            $roleName = $rule->default_role ?? 'user';
            try {
                $role = \Spatie\Permission\Models\Role::findByName($roleName);
                $user->assignRole($role);
            } catch (\Exception $e) {
                Log::warning("Default role {$roleName} not found", ['error' => $e->getMessage()]);
            }

            Log::info("JIT user created", ['email' => $email, 'idp' => $idp->name]);
        } elseif ($rule->auto_update_attributes) {
            // 更新属性
            $user->update(['name' => $name]);
        }

        // 应用组映射
        $idpGroups = $samlAttributes['groups'] ?? $samlAttributes['memberOf'] ?? [];
        if (is_string($idpGroups)) {
            $idpGroups = explode(',', $idpGroups);
        }
        $this->applyGroupMappings($idp, $user, (array) $idpGroups);

        return $user;
    }

    /**
     * 应用 IdP 组映射到本地角色
     */
    protected function applyGroupMappings(EnterpriseIdp $idp, User $user, array $idpGroups): void
    {
        if (empty($idpGroups)) return;

        $mappings = IdpGroupMapping::where('idp_id', $idp->id)
            ->where('is_active', true)
            ->get();

        foreach ($mappings as $mapping) {
            if (in_array($mapping->idp_group_name, $idpGroups)) {
                try {
                    $role = \Spatie\Permission\Models\Role::findByName($mapping->local_role);
                    if (!$user->hasRole($role)) {
                        $user->assignRole($role);
                        Log::info("Group mapping applied", ['user' => $user->email, 'role' => $mapping->local_role]);
                    }
                } catch (\Exception $e) {
                    Log::warning("Group mapping failed", ['role' => $mapping->local_role]);
                }
            }
        }
    }

    /**
     * 根据邮箱域名查找 IdP
     */
    public function resolveIdpByEmail(string $email, int $tenantId): ?EnterpriseIdp
    {
        $domain = substr(strrchr($email, '@'), 1);
        if (!$domain) return null;

        $route = \App\Models\IdpDomainRoute::where('tenant_id', $tenantId)
            ->where('domain', $domain)
            ->first();

        return $route?->idp;
    }

    /**
     * 执行 IdP 健康检查
     */
    public function checkIdpHealth(EnterpriseIdp $idp): array
    {
        $startTime = microtime(true);
        $result = ['is_healthy' => false, 'message' => ''];

        try {
            $ch = curl_init($idp->idp_sso_url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_NOBODY => true,
            ]);
            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            $responseTime = round((microtime(true) - $startTime) * 1000);

            $result['is_healthy'] = $httpCode > 0 && empty($error);
            $result['message'] = $result['is_healthy']
                ? "IdP responded with HTTP {$httpCode} in {$responseTime}ms"
                : "IdP unreachable: {$error}";
        } catch (\Exception $e) {
            $result['message'] = 'Health check failed: ' . $e->getMessage();
        }

        IdpHealthLog::create([
            'tenant_id' => $idp->tenant_id,
            'idp_id' => $idp->id,
            'check_type' => 'connectivity',
            'is_healthy' => $result['is_healthy'],
            'message' => $result['message'],
            'checked_at' => now(),
        ]);

        $idp->update(['metadata' => array_merge($idp->metadata ?? [], ['last_health_check' => now()->toDateTimeString()])]);

        return $result;
    }

    protected function boolToString(?bool $value): string { return $value ? 'true' : 'false'; }

    protected function getNameIdFormatUrn(?string $format): string
    {
        return \App\Models\EnterpriseIdp::NAME_ID_FORMATS[$format]
            ?? 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress';
    }

    protected function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
