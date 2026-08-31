<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomDomain;
use App\Models\SslCertificate;
use App\Services\AcmeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class SslCertificateController extends Controller
{
    public function __construct(
        protected AcmeService $acmeService,
    ) {}

    /**
     * 检查管理员权限
     */
    protected function ensureAdmin(): void
    {
        if (Gate::denies('admin')) {
            abort(403, __('app.api.ssl_cert.admin_required'));
        }
    }

    /**
     * 证书列表
     */
    public function index(Request $request): JsonResponse
    {
        $this->ensureAdmin();

        $query = SslCertificate::with(['customDomain.tenant'])->orderBy('created_at', 'desc');

        // 按域名搜索
        if ($search = $request->input('search')) {
            $query->whereHas('customDomain', function ($q) use ($search) {
                $q->where('domain', 'like', "%{$search}%");
            });
        }

        // 按状态筛选
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $certificates = $query->paginate($request->input('per_page', 20));

        $certificates->getCollection()->transform(function ($c) {
            return [
                'id' => $c->id,
                'domain' => $c->customDomain?->domain ?? '-',
                'tenant_id' => $c->customDomain?->tenant_id,
                'tenant_name' => $c->customDomain?->tenant?->name ?? '-',
                'issuer' => $c->issuer,
                'status' => $c->status,
                'auto_renew' => $c->auto_renew,
                'is_valid' => $c->isValid(),
                'needs_renewal' => $c->needsRenewal(),
                'expiring_soon' => $c->isExpiringSoon(),
                'issued_at' => $c->issued_at?->toIso8601String(),
                'expires_at' => $c->expires_at?->toIso8601String(),
                'last_renewed_at' => $c->last_renewed_at?->toIso8601String(),
                'error_message' => $c->error_message,
                'created_at' => $c->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $certificates,
        ]);
    }

    /**
     * 证书详情
     */
    public function show(SslCertificate $sslCertificate): JsonResponse
    {
        $this->ensureAdmin();

        $sslCertificate->load('customDomain.tenant');

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $sslCertificate->id,
                'domain' => $sslCertificate->customDomain?->domain ?? '-',
                'tenant_id' => $sslCertificate->customDomain?->tenant_id,
                'tenant_name' => $sslCertificate->customDomain?->tenant?->name ?? '-',
                'issuer' => $sslCertificate->issuer,
                'status' => $sslCertificate->status,
                'auto_renew' => $sslCertificate->auto_renew,
                'is_valid' => $sslCertificate->isValid(),
                'needs_renewal' => $sslCertificate->needsRenewal(),
                'expiring_soon' => $sslCertificate->isExpiringSoon(),
                'issued_at' => $sslCertificate->issued_at?->toIso8601String(),
                'expires_at' => $sslCertificate->expires_at?->toIso8601String(),
                'last_renewed_at' => $sslCertificate->last_renewed_at?->toIso8601String(),
                'error_message' => $sslCertificate->error_message,
                'renewal_alert_sent_at' => $sslCertificate->renewal_alert_sent_at?->toIso8601String(),
                'created_at' => $sslCertificate->created_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * 创建新的 SSL 证书（通过 ACME 签发）
     */
    public function store(Request $request): JsonResponse
    {
        $this->ensureAdmin();

        $validator = Validator::make($request->all(), [
            'custom_domain_id' => 'required|integer|exists:custom_domains,id',
            'auto_renew' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $customDomain = CustomDomain::findOrFail($request->input('custom_domain_id'));

        // 检查是否已有证书
        if ($customDomain->sslCertificate) {
            return response()->json([
                'success' => false,
                'message' => __('app.api.ssl_cert.existing_cert'),
            ], 422);
        }

        // 创建证书记录（状态：pending）
        $certificate = SslCertificate::create([
            'custom_domain_id' => $customDomain->id,
            'status' => 'pending',
            'auto_renew' => $request->input('auto_renew', true),
            'issuer' => 'Let\'s Encrypt',
        ]);

        // 异步签发（通过队列或同步，此处先返回创建成功，签发过程由任务驱动）
        // 实际场景建议 dispatch(new IssueSslCertificateJob($certificate))
        // 同步尝试签发：
        try {
            $result = $this->acmeService->issueForDomain($customDomain);
            if (! $result['success']) {
                $certificate->update([
                    'status' => 'failed',
                    'error_message' => $result['error'] ?? __('app.api.ssl_cert.issue_failed'),
                ]);
            }
        } catch (\Throwable $e) {
            $certificate->update([
                'status' => 'failed',
                'error_message' => __('app.api.ssl_cert.issue_exception', ['error' => $e->getMessage()]),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => __('app.api.ssl_cert.cert_submitted'),
            'data' => $certificate->fresh()->load('customDomain'),
        ]);
    }

    /**
     * 更新证书（主要用于 auto_renew 切换）
     */
    public function update(Request $request, SslCertificate $sslCertificate): JsonResponse
    {
        $this->ensureAdmin();

        $validator = Validator::make($request->all(), [
            'auto_renew' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        if ($request->has('auto_renew')) {
            $sslCertificate->update(['auto_renew' => $request->boolean('auto_renew')]);
        }

        return response()->json([
            'success' => true,
            'message' => __('app.api.ssl_cert.cert_updated'),
            'data' => $sslCertificate->fresh(),
        ]);
    }

    /**
     * 手动触发续期
     */
    public function renew(SslCertificate $sslCertificate): JsonResponse
    {
        $this->ensureAdmin();

        $customDomain = $sslCertificate->customDomain;
        if (! $customDomain) {
            return response()->json(['success' => false, 'message' => __('app.api.ssl_cert.domain_not_found')], 404);
        }

        $sslCertificate->update([
            'status' => 'renewing',
            'error_message' => null,
        ]);

        try {
            $result = $this->acmeService->issueForDomain($customDomain);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => __('app.api.ssl_cert.renewal_success'),
                    'data' => $sslCertificate->fresh(),
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? __('app.api.ssl_cert.renewal_failed'),
            ], 502);
        } catch (\Throwable $e) {
            $sslCertificate->update([
                'status' => 'failed',
                'error_message' => __('app.api.ssl_cert.renewal_exception', ['error' => $e->getMessage()]),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('app.api.ssl_cert.renewal_exception', ['error' => $e->getMessage()]),
            ], 502);
        }
    }

    /**
     * 吊销证书（数据库软吊销，不会向 CA 提交 OCSP 撤销请求）
     */
    public function revoke(Request $request, SslCertificate $sslCertificate): JsonResponse
    {
        $this->ensureAdmin();

        $sslCertificate->update([
            'status' => 'revoked',
            'error_message' => $request->input('reason', __('app.api.ssl_cert.default_revoke_reason')),
        ]);

        return response()->json([
            'success' => true,
            'message' => __('app.api.ssl_cert.cert_revoked'),
        ]);
    }

    /**
     * 统计概览
     */
    public function stats(): JsonResponse
    {
        $this->ensureAdmin();

        $total = SslCertificate::count();
        $issued = SslCertificate::where('status', 'issued')->count();
        $valid = SslCertificate::where('status', 'issued')
            ->where('issued_at', '<=', now())
            ->where('expires_at', '>', now())
            ->count();
        $expiringSoon = SslCertificate::where('status', 'issued')
            ->where('expires_at', '<=', now()->addDays(7))
            ->where('expires_at', '>', now())
            ->count();
        $needsRenewal = SslCertificate::where('status', 'issued')
            ->where('expires_at', '<=', now()->addDays(30))
            ->where('expires_at', '>', now())
            ->count();
        $failed = SslCertificate::where('status', 'failed')->count();
        $renewing = SslCertificate::where('status', 'renewing')->count();
        $pending = SslCertificate::where('status', 'pending')->count();

        // 即将到期证书列表
        $expiringCerts = SslCertificate::with('customDomain')
            ->where('status', 'issued')
            ->get()
            ->filter->isExpiringSoon()
            ->map(fn($c) => [
                'id' => $c->id,
                'domain' => $c->customDomain?->domain,
                'expires_at' => $c->expires_at?->toIso8601String(),
                'days_left' => $c->expires_at ? now()->diffInDays($c->expires_at, false) : 0,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'total_certificates' => $total,
                'issued' => $issued,
                'valid' => $valid,
                'expiring_soon' => $expiringSoon,
                'needs_renewal' => $needsRenewal,
                'failed' => $failed,
                'renewing' => $renewing,
                'pending' => $pending,
                'expiring_certificates' => $expiringCerts,
            ],
        ]);
    }

    /**
     * 公开证书内容（供 Nginx 使用）
     */
    public function certificateContent(SslCertificate $sslCertificate): JsonResponse
    {
        $this->ensureAdmin();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $sslCertificate->id,
                'domain' => $sslCertificate->customDomain?->domain,
                'issuer' => $sslCertificate->issuer,
                'status' => $sslCertificate->status,
                'is_valid' => $sslCertificate->isValid(),
                'issued_at' => $sslCertificate->issued_at?->toIso8601String(),
                'expires_at' => $sslCertificate->expires_at?->toIso8601String(),
                'certificate' => $sslCertificate->certificate ? Crypt::decryptString($sslCertificate->certificate) : null,
                'certificate_chain' => $sslCertificate->certificate_chain ? Crypt::decryptString($sslCertificate->certificate_chain) : null,
            ],
        ]);
    }
}
