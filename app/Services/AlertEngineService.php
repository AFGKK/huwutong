<?php

namespace App\Services;

use App\Models\AlertEvent;
use App\Models\AlertIntegration;
use App\Models\AlertRule;
use App\Models\ApmRequest;
use App\Models\License;
use App\Models\SdkHeartbeat;
use App\Models\Subscription;
use App\Models\SslCertificate;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 智能告警引擎
 *
 * 功能：
 * - 规则驱动的告警检测（定时任务调用）
 * - 多渠道通知（Email / SMS / Slack / DingTalk / Webhook）
 * - 告警生命周期管理（Firing → Acknowledged → Resolved）
 * - 去重与频率限制（冷却期 + 每日限额）
 * - 预置告警模板 + 自定义 Webhook
 */
class AlertEngineService
{
    public function __construct(
        protected NotificationService $notificationService,
        protected MultiChannelNotifier $notifier,
    ) {}

    /**
     * 执行全量告警检测 — 由定时任务调用
     */
    public function evaluateAllRules(): array
    {
        $stats = ['evaluated' => 0, 'fired' => 0, 'skipped_cooldown' => 0, 'skipped_limited' => 0, 'errors' => 0];

        $rules = AlertRule::where('is_active', true)->get();

        foreach ($rules as $rule) {
            $stats['evaluated']++;

            try {
                $shouldFire = $this->evaluateRule($rule);

                if ($shouldFire) {
                    if (!$rule->canFire()) {
                        if (!$rule->isCooldownPassed()) $stats['skipped_cooldown']++;
                        else $stats['skipped_limited']++;
                        continue;
                    }

                    $this->fireRule($rule);
                    $stats['fired']++;
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
                Log::error("AlertEngine: rule #{$rule->id} evaluation failed", [
                    'rule' => $rule->slug,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('AlertEngine: evaluation completed', $stats);
        return $stats;
    }

    /**
     * 评估单条规则是否应触发
     */
    protected function evaluateRule(AlertRule $rule): bool
    {
        return match ($rule->metric_type) {
            'license_expiry' => $this->checkLicenseExpiry($rule),
            'activation_burst' => $this->checkActivationBurst($rule),
            'heartbeat_missed' => $this->checkHeartbeatMissed($rule),
            'payment_failed' => $this->checkPaymentFailed($rule),
            'apm_slow' => $this->checkApmSlow($rule),
            'sdk_deprecated' => $this->checkSdkDeprecated($rule),
            'certificate_expiry' => $this->checkCertificateExpiry($rule),
            default => false,
        };
    }

    /**
     * 触发告警
     */
    public function fireRule(AlertRule $rule, ?array $context = null): AlertEvent
    {
        $eventData = $this->buildAlertData($rule, $context);

        $event = AlertEvent::create($eventData);

        // 发送多渠道通知
        $channelsSent = $this->sendNotifications($rule, $event);
        if (!empty($channelsSent)) {
            $event->update(['channels_sent' => $channelsSent]);
        }

        $rule->recordFire();

        Log::info("AlertEngine: rule {$rule->slug} fired", [
            'event_id' => $event->id,
            'severity' => $rule->severity,
            'channels' => $channelsSent,
        ]);

        return $event;
    }

    /**
     * 手动触发告警（不用规则，直接创建事件）
     */
    public function fireManual(
        string $title,
        string $message,
        string $severity = 'warning',
        string $eventType = 'manual',
        ?array $context = null,
        ?string $sourceType = null,
        ?int $sourceId = null,
    ): AlertEvent {
        $event = AlertEvent::create([
            'alert_rule_id' => null,
            'event_type' => $eventType,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'status' => 'firing',
            'context' => $context,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'fired_at' => now(),
        ]);

        // 发送给所有匹配的集成
        $integrations = AlertIntegration::where('is_active', true)->get();
        $channelsSent = [];
        foreach ($integrations as $integration) {
            if ($integration->acceptsSeverity($severity)) {
                $this->sendToIntegration($integration, $title, $message, $severity, $context);
                $channelsSent[] = $integration->type;
            }
        }
        if (!empty($channelsSent)) {
            $event->update(['channels_sent' => $channelsSent]);
        }

        return $event;
    }

    /**
     * 确认告警
     */
    public function acknowledge(AlertEvent $event, int $userId): AlertEvent
    {
        $event->acknowledge($userId);
        return $event;
    }

    /**
     * 解决告警
     */
    public function resolve(AlertEvent $event, int $userId): AlertEvent
    {
        $event->resolve($userId);
        return $event;
    }

    /**
     * 批量解决告警（按规则）
     */
    public function resolveByRule(AlertRule $rule, int $userId): int
    {
        return AlertEvent::where('alert_rule_id', $rule->id)
            ->where('status', 'firing')
            ->update([
                'status' => 'resolved',
                'resolved_at' => now(),
                'resolved_by' => $userId,
            ]);
    }

    /**
     * 测试集成连通性
     */
    public function testIntegration(AlertIntegration $integration): bool
    {
        try {
            $result = $this->sendToIntegration(
                $integration,
                '【测试消息】告警引擎集成测试',
                '这是一条测试消息，用于验证集成配置是否正确。',
                'info',
                ['test' => true, 'timestamp' => now()->toIso8601String()],
            );
            $integration->update(['last_test_at' => now()]);
            return $result;
        } catch (\Throwable $e) {
            Log::error("AlertEngine: integration test failed", [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    // ─────────────────────────────────────────────
    //  规则检测实现
    // ─────────────────────────────────────────────

    protected function checkLicenseExpiry(AlertRule $rule): bool
    {
        $thresholdDays = (int) $rule->threshold;
        $operator = $rule->condition_operator;

        $query = License::where('status', 'active')
            ->whereNotNull('expires_at');

        $now = now();
        $targetDate = match ($operator) {
            'lte' => $now->copy()->addDays($thresholdDays),
            'lt' => $now->copy()->addDays($thresholdDays),
            'eq' => $now->copy()->addDays($thresholdDays),
            default => $now->copy()->addDays($thresholdDays),
        };

        $count = (clone $query)
            ->where('expires_at', '<=', $targetDate)
            ->where('expires_at', '>', $now)
            ->count();

        return $count > 0;
    }

    protected function checkActivationBurst(AlertRule $rule): bool
    {
        $threshold = (int) $rule->threshold;
        $minutes = max(1, $rule->duration_minutes);

        $count = \App\Models\LicenseAnalyticsEvent::where('event_type', 'activation')
            ->where('occurred_at', '>=', now()->subMinutes($minutes))
            ->count();

        return $count >= $threshold;
    }

    protected function checkHeartbeatMissed(AlertRule $rule): bool
    {
        $thresholdMinutes = max(5, (int) $rule->threshold);

        $count = License::where('status', 'active')
            ->whereDoesntHave('devices', function ($q) use ($thresholdMinutes) {
                $q->where('last_seen_at', '>=', now()->subMinutes($thresholdMinutes));
            })
            ->count();

        return $count > 0;
    }

    protected function checkPaymentFailed(AlertRule $rule): bool
    {
        $threshold = (int) $rule->threshold;
        $minutes = max(60, $rule->duration_minutes);

        $count = Subscription::where('status', 'grace')
            ->where('grace_ends_at', '>', now())
            ->where('updated_at', '>=', now()->subMinutes($minutes))
            ->count();

        return $count >= $threshold;
    }

    protected function checkApmSlow(AlertRule $rule): bool
    {
        $thresholdMs = (int) $rule->threshold;
        $minutes = max(5, $rule->duration_minutes);

        $count = ApmRequest::where('is_slow', true)
            ->where('duration_ms', '>=', $thresholdMs)
            ->where('created_at', '>=', now()->subMinutes($minutes))
            ->count();

        return $count > 0;
    }

    protected function checkSdkDeprecated(AlertRule $rule): bool
    {
        $thresholdDays = (int) $rule->threshold;
        $cutoff = now()->subDays($thresholdDays);

        $count = SdkHeartbeat::where('reported_at', '>=', $cutoff)
            ->selectRaw('sdk_version, count(*) as count')
            ->groupBy('sdk_version')
            ->having('count', '>', 0)
            ->get()
            ->filter(function ($hb) {
                return version_compare($hb->sdk_version, '2.0.0', '<');
            })
            ->count();

        return $count > 0;
    }

    protected function checkCertificateExpiry(AlertRule $rule): bool
    {
        if (!class_exists(SslCertificate::class)) return false;

        $thresholdDays = (int) $rule->threshold;

        $count = SslCertificate::where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays($thresholdDays))
            ->where('expires_at', '>', now())
            ->count();

        return $count > 0;
    }

    // ─────────────────────────────────────────────
    //  通知发送
    // ─────────────────────────────────────────────

    protected function buildAlertData(AlertRule $rule, ?array $context = null): array
    {
        $templates = $this->getAlertTemplates();
        $template = $templates[$rule->metric_type] ?? $templates['default'];

        $title = $template['title'] ?? $rule->name;
        $message = $template['message'] ?? $rule->description ?? $rule->name;

        return [
            'alert_rule_id' => $rule->id,
            'event_type' => $rule->metric_type,
            'severity' => $rule->severity,
            'title' => $title,
            'message' => $message,
            'status' => 'firing',
            'context' => $context,
            'fired_at' => now(),
        ];
    }

    protected function sendNotifications(AlertRule $rule, AlertEvent $event): array
    {
        $channelsSent = [];
        $channels = $rule->channels ?? ['database'];
        $title = $event->title;
        $message = $event->message;

        foreach ($channels as $channel) {
            try {
                match ($channel) {
                    'database' => $this->sendDatabaseNotification($title, $message),
                    'slack' => $this->sendSlack($rule->slack_webhook, $title, $message, $rule->severity),
                    'dingtalk' => $this->sendDingTalk($rule->dingtalk_webhook, $title, $message),
                    'webhook' => $this->sendWebhook($rule->webhook_urls ?? [], $title, $message, $rule->severity),
                    default => null,
                };
                $channelsSent[] = $channel;
            } catch (\Throwable $e) {
                Log::error("AlertEngine: channel {$channel} send failed", [
                    'rule_id' => $rule->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 也发送到配置的集成
        $integrations = AlertIntegration::where('is_active', true)->get();
        foreach ($integrations as $integration) {
            if ($integration->acceptsSeverity($rule->severity)) {
                $this->sendToIntegration($integration, $title, $message, $rule->severity, $event->context);
                $channelsSent[] = $integration->type;
            }
        }

        return array_unique($channelsSent);
    }

    protected function sendDatabaseNotification(string $title, string $message): void
    {
        // 发送到所有管理员
        $admins = \App\Models\User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['admin', 'super-admin']);
        })->get();

        foreach ($admins as $admin) {
            $this->notificationService->send(
                $admin->id,
                'alert',
                $title,
                $message,
                ['alert' => true, 'fired_at' => now()->toIso8601String()],
            );
        }
    }

    // ─────────────────────────────────────────────
    //  集成发送
    // ─────────────────────────────────────────────

    public function sendToIntegration(
        AlertIntegration $integration,
        string $title,
        string $message,
        string $severity = 'warning',
        ?array $context = null,
    ): bool {
        return match ($integration->type) {
            'slack' => $this->sendSlack($integration->webhook_url, $title, $message, $severity),
            'dingtalk' => $this->sendDingTalk($integration->webhook_url, $title, $message),
            'webhook' => $this->sendWebhook([$integration->webhook_url], $title, $message, $severity, $integration->config ?? []),
            'email_group' => $this->sendEmailGroup($integration, $title, $message),
            default => false,
        };
    }

    protected function sendSlack(?string $webhookUrl, string $title, string $message, string $severity): bool
    {
        if (!$webhookUrl) return false;

        $color = match ($severity) {
            'critical' => '#dc3545',
            'warning' => '#ffc107',
            default => '#17a2b8',
        };

        $payload = [
            'attachments' => [[
                'color' => $color,
                'title' => $title,
                'text' => $message,
                'footer' => 'HWT Alert Engine',
                'ts' => now()->timestamp,
            ]],
        ];

        $response = Http::timeout(10)->post($webhookUrl, $payload);
        return $response->successful();
    }

    protected function sendDingTalk(?string $webhookUrl, string $title, string $message): bool
    {
        if (!$webhookUrl) return false;

        $payload = [
            'msgtype' => 'text',
            'text' => [
                'content' => "【HWT告警】{$title}\n\n{$message}\n\n---\n时间：" . now()->toDateTimeString(),
            ],
        ];

        $response = Http::timeout(10)->post($webhookUrl, $payload);
        return $response->successful();
    }

    protected function sendWebhook(
        array $urls,
        string $title,
        string $message,
        string $severity,
        array $extraConfig = [],
    ): bool {
        $success = true;
        $payload = array_merge([
            'event' => 'alert',
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'timestamp' => now()->toIso8601String(),
            'source' => 'hwt-alert-engine',
        ], $extraConfig);

        foreach ($urls as $url) {
            try {
                $response = Http::timeout(10)->post($url, $payload);
                if (!$response->successful()) $success = false;
            } catch (\Throwable $e) {
                $success = false;
                Log::warning("AlertEngine: webhook {$url} failed", ['error' => $e->getMessage()]);
            }
        }

        return $success;
    }

    protected function sendEmailGroup(AlertIntegration $integration, string $title, string $message): bool
    {
        $emails = $integration->config['emails'] ?? [];
        if (empty($emails)) return false;

        foreach ($emails as $email) {
            try {
                \Illuminate\Support\Facades\Mail::to($email)->send(new \App\Mail\GenericNotification(
                    title: $title,
                    content: $message,
                    payload: ['alert' => true],
                ));
            } catch (\Throwable $e) {
                Log::warning("AlertEngine: email to {$email} failed", ['error' => $e->getMessage()]);
            }
        }

        return true;
    }

    // ─────────────────────────────────────────────
    //  模板和看板
    // ─────────────────────────────────────────────

    public function getAlertTemplates(): array
    {
        return [
            'license_expiry' => [
                'title' => 'License 即将过期',
                'message' => '有 License 即将在阈值时间内过期，请及时处理续费。',
            ],
            'activation_burst' => [
                'title' => '激活异常激增',
                'message' => '检测到短时间内大量 License 激活请求，可能存在异常活动。',
            ],
            'heartbeat_missed' => [
                'title' => '心跳丢失告警',
                'message' => '部分活跃 License 超过阈值时间未上报心跳。',
            ],
            'payment_failed' => [
                'title' => '支付失败聚集',
                'message' => '检测到多笔支付失败，存在计费风险。',
            ],
            'apm_slow' => [
                'title' => 'API 响应缓慢',
                'message' => '检测到大量慢请求，系统性能可能下降。',
            ],
            'sdk_deprecated' => [
                'title' => 'SDK 版本过旧',
                'message' => '检测到大量客户端使用过旧 SDK 版本，建议推动升级。',
            ],
            'certificate_expiry' => [
                'title' => 'SSL 证书即将过期',
                'message' => '有 SSL 证书即将过期，请及时续期。',
            ],
            'default' => [
                'title' => '系统告警',
                'message' => '系统检测到异常情况，请查看详情。',
            ],
        ];
    }

    public function getMetricTypeOptions(): array
    {
        return [
            'license_expiry' => 'License 过期',
            'activation_burst' => '激活激增',
            'heartbeat_missed' => '心跳丢失',
            'payment_failed' => '支付失败',
            'apm_slow' => 'API 慢请求',
            'sdk_deprecated' => 'SDK 版本过旧',
            'certificate_expiry' => 'SSL 证书过期',
            'custom' => '自定义',
        ];
    }

    public function getSeverityOptions(): array
    {
        return [
            'critical' => '严重',
            'warning' => '警告',
            'info' => '信息',
        ];
    }

    public function getDashboardData(): array
    {
        $now = now();

        $firingCount = AlertEvent::where('status', 'firing')->count();
        $acknowledgedCount = AlertEvent::where('status', 'acknowledged')->count();
        $resolvedCount = AlertEvent::where('status', 'resolved')->count();
        $totalToday = AlertEvent::whereDate('fired_at', today())->count();

        $bySeverity = AlertEvent::whereIn('status', ['firing', 'acknowledged'])
            ->selectRaw('severity, count(*) as count')
            ->groupBy('severity')
            ->pluck('count', 'severity')
            ->toArray();

        $byRule = AlertEvent::whereIn('status', ['firing', 'acknowledged'])
            ->selectRaw('alert_rule_id, count(*) as count')
            ->groupBy('alert_rule_id')
            ->with('rule:id,name')
            ->get()
            ->map(fn($e) => [
                'rule_id' => $e->alert_rule_id,
                'rule_name' => $e->rule?->name ?? '手动触发',
                'count' => $e->count,
            ])
            ->toArray();

        $trend = AlertEvent::where('fired_at', '>=', $now->copy()->subDays(7))
            ->selectRaw('DATE(fired_at) as date, count(*) as count')
            ->groupByRaw('DATE(fired_at)')
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();

        $trendData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = $now->copy()->subDays($i)->format('Y-m-d');
            $trendData[] = ['date' => $date, 'count' => (int) ($trend[$date] ?? 0)];
        }

        $activeRules = AlertRule::where('is_active', true)->count();
        $totalRules = AlertRule::count();

        return [
            'firing_count' => $firingCount,
            'acknowledged_count' => $acknowledgedCount,
            'resolved_count' => $resolvedCount,
            'total_today' => $totalToday,
            'by_severity' => $bySeverity,
            'by_rule' => $byRule,
            'trend' => $trendData,
            'active_rules' => $activeRules,
            'total_rules' => $totalRules,
        ];
    }
}
