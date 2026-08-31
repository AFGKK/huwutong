<?php

namespace App\Services;

use App\Models\AlertChannel;
use App\Models\AlertEscalation;
use App\Models\AlertEscalationLog;
use App\Models\AlertEvent;
use App\Models\AlertIntegration;
use App\Models\AlertNotificationLog;
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
 * 智能告警引擎（已合并原 AlertingService）
 *
 * 功能：
 * - 规则驱动的告警检测（定时任务调用）
 * - 多渠道通知（Email / SMS / Slack / DingTalk / Webhook）
 * - 告警生命周期管理（Firing → Acknowledged → Resolved）
 * - 去重与频率限制（冷却期 + 每日限额）
 * - 预置告警模板 + 自定义 Webhook
 * - 通知渠道管理、升级策略、事件统计
 */
class AlertEngineService
{
    public function __construct(
        protected NotificationService $notificationService,
        protected MultiChannelNotifier $notifier,
    ) {}

    // ═══════════════════════════════════════════════════════════
    //  来自 AlertEngineService — 规则评估引擎
    // ═══════════════════════════════════════════════════════════

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
                __('app.alert_engine.test_title'),
                __('app.alert_engine.test_body'),
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
    //  通知发送（AlertEngine 集成方式）
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
                'content' => strtr(__('app.alert_engine.dingtalk_template'), [
                    ':title' => $title,
                    ':message' => $message,
                    ':time' => now()->toDateTimeString(),
                ]),
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
        $t = fn(string $k) => __('app.alert_engine.templates.' . $k);

        return [
            'license_expiry' => [
                'title' => $t('license_expiry'),
                'message' => $t('license_expiry_msg'),
            ],
            'activation_burst' => [
                'title' => $t('activation_burst'),
                'message' => $t('activation_burst_msg'),
            ],
            'heartbeat_missed' => [
                'title' => $t('heartbeat_missed'),
                'message' => $t('heartbeat_missed_msg'),
            ],
            'payment_failed' => [
                'title' => $t('payment_failed'),
                'message' => $t('payment_failed_msg'),
            ],
            'apm_slow' => [
                'title' => $t('apm_slow'),
                'message' => $t('apm_slow_msg'),
            ],
            'sdk_deprecated' => [
                'title' => $t('sdk_deprecated'),
                'message' => $t('sdk_deprecated_msg'),
            ],
            'certificate_expiry' => [
                'title' => $t('certificate_expiry'),
                'message' => $t('certificate_expiry_msg'),
            ],
            'default' => [
                'title' => $t('default'),
                'message' => $t('default_msg'),
            ],
        ];
    }

    public function getMetricTypeOptions(): array
    {
        $t = fn(string $k) => __('app.alert_engine.metric_types.' . $k);

        return [
            'license_expiry' => $t('license_expiry'),
            'activation_burst' => $t('activation_burst'),
            'heartbeat_missed' => $t('heartbeat_missed'),
            'payment_failed' => $t('payment_failed'),
            'apm_slow' => $t('apm_slow'),
            'sdk_deprecated' => $t('sdk_deprecated'),
            'certificate_expiry' => $t('certificate_expiry'),
            'custom' => $t('custom'),
        ];
    }

    public function getSeverityOptions(): array
    {
        $t = fn(string $k) => __('app.alert_engine.severity_options.' . $k);

        return [
            'critical' => $t('critical'),
            'warning' => $t('warning'),
            'info' => $t('info'),
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
                'rule_name' => $e->rule?->name ?? __('app.alert_engine.manual_trigger'),
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

    // ═══════════════════════════════════════════════════════════
    //  来自 AlertingService — 概览、规则、渠道、升级策略、事件、统计、元数据
    // ═══════════════════════════════════════════════════════════

    // ─── 概览仪表盘 ───

    public function getDashboard(int $tenantId = null): array
    {
        $query = fn($q) => $tenantId ? $q->where('tenant_id', $tenantId) : $q;

        $rulesQuery = AlertRule::when($tenantId, fn($q) => $q->whereIn('id',
            AlertRule::select('id')->whereRaw('1=1')
        ));

        $totalRules = AlertRule::count();
        $activeRules = AlertRule::where('is_active', true)->count();

        $firingEvents = AlertEvent::where('status', 'firing')->count();
        $todayEvents = AlertEvent::whereDate('fired_at', today())->count();

        $bySeverity = AlertEvent::selectRaw('severity, COUNT(*) as count')
            ->groupBy('severity')->pluck('count', 'severity')->toArray();

        $recentEvents = AlertEvent::with('rule:id,name')
            ->orderByDesc('fired_at')->limit(10)->get()->toArray();

        $channels = AlertChannel::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('is_enabled', true)->count();

        return [
            'total_rules' => $totalRules,
            'active_rules' => $activeRules,
            'firing_events' => $firingEvents,
            'today_events' => $todayEvents,
            'active_channels' => $channels,
            'by_severity' => $bySeverity,
            'recent_events' => $recentEvents,
        ];
    }

    // ─── 告警规则 ───

    public function getRules(int $tenantId = null, array $filters = []): array
    {
        $query = AlertRule::with('channels:id,name,type')
            ->when(isset($filters['is_active']), fn($q) => $q->where('is_active', $filters['is_active'] === 'true'))
            ->when($filters['metric_type'] ?? null, fn($q, $v) => $q->where('metric_type', $v))
            ->when($filters['severity'] ?? null, fn($q, $v) => $q->where('severity', $v))
            ->orderByDesc('created_at');

        return $query->get()->toArray();
    }

    public function getRule(int $id): AlertRule
    {
        return AlertRule::with(['channels', 'escalations', 'events' => function ($q) {
            $q->orderByDesc('fired_at')->limit(20);
        }])->findOrFail($id);
    }

    public function createRule(array $data): AlertRule
    {
        $data['slug'] ??= str_slug($data['name']) . '-' . uniqid();
        $rule = AlertRule::create($data);

        if (!empty($data['channel_ids'])) {
            $rule->channels()->sync($data['channel_ids']);
        }

        return $rule->fresh('channels');
    }

    public function updateRule(AlertRule $rule, array $data): AlertRule
    {
        $rule->update($data);

        if (array_key_exists('channel_ids', $data)) {
            $rule->channels()->sync($data['channel_ids'] ?? []);
        }

        return $rule->fresh('channels');
    }

    public function deleteRule(AlertRule $rule): void
    {
        $rule->channels()->detach();
        $rule->delete();
    }

    // ─── 通知渠道 ───

    public function getChannels(int $tenantId = null): array
    {
        return AlertChannel::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->withCount('rules')
            ->orderBy('sort_order')
            ->get()
            ->toArray();
    }

    public function createChannel(array $data): AlertChannel
    {
        return AlertChannel::create($data);
    }

    public function updateChannel(AlertChannel $channel, array $data): AlertChannel
    {
        $channel->update($data);
        return $channel->fresh();
    }

    public function deleteChannel(AlertChannel $channel): void
    {
        $channel->rules()->detach();
        $channel->delete();
    }

    public function testChannel(AlertChannel $channel): array
    {
        $payload = match ($channel->type) {
            'slack' => ['text' => "🧪 " . __('app.alerting.test_notification_title') . "\n" . __('app.alerting.channel_label') . ": {$channel->name}\n" . __('app.alerting.time_label') . ": " . now()->format('Y-m-d H:i:s')],
            'dingtalk' => ['msgtype' => 'text', 'text' => ['content' => "🧪 " . __('app.alerting.test_notification_title') . "\n" . __('app.alerting.channel_label') . ": {$channel->name}"]],
            'webhook' => ['event' => 'test', 'channel' => $channel->name, 'timestamp' => now()->toIso8601String()],
            default => ['message' => "Test notification from Huwutong Alerting", 'channel' => $channel->name],
        };

        try {
            $url = $channel->config['webhook_url'] ?? '';
            if (empty($url)) return ['success' => false, 'error' => __('app.alerting.webhook_url_not_set')];

            $response = Http::timeout(10)->post($url, $payload);
            $channel->update(['last_test_at' => now()]);

            if ($response->successful()) {
                return ['success' => true];
            }

            return ['success' => false, 'error' => "HTTP {$response->status()}: " . $response->body()];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ─── 升级策略 ───

    public function getEscalations(int $tenantId = null, int $ruleId = null): array
    {
        return AlertEscalation::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->when($ruleId, fn($q, $v) => $q->where('alert_rule_id', $v))
            ->orderBy('escalation_level')
            ->get()
            ->toArray();
    }

    public function createEscalation(array $data): AlertEscalation
    {
        return AlertEscalation::create($data);
    }

    public function updateEscalation(AlertEscalation $escalation, array $data): AlertEscalation
    {
        $escalation->update($data);
        return $escalation->fresh();
    }

    public function deleteEscalation(int $id): void
    {
        AlertEscalation::findOrFail($id)->delete();
    }

    // ─── 告警事件 ───

    public function getEvents(int $tenantId = null, array $filters = []): array
    {
        $query = AlertEvent::with('rule:id,name')
            ->when($filters['status'] ?? null, fn($q, $v) => $q->where('status', $v))
            ->when($filters['severity'] ?? null, fn($q, $v) => $q->where('severity', $v))
            ->when($filters['event_type'] ?? null, fn($q, $v) => $q->where('event_type', $v))
            ->orderByDesc('fired_at');

        return $query->paginate(
            $filters['per_page'] ?? 50,
            ['*'],
            'page',
            $filters['page'] ?? 1
        )->toArray();
    }

    public function getEvent(int $id): AlertEvent
    {
        return AlertEvent::with([
            'rule:id,name,severity,metric_type',
            'notificationLogs' => fn($q) => $q->orderByDesc('created_at'),
            'escalationLogs' => fn($q) => $q->orderByDesc('escalation_level'),
        ])->findOrFail($id);
    }

    public function acknowledgeEvent(AlertEvent $event, int $userId): AlertEvent
    {
        $event->acknowledge($userId);
        return $event->fresh();
    }

    public function resolveEvent(AlertEvent $event, int $userId): AlertEvent
    {
        $event->resolve($userId);
        return $event->fresh();
    }

    // ─── 触发告警（核心逻辑） ───

    /**
     * 触发告警：评估规则 → 创建事件 → 发送通知 → 检查升级
     */
    public function fireAlert(int $ruleId, array $context): ?AlertEvent
    {
        $rule = AlertRule::with('channels', 'escalations')->find($ruleId);
        if (!$rule || !$rule->canFire()) return null;

        $severity = $context['severity'] ?? $rule->severity;
        $title = $context['title'] ?? __('app.alerting.alert_title_prefix') . ': ' . $rule->name;
        $message = $context['message'] ?? __('app.alerting.rule_triggered', ['name' => $rule->name]);

        // 创建事件
        $event = AlertEvent::create([
            'alert_rule_id' => $rule->id,
            'event_type' => $rule->metric_type,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'status' => 'firing',
            'context' => $context,
            'source_type' => $context['source_type'] ?? null,
            'source_id' => $context['source_id'] ?? null,
            'fired_at' => now(),
        ]);

        $rule->recordFire();

        // 发送通知
        $channels = $rule->channels;
        $sentChannels = [];

        foreach ($channels as $channel) {
            if (!$channel->is_enabled) continue;
            $result = $this->sendNotification($channel, $event);
            $sentChannels[] = $channel->type;

            AlertNotificationLog::create([
                'alert_event_id' => $event->id,
                'alert_channel_id' => $channel->id,
                'channel_type' => $channel->type,
                'status' => $result['success'] ? 'sent' : 'failed',
                'response' => $result['error'] ?? null,
                'sent_at' => $result['success'] ? now() : null,
            ]);
        }

        $event->update(['channels_sent' => $sentChannels]);

        // 检查升级策略
        $this->evaluateEscalation($rule, $event);

        return $event->fresh();
    }

    /**
     * 发送通知到指定渠道
     */
    protected function sendNotification(AlertChannel $channel, AlertEvent $event): array
    {
        $config = $channel->config;
        $webhookUrl = $config['webhook_url'] ?? '';

        if (empty($webhookUrl)) {
            return ['success' => false, 'error' => __('app.alerting.webhook_url_not_set')];
        }

        $payload = $this->buildPayload($channel->type, $event, $channel->name);

        try {
            $response = Http::timeout(10)->post($webhookUrl, $payload);

            if ($response->successful()) {
                return ['success' => true];
            }

            Log::warning("Alert notification failed to {$channel->type}: HTTP {$response->status()}", [
                'event_id' => $event->id,
                'channel' => $channel->name,
            ]);

            return ['success' => false, 'error' => "HTTP {$response->status()}"];
        } catch (\Exception $e) {
            Log::error("Alert notification exception to {$channel->type}: {$e->getMessage()}", [
                'event_id' => $event->id,
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 构建发送载荷
     */
    protected function buildPayload(string $type, AlertEvent $event, string $channelName): array
    {
        $color = match ($event->severity) { 'critical' => 'danger', 'warning' => 'warning', default => 'info' };

        return match ($type) {
            'slack' => [
                'attachments' => [[
                    'color' => $color,
                    'title' => $event->title,
                    'text' => $event->message,
                    'fields' => [
                        ['title' => __('app.alerting.field_rule'), 'value' => $event->rule?->name ?? 'N/A', 'short' => true],
                        ['title' => __('app.alerting.field_severity'), 'value' => $event->severity, 'short' => true],
                        ['title' => __('app.alerting.field_time'), 'value' => $event->fired_at?->format('Y-m-d H:i:s') ?? 'N/A', 'short' => false],
                    ],
                    'footer' => __('app.alerting.slack_footer', ['channel' => $channelName]),
                    'ts' => now()->timestamp,
                ]],
            ],
            'dingtalk' => [
                'msgtype' => 'markdown',
                'markdown' => [
                    'title' => "【{$event->severity}】{$event->title}",
                    'text' => "## {$event->title}\n\n**严重程度**: {$event->severity}\n**规则**: {$event->rule?->name}\n**消息**: {$event->message}\n**时间**: {$event->fired_at?->format('Y-m-d H:i:s')}\n\n---\n*" . __('app.alerting.dingtalk_footer') . "*",
                ],
            ],
            'feishu' => [
                'msg_type' => 'interactive',
                'card' => [
                    'header' => ['title' => ['tag' => 'plain_text', 'content' => "【{$event->severity}】{$event->title}"], 'template' => $color],
                    'elements' => [
                        ['tag' => 'div', 'text' => ['tag' => 'lark_md', 'content' => $event->message]],
                        ['tag' => 'hr'],
                        ['tag' => 'note', 'elements' => [['tag' => 'plain_text', 'content' => __('app.alerting.slack_footer', ['channel' => $channelName])]]],
                    ],
                ],
            ],
            default => [
                'event' => 'alert',
                'title' => $event->title,
                'message' => $event->message,
                'severity' => $event->severity,
                'rule' => $event->rule?->name,
                'timestamp' => $event->fired_at?->toIso8601String(),
                'source' => 'huwutong-alerting',
            ],
        };
    }

    /**
     * 评估升级策略
     */
    protected function evaluateEscalation(AlertRule $rule, AlertEvent $event): void
    {
        // 升级在 Job 中异步处理，此处记录事件已触发升级检查
        // 后续可由调度任务或队列检查未处理告警的升级
        if ($rule->escalations->isEmpty()) return;

        // 为每个升级策略创建日志（pending 状态，后续由队列处理）
        foreach ($rule->escalations as $escalation) {
            if (!$escalation->is_enabled) continue;

            AlertEscalationLog::create([
                'alert_event_id' => $event->id,
                'alert_escalation_id' => $escalation->id,
                'escalation_level' => $escalation->escalation_level,
                'notify_type' => $escalation->notify_type,
                'status' => 'pending',
            ]);
        }
    }

    /**
     * 执行待处理的升级（由调度任务调用）
     */
    public function processPendingEscalations(): int
    {
        $processed = 0;

        $pendingLogs = AlertEscalationLog::with(['event', 'escalation'])
            ->where('status', 'pending')
            ->whereHas('event', fn($q) => $q->where('status', 'firing'))
            ->get();

        foreach ($pendingLogs as $log) {
            if (!$log->escalation) continue;

            $escalation = $log->escalation;
            $minutesSinceFire = $log->event->fired_at->diffInMinutes(now());

            if ($minutesSinceFire >= $escalation->after_minutes) {
                // 执行升级通知
                $success = $this->executeEscalation($log->event, $escalation);
                $log->update([
                    'status' => $success ? 'sent' : 'failed',
                    'response' => $success ? __('app.alerting.escalation_sent') : __('app.alerting.escalation_failed'),
                ]);
                $processed++;
            }
        }

        return $processed;
    }

    protected function executeEscalation(AlertEvent $event, AlertEscalation $escalation): bool
    {
        $target = $escalation->notify_target;
        $message = $escalation->message_template
            ? str_replace(
                ['{title}', '{message}', '{severity}', '{rule}', '{time}'],
                [$event->title, $event->message, $event->severity, $event->rule?->name ?? 'N/A', $event->fired_at?->format('Y-m-d H:i:s')],
                $escalation->message_template
            )
            : ("[".__('app.alerting.escalation_level_prefix')." {$escalation->escalation_level}] {$event->title}: {$event->message}");

        try {
            match ($escalation->notify_type) {
                'slack' => $this->sendSlackEscalation($target['webhook_url'] ?? '', $message, $escalation->escalation_level),
                'email' => $this->sendEmailEscalation($target['emails'] ?? [], $message),
                'webhook' => $this->sendWebhookEscalation($target['webhook_url'] ?? '', $message),
                default => null,
            };
            return true;
        } catch (\Exception $e) {
            Log::error("Escalation failed: {$e->getMessage()}");
            return false;
        }
    }

    protected function sendSlackEscalation(string $webhookUrl, string $message, int $level): void
    {
        if (empty($webhookUrl)) return;
        Http::timeout(10)->post($webhookUrl, [
            'text' => "🚨 *[".__('app.alerting.escalation_level_prefix')." {$level}]* {$message}",
        ]);
    }

    protected function sendEmailEscalation(array $emails, string $message): void
    {
        // 由 Mail 系统处理
        Log::info("Escalation email would be sent to: " . implode(',', $emails), ['message' => $message]);
    }

    protected function sendWebhookEscalation(string $webhookUrl, string $message): void
    {
        if (empty($webhookUrl)) return;
        Http::timeout(10)->post($webhookUrl, ['event' => 'escalation', 'message' => $message]);
    }

    // ─── 事件统计 ───

    public function getEventStats(int $tenantId = null, string $startDate, string $endDate): array
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate)->endOfDay();

        // 每日趋势
        $dailyTrend = AlertEvent::whereBetween('fired_at', [$start, $end])
            ->selectRaw('DATE(fired_at) as date, COUNT(*) as count, severity')
            ->groupBy('date', 'severity')
            ->orderBy('date')
            ->get()
            ->groupBy('date');

        // 按类型统计
        $byType = AlertEvent::whereBetween('fired_at', [$start, $end])
            ->selectRaw('event_type, COUNT(*) as count')
            ->groupBy('event_type')
            ->orderByDesc('count')
            ->get()
            ->toArray();

        // 按严重程度统计
        $bySeverity = AlertEvent::whereBetween('fired_at', [$start, $end])
            ->selectRaw('severity, COUNT(*) as count')
            ->groupBy('severity')
            ->pluck('count', 'severity')
            ->toArray();

        // 处理率
        $total = AlertEvent::whereBetween('fired_at', [$start, $end])->count();
        $resolved = AlertEvent::whereBetween('fired_at', [$start, $end])
            ->where('status', 'resolved')->count();

        return [
            'total_events' => $total,
            'resolved_rate' => $total > 0 ? round(($resolved / $total) * 100, 1) : 0,
            'by_type' => $byType,
            'by_severity' => $bySeverity,
            'daily_trend' => $dailyTrend->map(fn($items, $date) => [
                'date' => $date,
                'total' => $items->sum('count'),
                'critical' => $items->where('severity', 'critical')->sum('count'),
                'warning' => $items->where('severity', 'warning')->sum('count'),
                'info' => $items->where('severity', 'info')->sum('count'),
            ])->values()->toArray(),
        ];
    }

    // ─── 元数据 ───

    public function getMetricTypes(): array
    {
        return [
            'license_expiry' => __('app.alerting.metric_license_expiry'),
            'certificate_expiry' => __('app.alerting.metric_cert_expiry'),
            'quota_exceeded' => __('app.alerting.metric_quota_exceeded'),
            'failed_payment' => __('app.alerting.metric_failed_payment'),
            'audit_anomaly' => __('app.alerting.metric_audit_anomaly'),
            'system_health' => __('app.alerting.metric_system_health'),
            'activation_burst' => __('app.alerting.metric_activation_burst'),
            'heartbeat_missed' => __('app.alerting.metric_heartbeat_missed'),
            'apm_slow' => __('app.alerting.metric_apm_slow'),
            'sdk_deprecated' => __('app.alerting.metric_sdk_deprecated'),
            'custom' => __('app.alerting.metric_custom'),
        ];
    }

    public function getSeverities(): array
    {
        return ['info' => __('app.alerting.severity_info'), 'warning' => __('app.alerting.severity_warning'), 'critical' => __('app.alerting.severity_critical')];
    }
}
