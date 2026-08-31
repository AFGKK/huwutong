<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\DeviceLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function __construct(
        protected DeviceLifecycleService $lifecycleService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Device::with('license.product', 'license.customer.user')
            ->where('tenant_id', $request->user()->tenant_id);

        // 搜索
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('fingerprint', 'like', "%{$search}%")
                  ->orWhere('hostname', 'like', "%{$search}%")
                  ->orWhere('platform', 'like', "%{$search}%");
            });
        }

        // 筛选
        if ($request->filled('filter.platform')) {
            $query->where('platform', $request->input('filter.platform'));
        }
        if ($request->filled('filter.is_blacklisted')) {
            $query->where('is_blacklisted', $request->boolean('filter.is_blacklisted'));
        }
        if ($request->filled('filter.is_virtual')) {
            $query->where('is_virtual', $request->boolean('filter.is_virtual'));
        }
        if ($request->filled('filter.license_id')) {
            $query->where('license_id', $request->input('filter.license_id'));
        }
        if ($request->filled('filter.trust_score_min')) {
            $query->where('trust_score', '>=', (int) $request->input('filter.trust_score_min'));
        }
        if ($request->filled('filter.lifecycle_stage')) {
            $query->where('lifecycle_stage', $request->input('filter.lifecycle_stage'));
        }

        // 排序
        $sortField = $request->input('sort', '-last_seen_at');
        $direction = str_starts_with($sortField, '-') ? 'desc' : 'asc';
        $field = ltrim($sortField, '-');

        $allowedSorts = ['fingerprint', 'platform', 'trust_score', 'is_blacklisted', 'is_virtual',
            'last_seen_at', 'last_activated_at', 'created_at', 'lifecycle_stage', 'days_active'];
        if (in_array($field, $allowedSorts)) {
            $query->orderBy($field, $direction);
        } else {
            $query->latest('last_seen_at');
        }

        $perPage = min((int) $request->input('per_page', 20), 100);

        return ApiResponse::paginated($query->paginate($perPage));
    }

    public function show(int $id, Request $request): JsonResponse
    {
        $device = Device::with('license.product', 'license.customer.user')
            ->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($id);

        return ApiResponse::success($device);
    }

    /**
     * 停用设备（软删除或标记）
     */
    public function deactivate(int $id, Request $request): JsonResponse
    {
        $device = Device::where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($id);

        // 移除 license 关联并降低信任分
        $device->update([
            'license_id' => null,
            'trust_score' => 0,
            'is_blacklisted' => $request->boolean('blacklist', false),
        ]);

        return ApiResponse::success($device->fresh(), __('app.device.device_deactivated'));
    }

    /**
     * 设备统计
     */
    public function stats(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $total = Device::where('tenant_id', $tenantId)->count();
        $active = Device::where('tenant_id', $tenantId)->whereNotNull('license_id')->count();
        $blacklisted = Device::where('tenant_id', $tenantId)->where('is_blacklisted', true)->count();
        $virtual = Device::where('tenant_id', $tenantId)->where('is_virtual', true)->count();

        // 平台分布
        $byPlatform = Device::where('tenant_id', $tenantId)
            ->whereNotNull('platform')
            ->selectRaw('platform, count(*) as count')
            ->groupBy('platform')
            ->orderByDesc('count')
            ->pluck('count', 'platform');

        // 信任分分布
        $trustBuckets = [
            'high' => Device::where('tenant_id', $tenantId)->where('trust_score', '>=', 80)->count(),
            'medium' => Device::where('tenant_id', $tenantId)->whereBetween('trust_score', [50, 79])->count(),
            'low' => Device::where('tenant_id', $tenantId)->where('trust_score', '<', 50)->count(),
        ];

        return ApiResponse::success([
            'total' => $total,
            'active' => $active,
            'inactive' => $total - $active,
            'blacklisted' => $blacklisted,
            'virtual' => $virtual,
            'by_platform' => $byPlatform,
            'trust_buckets' => $trustBuckets,
        ]);
    }

    /**
     * 批量操作
     */
    public function batch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:devices,id',
            'action' => 'required|in:deactivate,blacklist,remove_blacklist',
        ]);

        $tenantId = $request->user()->tenant_id;
        $count = 0;

        foreach ($validated['ids'] as $id) {
            $device = Device::where('tenant_id', $tenantId)->find($id);
            if (!$device) continue;

            match ($validated['action']) {
                'deactivate' => $device->update(['license_id' => null, 'trust_score' => 0]),
                'blacklist' => $device->update(['is_blacklisted' => true]),
                'remove_blacklist' => $device->update(['is_blacklisted' => false]),
            };

            $count++;
        }

        return ApiResponse::success(['affected' => $count], __("app.device.msg_dab6e2fb"));
    }

    // ═══════════════ 生命周期画像 (M3-24) ═══════════════

    /**
     * 获取设备画像
     */
    public function profile(int $id, Request $request): JsonResponse
    {
        $device = Device::where('tenant_id', $request->user()->tenant_id)->findOrFail($id);

        return ApiResponse::success(
            $this->lifecycleService->getProfile($device)
        );
    }

    /**
     * 获取设备画像统计（租户级）
     */
    public function profileStats(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->lifecycleService->getProfileStats($request->user()->tenant_id)
        );
    }

    /**
     * 调整设备信任分
     */
    public function adjustTrustScore(int $id, Request $request): JsonResponse
    {
        $data = $request->validate([
            'delta' => 'required|integer|min:-100|max:100',
            'reason' => 'required|string|max:500',
        ]);

        $device = Device::where('tenant_id', $request->user()->tenant_id)->findOrFail($id);

        $event = $this->lifecycleService->adjustTrustScore(
            $device,
            $data['delta'],
            $data['reason'],
            [],
            'admin',
            $request->user()->id,
        );

        return ApiResponse::success([
            'event' => $event,
            'new_trust_score' => $device->fresh()->trust_score,
            'new_stage' => $device->fresh()->lifecycle_stage,
        ], __('app.device.trust_score_adjusted'));
    }

    /**
     * 标记可疑设备
     */
    public function markSuspicious(int $id, Request $request): JsonResponse
    {
        $data = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $device = Device::where('tenant_id', $request->user()->tenant_id)->findOrFail($id);

        $event = $this->lifecycleService->markSuspicious($device, $data['reason'], $request->user()->id);

        return ApiResponse::success($event->load('device'), __('app.device.device_marked_suspicious'));
    }

    /**
     * 废弃设备
     */
    public function retire(int $id, Request $request): JsonResponse
    {
        $data = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $device = Device::where('tenant_id', $request->user()->tenant_id)->findOrFail($id);

        $event = $this->lifecycleService->retireDevice($device, $data['reason'], $request->user()->id);

        return ApiResponse::success($event->load('device'), __('app.device.device_retired'));
    }

    /**
     * 获取设备生命周期时间线
     */
    public function timeline(int $id, Request $request): JsonResponse
    {
        $device = Device::where('tenant_id', $request->user()->tenant_id)->findOrFail($id);

        return ApiResponse::success(
            $this->lifecycleService->buildTimeline($device)
        );
    }

    /**
     * 获取生命周期事件历史
     */
    public function lifecycleEvents(int $id, Request $request): JsonResponse
    {
        $device = Device::where('tenant_id', $request->user()->tenant_id)->findOrFail($id);

        $events = $device->lifecycleEvents()
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->paginate(min((int) $request->get('per_page', 20), 100));

        return ApiResponse::paginated($events);
    }
}
