<?php

namespace App\Services\Hsm;

use Illuminate\Support\Facades\Log;

/**
 * HSM 硬件安全模块抽象接口
 *
 * M3-79: License Key Ed25519/RSA 签名在 HSM 内完成
 * 私钥永不出 HSM，满足 FIPS 140-2 Level 3 合规
 */
interface HsmProvider
{
    /**
     * 获取 HSM 提供者名称
     */
    public function name(): string;

    /**
     * 生成 Ed25519 密钥对
     * @return array{public_key: string, key_handle: string}
     */
    public function generateEd25519KeyPair(): array;

    /**
     * 使用 HSM 内私钥签名
     * @param string $data 待签名数据
     * @param string $keyHandle HSM 密钥句柄
     * @return string 签名（hex 编码）
     */
    public function signEd25519(string $data, string $keyHandle): string;

    /**
     * 验证签名
     * @param string $data 原始数据
     * @param string $signature 签名（hex 编码）
     * @param string $publicKey 公钥（hex 编码）
     * @return bool
     */
    public function verifyEd25519(string $data, string $signature, string $publicKey): bool;

    /**
     * 生成 RSA-2048 密钥对（兼容模式）
     * @return array{public_key: string, key_handle: string}
     */
    public function generateRsaKeyPair(): array;

    /**
     * 使用 RSA 签名
     */
    public function signRsa(string $data, string $keyHandle): string;

    /**
     * 获取 HSM 健康状态
     * @return array{healthy: bool, message: string}
     */
    public function health(): array;
}

/**
 * 软件模拟 HSM（开发/测试环境使用）
 * 生产环境应替换为真实的 HSM 提供者
 */
class SoftwareHsmProvider implements HsmProvider
{
    public function name(): string
    {
        return 'software (development only)';
    }

    public function generateEd25519KeyPair(): array
    {
        $keyPair = sodium_crypto_sign_keypair();
        $publicKey = bin2hex(sodium_crypto_sign_publickey($keyPair));
        $secretKey = bin2hex(sodium_crypto_sign_secretkey($keyPair));

        // 模拟 HSM 密钥句柄
        $keyHandle = 'sw-ed25519-' . substr(md5($publicKey), 0, 16);

        // 保存密钥（真实 HSM 不会暴露私钥）
        // 开发模式下存储在加密缓存中
        cache()->forever("hsm_key:{$keyHandle}", $secretKey);

        return ['public_key' => $publicKey, 'key_handle' => $keyHandle];
    }

    public function signEd25519(string $data, string $keyHandle): string
    {
        $secretKeyHex = cache()->get("hsm_key:{$keyHandle}");
        if (!$secretKeyHex) {
            throw new \RuntimeException("HSM 密钥句柄无效: {$keyHandle}");
        }
        $secretKey = sodium_crypto_sign_secretkey(
            sodium_crypto_sign_seed_keypair(
                substr(hex2bin($secretKeyHex), 0, SODIUM_CRYPTO_SIGN_SEEDBYTES)
            )
        );
        return bin2hex(sodium_crypto_sign_detached($data, $secretKey));
    }

    public function verifyEd25519(string $data, string $signature, string $publicKey): bool
    {
        try {
            return sodium_crypto_sign_verify_detached(
                hex2bin($signature),
                $data,
                hex2bin($publicKey)
            );
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function generateRsaKeyPair(): array
    {
        $keyResource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        openssl_pkey_export($keyResource, $privateKey);
        $publicKey = openssl_pkey_get_details($keyResource)['key'];

        $keyHandle = 'sw-rsa-' . substr(md5($publicKey), 0, 16);
        cache()->forever("hsm_key:{$keyHandle}", $privateKey);

        return ['public_key' => base64_encode($publicKey), 'key_handle' => $keyHandle];
    }

    public function signRsa(string $data, string $keyHandle): string
    {
        $privateKey = cache()->get("hsm_key:{$keyHandle}");
        if (!$privateKey) {
            throw new \RuntimeException("HSM 密钥句柄无效: {$keyHandle}");
        }
        openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        return base64_encode($signature);
    }

    public function health(): array
    {
        $hasLibsodium = function_exists('sodium_crypto_sign_keypair');
        $hasOpenSsl = function_exists('openssl_sign');
        return [
            'healthy' => $hasLibsodium && $hasOpenSsl,
            'message' => $hasLibsodium && $hasOpenSsl
                ? '软件 HSM 运行正常'
                : '缺少必要的加密扩展（sodium/openssl）',
        ];
    }
}
