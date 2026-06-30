<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\HoneypotLicense;
use App\Services\HoneypotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HoneypotController extends Controller
{
    public function __construct(protected HoneypotService $service) {}

    /**
     * 仪表盘统计
     */
    public function dashboard(): JsonResponse
    {
        return ApiResponse::success($this->service->dashboard());
    }

    /**
     * 蜜罐 License 列表
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::paginated($this->service->list($request));
    }

    /**
     * 生成单个蜜罐 License
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'label' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
            'count' => 'nullable|integer|min:1|max:100',
        ]);

        if (isset($validated['count']) && $validated['count'] > 1) {
            $results = $this->service->generateBatch((int) $validated['count'], $validated['label'] ?? null);
            return ApiResponse::success($results, 201);
        }

        $honeypot = $this->service->generate($validated);
        return ApiResponse::success($honeypot, 201);
    }

    /**
     * 蜜罐详情
     */
    public function show(HoneypotLicense $honeypotLicense): JsonResponse
    {
        return ApiResponse::success($honeypotLicense);
    }

    /**
     * 禁用蜜罐
     */
    public function disable(HoneypotLicense $honeypotLicense): JsonResponse
    {
        $this->service->disable($honeypotLicense);
        return ApiResponse::success($honeypotLicense);
    }

    /**
     * 重新激活蜜罐
     */
    public function reactivate(HoneypotLicense $honeypotLicense): JsonResponse
    {
        $this->service->reactivate($honeypotLicense);
        return ApiResponse::success($honeypotLicense);
    }

    /**
     * 删除蜜罐
     */
    public function destroy(HoneypotLicense $honeypotLicense): JsonResponse
    {
        $this->service->delete($honeypotLicense);
        return ApiResponse::success(null, 204);
    }
}
