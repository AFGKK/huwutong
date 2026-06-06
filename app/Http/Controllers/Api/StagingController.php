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
            return ApiResponse::error('NO_TENANT', '未关联租户', 400);
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
            return ApiResponse::error('NO_TENANT', '未关联租户', 400);
        }

        if ($tenant->has_staging) {
            return ApiResponse::error('STAGING_EXISTS', 'Staging 环境已存在', 400);
        }

        $validated = $request->validate([
            'name' => 'nullable|string|max:100',
            'rate_limit' => 'nullable|integer|min:30|max:600',
        ]);

        $env = $this->stagingService->create($tenant, $validated);

        return ApiResponse::success(
            $this->stagingService->status($env),
            'Staging 环境创建成功！已分配独立子域名和 10 个测试 License。'
        );
    }

    /**
     * 获取环境详情
     */
    public function show(Request $request, StagingEnvironment $staging): JsonResponse
    {
        $tenant = $request->user()->tenant;
        if ($staging->tenant_id !== $tenant?->id) {
            return ApiResponse::error('FORBIDDEN', '无权访问', 403);
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
            return ApiResponse::error('FORBIDDEN', '无权访问', 403);
        }

        $success = $this->stagingService->reset($staging);
        if ($success) {
            return ApiResponse::success(
                $this->stagingService->status($staging),
                'Staging 环境已重置，所有设备绑定和激活记录已清除'
            );
        }

        return ApiResponse::error('RESET_FAILED', '重置失败', 500);
    }

    /**
     * 更新配置（限速等）
     */
    public function update(Request $request, StagingEnvironment $staging): JsonResponse
    {
        $tenant = $request->user()->tenant;
        if ($staging->tenant_id !== $tenant?->id) {
            return ApiResponse::error('FORBIDDEN', '无权访问', 403);
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
            '配置已更新'
        );
    }

    /**
     * 获取 Staging License 列表
     */
    public function licenses(Request $request, StagingEnvironment $staging): JsonResponse
    {
        $tenant = $request->user()->tenant;
        if ($staging->tenant_id !== $tenant?->id) {
            return ApiResponse::error('FORBIDDEN', '无权访问', 403);
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
