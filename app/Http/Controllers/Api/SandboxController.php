<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\SandboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SandboxController extends Controller
{
    public function __construct(
        protected SandboxService $sandboxService,
    ) {}

    /**
     * 创建沙箱环境（注册后调用）
     */
    public function create(Request $request): JsonResponse
    {
        $user = $request->user();

        $tenant = $this->sandboxService->create($user);

        return ApiResponse::success([
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
            'message' => __('app.sandbox.msg_5_license'),
            'sandbox_info' => $this->sandboxService->status($tenant),
        ]);
    }

    /**
     * 获取沙箱状态
     */
    public function status(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        if (! $tenant || ! $tenant->is_sandbox) {
            return ApiResponse::error('NOT_SANDBOX', __("app.sandbox.msg_f35bf9d9"), 400);
        }

        return ApiResponse::success($this->sandboxService->status($tenant));
    }

    /**
     * 重置沙箱
     */
    public function reset(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        if (! $tenant || ! $tenant->is_sandbox) {
            return ApiResponse::error('NOT_SANDBOX', __('app.sandbox.not_sandbox'), 400);
        }

        $success = $this->sandboxService->reset($tenant);

        if ($success) {
            return ApiResponse::success([
                'tenant_id' => $tenant->id,
                'reset_at' => now()->toIso8601String(),
            ], __('app.sandbox.sandbox_reset'));
        }

        return ApiResponse::error('RESET_FAILED', __("app.sandbox.msg_4d713822"), 500);
    }

    /**
     * 获取沙箱 License 列表
     */
    public function licenses(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        if (! $tenant || ! $tenant->is_sandbox) {
            return ApiResponse::error('NOT_SANDBOX', __('app.sandbox.not_sandbox'), 400);
        }

        $licenses = $tenant->licenses()
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
                ];
            });

        return ApiResponse::success($licenses);
    }
}
