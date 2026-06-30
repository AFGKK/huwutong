<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OpsGenie 告警集成服务 (M2-122)
 *
 * 通过 Alert API 创建/确认/关闭告警
 */
class OpsGenieService
{
    protected string $apiKey;
    protected string $endpoint;
    protected int $timeout;

    public function __construct()
    {
        $config = config('incident-alerting.opsgenie');
        $this->apiKey = $config['api_key'] ?? '';
        $this->endpoint = $config['api_endpoint'] ?? 'https://api.opsgenie.com/v2';
        $this->timeout = $config['timeout'] ?? 15;
    }

    /**
     * 检查是否已启用
     */
    public function isEnabled(): bool
    {
        return config('incident-alerting.opsgenie.enabled', false)
            && !empty($this->apiKey);
    }

    protected function headers(): array
    {
        return [
            'Authorization' => 'GenieKey ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * 创建告警
     *
     * @param string $message 告警消息
     * @param string $priority P1|P2|P3|P4|P5
     * @param array $details 详情
     * @return array{success: bool, message: string, alert_id?: string}
     */
    public function createAlert(
        string $message,
        string $priority = 'P3',
        array $details = []
    ): array {
        if (!$this->isEnabled()) {
            return ['success' => false, 'message' => 'OpsGenie 未启用'];
        }

        $alias = $details['alias'] ?? 'hwt-' . md5($message . json_encode($details));

        $payload = [
            'message' => mb_substr($message, 0, 130),
            'alias' => $alias,
            'priority' => $priority,
            'source' => $details['source'] ?? 'huwutong',
            'tags' => $details['tags'] ?? ['huwutong'],
            'details' => array_diff_key($details, array_flip(['alias', 'source', 'tags', 'team_id', 'responders'])),
            'note' => $details['note'] ?? '由互物通自动创建',
        ];

        if (!empty($details['team_id'])) {
            $payload['responders'][] = [
                'id' => $details['team_id'],
                'type' => 'team',
            ];
        }

        if (!empty($details['responders'])) {
            $payload['responders'] = ($payload['responders'] ?? []) + $details['responders'];
        }

        try {
            $response = Http::withHeaders($this->headers())
                ->timeout($this->timeout)
                ->post($this->endpoint . '/alerts', $payload);

            if ($response->successful()) {
                $body = $response->json();
                $alertId = $body['alertId'] ?? $body['data']['alertId'] ?? '';

                Log::info('OpsGenie 告警创建成功', [
                    'alert_id' => $alertId,
                    'alias' => $alias,
                    'priority' => $priority,
                ]);

                return [
                    'success' => true,
                    'message' => '告警已创建',
                    'alert_id' => $alertId,
                    'alias' => $alias,
                ];
            }

            $errorBody = $response->body();
            Log::error('OpsGenie 告警创建失败', [
                'status' => $response->status(),
                'body' => $errorBody,
            ]);

            return [
                'success' => false,
                'message' => '创建失败: HTTP ' . $response->status(),
            ];
        } catch (\Throwable $e) {
            Log::error('OpsGenie 告警创建异常', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => '创建异常: ' . $e->getMessage()];
        }
    }

    /**
     * 确认告警
     */
    public function acknowledge(string $identifier, string $identifierType = 'id'): array
    {
        return $this->executeAction($identifier, $identifierType, 'acknowledge');
    }

    /**
     * 关闭告警
     */
    public function close(string $identifier, string $identifierType = 'id'): array
    {
        return $this->executeAction($identifier, $identifierType, 'close');
    }

    /**
     * 添加备注
     */
    public function addNote(string $identifier, string $note, string $identifierType = 'id'): array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->timeout($this->timeout)
                ->post("{$this->endpoint}/alerts/{$identifier}/notes?identifierType={$identifierType}", [
                    'note' => $note,
                ]);

            if ($response->successful()) {
                return ['success' => true, 'message' => '备注已添加'];
            }

            return ['success' => false, 'message' => '添加备注失败'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => '备注异常: ' . $e->getMessage()];
        }
    }

    /**
     * 执行告警操作
     */
    protected function executeAction(string $identifier, string $identifierType, string $action): array
    {
        if (!$this->isEnabled()) {
            return ['success' => false, 'message' => 'OpsGenie 未启用'];
        }

        try {
            $response = Http::withHeaders($this->headers())
                ->timeout($this->timeout)
                ->post("{$this->endpoint}/alerts/{$identifier}/{$action}?identifierType={$identifierType}");

            if ($response->successful()) {
                return ['success' => true, 'message' => "告警已{$action}"];
            }

            return ['success' => false, 'message' => "{$action}失败: HTTP " . $response->status()];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => "{$action}异常: " . $e->getMessage()];
        }
    }

    /**
     * 获取未关闭的告警列表
     */
    public function getOpenAlerts(int $limit = 20): array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->timeout($this->timeout)
                ->get($this->endpoint . '/alerts', [
                    'limit' => $limit,
                    'sort' => '-createdAt',
                    'status' => 'open',
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $alerts = array_map(function ($alert) {
                    return [
                        'id' => $alert['id'],
                        'message' => $alert['message'],
                        'priority' => $alert['priority'],
                        'status' => $alert['status'],
                        'created_at' => $alert['createdAt'],
                        'updated_at' => $alert['updatedAt'],
                        'tags' => $alert['tags'] ?? [],
                        'source' => $alert['source'] ?? '',
                    ];
                }, $data['data'] ?? []);

                return [
                    'success' => true,
                    'alerts' => $alerts,
                    'total' => $data['totalCount'] ?? count($alerts),
                ];
            }

            return ['success' => false, 'message' => '获取告警列表失败'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => '获取告警异常: ' . $e->getMessage()];
        }
    }

    /**
     * 测试连接
     */
    public function testConnection(): array
    {
        if (!$this->isEnabled()) {
            return ['success' => false, 'message' => 'OpsGenie 未启用或 API Key 未配置'];
        }

        $testAlias = 'hwt-test-' . date('YmdHis');
        $result = $this->createAlert(
            '【互物通】OpsGenie 连接测试',
            'P5',
            [
                'alias' => $testAlias,
                'tags' => ['huwutong', 'test'],
                'source' => 'huwutong-test',
                'note' => '自动连接测试',
            ]
        );

        if ($result['success']) {
            // 关闭测试告警
            $this->close($testAlias, 'alias');
            return [
                'success' => true,
                'message' => 'OpsGenie 连接正常（测试告警已创建并关闭）',
                'alias' => $testAlias,
            ];
        }

        return $result;
    }
}
