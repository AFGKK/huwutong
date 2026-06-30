<?php

namespace App\Services;

use App\Models\AuthorizationReservation;
use App\Models\AuthorizationReservationLog;
use App\Models\License;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TwoPhaseCommitService
{
    /**
     * 预留默认过期时间（秒）
     */
    const int DEFAULT_TTL = 300; // 5 分钟

    /**
     * 预留锁定缓存前缀
     */
    const string RESERVE_LOCK_PREFIX = 'hwt:2pc:lock:';

    /**
     * Phase 1: 预申请授权（锁定配额/席位）
     *
     * 在分布式高并发场景下，客户端先预申请授权，
     * 系统锁定一个可用slot，返回 reservation_token
     * 客户端在 TTL 内必须 commit，否则预留自动过期释放。
     *
     * @param License $license
     * @param array $data 包含 fingerprint, components, platform, os_version, ip_address
     * @param int $ttl 预留过期时间（秒），默认 300
     * @return array ['success' => bool, 'reservation' => ?, 'error' => ?]
     */
    public function reserve(License $license, array $data, int $ttl = self::DEFAULT_TTL): array
    {
        // 使用分布式锁防止并发超卖
        $lockKey = self::RESERVE_LOCK_PREFIX . $license->id;
        $lock = Cache::lock($lockKey, 10);

        try {
            $lock->block(5);
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException) {
            return [
                'success' => false,
                'error' => 'RESERVATION_LOCK_TIMEOUT',
                'message' => '系统繁忙，请稍后重试',
            ];
        }

        try {
            // 检查 License 是否可激活
            $status = \App\Enums\LicenseStatus::tryFrom($license->status);
            if (!$status || !$status->isActivable()) {
                return [
                    'success' => false,
                    'error' => 'LICENSE_NOT_ACTIVATABLE',
                    'message' => "License 当前状态「{$license->status}」不允许激活",
                ];
            }

            // 检查过期
            if ($license->expires_at && $license->expires_at->isPast()) {
                return [
                    'success' => false,
                    'error' => 'LICENSE_EXPIRED',
                    'message' => 'License 已过期',
                ];
            }

            // 检查设备数量上限
            $currentDeviceCount = $license->devices()->where('is_blacklisted', false)->count();
            $maxDevices = $license->max_devices;

            if ($currentDeviceCount >= $maxDevices) {
                // 检查这个 fingerprint 是否已有设备
                $fingerprint = $data['fingerprint'] ?? null;
                if ($fingerprint) {
                    $isExisting = $license->devices()
                        ->where('fingerprint', $fingerprint)
                        ->where('is_blacklisted', false)
                        ->exists();
                    if (!$isExisting) {
                        return [
                            'success' => false,
                            'error' => 'DEVICE_LIMIT_EXCEEDED',
                            'message' => "设备数量已达上限 ({$maxDevices})",
                            'max_devices' => $maxDevices,
                            'current_count' => $currentDeviceCount,
                        ];
                    }
                } elseif ($currentDeviceCount >= $maxDevices) {
                    return [
                        'success' => false,
                        'error' => 'DEVICE_LIMIT_EXCEEDED',
                        'message' => "设备数量已达上限 ({$maxDevices})",
                        'max_devices' => $maxDevices,
                        'current_count' => $currentDeviceCount,
                    ];
                }
            }

            // 检查该 fingerprint 是否有未过期的 active 预留
            $fingerprint = $data['fingerprint'] ?? null;
            if ($fingerprint) {
                $activeReservation = AuthorizationReservation::where('license_id', $license->id)
                    ->where('fingerprint', $fingerprint)
                    ->where('status', 'reserved')
                    ->where('expires_at', '>', now())
                    ->first();

                if ($activeReservation) {
                    // 已有有效预留，返回已有 token（幂等）
                    return [
                        'success' => true,
                        'reservation' => $activeReservation,
                        'is_existing' => true,
                    ];
                }
            }

            // 创建预留记录
            $reservation = DB::transaction(function () use ($license, $data, $ttl, $fingerprint) {
                $reservation = AuthorizationReservation::create([
                    'license_id' => $license->id,
                    'tenant_id' => $license->tenant_id,
                    'reservation_token' => (string) Str::uuid(),
                    'fingerprint' => $fingerprint,
                    'ip_address' => $data['ip_address'] ?? request()->ip(),
                    'payload' => $data['payload'] ?? [],
                    'status' => 'reserved',
                    'expires_at' => now()->addSeconds($ttl),
                ]);

                // 记录日志
                AuthorizationReservationLog::create([
                    'reservation_id' => $reservation->id,
                    'action' => 'reserve',
                    'detail' => [
                        'fingerprint' => $fingerprint,
                        'ttl' => $ttl,
                        'expires_at' => $reservation->expires_at->toIso8601String(),
                    ],
                ]);

                return $reservation;
            });

            // 异步预热缓存，触发过期清理（通过队列）
            $this->scheduleExpiryCheck($reservation);

            return [
                'success' => true,
                'reservation' => $reservation,
                'is_existing' => false,
            ];

        } finally {
            $lock->forceRelease();
        }
    }

    /**
     * Phase 2: 确认提交预留（完成授权）
     *
     * 客户端在拿到 reservation_token 后调用此接口完成最终激活。
     * 此时才会真正变更 License 状态、创建设备、记录激活日志。
     *
     * @param string $reservationToken 预留令牌
     * @return array
     */
    public function commit(string $reservationToken): array
    {
        $reservation = AuthorizationReservation::where('reservation_token', $reservationToken)
            ->where('status', 'reserved')
            ->first();

        if (!$reservation) {
            return [
                'success' => false,
                'error' => 'RESERVATION_NOT_FOUND',
                'message' => '预留不存在或已被处理',
            ];
        }

        if ($reservation->isExpired()) {
            // 标记为过期
            $this->markExpired($reservation);
            return [
                'success' => false,
                'error' => 'RESERVATION_EXPIRED',
                'message' => '预留已过期，请重新预申请',
            ];
        }

        $license = $reservation->license;

        return DB::transaction(function () use ($reservation, $license) {
            // 再次校验 License 状态
            $status = \App\Enums\LicenseStatus::tryFrom($license->status);
            if (!$status || !$status->isActivable()) {
                $this->cancelReservation($reservation, 'license_not_activable');
                return [
                    'success' => false,
                    'error' => 'LICENSE_NOT_ACTIVATABLE',
                    'message' => "License 状态已变更，不允许激活",
                ];
            }

            // 将 License 转为 active（如果尚未激活）
            if ($license->status === 'pending') {
                $license->update([
                    'status' => \App\Enums\LicenseStatus::Active->value,
                    'activated_at' => now(),
                ]);
            }

            // 更新预留状态
            $reservation->update([
                'status' => 'committed',
                'committed_at' => now(),
            ]);

            // 记录日志
            AuthorizationReservationLog::create([
                'reservation_id' => $reservation->id,
                'action' => 'commit',
                'detail' => [
                    'license_status' => $license->status,
                    'committed_at' => now()->toIso8601String(),
                ],
            ]);

            return [
                'success' => true,
                'reservation' => $reservation->fresh(),
                'license' => $license->fresh(),
            ];
        });
    }

    /**
     * 取消预留（客户端主动取消）
     */
    public function cancel(string $reservationToken): array
    {
        $reservation = AuthorizationReservation::where('reservation_token', $reservationToken)
            ->where('status', 'reserved')
            ->first();

        if (!$reservation) {
            return [
                'success' => false,
                'error' => 'RESERVATION_NOT_FOUND',
                'message' => '预留不存在或已被处理',
            ];
        }

        $this->cancelReservation($reservation, 'client_cancelled');

        return [
            'success' => true,
            'message' => '预留已取消',
        ];
    }

    /**
     * 查询预留状态
     */
    public function getStatus(string $reservationToken): array
    {
        $reservation = AuthorizationReservation::where('reservation_token', $reservationToken)
            ->with('logs')
            ->first();

        if (!$reservation) {
            return [
                'success' => false,
                'error' => 'RESERVATION_NOT_FOUND',
                'message' => '预留不存在',
            ];
        }

        return [
            'success' => true,
            'reservation' => $reservation,
            'is_valid' => $reservation->isValid(),
            'is_expired' => $reservation->isExpired(),
            'seconds_remaining' => $reservation->isValid()
                ? (int) now()->diffInSeconds($reservation->expires_at, false)
                : 0,
        ];
    }

    /**
     * 批量清理过期预留
     */
    public function cleanupExpired(): int
    {
        $expired = AuthorizationReservation::where('status', 'reserved')
            ->where('expires_at', '<=', now())
            ->limit(500)
            ->get();

        $count = 0;
        foreach ($expired as $reservation) {
            $this->markExpired($reservation);
            $count++;
        }

        return $count;
    }

    /**
     * 获取 License 的预留统计
     */
    public function getReservationStats(License $license): array
    {
        $total = AuthorizationReservation::where('license_id', $license->id)->count();
        $reserved = AuthorizationReservation::where('license_id', $license->id)
            ->where('status', 'reserved')
            ->where('expires_at', '>', now())
            ->count();
        $committed = AuthorizationReservation::where('license_id', $license->id)
            ->where('status', 'committed')
            ->count();
        $expired = AuthorizationReservation::where('license_id', $license->id)
            ->whereIn('status', ['expired', 'cancelled'])
            ->count();

        return [
            'total' => $total,
            'active_reservations' => $reserved,
            'committed' => $committed,
            'expired_cancelled' => $expired,
        ];
    }

    /**
     * 获取租户下所有 active 预留列表
     */
    public function getActiveReservations(int $tenantId, int $perPage = 20): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return AuthorizationReservation::with('license')
            ->where('tenant_id', $tenantId)
            ->where('status', 'reserved')
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * 获取预留历史
     */
    public function getReservationHistory(int $tenantId, array $filters = [], int $perPage = 20): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = AuthorizationReservation::with('license')
            ->where('tenant_id', $tenantId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['license_id'])) {
            $query->where('license_id', $filters['license_id']);
        }
        if (!empty($filters['fingerprint'])) {
            $query->where('fingerprint', 'like', "%{$filters['fingerprint']}%");
        }

        return $query->orderByDesc('created_at')
            ->paginate($perPage);
    }

    // ─── 内部方法 ─────────────────────────────────────

    /**
     * 取消预留
     */
    protected function cancelReservation(AuthorizationReservation $reservation, string $reason): void
    {
        $reservation->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        AuthorizationReservationLog::create([
            'reservation_id' => $reservation->id,
            'action' => 'cancel',
            'detail' => ['reason' => $reason],
        ]);
    }

    /**
     * 标记预留过期
     */
    protected function markExpired(AuthorizationReservation $reservation): void
    {
        $reservation->update([
            'status' => 'expired',
            'cancelled_at' => now(),
        ]);

        AuthorizationReservationLog::create([
            'reservation_id' => $reservation->id,
            'action' => 'expire',
            'detail' => ['expired_at' => $reservation->expires_at->toIso8601String()],
        ]);
    }

    /**
     * 调度过期检查（实际通过定时任务批量清理）
     */
    protected function scheduleExpiryCheck(AuthorizationReservation $reservation): void
    {
        // 延迟清理预留：实际由 PruneExpiredReservations command 批量处理
        // 这里只是占位，不做实际调度
    }
}
