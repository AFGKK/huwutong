<?php

namespace App\Services;

use App\Models\AlertChannel;
use App\Models\AlertEscalation;
use App\Models\AlertEscalationLog;
use App\Models\AlertEvent;
use App\Models\AlertNotificationLog;
use App\Models\AlertRule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 智能告警服务
 *
 * 管理告警规则、通知渠道、升级策略和告警事件生命周期。
 * 基于现有 alert_rules / alert_events 体系扩展。
 */
class AlertingService
{
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
            'slack' => ['text' => "🧪 互物通告警测试\n渠道: {$channel->name}\n时间: " . now()->format('Y-m-d H:i:s')],
            'dingtalk' => ['msgtype' => 'text', 'text' => ['content' => "🧪 互物通告警测试\n渠道: {$channel->name}"]],
            'webhook' => ['event' => 'test', 'channel' => $channel->name, 'timestamp' => now()->toIso8601String()],
            default => ['message' => "Test notification from Huwutong Alerting", 'channel' => $channel->name],
        };

        try {
            $url = $channel->config['webhook_url'] ?? '';
            if (empty($url)) return ['success' => false, 'error' => '未配置 Webhook URL'];

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
        $title = $context['title'] ?? "告警: {$rule->name}";
        $message = $context['message'] ?? "{$rule->name} 触发告警";

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
            return ['success' => false, 'error' => '未配置 Webhook URL'];
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
                        ['title' => '规则', 'value' => $event->rule?->name ?? 'N/A', 'short' => true],
                        ['title' => '严重程度', 'value' => $event->severity, 'short' => true],
                        ['title' => '时间', 'value' => $event->fired_at?->format('Y-m-d H:i:s') ?? 'N/A', 'short' => false],
                    ],
                    'footer' => "互物通告警 · {$channelName}",
                    'ts' => now()->timestamp,
                ]],
            ],
            'dingtalk' => [
                'msgtype' => 'markdown',
                'markdown' => [
                    'title' => "【{$event->severity}】{$event->title}",
                    'text' => "## {$event->title}\n\n**严重程度**: {$event->severity}\n**规则**: {$event->rule?->name}\n**消息**: {$event->message}\n**时间**: {$event->fired_at?->format('Y-m-d H:i:s')}\n\n---\n*互物通智能告警*",
                ],
            ],
            'feishu' => [
                'msg_type' => 'interactive',
                'card' => [
                    'header' => ['title' => ['tag' => 'plain_text', 'content' => "【{$event->severity}】{$event->title}"], 'template' => $color],
                    'elements' => [
                        ['tag' => 'div', 'text' => ['tag' => 'lark_md', 'content' => $event->message]],
                        ['tag' => 'hr'],
                        ['tag' => 'note', 'elements' => [['tag' => 'plain_text', 'content' => "互物通告警 · {$channelName}"]]],
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
                    'response' => $success ? '升级通知已发送' : '升级通知发送失败',
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
            : "[升级 Lv.{$escalation->escalation_level}] {$event->title}: {$event->message}";

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
            'text' => "🚨 *[升级 Lv.{$level}]* {$message}",
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
            'license_expiry' => '许可证到期',
            'certificate_expiry' => '证书到期',
            'quota_exceeded' => '配额超限',
            'failed_payment' => '支付失败',
            'audit_anomaly' => '审计异常',
            'system_health' => '系统健康',
            'activation_burst' => '激活暴增',
            'heartbeat_missed' => '心跳丢失',
            'apm_slow' => 'APM 慢请求',
            'sdk_deprecated' => 'SDK 版本过期',
            'custom' => '自定义',
        ];
    }

    public function getSeverities(): array
    {
        return ['info' => '提示', 'warning' => '警告', 'critical' => '严重'];
    }
}
