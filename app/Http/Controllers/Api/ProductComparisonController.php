<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ProductComparisonService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductComparisonController extends Controller
{
    public function __construct(
        protected ProductComparisonService $comparisonService,
    ) {}

    // ─── 规格管理 ───

    /**
     * 获取商品规格
     */
    public function productSpecs(int $productId)
    {
        $product = Product::findOrFail($productId);
        return ApiResponse::success($this->comparisonService->getProductSpecs($productId));
    }

    /**
     * 创建规格分组
     */
    public function createSpecGroup(Request $request, int $productId)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'sort_order' => 'integer|min:0',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $group = $this->comparisonService->createSpecGroup(
            $productId,
            $request->input('name'),
            $request->integer('sort_order', 0),
        );

        return ApiResponse::success($group, 201, '规格分组已创建');
    }

    /**
     * 更新规格分组
     */
    public function updateSpecGroup(Request $request, int $groupId)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:100',
            'sort_order' => 'sometimes|integer|min:0',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $group = $this->comparisonService->updateSpecGroup($groupId, $request->only(['name', 'sort_order']));
        return ApiResponse::success($group, 200, '规格分组已更新');
    }

    /**
     * 删除规格分组
     */
    public function deleteSpecGroup(int $groupId)
    {
        $this->comparisonService->deleteSpecGroup($groupId);
        return ApiResponse::success(['message' => '规格分组已删除']);
    }

    /**
     * 创建规格项
     */
    public function createSpec(Request $request, int $groupId)
    {
        $validator = Validator::make($request->all(), [
            'label' => 'required|string|max:100',
            'type' => 'string|in:text,number,boolean,select',
            'unit' => 'nullable|string|max:30',
            'options' => 'nullable|array',
            'options.*' => 'string|max:100',
            'sort_order' => 'integer|min:0',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $spec = $this->comparisonService->createSpec($groupId, $request->only([
            'label', 'type', 'unit', 'options', 'sort_order',
        ]));

        return ApiResponse::success($spec, 201, '规格项已创建');
    }

    /**
     * 更新规格项
     */
    public function updateSpec(Request $request, int $specId)
    {
        $validator = Validator::make($request->all(), [
            'label' => 'sometimes|string|max:100',
            'type' => 'sometimes|string|in:text,number,boolean,select',
            'unit' => 'nullable|string|max:30',
            'options' => 'nullable|array',
            'options.*' => 'string|max:100',
            'sort_order' => 'sometimes|integer|min:0',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $spec = $this->comparisonService->updateSpec($specId, $request->only([
            'label', 'type', 'unit', 'options', 'sort_order',
        ]));

        return ApiResponse::success($spec, 200, '规格项已更新');
    }

    /**
     * 删除规格项
     */
    public function deleteSpec(int $specId)
    {
        $this->comparisonService->deleteSpec($specId);
        return ApiResponse::success(['message' => '规格项已删除']);
    }

    /**
     * 设置规格值
     */
    public function setSpecValue(Request $request, int $productId, int $specId)
    {
        $validator = Validator::make($request->all(), [
            'value' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $value = $this->comparisonService->setSpecValue(
            $productId,
            $specId,
            $request->input('value'),
        );

        return ApiResponse::success($value, 200, '规格值已设置');
    }

    // ─── 规格列表（管理端） ───

    /**
     * 管理端规格列表
     */
    public function adminSpecList(Request $request)
    {
        return ApiResponse::success($this->comparisonService->listAdminSpecs($request->only([
            'product_id', 'search', 'per_page',
        ])));
    }

    // ─── 商品比较 ───

    /**
     * 比较多个商品
     */
    public function compare(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_ids' => 'required|array|min:2|max:10',
            'product_ids.*' => 'integer|exists:products,id',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        return ApiResponse::success($this->comparisonService->compareProducts($request->input('product_ids')));
    }

    /**
     * 创建比较列表
     */
    public function createComparison(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_ids' => 'required|array|min:1|max:20',
            'product_ids.*' => 'integer|exists:products,id',
            'name' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $comparison = $this->comparisonService->createComparison(
            $request->user()?->id,
            $request->input('session_id'),
            $request->input('product_ids'),
            $request->input('name'),
        );

        return ApiResponse::success($comparison, 201, '比较列表已创建');
    }

    /**
     * 获取比较列表
     */
    public function showComparison(int $id)
    {
        $comparison = $this->comparisonService->getComparison($id);
        return ApiResponse::success($comparison);
    }

    /**
     * 获取用户的比较列表
     */
    public function myComparisons(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return ApiResponse::success([]);
        }
        return ApiResponse::success($this->comparisonService->getUserComparisons($user->id));
    }

    /**
     * 添加商品到比较列表
     */
    public function addItem(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|integer|exists:products,id',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $item = $this->comparisonService->addToComparison($id, $request->input('product_id'));
        return ApiResponse::success($item, 201, '已添加到比较列表');
    }

    /**
     * 从比较列表移除
     */
    public function removeItem(int $id, int $productId)
    {
        $this->comparisonService->removeFromComparison($id, $productId);
        return ApiResponse::success(['message' => '已从比较列表移除']);
    }

    /**
     * 删除比较列表
     */
    public function destroyComparison(int $id)
    {
        $this->comparisonService->deleteComparison($id);
        return ApiResponse::success(['message' => '比较列表已删除']);
    }
}
