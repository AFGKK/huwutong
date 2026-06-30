<?php

namespace App\Services;

use App\Models\Device;
use App\Models\DeviceLifecycleEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 设备生命周期画像服务
 *
 * 管理设备从「首次出现→逐步信任→长期稳定→异常行为→标记可疑→废弃」的完整生命周期。
 */
class DeviceLifecycleService
{
    /**
     * 记录生命周期事件并更新设备状态
     */
    public function recordEvent(
        Device $device,
        string $eventType,
        ?string $newStage = null,
        ?string $reason = null,
        array $metadata = [],
        string $triggeredBy = 'system',
        ?int $triggeredByUser = null,
    ): DeviceLifecycleEvent {
        $oldScore = $device->trust_score;
        $oldStage = $device->lifecycle_stage;

        // 如果提供了新阶段，转换
        $newStage = $newStage ?? $this->determineStageFromScoreFromValues(
            $device->trust_score, $device->is_blacklisted ?? false, $device->license_id !== null
        );

        // 更新设备
        $device->timestamps = false; // Don't bump updated_at
        $device->lifecycle_stage = $newStage;
        $device->total_events = ($device->total_events ?? 0) + 1;

        // 首次出现时间
        if (!$device->first_seen_at) {
            $device->first_seen_at = now();
        }

        // 阶段变更时记录时间
        if ($newStage !== $oldStage) {
            $device->last_stage_change_at = now();
        }

        // 重新计算活跃天数
        $device->days_active = $device->first_seen_at
            ? (int) $device->first_seen_at->diffInDays(now())
            : 0;

        $device->save();

        // 创建生命周期事件记录
        $event = DeviceLifecycleEvent::create([
            'device_id' => $device->id,
            'tenant_id' => $device->tenant_id,
            'event_type' => $eventType,
            'stage' => $newStage,
            'trust_score_before' => $oldScore,
            'trust_score_after' => $device->trust_score,
            'trust_score_change' => $device->trust_score - $oldScore,
            'metadata' => $metadata,
            'reason' => $reason,
            'triggered_by' => $triggeredBy,
            'triggered_by_user' => $triggeredByUser,
        ]);

        return $event;
    }

    /**
     * 调整信任分并自动触发阶段转换
     */
    public function adjustTrustScore(
        Device $device,
        int $delta,
        string $reason,
        array $metadata = [],
        string $triggeredBy = 'system',
        ?int $triggeredByUser = null,
    ): DeviceLifecycleEvent {
        $oldScore = $device->trust_score;
        $newScore = max(0, min(100, $oldScore + $delta));
        $oldStage = $device->lifecycle_stage;

        $newStage = $this->determineStageFromScoreFromValues(
            $newScore, $device->is_blacklisted ?? false, $device->license_id !== null
        );

        $eventType = $delta > 0 ? 'trust_increased' : ($delta < 0 ? 'trust_decreased' : 'trust_unchanged');

        // 如果阶段发生变化，使用阶段变更事件类型
        $displayEventType = $eventType;
        if ($newStage !== $oldStage) {
            $displayEventType = match ($newStage) {
                'new' => '首次出现',
                'onboarding' => '信任建立',
                'stable' => '活跃稳定',
                'suspicious' => '异常行为',
                'retired' => '废弃',
                default => $eventType,
            };
        } elseif ($delta < -10) {
            $displayEventType = '信任下降';
        } elseif ($delta > 10) {
            $displayEventType = '信任提升';
        }

        // Create the event first, then update device
        $event = DeviceLifecycleEvent::create([
            'device_id' => $device->id,
            'tenant_id' => $device->tenant_id,
            'event_type' => $displayEventType,
            'stage' => $newStage,
            'trust_score_before' => $oldScore,
            'trust_score_after' => $newScore,
            'trust_score_change' => $delta,
            'metadata' => array_merge($metadata, ['trust_delta' => $delta]),
            'reason' => $reason,
            'triggered_by' => $triggeredBy,
            'triggered_by_user' => $triggeredByUser,
        ]);

        // Update device
        $device->timestamps = false;
        $device->trust_score = $newScore;
        $device->lifecycle_stage = $newStage;
        $device->total_events = ($device->total_events ?? 0) + 1;

        if (!$device->first_seen_at) {
            $device->first_seen_at = now();
        }
        if ($newStage !== $oldStage) {
            $device->last_stage_change_at = now();
        }
        $device->days_active = $device->first_seen_at
            ? (int) $device->first_seen_at->diffInDays(now())
            : 0;
        $device->save();

        return $event;
    }

    /**
     * 根据数值判定阶段（不依赖数据库存储值）
     */
    protected function determineStageFromScoreFromValues(int $trustScore, bool $isBlacklisted, bool $hasLicense): string
    {
        if ($trustScore === 0 && $isBlacklisted) {
            return 'retired';
        }
        if ($trustScore >= 80) {
            return 'stable';
        }
        if ($trustScore >= 50) {
            return 'onboarding';
        }
        if ($trustScore < 50 && $trustScore > 0) {
            return 'suspicious';
        }
        if ($hasLicense) {
            return 'onboarding';
        }
        return 'new';
    }

    /**
     * 根据信任分确定生命周期阶段
     */
    public function determineStageFromScore(Device $device): string
    {
        return $this->determineStageFromScoreFromValues(
            $device->trust_score,
            $device->is_blacklisted ?? false,
            $device->license_id !== null
        );
    }

    /**
     * 获取设备的完整画像数据
     */
    public function getProfile(Device $device): array
    {
        $device->loadMissing([
            'license.product',
            'license.customer.user',
            'lifecycleEvents' => function ($q) {
                $q->orderByDesc('created_at')->limit(50);
            },
        ]);

        // 计算阶段停留时间
        $stageDuration = [];
        $currentStage = $device->lifecycle_stage;
        $stageDuration[$currentStage] = $device->last_stage_change_at
            ? (int) $device->last_stage_change_at->diffInDays(now())
            : $device->days_active;

        // 统计各阶段事件
        $eventStats = $device->lifecycleEvents()
            ->selectRaw('event_type, COUNT(*) as cnt')
            ->groupBy('event_type')
            ->pluck('cnt', 'event_type')
            ->toArray();

        return [
            'device' => $device,
            'profile' => [
                'current_stage' => $device->lifecycle_stage,
                'stage_label' => Device::LIFE_STAGES[$device->lifecycle_stage] ?? $device->lifecycle_stage,
                'trust_score' => $device->trust_score,
                'trust_level' => $this->getTrustLevel($device->trust_score),
                'days_active' => $device->days_active,
                'first_seen_at' => $device->first_seen_at,
                'last_stage_change_at' => $device->last_stage_change_at,
                'total_events' => $device->total_events,
                'event_stats' => $eventStats,
                'stage_duration_days' => $stageDuration,
            ],
            'recent_events' => $device->lifecycleEvents->toArray(),
            'timeline' => $this->buildTimeline($device),
        ];
    }

    /**
     * 构建生命周期时间线
     */
    public function buildTimeline(Device $device): array
    {
        $events = $device->lifecycleEvents()
            ->orderBy('created_at')
            ->get(['event_type', 'stage', 'trust_score_before', 'trust_score_after', 'reason', 'created_at']);

        $timeline = [];
        $stagesSeen = [];

        // If device has events but no 'new' stage was recorded as a transition,
        // include the initial stage based on earliest event or device state
        if ($events->isNotEmpty()) {
            $firstEvent = $events->first();
            $initialStage = null;

            // Determine what stage the device was in at first_seen_at
            // by looking at trust_score_before of the first event
            $score = $firstEvent->trust_score_before;
            if ($score === 0 && $firstEvent->stage !== 'retired') {
                $initialStage = $device->license_id ? 'new' : 'new';
            }

            // If initial stage differs from first event's stage, prepend it
            if ($initialStage && $initialStage !== $firstEvent->stage) {
                $timeline[] = [
                    'type' => 'stage_change',
                    'stage' => $initialStage,
                    'stage_label' => Device::LIFE_STAGES[$initialStage] ?? $initialStage,
                    'timestamp' => $device->first_seen_at ?? $firstEvent->created_at,
                    'trust_score' => $score,
                ];
                $stagesSeen[] = $initialStage;
            }
        } elseif ($device->lifecycle_stage === 'new') {
            $timeline[] = [
                'type' => 'stage_change',
                'stage' => 'new',
                'stage_label' => Device::LIFE_STAGES['new'],
                'timestamp' => $device->first_seen_at ?? $device->created_at,
                'trust_score' => 0,
            ];
            $stagesSeen[] = 'new';
        }

        foreach ($events as $event) {
            $stage = $event->stage;
            if (!in_array($stage, $stagesSeen)) {
                $stagesSeen[] = $stage;
                $timeline[] = [
                    'type' => 'stage_change',
                    'stage' => $stage,
                    'stage_label' => Device::LIFE_STAGES[$stage] ?? $stage,
                    'timestamp' => $event->created_at,
                    'trust_score' => $event->trust_score_after,
                ];
            }
        }

        return $timeline;
    }

    /**
     * 获取设备画像统计（租户级）
     */
    public function getProfileStats(int $tenantId): array
    {
        $devices = Device::where('tenant_id', $tenantId);

        $stageCounts = (clone $devices)
            ->selectRaw('lifecycle_stage, COUNT(*) as cnt')
            ->groupBy('lifecycle_stage')
            ->pluck('cnt', 'lifecycle_stage')
            ->toArray();

        $trustDistribution = [
            'high' => (clone $devices)->where('trust_score', '>=', 80)->count(),
            'medium' => (clone $devices)->whereBetween('trust_score', [50, 79])->count(),
            'low' => (clone $devices)->where('trust_score', '<', 50)->where('trust_score', '>', 0)->count(),
            'zero' => (clone $devices)->where('trust_score', 0)->count(),
        ];

        $avgDaysActive = (clone $devices)->avg('days_active');
        $avgTrust = (clone $devices)->avg('trust_score');

        // 阶段转换频率
        $transitionCounts = DeviceLifecycleEvent::where('tenant_id', $tenantId)
            ->selectRaw('event_type, COUNT(*) as cnt')
            ->groupBy('event_type')
            ->orderByDesc('cnt')
            ->limit(10)
            ->pluck('cnt', 'event_type')
            ->toArray();

        return [
            'stage_distribution' => $stageCounts,
            'trust_distribution' => $trustDistribution,
            'avg_days_active' => round($avgDaysActive, 1),
            'avg_trust_score' => round($avgTrust, 1),
            'total_profile_events' => DeviceLifecycleEvent::where('tenant_id', $tenantId)->count(),
            'transition_frequency' => $transitionCounts,
        ];
    }

    /**
     * 获取信任等级
     */
    public function getTrustLevel(int $score): string
    {
        return match (true) {
            $score >= 80 => 'high',
            $score >= 50 => 'medium',
            $score > 0 => 'low',
            default => 'none',
        };
    }

    /**
     * 检测异常行为并触发阶段降级
     */
    public function detectAnomaly(Device $device, string $reason, array $context = []): ?DeviceLifecycleEvent
    {
        // 扣减信任分
        return $this->adjustTrustScore(
            $device,
            -20,
            $reason,
            array_merge($context, ['detection' => 'anomaly']),
            'auto_detect',
        );
    }

    /**
     * 标记可疑设备
     */
    public function markSuspicious(Device $device, string $reason, ?int $userId = null): DeviceLifecycleEvent
    {
        $device->trust_score = min($device->trust_score, 30);
        $device->save();

        return $this->recordEvent(
            $device,
            '可疑标记',
            'suspicious',
            $reason,
            ['manual_mark' => true],
            'admin',
            $userId,
        );
    }

    /**
     * 废弃设备
     */
    public function retireDevice(Device $device, string $reason, ?int $userId = null): DeviceLifecycleEvent
    {
        $device->update([
            'trust_score' => 0,
            'is_blacklisted' => true,
            'license_id' => null,
        ]);

        return $this->recordEvent(
            $device,
            '废弃',
            'retired',
            $reason,
            [],
            'admin',
            $userId,
        );
    }
}
