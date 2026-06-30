<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\FlashSale;
use App\Services\FlashSaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 🛒 秒杀/抢购防护控制器 (M2-159)
 */
class FlashSaleController extends Controller
{
    public function __construct(
        protected FlashSaleService $flashSale,
    ) {}

    /**
     * 仪表盘
     * GET /api/admin/flash-sale/dashboard
     */
    public function dashboard(): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        return ApiResponse::success($this->flashSale->getDashboard($tenantId));
    }

    /**
     * 活动列表
     * GET /api/admin/flash-sale/list
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $query = FlashSale::where('tenant_id', $tenantId)->with('sku')->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = $request->input('per_page', 20);
        return ApiResponse::paginated($query->paginate($perPage)->withQueryString());
    }

    /**
     * 创建活动
     * POST /api/admin/flash-sale/create
     */
    public function store(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'sku_id' => 'required|exists:product_skus,id',
            'name' => 'required|string|max:200',
            'flash_price' => 'required|integer|min:1',
            'original_price' => 'required|integer|min:1',
            'stock' => 'required|integer|min:1|max:99999',
            'max_per_user' => 'nullable|integer|min:1|max:100',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
        ]);

        $sale = $this->flashSale->createFlashSale($tenantId, $validated);
        return ApiResponse::created($sale->load('sku'), '秒杀活动已创建');
    }

    /**
     * 更新状态
     * POST /api/admin/flash-sale/{id}/status
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate(['status' => 'required|in:scheduled,active,paused,ended']);

        $sale = FlashSale::findOrFail($id);
        $sale->update(['status' => $validated['status']]);

        if ($validated['status'] === 'active') {
            $this->flashSale->preheatStock($sale);
        }

        return ApiResponse::success($sale->fresh(), '状态已更新');
    }

    /**
     * 释放过期预占
     * POST /api/admin/flash-sale/{id}/release-expired
     */
    public function releaseExpired(int $id): JsonResponse
    {
        $count = $this->flashSale->releaseExpiredReservations($id);
        return ApiResponse::success(['released' => $count], "已释放 {$count} 个过期预占");
    }
}
