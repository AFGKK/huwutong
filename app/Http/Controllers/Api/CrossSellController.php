<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\CrossSellRecommendation;
use App\Services\CrossSellService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrossSellController extends Controller
{
    public function __construct(protected CrossSellService $service) {}

    /**
     * 为客户生成推荐
     */
    public function generate(Request $request): JsonResponse
    {
        $request->validate(['customer_id' => 'required|integer|exists:customers,id']);
        $recommendations = $this->service->generateRecommendations($request->customer_id);
        return ApiResponse::success($recommendations);
    }

    /**
     * 推荐列表
     */
    public function recommendations(Request $request): JsonResponse
    {
        $query = CrossSellRecommendation::with(['customer', 'recommendable'])
            ->where('tenant_id', $request->user()->tenant_id);

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return ApiResponse::paginated(
            $query->orderByDesc('score')
                ->paginate($request->input('per_page', 20))
        );
    }

    /**
     * 记录事件(shown/clicked/converted)
     */
    public function recordEvent(Request $request, CrossSellRecommendation $recommendation): JsonResponse
    {
        $validated = $request->validate([
            'event_type' => 'required|in:shown,clicked,converted,dismissed',
            'event_data' => 'nullable|array',
        ]);

        $this->service->recordEvent(
            $recommendation->id,
            $validated['event_type'],
            $validated['event_data'] ?? []
        );

        return ApiResponse::success(null, '事件已记录');
    }

    /**
     * 仪表盘统计
     */
    public function dashboard(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->service->getDashboard($request->user()->tenant_id)
        );
    }

    /**
     * 推荐详情
     */
    public function show(CrossSellRecommendation $recommendation): JsonResponse
    {
        $recommendation->load(['customer', 'recommendable', 'events']);
        return ApiResponse::success($recommendation);
    }
}
