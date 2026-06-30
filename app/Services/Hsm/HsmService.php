<?php

namespace App\Services\Hsm;

use App\Models\HsmKey;
use Illuminate\Support\Facades\Log;

/**
 * HSM 服务管理器
 *
 * M3-79: 统一管理 HSM 密钥操作
 * 透明集成到 KeyGenerator，支持多种 HSM 提供者
 */
class HsmService
{
    private ?HsmProvider $provider = null;
    private string $providerName;
    private bool $enabled;

    public function __construct()
    {
        $this->enabled = config('hsm.enabled', false);
        $this->providerName = config('hsm.default', 'software');
        $this->provider = $this->resolveProvider();
    }

    /**
     * 是否启用 HSM
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * 获取当前 HSM 提供者
     */
    public function provider(): HsmProvider
    {
        if (!$this->provider) {
            throw new \RuntimeException('HSM 未配置或未启用');
        }
        return $this->provider;
    }

    /**
     * 获取提供者名称
     */
    public function providerName(): string
    {
        return $this->provider?->name() ?? '未配置';
    }

    /**
     * 使用 HSM 签名 License Key
     *
     * @param string $licenseKey 待签名的 License Key
     * @param string $keyLabel   密钥标签（如 "license-v1"）
     * @return array{signature: string, key_id: int, algorithm: string}
     */
    public function signLicenseKey(string $licenseKey, string $keyLabel = 'license-v1'): array
    {
        // 查找或创建 HSM 密钥
        $hsmKey = HsmKey::where('key_label', $keyLabel)
            ->where('is_active', true)
            ->first();

        if (!$hsmKey) {
            $hsmKey = $this->createKey($keyLabel);
        }

        // 签名
        $dataToSign = $this->prepareSignData($licenseKey);
        $signature = $this->provider()->signEd25519($dataToSign, $hsmKey->key_handle);

        // 保存签名记录
        $hsmKey->increment('sign_count');

        return [
            'signature' => $signature,
            'key_id' => $hsmKey->id,
            'algorithm' => 'Ed25519',
        ];
    }

    /**
     * 验证 License Key 签名
     */
    public function verifyLicenseKey(string $licenseKey, string $signature, int $keyId): bool
    {
        $hsmKey = HsmKey::find($keyId);
        if (!$hsmKey) {
            return false;
        }

        return $this->provider()->verifyEd25519(
            $this->prepareSignData($licenseKey),
            $signature,
            $hsmKey->public_key
        );
    }

    /**
     * 创建新的 HSM 密钥
     */
    public function createKey(string $keyLabel, string $algorithm = 'Ed25519'): HsmKey
    {
        if ($algorithm === 'Ed25519') {
            $keyPair = $this->provider()->generateEd25519KeyPair();
        } else {
            $keyPair = $this->provider()->generateRsaKeyPair();
        }

        return HsmKey::create([
            'key_label' => $keyLabel,
            'key_handle' => $keyPair['key_handle'],
            'public_key' => $keyPair['public_key'],
            'algorithm' => $algorithm,
            'provider' => $this->providerName,
            'is_active' => true,
            'sign_count' => 0,
        ]);
    }

    /**
     * 密钥轮换：创建新密钥，停用旧密钥
     */
    public function rotateKey(string $keyLabel, string $algorithm = 'Ed25519'): HsmKey
    {
        // 停用旧密钥
        HsmKey::where('key_label', $keyLabel)
            ->where('is_active', true)
            ->update(['is_active' => false, 'rotated_at' => now()]);

        // 创建新密钥
        return $this->createKey($keyLabel, $algorithm);
    }

    /**
     * 获取 HSM 健康状态
     */
    public function health(): array
    {
        if (!$this->enabled) {
            return ['healthy' => false, 'provider' => 'disabled', 'message' => 'HSM 未启用'];
        }

        $health = $this->provider()->health();
        return [
            'healthy' => $health['healthy'],
            'provider' => $this->providerName,
            'message' => $health['message'],
        ];
    }

    /**
     * 获取 HSM 统计信息
     */
    public function stats(): array
    {
        $totalKeys = HsmKey::count();
        $activeKeys = HsmKey::where('is_active', true)->count();
        $totalSigns = HsmKey::sum('sign_count');

        return [
            'enabled' => $this->enabled,
            'provider' => $this->providerName,
            'total_keys' => $totalKeys,
            'active_keys' => $activeKeys,
            'total_signatures' => $totalSigns,
        ];
    }

    /**
     * 准备待签名数据
     */
    private function prepareSignData(string $licenseKey): string
    {
        // 使用固定前缀防长度攻击 + 时间戳防重放
        return 'HWT-LICENSE:' . $licenseKey . ':' . now()->format('Y-m-d');
    }

    /**
     * 解析 HSM 提供者
     */
    private function resolveProvider(): ?HsmProvider
    {
        if (!$this->enabled) {
            return null;
        }

        return match ($this->providerName) {
            'aws' => app(AwsCloudHsmProvider::class),
            'azure' => app(AzureDedicatedHsmProvider::class),
            'aliyun' => app(AliyunKmsProvider::class),
            default => app(SoftwareHsmProvider::class),
        };
    }
}
