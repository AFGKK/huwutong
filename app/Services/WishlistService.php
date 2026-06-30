<?php

namespace App\Services;

use App\Models\Product;
use App\Models\WishlistGroup;
use App\Models\WishlistItem;
use App\Models\WishlistShare;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WishlistService
{
    // ─── 分组管理 ───

    /**
     * 获取用户所有收藏分组及商品
     */
    public function getUserWishlists(int $userId): Collection
    {
        return WishlistGroup::with([
            'items' => fn($q) => $q->with('product:id,name,slug,is_active')->orderByDesc('priority')->latest(),
        ])
            ->where('user_id', $userId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * 创建收藏分组
     */
    public function createGroup(int $userId, string $name): WishlistGroup
    {
        $maxSort = WishlistGroup::where('user_id', $userId)->max('sort_order') ?? 0;
        return WishlistGroup::create([
            'user_id' => $userId,
            'name' => $name,
            'sort_order' => $maxSort + 1,
        ]);
    }

    /**
     * 更新收藏分组
     */
    public function updateGroup(int $groupId, array $data): WishlistGroup
    {
        $group = WishlistGroup::findOrFail($groupId);
        $group->update($data);
        return $group->fresh();
    }

    /**
     * 删除收藏分组（同时删除组内商品）
     */
    public function deleteGroup(int $groupId): void
    {
        DB::transaction(function () use ($groupId) {
            WishlistItem::where('group_id', $groupId)->delete();
            WishlistGroup::findOrFail($groupId)->delete();
        });
    }

    // ─── 商品收藏 ───

    /**
     * 添加商品到收藏
     */
    public function addItem(int $userId, int $productId, ?int $groupId = null, array $options = []): WishlistItem
    {
        $product = Product::findOrFail($productId);

        // 如果不指定分组，使用默认分组或创建
        if (!$groupId) {
            $groupId = $this->ensureDefaultGroup($userId);
        }

        return WishlistItem::firstOrCreate(
            ['user_id' => $userId, 'product_id' => $productId, 'group_id' => $groupId],
            [
                'note' => $options['note'] ?? null,
                'notify_on_sale' => $options['notify_on_sale'] ?? false,
                'notify_on_stock' => $options['notify_on_stock'] ?? false,
                'quantity' => $options['quantity'] ?? 1,
                'target_price' => $options['target_price'] ?? null,
                'priority' => $options['priority'] ?? 0,
            ],
        );
    }

    /**
     * 更新收藏项
     */
    public function updateItem(int $itemId, array $data): WishlistItem
    {
        $item = WishlistItem::findOrFail($itemId);
        $item->update($data);
        return $item->fresh()->load('product:id,name,slug');
    }

    /**
     * 移动收藏项到其他分组
     */
    public function moveItem(int $itemId, int $targetGroupId): WishlistItem
    {
        $item = WishlistItem::findOrFail($itemId);
        WishlistGroup::findOrFail($targetGroupId); // validate group exists
        $item->update(['group_id' => $targetGroupId]);
        return $item->fresh();
    }

    /**
     * 从收藏移除
     */
    public function removeItem(int $itemId): void
    {
        WishlistItem::findOrFail($itemId)->delete();
    }

    /**
     * 批量移除收藏
     */
    public function batchRemoveItems(array $itemIds): void
    {
        WishlistItem::whereIn('id', $itemIds)->delete();
    }

    /**
     * 切换收藏状态（收藏/取消收藏）
     */
    public function toggleItem(int $userId, int $productId, ?int $groupId = null): ?WishlistItem
    {
        $item = WishlistItem::where('user_id', $userId)
            ->where('product_id', $productId)
            ->first();

        if ($item) {
            $item->delete();
            return null;
        }

        return $this->addItem($userId, $productId, $groupId);
    }

    /**
     * 检查用户是否已收藏某商品
     */
    public function isWishlisted(int $userId, int $productId): bool
    {
        return WishlistItem::where('user_id', $userId)
            ->where('product_id', $productId)
            ->exists();
    }

    /**
     * 获取用户收藏的商品ID列表
     */
    public function getUserWishlistedProductIds(int $userId): array
    {
        return WishlistItem::where('user_id', $userId)
            ->pluck('product_id')
            ->toArray();
    }

    // ─── 收藏统计 ───

    /**
     * 获取用户的收藏统计
     */
    public function getUserStats(int $userId): array
    {
        $totalItems = WishlistItem::where('user_id', $userId)->count();
        $totalGroups = WishlistGroup::where('user_id', $userId)->count();
        $withSaleNotify = WishlistItem::where('user_id', $userId)->where('notify_on_sale', true)->count();
        $withStockNotify = WishlistItem::where('user_id', $userId)->where('notify_on_stock', true)->count();
        $highPriority = WishlistItem::where('user_id', $userId)->where('priority', '>=', 2)->count();

        return [
            'total_items' => $totalItems,
            'total_groups' => $totalGroups,
            'sale_notify' => $withSaleNotify,
            'stock_notify' => $withStockNotify,
            'high_priority' => $highPriority,
        ];
    }

    /**
     * 获取全局统计（管理端）
     */
    public function getGlobalStats(): array
    {
        return [
            'total_items' => WishlistItem::count(),
            'total_users' => WishlistItem::distinct('user_id')->count('user_id'),
            'total_groups' => WishlistGroup::count(),
            'total_shares' => WishlistShare::count(),
            'most_wishlisted_products' => WishlistItem::select('product_id')
                ->selectRaw('COUNT(*) as count')
                ->groupBy('product_id')
                ->orderByDesc('count')
                ->take(10)
                ->with('product:id,name,slug')
                ->get()
                ->map(fn($item) => [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product?->name,
                    'count' => $item->count,
                ]),
        ];
    }

    // ─── 收藏分享 ───

    /**
     * 创建分享链接
     */
    public function createShareLink(int $groupId, int $userId, array $options = []): WishlistShare
    {
        return WishlistShare::create([
            'wishlist_group_id' => $groupId,
            'user_id' => $userId,
            'share_token' => Str::random(32),
            'share_type' => $options['share_type'] ?? 'public',
            'shared_with' => $options['shared_with'] ?? [],
            'expires_at' => $options['expires_at'] ?? null,
        ]);
    }

    /**
     * 通过token获取分享的收藏
     */
    public function getSharedByToken(string $token)
    {
        $share = WishlistShare::with('group.items.product:id,name,slug,is_active')
            ->where('share_token', $token)
            ->firstOrFail();

        if ($share->expires_at && $share->expires_at->isPast()) {
            return null;
        }

        return $share;
    }

    /**
     * 删除分享
     */
    public function deleteShare(int $shareId): void
    {
        WishlistShare::findOrFail($shareId)->delete();
    }

    // ─── 内部方法 ───

    /**
     * 确保用户有默认收藏分组
     */
    protected function ensureDefaultGroup(int $userId): int
    {
        $default = WishlistGroup::where('user_id', $userId)
            ->orderBy('sort_order')
            ->first();

        if (!$default) {
            $default = $this->createGroup($userId, '默认收藏');
        }

        return $default->id;
    }
}
