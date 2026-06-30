<?php

/**
 * HWT License SDK Client for PHP
 *
 * 基于 M2-34 统一错误码标准的 PHP SDK 示例
 * 对应 API: /api/license/activate, /api/license/validate, /api/license/check-feature
 */

namespace Huwutong\Demo;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class HWTClient
{
    private Client $http;
    private string $apiKey;
    private string $host;

    public function __construct(string $apiKey, string $host = 'https://api.huwutong.com')
    {
        $this->apiKey = $apiKey;
        $this->host = rtrim($host, '/');
        $this->http = new Client([
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $apiKey,
            ],
        ]);
    }

    /**
     * 激活 License
     *
     * POST /api/license/activate
     */
    public function activate(string $licenseKey, array $deviceInfo): array
    {
        return $this->post('/api/license/activate', [
            'license_key' => $licenseKey,
            'fingerprint' => $deviceInfo['fingerprint'] ?? '',
            'components' => [
                'mac' => $deviceInfo['mac'] ?? '',
                'cpu_id' => $deviceInfo['cpu_id'] ?? '',
                'disk_sn' => $deviceInfo['disk_sn'] ?? '',
                'system_uuid' => $deviceInfo['system_uuid'] ?? '',
            ],
            'platform' => $deviceInfo['platform'] ?? PHP_OS,
            'metadata' => $deviceInfo['metadata'] ?? [],
        ]);
    }

    /**
     * 验证 License
     *
     * POST /api/license/validate
     */
    public function validate(string $licenseKey, ?string $fingerprint = null): array
    {
        return $this->post('/api/license/validate', [
            'license_key' => $licenseKey,
            'fingerprint' => $fingerprint ?? '',
        ]);
    }

    /**
     * 检查 Feature Flag
     *
     * POST /api/license/check-feature
     */
    public function checkFeature(string $licenseKey, string $featureCode): array
    {
        return $this->post('/api/license/check-feature', [
            'license_key' => $licenseKey,
            'feature_code' => $featureCode,
        ]);
    }

    /**
     * 查询 License 信息
     *
     * GET /api/license/info/{key}
     */
    public function getLicenseInfo(string $licenseKey): array
    {
        try {
            $response = $this->http->get($this->host . '/api/license/info/' . urlencode($licenseKey));
            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            return $this->handleError($e);
        }
    }

    /**
     * 发送心跳
     *
     * POST /api/telemetry/heartbeat
     */
    public function heartbeat(string $licenseKey, string $fingerprint): array
    {
        return $this->post('/api/telemetry/heartbeat', [
            'license_key' => $licenseKey,
            'fingerprint' => $fingerprint,
            'timestamp' => time(),
            'sdk_version' => '1.0.0',
        ]);
    }

    private function post(string $path, array $data): array
    {
        try {
            $response = $this->http->post($this->host . $path, [
                'json' => $data,
                'headers' => $this->buildSignatureHeaders($data),
            ]);
            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            return $this->handleError($e);
        }
    }

    /**
     * 构建 HMAC-SHA256 签名头
     * 排序参数 key → key=value 拼接 → HmacSHA256
     */
    private function buildSignatureHeaders(array $data): array
    {
        $nonce = substr(md5((string) microtime(true)), 0, 16);
        $timestamp = (string) time();

        ksort($data);
        $signStr = '';
        foreach ($data as $key => $value) {
            $signStr .= $key . '=' . (is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_SLASHES));
        }

        $signature = hash_hmac('sha256', $signStr . $nonce . $timestamp, $this->apiKey);

        return [
            'X-Nonce' => $nonce,
            'X-Timestamp' => $timestamp,
            'X-Signature' => $signature,
        ];
    }

    private function handleError(RequestException $e): array
    {
        if ($e->hasResponse()) {
            $body = json_decode($e->getResponse()->getBody()->getContents(), true);
            $error = $body['error'] ?? $body;
            return [
                'success' => false,
                'error' => $error['code'] ?? 'UNKNOWN_ERROR',
                'message' => $error['message'] ?? $e->getMessage(),
                'status_code' => $e->getResponse()->getStatusCode(),
            ];
        }
        return [
            'success' => false,
            'error' => 'NETWORK_ERROR',
            'message' => $e->getMessage(),
            'status_code' => 0,
        ];
    }
}
