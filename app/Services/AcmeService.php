<?php

namespace App\Services;

use App\Models\CustomDomain;
use App\Models\SslCertificate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * ACME 证书签发服务
 *
 * 使用 Let's Encrypt ACME HTTP-01 验证自动签发 SSL 证书。
 * 生成 ACME 验证文件 → Nginx 响应验证 → 获取 CA 签发的正式证书。
 *
 * 依赖：
 *   - PHP OpenSSL extension (openssl_pkey_new, openssl_csr_new)
 *   - Nginx 或反向代理配置了 .well-known/acme-challenge/ 的访问路径
 */
class AcmeService
{
    const PRODUCTION_DIRECTORY = 'https://acme-v02.api.letsencrypt.org/directory';
    const STAGING_DIRECTORY = 'https://acme-staging-v02.api.letsencrypt.org/directory';
    const CHALLENGE_CHECK_INTERVAL = 2; // 检查验证的间隔（秒）
    const CHALLENGE_MAX_RETRIES = 15;     // 最大检查次数（~30秒超时）

    /**
     * ACME 账户邮箱
     */
    protected string $accountEmail;

    /**
     * ACME 目录 URL
     */
    protected string $directoryUrl;

    /**
     * ACME Nonce
     */
    protected ?string $nonce = null;

    public function __construct()
    {
        $this->accountEmail = config('services.acme.email', 'admin@huwutong.com');
        $this->directoryUrl = config('services.acme.staging', false)
            ? self::STAGING_DIRECTORY
            : self::PRODUCTION_DIRECTORY;
    }

    /**
     * 为主域名签发证书
     *
     * @return array{success: bool, certificate?: string, private_key?: string, chain?: string, error?: string}
     */
    public function issueForDomain(CustomDomain $customDomain): array
    {
        $domain = $customDomain->domain;
        $ssl = $customDomain->sslCertificate;

        if (! $ssl) {
            return ['success' => false, 'error' => 'SSL 证书记录不存在'];
        }

        try {
            // 1. 加载或创建 ACME 账户密钥
            $accountKey = $this->loadOrCreateAccountKey();

            // 2. 注册 ACME 账户（如果未注册）
            $this->registerAccount($accountKey);

            // 3. 创建订单
            $order = $this->createOrder($accountKey, [$domain]);

            // 4. 完成 HTTP-01 挑战
            $challenge = $this->getHttpChallenge($order, $accountKey);

            // 5. 记录 ACME 挑战信息（供 Nginx 响应验证）
            $ssl->update([
                'acme_challenge_token' => $challenge['token'],
                'acme_challenge_content' => $challenge['key_authorization'],
                'status' => 'renewing',
                'error_message' => '正在验证域名所有权，请确保 .well-known/acme-challenge/ 可访问',
            ]);

            // 6. 通知 Nginx 响应验证（或等待外部 HTTP 服务器）
            $this->notifyChallengeReady($domain, $challenge['token'], $challenge['key_authorization']);

            // 7. 通知 ACME 验证已就绪
            $this->respondToChallenge($accountKey, $challenge['url']);

            // 8. 轮询等待验证结果
            $order = $this->pollOrderStatus($accountKey, $order['url']);

            if (($order['status'] ?? '') !== 'ready' && ($order['status'] ?? '') !== 'valid') {
                return [
                    'success' => false,
                    'error' => '域名验证失败: ' . ($order['error']['detail'] ?? '未知错误'),
                ];
            }

            // 9. 生成 CSR
            $csr = $this->generateCsr($accountKey, $domain);

            // 10. 最终化订单，获取证书
            $result = $this->finalizeOrder($accountKey, $order['finalize'], $csr);

            if (! $result['success']) {
                return $result;
            }

            // 11. 下载证书
            $certificateChain = $this->downloadCertificate($accountKey, $result['certificate_url']);

            // 12. 提取证书和私钥
            $privateKeyPem = $this->getDomainPrivateKeyPem($domain);

            $ssl->update([
                'certificate' => Crypt::encryptString($certificateChain['fullchain']),
                'private_key' => Crypt::encryptString($privateKeyPem),
                'certificate_chain' => Crypt::encryptString($certificateChain['chain'] ?? ''),
                'issued_at' => now(),
                'expires_at' => now()->addDays(89),
                'status' => 'issued',
                'last_renewed_at' => now(),
                'acme_challenge_token' => null,
                'acme_challenge_content' => null,
                'error_message' => null,
            ]);

            // 激活自定义域名
            $customDomain->update([
                'is_active' => true,
                'status' => 'active',
                'error_message' => null,
            ]);

            Log::info('ACME: 证书签发成功', ['domain' => $domain]);

            return [
                'success' => true,
                'certificate' => $certificateChain['fullchain'],
                'expires_at' => $ssl->fresh()->expires_at,
            ];

        } catch (\Throwable $e) {
            $errorMsg = '证书签发失败: ' . $e->getMessage();
            Log::error('ACME: ' . $errorMsg, ['domain' => $domain, 'trace' => $e->getTraceAsString()]);

            $ssl->update([
                'status' => 'failed',
                'error_message' => $errorMsg,
            ]);

            $customDomain->update([
                'status' => 'failed',
                'error_message' => $errorMsg,
            ]);

            return ['success' => false, 'error' => $errorMsg];
        }
    }

    // ================================================================
    // ACME 协议实现
    // ================================================================

    /**
     * 加载或创建 ACME 账户密钥
     */
    protected function loadOrCreateAccountKey(): array
    {
        $stored = Cache::get('acme_account_key');
        if ($stored) {
            return $stored;
        }

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privateKey);
        $details = openssl_pkey_get_details($key);

        $accountKey = [
            'private_key' => $privateKey,
            'public_key' => $details['key'],
        ];

        Cache::forever('acme_account_key', $accountKey);

        return $accountKey;
    }

    /**
     * 注册 ACME 账户
     */
    protected function registerAccount(array $accountKey): void
    {
        $payload = ['termsOfServiceAgreed' => true, 'contact' => ["mailto:{$this->accountEmail}"]];
        $this->signedPost('newAccount', $payload, $accountKey);
    }

    /**
     * 创建 ACME 订单
     */
    protected function createOrder(array $accountKey, array $domains): array
    {
        $identifiers = array_map(fn($d) => ['type' => 'dns', 'value' => $d], $domains);
        return $this->signedPost('newOrder', ['identifiers' => $identifiers], $accountKey);
    }

    /**
     * 获取 HTTP-01 挑战信息
     */
    protected function getHttpChallenge(array $order, array $accountKey): array
    {
        $authUrl = $order['authorizations'][0] ?? null;
        if (! $authUrl) {
            throw new \RuntimeException('订单未包含授权 URL');
        }

        $auth = $this->signedGet($authUrl, $accountKey);

        foreach ($auth['challenges'] ?? [] as $challenge) {
            if ($challenge['type'] === 'http-01') {
                $token = $challenge['token'];
                $thumbprint = $this->getKeyThumbprint($accountKey);
                $keyAuthorization = "{$token}.{$thumbprint}";

                return [
                    'token' => $token,
                    'key_authorization' => $keyAuthorization,
                    'url' => $challenge['url'],
                ];
            }
        }

        throw new \RuntimeException('未找到 HTTP-01 挑战');
    }

    /**
     * 通知 ACME 验证已准备就绪
     */
    protected function respondToChallenge(array $accountKey, string $challengeUrl): void
    {
        $this->signedPostByUrl($challengeUrl, [], $accountKey);
    }

    /**
     * 轮询订单状态
     */
    protected function pollOrderStatus(array $accountKey, string $orderUrl): array
    {
        for ($i = 0; $i < self::CHALLENGE_MAX_RETRIES; $i++) {
            sleep(self::CHALLENGE_CHECK_INTERVAL);
            $order = $this->signedGet($orderUrl, $accountKey);

            $status = $order['status'] ?? '';
            if (in_array($status, ['ready', 'valid'])) {
                return $order;
            }
            if ($status === 'invalid') {
                throw new \RuntimeException(
                    '验证失败: ' . ($order['error']['detail'] ?? 'ACME 拒绝')
                );
            }
        }

        throw new \RuntimeException('验证超时');
    }

    /**
     * 生成 CSR
     */
    protected function generateCsr(array $accountKey, string $domain): string
    {
        $dn = [
            'commonName' => $domain,
            'organizationName' => 'Huwutong',
        ];

        $san = "DNS:{$domain}";
        $privKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $csr = openssl_csr_new($dn, $privKey, ['digest_alg' => 'sha256']);

        if (! $csr) {
            throw new \RuntimeException('CSR 生成失败');
        }

        // 缓存私钥
        openssl_pkey_export($privKey, $domainKeyPem);
        Cache::forever("acme_domain_key_{$domain}", $domainKeyPem);

        // 导出 PEM 后转 DER
        openssl_csr_export($csr, $csrPem);
        $csrDer = $this->pemToDer($csrPem);

        return $this->base64UrlEncode($csrDer);
    }

    /**
     * 最终化订单
     */
    protected function finalizeOrder(array $accountKey, string $finalizeUrl, string $csr): array
    {
        $result = $this->signedPostByUrl($finalizeUrl, ['csr' => $csr], $accountKey);

        if (($result['status'] ?? '') === 'valid') {
            return [
                'success' => true,
                'certificate_url' => $result['certificate'],
            ];
        }

        // 等待最终化完成
        for ($i = 0; $i < 10; $i++) {
            sleep(2);
            $result = $this->signedGet($finalizeUrl, $accountKey);

            if (($result['status'] ?? '') === 'valid') {
                return [
                    'success' => true,
                    'certificate_url' => $result['certificate'],
                ];
            }
            if (($result['status'] ?? '') === 'invalid') {
                throw new \RuntimeException(
                    '证书最终化失败: ' . ($result['error']['detail'] ?? '未知')
                );
            }
        }

        throw new \RuntimeException('证书最终化超时');
    }

    /**
     * 下载证书
     */
    protected function downloadCertificate(array $accountKey, string $certUrl): array
    {
        $response = Http::withOptions(['verify' => false])->get($certUrl);
        $pem = $response->body();

        // 分割完整证书链
        $certs = preg_split('/-----END CERTIFICATE-----/', $pem);
        $fullchain = '';
        $chain = '';

        foreach ($certs as $i => $cert) {
            $cert = trim($cert);
            if (empty($cert)) continue;
            $cert .= "\n-----END CERTIFICATE-----\n";
            if ($i === 0) {
                $fullchain = $cert;
            } else {
                $fullchain .= $cert;
                $chain .= $cert;
            }
        }

        return [
            'fullchain' => $fullchain,
            'chain' => $chain,
        ];
    }

    /**
     * 获取域名的私钥 PEM
     */
    protected function getDomainPrivateKeyPem(string $domain): string
    {
        return Cache::get("acme_domain_key_{$domain}", '');
    }

    // ================================================================
    // HTTP 签名请求
    // ================================================================

    /**
     * 签发 ACME JWS 请求（POST by path）
     */
    protected function signedPost(string $path, array $payload, array $accountKey): array
    {
        $dir = $this->getDirectory();
        $url = $dir[$path] ?? $this->directoryUrl($path);
        return $this->signedPostByUrl($url, $payload, $accountKey);
    }

    /**
     * 签发 ACME JWS 请求（POST by URL）
     */
    protected function signedPostByUrl(string $url, array $payload, array $accountKey): array
    {
        $nonce = $this->getNonce();
        $protected = [
            'alg' => 'RS256',
            'kid' => $this->getAccountKid($accountKey),
            'nonce' => $nonce,
            'url' => $url,
        ];

        $payloadEncoded = $this->base64UrlEncode(json_encode($payload));
        $protectedEncoded = $this->base64UrlEncode(json_encode($protected));
        $signature = $this->sign("{$protectedEncoded}.{$payloadEncoded}", $accountKey['private_key']);

        $jws = [
            'protected' => $protectedEncoded,
            'payload' => $payloadEncoded,
            'signature' => $this->base64UrlEncode($signature),
        ];

        $response = Http::withOptions(['verify' => false])
            ->withHeaders(['Content-Type' => 'application/jose+json'])
            ->post($url, $jws);

        $this->nonce = $response->header('Replay-Nonce');

        return $response->json() ?? [];
    }

    /**
     * ACME GET 请求
     */
    protected function signedGet(string $url, array $accountKey): array
    {
        $response = Http::withOptions(['verify' => false])
            ->withHeaders(['Accept' => 'application/json'])
            ->get($url);

        $this->nonce = $response->header('Replay-Nonce');

        return $response->json() ?? [];
    }

    /**
     * 获取 Nonce
     */
    protected function getNonce(): string
    {
        if ($this->nonce) {
            return $this->nonce;
        }

        $dir = $this->getDirectory();
        $nonceUrl = $dir['newNonce'] ?? $this->directoryUrl('/acme/new-nonce');

        $response = Http::withOptions(['verify' => false])
            ->head($nonceUrl);

        $nonce = $response->header('Replay-Nonce');
        if (! $nonce) {
            throw new \RuntimeException('无法获取 ACME Nonce');
        }

        return $nonce;
    }

    /**
     * 获取 ACME 目录数据
     */
    protected function getDirectory(): array
    {
        $dir = Cache::get('acme_directory');
        if ($dir) {
            return $dir;
        }

        $response = Http::withOptions(['verify' => false])->get($this->directoryUrl);
        $dir = $response->json() ?? [];

        Cache::forever('acme_directory', $dir);

        return $dir;
    }

    /**
     * ACME 目录 URL
     */
    protected function directoryUrl(string $path = ''): string
    {
        return $this->directoryUrl . $path;
    }

    /**
     * 获取账户 KID
     */
    protected function getAccountKid(array $accountKey): string
    {
        $cached = Cache::get('acme_account_kid');
        if ($cached) {
            return $cached;
        }

        // 新注册时，从响应中获取 KID
        throw new \RuntimeException('尚未注册 ACME 账户 KID');
    }

    /**
     * RSA 签名
     */
    protected function sign(string $data, string $privateKey): string
    {
        $signature = '';
        openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        return $signature;
    }

    /**
     * 获取 JWK 的 Thumbprint（用于 HTTP-01 keyAuthorization）
     */
    protected function getKeyThumbprint(array $accountKey): string
    {
        $publicKey = openssl_pkey_get_public($accountKey['public_key']);
        $details = openssl_pkey_get_details($publicKey);

        $jwk = [
            'e' => $this->base64UrlEncode($details['rsa']['e']),
            'kty' => 'RSA',
            'n' => $this->base64UrlEncode($details['rsa']['n']),
        ];

        $thumbprint = hash('sha256', json_encode($jwk), true);
        return $this->base64UrlEncode($thumbprint);
    }

    /**
     * 通知挑战文件已准备好
     */
    protected function notifyChallengeReady(string $domain, string $token, string $keyAuthorization): void
    {
        // 将验证文件写入 storage 供 Nginx 引用
        $challengeDir = storage_path("app/acme-challenges/{$domain}");
        if (! is_dir($challengeDir)) {
            mkdir($challengeDir, 0755, true);
        }

        file_put_contents("{$challengeDir}/{$token}", $keyAuthorization);

        Log::info('ACME: 验证文件已就绪', [
            'domain' => $domain,
            'path' => ".well-known/acme-challenge/{$token}",
        ]);
    }

    // ================================================================
    // 工具方法
    // ================================================================

    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    protected function pemToDer(string $pem): string
    {
        $lines = explode("\n", trim($pem));
        array_shift($lines); // 去掉 BEGIN
        array_pop($lines);   // 去掉 END
        return base64_decode(implode('', $lines));
    }
}
