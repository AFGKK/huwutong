<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * D-28: Firebase Cloud Messaging (FCM) HTTP v1 推送服务
 *
 * 使用 Firebase 的 OAuth 2.0 + HTTP v1 API 发送推送通知。
 *
 * 环境变量：
 *   FCM_CREDENTIALS_PATH  — 服务账号 JSON 文件路径 (用于 JWT 授权)
 *   FCM_PROJECT_ID        — Firebase 项目 ID
 *   FCM_DRY_RUN           — 是否干跑 (true=测试不实际发送)
 */
class FcmPushService
{
    protected ?string $accessToken = null;
    protected ?int $tokenExpiresAt = null;

    const FCM_SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    /**
     * 向单个用户发送推送通知
     *
     * @param User $user  目标用户
     * @param string $title  通知标题
     * @param string $body   通知正文
     * @param array $data    附加数据 payload (最大 4KB)
     * @return array  ['success' => bool, 'message' => string]
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): array
    {
        if (!$user->fcm_token) {
            return ['success' => false, 'message' => __('app.common.user_fcm_token_not_registered')];
        }

        return $this->sendToToken($user->fcm_token, $title, $body, $data, $user->fcm_platform);
    }

    /**
     * 向指定 FCM Token 发送推送
     */
    public function sendToToken(string $token, string $title, string $body, array $data = [], ?string $platform = null): array
    {
        $projectId = config('services.fcm.project_id');
        if (!$projectId) {
            return ['success' => false, 'message' => __('app.common.fcm_project_id_not_configured')];
        }

        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return ['success' => false, 'message' => __('app.common.fcm_access_token_failed')];
        }

        $dryRun = config('services.fcm.dry_run', false);

        $message = $this->buildMessage($token, $title, $body, $data, $platform);

        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send" . ($dryRun ? '?dry_run=true' : '');

        try {
            $response = Http::withToken($accessToken)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, ['message' => $message]);

            if ($response->successful()) {
                $result = $response->json();
                Log::info('FCM 推送成功', [
                    'name' => $result['name'] ?? 'unknown',
                    'token_prefix' => substr($token, 0, 12) . '...',
                ]);
                return ['success' => true, 'message' => 'sent', 'name' => $result['name'] ?? ''];
            }

            $errorBody = $response->body();
            Log::warning('FCM 推送失败', [
                'status' => $response->status(),
                'body' => $errorBody,
                'token_prefix' => substr($token, 0, 12) . '...',
            ]);

            // 如果 token 已失效，记录以便清理
            if ($response->status() === 404 || str_contains($errorBody, 'UNREGISTERED')) {
                return ['success' => false, 'message' => 'token_unregistered', 'should_remove' => true];
            }

            return ['success' => false, 'message' => "HTTP {$response->status()}: {$errorBody}"];

        } catch (\Throwable $e) {
            Log::error('FCM 请求异常: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * 构建 FCM HTTP v1 消息体
     */
    protected function buildMessage(string $token, string $title, string $body, array $data, ?string $platform): array
    {
        $message = [
            'token' => $token,
            'notification' => [
                'title' => $title,
                'body' => $body,
            ],
            'data' => [],
        ];

        // 附加数据 (FCM data payload 所有值必须为字符串)
        foreach ($data as $key => $value) {
            $message['data'][$key] = is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        // Android 特定配置
        if ($platform !== 'ios') {
            $message['android'] = [
                'priority' => 'high',
                'notification' => [
                    'channel_id' => 'default',
                    'priority' => 'high',
                    'sound' => 'default',
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                ],
            ];
            if (!empty($data['route'])) {
                $message['android']['notification']['click_action'] = $data['route'];
            }
        }

        // iOS/APNs 特定配置
        if ($platform === 'ios') {
            $message['apns'] = [
                'headers' => [
                    'apns-priority' => '10',
                ],
                'payload' => [
                    'aps' => [
                        'alert' => [
                            'title' => $title,
                            'body' => $body,
                        ],
                        'sound' => 'default',
                        'badge' => 1,
                        'mutable-content' => 1,
                        'category' => $data['category'] ?? 'default',
                    ],
                ],
            ];
        }

        return $message;
    }

    /**
     * 获取 OAuth 2.0 访问令牌（缓存至过期前 5 分钟）
     */
    protected function getAccessToken(): ?string
    {
        if ($this->accessToken && $this->tokenExpiresAt && now()->timestamp < $this->tokenExpiresAt - 300) {
            return $this->accessToken;
        }

        $credentialsPath = config('services.fcm.credentials_path');
        if (!$credentialsPath || !file_exists($credentialsPath)) {
            Log::warning('FCM 凭证文件不存在: ' . ($credentialsPath ?? '未配置'));
            return null;
        }

        try {
            $credentials = json_decode(file_get_contents($credentialsPath), true);
            if (!$credentials || !isset($credentials['client_email'], $credentials['private_key'])) {
                Log::warning('FCM 凭证文件格式无效');
                return null;
            }

            $now = now()->timestamp;
            $jwt = $this->createJwt($credentials['client_email'], $credentials['private_key'], $now);

            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if (!$response->successful()) {
                Log::warning('FCM OAuth 令牌获取失败: ' . $response->body());
                return null;
            }

            $result = $response->json();
            $this->accessToken = $result['access_token'] ?? null;
            $this->tokenExpiresAt = $now + ($result['expires_in'] ?? 3600);

            return $this->accessToken;

        } catch (\Throwable $e) {
            Log::error('FCM 令牌获取异常: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 创建 JWT 断言用于 OAuth 2.0 客户端凭证授权
     */
    protected function createJwt(string $clientEmail, string $privateKey, int $now): string
    {
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));

        $payload = $this->base64UrlEncode(json_encode([
            'iss' => $clientEmail,
            'scope' => self::FCM_SCOPE,
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now,
        ]));

        $signature = '';
        openssl_sign("{$header}.{$payload}", $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return "{$header}.{$payload}." . $this->base64UrlEncode($signature);
    }

    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
