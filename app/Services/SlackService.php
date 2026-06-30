<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SlackService
{
    public function send(string $webhookUrl, string $title, string $content, string $severity = 'info'): bool
    {
        $color = match ($severity) {
            'critical' => '#dc3545',
            'high' => '#fd7e14',
            'medium' => '#ffc107',
            'low' => '#28a745',
            default => '#409eff',
        };

        try {
            $response = Http::timeout(10)->post($webhookUrl, [
                'blocks' => [
                    [
                        'type' => 'header',
                        'text' => ['type' => 'plain_text', 'text' => $title],
                    ],
                    [
                        'type' => 'section',
                        'text' => ['type' => 'mrkdwn', 'text' => $content],
                    ],
                    [
                        'type' => 'context',
                        'elements' => [
                            ['type' => 'mrkdwn', 'text' => 'HWT License · Slack 通知'],
                        ],
                    ],
                ],
            ]);

            if (!$response->successful()) {
                Log::error('Slack 消息发送失败', ['status' => $response->status(), 'body' => $response->body()]);
                return false;
            }
            return true;
        } catch (\Exception $e) {
            Log::error('Slack 消息发送异常', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function testConnection(string $webhookUrl): array
    {
        $ok = $this->send($webhookUrl, '🔄 Slack 连接测试', "测试时间: " . now()->format('Y-m-d H:i:s') . "\n状态: 连接成功 ✅", 'low');
        return ['success' => $ok, 'message' => $ok ? 'Slack 连接测试通过' : 'Slack 连接测试失败，请检查 Webhook URL'];
    }
}