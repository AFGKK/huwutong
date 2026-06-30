<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DingTalkService
{
    public function send(string $webhookUrl, string $title, string $content, string $severity = 'info'): bool
    {
        $color = match ($severity) {
            'critical' => 'red',
            'high' => 'orange',
            'medium' => 'yellow',
            'low' => 'green',
            default => 'blue',
        };

        try {
            $response = Http::timeout(10)->post($webhookUrl, [
                'msgtype' => 'actionCard',
                'actionCard' => [
                    'title' => $title,
                    'text' => "# {$title}\n\n{$content}",
                    'btnOrientation' => '0',
                    'singleTitle' => '查看详情',
                    'singleURL' => '',
                ],
            ]);

            if (!$response->successful()) {
                Log::error('钉钉消息发送失败', ['status' => $response->status(), 'body' => $response->body()]);
                return false;
            }
            $body = $response->json();
            if (($body['errcode'] ?? -1) !== 0) {
                Log::error('钉钉 API 错误', ['errcode' => $body['errcode'], 'errmsg' => $body['errmsg'] ?? '']);
                return false;
            }
            return true;
        } catch (\Exception $e) {
            Log::error('钉钉消息发送异常', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function testConnection(string $webhookUrl): array
    {
        $ok = $this->send($webhookUrl, '🔄 钉钉连接测试', "测试时间: " . now()->format('Y-m-d H:i:s') . "\n\n状态: **连接成功** ✅", 'low');
        return ['success' => $ok, 'message' => $ok ? '钉钉连接测试通过' : '钉钉连接测试失败，请检查 Webhook URL'];
    }
}