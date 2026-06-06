<?php

namespace Tests\Concerns;

/**
 * License Activation 测试辅助 Trait
 *
 * 提供构建带安全头的激活/验证请求的方法。
 */
trait LicenseActivationHelpers
{
    /**
     * 生成测试用的 Nonce（UUID v4 格式）
     */
    protected function generateNonce(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * 计算 HMAC 签名（与 SignatureMiddleware 算法一致）
     */
    protected function computeSignature(string $secret, string $method, string $path, array $body, string $timestamp, string $nonce = ''): string
    {
        $bodyStr = ! empty($body) ? json_encode($body) : '';
        $canonicalString = implode("\n", [$method, $path, $bodyStr, $timestamp, $nonce]);
        return base64_encode(hash_hmac('sha256', $canonicalString, $secret, true));
    }

    /**
     * 构建带安全头的激活请求头
     *
     * @param string $method 请求方法
     * @param string $path 请求路径
     * @param array $body 请求体
     * @param string|null $secret 签名密钥（默认使用签名的密钥）
     * @return array 请求头
     */
    protected function activationHeaders(string $method = 'POST', string $path = '', array $body = [], ?string $secret = null): array
    {
        $nonce = $this->generateNonce();
        $timestamp = (string) time();

        $signatureSecret = $secret ?? 'test-activation-secret';
        $signature = $this->computeSignature($signatureSecret, $method, $path, $body, $timestamp, $nonce);

        return [
            'X-Nonce' => $nonce,
            'X-Timestamp' => $timestamp,
            'X-Signature' => $signature,
            'X-Signature-Timestamp' => $timestamp,
        ];
    }

    /**
     * 使用指定的 nonce 和 timestamp 构建安全头（用于重放测试）
     */
    protected function fixedActivationHeaders(string $method, string $path, array $body, ?string $secret, string $nonce, string $timestamp): array
    {
        $signatureSecret = $secret ?? 'test-activation-secret';
        $signature = $this->computeSignature($signatureSecret, $method, $path, $body, $timestamp, $nonce);

        return [
            'X-Nonce' => $nonce,
            'X-Timestamp' => $timestamp,
            'X-Signature' => $signature,
            'X-Signature-Timestamp' => $timestamp,
        ];
    }

    /**
     * 发送带安全头的 POST 请求
     *
     * @param string $uri
     * @param array $data
     * @param array $options 可选参数，支持 'secret' 覆盖密钥、'idempotency_key'、及其他 headers
     */
    protected function securePostJson(string $uri, array $data = [], array $options = []): \Illuminate\Testing\TestResponse
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: $uri;
        $secret = $options['secret'] ?? null;
        $securityHeaders = $this->activationHeaders('POST', $path, $data, $secret);

        $idempotencyKey = $options['idempotency_key'] ?? null;
        if ($idempotencyKey) {
            $securityHeaders['X-Idempotency-Key'] = $idempotencyKey;
        }

        unset($options['secret'], $options['idempotency_key']);

        return $this->postJson($uri, $data, array_merge($securityHeaders, $options));
    }
}
