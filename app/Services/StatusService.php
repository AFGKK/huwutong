<?php

namespace App\Services;

use App\Models\IncidentUpdate;
use App\Models\StatusComponent;
use App\Models\StatusIncident;
use App\Models\StatusSubscriber;
use App\Models\StatusUptimeRecord;
use App\Notifications\StatusIncidentNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * 公开状态页服务
 *
 * 管理：组件健康检查、事件时间线、订阅通知、uptime 统计。
 * 设计为独立于主站认证，可通过 status.huwutong.com 独立部署。
 */
class StatusService
{
    /**
     * 获取系统状态概览
     */
    public function getOverview(): array
    {
        $components = StatusComponent::public()->byGroup()->get();
        $incidents = StatusIncident::public()->recent(7)->with('updates')
            ->orderBy('occurred_at', 'desc')
            ->limit(10)
            ->get();

        $overallStatus = $this->calculateOverallStatus($components);

        return [
            'overall_status' => $overallStatus,
            'components' => $components->map(fn($c) => $this->formatComponent($c)),
            'incidents' => $incidents->map(fn($i) => $this->formatIncident($i)),
            'uptime' => $this->getUptimeDisplay(),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * 获取状态历史
     */
    public function getHistory(int $days = 30): array
    {
        $incidents = StatusIncident::public()
            ->where('occurred_at', '>=', now()->subDays($days))
            ->orWhere('created_at', '>=', now()->subDays($days))
            ->with('updates')
            ->orderBy('occurred_at', 'desc')
            ->get();

        $uptimePercent = $this->calculateUptimePercent($days);

        return [
            'uptime_percent' => $uptimePercent,
            'total_incidents' => $incidents->count(),
            'incidents' => $incidents->map(fn($i) => $this->formatIncident($i)),
            'period_days' => $days,
        ];
    }

    /**
     * 计算指定天数内的 uptime 百分比
     */
    public function calculateUptimePercent(int $days = 30): float
    {
        $since = now()->subDays($days);

        // 从 uptime 记录计算
        $total = StatusUptimeRecord::where('checked_at', '>=', $since)->count();
        $up = StatusUptimeRecord::where('checked_at', '>=', $since)->where('is_up', true)->count();

        if ($total === 0) {
            return 100.0; // 无数据时默认 100%
        }

        return round(($up / $total) * 100, 2);
    }

    /**
     * 创建事件
     */
    public function createIncident(array $data): StatusIncident
    {
        $incident = DB::transaction(function () use ($data) {
            $incident = StatusIncident::create([
                'title' => $data['title'],
                'description' => $data['description'] ?? '',
                'severity' => $data['severity'] ?? 'minor',
                'status' => 'investigating',
                'is_public' => $data['is_public'] ?? true,
                'occurred_at' => $data['occurred_at'] ?? now(),
            ]);

            // 关联组件
            if (!empty($data['component_ids'])) {
                $incident->components()->sync($data['component_ids']);
                // 更新组件状态
                StatusComponent::whereIn('id', $data['component_ids'])
                    ->update(['status' => $this->severityToComponentStatus($data['severity'] ?? 'minor')]);
            }

            // 创建第一条更新
            $incident->updates()->create([
                'status' => 'investigating',
                'message' => $data['description'] ?? '正在调查中...',
            ]);

            // 通知订阅者
            $this->notifySubscribers($incident, 'investigating');

            return $incident;
        });

        Log::info('StatusPage: incident created', [
            'incident_id' => $incident->id,
            'title' => $incident->title,
            'severity' => $incident->severity,
        ]);

        return $incident;
    }

    /**
     * 更新事件状态
     */
    public function updateIncidentStatus(StatusIncident $incident, string $status, string $message, ?array $componentStatuses = null): IncidentUpdate
    {
        $update = DB::transaction(function () use ($incident, $status, $message, $componentStatuses) {
            $incident->update(['status' => $status]);

            if ($status === 'resolved') {
                $incident->update(['resolved_at' => now()]);
                // 恢复所有关联组件状态
                $incident->components()->update(['status' => 'operational']);
            }

            // 更新单个组件状态
            if ($componentStatuses) {
                foreach ($componentStatuses as $componentId => $componentStatus) {
                    StatusComponent::where('id', $componentId)->update(['status' => $componentStatus]);
                }
            }

            $update = $incident->updates()->create([
                'status' => $status,
                'message' => $message,
            ]);

            // 通知订阅者
            $this->notifySubscribers($incident, $status);

            return $update;
        });

        Log::info('StatusPage: incident updated', [
            'incident_id' => $incident->id,
            'status' => $status,
        ]);

        return $update;
    }

    /**
     * 执行系统健康检查
     */
    public function runHealthChecks(): array
    {
        $results = [];
        $components = StatusComponent::public()->get();

        foreach ($components as $component) {
            $checkResult = $this->checkComponent($component->slug);
            $results[$component->slug] = $checkResult;

            // 记录 uptime
            StatusUptimeRecord::create([
                'component_slug' => $component->slug,
                'is_up' => $checkResult['is_up'],
                'latency_ms' => $checkResult['latency_ms'],
                'checked_at' => now(),
            ]);

            // 更新组件状态（如果检查失败）
            if (!$checkResult['is_up'] && $component->status === 'operational') {
                $component->update(['status' => 'major_outage']);
            } elseif ($checkResult['is_up'] && $component->status !== 'operational') {
                // 只有在没有活跃事件时才自动恢复
                $hasActiveIncident = $component->incidents()
                    ->whereIn('status', ['investigating', 'identified', 'monitoring'])
                    ->exists();
                if (!$hasActiveIncident) {
                    $component->update(['status' => 'operational']);
                }
            }
        }

        return $results;
    }

    /**
     * 检查单个组件
     */
    public function checkComponent(string $slug): array
    {
        $startTime = microtime(true);

        try {
            $result = match ($slug) {
                'api' => $this->checkApi(),
                'database' => $this->checkDatabase(),
                'redis' => $this->checkRedis(),
                'queue' => $this->checkQueue(),
                default => ['is_up' => true, 'latency_ms' => 0],
            };
        } catch (\Throwable $e) {
            Log::error("StatusPage: check failed for {$slug}", ['error' => $e->getMessage()]);
            $result = ['is_up' => false, 'latency_ms' => 0];
        }

        $elapsed = (int) ((microtime(true) - $startTime) * 1000);

        return array_merge($result, [
            'latency_ms' => $result['latency_ms'] ?? $elapsed,
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * 订阅状态通知
     */
    public function subscribe(string $email): StatusSubscriber
    {
        $existing = StatusSubscriber::where('email', $email)->first();

        if ($existing) {
            if (!$existing->is_active) {
                $existing->update([
                    'is_active' => true,
                    'subscribed_at' => now(),
                    'unsubscribed_at' => null,
                ]);
            }
            return $existing;
        }

        return StatusSubscriber::create([
            'email' => $email,
            'subscribed_at' => now(),
        ]);
    }

    /**
     * 退订
     */
    public function unsubscribe(string $token): bool
    {
        $subscriber = StatusSubscriber::where('token', $token)->first();
        if (!$subscriber) {
            return false;
        }

        $subscriber->unsubscribe();
        return true;
    }

    /**
     * 获取所有订阅者
     */
    public function getSubscribers(): array
    {
        return StatusSubscriber::active()->get()->toArray();
    }

    /**
     * 获取统计
     */
    public function getStats(): array
    {
        return [
            'total_components' => StatusComponent::count(),
            'operational_components' => StatusComponent::where('status', 'operational')->count(),
            'degraded_components' => StatusComponent::where('status', '!=', 'operational')->count(),
            'open_incidents' => StatusIncident::whereNotIn('status', ['resolved', 'postmortem'])->count(),
            'total_incidents' => StatusIncident::count(),
            'active_subscribers' => StatusSubscriber::active()->count(),
            'uptime_30d' => $this->calculateUptimePercent(30),
            'uptime_90d' => $this->calculateUptimePercent(90),
        ];
    }

    /**
     * 通知所有订阅者
     */
    protected function notifySubscribers(StatusIncident $incident, string $status): void
    {
        $subscribers = StatusSubscriber::active()->get();

        if ($subscribers->isEmpty()) {
            return;
        }

        foreach ($subscribers as $subscriber) {
            try {
                Notification::route('mail', $subscriber->email)
                    ->notify(new StatusIncidentNotification($incident, $status, $subscriber->token));
            } catch (\Throwable $e) {
                Log::error("StatusPage: failed to notify subscriber", [
                    'email' => $subscriber->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    // ============== 内部检查方法 ==============

    protected function checkApi(): array
    {
        $start = microtime(true);
        DB::select('SELECT 1');
        $latency = (int) ((microtime(true) - $start) * 1000);

        return [
            'is_up' => true,
            'latency_ms' => $latency,
            'description' => 'API 服务',
        ];
    }

    protected function checkDatabase(): array
    {
        $start = microtime(true);
        DB::select('SELECT 1');
        $latency = (int) ((microtime(true) - $start) * 1000);

        return [
            'is_up' => true,
            'latency_ms' => $latency,
            'description' => '数据库',
        ];
    }

    protected function checkRedis(): array
    {
        $start = microtime(true);
        $result = Cache::store('redis')->set('sp:ping', 'pong', 5);
        $pong = Cache::store('redis')->get('sp:ping') === 'pong';
        $latency = (int) ((microtime(true) - $start) * 1000);

        return [
            'is_up' => $result && $pong,
            'latency_ms' => $latency,
            'description' => 'Redis 缓存',
        ];
    }

    protected function checkQueue(): array
    {
        $start = microtime(true);
        $latency = (int) ((microtime(true) - $start) * 1000);

        // 通过检查队列连接来判断
        try {
            $queue = app()->make('queue');
            $queue->connection();
            return ['is_up' => true, 'latency_ms' => $latency, 'description' => '消息队列'];
        } catch (\Throwable) {
            return ['is_up' => false, 'latency_ms' => $latency, 'description' => '消息队列'];
        }
    }

    // ============== 辅助方法 ==============

    protected function calculateOverallStatus($components): string
    {
        $statuses = $components->pluck('status')->toArray();
        if (in_array('major_outage', $statuses)) return 'major_outage';
        if (in_array('partial_outage', $statuses)) return 'partial_outage';
        if (in_array('degraded_performance', $statuses)) return 'degraded_performance';
        return 'operational';
    }

    protected function formatComponent(StatusComponent $component): array
    {
        return [
            'id' => $component->id,
            'name' => $component->name,
            'slug' => $component->slug,
            'description' => $component->description,
            'group' => $component->group,
            'status' => $component->status,
            'status_label' => $component->statusLabel(),
            'sort_order' => $component->sort_order,
        ];
    }

    protected function formatIncident(StatusIncident $incident): array
    {
        return [
            'id' => $incident->id,
            'title' => $incident->title,
            'description' => $incident->description,
            'severity' => $incident->severity,
            'severity_label' => $incident->severityLabel(),
            'status' => $incident->status,
            'status_label' => $incident->statusLabel(),
            'components' => $incident->components->map(fn($c) => ['id' => $c->id, 'name' => $c->name]),
            'updates' => $incident->updates->map(fn($u) => [
                'status' => $u->status,
                'message' => $u->message,
                'created_at' => $u->created_at->toIso8601String(),
            ]),
            'occurred_at' => $incident->occurred_at?->toIso8601String(),
            'resolved_at' => $incident->resolved_at?->toIso8601String(),
            'created_at' => $incident->created_at->toIso8601String(),
        ];
    }

    protected function getUptimeDisplay(): string
    {
        $startedAt = Cache::get('app:started_at', now()->timestamp);
        $seconds = time() - $startedAt;
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        $parts = [];
        if ($days > 0) $parts[] = "{$days}d";
        if ($hours > 0) $parts[] = "{$hours}h";
        $parts[] = "{$minutes}m";

        return implode(' ', $parts);
    }

    protected function severityToComponentStatus(string $severity): string
    {
        return match ($severity) {
            'critical' => 'major_outage',
            'major' => 'partial_outage',
            'minor' => 'degraded_performance',
            default => 'degraded_performance',
        };
    }
}
