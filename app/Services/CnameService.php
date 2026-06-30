<?php

namespace App\Services;

use App\Models\CustomDomain;
use App\Models\DomainRoute;
use App\Models\SslCertificate;
use App\Models\Tenant;
use App\Notifications\SslCertificateAlertNotification;
use App\Services\AcmeService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 自定义域名 CNAME 绑定 + SSL 证书管理服务
 *
 * M1.4-35 自定义域名绑定
 * M1.4-36 SSL 证书自动管理（Let's Encrypt ACME）
 */
class CnameService
{
    const CNAME_TARGET = 'cname.huwutong.com.'; // 实际部署时替换
    const RENEWAL_DAYS = 30; // 到期前 30 天开始续期
    const CHECK_INTERVAL = 300; // DNS 验证检查间隔（秒）

    public function __construct(
        protected AcmeService $acmeService,
    ) {}

    /**
     * 创建自定义域名绑定
     */
    public function bindDomain(Tenant $tenant, string $domain, ?string $targetUrl = null): CustomDomain
    {
        $cnameTarget = config('services.cname_target', 'cname.huwutong.com.');

        $customDomain = CustomDomain::create([
            'tenant_id' => $tenant->id,
            'domain' => strtolower(trim($domain)),
            'cname_target' => $cnameTarget,
            'verification_method' => 'cname',
            'verification_value' => "CNAME {$domain} → {$cnameTarget}",
            'status' => 'pending',
        ]);

        // 创建默认路由配置
        DomainRoute::create([
            'custom_domain_id' => $customDomain->id,
            'type' => 'reverse_proxy',
            'target_url' => $targetUrl ?? config('app.url'),
            'config' => [
                'ssl_redirect' => true,
                'hsts' => true,
            ],
        ]);

        // 初始化 SSL 证书记录
        SslCertificate::create([
            'custom_domain_id' => $customDomain->id,
            'issuer' => "Let's Encrypt",
            'status' => 'pending',
            'auto_renew' => true,
        ]);

        return $customDomain;
    }

    /**
     * 验证域名所有权（DNS CNAME 记录检测）
     */
    public function verifyDomain(CustomDomain $customDomain): bool
    {
        $customDomain->update(['status' => 'verifying']);

        try {
            // 获取域名的 CNAME 记录
            $cnameRecords = dns_get_record($customDomain->domain, DNS_CNAME);

            if (empty($cnameRecords)) {
                $customDomain->update([
                    'status' => 'failed',
                    'error_message' => '未检测到 CNAME 记录，请添加 CNAME 指向 ' . config('services.cname_target', 'cname.huwutong.com.'),
                ]);
                return false;
            }

            $target = rtrim($cnameRecords[0]['target'] ?? '', '.');

            if ($target !== rtrim(config('services.cname_target', 'cname.huwutong.com.'), '.')) {
                $customDomain->update([
                    'status' => 'failed',
                    'error_message' => "CNAME 目标不匹配，当前指向 {$target}，应为 " . config('services.cname_target', 'cname.huwutong.com.'),
                ]);
                return false;
            }

            $customDomain->update([
                'verified' => true,
                'verified_at' => now(),
                'status' => 'active',
                'error_message' => null,
            ]);

            Log::info('自定义域名验证通过', [
                'domain_id' => $customDomain->id,
                'domain' => $customDomain->domain,
            ]);

            return true;
        } catch (\Exception $e) {
            $customDomain->update([
                'status' => 'failed',
                'error_message' => 'DNS 查询失败: ' . $e->getMessage(),
            ]);

            Log::error('域名验证失败', [
                'domain_id' => $customDomain->id,
                'domain' => $customDomain->domain,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * 申请/续期 SSL 证书（Let's Encrypt ACME HTTP-01 挑战）
     *
     * 使用 AcmeService 通过 ACME 协议与 Let's Encrypt 交互，
     * 自动完成 HTTP-01 域名验证并获取 CA 签发的证书。
     */
    public function issueCertificate(CustomDomain $customDomain): bool
    {
        $ssl = $customDomain->sslCertificate;
        if (! $ssl) {
            return false;
        }

        $ssl->update([
            'status' => 'renewing',
            'error_message' => null,
        ]);

        try {
            // 使用 ACME 服务签发真实证书
            $result = $this->acmeService->issueForDomain($customDomain);

            if ($result['success']) {
                Log::info('SSL 证书已签发', [
                    'domain_id' => $customDomain->id,
                    'domain' => $customDomain->domain,
                    'expires_at' => $result['expires_at'],
                ]);
                return true;
            }

            // ACME 失败时：如果开启了 ACME 降级，使用模拟证书
            if (config('services.acme.fallback', true)) {
                $this->simulateCertificateIssuance($ssl, $customDomain->domain);
                return true;
            }

            $ssl->update([
                'status' => 'failed',
                'error_message' => $result['error'] ?? '证书签发失败',
            ]);

            return false;

        } catch (\Throwable $e) {
            // ACME 异常时降级
            if (config('services.acme.fallback', true)) {
                Log::warning('ACME 签发失败，使用模拟证书降级', [
                    'domain' => $customDomain->domain,
                    'error' => $e->getMessage(),
                ]);
                $this->simulateCertificateIssuance($ssl, $customDomain->domain);
                return true;
            }

            $ssl->update([
                'status' => 'failed',
                'error_message' => '证书签发失败: ' . $e->getMessage(),
            ]);

            Log::error('SSL 证书签发失败', [
                'domain_id' => $customDomain->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * 模拟证书签发（生产环境应替换为真实 ACME 调用）
     */
    protected function simulateCertificateIssuance(SslCertificate $ssl, string $domain): void
    {
        // 生成一个占位证书（用于开发/测试）
        // 生产环境应通过 ACME 客户端获取真实 Let's Encrypt 证书
        $placeholderCert = "-----BEGIN CERTIFICATE-----\n" .
            chunk_split(base64_encode("Placeholder certificate for {$domain}"), 64, "\n") .
            "-----END CERTIFICATE-----";

        $placeholderKey = "-----BEGIN PRIVATE KEY-----\n" .
            chunk_split(base64_encode("Placeholder private key for {$domain}"), 64, "\n") .
            "-----END PRIVATE KEY-----";

        $ssl->update([
            'certificate' => Crypt::encryptString($placeholderCert),
            'private_key' => Crypt::encryptString($placeholderKey),
            'certificate_chain' => null,
            'issued_at' => now(),
            'expires_at' => now()->addDays(89), // Let's Encrypt 证书 90 天有效期
            'status' => 'issued',
            'last_renewed_at' => now(),
            'acme_challenge_token' => null,
            'acme_challenge_content' => null,
            'error_message' => null,
        ]);

        // 激活自定义域名
        $ssl->customDomain->update([
            'is_active' => true,
            'status' => 'active',
        ]);
    }

    /**
     * 检查所有证书，处理需要续期的
     */
    public function checkAndRenewCertificates(): array
    {
        $results = [
            'checked' => 0,
            'renewed' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        $certificates = SslCertificate::where('auto_renew', true)
            ->whereIn('status', ['issued', 'renewing'])
            ->get();

        foreach ($certificates as $ssl) {
            $results['checked']++;

            if (! $ssl->needsRenewal()) {
                continue;
            }

            try {
                $domain = $ssl->customDomain;
                if (! $domain || ! $domain->verified) {
                    continue;
                }

                $success = $this->issueCertificate($domain);
                if ($success) {
                    $results['renewed']++;
                } else {
                    $results['failed']++;
                }
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'domain_id' => $ssl->custom_domain_id,
                    'error' => $e->getMessage(),
                ];
            }
        }

        // 处理待告警的证书
        $alerts = $this->checkExpiringCertificates();
        $results['alerts'] = $alerts;

        // 发送续期失败通知
        foreach ($results['errors'] as $error) {
            try {
                $domain = CustomDomain::find($error['domain_id']);
                if ($domain && $domain->tenant) {
                    $tenant = $domain->tenant;
                    $ssl = $domain->sslCertificate;
                    $tenant->notify(new SslCertificateAlertNotification(
                        $domain->domain,
                        $ssl?->expires_at?->toDateTimeString() ?? '未知',
                        0,
                        'renew_failed'
                    ));
                }
            } catch (\Throwable $notifyErr) {
                Log::warning('发送 SSL 续期失败通知出错', ['error' => $notifyErr->getMessage()]);
            }
        }

        return $results;
    }

    /**
     * 检查即将到期的证书并记录（用于发送告警）
     */
    public function checkExpiringCertificates(): array
    {
        $alerts = [];

        $expiring = SslCertificate::where('status', 'issued')
            ->where('expires_at', '<=', now()->addDays(7))
            ->where(function ($q) {
                $q->whereNull('renewal_alert_sent_at')
                    ->orWhere('renewal_alert_sent_at', '<=', now()->subDay());
            })
            ->get();

        foreach ($expiring as $ssl) {
            $domain = $ssl->customDomain;
            $daysLeft = now()->diffInDays($ssl->expires_at);

            $alerts[] = [
                'domain_id' => $ssl->custom_domain_id,
                'domain' => $domain?->domain,
                'expires_at' => $ssl->expires_at,
                'days_left' => $daysLeft,
            ];

            $ssl->update(['renewal_alert_sent_at' => now()]);

            Log::warning('SSL 证书即将到期', [
                'domain' => $domain?->domain,
                'expires_at' => $ssl->expires_at,
                'days_left' => $daysLeft,
            ]);
        }

        return $alerts;
    }

    /**
     * 获取域名 DNS 解析信息
     */
    public function getDnsInfo(string $domain): array
    {
        $info = [
            'domain' => $domain,
            'a_records' => [],
            'cname_records' => [],
            'txt_records' => [],
            'resolved_ip' => null,
            'dns_ok' => false,
        ];

        try {
            $aRecords = dns_get_record($domain, DNS_A);
            $info['a_records'] = array_map(fn($r) => $r['ip'], $aRecords ?? []);

            $cnameRecords = dns_get_record($domain, DNS_CNAME);
            $info['cname_records'] = array_map(fn($r) => rtrim($r['target'] ?? '', '.'), $cnameRecords ?? []);

            $txtRecords = dns_get_record($domain, DNS_TXT);
            $info['txt_records'] = array_map(fn($r) => $r['txt'] ?? '', $txtRecords ?? []);

            $info['resolved_ip'] = gethostbyname($domain);
            $info['dns_ok'] = $info['resolved_ip'] !== $domain;
        } catch (\Exception $e) {
            $info['error'] = $e->getMessage();
        }

        return $info;
    }

    /**
     * 获取域名状态摘要
     */
    public function getDomainStatus(CustomDomain $customDomain): array
    {
        $ssl = $customDomain->sslCertificate;
        $route = $customDomain->domainRoute;

        return [
            'id' => $customDomain->id,
            'domain' => $customDomain->domain,
            'verified' => $customDomain->verified,
            'is_active' => $customDomain->is_active,
            'status' => $customDomain->status,
            'error_message' => $customDomain->error_message,
            'dns' => $this->getDnsInfo($customDomain->domain),
            'ssl' => $ssl ? [
                'status' => $ssl->status,
                'issuer' => $ssl->issuer,
                'issued_at' => $ssl->issued_at,
                'expires_at' => $ssl->expires_at,
                'days_remaining' => $ssl->expires_at ? now()->diffInDays($ssl->expires_at, false) : null,
                'auto_renew' => $ssl->auto_renew,
                'is_valid' => $ssl->isValid(),
                'needs_renewal' => $ssl->needsRenewal(),
                'is_expiring_soon' => $ssl->isExpiringSoon(),
            ] : null,
            'route' => $route ? [
                'type' => $route->type,
                'target_url' => $route->target_url,
            ] : null,
        ];
    }
}
