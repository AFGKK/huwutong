<?php

namespace App\Services;

use App\Models\TeamsNotificationLog;
use App\Models\TeamsWebhook;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Microsoft Teams 通知集成服务
 *
 * M2-95: 激活成功/异常告警/过期提醒 → Teams 频道消息（Adaptive Cards）
 * 支持多 Webhook 分发、频率限制、发送日志
 */
class TeamsNotifierService
{
    protected array $config;

    public function __construct()
    {
        $this->config = config('teams-notifier');
    }

    /**
     * 仪表盘数据
     */
    public function getDashboard(int $tenantId): array
    {
        $webhooks = TeamsWebhook::byTenant($tenantId)->get();
        $total = $webhooks->count();
        $active = $webhooks->where('is_active', true)->count();

        $now = now();
        $today = TeamsNotificationLog::byTenant($tenantId)
            ->whereDate('created_at', $today = $now->toDateString())->count();
        $todaySuccess = TeamsNotificationLog::byTenant($tenantId)
            ->whereDate('created_at', $today)->byStatus('success')->count();
        $todayFailed = TeamsNotificationLog::byTenant($tenantId)
            ->whereDate('created_at', $today)->byStatus('failed')->count();

        $recentLogs = TeamsNotificationLog::byTenant($tenantId)
            ->latest()->take(10)->get();

        return [
            'stats' => [
                'total' => $total,
                'active' => $active,
                'today_total' => $today,
                'today_success' => $todaySuccess,
                'today_failed' => $todayFailed,
            ],
            'recent_logs' => $recentLogs,
        ];
    }

    /**
     * 获取 Webhook 列表
     */
    public function getWebhooks(int $tenantId): array
    {
        return TeamsWebhook::byTenant($tenantId)->orderByDesc('id')->get()->toArray();
    }

    /**
     * 创建 Webhook 配置
     */
    public function createWebhook(array $data): TeamsWebhook
    {
        return TeamsWebhook::create($data);
    }

    /**
     * 更新 Webhook 配置
     */
    public function updateWebhook(TeamsWebhook $webhook, array $data): TeamsWebhook
    {
        $webhook->update($data);
        return $webhook->fresh();
    }

    /**
     * 删除 Webhook 配置
     */
    public function deleteWebhook(TeamsWebhook $webhook): void
    {
        $webhook->logs()->delete();
        $webhook->delete();
    }

    /**
     * 测试 Webhook 连接
     */
    public function testConnection(TeamsWebhook $webhook): array
    {
        $payload = $this->buildAdaptiveCard(
            '✅ Teams 连接测试',
            '如果您看到此消息，说明 Teams 通知集成配置正确。',
            'info',
            [
                ['title' => '频道', 'value' => $webhook->name],
                ['title' => '测试时间', 'value' => now()->format('Y-m-d H:i:s')],
                ['title' => '来源', 'value' => config('app.name')],
            ]
        );

        try {
            $response = Http::timeout($this->config['timeout'] ?? 15)
                ->post($webhook->webhook_url, $payload);

            if ($response->successful()) {
                $this->log($webhook->tenant_id, $webhook->id, 'test', 'Teams 连接测试', '连接测试成功', 'success');
                return ['success' => true, 'message' => '连接测试成功'];
            }

            $errorMsg = "HTTP {$response->status()}: " . $response->body();
            $this->log($webhook->tenant_id, $webhook->id, 'test', 'Teams 连接测试', $errorMsg, 'failed', $response->status());
            return ['success' => false, 'message' => $errorMsg];
        } catch (\Throwable $e) {
            $this->log($webhook->tenant_id, $webhook->id, 'test', 'Teams 连接测试', $e->getMessage(), 'failed');
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * 发送 Teams 通知
     *
     * @param int $tenantId
     * @param string $type activation/alert/expiry
     * @param string $title 标题
     * @param string $message 消息正文
     * @param array $fields 附加字段
     * @param string|null $webhookId 指定 webhook（null=发送到所有匹配的）
     */
    public function send(
        int $tenantId,
        string $type,
        string $title,
        string $message,
        array $fields = [],
        ?int $webhookId = null,
    ): array {
        $results = ['sent' => 0, 'failed' => 0, 'errors' => []];

        $webhooks = TeamsWebhook::byTenant($tenantId)->active()->byType($type);
        if ($webhookId) {
            $webhooks = $webhooks->where('id', $webhookId);
        }

        /** @var TeamsWebhook $webhook */
        foreach ($webhooks->get() as $webhook) {
            $configType = $this->config['notification_types'][$type] ?? null;
            $color = $configType['color'] ?? 'info';

            $payload = $this->buildAdaptiveCard($title, $message, $color, $fields);

            try {
                $response = Http::timeout($this->config['timeout'] ?? 15)
                    ->post($webhook->webhook_url, $payload);

                if ($response->successful()) {
                    $this->log($tenantId, $webhook->id, $type, $title, $message, 'success');
                    $webhook->update(['last_sent_at' => now()]);
                    $results['sent']++;
                } else {
                    $errorMsg = "HTTP {$response->status()}";
                    $this->log($tenantId, $webhook->id, $type, $title, $message, 'failed', $response->status(), $errorMsg);
                    $results['failed']++;
                    $results['errors'][] = "{$webhook->name}: {$errorMsg}";
                }
            } catch (\Throwable $e) {
                $this->log($tenantId, $webhook->id, $type, $title, $message, 'failed', null, $e->getMessage());
                $results['failed']++;
                $results['errors'][] = "{$webhook->name}: {$e->getMessage()}";
            }
        }

        return $results;
    }

    /**
     * 发送激活成功通知
     */
    public function sendActivationSuccess(int $tenantId, string $licenseKey, string $productName, string $customerName): array
    {
        $title = $this->config['notification_types']['activation']['title'] ?? '✅ License 激活成功';
        $message = "客户 **{$customerName}** 已成功激活 License **{$licenseKey}**（{$productName}）";

        return $this->send($tenantId, 'activation', $title, $message, [
            ['title' => 'License', 'value' => $licenseKey],
            ['title' => '产品', 'value' => $productName],
            ['title' => '客户', 'value' => $customerName],
            ['title' => '激活时间', 'value' => now()->format('Y-m-d H:i:s')],
        ]);
    }

    /**
     * 发送异常告警
     */
    public function sendAlert(int $tenantId, string $alertTitle, string $alertMessage, string $severity = 'warning', array $extraFields = []): array
    {
        $title = $this->config['notification_types']['alert']['title'] ?? '🚨 系统异常告警';
        $fields = array_merge([
            ['title' => '严重程度', 'value' => strtoupper($severity)],
            ['title' => '时间', 'value' => now()->format('Y-m-d H:i:s')],
        ], $extraFields);

        return $this->send($tenantId, 'alert', $title, $alertMessage, $fields);
    }

    /**
     * 发送过期提醒
     */
    public function sendExpiryReminder(int $tenantId, string $licenseKey, string $productName, string $customerName, int $daysRemaining): array
    {
        $title = $this->config['notification_types']['expiry']['title'] ?? '⏰ License 过期提醒';
        $emoji = $daysRemaining <= 3 ? '🔴' : ($daysRemaining <= 7 ? '🟡' : '🟢');
        $message = "{$emoji} 客户 **{$customerName}** 的 License **{$licenseKey}**（{$productName}）将在 **{$daysRemaining} 天** 后过期";

        $fields = [
            ['title' => 'License', 'value' => $licenseKey],
            ['title' => '产品', 'value' => $productName],
            ['title' => '客户', 'value' => $customerName],
            ['title' => '剩余天数', 'value' => "{$daysRemaining} 天"],
            ['title' => '过期时间', 'value' => now()->addDays($daysRemaining)->format('Y-m-d')],
        ];

        return $this->send($tenantId, 'expiry', $title, $message, $fields);
    }

    /**
     * 构建 Adaptive Card 消息
     */
    protected function buildAdaptiveCard(string $title, string $message, string $color = 'info', array $fields = []): array
    {
        $themeColor = $this->config['theme_colors'][$color] ?? '2979FF';
        $brandName = $this->config['brand_name'] ?? '互物通';
        $brandIcon = $this->config['brand_icon'] ?? '';

        $body = [
            [
                'type' => 'TextBlock',
                'text' => $title,
                'weight' => 'bolder',
                'size' => 'large',
                'wrap' => true,
                'style' => 'heading',
            ],
            [
                'type' => 'TextBlock',
                'text' => $message,
                'wrap' => true,
                'size' => 'medium',
                'spacing' => 'medium',
            ],
        ];

        if (!empty($fields)) {
            $factSet = [
                'type' => 'FactSet',
                'facts' => [],
            ];

            foreach ($fields as $field) {
                $factSet['facts'][] = [
                    'title' => $field['title'] ?? '',
                    'value' => $field['value'] ?? '',
                ];
            }

            $body[] = [
                'type' => 'Container',
                'spacing' => 'medium',
                'items' => [$factSet],
                'style' => 'emphasis',
            ];
        }

        $body[] = [
            'type' => 'TextBlock',
            'text' => "🕐 " . now()->format('Y-m-d H:i:s') . " · {$brandName}",
            'size' => 'small',
            'isSubtle' => true,
            'spacing' => 'default',
            'wrap' => true,
        ];

        return [
            'type' => 'message',
            'attachments' => [
                [
                    'contentType' => 'application/vnd.microsoft.card.adaptive',
                    'content' => [
                        '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
                        'type' => 'AdaptiveCard',
                        'version' => '1.4',
                        'minHeight' => '100px',
                        'body' => $body,
                        'msteams' => [
                            'width' => 'Full',
                            'entities' => [],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * 获取发送日志
     */
    public function getLogs(array $params = []): array
    {
        $query = TeamsNotificationLog::query();

        if (!empty($params['tenant_id'])) {
            $query->byTenant($params['tenant_id']);
        }
        if (!empty($params['status'])) {
            $query->byStatus($params['status']);
        }
        if (!empty($params['notification_type'])) {
            $query->byType($params['notification_type']);
        }
        if (!empty($params['webhook_id'])) {
            $query->where('teams_webhook_id', $params['webhook_id']);
        }
        if (!empty($params['date_from'])) {
            $query->whereDate('created_at', '>=', $params['date_from']);
        }
        if (!empty($params['date_to'])) {
            $query->whereDate('created_at', '<=', $params['date_to']);
        }

        $perPage = (int) ($params['per_page'] ?? 20);
        $page = (int) ($params['page'] ?? 1);

        $total = $query->count();
        $items = $query->latest()->forPage($page, $perPage)->get();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * 记录发送日志
     */
    protected function log(int $tenantId, ?int $webhookId, string $type, string $title, ?string $message, string $status, ?int $httpStatus = null, ?string $error = null): void
    {
        try {
            TeamsNotificationLog::create([
                'tenant_id' => $tenantId,
                'teams_webhook_id' => $webhookId,
                'notification_type' => $type,
                'title' => $title,
                'message' => $message ? mb_substr($message, 0, 500) : null,
                'status' => $status,
                'http_status' => $httpStatus,
                'error_message' => $error ? mb_substr($error, 0, 500) : null,
            ]);
        } catch (\Throwable $e) {
            Log::error("TeamsNotifier: failed to write log", ['error' => $e->getMessage()]);
        }
    }
}
