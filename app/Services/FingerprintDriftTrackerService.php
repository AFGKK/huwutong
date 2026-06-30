<?php

namespace App\Services;

use App\Models\Device;
use App\Models\DeviceFingerprintHistory;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * 设备指纹漂移追踪服务 (M2-25)
 *
 * 记录设备指纹随时间的变化，识别硬件逐步更换（漂移）vs 突然变更（可能被盗/克隆），
 * 在安全阈值内自动更新基准指纹。
 */
class FingerprintDriftTrackerService
{
    const COMPONENT_KEYS = ['mac', 'cpu_id', 'motherboard', 'disk_sn', 'system_uuid'];

    /**
     * 记录指纹快照并检测漂移
     */
    public function recordSnapshot(
        Device $device,
        array $components,
        string $source = 'activation',
        ?string $notes = null
    ): DeviceFingerprintHistory {
        $tenantId = $device->tenant_id;
        $baseline = $this->getBaseline($device->id);

        // 计算组件变更
        $changedComponents = 0;
        $matchedComponents = 0;
        $totalComponents = count(self::COMPONENT_KEYS);
        $componentDetails = [];

        if ($baseline) {
            foreach (self::COMPONENT_KEYS as $key) {
                $oldVal = $baseline->$key ?? '';
                $newVal = $components[$key] ?? '';
                $isMatch = !empty($oldVal) && !empty($newVal) && strtolower(trim($oldVal)) === strtolower(trim($newVal));

                if (!empty($oldVal) && !empty($newVal)) {
                    if (!$isMatch) {
                        $changedComponents++;
                    } else {
                        $matchedComponents++;
                    }
                }

                $componentDetails[$key] = [
                    'old' => $oldVal,
                    'new' => $newVal,
                    'matched' => $isMatch,
                ];
            }
        }

        $similarityScore = $baseline
            ? ($totalComponents > 0 ? round(($matchedComponents / $totalComponents) * 100, 2) : null)
            : null;

        // 判断漂移类型
        $driftType = $this->determineDriftType($changedComponents, $totalComponents, $similarityScore, $baseline);

        // 自动接受安全漂移
        $autoAccepted = in_array($driftType, ['initial', 'gradual']);

        // 如果是首次记录或自动接受的漂移，更新基准
        if (!$baseline || $autoAccepted) {
            $this->resetBaseline($device->id);
        }

        $fingerprintVersion = $components['_version'] ?? FingerprintService::CURRENT_VERSION;
        $fingerprintStr = $components['_fingerprint'] ?? '';

        $record = DeviceFingerprintHistory::create([
            'device_id' => $device->id,
            'tenant_id' => $tenantId,
            'license_id' => $device->license_id,
            'fingerprint' => $fingerprintStr,
            'fingerprint_version' => $fingerprintVersion,
            'mac' => $components['mac'] ?? null,
            'cpu_id' => $components['cpu_id'] ?? null,
            'motherboard' => $components['motherboard'] ?? null,
            'disk_sn' => $components['disk_sn'] ?? null,
            'system_uuid' => $components['system_uuid'] ?? null,
            'components' => $componentDetails,
            'drift_type' => $driftType,
            'changed_components' => $changedComponents,
            'total_components' => $totalComponents,
            'similarity_score' => $similarityScore,
            'is_baseline' => $autoAccepted || !$baseline,
            'auto_accepted' => $autoAccepted,
            'source' => $source,
            'notes' => $notes,
        ]);

        // 如果手动接受了漂移，同时更新设备的指纹
        if ($autoAccepted && $fingerprintStr && $fingerprintStr !== $device->fingerprint) {
            $device->update(['fingerprint' => $fingerprintStr]);
        }

        return $record;
    }

    /**
     * 获取设备指纹漂移历史
     */
    public function getHistory(int $deviceId, int $limit = 50): Collection
    {
        return DeviceFingerprintHistory::where('device_id', $deviceId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * 获取设备的当前基准指纹
     */
    public function getBaseline(int $deviceId): ?DeviceFingerprintHistory
    {
        return DeviceFingerprintHistory::where('device_id', $deviceId)
            ->where('is_baseline', true)
            ->latest('created_at')
            ->first();
    }

    /**
     * 手动接受漂移（更新基准）
     */
    public function acceptDrift(int $historyId, string $notes = null): DeviceFingerprintHistory
    {
        $record = DeviceFingerprintHistory::findOrFail($historyId);

        // 重置该设备的所有基准标记
        $this->resetBaseline($record->device_id);

        // 标记为新基准
        $record->update([
            'is_baseline' => true,
            'auto_accepted' => false,
            'drift_type' => 'manual',
            'notes' => $notes ? ($record->notes ? $record->notes . "\n" . $notes : $notes) : $record->notes,
        ]);

        // 更新设备指纹
        if ($record->fingerprint) {
            Device::where('id', $record->device_id)->update(['fingerprint' => $record->fingerprint]);
        }

        return $record->fresh();
    }

    /**
     * 获取漂移统计仪表盘
     */
    public function getDashboard(int $tenantId): array
    {
        $totalDevices = Device::where('tenant_id', $tenantId)->count();

        $recentDrifts = DeviceFingerprintHistory::where('tenant_id', $tenantId)
            ->whereIn('drift_type', ['partial', 'major'])
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->count();

        $autoAccepted = DeviceFingerprintHistory::where('tenant_id', $tenantId)
            ->where('auto_accepted', true)
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->count();

        $driftByType = DeviceFingerprintHistory::where('tenant_id', $tenantId)
            ->selectRaw('drift_type, COUNT(*) as total')
            ->groupBy('drift_type')
            ->pluck('total', 'drift_type')
            ->toArray();

        // 需要关注的漂移（未处理的部分/重大漂移）
        $pendingDrifts = DeviceFingerprintHistory::where('tenant_id', $tenantId)
            ->whereIn('drift_type', ['partial', 'major'])
            ->where('auto_accepted', false)
            ->where('is_baseline', false)
            ->with(['device:id,fingerprint,platform,last_seen_at'])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return [
            'total_devices' => $totalDevices,
            'recent_drifts_30d' => $recentDrifts,
            'auto_accepted_30d' => $autoAccepted,
            'drift_by_type' => $driftByType,
            'pending_drifts' => $pendingDrifts,
        ];
    }

    /**
     * 获取设备的漂移摘要
     */
    public function getDeviceSummary(int $deviceId): array
    {
        $device = Device::findOrFail($deviceId);
        $history = $this->getHistory($deviceId, 20);
        $baseline = $this->getBaseline($deviceId);

        $driftCount = $history->where('is_baseline', false)->count();
        $totalSnapshots = $history->count();
        $lastDrift = $history->first();

        return [
            'device_id' => $deviceId,
            'fingerprint' => $device->fingerprint,
            'platform' => $device->platform,
            'baseline' => $baseline,
            'total_snapshots' => $totalSnapshots,
            'drift_events' => $driftCount,
            'last_drift' => $lastDrift,
            'recent_history' => $history,
        ];
    }

    /**
     * 判断漂移类型
     */
    private function determineDriftType(int $changed, int $total, ?float $similarity, ?DeviceFingerprintHistory $baseline): string
    {
        if (!$baseline) {
            return 'initial';
        }

        if ($changed === 0) {
            return 'initial'; // 无变更，作为基准更新
        }

        $ratio = $total > 0 ? $changed / $total : 1;

        // 1个组件变更 → 逐步漂移
        if ($changed === 1) {
            return 'gradual';
        }

        // 2个组件变更 → 部分漂移
        if ($changed === 2) {
            return 'partial';
        }

        // 3个以上组件变更 → 重大漂移（可能被盗/克隆）
        return 'major';
    }

    /**
     * 重置设备的所有基准标记
     */
    private function resetBaseline(int $deviceId): void
    {
        DeviceFingerprintHistory::where('device_id', $deviceId)
            ->where('is_baseline', true)
            ->update(['is_baseline' => false]);
    }
}
