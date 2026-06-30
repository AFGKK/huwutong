<?php

namespace App\Services;

use App\Models\License;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Edge 授权验证服务
 *
 * Cloudflare Workers 边缘节点 License 验证 (<10ms)
 * - 生成边缘验证 Token (HMAC-SHA256 签名)
 * - Token 缓存加速
 * - 吊销列表同步
 * - 回源降级支持
 *
 * @m3-53 EdgeVerifier
 */
class EdgeVerifierService
{
    /**
     * Token 签名算法
     */
    const SIGNATURE_ALGORITHM = 'HS256';

    /**
     * Token 默认有效期 (秒)
     */
    const DEFAULT_TOKEN_TTL = 3600; // 1 小时

    /**
     * 边缘缓存 TTL (秒)
     */
    const EDGE_CACHE_TTL = 300; // 5 分钟

    /**
     * 回源验证端点
     */
    const FALLBACK_ENDPOINT = '/api/edge/verify';

    /**
     * 获取边缘验证 Token 密钥
     */
    public function getSigningSecret(): string
    {
        $secret = config('edge-verifier.signing_secret');

        if (empty($secret)) {
            $secret = Crypt::encryptString(Str::random(64));
            // 注：生产环境应在 .env 中设置 EDGE_VERIFIER_SECRET
        }

        return $secret;
    }

    /**
     * 生成边缘验证 Token
     *
     * @param License $license
     * @param int $ttl Token 有效期 (秒)
     * @return array{token: string, expires_at: int, payload: array}
     */
    public function generateToken(License $license, int $ttl = self::DEFAULT_TOKEN_TTL): array
    {
        $now = time();
        $expiresAt = $now + $ttl;

        $payload = [
            'lic_key' => $license->license_key,
            'product_code' => $license->product?->code,
            'customer' => $license->customer ? [
                'id' => $license->customer->id,
                'name' => $license->customer->name,
            ] : null,
            'type' => $license->type,
            'status' => $license->status,
            'seats' => $license->seats,
            'max_devices' => $license->max_devices,
            'iat' => $now,
            'exp' => $expiresAt,
            'jti' => Str::uuid()->toString(),
            'iss' => config('app.url'),
        ];

        $token = $this->signToken($payload);

        // 缓存 Token 用于边缘节点快速验证
        $this->cacheToken($license->license_key, $payload);

        return [
            'token' => $token,
            'expires_at' => $expiresAt,
            'payload' => $payload,
        ];
    }

    /**
     * 验证边缘 Token
     *
     * @param string $token
     * @return array{valid: bool, data?: array, error?: string, message?: string}
     */
    public function verifyToken(string $token): array
    {
        try {
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return $this->error('INVALID_FORMAT', 'Token 格式无效');
            }

            [$headerB64, $payloadB64, $signatureB64] = $parts;

            // 解码 payload
            $payload = json_decode(base64_decode($payloadB64), true);
            if (!$payload || !isset($payload['lic_key'])) {
                return $this->error('INVALID_PAYLOAD', 'Token Payload 无效');
            }

            // 检查过期
            $now = time();
            if (isset($payload['exp']) && $now > $payload['exp']) {
                return $this->error('EXPIRED', 'Token 已过期', [
                    'expired_at' => $payload['exp'],
                    'now' => $now,
                ]);
            }

            // 验证签名
            $secret = $this->getSigningSecret();
            $msg = "{$headerB64}.{$payloadB64}";
            $expectedSig = $this->hmacSha256($msg, $secret);

            if (!hash_equals($expectedSig, $signatureB64)) {
                return $this->error('SIGNATURE_MISMATCH', 'Token 签名验证失败');
            }

            // 检查吊销状态
            $cacheKey = "edge:revoked:{$payload['lic_key']}";
            if (Cache::get($cacheKey)) {
                return $this->error('REVOKED', 'License 已被吊销');
            }

            return [
                'valid' => true,
                'data' => [
                    'license_key' => $payload['lic_key'],
                    'product_code' => $payload['product_code'] ?? null,
                    'customer' => $payload['customer'] ?? null,
                    'type' => $payload['type'] ?? null,
                    'status' => $payload['status'] ?? null,
                    'seats' => $payload['seats'] ?? null,
                    'issued_at' => $payload['iat'] ?? null,
                    'expires_at' => $payload['exp'] ?? null,
                ],
            ];
        } catch (\Throwable $e) {
            Log::error('Edge token verification failed', [
                'error' => $e->getMessage(),
            ]);

            return $this->error('VERIFY_ERROR', 'Token 验证异常');
        }
    }

    /**
     * 签名 Token (JWT-like HMAC-SHA256)
     */
    protected function signToken(array $payload): string
    {
        $header = [
            'alg' => self::SIGNATURE_ALGORITHM,
            'typ' => 'JWT',
            'kid' => config('edge-verifier.key_version', 1),
        ];

        $headerB64 = $this->base64UrlEncode(json_encode($header));
        $payloadB64 = $this->base64UrlEncode(json_encode($payload));
        $msg = "{$headerB64}.{$payloadB64}";

        $secret = $this->getSigningSecret();
        $signature = $this->hmacSha256($msg, $secret);

        return "{$msg}.{$signature}";
    }

    /**
     * HMAC-SHA256 签名
     */
    protected function hmacSha256(string $data, string $secret): string
    {
        return $this->base64UrlEncode(
            hash_hmac('sha256', $data, $secret, true)
        );
    }

    /**
     * Base64 URL 安全编码
     */
    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * 缓存 Token 到边缘节点
     */
    protected function cacheToken(string $licenseKey, array $payload): void
    {
        $cacheKey = "edge:token:{$licenseKey}";

        Cache::put($cacheKey, $payload, now()->addSeconds(self::EDGE_CACHE_TTL));
    }

    /**
     * 吊销 License 的边缘缓存
     */
    public function revokeCache(string $licenseKey): void
    {
        $cacheKey = "edge:revoked:{$licenseKey}";
        Cache::forever($cacheKey, [
            'revoked_at' => now()->toIso8601String(),
            'reason' => 'manual_revoke',
        ]);

        // 清除 Token 缓存
        Cache::forget("edge:token:{$licenseKey}");
    }

    /**
     * 获取边缘缓存状态
     */
    public function getCacheStatus(): array
    {
        $tokenCount = 0;
        $revokedCount = 0;

        // 注：实际环境中应使用 Redis SCAN 或专用计数器
        // 这里简化为读取配置数据
        $result = Cache::get('edge:cache:stats', [
            'token_count' => 0,
            'revoked_count' => 0,
            'last_sync' => null,
            'cache_hit_rate' => 0,
        ]);

        return [
            'token_count' => $tokenCount,
            'revoked_count' => $revokedCount,
            'edge_cache_ttl' => self::EDGE_CACHE_TTL,
            'token_ttl' => self::DEFAULT_TOKEN_TTL,
            'last_cache_update' => $result['last_sync'] ?? null,
            'cache_hit_rate' => $result['cache_hit_rate'] ?? 0,
            'signing_algorithm' => self::SIGNATURE_ALGORITHM,
            'key_version' => config('edge-verifier.key_version', 1),
        ];
    }

    /**
     * 同步吊销列表到边缘节点
     * 供 CRON 或 Webhook 调用
     */
    public function syncRevocationList(): array
    {
        $revokedLicenses = Cache::get('edge:revoked:list', []);

        // 清除过期的吊销记录
        $now = now();
        $active = [];

        foreach ($revokedLicenses as $key => $entry) {
            if (!isset($entry['expires_at']) || $now->isBefore($entry['expires_at'])) {
                $active[$key] = $entry;
            }
        }

        Cache::forever('edge:revoked:list', $active);

        return [
            'synced' => true,
            'active_revocations' => count($active),
            'synced_at' => $now->toIso8601String(),
        ];
    }

    /**
     * 批量生成边缘 Token
     *
     * @param array $licenseKeys
     * @return array
     */
    public function batchGenerateTokens(array $licenseKeys): array
    {
        $tokens = [];
        $errors = [];

        foreach ($licenseKeys as $key) {
            $license = License::where('license_key', $key)->first();

            if (!$license) {
                $errors[] = [
                    'license_key' => $key,
                    'error' => 'License 不存在',
                ];
                continue;
            }

            try {
                $tokens[] = $this->generateToken($license);
            } catch (\Throwable $e) {
                $errors[] = [
                    'license_key' => $key,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'tokens' => $tokens,
            'errors' => $errors,
            'total' => count($licenseKeys),
            'success' => count($tokens),
            'failed' => count($errors),
        ];
    }

    /**
     * 生成回源验证响应 (供 API 控制器使用)
     */
    public function originVerify(array $params): array
    {
        $licenseKey = $params['license_key'] ?? null;
        $token = $params['token'] ?? null;
        $productCode = $params['product_code'] ?? null;

        // 如果有 Token，优先验证 Token
        if ($token) {
            $result = $this->verifyToken($token);
            if ($result['valid']) {
                return $result;
            }
        }

        // Token 无效或不存在，通过 License Key 完整验证
        if (!$licenseKey) {
            return $this->error('MISSING_KEY', '请提供 license_key 或 token');
        }

        $license = License::where('license_key', $licenseKey)->first();

        if (!$license) {
            return $this->error('NOT_FOUND', 'License 不存在');
        }

        // 检查状态
        if ($license->status !== 'active') {
            return $this->error('INACTIVE', "License 状态异常: {$license->status}");
        }

        // 检查过期
        if ($license->expires_at && $license->expires_at->isPast()) {
            return $this->error('EXPIRED', 'License 已过期');
        }

        // 产品代码匹配
        if ($productCode && $license->product && $license->product->code !== $productCode) {
            return $this->error('PRODUCT_MISMATCH', "产品不匹配: {$license->product->code}");
        }

        // 生成新的 Token 并返回
        $tokenData = $this->generateToken($license);

        return [
            'valid' => true,
            'data' => [
                'license_key' => $license->license_key,
                'product_code' => $license->product?->code,
                'type' => $license->type,
                'status' => $license->status,
                'seats' => $license->seats,
                'max_devices' => $license->max_devices,
                'customer_name' => $license->customer?->name,
                'expires_at' => $license->expires_at?->toIso8601String(),
            ],
            'token' => $tokenData['token'],
            'token_expires_at' => $tokenData['expires_at'],
        ];
    }

    /**
     * 获取部署信息
     */
    public function getDeploymentInfo(): array
    {
        return [
            'worker_name' => 'hwt-edge-verifier',
            'worker_version' => '1.0.0',
            'compatibility_date' => '2025-12-01',
            'kv_namespace' => 'HWT_CACHE',
            'routes' => [
                'POST /api/edge/verify',
                'POST /api/edge/token',
                'GET /api/edge/health',
            ],
            'env_vars' => [
                'EDGE_CACHE_TTL' => (string) self::EDGE_CACHE_TTL,
                'FALLBACK_TIMEOUT' => '2000',
                'MAX_TOKEN_AGE_SECONDS' => (string) self::DEFAULT_TOKEN_TTL,
                'RATE_LIMIT_PER_MINUTE' => '1000',
            ],
            'origin_endpoint' => self::FALLBACK_ENDPOINT,
            'signing_algorithm' => self::SIGNATURE_ALGORITHM,
        ];
    }

    /**
     * 统一错误响应
     */
    protected function error(string $code, string $message, array $extra = []): array
    {
        return array_merge([
            'valid' => false,
            'error' => $code,
            'message' => $message,
        ], $extra);
    }
}
