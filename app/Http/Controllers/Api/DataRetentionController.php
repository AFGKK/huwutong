<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\DataRetentionPolicy;
use App\Services\DataRetentionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DataRetentionController extends Controller
{
    public function __construct(
        protected DataRetentionService $retentionService
    ) {}

    /**
     * 仪表盘
     * GET /api/v1/admin/data-retention/dashboard
     */
    public function dashboard(): JsonResponse
    {
        return ApiResponse::success($this->retentionService->getDashboard(), '数据留存仪表盘获取成功');
    }

    /**
     * 策略列表
     * GET /api/v1/admin/data-retention/policies
     */
    public function policies(): JsonResponse
    {
        return ApiResponse::success($this->retentionService->getPolicies(), '策略列表获取成功');
    }

    /**
     * 更新策略
     * PUT /api/v1/admin/data-retention/policies/{id}
     */
    public function updatePolicy(Request $request, DataRetentionPolicy $dataRetentionPolicy): JsonResponse
    {
        $validated = $request->validate([
            'retention_days' => 'integer|min:1|max:36500',
            'action' => 'string|in:archive,delete,anonymize',
            'archive_enabled' => 'boolean',
            'archive_after_days' => 'nullable|integer|min:1',
            'archive_storage_tier' => 'string|in:hot,warm,cold,frozen,deep_frozen',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        $dataRetentionPolicy->update($validated);
        $this->retentionService->clearCache();

        return ApiResponse::success($dataRetentionPolicy->fresh(), '策略已更新');
    }

    /**
     * 同步策略
     * POST /api/v1/admin/data-retention/policies/sync
     */
    public function syncPolicies(): JsonResponse
    {
        $result = $this->retentionService->syncPoliciesFromConfig();

        return ApiResponse::success($result, $result['message']);
    }

    /**
     * 执行清理
     * POST /api/v1/admin/data-retention/cleanup
     */
    public function cleanup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'policy' => 'nullable|string',
            'dry_run' => 'boolean',
        ]);

        $result = $this->retentionService->cleanup(
            $validated['policy'] ?? null,
            $validated['dry_run'] ?? false
        );

        $message = ($validated['dry_run'] ?? false) ? '预览完成' : '清理完成';

        return ApiResponse::success($result, $message);
    }

    /**
     * 执行历史
     * GET /api/v1/admin/data-retention/executions
     */
    public function executions(Request $request): JsonResponse
    {
        $filters = $request->only(['policy_key', 'status', 'date_from', 'date_to', 'page', 'per_page']);

        return ApiResponse::success($this->retentionService->getExecutions($filters), '执行历史获取成功');
    }

    /**
     * 存储统计
     * GET /api/v1/admin/data-retention/storage-stats
     */
    public function storageStats(): JsonResponse
    {
        return ApiResponse::success($this->retentionService->getStorageStats(), '存储统计获取成功');
    }
}
