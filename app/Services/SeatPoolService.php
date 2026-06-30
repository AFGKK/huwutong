<?php

namespace App\Services;

use App\Models\License;
use App\Models\SeatAssignment;
use App\Models\SeatWaitingQueue;
use App\Models\Device;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * License 席位池管理
 *
 * 支持三种模式：
 * - shared: N个席位共享给所有设备，先到先得
 * - exclusive: 每个设备独占一个席位
 * - auto: 自动分配，有空位就占用，超限排队等待
 */
class SeatPoolService
{
    const MODE_SHARED = 'shared';
    const MODE_EXCLUSIVE = 'exclusive';
    const MODE_AUTO = 'auto';

    /**
     * 分配席位
     *
     * @return array{success: bool, assignment: ?SeatAssignment, message: string, queue_position: ?int}
     */
    public function assignSeat(License $license, string $seatIdentifier, ?string $label = null, ?int $deviceId = null, ?int $customerId = null, string $assignedBy = 'auto'): array
    {
        // 先释放过期席位
        $this->releaseExpiredSeats($license);

        // 检查已有分配
        $existing = SeatAssignment::where('license_id', $license->id)
            ->where('seat_identifier', $seatIdentifier)
            ->whereIn('status', ['active', 'inactive'])
            ->first();

        if ($existing) {
            $existing->update([
                'last_active_at' => now(),
                'status' => 'active',
                'device_id' => $deviceId ?: $existing->device_id,
            ]);
            return ['success' => true, 'assignment' => $existing->fresh(), 'message' => '席位已存在，已重新激活', 'queue_position' => null];
        }

        // 统计活跃席位
        $activeCount = $this->getActiveSeatCount($license);

        // 检查模式
        $poolMode = $license->pool_mode ?? self::MODE_SHARED;

        if ($poolMode === self::MODE_EXCLUSIVE) {
            // 独占模式：每个device_id只能有一个席位
            if ($deviceId) {
                $existingDevice = SeatAssignment::where('license_id', $license->id)
                    ->where('device_id', $deviceId)
                    ->where('status', 'active')
                    ->first();
                if ($existingDevice) {
                    return ['success' => false, 'assignment' => null, 'message' => '该设备已有独占席位', 'queue_position' => null];
                }
            }
        }

        // 检查是否有可用席位
        $totalSeats = $license->seats ?? 0;

        if ($activeCount < $totalSeats) {
            // 有空位
            $assignment = SeatAssignment::create([
                'license_id' => $license->id,
                'tenant_id' => $license->tenant_id,
                'device_id' => $deviceId,
                'customer_id' => $customerId,
                'seat_identifier' => $seatIdentifier,
                'label' => $label,
                'status' => 'active',
                'assigned_at' => now(),
                'last_active_at' => now(),
                'assigned_by' => $assignedBy,
            ]);
            return ['success' => true, 'assignment' => $assignment, 'message' => '席位分配成功', 'queue_position' => null];
        }

        // 超出容量
        if ($poolMode === self::MODE_SHARED) {
            return ['success' => false, 'assignment' => null, 'message' => "席位已满 ({$activeCount}/{$totalSeats})", 'queue_position' => null];
        }

        // AUTO模式：排队等待
        if ($poolMode === self::MODE_AUTO) {
            return $this->joinQueue($license, $seatIdentifier, $label, $deviceId);
        }

        return ['success' => false, 'assignment' => null, 'message' => "席位已满 ({$activeCount}/{$totalSeats})", 'queue_position' => null];
    }

    /**
     * 释放席位
     */
    public function releaseSeat(License $license, ?string $seatIdentifier = null, ?int $assignmentId = null): bool
    {
        $query = SeatAssignment::where('license_id', $license->id)->where('status', 'active');

        if ($assignmentId) {
            $query->where('id', $assignmentId);
        } elseif ($seatIdentifier) {
            $query->where('seat_identifier', $seatIdentifier);
        }

        $updated = $query->update([
            'status' => 'inactive',
            'released_at' => now(),
        ]);

        if ($updated > 0 && $license->pool_mode === self::MODE_AUTO) {
            // 尝试从队列中分配下一个等待者
            $this->assignNextFromQueue($license);
        }

        return $updated > 0;
    }

    /**
     * 释放过期席位（超过超时时间未活跃的）
     */
    public function releaseExpiredSeats(License $license): int
    {
        $timeout = $license->pool_timeout_minutes ?? 30;

        $expired = SeatAssignment::where('license_id', $license->id)
            ->where('status', 'active')
            ->where('last_active_at', '<', now()->subMinutes($timeout))
            ->get();

        $count = 0;
        foreach ($expired as $seat) {
            $seat->update([
                'status' => 'inactive',
                'released_at' => now(),
            ]);
            $count++;
        }

        if ($count > 0 && $license->pool_mode === self::MODE_AUTO) {
            $this->assignNextFromQueue($license);
        }

        return $count;
    }

    /**
     * 心跳更新：标记席位活跃
     */
    public function heartbeat(License $license, string $seatIdentifier): bool
    {
        return SeatAssignment::where('license_id', $license->id)
            ->where('seat_identifier', $seatIdentifier)
            ->where('status', 'active')
            ->update(['last_active_at' => now()]) > 0;
    }

    /**
     * 加入排队队列
     */
    protected function joinQueue(License $license, string $seatIdentifier, ?string $label = null, ?int $deviceId = null): array
    {
        // 检查是否已在队列中
        $existing = SeatWaitingQueue::where('license_id', $license->id)
            ->where('seat_identifier', $seatIdentifier)
            ->where('status', 'waiting')
            ->first();

        if ($existing) {
            return ['success' => false, 'assignment' => null, 'message' => '已在排队队列中', 'queue_position' => $existing->queue_position];
        }

        // 检查队列上限
        $waitingLimit = $license->pool_waiting_limit ?? 50;
        $queueCount = SeatWaitingQueue::where('license_id', $license->id)
            ->where('status', 'waiting')->count();

        if ($queueCount >= $waitingLimit) {
            return ['success' => false, 'assignment' => null, 'message' => "排队队列已满 ({$waitingLimit})", 'queue_position' => null];
        }

        // 获取队列位置
        $maxPosition = SeatWaitingQueue::where('license_id', $license->id)
            ->where('status', 'waiting')
            ->max('queue_position') ?? 0;

        $entry = SeatWaitingQueue::create([
            'license_id' => $license->id,
            'tenant_id' => $license->tenant_id,
            'seat_identifier' => $seatIdentifier,
            'label' => $label,
            'device_fingerprint' => $deviceId ? (Device::find($deviceId)?->fingerprint) : null,
            'status' => 'waiting',
            'queue_position' => $maxPosition + 1,
            'max_wait_minutes' => $license->pool_timeout_minutes ?? 30,
            'expires_at' => now()->addMinutes($license->pool_timeout_minutes ?? 30),
        ]);

        return ['success' => false, 'assignment' => null, 'message' => "已加入排队队列 #{$entry->queue_position}", 'queue_position' => $entry->queue_position];
    }

    /**
     * 从队列中分配下一个席位
     */
    protected function assignNextFromQueue(License $license): ?SeatAssignment
    {
        $nextInQueue = SeatWaitingQueue::where('license_id', $license->id)
            ->where('status', 'waiting')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderBy('queue_position')
            ->first();

        if (!$nextInQueue) {
            return null;
        }

        $activeCount = $this->getActiveSeatCount($license);
        if ($activeCount >= ($license->seats ?? 0)) {
            return null;
        }

        $assignment = SeatAssignment::create([
            'license_id' => $license->id,
            'tenant_id' => $license->tenant_id,
            'customer_id' => $license->customer_id,
            'seat_identifier' => $nextInQueue->seat_identifier,
            'label' => $nextInQueue->label,
            'status' => 'active',
            'assigned_at' => now(),
            'last_active_at' => now(),
            'assigned_by' => 'auto',
        ]);

        $nextInQueue->update(['status' => 'assigned']);

        return $assignment;
    }

    /**
     * 取消排队
     */
    public function cancelQueue(License $license, string $seatIdentifier): bool
    {
        return SeatWaitingQueue::where('license_id', $license->id)
            ->where('seat_identifier', $seatIdentifier)
            ->where('status', 'waiting')
            ->update(['status' => 'cancelled']) > 0;
    }

    /**
     * 获取活跃席位数
     */
    public function getActiveSeatCount(License $license): int
    {
        return SeatAssignment::where('license_id', $license->id)
            ->where('status', 'active')
            ->count();
    }

    /**
     * 获取席位池状态
     */
    public function getPoolStatus(License $license): array
    {
        $active = $this->getActiveSeatCount($license);
        $total = $license->seats ?? 0;
        $inactive = SeatAssignment::where('license_id', $license->id)->where('status', 'inactive')->count();
        $waiting = SeatWaitingQueue::where('license_id', $license->id)->where('status', 'waiting')->count();
        $expiredQueue = SeatWaitingQueue::where('license_id', $license->id)
            ->where('status', 'waiting')
            ->where('expires_at', '<', now())
            ->count();

        $modeLabel = [
            self::MODE_SHARED => '共享',
            self::MODE_EXCLUSIVE => '独占',
            self::MODE_AUTO => '自动排队',
        ];

        $utilization = $total > 0 ? round(($active / $total) * 100, 1) : 0;

        return [
            'total_seats' => $total,
            'active' => $active,
            'inactive' => $inactive,
            'available' => max(0, $total - $active),
            'utilization_percent' => $utilization,
            'waiting_queue' => $waiting,
            'expired_queue' => $expiredQueue,
            'pool_mode' => $license->pool_mode ?? self::MODE_SHARED,
            'pool_mode_label' => $modeLabel[$license->pool_mode ?? self::MODE_SHARED] ?? '共享',
            'timeout_minutes' => $license->pool_timeout_minutes ?? 30,
            'waiting_limit' => $license->pool_waiting_limit ?? 50,
        ];
    }

    /**
     * 获取席位分配列表
     */
    public function getAssignments(License $license, array $filters = [], int $perPage = 20)
    {
        $query = SeatAssignment::where('license_id', $license->id)
            ->with('device:id,fingerprint,platform,hostname')
            ->orderByDesc('assigned_at');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('seat_identifier', 'like', "%{$filters['search']}%")
                  ->orWhere('label', 'like', "%{$filters['search']}%");
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * 获取排队列表
     */
    public function getQueue(License $license)
    {
        return SeatWaitingQueue::where('license_id', $license->id)
            ->where('status', 'waiting')
            ->orderBy('queue_position')
            ->get();
    }

    /**
     * 批量释放过期席位（所有License）
     */
    public function batchReleaseExpiredSeats(int $tenantId): array
    {
        $licenses = License::where('tenant_id', $tenantId)->get();
        $results = [];

        foreach ($licenses as $license) {
            $count = $this->releaseExpiredSeats($license);
            if ($count > 0) {
                $results[] = [
                    'license_id' => $license->id,
                    'license_key' => $license->license_key,
                    'released' => $count,
                ];
            }
        }

        return $results;
    }

    /**
     * 更新席位池配置
     */
    public function updatePoolConfig(License $license, array $data): License
    {
        $license->update($data);
        return $license->fresh();
    }
}
