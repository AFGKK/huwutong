<?php

namespace App\Services\Hsm;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AWS CloudHSM 适配器
 *
 * 通过 AWS CloudHSM 的 PKCS#11 / JCE 接口进行签名操作。
 * 需要安装 AWS CloudHSM 客户端并配置。
 */
class AwsCloudHsmProvider implements HsmProvider
{
    private string $endpoint;
    private string $apiKey;
    private int $timeout;

    public function __construct()
    {
        $this->endpoint = config('hsm.providers.aws.endpoint', '');
        $this->apiKey = config('hsm.providers.aws.api_key', '');
        $this->timeout = config('hsm.providers.aws.timeout', 10);
    }

    public function name(): string
    {
        return 'AWS CloudHSM';
    }

    public function generateEd25519KeyPair(): array
    {
        return $this->call('/keys/ed25519', 'POST');
    }

    public function signEd25519(string $data, string $keyHandle): string
    {
        $result = $this->call('/sign/ed25519', 'POST', [
            'key_handle' => $keyHandle,
            'data' => bin2hex($data),
        ]);
        return $result['signature'] ?? '';
    }

    public function verifyEd25519(string $data, string $signature, string $publicKey): bool
    {
        try {
            $result = $this->call('/verify/ed25519', 'POST', [
                'data' => bin2hex($data),
                'signature' => $signature,
                'public_key' => $publicKey,
            ]);
            return ($result['valid'] ?? false) === true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function generateRsaKeyPair(): array
    {
        return $this->call('/keys/rsa', 'POST');
    }

    public function signRsa(string $data, string $keyHandle): string
    {
        $result = $this->call('/sign/rsa', 'POST', [
            'key_handle' => $keyHandle,
            'data' => base64_encode($data),
        ]);
        return $result['signature'] ?? '';
    }

    public function health(): array
    {
        try {
            $result = $this->call('/health', 'GET');
            return ['healthy' => true, 'message' => __('app.common.aws_cloudhsm_ok')];
        } catch (\Throwable $e) {
            return ['healthy' => false, 'message' => $e->getMessage()];
        }
    }

    private function call(string $path, string $method, array $data = []): array
    {
        $url = rtrim($this->endpoint, '/') . $path;
        $response = Http::timeout($this->timeout)
            ->withHeaders(['X-API-Key' => $this->apiKey])
            ->{$method}($url, $data);

        if (!$response->successful()) {
            throw new \RuntimeException(__("app.cloud_hsm_providers.aws_cloudhsm_request_failed"));
        }

        return $response->json() ?? [];
    }
}

/**
 * Azure Dedicated HSM 适配器
 */
class AzureDedicatedHsmProvider implements HsmProvider
{
    private string $endpoint;
    private string $tenantId;
    private string $clientId;
    private string $clientSecret;

    public function __construct()
    {
        $this->endpoint = config('hsm.providers.azure.endpoint', '');
        $this->tenantId = config('hsm.providers.azure.tenant_id', '');
        $this->clientId = config('hsm.providers.azure.client_id', '');
        $this->clientSecret = config('hsm.providers.azure.client_secret', '');
    }

    public function name(): string
    {
        return 'Azure Dedicated HSM';
    }

    public function generateEd25519KeyPair(): array
    {
        return $this->call('/keys', [
            'key_size' => 256,
            'key_ops' => ['sign', 'verify'],
        ]);
    }

    public function signEd25519(string $data, string $keyHandle): string
    {
        $result = $this->call("/keys/{$keyHandle}/sign", [
            'alg' => 'Ed25519',
            'value' => base64_encode($data),
        ]);
        return $result['signature'] ?? '';
    }

    public function verifyEd25519(string $data, string $signature, string $publicKey): bool
    {
        return sodium_crypto_sign_verify_detached(hex2bin($signature), $data, hex2bin($publicKey));
    }

    public function generateRsaKeyPair(): array
    {
        return $this->call('/keys', [
            'key_size' => 2048,
            'key_ops' => ['sign', 'verify'],
        ]);
    }

    public function signRsa(string $data, string $keyHandle): string
    {
        $result = $this->call("/keys/{$keyHandle}/sign", [
            'alg' => 'RS256',
            'value' => base64_encode($data),
        ]);
        return $result['signature'] ?? '';
    }

    public function health(): array
    {
        return ['healthy' => !empty($this->endpoint), 'message' => __('app.common.azure_hsm_configured')];
    }

    private function call(string $path, array $data = []): array
    {
        $token = $this->getAccessToken();
        $response = Http::withToken($token)
            ->post(rtrim($this->endpoint, '/') . $path, $data);

        if (!$response->successful()) {
            throw new \RuntimeException(__("app.cloud_hsm_providers.azure_hsm_request_failed"));
        }
        return $response->json() ?? [];
    }

    private function getAccessToken(): string
    {
        $response = Http::asForm()->post(
            "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token",
            [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope' => 'https://vault.azure.net/.default',
                'grant_type' => 'client_credentials',
            ]
        );
        return $response->json('access_token', '');
    }
}

/**
 * 阿里云加密服务适配器
 */
class AliyunKmsProvider implements HsmProvider
{
    private string $endpoint;
    private string $accessKey;
    private string $accessSecret;

    public function __construct()
    {
        $this->endpoint = config('hsm.providers.aliyun.endpoint', '');
        $this->accessKey = config('hsm.providers.aliyun.access_key', '');
        $this->accessSecret = config('hsm.providers.aliyun.access_secret', '');
    }

    public function name(): string
    {
        return 'Alibaba Cloud KMS';
    }

    public function generateEd25519KeyPair(): array
    {
        return ['public_key' => '', 'key_handle' => ''];
    }

    public function signEd25519(string $data, string $keyHandle): string
    {
        return '';
    }

    public function verifyEd25519(string $data, string $signature, string $publicKey): bool
    {
        return sodium_crypto_sign_verify_detached(hex2bin($signature), $data, hex2bin($publicKey));
    }

    public function generateRsaKeyPair(): array
    {
        return ['public_key' => '', 'key_handle' => ''];
    }

    public function signRsa(string $data, string $keyHandle): string
    {
        return '';
    }

    public function health(): array
    {
        return ['healthy' => !empty($this->endpoint), 'message' => $this->endpoint ? __('app.common.aliyun_kms_configured') : __('app.common.not_configured')];
    }
}
