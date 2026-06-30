<?php

namespace App\Services;

use App\Models\LarkIntegration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 飞书/Lark 开放平台集成服务 (M3-38)
 *
 * 处理飞书自建应用的 tenant_token 管理、消息发送、用户信息获取。
 */
class LarkService
{
    const BASE_URL = 'https://open.feishu.cn/open-apis';

    /**
     * 获取 tenant_access_token
     */
    public function getTenantToken(LarkIntegration $integration): ?string
    {
        if ($integration->isTenantTokenValid()) {
            return $integration->tenant_token;
        }

        $appSecret = $integration->getDecryptedAppSecret();
        if (!$integration->app_id || !$appSecret) {
            Log::warning('飞书集成配置不完整', ['integration_id' => $integration->id]);
            return null;
        }

        try {
            $response = Http::timeout(10)->post(self::BASE_URL . '/auth/v3/tenant_access_token/internal', [
                'app_id' => $integration->app_id,
                'app_secret' => $appSecret,
            ]);

            if (!$response->successful()) {
                Log::error('飞书 tenant_token 获取失败', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $data = $response->json();
            if (($data['code'] ?? -1) !== 0) {
                Log::error('飞书 API 错误', ['code' => $data['code'], 'msg' => $data['msg'] ?? '']);
                return null;
            }

            $token = $data['tenant_access_token'];
            $expiresIn = $data['expire'] ?? 7200;

            $integration->update([
                'tenant_token' => $token,
                'tenant_token_expires_at' => now()->addSeconds($expiresIn - 60),
            ]);

            return $token;

        } catch (\Exception $e) {
            Log::error('飞书 tenant_token 请求异常', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * 通过群机器人 Webhook 发送消息
     */
    public function sendWebhookMessage(LarkIntegration $integration, string $title, string $content, string $severity = 'info'): bool
    {
        if (!$integration->bot_webhook_url) {
            Log::warning('飞书 Webhook URL 未配置');
            return false;
        }

        $color = match ($severity) {
            'critical' => 'red',
            'high' => 'orange',
            'medium' => 'yellow',
            'low' => 'green',
            default => 'blue',
        };

        try {
            $response = Http::timeout(10)->post($integration->bot_webhook_url, [
                'msg_type' => 'interactive',
                'card' => [
                    'header' => [
                        'title' => ['tag' => 'plain_text', 'content' => $title],
                        'template' => $color,
                    ],
                    'elements' => [
                        ['tag' => 'div', 'text' => ['tag' => 'lark_md', 'content' => $content]],
                        ['tag' => 'hr'],
                        ['tag' => 'note', 'elements' => [
                            ['tag' => 'plain_text', 'content' => '互物通 · 飞书集成'],
                        ]],
                    ],
                ],
            ]);

            if (!$response->successful()) {
                Log::error('飞书 Webhook 发送失败', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }

            return true;

        } catch (\Exception $e) {
            Log::error('飞书 Webhook 发送异常', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * 通过 API 发送消息到指定用户（需要 user_open_id 或 email）
     */
    public function sendUserMessage(LarkIntegration $integration, string $openId, string $title, string $content): bool
    {
        $token = $this->getTenantToken($integration);
        if (!$token) return false;

        try {
            $response = Http::timeout(10)
                ->withToken($token)
                ->post(self::BASE_URL . '/im/v1/messages?receive_id_type=open_id', [
                    'receive_id' => $openId,
                    'msg_type' => 'interactive',
                    'content' => json_encode([
                        'header' => [
                            'title' => ['tag' => 'plain_text', 'content' => $title],
                            'template' => 'blue',
                        ],
                        'elements' => [
                            ['tag' => 'div', 'text' => ['tag' => 'lark_md', 'content' => $content]],
                        ],
                    ]),
                ]);

            if (!$response->successful()) {
                Log::error('飞书用户消息发送失败', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }

            return true;

        } catch (\Exception $e) {
            Log::error('飞书用户消息发送异常', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * 通过 API 发送消息到群
     */
    public function sendGroupMessage(LarkIntegration $integration, string $chatId, string $title, string $content): bool
    {
        $token = $this->getTenantToken($integration);
        if (!$token) return false;

        try {
            $response = Http::timeout(10)
                ->withToken($token)
                ->post(self::BASE_URL . '/im/v1/messages?receive_id_type=chat_id', [
                    'receive_id' => $chatId,
                    'msg_type' => 'interactive',
                    'content' => json_encode([
                        'header' => [
                            'title' => ['tag' => 'plain_text', 'content' => $title],
                            'template' => 'blue',
                        ],
                        'elements' => [
                            ['tag' => 'div', 'text' => ['tag' => 'lark_md', 'content' => $content]],
                        ],
                    ]),
                ]);

            if (!$response->successful()) {
                Log::error('飞书群消息发送失败', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }

            return true;

        } catch (\Exception $e) {
            Log::error('飞书群消息发送异常', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * 获取飞书用户信息（通过 OAuth code）
     */
    public function getUserInfo(LarkIntegration $integration, string $code): ?array
    {
        $token = $this->getTenantToken($integration);
        if (!$token) return null;

        try {
            // 获取 user_access_token
            $appSecret = $integration->getDecryptedAppSecret();
            $authResp = Http::timeout(10)->post(self::BASE_URL . '/authen/v1/access_token', [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'app_id' => $integration->app_id,
                'app_secret' => $appSecret,
            ]);

            if (!$authResp->successful()) {
                Log::error('飞书 OAuth token 获取失败', ['body' => $authResp->body()]);
                return null;
            }

            $authData = $authResp->json();
            if (($authData['code'] ?? -1) !== 0) return null;

            $userToken = $authData['data']['access_token'] ?? null;
            if (!$userToken) return null;

            // 获取用户信息
            $userResp = Http::timeout(10)
                ->withToken($userToken)
                ->get(self::BASE_URL . '/authen/v1/user_info');

            if (!$userResp->successful()) return null;

            $userData = $userResp->json();
            if (($userData['code'] ?? -1) !== 0) return null;

            return $userData['data'] ?? null;

        } catch (\Exception $e) {
            Log::error('飞书用户信息获取异常', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * 测试连接 — 验证配置是否有效
     */
    public function testConnection(LarkIntegration $integration): array
    {
        $results = [
            'tenant_token' => false,
            'webhook' => false,
        ];

        // 测试 tenant_token
        $token = $this->getTenantToken($integration);
        $results['tenant_token'] = $token !== null;

        // 测试 webhook
        if ($integration->bot_webhook_url) {
            $results['webhook'] = $this->sendWebhookMessage(
                $integration,
                '🔄 飞书集成连接测试',
                "**测试时间**: " . now()->format('Y-m-d H:i:s') . "\n**状态**: 连接成功 ✅\n\n如果收到此消息，说明飞书集成配置正确。",
                'info'
            );
        } else {
            $results['webhook'] = true; // 未配置 webhook 也算通过
        }

        $allPassed = $results['tenant_token'] && $results['webhook'];

        return [
            'success' => $allPassed,
            'results' => $results,
            'message' => $allPassed
                ? '飞书连接测试通过！tenant_token 和 Webhook 均正常工作。'
                : '部分测试未通过，请检查配置。',
        ];
    }

    /**
     * 获取当前租户的飞书集成配置
     */
    public function getIntegration(?int $tenantId = null): ?LarkIntegration
    {
        $tenantId = $tenantId ?? tenant()?->id ?? auth()->user()?->tenant_id;
        if (!$tenantId) return null;

        return LarkIntegration::where('tenant_id', $tenantId)->first();
    }

    /**
     * 保存或更新集成配置
     */
    public function saveIntegration(array $data, ?int $tenantId = null): LarkIntegration
    {
        $tenantId = $tenantId ?? tenant()?->id ?? auth()->user()?->tenant_id;

        $integration = LarkIntegration::updateOrCreate(
            ['tenant_id' => $tenantId],
            $data
        );

        // 配置变更后清除缓存的 token
        if ($integration->wasChanged(['app_id', 'app_secret'])) {
            $integration->update([
                'tenant_token' => null,
                'tenant_token_expires_at' => null,
            ]);
        }

        return $integration->fresh();
    }
}
