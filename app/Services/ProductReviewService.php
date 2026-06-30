<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProductReviewService
{
    /**
     * 获取商品的评论列表（公开）
     */
    public function getProductReviews(int $productId, array $filters = []): LengthAwarePaginator
    {
        $query = ProductReview::with([
            'user:id,name,email,avatar',
        ])
            ->forProduct($productId)
            ->approved();

        // 按评分筛选
        if (!empty($filters['rating'])) {
            $query->withRating((int) $filters['rating']);
        }

        // 按标签筛选
        if (!empty($filters['tag'])) {
            $query->whereJsonContains('tags', $filters['tag']);
        }

        // 排序
        $sort = $filters['sort'] ?? 'latest';
        match ($sort) {
            'oldest' => $query->oldest(),
            'highest' => $query->orderByDesc('rating'),
            'lowest' => $query->orderBy('rating'),
            default => $query->latest(),
        };

        $perPage = (int) ($filters['per_page'] ?? 15);
        return $query->paginate(min($perPage, 50));
    }

    /**
     * 获取商品评分统计
     */
    public function getProductRatingStats(int $productId): array
    {
        $stats = ProductReview::forProduct($productId)
            ->approved()
            ->selectRaw('COUNT(*) as total, AVG(rating) as avg_rating')
            ->selectRaw('SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star')
            ->selectRaw('SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star')
            ->selectRaw('SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star')
            ->selectRaw('SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star')
            ->selectRaw('SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star')
            ->first();

        $total = (int) ($stats->total ?? 0);

        return [
            'total_reviews' => $total,
            'avg_rating' => $total > 0 ? round((float) $stats->avg_rating, 1) : 0,
            'distribution' => [
                '5' => (int) ($stats->five_star ?? 0),
                '4' => (int) ($stats->four_star ?? 0),
                '3' => (int) ($stats->three_star ?? 0),
                '2' => (int) ($stats->two_star ?? 0),
                '1' => (int) ($stats->one_star ?? 0),
            ],
            'percentages' => $total > 0 ? [
                '5' => round((int) ($stats->five_star ?? 0) / $total * 100),
                '4' => round((int) ($stats->four_star ?? 0) / $total * 100),
                '3' => round((int) ($stats->three_star ?? 0) / $total * 100),
                '2' => round((int) ($stats->two_star ?? 0) / $total * 100),
                '1' => round((int) ($stats->one_star ?? 0) / $total * 100),
            ] : [],
        ];
    }

    /**
     * 创建评论
     */
    public function createReview(array $data): ProductReview
    {
        return DB::transaction(function () use ($data) {
            $review = ProductReview::create([
                'product_id' => $data['product_id'],
                'customer_id' => $data['customer_id'] ?? null,
                'user_id' => $data['user_id'],
                'rating' => $data['rating'],
                'content' => $data['content'] ?? '',
                'images' => $data['images'] ?? [],
                'tags' => $data['tags'] ?? [],
                'status' => 'pending',
                'is_anonymous' => $data['is_anonymous'] ?? false,
                'is_verified_purchase' => $data['is_verified_purchase'] ?? true,
            ]);

            return $review->load(['user:id,name,email,avatar']);
        });
    }

    /**
     * 审核评论（审批/驳回）
     */
    public function moderateReview(int $reviewId, string $status, ?string $rejectReason = null, ?int $userId = null): ProductReview
    {
        $review = ProductReview::findOrFail($reviewId);

        if (!in_array($status, ['approved', 'rejected'])) {
            throw new \InvalidArgumentException('状态必须是 approved 或 rejected');
        }

        $data = ['status' => $status];
        if ($status === 'rejected') {
            $data['reject_reason'] = $rejectReason;
        }
        if ($status === 'approved' && $review->status !== 'approved') {
            // 审批通过后更新商品评分缓存
        }

        $review->update($data);
        return $review->fresh()->load(['user:id,name,email,avatar', 'product:id,name']);
    }

    /**
     * 商家回复评论
     */
    public function replyToReview(int $reviewId, string $reply, int $userId): ProductReview
    {
        $review = ProductReview::findOrFail($reviewId);

        $review->update([
            'admin_reply' => $reply,
            'reply_at' => now(),
            'replied_by' => $userId,
        ]);

        return $review->fresh()->load(['user:id,name,email,avatar', 'replier:id,name']);
    }

    /**
     * 管理端获取评论列表
     */
    public function listReviews(array $filters = []): LengthAwarePaginator
    {
        $query = ProductReview::with([
            'user:id,name,email,avatar',
            'product:id,name,slug',
            'replier:id,name',
        ]);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['product_id'])) {
            $query->where('product_id', (int) $filters['product_id']);
        }

        if (!empty($filters['rating'])) {
            $query->where('rating', (int) $filters['rating']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('content', 'like', "%{$search}%")
                  ->orWhereHas('user', fn($uq) => $uq->where('name', 'like', "%{$search}%"));
            });
        }

        $sort = $filters['sort'] ?? 'latest';
        match ($sort) {
            'oldest' => $query->oldest(),
            'rating_high' => $query->orderByDesc('rating'),
            'rating_low' => $query->orderBy('rating'),
            default => $query->latest(),
        };

        $perPage = (int) ($filters['per_page'] ?? 20);
        return $query->paginate(min($perPage, 100));
    }

    /**
     * 获取评论统计（管理端）
     */
    public function getStats(): array
    {
        $total = ProductReview::count();
        $pending = ProductReview::where('status', 'pending')->count();
        $approved = ProductReview::where('status', 'approved')->count();
        $rejected = ProductReview::where('status', 'rejected')->count();

        $avgRating = ProductReview::where('status', 'approved')->avg('rating');

        return [
            'total_reviews' => $total,
            'pending' => $pending,
            'approved' => $approved,
            'rejected' => $rejected,
            'avg_rating' => $total > 0 ? round((float) $avgRating, 1) : 0,
            'approval_rate' => $total > 0 ? round($approved / $total * 100, 1) : 0,
        ];
    }

    /**
     * 删除评论
     */
    public function deleteReview(int $reviewId): void
    {
        $review = ProductReview::findOrFail($reviewId);
        $review->delete();
    }
}
