<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ProductSku;
use App\Models\SkuStockLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    /**
     * 库存快照（所有 SKU 的库存概览）
     */
    public function snapshot(Request $request): JsonResponse
    {
        $query = ProductSku::with('product:id,name')
            ->select('id', 'product_id', 'sku_code', 'name', 'stock', 'sold_count', 'low_stock_threshold', 'is_active');

        // 搜索
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('sku_code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        // 按产品筛选
        if ($request->filled('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }

        // 库存状态筛选
        if ($request->filled('stock_status')) {
            match ($request->input('stock_status')) {
                'low' => $query->where('stock', '>=', 0)->whereRaw('stock <= COALESCE(low_stock_threshold, 10)'),
                'out' => $query->where('stock', 0),
                'unlimited' => $query->where('stock', -1),
                default => null,
            };
        }

        $perPage = min((int) $request->input('per_page', 50), 200);
        $items = $query->orderBy('stock')->orderBy('sku_code')->paginate($perPage);

        return ApiResponse::paginated($items);
    }

    /**
     * 低库存告警列表
     */
    public function alerts(Request $request): JsonResponse
    {
        $threshold = (int) $request->input('threshold', 10);

        $items = ProductSku::with('product:id,name')
            ->where('is_active', true)
            ->where('stock', '>=', 0)
            ->whereRaw('stock <= COALESCE(low_stock_threshold, ?)', [$threshold])
            ->orderBy('stock')
            ->limit(50)
            ->get(['id', 'product_id', 'sku_code', 'name', 'stock', 'low_stock_threshold']);

        return ApiResponse::success($items);
    }

    /**
     * 库存变更日志
     */
    public function logs(int $skuId, Request $request): JsonResponse
    {
        $sku = ProductSku::findOrFail($skuId);

        $query = SkuStockLog::with('user:id,name')
            ->where('product_sku_id', $skuId);

        $perPage = min((int) $request->input('per_page', 50), 200);
        $logs = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // 转换格式：change → quantity, old_stock → stock_before, new_stock → stock_after, reason → remark
        $logs->getCollection()->transform(function ($log) {
            return [
                'id' => $log->id,
                'created_at' => $log->created_at,
                'type' => $log->change > 0 ? 'add' : ($log->change < 0 ? 'deduct' : 'manual'),
                'quantity' => abs($log->change),
                'stock_before' => $log->old_stock,
                'stock_after' => $log->new_stock,
                'remark' => $log->reason,
                'operator' => $log->user?->name,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $logs->items(),
            'meta' => [
                'total' => $logs->total(),
                'current_page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
            ],
        ]);
    }

    /**
     * 调整库存
     */
    public function adjust(int $skuId, Request $request): JsonResponse
    {
        $sku = ProductSku::findOrFail($skuId);

        $validated = $request->validate([
            'delta' => 'required|integer',
            'remark' => 'nullable|string|max:500',
        ]);

        $oldStock = $sku->stock;
        $newStock = $oldStock === -1 ? $oldStock : max(0, $oldStock + $validated['delta']);
        if ($newStock === $oldStock) {
            return ApiResponse::error('库存未发生变化');
        }

        $sku->update(['stock' => $newStock]);

        // 记录日志
        SkuStockLog::create([
            'product_sku_id' => $sku->id,
            'user_id' => auth()->id(),
            'change' => $newStock - $oldStock,
            'old_stock' => $oldStock,
            'new_stock' => $newStock,
            'reason' => $validated['remark'] ?? '库存调整',
        ]);

        return ApiResponse::success([
            'sku_code' => $sku->sku_code,
            'old_stock' => $oldStock,
            'new_stock' => $newStock,
            'delta' => $newStock - $oldStock,
        ], '库存已调整');
    }

    /**
     * 初始化库存（设置初始库存量）
     */
    public function initialize(int $skuId, Request $request): JsonResponse
    {
        $sku = ProductSku::findOrFail($skuId);

        $validated = $request->validate([
            'stock' => 'required|integer|min:-1',
            'remark' => 'nullable|string|max:500',
        ]);

        $oldStock = $sku->stock;
        $sku->update(['stock' => $validated['stock']]);

        SkuStockLog::create([
            'product_sku_id' => $sku->id,
            'user_id' => auth()->id(),
            'change' => $validated['stock'] - $oldStock,
            'old_stock' => $oldStock,
            'new_stock' => $validated['stock'],
            'reason' => $validated['remark'] ?? '库存初始化',
        ]);

        return ApiResponse::success([
            'sku_code' => $sku->sku_code,
            'old_stock' => $oldStock,
            'new_stock' => $validated['stock'],
        ], '库存已初始化');
    }
}
