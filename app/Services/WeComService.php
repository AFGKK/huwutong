<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeComService
{
    public function send(string $webhookUrl, string $title, string $content, string $severity = 'info'): bool
    {
        try {
            $response = Http::timeout(10)->post($webhookUrl, [
                'msgtype' => 'markdown',
                'markdown' => [
                    'content' => "# {$title}\n{$content}\n---\nHWT License · 企业微信通知",
                ],
            ]);

            if (!$response->successful()) {
                Log::error('企业微信消息发送失败', ['status' => $response->status(), 'body' => $response->body()]);
                return false;
            }
            $body = $response->json();
            if (($body['errcode'] ?? -1) !== 0) {
                Log::error('企业微信 API 错误', ['errcode' => $body['errcode'], 'errmsg' => $body['errmsg'] ?? '']);
                return false;
            }
            return true;
        } catch (\Exception $e) {
            Log::error('企业微信消息发送异常', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function testConnection(string $webhookUrl): array
    {
        $ok = $this->send($webhookUrl, '🔄 企业微信连接测试', "测试时间: " . now()->format('Y-m-d H:i:s') . "\n>状态: 连接成功 ✅", 'low');
        return ['success' => $ok, 'message' => $ok ? __('app.common.wecom_connection_test_passed') : __('app.common.wecom_connection_test_failed')];
    }
}