<?php

namespace App\Services;

use App\Models\QuotaAlert;
use App\Models\QuotaAlertLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * License 用量配额预警通知服务 (M2-124)
 */
class QuotaAlertService
{
    /**
     * 创建或更新配额预警
     */
    public function upsert(Model $alertable, string $quotaType, int $limit, int $usage): QuotaAlert
    {
        $percent = $limit > 0 ? round($usage / $limit * 100) : 0;
        $level = $this->determineLevel($percent);

        $alert = QuotaAlert::updateOrCreate(
            [
                'alertable_type' => get_class($alertable),
                'alertable_id' => $alertable->id,
                'quota_type' => $quotaType,
            ],
            [
                'quota_limit' => $limit,
                'current_usage' => $usage,
                'usage_percent' => $percent,
                'level' => $level,
                'last_checked_at' => now(),
            ]
        );

        // 检查是否需要发送通知
        if ($level !== 'normal' && $alert->notifications_enabled) {
            $this->checkAndNotify($alert);
        }

        return $alert->fresh();
    }

    /**
     * 判断预警级别
     */
    protected function determineLevel(int $percent): string
    {
        $thresholds = config('quota-alert.thresholds', []);
        $exceeded = $thresholds['exceeded'] ?? 100;
        $critical = $thresholds['critical'] ?? 90;
        $warning = $thresholds['warning'] ?? 80;

        if ($percent >= $exceeded) return 'exceeded';
        if ($percent >= $critical) return 'critical';
        if ($percent >= $warning) return 'warning';
        return 'normal';
    }

    /**
     * 检查并发送通知（抑制重复）
     */
    protected function checkAndNotify(QuotaAlert $alert): void
    {
        $suppressHours = config('quota-alert.suppress_hours', 24);

        // 如果上次通知时间在抑制期内，跳过
        if ($alert->last_notified_at && $alert->last_notified_at->diffInHours(now()) < $suppressHours) {
            return;
        }

        $this->sendNotifications($alert);
    }

    /**
     * 发送多渠道通知
     */
    protected function sendNotifications(QuotaAlert $alert): void
    {
        $message = $this->buildMessage($alert);
        $channels = array_keys(config('quota-alert.channels', []));

        foreach ($channels as $channel) {
            try {
                $this->sendToChannel($alert, $channel, $message);
            } catch (\Exception $e) {
                Log::error("Quota alert {$channel} failed: {$e->getMessage()}");
            }
        }

        $alert->update(['last_notified_at' => now()]);
    }

    /**
     * 构建通知消息
     */
    protected function buildMessage(QuotaAlert $alert): string
    {
        $types = config('quota-alert.types', []);
        $typeName = $types[$alert->quota_type] ?? $alert->quota_type;
        $levelLabels = ['warning' => '警告', 'critical' => '严重', 'exceeded' => '已超限'];

        $upgradeText = '';
        if (config('quota-alert.upgrade.enabled')) {
            $upgradeText = "\n\n" . config('quota-alert.upgrade.text', '立即扩容')
                . ': ' . config('quota-alert.upgrade.url', '/portal/plans');
        }

        return "【配额{$levelLabels[$alert->level]}】{$typeName} 使用量已达 {$alert->usage_percent}%（{$alert->current_usage}/{$alert->quota_limit}）{$upgradeText}";
    }

    /**
     * 发送到指定渠道
     */
    protected function sendToChannel(QuotaAlert $alert, string $channel, string $message): void
    {
        $levelLabels = ['warning' => '警告', 'critical' => '严重', 'exceeded' => '已超限'];

        if ($channel === 'in_app') {
            // 站内信
            try {
                $notificationService = app(NotificationService::class);
                $alertable = $alert->alertable;

                // 如果是客户的配额预警，通知客户关联的用户
                if ($alertable && method_exists($alertable, 'users')) {
                    foreach ($alertable->users as $user) {
                        $notificationService->send($user->id, [
                            'title' => "配额{$levelLabels[$alert->level]}: {$alert->quota_type}",
                            'content' => $message,
                            'type' => 'quota_alert',
                            'action_url' => config('quota-alert.upgrade.url', '/portal/plans'),
                        ]);
                    }
                }
            } catch (\Exception $e) {
                Log::warning("Quota in-app notification failed: {$e->getMessage()}");
            }
        } elseif ($channel === 'email') {
            // 邮件（由队列Job处理）
            // Mail::to($recipient)->send(...)
        } elseif ($channel === 'im') {
            // IM 通知（Slack/飞书/钉钉）
        }

        // 记录日志
        QuotaAlertLog::create([
            'quota_alert_id' => $alert->id,
            'quota_type' => $alert->quota_type,
            'level' => $alert->level,
            'usage_percent' => $alert->usage_percent,
            'current_usage' => $alert->current_usage,
            'quota_limit' => $alert->quota_limit,
            'channel' => $channel,
            'status' => 'sent',
            'message' => $message,
        ]);
    }

    /**
     * 批量检查所有活跃配额
     */
    public function checkAll(): array
    {
        $alerts = QuotaAlert::where('notifications_enabled', true)->get();
        $results = [];

        foreach ($alerts as $alert) {
            try {
                $alertable = $alert->alertable;
                if (!$alertable) continue;

                // 获取当前用量（由各业务模块提供）
                $usage = $this->getCurrentUsage($alertable, $alert->quota_type);

                $updated = $this->upsert($alertable, $alert->quota_type, $alert->quota_limit, $usage);
                $results[] = [
                    'id' => $updated->id,
                    'quota_type' => $updated->quota_type,
                    'level' => $updated->level,
                    'usage_percent' => $updated->usage_percent,
                ];
            } catch (\Exception $e) {
                Log::error("Quota check failed for alert #{$alert->id}: {$e->getMessage()}");
            }
        }

        return $results;
    }

    /**
     * 获取当前用量（后续由各模块注入实际数据）
     */
    protected function getCurrentUsage(Model $alertable, string $quotaType): int
    {
        // 根据不同类型从对应服务获取实际用量
        // 例如 device_count -> DeviceService::count(), api_calls -> UsageMeterService::getUsage()
        return $alertable->current_usage ?? 0;
    }

    /**
     * 获取仪表盘数据
     */
    public function getDashboard(string $startDate, string $endDate): array
    {
        $alerts = QuotaAlert::whereBetween('created_at', [$startDate, $endDate])->get();

        $total = $alerts->count();
        $warning = $alerts->where('level', 'warning')->count();
        $critical = $alerts->where('level', 'critical')->count();
        $exceeded = $alerts->where('level', 'exceeded')->count();
        $normal = $alerts->where('level', 'normal')->count();

        // 按类型统计
        $byType = $alerts->groupBy('quota_type')->map(function ($group) {
            $first = $group->first();
            return [
                'type' => $first->quota_type,
                'type_name' => config("quota-alert.types.{$first->quota_type}", $first->quota_type),
                'count' => $group->count(),
                'active' => $group->whereIn('level', ['warning', 'critical', 'exceeded'])->count(),
            ];
        })->values();

        // 最近预警日志
        $recentLogs = QuotaAlertLog::with('quotaAlert')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return [
            'stats' => [
                'total' => $total,
                'normal' => $normal,
                'warning' => $warning,
                'critical' => $critical,
                'exceeded' => $exceeded,
                'active_total' => $warning + $critical + $exceeded,
            ],
            'by_type' => $byType,
            'recent_logs' => $recentLogs,
            'thresholds' => config('quota-alert.thresholds'),
            'types' => config('quota-alert.types'),
            'channels' => config('quota-alert.channels'),
        ];
    }

    /**
     * 获取预警列表
     */
    public function getList(array $filters = []): array
    {
        $query = QuotaAlert::with('alertable');

        if (!empty($filters['level'])) {
            $query->where('level', $filters['level']);
        }
        if (!empty($filters['quota_type'])) {
            $query->where('quota_type', $filters['quota_type']);
        }
        if (!empty($filters['alertable_type'])) {
            $query->where('alertable_type', $filters['alertable_type']);
        }

        $perPage = (int) ($filters['per_page'] ?? 20);
        $page = (int) ($filters['page'] ?? 1);

        $total = $query->count();
        $items = $query->orderByDesc('usage_percent')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * 获取预警日志
     */
    public function getLogs(array $filters = []): array
    {
        $query = QuotaAlertLog::with('quotaAlert');

        if (!empty($filters['level'])) {
            $query->where('level', $filters['level']);
        }
        if (!empty($filters['quota_type'])) {
            $query->where('quota_type', $filters['quota_type']);
        }
        if (!empty($filters['quota_alert_id'])) {
            $query->where('quota_alert_id', (int) $filters['quota_alert_id']);
        }
        if (!empty($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        $perPage = (int) ($filters['per_page'] ?? 20);
        $page = (int) ($filters['page'] ?? 1);

        $total = $query->count();
        $items = $query->orderByDesc('created_at')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * 更新配额上限
     */
    public function updateLimit(int $id, int $newLimit): QuotaAlert
    {
        $alert = QuotaAlert::findOrFail($id);
        $alert->update(['quota_limit' => $newLimit]);
        return $this->upsert($alert->alertable, $alert->quota_type, $newLimit, $alert->current_usage);
    }

    /**
     * 切换通知启用
     */
    public function toggleNotifications(int $id): QuotaAlert
    {
        $alert = QuotaAlert::findOrFail($id);
        $alert->update(['notifications_enabled' => !$alert->notifications_enabled]);
        return $alert->fresh();
    }

    /**
     * 获取配置
     */
    public function getConfig(): array
    {
        return [
            'types' => config('quota-alert.types'),
            'thresholds' => config('quota-alert.thresholds'),
            'channels' => config('quota-alert.channels'),
            'upgrade' => config('quota-alert.upgrade'),
            'suppress_hours' => config('quota-alert.suppress_hours'),
        ];
    }
}
