<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\CustomDomain;
use App\Models\SiteSetting;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DomainOverviewController extends Controller
{
    public function __construct() {}

    /**
     * 域名管理总览
     */
    public function overview(): JsonResponse
    {
        // 平台域名配置
        $canonicalDomain = SiteSetting::where('key', 'seo_canonical_domain')->value('value');
        $cdnUrl = SiteSetting::where('key', 'cdn_url')->value('value');
        $cdnEnabled = SiteSetting::where('key', 'cdn_enabled')->value('value');

        // 租户域名统计
        $tenantDomainCount = Tenant::whereNotNull('domain')->where('domain', '!=', '')->count();
        $tenantTotal = Tenant::count();

        // 自定义域名统计
        $customDomainTotal = CustomDomain::count();
        $customDomainVerified = CustomDomain::where('verified', true)->count();
        $customDomainActive = CustomDomain::where('is_active', true)->count();
        $customDomainPending = CustomDomain::where('verified', false)->count();
        $customDomainFailed = CustomDomain::where('status', 'failed')->count();
        $customDomainExpired = CustomDomain::where('status', 'expired')->count();

        // SSL 统计
        $sslTotal = \App\Models\SslCertificate::count();
        $sslIssued = \App\Models\SslCertificate::where('status', 'issued')->count();
        $sslExpiring = \App\Models\SslCertificate::where('status', 'issued')
            ->where('expires_at', '<', now()->addDays(30))
            ->count();
        $sslFailed = \App\Models\SslCertificate::where('status', 'failed')->count();

        // 最近绑定的自定义域名（含 SSL 和路由信息）
        $recentDomains = CustomDomain::with(['tenant:id,name,slug', 'sslCertificate', 'domainRoute'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->toArray();

        // 无域名的租户
        $tenantsWithoutDomain = Tenant::whereNull('domain')
            ->orWhere('domain', '')
            ->select('id', 'name', 'slug', 'created_at')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->toArray();

        return ApiResponse::success([
            'platform' => [
                'canonical_domain' => $canonicalDomain ?? 'https://88.huwutong.com',
                'cdn_url' => $cdnUrl ?? '',
                'cdn_enabled' => (bool) $cdnEnabled,
            ],
            'tenants' => [
                'total' => $tenantTotal,
                'with_domain' => $tenantDomainCount,
                'without_domain' => $tenantTotal - $tenantDomainCount,
                'without_domain_list' => $tenantsWithoutDomain,
            ],
            'custom_domains' => [
                'total' => $customDomainTotal,
                'verified' => $customDomainVerified,
                'active' => $customDomainActive,
                'pending' => $customDomainPending,
                'failed' => $customDomainFailed,
                'expired' => $customDomainExpired,
                'recent' => $recentDomains,
            ],
            'ssl' => [
                'total' => $sslTotal,
                'issued' => $sslIssued,
                'expiring_soon' => $sslExpiring,
                'failed' => $sslFailed,
            ],
        ]);
    }

    /**
     * 更新平台域名配置
     */
    public function updatePlatform(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'canonical_domain' => 'nullable|string|max:255',
            'cdn_url' => 'nullable|string|max:255',
            'cdn_enabled' => 'nullable|boolean',
        ]);

        if (isset($validated['canonical_domain'])) {
            SiteSetting::updateOrCreate(
                ['key' => 'seo_canonical_domain'],
                ['value' => $validated['canonical_domain'], 'group' => 'seo', 'type' => 'text', 'description' => '权威域名', 'is_public' => true]
            );
        }
        if (isset($validated['cdn_url'])) {
            SiteSetting::updateOrCreate(
                ['key' => 'cdn_url'],
                ['value' => $validated['cdn_url'], 'group' => 'storage', 'type' => 'text', 'description' => 'CDN 域名', 'is_public' => true]
            );
        }
        if (isset($validated['cdn_enabled'])) {
            SiteSetting::updateOrCreate(
                ['key' => 'cdn_enabled'],
                ['value' => $validated['cdn_enabled'] ? '1' : '0', 'group' => 'storage', 'type' => 'switch', 'description' => '启用 CDN 加速', 'is_public' => true]
            );
        }

        return ApiResponse::success(null, __("app.domain_overview.msg_abbebf1f"));
    }

    /**
     * 更新租户默认域名
     */
    public function updateTenantDomain(Request $request, int $tenantId): JsonResponse
    {
        $validated = $request->validate([
            'domain' => 'nullable|string|max:255',
        ]);

        $tenant = Tenant::findOrFail($tenantId);
        $tenant->domain = $validated['domain'] ?? null;
        $tenant->save();

        return ApiResponse::success($tenant, __("app.domain_overview.msg_ba060a68"));
    }

    /**
     * 域名解析 + SSL 状态检查
     */
    public function dnsStatus(): JsonResponse
    {
        $domains = CustomDomain::with(['tenant:id,name,slug', 'sslCertificate'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $statuses = $domains->map(function ($d) {
            $dnsOk = $this->checkDnsRecord($d->domain, $d->cname_target);
            $ssl = $d->sslCertificate;
            $sslExpiresAt = $ssl?->expires_at;
            $sslDaysLeft = $sslExpiresAt ? now()->diffInDays($sslExpiresAt, false) : null;
            $sslValid = $ssl && $ssl->status === 'issued' && $sslDaysLeft !== null && $sslDaysLeft > 0;

            // 综合健康状态
            $health = 'healthy';
            if (!$d->verified) $health = 'unverified';
            elseif (!$dnsOk) $health = 'dns_error';
            elseif (!$sslValid) $health = 'ssl_issue';
            elseif ($sslDaysLeft !== null && $sslDaysLeft < 30) $health = 'ssl_expiring_soon';

            return [
                'id' => $d->id,
                'domain' => $d->domain,
                'tenant' => $d->tenant?->name ?? '-',
                'cname_target' => $d->cname_target,
                'dns_resolved' => $dnsOk,
                'verified' => $d->verified,
                'ssl_status' => $ssl?->status ?? 'none',
                'ssl_issuer' => $ssl?->issuer ?? '-',
                'ssl_expires_at' => $sslExpiresAt?->toDateString(),
                'ssl_days_left' => $sslDaysLeft,
                'ssl_valid' => $sslValid,
                'health' => $health,
                'status' => $d->status,
                'is_active' => $d->is_active,
            ];
        });

        return ApiResponse::success($statuses);
    }

    /**
     * 获取所有域名列表（含搜索/过滤）
     */
    public function domainList(Request $request): JsonResponse
    {
        $query = CustomDomain::with(['tenant:id,name,slug', 'sslCertificate', 'domainRoute']);

        // 搜索
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('domain', 'like', "%{$search}%")
                  ->orWhereHas('tenant', fn($t) => $t->where('name', 'like', "%{$search}%"));
            });
        }

        // 状态过滤
        if ($status = $request->input('status')) {
            if ($status === 'verified') $query->where('verified', true);
            elseif ($status === 'pending') $query->where('verified', false);
            elseif ($status === 'active') $query->where('is_active', true);
            elseif ($status === 'failed') $query->where('status', 'failed');
        }

        $domains = $query->orderByDesc('created_at')
            ->paginate($request->input('per_page', 20));

        return ApiResponse::success($domains);
    }

    /**
     * 触发 SSL 证书续期
     */
    public function renewSsl(int $domainId): JsonResponse
    {
        $domain = CustomDomain::with('sslCertificate')->findOrFail($domainId);
        $ssl = $domain->sslCertificate;

        if (!$ssl) {
            return ApiResponse::error('NOT_FOUND', __("app.domain_overview.msg_688551d8"), 404);
        }

        $ssl->status = 'renewing';
        $ssl->save();

        // 实际续期由 AcmeService 后台处理，此处仅触发标记
        return ApiResponse::success(null, __("app.domain_overview.msg_269dc962"));
    }

    /**
     * 批量 SSL 续期
     */
    public function batchRenewSsl(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'domain_ids' => 'required|array|min:1|max:50',
            'domain_ids.*' => 'integer|exists:custom_domains,id',
        ]);

        $updated = \App\Models\SslCertificate::whereIn('custom_domain_id', $validated['domain_ids'])
            ->where('status', 'issued')
            ->update(['status' => 'renewing']);

        return ApiResponse::success(['renewed' => $updated], __("app.domain_overview.msg_372535d9"));
    }

    /**
     * 简单的 DNS 检查
     */
    private function checkDnsRecord(string $domain, ?string $expectedCname): bool
    {
        if (!$expectedCname) return false;
        try {
            $records = @dns_get_record($domain, DNS_CNAME);
            foreach ($records as $r) {
                if (isset($r['target']) && rtrim($r['target'], '.') === rtrim($expectedCname, '.')) {
                    return true;
                }
            }
            // 也检查 A 记录
            $aRecords = @dns_get_record($domain, DNS_A);
            return !empty($aRecords);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
