<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ConversationMessage;
use App\Models\E2eeIdentityKey;
use App\Models\E2eeOneTimePrekey;
use App\Models\E2eeSession;
use App\Services\E2eeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class E2eeController extends Controller
{
    private E2eeService $e2ee;

    public function __construct(E2eeService $e2ee)
    {
        $this->e2ee = $e2ee;
    }

    /**
     * E2EE-001: 注册身份密钥
     */
    public function registerKeys(Request $request): JsonResponse
    {
        $request->validate([
            'public_key' => 'required|string',
            'signed_prekey' => 'required|string',
            'signature' => 'required|string',
            'one_time_prekeys' => 'nullable|array',
            'one_time_prekeys.*.key_id' => 'required|string',
            'one_time_prekeys.*.public_key' => 'required|string',
        ]);

        $userId = auth()->id();

        E2eeIdentityKey::updateOrCreate(
            ['user_id' => $userId],
            [
                'public_key' => $request->input('public_key'),
                'signed_prekey' => $request->input('signed_prekey'),
                'signature' => $request->input('signature'),
            ]
        );

        // 保存一次性预密钥
        if ($prekeys = $request->input('one_time_prekeys')) {
            foreach ($prekeys as $pk) {
                E2eeOneTimePrekey::create([
                    'user_id' => $userId,
                    'key_id' => $pk['key_id'],
                    'public_key' => $pk['public_key'],
                ]);
            }
        }

        return ApiResponse::success([
            'user_id' => $userId,
            'has_keys' => true,
            'prekeys_count' => count($prekeys ?? []),
        ], '密钥已注册', 201);
    }

    /**
     * E2EE-002: 获取用户的预密钥包（用于发起会话）
     */
    public function getPrekeyBundle(int $userId): JsonResponse
    {
        $identity = E2eeIdentityKey::where('user_id', $userId)->first();
        if (!$identity) {
            return ApiResponse::error('NO_KEYS', '对方尚未注册加密密钥', 404);
        }

        // 取一个未使用的一次性预密钥
        $prekey = E2eeOneTimePrekey::where('user_id', $userId)->where('is_used', false)->first();

        $bundle = [
            'identity_key' => $identity->public_key,
            'signed_prekey' => $identity->signed_prekey,
            'signature' => $identity->signature,
        ];

        if ($prekey) {
            $bundle['one_time_prekey'] = [
                'key_id' => $prekey->key_id,
                'public_key' => $prekey->public_key,
            ];
            // 标记为已使用
            $prekey->update(['is_used' => true, 'used_at' => now()]);
        }

        return ApiResponse::success($bundle);
    }

    /**
     * E2EE-003: 初始化加密会话
     */
    public function initSession(Request $request): JsonResponse
    {
        $request->validate([
            'conversation_id' => 'required|integer|exists:user_conversations,id',
            'their_public_key' => 'required|string',
        ]);

        $myId = auth()->id();
        $convId = (int) $request->input('conversation_id');

        // 获取自己的密钥
        $myIdentity = E2eeIdentityKey::where('user_id', $myId)->first();
        if (!$myIdentity) {
            return ApiResponse::error('NO_LOCAL_KEYS', '请先注册您的加密密钥', 400);
        }

        // 执行密钥交换
        $result = $this->e2ee->initSession(
            $myId,
            $convId,
            $request->input('their_public_key'),
            ''  // 简化: 使用服务端协助的会话初始化
        );

        if (!$result) {
            return ApiResponse::error('SESSION_FAILED', '会话初始化失败', 500);
        }

        return ApiResponse::success([
            'conversation_id' => $convId,
            'session_established' => true,
        ], '加密会话已建立', 201);
    }

    /**
     * E2EE-004: 加密消息
     */
    public function encrypt(Request $request): JsonResponse
    {
        $request->validate([
            'conversation_id' => 'required|integer',
            'content' => 'required|string|max:10000',
        ]);

        $myId = auth()->id();
        $convId = (int) $request->input('conversation_id');
        $content = $request->input('content');

        // 检查是否已加密（防止重复加密）
        if ($request->input('is_encrypted')) {
            $encrypted = [
                'ciphertext' => $content,
                'nonce' => $request->input('nonce', ''),
                'ratchet_step' => (int) $request->input('ratchet_step', 0),
            ];
        } else {
            $encrypted = $this->e2ee->encryptMessage($content, $myId, $convId);
        }

        if (!$encrypted) {
            return ApiResponse::error('ENCRYPT_FAILED', '加密失败，请先建立加密会话', 400);
        }

        return ApiResponse::success($encrypted);
    }

    /**
     * E2EE-005: 解密消息
     */
    public function decrypt(Request $request): JsonResponse
    {
        $request->validate([
            'conversation_id' => 'required|integer',
            'ciphertext' => 'required|string',
            'nonce' => 'required|string',
            'ratchet_step' => 'nullable|integer',
        ]);

        $myId = auth()->id();
        $convId = (int) $request->input('conversation_id');

        $plaintext = $this->e2ee->decryptMessage(
            $request->input('ciphertext'),
            $request->input('nonce'),
            $myId,
            $convId,
            (int) $request->input('ratchet_step', 0)
        );

        if ($plaintext === null) {
            return ApiResponse::error('DECRYPT_FAILED', '解密失败，密钥不匹配', 400);
        }

        return ApiResponse::success(['plaintext' => $plaintext]);
    }

    /**
     * E2EE-006: 获取加密状态
     */
    public function status(): JsonResponse
    {
        $userId = auth()->id();

        $identity = E2eeIdentityKey::where('user_id', $userId)->first();
        $sessionCount = E2eeSession::where('user_id', $userId)->where('status', 'active')->count();
        $prekeyCount = E2eeOneTimePrekey::where('user_id', $userId)->where('is_used', false)->count();

        return ApiResponse::success([
            'has_identity_keys' => $identity !== null,
            'active_sessions' => $sessionCount,
            'available_prekeys' => $prekeyCount,
            'registered_at' => $identity?->created_at,
        ]);
    }

    /**
     * E2EE-007: 服务端生成密钥对（辅助前端）
     */
    public function generateKeys(): JsonResponse
    {
        $keyPair = $this->e2ee->generateIdentityKeyPair();
        $signedPrekey = $this->e2ee->generateSignedPrekey($keyPair['secret_key']);
        $oneTimePrekeys = $this->e2ee->generateOneTimePrekeys(10);

        return ApiResponse::success([
            'identity' => [
                'public_key' => $keyPair['public_key'],
                'secret_key' => $keyPair['secret_key'],
            ],
            'signed_prekey' => [
                'public_key' => $signedPrekey['public_key'],
                'secret_key' => $signedPrekey['secret_key'],
                'signature' => $signedPrekey['signature'],
            ],
            'one_time_prekeys' => array_map(fn($k) => [
                'key_id' => $k['key_id'],
                'public_key' => $k['public_key'],
                'secret_key' => $k['secret_key'],
            ], $oneTimePrekeys),
        ]);
    }
}
