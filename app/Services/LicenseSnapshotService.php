<?php

namespace App\Services;

use App\Models\License;
use App\Models\LicenseSnapshot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * License 快照与回滚服务 (M2-12)
 *
 * 在 License 变更前自动创建快照，支持查看快照历史、回滚到指定版本。
 * 快照保留期：30天
 */
class LicenseSnapshotService
{
    /**
     * 在 License 变更前创建快照
     *
     * @param License $license
     * @param string $action upgrade/downgrade/transfer/seat_change/type_change/admin_edit
     * @param User|null $operator
     * @param array|null $extraDiff 额外变更说明
     * @return LicenseSnapshot
     */
    public function createSnapshot(License $license, string $action, ?User $operator = null, ?array $extraDiff = null): LicenseSnapshot
    {
        $now = now();

        // 获取当前 license 完整数据
        $licenseData = $this->captureLicenseData($license);

        $snapshot = LicenseSnapshot::create([
            'tenant_id'    => $license->tenant_id,
            'license_id'   => $license->id,
            'action'       => $action,
            'status_before' => $license->getOriginal('status') ?? $license->status,
            'status_after' => $license->status,
            'license_data' => $licenseData,
            'diff'         => $extraDiff ?: $this->detectChanges($license, $licenseData),
            'created_by'   => $operator?->id,
        ]);

        return $snapshot;
    }

    /**
     * 获取 License 的快照历史
     */
    public function getSnapshots(int $tenantId, ?int $licenseId = null, array $params = []): array
    {
        $query = LicenseSnapshot::byTenant($tenantId)
            ->with(['license' => fn($q) => $q->withTrashed()]);

        if ($licenseId) {
            $query->byLicense($licenseId);
        }
        if (!empty($params['action'])) {
            $query->byAction($params['action']);
        }

        $perPage = (int) ($params['per_page'] ?? 20);
        $page = (int) ($params['page'] ?? 1);

        $total = $query->count();
        $items = $query->latest()->forPage($page, $perPage)->get();

        return [
            'items' => $items,
            'total' => $total,
            'page'  => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * 回滚 License 到指定快照
     *
     * @param License $license
     * @param LicenseSnapshot $snapshot
     * @param User $operator
     * @return array ['success' => bool, 'message' => string]
     */
    public function rollback(License $license, LicenseSnapshot $snapshot, User $operator): array
    {
        if ($snapshot->license_id !== $license->id) {
            return ['success' => false, 'message' => '快照不属于此 License'];
        }

        $snapshotData = $snapshot->license_data;

        try {
            DB::transaction(function () use ($license, $snapshotData, $operator, $snapshot) {
                // 创建当前状态的快照（作为回滚的备份）
                $this->createSnapshot($license, 'rollback', $operator, [
                    'note' => '回滚到快照 #' . $snapshot->id,
                ]);

                // 恢复 License 字段
                $restorable = array_intersect_key($snapshotData, array_flip([
                    'status', 'type', 'max_devices', 'seats', 'expires_at',
                    'metadata', 'allowed_domains', 'allowed_ips', 'allowed_versions',
                    'feature_flags', 'is_trial', 'trial_ends_at',
                ]));

                $license->update($restorable);

                // 如果快照中的状态与当前不同，触发状态机转移
                if ($snapshotData['status'] !== $license->status) {
                    // 使用原始状态，不触发额外审批
                    $license->update(['status' => $snapshotData['status']]);
                }
            });

            return ['success' => true, 'message' => '已回滚到快照 #' . $snapshot->id];
        } catch (\Throwable $e) {
            Log::error("LicenseSnapshot: rollback failed", [
                'license_id' => $license->id,
                'snapshot_id' => $snapshot->id,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => '回滚失败: ' . $e->getMessage()];
        }
    }

    /**
     * 仪表盘统计
     */
    public function getDashboard(int $tenantId): array
    {
        $today = now()->toDateString();
        $thirtyDaysAgo = now()->subDays(30);

        return [
            'total'    => LicenseSnapshot::byTenant($tenantId)->count(),
            'today'    => LicenseSnapshot::byTenant($tenantId)->whereDate('created_at', $today)->count(),
            'last_30d' => LicenseSnapshot::byTenant($tenantId)->where('created_at', '>=', $thirtyDaysAgo)->count(),
            'by_action' => LicenseSnapshot::byTenant($tenantId)
                ->select('action', \DB::raw('count(*) as count'))
                ->groupBy('action')
                ->pluck('count', 'action')
                ->toArray(),
        ];
    }

    /**
     * 清理过期快照（由定时任务调用）
     */
    public function cleanup(): int
    {
        $retentionDays = config('license-lifecycle.snapshot.retention_days', 30);
        $cutoff = now()->subDays($retentionDays);

        $count = LicenseSnapshot::where('created_at', '<', $cutoff)->delete();

        if ($count > 0) {
            Log::info("LicenseSnapshot: cleaned {$count} expired snapshots older than {$retentionDays} days");
        }

        return $count;
    }

    /**
     * 捕获 License 当前全量数据
     */
    protected function captureLicenseData(License $license): array
    {
        return $license->fresh()->toArray();
    }

    /**
     * 检测变更差异
     */
    protected function detectChanges(License $license, array $beforeData): array
    {
        $changes = [];
        $after = $license->fresh()->toArray();

        $trackedFields = ['status', 'type', 'max_devices', 'seats', 'expires_at', 'feature_flags'];

        foreach ($trackedFields as $field) {
            $before = $beforeData[$field] ?? null;
            $afterVal = $after[$field] ?? null;
            if ($before !== $afterVal) {
                $changes[$field] = ['from' => $before, 'to' => $afterVal];
            }
        }

        return $changes;
    }
}
