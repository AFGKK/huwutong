<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MarketplaceRolloutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketplaceRolloutController extends Controller
{
    public function __construct(
        protected MarketplaceRolloutService $rolloutService
    ) {}

    /**
     * List rollouts.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $this->rolloutService->list($request->only(['app_id', 'status', 'search']), (int) $request->input('per_page', 20));
        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Get single rollout detail.
     */
    public function show(int $id): JsonResponse
    {
        $rollout = $this->rolloutService->show($id);
        return response()->json(['success' => true, 'data' => $rollout]);
    }

    /**
     * Create a new rollout.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'app_id' => 'required|exists:marketplace_apps,id',
            'version_id' => 'required|exists:marketplace_app_versions,id',
            'name' => 'required|string|max:200',
            'description' => 'nullable|string|max:2000',
            'rollout_type' => 'nullable|in:percentage,tenant_group,user_segment',
            'percentage' => 'nullable|integer|min:1|max:100',
            'target_filters' => 'nullable|array',
            'auto_rollback' => 'nullable|boolean',
            'error_threshold' => 'nullable|numeric|min:0|max:100',
            'tenant_ids' => 'nullable|array',
            'tenant_ids.*' => 'exists:tenants,id',
            'excluded_tenant_ids' => 'nullable|array',
            'excluded_tenant_ids.*' => 'exists:tenants,id',
        ]);

        $rollout = $this->rolloutService->create($validated, $request->user());
        return response()->json(['success' => true, 'data' => $rollout, 'message' => '灰度发布已创建'], 201);
    }

    /**
     * Update a rollout.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:200',
            'description' => 'nullable|string|max:2000',
            'percentage' => 'nullable|integer|min:1|max:100',
            'auto_rollback' => 'nullable|boolean',
            'error_threshold' => 'nullable|numeric|min:0|max:100',
            'tenant_ids' => 'nullable|array',
            'tenant_ids.*' => 'exists:tenants,id',
            'excluded_tenant_ids' => 'nullable|array',
            'excluded_tenant_ids.*' => 'exists:tenants,id',
        ]);

        $rollout = $this->rolloutService->update($id, $validated);
        return response()->json(['success' => true, 'data' => $rollout, 'message' => '灰度发布已更新']);
    }

    /**
     * Start a rollout.
     */
    public function start(int $id): JsonResponse
    {
        $rollout = $this->rolloutService->start($id);
        return response()->json(['success' => true, 'data' => $rollout, 'message' => '灰度发布已启动']);
    }

    /**
     * Pause a rollout.
     */
    public function pause(int $id): JsonResponse
    {
        $rollout = $this->rolloutService->pause($id);
        return response()->json(['success' => true, 'data' => $rollout, 'message' => '灰度发布已暂停']);
    }

    /**
     * Complete a rollout (promote to all).
     */
    public function complete(int $id): JsonResponse
    {
        $rollout = $this->rolloutService->complete($id);
        return response()->json(['success' => true, 'data' => $rollout, 'message' => '灰度发布已完成，版本已全量上线']);
    }

    /**
     * Rollback a rollout.
     */
    public function rollback(int $id, Request $request): JsonResponse
    {
        $rollout = $this->rolloutService->rollback($id, $request->user());
        return response()->json(['success' => true, 'data' => $rollout, 'message' => '灰度发布已回滚']);
    }

    /**
     * Get rollout statistics.
     */
    public function stats(int $id): JsonResponse
    {
        $stats = $this->rolloutService->stats($id);
        return response()->json(['success' => true, 'data' => $stats]);
    }

    /**
     * Get available apps with versions for selection.
     */
    public function availableApps(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->rolloutService->getAvailableApps()]);
    }

    /**
     * Search available tenants.
     */
    public function availableTenants(Request $request): JsonResponse
    {
        $search = $request->input('search', '');
        return response()->json(['success' => true, 'data' => $this->rolloutService->getAvailableTenants($search)]);
    }
}
