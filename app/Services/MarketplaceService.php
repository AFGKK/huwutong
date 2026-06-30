<?php

namespace App\Services;

use App\Models\MarketplaceApp;
use App\Models\MarketplaceAppReview;
use App\Models\MarketplaceAppReviewReply;
use App\Models\MarketplaceBanner;
use App\Models\MarketplaceCategory;
use App\Models\MarketplaceDownloadLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MarketplaceService
{
    /**
     * ─── 评价与评分 ───
     */

    /**
     * 获取应用的评价列表
     */
    public function getReviews(int $appId, array $filters = [], int $perPage = 20)
    {
        $query = MarketplaceAppReview::with(['user:id,name,avatar_url', 'replies.user:id,name'])
            ->byApp($appId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        } else {
            $query->approved();
        }

        if (!empty($filters['rating'])) {
            $query->where('rating', $filters['rating']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * 用户提交评价
     */
    public function submitReview(int $appId, int $userId, ?int $tenantId, array $data): MarketplaceAppReview
    {
        return DB::transaction(function () use ($appId, $userId, $tenantId, $data) {
            // 检查是否已评价
            $existing = MarketplaceAppReview::byApp($appId)->byUser($userId)->first();
            if ($existing) {
                throw new \InvalidArgumentException('您已评价过此应用');
            }

            $review = MarketplaceAppReview::create([
                'app_id' => $appId,
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'rating' => $data['rating'],
                'content' => $data['content'] ?? null,
                'status' => 'approved', // 默认直接通过，可改为 pending
            ]);

            // 更新应用平均评分
            $this->recalculateRating($appId);

            // 记录日志
            MarketplaceDownloadLog::create([
                'app_id' => $appId,
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'action' => 'review',
            ]);

            return $review->load('user:id,name,avatar_url');
        });
    }

    /**
     * 更新评价
     */
    public function updateReview(int $reviewId, int $userId, array $data): MarketplaceAppReview
    {
        $review = MarketplaceAppReview::findOrFail($reviewId);

        if ($review->user_id !== $userId) {
            throw new \InvalidArgumentException('无权修改此评价');
        }

        $review->update([
            'rating' => $data['rating'] ?? $review->rating,
            'content' => $data['content'] ?? $review->content,
            'status' => 'approved', // 修改后重新审核
        ]);

        $this->recalculateRating($review->app_id);

        return $review->fresh()->load('user:id,name,avatar_url');
    }

    /**
     * 删除评价
     */
    public function deleteReview(int $reviewId, int $userId): void
    {
        $review = MarketplaceAppReview::findOrFail($reviewId);

        if ($review->user_id !== $userId) {
            throw new \InvalidArgumentException('无权删除此评价');
        }

        $appId = $review->app_id;
        $review->delete();
        $this->recalculateRating($appId);
    }

    /**
     * 开发者回复评价
     */
    public function replyToReview(int $reviewId, int $userId, string $content): MarketplaceAppReviewReply
    {
        $review = MarketplaceAppReview::findOrFail($reviewId);

        return MarketplaceAppReviewReply::create([
            'review_id' => $reviewId,
            'user_id' => $userId,
            'content' => $content,
        ]);
    }

    /**
     * 审核评价（管理员）
     */
    public function reviewReview(int $reviewId, int $reviewerId, string $action): MarketplaceAppReview
    {
        $review = MarketplaceAppReview::findOrFail($reviewId);
        $review->update([
            'status' => $action === 'approve' ? 'approved' : 'rejected',
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ]);

        if ($action === 'approve') {
            $this->recalculateRating($review->app_id);
        }

        return $review->fresh();
    }

    /**
     * 重新计算应用评分
     */
    protected function recalculateRating(int $appId): void
    {
        $avg = MarketplaceAppReview::byApp($appId)->approved()->avg('rating');
        $count = MarketplaceAppReview::byApp($appId)->approved()->count();

        MarketplaceApp::where('id', $appId)->update([
            'avg_rating' => round($avg ?? 0, 1),
            'review_count' => $count,
        ]);
    }

    /**
     * 获取评价统计
     */
    public function getReviewStats(int $appId): array
    {
        $ratings = [];
        for ($i = 1; $i <= 5; $i++) {
            $ratings[$i] = MarketplaceAppReview::byApp($appId)->approved()->where('rating', $i)->count();
        }

        return [
            'total' => array_sum($ratings),
            'average' => MarketplaceApp::where('id', $appId)->value('avg_rating') ?? 0,
            'distribution' => $ratings,
        ];
    }

    /**
     * ─── 分类管理 ───
     */

    public function getCategories(bool $activeOnly = false)
    {
        $query = MarketplaceCategory::ordered();
        if ($activeOnly) {
            $query->active();
        }
        return $query->get();
    }

    public function createCategory(array $data): MarketplaceCategory
    {
        return MarketplaceCategory::create($data);
    }

    public function updateCategory(int $id, array $data): MarketplaceCategory
    {
        $cat = MarketplaceCategory::findOrFail($id);
        $cat->update($data);
        return $cat;
    }

    public function deleteCategory(int $id): void
    {
        MarketplaceCategory::findOrFail($id)->delete();
    }

    /**
     * ─── Banner/推荐位管理 ───
     */

    public function getActiveBanners()
    {
        return MarketplaceBanner::with('creator:id,name')
            ->active()
            ->ordered()
            ->get();
    }

    public function getBanners(array $filters = [], int $perPage = 20)
    {
        $query = MarketplaceBanner::with('creator:id,name')->ordered();

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        return $query->paginate($perPage);
    }

    public function createBanner(array $data): MarketplaceBanner
    {
        return MarketplaceBanner::create($data);
    }

    public function updateBanner(int $id, array $data): MarketplaceBanner
    {
        $banner = MarketplaceBanner::findOrFail($id);
        $banner->update($data);
        return $banner;
    }

    public function deleteBanner(int $id): void
    {
        MarketplaceBanner::findOrFail($id)->delete();
    }

    /**
     * ─── 统计与趋势 ───
     */

    public function getAppAnalytics(int $appId, string $period = '7d'): array
    {
        $days = match ($period) {
            '24h' => 1,
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            default => 7,
        };

        $since = now()->subDays($days);

        $dailyTrend = MarketplaceDownloadLog::byApp($appId)
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as date, action, COUNT(*) as total')
            ->groupBy('date', 'action')
            ->orderBy('date')
            ->get()
            ->groupBy('date');

        $summary = MarketplaceDownloadLog::byApp($appId)
            ->where('created_at', '>=', $since)
            ->selectRaw('action, COUNT(*) as total')
            ->groupBy('action')
            ->pluck('total', 'action');

        return [
            'period' => $period,
            'daily_trend' => $dailyTrend,
            'summary' => $summary,
            'total' => array_sum($summary->toArray()),
        ];
    }

    public function recordDownloadLog(int $appId, ?int $userId, ?int $tenantId, string $action, ?string $ip = null, ?string $ua = null): void
    {
        try {
            MarketplaceDownloadLog::create([
                'app_id' => $appId,
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'action' => $action,
                'ip_address' => $ip,
                'user_agent' => $ua,
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to record download log: ' . $e->getMessage());
        }
    }
}
