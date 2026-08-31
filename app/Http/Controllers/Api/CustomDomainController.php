<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\CustomDomain;
use App\Models\SslCertificate;
use App\Services\CnameService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * 自定义域名 + SSL 管理 API
 *
 * M1.4-35 自定义域名 CNAME 绑定
 * M1.4-36 SSL 证书自动管理
 */
class CustomDomainController extends Controller
{
    public function __construct(
        protected CnameService $cnameService,
    ) {}

    /**
     * 获取域名列表
     *
     * GET /api/domains
     */
    public function index(Request $request): JsonResponse
    {
        $query = $request->user()->tenant->customDomains()
            ->with('sslCertificate', 'domainRoute');

        // 搜索
        if ($search = $request->input('search')) {
            $query->where('domain', 'like', "%{$search}%");
        }
        // 状态过滤
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($request->boolean('verified_only')) {
            $query->where('verified', true);
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $paginated = $query->latest()->paginate($perPage);

        $paginated->getCollection()->transform(
            fn($d) => $this->cnameService->getDomainStatus($d),
        );

        return ApiResponse::paginated($paginated);
    }

    /**
     * 创建域名绑定
     *
     * POST /api/domains
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'domain' => 'required|string|max:255|unique:custom_domains,domain',
            'target_url' => 'nullable|url|max:500',
        ]);

        $tenant = $request->user()->tenant;

        // 检查域名格式
        $domain = strtolower(trim($data['domain']));
        if (! preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/', $domain)) {
            return ApiResponse::validationError(__('app.api.custom_domain.invalid_format'));
        }

        // 检查是否已存在
        if (CustomDomain::where('domain', $domain)->exists()) {
            return ApiResponse::error('DOMAIN_EXISTS', __('app.api.custom_domain.domain_exists'), 422);
        }

        $customDomain = $this->cnameService->bindDomain(
            $tenant,
            $domain,
            $data['target_url'] ?? null,
        );

        return ApiResponse::created(
            $this->cnameService->getDomainStatus($customDomain),
            __('app.api.custom_domain.binding_success'),
        );
    }

    /**
     * 获取域名详情
     *
     * GET /api/domains/{domain}
     */
    public function show(int $domainId, Request $request): JsonResponse
    {
        $domain = $request->user()->tenant->customDomains()
            ->with('sslCertificate', 'domainRoute')
            ->findOrFail($domainId);

        return ApiResponse::success(
            $this->cnameService->getDomainStatus($domain)
        );
    }

    /**
     * 验证域名所有权
     *
     * POST /api/domains/{domain}/verify
     */
    public function verify(int $domainId, Request $request): JsonResponse
    {
        $domain = $request->user()->tenant->customDomains()
            ->findOrFail($domainId);

        if ($domain->verified) {
            return ApiResponse::success(
                $this->cnameService->getDomainStatus($domain),
                __('app.api.custom_domain.verified'),
            );
        }

        $success = $this->cnameService->verifyDomain($domain);

        if ($success) {
            // 验证通过后自动申请 SSL 证书
            $this->cnameService->issueCertificate($domain);
        }

        return ApiResponse::success(
            $this->cnameService->getDomainStatus($domain),
            $success ? __('app.api.custom_domain.verify_success') : __('app.api.custom_domain.verify_failed'),
        );
    }

    /**
     * 申请/续期 SSL 证书
     *
     * POST /api/domains/{domain}/ssl/issue
     */
    public function issueSsl(int $domainId, Request $request): JsonResponse
    {
        $domain = $request->user()->tenant->customDomains()
            ->findOrFail($domainId);

        if (! $domain->verified) {
            return ApiResponse::error('DOMAIN_NOT_VERIFIED', __('app.api.custom_domain.domain_not_verified'), 422);
        }

        $success = $this->cnameService->issueCertificate($domain);

        return ApiResponse::success(
            $this->cnameService->getDomainStatus($domain),
            $success ? __('app.api.custom_domain.ssl_issued') : __('app.api.custom_domain.ssl_issue_failed'),
        );
    }

    /**
     * 查看域名 DNS 信息
     *
     * GET /api/domains/{domain}/dns
     */
    public function dnsInfo(int $domainId, Request $request): JsonResponse
    {
        $domain = $request->user()->tenant->customDomains()
            ->findOrFail($domainId);

        $dnsInfo = $this->cnameService->getDnsInfo($domain->domain);

        return ApiResponse::success([
            'domain' => $domain->domain,
            'expected_cname' => CnameService::CNAME_TARGET,
            'dns' => $dnsInfo,
        ]);
    }

    /**
     * 更新域名路由配置
     *
     * PUT /api/domains/{domain}/route
     */
    public function updateRoute(int $domainId, Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => 'required|string|in:reverse_proxy,redirect,static',
            'target_url' => 'nullable|url|max:500',
            'config' => 'nullable|array',
        ]);

        $domain = $request->user()->tenant->customDomains()
            ->findOrFail($domainId);

        $route = $domain->domainRoute;
        if (! $route) {
            return ApiResponse::notFound(__('app.api.custom_domain.route_not_found'));
        }

        $route->update([
            'type' => $data['type'],
            'target_url' => $data['target_url'] ?? $route->target_url,
            'config' => $data['config'] ?? $route->config,
        ]);

        return ApiResponse::success($route, __('app.api.custom_domain.route_updated'));
    }

    /**
     * 上传自有 SSL 证书 + 私钥
     *
     * POST /api/domains/{domain}/ssl/upload
     */
    public function uploadSsl(int $domainId, Request $request): JsonResponse
    {
        $data = $request->validate([
            'certificate' => 'required|string|min:64|max:65535',
            'private_key' => 'required|string|min:64|max:65535',
            'certificate_chain' => 'nullable|string|max:65535',
        ]);

        $domain = $request->user()->tenant->customDomains()
            ->with('sslCertificate')
            ->findOrFail($domainId);

        if (! $domain->verified) {
            return ApiResponse::error('DOMAIN_NOT_VERIFIED', __('app.api.custom_domain.domain_not_verified'), 422);
        }

        $cert = trim($data['certificate']);
        $key  = trim($data['private_key']);

        // 验证 PEM 格式
        if (! $this->isValidPem($cert, 'CERTIFICATE')) {
            return ApiResponse::validationError(__('app.api.custom_domain.invalid_cert_pem'));
        }
        if (! $this->isValidPem($key, 'PRIVATE KEY')) {
            return ApiResponse::validationError(__('app.api.custom_domain.invalid_key_pem'));
        }

        // 通过 openssl 验证证书与私钥匹配
        $certResource = openssl_x509_read($cert);
        if (! $certResource) {
            return ApiResponse::validationError(__('app.api.custom_domain.cert_parse_failed'));
        }
        $keyResource = openssl_pkey_get_private($key);
        if (! $keyResource) {
            return ApiResponse::validationError(__('app.api.custom_domain.key_parse_failed'));
        }
        if (! openssl_x509_check_private_key($certResource, $keyResource)) {
            return ApiResponse::validationError(__('app.api.custom_domain.cert_key_mismatch'));
        }

        // 读取证书有效期
        $certInfo = openssl_x509_parse($certResource);
        $validFrom  = $certInfo['validFrom_time_t'] ?? null;
        $validTo    = $certInfo['validTo_time_t'] ?? null;
        $issuerName = $certInfo['issuer']['CN'] ?? ($certInfo['issuer']['O'] ?? 'Unknown');

        $ssl = $domain->sslCertificate;
        if (! $ssl) {
            $ssl = SslCertificate::create([
                'custom_domain_id' => $domain->id,
                'issuer' => $issuerName,
                'auto_renew' => false,
                'status' => 'pending',
            ]);
        }

        $ssl->update([
            'certificate'       => Crypt::encryptString($cert),
            'private_key'       => Crypt::encryptString($key),
            'certificate_chain' => ! empty($data['certificate_chain']) ? Crypt::encryptString(trim($data['certificate_chain'])) : $ssl->certificate_chain,
            'issuer'            => $issuerName,
            'issued_at'         => $validFrom ? date_create("@{$validFrom}") : now(),
            'expires_at'        => $validTo   ? date_create("@{$validTo}")   : now()->addYear(),
            'status'            => 'issued',
            'auto_renew'        => false, // 用户自传证书不自动续期
            'last_renewed_at'   => now(),
            'error_message'     => null,
        ]);

        // 激活自定义域名
        $domain->update([
            'is_active' => true,
            'status'    => 'active',
        ]);

        Log::info('用户上传 SSL 证书成功', [
            'domain_id' => $domain->id,
            'domain'    => $domain->domain,
            'issuer'    => $issuerName,
            'expires_at' => $ssl->expires_at,
        ]);

        return ApiResponse::success(
            $this->cnameService->getDomainStatus($domain->fresh()),
            __('app.api.custom_domain.ssl_uploaded'),
        );
    }

    /**
     * 验证字符串是否为合法 PEM 格式
     */
    private function isValidPem(string $content, string $expectedType): bool
    {
        $pattern = "/-----BEGIN\s+(?:RSA\s+|EC\s+)?{$expectedType}-----\s*.+?\s*-----END\s+(?:RSA\s+|EC\s+)?{$expectedType}-----/s";
        return (bool) preg_match($pattern, $content);
    }

    /**
     * 删除域名绑定
     *
     * DELETE /api/domains/{domain}
     */
    public function destroy(int $domainId, Request $request): JsonResponse
    {
        $domain = $request->user()->tenant->customDomains()
            ->findOrFail($domainId);

        $domain->delete();

        return ApiResponse::success(null, __('app.api.custom_domain.domain_deleted'));
    }
}
