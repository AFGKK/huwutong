<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\FingerprintDriftTrackerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 设备指纹漂移追踪 (M2-25)
 */
class FingerprintDriftController extends Controller
{
    public function __construct(protected FingerprintDriftTrackerService $driftService) {}

    /**
     * 漂移仪表盘
     */
    public function dashboard(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        return ApiResponse::success($this->driftService->getDashboard($tenantId));
    }

    /**
     * 获取指定设备的指纹历史
     */
    public function deviceHistory(Request $request, int $deviceId): JsonResponse
    {
        $device = Device::where('id', $deviceId)
            ->where('tenant_id', $request->user()->tenant_id)
            ->firstOrFail();

        return ApiResponse::success($this->driftService->getDeviceSummary($device->id));
    }

    /**
     * 手动记录指纹快照
     */
    public function recordSnapshot(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => 'required|integer|exists:devices,id',
            'components' => 'required|array',
            'components.mac' => 'nullable|string|max:100',
            'components.cpu_id' => 'nullable|string|max:100',
            'components.motherboard' => 'nullable|string|max:100',
            'components.disk_sn' => 'nullable|string|max:100',
            'components.system_uuid' => 'nullable|string|max:100',
            'source' => 'nullable|string|in:activation,verification,heartbeat,manual',
            'notes' => 'nullable|string|max:500',
        ]);

        $device = Device::where('id', $validated['device_id'])
            ->where('tenant_id', $request->user()->tenant_id)
            ->firstOrFail();

        $record = $this->driftService->recordSnapshot(
            $device,
            $validated['components'],
            $validated['source'] ?? 'manual',
            $validated['notes'] ?? null
        );

        return ApiResponse::success($record, __("app.fingerprint_drift.msg_a19e358c"));
    }

    /**
     * 手动接受指纹漂移
     */
    public function acceptDrift(Request $request, int $historyId): JsonResponse
    {
        $validated = $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        $record = $this->driftService->acceptDrift($historyId, $validated['notes'] ?? null);

        return ApiResponse::success($record, __("app.fingerprint_drift.msg_e6c7e2aa"));
    }

    /**
     * 漂移待处理列表（需要人工确认的漂移）
     */
    public function pendingDrifts(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $pending = \App\Models\DeviceFingerprintHistory::where('tenant_id', $tenantId)
            ->whereIn('drift_type', ['partial', 'major'])
            ->where('auto_accepted', false)
            ->where('is_baseline', false)
            ->with(['device:id,fingerprint,platform,last_seen_at,os_version'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));

        return ApiResponse::success($pending);
    }
}
