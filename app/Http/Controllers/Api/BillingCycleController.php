<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\BillingCycle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingCycleController extends Controller
{
    /**
     * 列表（管理用）
     */
    public function index(): JsonResponse
    {
        $cycles = BillingCycle::orderBy('sort_order')->get();
        return ApiResponse::success($cycles);
    }

    /**
     * 选项列表（前端下拉用）
     */
    public function options(): JsonResponse
    {
        return ApiResponse::success(BillingCycle::options());
    }

    /**
     * 创建
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:30|unique:billing_cycles,code',
            'name' => 'required|string|max:50',
            'months' => 'nullable|integer|min:1',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['sort_order'] ??= 0;
        $validated['is_active'] ??= true;

        $cycle = BillingCycle::create($validated);
        return ApiResponse::success($cycle, '计费周期已创建', 201);
    }

    /**
     * 更新
     */
    public function update(int $id, Request $request): JsonResponse
    {
        $cycle = BillingCycle::findOrFail($id);

        $validated = $request->validate([
            'code' => 'sometimes|string|max:30|unique:billing_cycles,code,' . $id,
            'name' => 'sometimes|string|max:50',
            'months' => 'nullable|integer|min:1',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $cycle->update($validated);
        return ApiResponse::success($cycle->fresh(), '计费周期已更新');
    }

    /**
     * 删除
     */
    public function destroy(int $id): JsonResponse
    {
        $cycle = BillingCycle::findOrFail($id);

        // 检查是否有 SKU 使用此周期
        $usageCount = \App\Models\ProductSku::where('billing_cycle', $cycle->code)->count();
        if ($usageCount > 0) {
            return ApiResponse::error("有 {$usageCount} 个 SKU 使用此计费周期，无法删除");
        }

        $cycle->delete();
        return ApiResponse::success(null, '计费周期已删除');
    }
}
