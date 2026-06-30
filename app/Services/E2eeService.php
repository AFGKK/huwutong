<?php

namespace App\Services;

use App\Models\ConversationMessage;
use App\Models\E2eeIdentityKey;
use App\Models\E2eeSession;
use App\Models\E2eeOneTimePrekey;
use Illuminate\Support\Facades\Crypt;

class E2eeService
{
    // ════════════════════════════════════════════
    // E2EE-001~008: 端到端加密（Signal 风格）
    // ════════════════════════════════════════════

    /**
     * 生成身份密钥对 (X25519)
     */
    public function generateIdentityKeyPair(): array
    {
        $keyPair = sodium_crypto_box_keypair();
        return [
            'public_key' => base64_encode(sodium_crypto_box_publickey($keyPair)),
            'secret_key' => base64_encode(sodium_crypto_box_secretkey($keyPair)),
            'keypair' => base64_encode($keyPair),
        ];
    }

    /**
     * 生成签名预密钥
     */
    public function generateSignedPrekey(string $identitySecretKey): array
    {
        $preKeyPair = sodium_crypto_box_keypair();
        $publicKey = sodium_crypto_box_publickey($preKeyPair);
        $signature = sodium_crypto_sign_detached($publicKey, base64_decode($identitySecretKey));

        return [
            'public_key' => base64_encode($publicKey),
            'secret_key' => base64_encode(sodium_crypto_box_secretkey($preKeyPair)),
            'signature' => base64_encode($signature),
        ];
    }

    /**
     * 生成一次性预密钥
     */
    public function generateOneTimePrekeys(int $count = 10): array
    {
        $keys = [];
        for ($i = 0; $i < $count; $i++) {
            $kp = sodium_crypto_box_keypair();
            $keys[] = [
                'key_id' => 'prekey_' . bin2hex(random_bytes(8)),
                'public_key' => base64_encode(sodium_crypto_box_publickey($kp)),
                'secret_key' => base64_encode(sodium_crypto_box_secretkey($kp)),
            ];
        }
        return $keys;
    }

    /**
     * 初始化加密会话 (X3DH 风格密钥交换)
     */
    public function initSession(int $userId, int $conversationId, string $theirPublicKey, string $mySecretKey): array
    {
        // 计算共享密钥 (ECDH)
        $theirPub = base64_decode($theirPublicKey);
        $mySec = base64_decode($mySecretKey);
        $sharedSecret = sodium_crypto_scalarmult($mySec, $theirPub);

        // 派生 AES-256 会话密钥
        $sessionKey = hash_hkdf('sha256', $sharedSecret, 32, 'e2ee-session', '');

        // 保存会话
        E2eeSession::updateOrCreate(
            ['user_id' => $userId, 'conversation_id' => $conversationId],
            [
                'session_key' => base64_encode($sessionKey),
                'ratchet_step' => 0,
                'status' => 'active',
            ]
        );

        return ['session_key' => base64_encode($sessionKey), 'ratchet_step' => 0];
    }

    /**
     * 加密消息 (AES-256-GCM)
     */
    public function encryptMessage(string $plaintext, int $userId, int $conversationId): ?array
    {
        $session = E2eeSession::where('user_id', $userId)
            ->where('conversation_id', $conversationId)
            ->where('status', 'active')
            ->first();

        if (!$session) return null;

        $key = base64_decode($session->session_key);

        // Ratchet: 每次加密后派生新密钥 (Double Ratchet 简化版)
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_AES256GCM_NPUBBYTES);
        $ciphertext = sodium_crypto_aead_aes256gcm_encrypt($plaintext, $nonce, $nonce, $key);

        // 更新密钥 (ratchet forward)
        $newKey = hash_hkdf('sha256', $key, 32, 'ratchet-' . $session->ratchet_step, '');
        $session->update([
            'session_key' => base64_encode($newKey),
            'ratchet_step' => $session->ratchet_step + 1,
        ]);

        return [
            'ciphertext' => base64_encode($ciphertext),
            'nonce' => base64_encode($nonce),
            'ratchet_step' => $session->ratchet_step - 1,
        ];
    }

    /**
     * 解密消息 (AES-256-GCM)
     */
    public function decryptMessage(string $ciphertext, string $nonce, int $userId, int $conversationId, int $ratchetStep = 0): ?string
    {
        $session = E2eeSession::where('user_id', $userId)
            ->where('conversation_id', $conversationId)
            ->where('status', 'active')
            ->first();

        if (!$session) return null;

        // 回滚到指定 ratchet 步骤的密钥
        $key = base64_decode($session->session_key);
        $currentStep = $session->ratchet_step;

        // 如果是旧消息，需要派生对应步骤的密钥
        for ($i = $currentStep - 1; $i > $ratchetStep; $i--) {
            $key = hash_hkdf('sha256', $key, 32, 'ratchet-' . $i, '');
        }

        $ct = base64_decode($ciphertext);
        $non = base64_decode($nonce);

        try {
            $plaintext = sodium_crypto_aead_aes256gcm_decrypt($ct, $non, $non, $key);
            return $plaintext !== false ? $plaintext : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
