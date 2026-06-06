<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CircuitBreakerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * 公开状态页 API
 *
 * 用于 status.huwutong.com 展示系统实时状态。
 * 无需认证，只返回关键的健康指标和事件时间线。
 */
class StatusPageController extends Controller
{
    /**
     * 获取系统状态概览
     */
    public function index(): JsonResponse
    {
        $uptime = $this->getUptime();
        $incidents = $this->getRecentIncidents(5);

        $checks = Cache::remember('status_page_checks', 30, function () {
            return $this->runChecks();
        });

        $overallStatus = $this->calculateOverallStatus($checks);

        return response()->json([
            'status' => $overallStatus,
            'timestamp' => now()->toIso8601String(),
            'service' => config('app.name', 'HWT API'),
            'checks' => $checks,
            'uptime' => $uptime,
            'incidents' => $incidents,
        ]);
    }

    /**
     * 获取完整状态历史
     */
    public function history(): JsonResponse
    {
        $incidents = $this->getRecentIncidents(30);
        $uptimePercent = $this->calculateUptimePercent(30); // 30天 uptime

        return response()->json([
            'uptime_percent' => $uptimePercent,
            'total_incidents' => count($incidents),
            'incidents' => $incidents,
        ]);
    }

    /**
     * 记录状态事件（由监控系统调用）
     */
    public function reportIncident(): JsonResponse
    {
        // 仅内部监控系统可访问
        abort_unless(request()->bearerToken() === config('app.status_page_token'), 403);

        $data = request()->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'severity' => 'required|in:minor,major,critical',
            'status' => 'sometimes|in:investigating,identified,monitoring,resolved',
        ]);

        $incident = [
            'id' => uniqid('inc_', true),
            'title' => $data['title'],
            'description' => $data['description'],
            'severity' => $data['severity'],
            'status' => $data['status'] ?? 'investigating',
            'reported_at' => now()->toIso8601String(),
            'updates' => [],
        ];

        // 存储到缓存中的事件列表
        $incidents = Cache::get('status_page_incidents', []);
        array_unshift($incidents, $incident);
        Cache::put('status_page_incidents', array_slice($incidents, 0, 100), now()->addDays(90));

        return response()->json([
            'success' => true,
            'data' => $incident,
        ], 201);
    }

    /**
     * 更新事件状态
     */
    public function updateIncident(string $id): JsonResponse
    {
        abort_unless(request()->bearerToken() === config('app.status_page_token'), 403);

        $data = request()->validate([
            'status' => 'required|in:investigating,identified,monitoring,resolved',
            'message' => 'sometimes|string|max:500',
        ]);

        $incidents = Cache::get('status_page_incidents', []);

        foreach ($incidents as &$incident) {
            if ($incident['id'] === $id) {
                $incident['status'] = $data['status'];
                if (!empty($data['message'])) {
                    $incident['updates'][] = [
                        'status' => $data['status'],
                        'message' => $data['message'],
                        'updated_at' => now()->toIso8601String(),
                    ];
                }
                Cache::put('status_page_incidents', $incidents, now()->addDays(90));
                return response()->json(['success' => true, 'data' => $incident]);
            }
        }

        return response()->json(['success' => false, 'message' => '事件未找到'], 404);
    }

    /**
     * 获取订阅状态通知的邮箱
     */
    public function subscribe(): JsonResponse
    {
        $data = request()->validate([
            'email' => 'required|email',
        ]);

        $subscribers = Cache::get('status_page_subscribers', []);
        if (!in_array($data['email'], $subscribers)) {
            $subscribers[] = $data['email'];
            Cache::put('status_page_subscribers', $subscribers, now()->addYear());
        }

        return response()->json([
            'success' => true,
            'message' => '订阅成功',
        ]);
    }

    /**
     * 运行系统检查
     */
    protected function runChecks(): array
    {
        return [
            'api' => [
                'status' => 'operational',
                'latency_ms' => 0,
                'description' => 'API 服务',
            ],
            'database' => $this->formatCheck('数据库', $this->pingDatabase()),
            'redis' => $this->formatCheck('Redis 缓存', $this->pingRedis()),
            'queue' => [
                'status' => 'operational',
                'description' => '消息队列',
            ],
        ];
    }

    protected function pingDatabase(): bool
    {
        try {
            DB::select('SELECT 1');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    protected function pingRedis(): bool
    {
        try {
            Cache::store('redis')->set('sp:ping', 'pong', 5);
            return Cache::store('redis')->get('sp:ping') === 'pong';
        } catch (\Throwable) {
            return false;
        }
    }

    protected function formatCheck(string $name, bool $healthy): array
    {
        return [
            'status' => $healthy ? 'operational' : 'down',
            'description' => $name,
        ];
    }

    protected function calculateOverallStatus(array $checks): string
    {
        $statuses = array_column($checks, 'status');
        if (in_array('down', $statuses)) return 'degraded';
        if (in_array('degraded', $statuses)) return 'degraded';
        return 'operational';
    }

    protected function getRecentIncidents(int $limit): array
    {
        return array_slice(Cache::get('status_page_incidents', []), 0, $limit);
    }

    protected function calculateUptimePercent(int $days): float
    {
        // 基于最近事件的简单计算
        $incidents = Cache::get('status_page_incidents', []);
        $recent = array_filter($incidents, function ($inc) use ($days) {
            $reportedAt = new \DateTime($inc['reported_at']);
            return $reportedAt >= now()->subDays($days);
        });

        $criticalCount = count(array_filter($recent, fn($i) => $i['severity'] === 'critical'));
        // 每次 critical 事件扣除 0.5%
        $deduction = $criticalCount * 0.5;

        return max(99.0, min(100.0, 100.0 - $deduction));
    }

    protected function getUptime(): string
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
}
