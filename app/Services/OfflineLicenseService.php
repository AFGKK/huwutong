<?php

namespace App\Services;

use App\Models\License;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SodiumException;

/**
 * 离线验证文件生成服务
 *
 * 负责：
 * - Ed25519 密钥对生成与管理
 * - 离线 License 文件生成（.license 格式）
 * - 支持 RSA-2048 兼容模式
 *
 * Ed25519 优势（参见 M0-12 ADR）：
 * - 签名体积小（64B vs RSA-2048 的 256B）
 * - 公钥小（32B vs RSA-2048 的 256B）
 * - 验签速度快 10-20 倍
 */
class OfflineLicenseService
{
    /**
     * 当前密钥版本号
     */
    const KEY_VERSION = 2;

    /**
     * 签名算法标识
     */
    const ALGORITHM_ED25519 = 'Ed25519';
    const ALGORITHM_RSA2048 = 'RSA-2048';

    /**
     * 缓存密钥前缀
     */
    const CACHE_PREFIX = 'offline_key:';

    /**
     * License 文件魔数
     */
    const FILE_MAGIC = "HWTLC";

    /**
     * License 文件格式版本
     */
    const FILE_VERSION = 2;

    /**
     * 生成 Ed25519 密钥对
     *
     * @return array{private_key: string, public_key: string, seed: string}
     * @throws SodiumException
     */
    public function generateKeyPair(): array
    {
        $seed = random_bytes(32); // Ed25519 seed
        $keyPair = sodium_crypto_sign_seed_keypair($seed);

        return [
            'private_key' => base64_encode(sodium_crypto_sign_secretkey($keyPair)),
            'public_key' => base64_encode(sodium_crypto_sign_publickey($keyPair)),
            'seed' => base64_encode($seed),
        ];
    }

    /**
     * 从 seed 恢复密钥对（用于密钥轮换场景）
     */
    public function restoreKeyPair(string $seedBase64): array
    {
        $seed = base64_decode($seedBase64);
        $keyPair = sodium_crypto_sign_seed_keypair($seed);

        return [
            'private_key' => base64_encode(sodium_crypto_sign_secretkey($keyPair)),
            'public_key' => base64_encode(sodium_crypto_sign_publickey($keyPair)),
            'seed' => $seedBase64,
        ];
    }

    /**
     * 生成 Ed25519 签名
     *
     * @param string $data 待签名数据
     * @param string $privateKeyBase64 Base64 编码的私钥
     * @return string Base64 编码的签名
     */
    public function sign(string $data, string $privateKeyBase64): string
    {
        $privateKey = base64_decode($privateKeyBase64);
        $signature = sodium_crypto_sign_detached($data, $privateKey);

        return base64_encode($signature);
    }

    /**
     * 生成离线 License 文件内容
     *
     * 文件格式（二进制 + JSON）：
     * - 魔数 5B: "HWTLC"
     * - 格式版本 2B: uint16 BE
     * - 算法标识 1B: 0x01=Ed25519, 0x02=RSA-2048
     * - 签名 64B (Ed25519) 或 256B (RSA-2048)
     * - 签名数据体（JSON）：包含公钥版本、License信息、有效期等
     *
     * @param License $license
     * @param string $privateKeyBase64
     * @param string|null $publicKeyBase64
     * @param string $algorithm
     * @return array{file_content: string, payload: array, signature: string}
     */
    public function generateLicenseFile(
        License $license,
        string  $privateKeyBase64,
        ?string $publicKeyBase64 = null,
        string  $algorithm = self::ALGORITHM_ED25519,
    ): array {
        // 构建载荷
        $payload = $this->buildPayload($license, $publicKeyBase64, $algorithm);

        // 序列化
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // 签名
        $signature = $this->sign($payloadJson, $privateKeyBase64);

        // 构建二进制文件
        $fileContent = $this->buildFileContent($payloadJson, $signature, $algorithm);

        return [
            'file_content' => base64_encode($fileContent),
            'payload' => $payload,
            'signature' => $signature,
        ];
    }

    /**
     * 构建 License 载荷
     */
    protected function buildPayload(
        License $license,
        ?string $publicKeyBase64 = null,
        string  $algorithm = self::ALGORITHM_ED25519,
    ): array {
        $product = $license->product;

        return [
            'ver' => self::FILE_VERSION,
            'alg' => $algorithm,
            'kid' => self::KEY_VERSION,
            'lic_key' => $license->license_key,
            'type' => $license->type,
            'status' => $license->status,
            'product' => $product ? [
                'id' => $product->id,
                'name' => $product->name,
            ] : null,
            'customer' => $license->customer ? [
                'id' => $license->customer->id,
                'name' => $license->customer->name,
                'email' => $license->customer->email,
            ] : null,
            'seats' => $license->seats,
            'max_devices' => $license->max_devices,
            'issued_at' => ($license->created_at ?? now())->toIso8601String(),
            'expires_at' => $license->expires_at?->toIso8601String(),
            'metadata' => $license->metadata,
            'pub_key' => $publicKeyBase64,
            'key_version' => self::KEY_VERSION,
            'issued_by' => config('app.url'),
            'salt' => bin2hex(random_bytes(8)),
        ];
    }

    /**
     * 构建二进制文件内容
     */
    protected function buildFileContent(string $payloadJson, string $signature, string $algorithm): string
    {
        $magic = self::FILE_MAGIC; // 5B
        $version = pack('n', self::FILE_VERSION); // 2B
        $algByte = $algorithm === self::ALGORITHM_ED25519 ? "\x01" : "\x02"; // 1B
        $sigBin = base64_decode($signature); // 64B (Ed25519)

        return $magic . $version . $algByte . $sigBin . $payloadJson;
    }

    /**
     * 批量生成离线 License 文件
     */
    public function generateBatch(array $licenses, string $privateKeyBase64, ?string $publicKeyBase64 = null): array
    {
        $results = [];
        foreach ($licenses as $license) {
            $results[] = $this->generateLicenseFile($license, $privateKeyBase64, $publicKeyBase64);
        }
        return $results;
    }

    /**
     * 获取/缓存当前密钥对
     *
     * @param bool $forceGenerate 强制生成新密钥
     * @return array{private_key: string, public_key: string, seed: string, key_version: int}
     */
    public function getActiveKeyPair(bool $forceGenerate = false): array
    {
        $cacheKey = self::CACHE_PREFIX . 'active';

        if (! $forceGenerate) {
            $cached = Cache::get($cacheKey);
            if ($cached) {
                return $cached;
            }
        }

        // 从配置获取 seed（优先使用持久化 seed）
        $seed = config('license.ed25519_seed');
        if ($seed) {
            $keyPair = $this->restoreKeyPair($seed);
        } else {
            $keyPair = $this->generateKeyPair();
        }

        $keyPair['key_version'] = self::KEY_VERSION;
        Cache::forever($cacheKey, $keyPair);

        return $keyPair;
    }

    /**
     * 获取公钥（以多种格式返回，便于分发）
     */
    public function getPublicKey(string $publicKeyBase64): array
    {
        $publicKeyBin = base64_decode($publicKeyBase64);

        return [
            'base64' => $publicKeyBase64,
            'hex' => bin2hex($publicKeyBin),
            'version' => self::KEY_VERSION,
            'algorithm' => self::ALGORITHM_ED25519,
        ];
    }
}
