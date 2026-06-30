<?php

namespace App\Services;

use App\Jobs\GenerateAndDeliverReportJob;
use App\Models\CustomReport;
use App\Models\ReportDeliveryLog;
use App\Models\ReportSchedule;
use Illuminate\Support\Facades\DB;

class ReportSchedulerService
{
    /**
     * 获取调度仪表盘数据
     */
    public function getDashboard(int $userId): array
    {
        $totalSchedules = ReportSchedule::where('user_id', $userId)->count();
        $activeSchedules = ReportSchedule::where('user_id', $userId)->where('is_active', true)->count();
        $dueCount = ReportSchedule::where('user_id', $userId)
            ->where('is_active', true)
            ->where('next_run_at', '<=', now())
            ->count();
        $totalDeliveries = ReportDeliveryLog::whereIn('schedule_id', function ($q) use ($userId) {
            $q->select('id')->from('report_schedules')->where('user_id', $userId);
        })->count();
        $recentDeliveries = ReportDeliveryLog::whereIn('schedule_id', function ($q) use ($userId) {
            $q->select('id')->from('report_schedules')->where('user_id', $userId);
        })->orderByDesc('created_at')->limit(10)->get();

        // Success rate
        $completed = ReportDeliveryLog::whereIn('schedule_id', function ($q) use ($userId) {
            $q->select('id')->from('report_schedules')->where('user_id', $userId);
        })->where('status', 'completed')->count();

        $failed = ReportDeliveryLog::whereIn('schedule_id', function ($q) use ($userId) {
            $q->select('id')->from('report_schedules')->where('user_id', $userId);
        })->where('status', 'failed')->count();

        $successRate = $totalDeliveries > 0
            ? round(($completed / $totalDeliveries) * 100, 1)
            : 100;

        return [
            'stats' => [
                'total_schedules' => $totalSchedules,
                'active_schedules' => $activeSchedules,
                'due_count' => $dueCount,
                'total_deliveries' => $totalDeliveries,
                'success_rate' => $successRate,
            ],
            'recent_deliveries' => $recentDeliveries,
        ];
    }

    /**
     * 获取调度列表
     */
    public function getSchedules(int $userId, array $filters = []): array
    {
        $query = ReportSchedule::with('report:id,name,data_source')
            ->where('user_id', $userId);

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }
        if (!empty($filters['report_id'])) {
            $query->where('report_id', (int) $filters['report_id']);
        }

        $perPage = (int) ($filters['per_page'] ?? 50);
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
     * 创建调度
     */
    public function createSchedule(int $userId, ?int $tenantId, array $data): ReportSchedule
    {
        $data['user_id'] = $userId;
        $data['tenant_id'] = $tenantId;
        $data['next_run_at'] = $this->calculateNextRun($data['cron_expression'] ?? '0 8 * * *');

        $schedule = ReportSchedule::create($data);

        // Sync custom_reports scheduling fields
        if (isset($data['report_id'])) {
            CustomReport::where('id', $data['report_id'])->update([
                'is_scheduled' => true,
                'schedule_cron' => $data['cron_expression'] ?? null,
                'schedule_recipients' => $data['recipients'] ?? null,
                'export_format' => $data['export_format'] ?? 'csv',
            ]);
        }

        return $schedule->fresh()->load('report:id,name,data_source');
    }

    /**
     * 更新调度
     */
    public function updateSchedule(ReportSchedule $schedule, array $data): ReportSchedule
    {
        if (isset($data['cron_expression'])) {
            $data['next_run_at'] = $this->calculateNextRun($data['cron_expression']);
        }

        $schedule->update($data);

        // Sync custom_reports scheduling fields
        if (isset($data['cron_expression']) || isset($data['recipients']) || isset($data['export_format'])) {
            CustomReport::where('id', $schedule->report_id)->update([
                'is_scheduled' => $schedule->is_active,
                'schedule_cron' => $schedule->cron_expression,
                'schedule_recipients' => $schedule->recipients,
                'export_format' => $schedule->export_format,
            ]);
        }

        return $schedule->fresh()->load('report:id,name,data_source');
    }

    /**
     * 删除调度
     */
    public function deleteSchedule(ReportSchedule $schedule): void
    {
        $reportId = $schedule->report_id;
        $schedule->deliveryLogs()->delete();
        $schedule->delete();

        // Check if there are no more schedules for this report
        $remaining = ReportSchedule::where('report_id', $reportId)->count();
        if ($remaining === 0) {
            CustomReport::where('id', $reportId)->update([
                'is_scheduled' => false,
                'schedule_cron' => null,
            ]);
        }
    }

    /**
     * 切换调度启用状态
     */
    public function toggleSchedule(ReportSchedule $schedule): ReportSchedule
    {
        $schedule->update([
            'is_active' => !$schedule->is_active,
            'next_run_at' => !$schedule->is_active
                ? $this->calculateNextRun($schedule->cron_expression)
                : null,
        ]);

        CustomReport::where('id', $schedule->report_id)->update([
            'is_scheduled' => $schedule->is_active,
        ]);

        return $schedule->fresh();
    }

    /**
     * 手动触发调度
     */
    public function triggerNow(ReportSchedule $schedule): ReportDeliveryLog
    {
        GenerateAndDeliverReportJob::dispatchSync($schedule);

        return ReportDeliveryLog::where('schedule_id', $schedule->id)
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * 处理到期的调度
     */
    public function processDueSchedules(): array
    {
        $schedules = ReportSchedule::getDueSchedules();
        $processed = [];

        foreach ($schedules as $schedule) {
            try {
                GenerateAndDeliverReportJob::dispatch($schedule);
                $processed[] = [
                    'id' => $schedule->id,
                    'report_id' => $schedule->report_id,
                    'status' => 'dispatched',
                ];
            } catch (\Exception $e) {
                $processed[] = [
                    'id' => $schedule->id,
                    'report_id' => $schedule->report_id,
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $processed;
    }

    /**
     * 获取投递日志
     */
    public function getDeliveryLogs(int $userId, array $filters = []): array
    {
        $query = ReportDeliveryLog::with(['report:id,name', 'schedule:id,cron_expression'])
            ->whereIn('schedule_id', function ($q) use ($userId) {
                $q->select('id')->from('report_schedules')->where('user_id', $userId);
            });

        if (!empty($filters['schedule_id'])) {
            $query->where('schedule_id', (int) $filters['schedule_id']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
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
     * 获取可调度的报表列表（用户拥有的且未设置调度的）
     */
    public function getSchedulableReports(int $userId): array
    {
        $scheduledIds = ReportSchedule::where('user_id', $userId)
            ->pluck('report_id')
            ->toArray();

        return CustomReport::where('user_id', $userId)
            ->whereNotIn('id', $scheduledIds)
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get(['id', 'name', 'data_source', 'category'])
            ->all();
    }

    /**
     * 计算 cron 表达式的下次执行时间
     */
    public function calculateNextRun(string $cronExpression): \DateTime
    {
        $parts = explode(' ', $cronExpression);
        if (count($parts) !== 5) {
            return now()->addHour();
        }

        [$minute, $hour, $dayOfMonth, $month, $dayOfWeek] = $parts;
        $next = now()->copy()->addMinute()->setSecond(0);

        // Common pattern: "0 8 * * *" -> next 08:00
        if ($minute !== '*' && $hour !== '*' && $dayOfMonth === '*' && $month === '*' && $dayOfWeek === '*') {
            $min = (int) $minute;
            $hr = (int) $hour;
            $next->setMinute($min)->setSecond(0);
            if ($next->hour > $hr || ($next->hour === $hr && $next->minute > $min)) {
                $next->addDay();
            }
            $next->setHour($hr)->setMinute($min)->setSecond(0);
            return $next;
        }

        // Every hour pattern: "0 * * * *"
        if ($minute !== '*' && $hour === '*') {
            $min = (int) $minute;
            $next->setMinute($min)->setSecond(0);
            if ($next->minute > $min) {
                $next->addHour();
            }
            return $next;
        }

        // Default: every hour
        return $next->addHour();
    }
}
