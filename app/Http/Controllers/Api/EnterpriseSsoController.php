<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EnterpriseIdp;
use App\Models\IdpDomainRoute;
use App\Models\IdpGroupMapping;
use App\Models\JitProvisioningRule;
use App\Services\EnterpriseSsoService;
use Illuminate\Http\Request;

class EnterpriseSsoController extends Controller
{
    public function __construct(protected EnterpriseSsoService $enterpriseSso) {}

    // ── IdP 配置 ──

    public function idps()
    {
        $idps = EnterpriseIdp::withCount(['domainRoutes', 'groupMappings'])->orderByDesc('created_at')->get();
        return response()->json(['success' => true, 'data' => $idps]);
    }

    public function storeIdp(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'provider_type' => 'required|in:okta,azure_ad,onelogin,generic_saml',
            'idp_metadata_xml' => 'nullable|string',
            'name' => 'required|string|max:100',
            'name_id_format' => 'nullable|in:email,unspecified,persistent,transient',
            'encrypt_assertion' => 'boolean',
            'sign_authn_requests' => 'boolean',
        ]);

        $idp = EnterpriseIdp::create(array_merge($validated, [
            'tenant_id' => $request->user()->tenant_id ?? 1,
            'sp_entity_id' => $validated['sp_entity_id'] ?? null,
        ]));

        // 如果提供 Metadata XML，自动解析
        if (!empty($validated['idp_metadata_xml'])) {
            try {
                $parsed = $this->enterpriseSso->parseIdpMetadata($validated['idp_metadata_xml']);
                $idp->update($parsed);
            } catch (\Exception $e) {
                // 解析失败不阻止创建
            }
        }

        return response()->json(['success' => true, 'data' => $idp], 201);
    }

    public function updateIdp(Request $request, EnterpriseIdp $enterpriseIdp)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'is_active' => 'boolean',
            'idp_entity_id' => 'nullable|string',
            'idp_sso_url' => 'nullable|url',
            'idp_x509_certificate' => 'nullable|string',
        ]);
        $enterpriseIdp->update($validated);
        return response()->json(['success' => true, 'data' => $enterpriseIdp]);
    }

    public function destroyIdp(EnterpriseIdp $enterpriseIdp)
    {
        $enterpriseIdp->domainRoutes()->delete();
        $enterpriseIdp->groupMappings()->delete();
        $enterpriseIdp->jitRules()->delete();
        $enterpriseIdp->healthLogs()->delete();
        $enterpriseIdp->delete();
        return response()->json(['success' => true]);
    }

    // ── SP Metadata ──

    public function spMetadata(EnterpriseIdp $enterpriseIdp)
    {
        $xml = $this->enterpriseSso->generateSpMetadata($enterpriseIdp);
        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    public function parseMetadata(Request $request)
    {
        $validated = $request->validate(['metadata_xml' => 'required|string']);
        $parsed = $this->enterpriseSso->parseIdpMetadata($validated['metadata_xml']);
        return response()->json(['success' => true, 'data' => $parsed]);
    }

    // ── 域名路由 ──

    public function domainRoutes(EnterpriseIdp $enterpriseIdp)
    {
        return response()->json(['success' => true, 'data' => $enterpriseIdp->domainRoutes]);
    }

    public function storeDomainRoute(Request $request, EnterpriseIdp $enterpriseIdp)
    {
        $validated = $request->validate([
            'domain' => 'required|string|max:200',
            'is_primary' => 'boolean',
        ]);
        $route = $enterpriseIdp->domainRoutes()->create(array_merge($validated, [
            'tenant_id' => $request->user()->tenant_id ?? 1,
        ]));
        return response()->json(['success' => true, 'data' => $route], 201);
    }

    public function destroyDomainRoute(IdpDomainRoute $idpDomainRoute)
    {
        $idpDomainRoute->delete();
        return response()->json(['success' => true]);
    }

    // ── 组映射 ──

    public function groupMappings(EnterpriseIdp $enterpriseIdp)
    {
        return response()->json(['success' => true, 'data' => $enterpriseIdp->groupMappings]);
    }

    public function storeGroupMapping(Request $request, EnterpriseIdp $enterpriseIdp)
    {
        $validated = $request->validate([
            'idp_group_name' => 'required|string|max:200',
            'local_role' => 'required|string|max:100',
        ]);
        $mapping = $enterpriseIdp->groupMappings()->create(array_merge($validated, [
            'tenant_id' => $request->user()->tenant_id ?? 1,
        ]));
        return response()->json(['success' => true, 'data' => $mapping], 201);
    }

    // ── JIT 规则 ──

    public function jitRules(EnterpriseIdp $enterpriseIdp)
    {
        return response()->json(['success' => true, 'data' => $enterpriseIdp->jitRules]);
    }

    public function storeJitRule(Request $request, EnterpriseIdp $enterpriseIdp)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'default_role' => 'nullable|string|max:50',
            'auto_create_users' => 'boolean',
            'auto_update_attributes' => 'boolean',
            'email_domain_filter' => 'nullable|string|max:200',
            'attribute_mapping' => 'nullable|array',
        ]);
        $rule = $enterpriseIdp->jitRules()->create(array_merge($validated, [
            'tenant_id' => $request->user()->tenant_id ?? 1,
        ]));
        return response()->json(['success' => true, 'data' => $rule], 201);
    }

    // ── 健康检查 ──

    public function healthCheck(EnterpriseIdp $enterpriseIdp)
    {
        $result = $this->enterpriseSso->checkIdpHealth($enterpriseIdp);
        $logs = $enterpriseIdp->healthLogs()->orderByDesc('checked_at')->take(10)->get();
        return response()->json(['success' => true, 'data' => ['result' => $result, 'history' => $logs]]);
    }

    // ── 域名解析 ──

    public function resolveDomain(Request $request)
    {
        $validated = $request->validate(['email' => 'required|email']);
        $tenantId = $request->user()->tenant_id ?? 1;
        $idp = $this->enterpriseSso->resolveIdpByEmail($validated['email'], $tenantId);
        return response()->json(['success' => true, 'data' => $idp ? [
            'id' => $idp->id,
            'name' => $idp->name,
            'provider_type' => $idp->provider_type,
        ] : null]);
    }

    public function stats()
    {
        $tenantId = request()->user()->tenant_id ?? 1;
        return response()->json([
            'success' => true,
            'data' => [
                'total_idps' => EnterpriseIdp::where('tenant_id', $tenantId)->count(),
                'active_idps' => EnterpriseIdp::where('tenant_id', $tenantId)->where('is_active', true)->count(),
                'total_domains' => IdpDomainRoute::where('tenant_id', $tenantId)->count(),
                'total_mappings' => IdpGroupMapping::where('tenant_id', $tenantId)->count(),
                'total_jit_rules' => JitProvisioningRule::where('tenant_id', $tenantId)->count(),
            ],
        ]);
    }
}
