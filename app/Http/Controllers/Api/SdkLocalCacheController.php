<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SdkLocalCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * M2-17b SDK 在线验证本地缓存 + 离线宽限期 API
 */
class SdkLocalCacheController extends Controller
{
    public function __construct(
        private readonly SdkLocalCacheService $localCache,
    ) {}

    /**
     * 仪表盘
     */
    public function dashboard(): JsonResponse
    {
        return response()->json([
            'code' => 0,
            'data' => $this->localCache->getDashboard(),
        ]);
    }

    /**
     * SDK 上报缓存状态（公开，SDK调用）
     */
    public function reportStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sdk_instance_id' => 'required|string|max:64',
            'language' => 'nullable|string|max:20',
            'sdk_version' => 'nullable|string|max:20',
            'machine_id' => 'nullable|string|max:64',
            'license_key' => 'nullable|string|max:64',
            'cached_at' => 'nullable|date',
            'result' => 'nullable|string|max:20',
            'is_offline' => 'nullable|boolean',
        ]);

        $record = $this->localCache->reportCacheStatus($validated);

        return response()->json([
            'code' => 0,
            'data' => [
                'id' => $record->id,
                'expires_at' => $record->expires_at,
                'grace_expires_at' => $record->grace_expires_at,
            ],
        ]);
    }

    /**
     * SDK 查询缓存状态（公开，SDK调用）
     */
    public function checkStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sdk_instance_id' => 'required|string|max:64',
            'license_key' => 'required|string|max:64',
        ]);

        return response()->json([
            'code' => 0,
            'data' => $this->localCache->getCacheStatus($validated['sdk_instance_id'], $validated['license_key']),
        ]);
    }

    /**
     * SDK 获取缓存配置（公开，SDK启动时拉取）
     */
    public function getConfig(): JsonResponse
    {
        return response()->json([
            'code' => 0,
            'data' => $this->localCache->getCacheConfig(),
        ]);
    }

    /**
     * 缓存记录列表
     */
    public function records(Request $request): JsonResponse
    {
        $filters = $request->only(['sdk_instance_id', 'license_key', 'status', 'is_offline', 'language']);

        return response()->json([
            'code' => 0,
            'data' => $this->localCache->getRecords($filters, $request->input('per_page', 20)),
        ]);
    }

    /**
     * 失效指定 License 的缓存
     */
    public function invalidateByLicense(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'license_key' => 'required|string|max:64',
            'reason' => 'nullable|string|max:200',
        ]);

        $count = $this->localCache->invalidateByLicense(
            $validated['license_key'],
            $validated['reason'] ?? 'manual_invalidation',
            $request->user()?->id,
        );

        return response()->json([
            'code' => 0,
            'message' => "已失效 {$count} 条缓存记录",
            'data' => ['invalidated_count' => $count],
        ]);
    }

    /**
     * 失效指定 SDK 实例的缓存
     */
    public function invalidateByInstance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sdk_instance_id' => 'required|string|max:64',
            'reason' => 'nullable|string|max:200',
        ]);

        $count = $this->localCache->invalidateByInstance(
            $validated['sdk_instance_id'],
            $validated['reason'] ?? 'manual_invalidation',
            $request->user()?->id,
        );

        return response()->json([
            'code' => 0,
            'message' => "已失效 {$count} 条缓存记录",
            'data' => ['invalidated_count' => $count],
        ]);
    }

    /**
     * 失效日志列表
     */
    public function invalidationLogs(Request $request): JsonResponse
    {
        $filters = $request->only(['sdk_instance_id', 'license_key', 'trigger_type']);

        return response()->json([
            'code' => 0,
            'data' => $this->localCache->getInvalidationLogs($filters, $request->input('per_page', 20)),
        ]);
    }

    /**
     * 批量失效缓存
     */
    public function batchInvalidate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'language' => 'nullable|string|max:20',
            'sdk_version' => 'nullable|string|max:20',
            'offline_only' => 'nullable|boolean',
            'expired_only' => 'nullable|boolean',
            'reason' => 'required|string|max:200',
        ]);

        $result = $this->localCache->batchInvalidate(
            $validated,
            $validated['reason'],
            $request->user()?->id,
        );

        return response()->json([
            'code' => 0,
            'message' => "批量失效完成: {$result['total']} 条记录",
            'data' => $result,
        ]);
    }

    /**
     * 处理过期缓存记录
     */
    public function processExpired(): JsonResponse
    {
        $count = $this->localCache->processExpiredRecords();

        return response()->json([
            'code' => 0,
            'message' => "已处理 {$count} 条过期缓存",
            'data' => ['processed' => $count],
        ]);
    }

    /**
     * 标记缓存为篡改
     */
    public function markTampered(Request $request, int $id): JsonResponse
    {
        $result = $this->localCache->handleTamperedCache($id);

        return response()->json([
            'code' => 0,
            'message' => $result['message'],
            'data' => $result['record'],
        ]);
    }
}
