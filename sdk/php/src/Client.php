<?php

/**
 * HWT License PHP SDK
 * 
 * 基于统一错误码标准 M2-34，提供 License 激活/验证/设备绑定功能。
 * 
 * 使用：
 *   $client = new Huwutong\Client('your_api_key', 'https://api.huwutong.com');
 *   $result = $client->activate('LICENSE-KEY', $machineInfo);
 * 
 * 安装：
 *   composer require huwutong/sdk
 */

namespace Huwutong;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;

class ApiError extends \RuntimeException
{
    public string $errorCode;
    public int $statusCode;
    public array $details;

    public function __construct(string $code, string $message, int $statusCode = 400, array $details = [])
    {
        parent::__construct("[{$code}] {$message}");
        $this->errorCode = $code;
        $this->statusCode = $statusCode;
        $this->details = $details;
    }
}

class ActivationResult
{
    public function __construct(
        public bool $success,
        public string $licenseKey,
        public string $expiresAt = '',
        public array $features = [],
        public string $message = '',
    ) {}
}

class ValidationResult
{
    public function __construct(
        public bool $isValid,
        public string $licenseKey,
        public string $status = '',
        public string $expiresAt = '',
        public string $machineId = '',
        public array $features = [],
        public string $message = '',
    ) {}
}

class Client
{
    private const VERSION = '1.0.0';
    private const DEFAULT_TIMEOUT = 10;

    private HttpClient $http;
    private string $apiKey;
    private string $host;

    public function __construct(
        string $apiKey,
        string $host = 'https://api.huwutong.com',
        ?HttpClient $httpClient = null,
    ) {
        $this->apiKey = $apiKey;
        $this->host = rtrim($host, '/');
        $this->http = $httpClient ?: new HttpClient([
            'base_uri' => $this->host . '/',
            'timeout' => self::DEFAULT_TIMEOUT,
            'headers' => [
                'User-Agent' => 'HWT-SDK-PHP/' . self::VERSION,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * 激活 License
     */
    public function activate(string $licenseKey, array $machineInfo): ActivationResult
    {
        $data = $this->call('POST', '/api/activate', [
            'license_key' => $licenseKey,
            'machine_info' => $machineInfo,
        ]);
        return new ActivationResult(
            success: $data['success'] ?? false,
            licenseKey: $licenseKey,
            expiresAt: $data['expires_at'] ?? '',
            features: $data['features'] ?? [],
            message: $data['message'] ?? '',
        );
    }

    /**
     * 验证 License
     */
    public function validate(string $licenseKey, array $context = []): ValidationResult
    {
        $data = $this->call('POST', '/api/validate', [
            'license_key' => $licenseKey,
            'context' => $context,
        ]);
        return new ValidationResult(
            isValid: $data['is_valid'] ?? false,
            licenseKey: $licenseKey,
            status: $data['status'] ?? '',
            expiresAt: $data['expires_at'] ?? '',
            machineId: $data['machine_id'] ?? '',
            features: $data['features'] ?? [],
            message: $data['message'] ?? '',
        );
    }

    /**
     * 解除激活
     */
    public function deactivate(string $licenseKey, string $deviceId = ''): bool
    {
        $data = $this->call('POST', '/api/deactivate', [
            'license_key' => $licenseKey,
            'device_id' => $deviceId,
        ]);
        return $data['success'] ?? false;
    }

    /**
     * 离线验证（返回签名）
     */
    public function offlineVerify(string $licenseKey, string $deviceId): array
    {
        return $this->call('POST', '/api/offline/verify', [
            'license_key' => $licenseKey,
            'device_id' => $deviceId,
        ]);
    }

    /**
     * 检查 Feature 是否可用
     */
    public function checkFeature(string $licenseKey, string $feature): bool
    {
        $data = $this->call('GET', '/api/check-feature', [
            'license_key' => $licenseKey,
            'feature' => $feature,
        ]);
        return $data['available'] ?? false;
    }

    /**
     * 获取 License 信息
     */
    public function getLicense(string $licenseKey): array
    {
        return $this->call('GET', '/api/licenses/' . urlencode($licenseKey));
    }

    private function call(string $method, string $path, array $params = []): array
    {
        try {
            $options = [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                ],
            ];

            if ($method === 'GET') {
                $options['query'] = $params;
            } else {
                $options['json'] = $params;
            }

            $response = $this->http->request($method, ltrim($path, '/'), $options);
            $body = json_decode($response->getBody()->getContents(), true);

            if (!$body || isset($body['error'])) {
                throw new ApiError(
                    $body['code'] ?? 'UNKNOWN',
                    $body['message'] ?? 'Unknown error',
                    $response->getStatusCode(),
                    $body['details'] ?? [],
                );
            }

            return $body['data'] ?? $body;
        } catch (GuzzleException $e) {
            throw new \RuntimeException("Network error: {$e->getMessage()}", 0, $e);
        }
    }
}
