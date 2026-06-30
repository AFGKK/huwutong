<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WishlistService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\ApiResponse;

class WishlistController extends Controller
{
    public function __construct(
        protected WishlistService $wishlistService,
    ) {}

    // ─── 用户收藏（需认证） ───

    /**
     * 获取我的收藏列表（含分组）
     */
    public function myWishlists(Request $request)
    {
        $userId = $request->user()->id;
        return ApiResponse::success($this->wishlistService->getUserWishlists($userId));
    }

    /**
     * 获取我的收藏统计
     */
    public function myStats(Request $request)
    {
        return ApiResponse::success($this->wishlistService->getUserStats($request->user()->id));
    }

    /**
     * 获取已收藏的商品ID列表
     */
    public function myWishlistedProductIds(Request $request)
    {
        return ApiResponse::success([
            'product_ids' => $this->wishlistService->getUserWishlistedProductIds($request->user()->id),
        ]);
    }

    /**
     * 检查是否已收藏
     */
    public function isWishlisted(Request $request, int $productId)
    {
        $isWishlisted = $this->wishlistService->isWishlisted($request->user()->id, $productId);
        return ApiResponse::success(['wishlisted' => $isWishlisted]);
    }

    /**
     * 切换收藏状态
     */
    public function toggle(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|integer|exists:products,id',
            'group_id' => 'nullable|integer|exists:wishlist_groups,id',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', '验证失败', 422, ['errors' => $validator->errors()]);
        }

        $item = $this->wishlistService->toggleItem(
            $request->user()->id,
            $request->input('product_id'),
            $request->input('group_id'),
        );

        if ($item) {
            return ApiResponse::success($item, '已添加到收藏');
        }

        return ApiResponse::success(['message' => '已取消收藏']);
    }

    /**
     * 添加到收藏
     */
    public function add(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|integer|exists:products,id',
            'group_id' => 'nullable|integer|exists:wishlist_groups,id',
            'note' => 'nullable|string|max:500',
            'notify_on_sale' => 'boolean',
            'notify_on_stock' => 'boolean',
            'quantity' => 'integer|min:1|max:999',
            'target_price' => 'nullable|numeric|min:0',
            'priority' => 'integer|in:0,1,2',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', '验证失败', 422, ['errors' => $validator->errors()]);
        }

        $item = $this->wishlistService->addItem(
            $request->user()->id,
            $request->input('product_id'),
            $request->input('group_id'),
            $validator->validated(),
        );

        return ApiResponse::success($item, '已添加到收藏');
    }

    /**
     * 更新收藏项
     */
    public function update(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'note' => 'nullable|string|max:500',
            'notify_on_sale' => 'boolean',
            'notify_on_stock' => 'boolean',
            'quantity' => 'integer|min:1|max:999',
            'target_price' => 'nullable|numeric|min:0',
            'priority' => 'integer|in:0,1,2',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', '验证失败', 422, ['errors' => $validator->errors()]);
        }

        $item = $this->wishlistService->updateItem($id, $validator->validated());
        return ApiResponse::success($item, '已更新');
    }

    /**
     * 移除收藏
     */
    public function remove(int $id)
    {
        $this->wishlistService->removeItem($id);
        return ApiResponse::success(['message' => '已移除收藏']);
    }

    /**
     * 批量移除
     */
    public function batchRemove(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_ids' => 'required|array|min:1|max:100',
            'item_ids.*' => 'integer|exists:wishlist_items,id',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', '验证失败', 422, ['errors' => $validator->errors()]);
        }

        $this->wishlistService->batchRemoveItems($request->input('item_ids'));
        return ApiResponse::success(['message' => '已批量移除']);
    }

    /**
     * 移动收藏到其他分组
     */
    public function move(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'group_id' => 'required|integer|exists:wishlist_groups,id',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', '验证失败', 422, ['errors' => $validator->errors()]);
        }

        $item = $this->wishlistService->moveItem($id, $request->input('group_id'));
        return ApiResponse::success($item, '已移动');
    }

    // ─── 分组管理 ───

    /**
     * 创建分组
     */
    public function createGroup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', '验证失败', 422, ['errors' => $validator->errors()]);
        }

        $group = $this->wishlistService->createGroup($request->user()->id, $request->input('name'));
        return ApiResponse::success($group, '分组已创建');
    }

    /**
     * 更新分组
     */
    public function updateGroup(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:100',
            'sort_order' => 'sometimes|integer|min:0',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', '验证失败', 422, ['errors' => $validator->errors()]);
        }

        $group = $this->wishlistService->updateGroup($id, $request->only(['name', 'sort_order']));
        return ApiResponse::success($group, '分组已更新');
    }

    /**
     * 删除分组
     */
    public function deleteGroup(int $id)
    {
        $this->wishlistService->deleteGroup($id);
        return ApiResponse::success(['message' => '分组已删除']);
    }

    // ─── 分享 ───

    /**
     * 创建分享链接
     */
    public function createShare(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'group_id' => 'required|integer|exists:wishlist_groups,id',
            'share_type' => 'string|in:public,private_link,email',
            'shared_with' => 'nullable|array',
            'shared_with.*' => 'email',
            'expires_at' => 'nullable|date|after:now',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', '验证失败', 422, ['errors' => $validator->errors()]);
        }

        $share = $this->wishlistService->createShareLink(
            $request->input('group_id'),
            $request->user()->id,
            $validator->validated(),
        );

        return ApiResponse::success($share, '分享链接已创建');
    }

    /**
     * 通过Token获取分享的收藏（公开）
     */
    public function sharedByToken(string $token)
    {
        $share = $this->wishlistService->getSharedByToken($token);

        if (!$share) {
            return ApiResponse::error('SHARE_EXPIRED', '分享链接已过期', 410);
        }

        return ApiResponse::success($share);
    }

    /**
     * 删除分享
     */
    public function deleteShare(int $id)
    {
        $this->wishlistService->deleteShare($id);
        return ApiResponse::success(['message' => '分享已删除']);
    }

    // ─── 管理端 ───

    /**
     * 全局收藏统计（管理端）
     */
    public function globalStats()
    {
        return ApiResponse::success($this->wishlistService->getGlobalStats());
    }
}
