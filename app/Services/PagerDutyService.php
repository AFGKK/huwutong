<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * PagerDuty 告警集成服务 (M2-122)
 *
 * 通过 Events API v2 推送告警，通过 REST API 管理事件
 */
class PagerDutyService
{
    protected string $apiKey;
    protected string $routingKey;
    protected string $endpoint;
    protected string $fromEmail;
    protected int $timeout;

    public function __construct()
    {
        $config = config('incident-alerting.pagerduty');
        $this->apiKey = $config['api_key'] ?? '';
        $this->routingKey = $config['routing_key'] ?? '';
        $this->endpoint = $config['api_endpoint'] ?? 'https://api.pagerduty.com';
        $this->fromEmail = $config['from_email'] ?? 'alerts@huwutong.com';
        $this->timeout = $config['timeout'] ?? 15;
    }

    /**
     * 检查是否已启用
     */
    public function isEnabled(): bool
    {
        return config('incident-alerting.pagerduty.enabled', false)
            && !empty($this->routingKey);
    }

    /**
     * 推送告警（Events API v2）
     *
     * @param string $summary 告警摘要
     * @param string $severity critical|error|warning|info
     * @param array $details 详情数据
     * @param string|null $source 来源
     * @return array{success: bool, message: string, dedup_key?: string}
     */
    public function triggerAlert(
        string $summary,
        string $severity = 'critical',
        array $details = [],
        ?string $source = null
    ): array {
        if (!$this->isEnabled()) {
            return ['success' => false, 'message' => __('app.pager_duty_service.not_enabled')];
        }

        $dedupKey = $details['dedup_key'] ?? 'hwt-' . md5($summary . json_encode($details));

        $payload = [
            'routing_key' => $this->routingKey,
            'event_action' => 'trigger',
            'dedup_key' => $dedupKey,
            'payload' => [
                'summary' => mb_substr($summary, 0, 1024),
                'severity' => $severity,
                'source' => $source ?? gethostname(),
                'component' => $details['component'] ?? 'huwutong',
                'group' => $details['group'] ?? 'general',
                'class' => $details['class'] ?? 'application',
                'custom_details' => array_diff_key($details, array_flip(['dedup_key', 'component', 'group', 'class'])),
            ],
        ];

        try {
            $response = Http::timeout($this->timeout)
                ->post('https://events.pagerduty.com/v2/enqueue', $payload);

            if ($response->successful()) {
                $body = $response->json();
                Log::info('PagerDuty 告警推送成功', [
                    'dedup_key' => $dedupKey,
                    'status' => $body['status'] ?? 'unknown',
                ]);
                return [
                    'success' => true,
                    'message' => __('app.pager_duty_service.alert_pushed'),
                    'dedup_key' => $dedupKey,
                    'status' => $body['status'] ?? 'unknown',
                ];
            }

            Log::error('PagerDuty 告警推送失败', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return [
                'success' => false,
                'message' => __('app.pager_duty_service.push_failed', ['status' => $response->status()]),
            ];
        } catch (\Throwable $e) {
            Log::error('PagerDuty 告警推送异常', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => __('app.pager_duty_service.push_exception', ['error' => $e->getMessage()])];
        }
    }

    /**
     * 确认告警（将事件标记为 acknowledged）
     */
    public function acknowledge(string $dedupKey): array
    {
        return $this->changeAction($dedupKey, 'acknowledge');
    }

    /**
     * 解决告警
     */
    public function resolve(string $dedupKey): array
    {
        return $this->changeAction($dedupKey, 'resolve');
    }

    /**
     * 变更告警状态
     */
    protected function changeAction(string $dedupKey, string $action): array
    {
        if (!$this->isEnabled()) {
            return ['success' => false, 'message' => __('app.pager_duty_service.not_enabled')];
        }

        $payload = [
            'routing_key' => $this->routingKey,
            'event_action' => $action,
            'dedup_key' => $dedupKey,
        ];

        try {
            $response = Http::timeout($this->timeout)
                ->post('https://events.pagerduty.com/v2/enqueue', $payload);

            if ($response->successful()) {
                return ['success' => true, 'message' => __('app.pager_duty_service.event_completed', ['action' => $action])];
            }

            return ['success' => false, 'message' => __('app.pager_duty_service.op_failed', ['status' => $response->status()])];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => __('app.pager_duty_service.op_exception', ['error' => $e->getMessage()])];
        }
    }

    /**
     * 获取最近的事件列表（REST API）
     */
    public function getRecentEvents(int $limit = 25): array
    {
        if (empty($this->apiKey)) {
            return ['success' => false, 'message' => __('app.pager_duty_service.api_key_missing')];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Token token=' . $this->apiKey,
                'Accept' => 'application/vnd.pagerduty+json;version=2',
                'From' => $this->fromEmail,
            ])->timeout($this->timeout)
                ->get($this->endpoint . '/incidents', [
                    'limit' => $limit,
                    'sort_by' => 'created_at:desc',
                    'statuses' => ['triggered', 'acknowledged'],
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $incidents = array_map(function ($inc) {
                    return [
                        'id' => $inc['id'],
                        'title' => $inc['title'],
                        'status' => $inc['status'],
                        'urgency' => $inc['urgency'] ?? 'low',
                        'severity' => $inc['severity'] ?? 'info',
                        'created_at' => $inc['created_at'],
                        'service' => $inc['service']['summary'] ?? '',
                        'assignments' => array_map(fn($a) => $a['assignee']['summary'] ?? '', $inc['assignments'] ?? []),
                    ];
                }, $data['incidents'] ?? []);

                return [
                    'success' => true,
                    'incidents' => $incidents,
                    'total' => $data['total'] ?? count($incidents),
                ];
            }

            return ['success' => false, 'message' => __('app.pager_duty_service.list_failed')];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => __('app.pager_duty_service.list_exception', ['error' => $e->getMessage()])];
        }
    }

    /**
     * 测试连接
     */
    public function testConnection(): array
    {
        if (!$this->isEnabled()) {
            return ['success' => false, 'message' => __('app.pager_duty_service.not_configured')];
        }

        // 发送一条测试告警，然后用 dedup_key 立即 resolve
        $testKey = 'hwt-test-' . date('YmdHis');
        $result = $this->triggerAlert(
            __('app.pager_duty_service.test_title'),
            'info',
            ['dedup_key' => $testKey, 'component' => 'test', 'group' => 'test'],
            'huwutong-test'
        );

        if ($result['success']) {
            // 立即解决测试告警
            $this->resolve($testKey);
            return [
                'success' => true,
                'message' => __('app.pager_duty_service.test_ok'),
                'dedup_key' => $testKey,
            ];
        }

        return $result;
    }
}
