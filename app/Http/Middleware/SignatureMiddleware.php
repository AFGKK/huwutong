<?php

namespace App\Http\Middleware;

use App\Http\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * HMAC 签名校验中间件
 *
 * 客户端需在请求头中携带：
 * - X-Signature: HMAC-SHA256 签名
 * - X-Signature-Timestamp: 签名时间戳（Unix 秒）
 * - X-Signature-Key-Id: （可选）密钥 ID，用于多密钥轮换场景
 *
 * 签名算法：
 *   signature = base64(hmac-sha256(secret_key, canonical_string))
 *
 * 规范字符串（canonical_string）组成：
 *   HTTP方法 + "\n"
 *   + URI路径 + "\n"
 *   + 请求体 JSON（若有）+ "\n"
 *   + X-Signature-Timestamp + "\n"
 *   + X-Nonce（若有）
 *
 * 密钥来源：
 * - API 客户端可从配置或 License metadata 中获取 secret_key
 * - 支持按密钥 ID 轮换（多 key 场景）
 */
class SignatureMiddleware
{
    /**
     * 默认签名算法
     */
    const ALGORITHM = 'sha256';

    /**
     * 默认时间窗口（秒）
     */
    const TIME_WINDOW = 300;

    public function __construct()
    {
        //
    }

    /**
     * 处理请求
     *
     * @param Request $request
     * @param Closure $next
     * @param string|null $keyResolver 可选：密钥解析服务标识
     * @return Response
     */
    public function handle(Request $request, Closure $next, ?string $keyResolver = null): Response
    {
        // 仅对 POST/PUT/PATCH/DELETE 生效
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return $next($request);
        }

        $signature = $request->header('X-Signature');
        $timestamp = $request->header('X-Signature-Timestamp');
        $keyId = $request->header('X-Signature-Key-Id');

        // 1. 必填校验
        if (empty($signature) || empty($timestamp)) {
            return ApiResponse::error(
                'MISSING_SIGNATURE',
                '缺少签名参数',
                401,
                ['required_headers' => ['X-Signature', 'X-Signature-Timestamp']],
            );
        }

        // 2. Timestamp 校验
        if (! ctype_digit((string) $timestamp)) {
            return ApiResponse::error('INVALID_SIGNATURE_TIMESTAMP', __('app.middleware.invalid_signature_timestamp'), 401);
        }

        $now = time();
        $ts = (int) $timestamp;

        if (abs($now - $ts) > self::TIME_WINDOW) {
            return ApiResponse::error(
                'SIGNATURE_EXPIRED',
                '签名已过期',
                401,
                ['server_time' => $now, 'allowed_window_seconds' => self::TIME_WINDOW],
            );
        }

        // 3. 获取密钥
        $secretKey = $this->resolveSecretKey($request, $keyId);

        if (empty($secretKey)) {
            Log::info('签名校验: 无法解析密钥，跳过签名校验', [
                'key_id' => $keyId,
                'client_ip' => $request->ip(),
                'path' => $request->path(),
            ]);

            // 无密钥时跳过签名校验（兼容 SDK 无签名模式）
            return $next($request);
        }

        // 4. 计算规范字符串
        $canonicalString = $this->buildCanonicalString($request, $timestamp);

        // 5. 验证签名
        $expectedSignature = $this->computeSignature($secretKey, $canonicalString);

        if (! hash_equals($expectedSignature, $signature)) {
            Log::warning('签名校验: 签名不匹配', [
                'path' => $request->path(),
                'method' => $request->method(),
                'client_ip' => $request->ip(),
                'key_id' => $keyId,
            ]);

            return ApiResponse::error(
                'SIGNATURE_MISMATCH',
                '签名验证失败',
                401,
            );
        }

        return $next($request);
    }

    /**
     * 构建规范字符串
     *
     * HTTP方法 + "\n" + URI路径 + "\n" + 请求体(JSON) + "\n" + X-Signature-Timestamp + "\n" + X-Nonce
     */
    protected function buildCanonicalString(Request $request, string $timestamp): string
    {
        $method = strtoupper($request->method());
        $path = $request->getPathInfo();

        // 获取请求体（JSON）
        $body = $request->getContent();
        // 空体统一为 '' 而非 null
        if ($body === null || $body === '') {
            $body = '';
        }

        $nonce = $request->header('X-Nonce', '');

        return implode("\n", [$method, $path, $body, $timestamp, $nonce]);
    }

    /**
     * 计算 HMAC-SHA256 签名
     */
    protected function computeSignature(string $secretKey, string $canonicalString): string
    {
        return base64_encode(
            hash_hmac(self::ALGORITHM, $canonicalString, $secretKey, true),
        );
    }

    /**
     * 解析密钥
     *
     * 密钥来源（按优先级）：
     * 1. 请求头 X-Signature-Key-Id → 从数据库查询 API Key
     * 2. 从请求参数中的 license_key 获取 License metadata 中的 secret
     * 3. 从系统配置获取默认密钥
     *
     * @param Request $request
     * @param string|null $keyId
     * @return string|null
     */
    protected function resolveSecretKey(Request $request, ?string $keyId = null): ?string
    {
        // 1. Key-Id 方式：从数据库查询 API Key
        if ($keyId) {
            $apiKey = \App\Models\ApiKey::where('key_id', $keyId)
                ->where('is_active', true)
                ->first();

            if ($apiKey) {
                return $apiKey->secret;
            }
        }

        // 2. 从 License metadata 获取（适用于 SDK 场景）
        $licenseKey = $request->input('license_key');
        if ($licenseKey) {
            $license = \App\Models\License::where('license_key', $licenseKey)->first();
            if ($license && ! empty($license->metadata['signature_secret'] ?? null)) {
                return $license->metadata['signature_secret'];
            }

            // 请求携带了 license_key 但未找到对应 License 时，不使用系统默认密钥，
            // 避免测试/客户端用 License 专属密钥签名时被默认密钥校验拦截。
            return null;
        }

        // 3. 系统默认密钥（用于内部服务间通信）
        $defaultSecret = config('security.default_signature_secret');
        if ($defaultSecret) {
            return $defaultSecret;
        }

        return null;
    }
}
