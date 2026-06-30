<?php

namespace App\Services;

use App\Models\QueueDeadLetter;
use App\Models\QueueMonitorLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 队列死信监控面板服务 (M2-82)
 */
class QueueMonitorService
{
    /**
     * 获取仪表盘数据
     */
    public function getDashboard(): array
    {
        $queues = array_keys(config('queue-monitor.queues', ['default' => []]));
        $stats = [];

        foreach ($queues as $queue) {
            $stats[$queue] = [
                'total' => QueueMonitorLog::where('queue', $queue)->count(),
                'failed' => QueueMonitorLog::where('queue', $queue)->where('status', 'failed')->count(),
                'completed' => QueueMonitorLog::where('queue', $queue)->where('status', 'completed')->count(),
                'running' => QueueMonitorLog::where('queue', $queue)->where('status', 'running')->count(),
                'dead_letters' => QueueDeadLetter::where('queue', $queue)->where('status', 'dead')->count(),
                'avg_duration_ms' => QueueMonitorLog::where('queue', $queue)->where('status', 'completed')
                    ->avg('duration_ms'),
            ];
        }

        $totalDeadLetters = QueueDeadLetter::where('status', 'dead')->count();
        $recentFailed = QueueMonitorLog::where('status', 'failed')
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        $totalRetried = QueueDeadLetter::where('status', 'retried')->count();

        return [
            'by_queue' => $stats,
            'total_dead_letters' => $totalDeadLetters,
            'recent_24h_failed' => $recentFailed,
            'total_retried' => $totalRetried,
        ];
    }

    /**
     * 获取失败任务列表
     */
    public function getFailedJobs(array $filters = []): array
    {
        $query = QueueMonitorLog::where('status', 'failed')->orderByDesc('id');

        if (!empty($filters['queue'])) {
            $query->where('queue', $filters['queue']);
        }
        if (!empty($filters['search'])) {
            $query->where('job_class', 'like', "%{$filters['search']}%");
        }

        $perPage = $filters['per_page'] ?? 20;
        return $query->paginate($perPage)->withQueryString()->toArray();
    }

    /**
     * 获取死信队列列表
     */
    public function getDeadLetters(array $filters = []): array
    {
        $query = QueueDeadLetter::orderByDesc('id');

        if (!empty($filters['queue'])) {
            $query->where('queue', $filters['queue']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $perPage = $filters['per_page'] ?? 20;
        return $query->paginate($perPage)->withQueryString()->toArray();
    }

    /**
     * 重试死信任务
     */
    public function retryDeadLetter(int $id): QueueDeadLetter
    {
        $letter = QueueDeadLetter::findOrFail($id);
        $letter->update([
            'status' => 'retried',
            'retried_at' => now(),
        ]);

        Log::info('死信任务已重试', [
            'dead_letter_id' => $id,
            'job_class' => $letter->job_class,
            'queue' => $letter->queue,
        ]);

        return $letter->fresh();
    }

    /**
     * 批量重试死信
     */
    public function batchRetryDeadLetters(array $ids): array
    {
        $results = ['success' => 0, 'failed' => 0];
        DB::transaction(function () use ($ids, &$results) {
            foreach ($ids as $id) {
                try {
                    $this->retryDeadLetter($id);
                    $results['success']++;
                } catch (\Throwable $e) {
                    $results['failed']++;
                }
            }
        });
        return $results;
    }

    /**
     * 忽略死信
     */
    public function ignoreDeadLetter(int $id): QueueDeadLetter
    {
        $letter = QueueDeadLetter::findOrFail($id);
        $letter->update(['status' => 'ignored']);
        return $letter->fresh();
    }

    /**
     * 获取趋势数据
     */
    public function getTrend(string $queue = null, int $hours = 24): array
    {
        $since = now()->subHours($hours);
        $query = QueueMonitorLog::where('created_at', '>=', $since);

        if ($queue) {
            $query->where('queue', $queue);
        }

        $records = $query->selectRaw("DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as hour, status, COUNT(*) as count")
            ->groupBy('hour', 'status')
            ->orderBy('hour')
            ->get();

        return $records->toArray();
    }

    /**
     * 清理旧记录
     */
    public function cleanup(): int
    {
        $days = config('queue-monitor.failed_jobs.retention_days', 30);
        $cutoff = now()->subDays($days);

        $deleted = QueueMonitorLog::where('created_at', '<', $cutoff)->delete();
        QueueDeadLetter::where('created_at', '<', $cutoff)->delete();

        return $deleted;
    }
}
