<?php

namespace App\Services;

use App\Models\AlertEvent;
use App\Models\OnCallEntry;
use App\Models\OnCallLog;
use App\Models\OnCallMember;
use App\Models\OnCallOverride;
use App\Models\OnCallSchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OnCallService
{
    protected NotificationService $notifications;

    public function __construct(NotificationService $notifications)
    {
        $this->notifications = $notifications;
    }

    // ══════════════════════════════════════════════
    //  排班管理
    // ══════════════════════════════════════════════

    /**
     * 获取当前值班人（含 override 覆盖）
     */
    public function getCurrentOnCall(?int $scheduleId = null): Collection
    {
        $now = now();
        $query = OnCallEntry::where('status', 'scheduled')
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now);

        if ($scheduleId) {
            $query->where('schedule_id', $scheduleId);
        }

        $entries = $query->with('user:id,name,email,phone')->get();

        // 检查是否有 override
        return $entries->map(function ($entry) {
            $override = OnCallOverride::where('schedule_id', $entry->schedule_id)
                ->where('status', 'approved')
                ->where('starts_at', '<=', now())
                ->where('ends_at', '>=', now())
                ->first();

            if ($override) {
                $entry->replacement_user = $override->replacementUser;
                $entry->overridden = true;
            }

            return $entry;
        });
    }

    /**
     * 获取指定时间的值班人
     */
    public function getOnCallAt(\DateTimeInterface $time, ?int $scheduleId = null): Collection
    {
        $query = OnCallEntry::where('status', 'scheduled')
            ->where('starts_at', '<=', $time)
            ->where('ends_at', '>=', $time);

        if ($scheduleId) {
            $query->where('schedule_id', $scheduleId);
        }

        return $query->with('user:id,name,email,phone')->get();
    }

    /**
     * 自动生成排班
     */
    public function autoGenerate(OnCallSchedule $schedule, ?int $days = null): int
    {
        $days = $days ?? config('on-call.auto_schedule_days', 90);
        $members = $schedule->members()->where('is_active', true)->orderBy('sort_order')->get();

        if ($members->isEmpty()) {
            return 0;
        }

        $rotationDays = $this->getRotationDays($schedule->rotation_type, $schedule->rotation_length ?? 1);
        $startDate = now()->startOfDay();
        $endDate = $startDate->copy()->addDays($days);
        $generated = 0;

        // 清除已有未来安排
        OnCallEntry::where('schedule_id', $schedule->id)
            ->where('starts_at', '>=', $startDate)
            ->where('source', 'rotation')
            ->delete();

        $currentDate = $startDate->copy();
        $memberIndex = 0;
        $memberCount = $members->count();

        DB::transaction(function () use ($schedule, $members, $rotationDays, $startDate, $endDate, &$currentDate, &$memberIndex, $memberCount, &$generated) {
            while ($currentDate->lt($endDate)) {
                $periodEnd = $currentDate->copy()->addDays($rotationDays);

                // 一线
                $primary = $members[$memberIndex % $memberCount];
                $this->createEntry($schedule, $primary, $currentDate, $periodEnd, 'primary');
                $generated++;

                // 二线（下一个成员）
                if ($memberCount > 1) {
                    $backup = $members[($memberIndex + 1) % $memberCount];
                    $this->createEntry($schedule, $backup, $currentDate, $periodEnd, 'backup');
                    $generated++;
                }

                // 三线（下下个成员）
                if ($memberCount > 2) {
                    $escalation = $members[($memberIndex + 2) % $memberCount];
                    $this->createEntry($schedule, $escalation, $currentDate, $periodEnd, 'escalation');
                    $generated++;
                }

                $currentDate = $periodEnd;
                $memberIndex++;
            }
        });

        return $generated;
    }

    protected function createEntry(OnCallSchedule $schedule, OnCallMember $member, Carbon $start, Carbon $end, string $role): void
    {
        OnCallEntry::create([
            'schedule_id' => $schedule->id,
            'member_id' => $member->id,
            'user_id' => $member->user_id,
            'starts_at' => $start,
            'ends_at' => $end,
            'role' => $role,
            'status' => 'scheduled',
            'source' => 'rotation',
        ]);
    }

    protected function getRotationDays(string $type, int $length): int
    {
        $map = ['daily' => 1, 'weekly' => 7, 'biweekly' => 14, 'monthly' => 30];
        return ($map[$type] ?? 7) * max(1, $length);
    }

    // ══════════════════════════════════════════════
    //  告警路由
  // ══════════════════════════════════════════════

    /**
     * 将告警路由到当前值班人
     */
    public function routeAlert(AlertEvent $event): array
    {
        $result = ['routed' => false, 'notified' => [], 'escalation_level' => 0];

        if (!config('on-call.alert_routing.enabled', true)) {
            return $result;
        }

        $schedules = OnCallSchedule::active()->get();

        foreach ($schedules as $schedule) {
            $currentEntries = $this->getCurrentOnCall($schedule->id);

            foreach ($currentEntries as $entry) {
                $targetUser = $entry->overridden ? $entry->replacement_user : $entry->user;
                if (!$targetUser) continue;

                $this->notifyUser($targetUser, $event, $entry, $schedule);
                $result['notified'][] = $targetUser->id;
                $result['routed'] = true;
            }
        }

        // 回退：如果没有找到任何值班人，通知默认用户
        if (empty($result['notified'])) {
            $fallbackId = config('on-call.alert_routing.fallback_user_id', 1);
            $fallbackUser = User::find($fallbackId);
            if ($fallbackUser) {
                $this->notifications->send(
                    $fallbackUser->id,
                    'alert_on_call',
                    '🚨 告警（无值班人）: ' . $event->title,
                    $event->message,
                    ['alert_event_id' => $event->id, 'on_call_fallback' => true]
                );
                $result['notified'][] = $fallbackUser->id;
            }
        }

        return $result;
    }

    /**
     * 通知值班人
     */
    protected function notifyUser(User $user, AlertEvent $event, OnCallEntry $entry, OnCallSchedule $schedule): void
    {
        $channels = $schedule->channels ?? ['database', 'email'];

        $payload = [
            'alert_event_id' => $event->id,
            'on_call_entry_id' => $entry->id,
            'schedule_name' => $schedule->name,
            'role' => $entry->role,
        ];

        // 站内通知
        if (in_array('database', $channels)) {
            $this->notifications->send(
                $user->id,
                'alert_on_call',
                "🔔 [{$schedule->name}] {$event->title}",
                $event->message,
                $payload
            );
        }

        // 记录日志
        OnCallLog::create([
            'on_call_entry_id' => $entry->id,
            'alert_event_id' => $event->id,
            'user_id' => $user->id,
            'action' => 'notified',
            'channel' => 'database',
            'status' => 'success',
            'details' => ['schedule' => $schedule->name, 'role' => $entry->role],
        ]);
    }

    // ══════════════════════════════════════════════
    //  统计
  // ══════════════════════════════════════════════

    public function getDashboard(): array
    {
        $now = now();
        $schedules = OnCallSchedule::active()->with('members.user')->get();

        $currentOnCall = $this->getCurrentOnCall()->load('user', 'schedule');

        return [
            'total_schedules' => $schedules->count(),
            'total_members' => OnCallMember::where('is_active', true)->count(),
            'active_entries' => OnCallEntry::active()->count(),
            'current_on_call' => $currentOnCall,
            'upcoming_shifts' => OnCallEntry::upcoming()->with('user', 'schedule')
                ->orderBy('starts_at')->take(10)->get(),
            'recent_logs' => OnCallLog::with('user')->orderBy('created_at', 'desc')->take(10)->get(),
            'schedules' => $schedules->map(fn($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'type' => $s->rotation_type,
                'members' => $s->members->where('is_active', true)->values()->map(fn($m) => [
                    'id' => $m->id,
                    'user_id' => $m->user_id,
                    'name' => $m->user?->name ?? '未知',
                    'sort_order' => $m->sort_order,
                ]),
            ]),
        ];
    }
}
