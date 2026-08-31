<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\StagingEnvironment;
use App\Services\StagingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StagingController extends Controller
{
    public function __construct(
        protected StagingService $stagingService,
    ) {}

    /**
     * 获取当前租户的 Staging 环境
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;
        if (! $tenant) {
            return ApiResponse::error('NO_TENANT', __('app.api.staging.no_tenant'), 400);
        }

        $env = StagingEnvironment::where('tenant_id', $tenant->id)->first();

        if (! $env) {
            return ApiResponse::success(null);
        }

        return ApiResponse::success($this->stagingService->status($env));
    }

    /**
     * 创建 Staging 环境
     */
    public function create(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;
        if (! $tenant) {
            return ApiResponse::error('NO_TENANT', __('app.api.staging.no_tenant'), 400);
        }

        if ($tenant->has_staging) {
            return ApiResponse::error('STAGING_EXISTS', __('app.api.staging.staging_exists'), 400);
        }

        $validated = $request->validate([
            'name' => 'nullable|string|max:100',
            'rate_limit' => 'nullable|integer|min:30|max:600',
        ]);

        $env = $this->stagingService->create($tenant, $validated);

        return ApiResponse::success(
            $this->stagingService->status($env),
            __('app.api.staging.staging_created')
        );
    }

    /**
     * 获取环境详情
     */
    public function show(Request $request, StagingEnvironment $staging): JsonResponse
    {
        $tenant = $request->user()->tenant;
        if ($staging->tenant_id !== $tenant?->id) {
            return ApiResponse::error('FORBIDDEN', __('app.api.staging.forbidden'), 403);
        }

        return ApiResponse::success($this->stagingService->status($staging));
    }

    /**
     * 重置 Staging 环境
     */
    public function reset(Request $request, StagingEnvironment $staging): JsonResponse
    {
        $tenant = $request->user()->tenant;
        if ($staging->tenant_id !== $tenant?->id) {
            return ApiResponse::error('FORBIDDEN', __('app.api.staging.forbidden'), 403);
        }

        $success = $this->stagingService->reset($staging);
        if ($success) {
            return ApiResponse::success(
                $this->stagingService->status($staging),
                __('app.api.staging.staging_reset')
            );
        }

        return ApiResponse::error('RESET_FAILED', __('app.api.staging.reset_failed'), 500);
    }

    /**
     * 更新配置（限速等）
     */
    public function update(Request $request, StagingEnvironment $staging): JsonResponse
    {
        $tenant = $request->user()->tenant;
        if ($staging->tenant_id !== $tenant?->id) {
            return ApiResponse::error('FORBIDDEN', __('app.api.staging.forbidden'), 403);
        }

        $validated = $request->validate([
            'name' => 'nullable|string|max:100',
            'rate_limit' => 'nullable|integer|min:30|max:600',
            'status' => 'nullable|in:active,suspended',
        ]);

        if (isset($validated['name'])) {
            $staging->name = $validated['name'];
        }
        if (isset($validated['rate_limit'])) {
            $staging->rate_limit = (int) $validated['rate_limit'];
        }
        if (isset($validated['status'])) {
            $staging->status = $validated['status'];
        }
        $staging->save();

        return ApiResponse::success(
            $this->stagingService->status($staging),
            __('app.api.staging.config_updated')
        );
    }

    /**
     * 获取 Staging License 列表
     */
    public function licenses(Request $request, StagingEnvironment $staging): JsonResponse
    {
        $tenant = $request->user()->tenant;
        if ($staging->tenant_id !== $tenant?->id) {
            return ApiResponse::error('FORBIDDEN', __('app.api.staging.forbidden'), 403);
        }

        $licenses = $tenant->licenses()
            ->where('type', 'staging')
            ->with('product')
            ->orderBy('created_at')
            ->get()
            ->map(function ($l) {
                return [
                    'id' => $l->id,
                    'license_key' => $l->license_key,
                    'status' => $l->status,
                    'type' => $l->type,
                    'product_name' => $l->product?->name,
                    'expires_at' => $l->expires_at?->toIso8601String(),
                    'device_count' => $l->devices()->count(),
                    'max_devices' => $l->metadata['max_devices'] ?? 5,
                ];
            });

        return ApiResponse::success($licenses);
    }
}
