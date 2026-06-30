<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\CustomDomain;
use App\Models\SslCertificate;
use App\Services\CnameService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        $domains = $query->latest()
            ->get()
            ->map(fn($d) => $this->cnameService->getDomainStatus($d));

        return ApiResponse::success($domains);
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
            return ApiResponse::validationError('请输入有效的域名格式');
        }

        // 检查是否已存在
        if (CustomDomain::where('domain', $domain)->exists()) {
            return ApiResponse::error('DOMAIN_EXISTS', '该域名已被绑定', 422);
        }

        $customDomain = $this->cnameService->bindDomain(
            $tenant,
            $domain,
            $data['target_url'] ?? null,
        );

        return ApiResponse::created(
            $this->cnameService->getDomainStatus($customDomain),
            '域名绑定成功，请添加 CNAME 记录',
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
                '域名已验证通过',
            );
        }

        $success = $this->cnameService->verifyDomain($domain);

        if ($success) {
            // 验证通过后自动申请 SSL 证书
            $this->cnameService->issueCertificate($domain);
        }

        return ApiResponse::success(
            $this->cnameService->getDomainStatus($domain),
            $success ? '域名验证通过，正在申请 SSL 证书' : '域名验证失败',
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
            return ApiResponse::error('DOMAIN_NOT_VERIFIED', '请先验证域名所有权', 422);
        }

        $success = $this->cnameService->issueCertificate($domain);

        return ApiResponse::success(
            $this->cnameService->getDomainStatus($domain),
            $success ? 'SSL 证书已签发' : 'SSL 证书签发失败',
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
            return ApiResponse::notFound('路由配置不存在');
        }

        $route->update([
            'type' => $data['type'],
            'target_url' => $data['target_url'] ?? $route->target_url,
            'config' => $data['config'] ?? $route->config,
        ]);

        return ApiResponse::success($route, '路由配置已更新');
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

        return ApiResponse::success(null, '域名绑定已删除');
    }
}
