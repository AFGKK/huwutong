<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\MarketplaceApp;
use App\Models\MarketplaceBanner;
use App\Models\MarketplaceCategory;
use App\Services\MarketplaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketplaceController extends Controller
{
    protected MarketplaceService $marketplaceService;

    public function __construct(MarketplaceService $marketplaceService)
    {
        $this->marketplaceService = $marketplaceService;
    }

    // ══════════════════════════════════════════
    //  分类管理
    // ══════════════════════════════════════════

    public function categories(): JsonResponse
    {
        return ApiResponse::success($this->marketplaceService->getCategories());
    }

    public function categoryStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'slug' => 'required|string|max:50|unique:marketplace_categories,slug',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        return ApiResponse::created(
            $this->marketplaceService->createCategory($validated),
            '分类创建成功'
        );
    }

    public function categoryUpdate(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'slug' => 'sometimes|string|max:50|unique:marketplace_categories,slug,' . $id,
            'name' => 'sometimes|string|max:100',
            'description' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        return ApiResponse::success(
            $this->marketplaceService->updateCategory($id, $validated),
            '分类更新成功'
        );
    }

    public function categoryDestroy(int $id): JsonResponse
    {
        $this->marketplaceService->deleteCategory($id);
        return ApiResponse::success(null, '分类已删除');
    }

    // ══════════════════════════════════════════
    //  评价与评分
    // ══════════════════════════════════════════

    public function reviews(int $appId, Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 20), 100);
        return ApiResponse::paginated(
            $this->marketplaceService->getReviews($appId, $request->only(['status', 'rating']), $perPage)
        );
    }

    public function reviewStore(int $appId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'content' => 'nullable|string|max:2000',
        ]);

        try {
            $review = $this->marketplaceService->submitReview(
                $appId,
                $request->user()->id,
                $request->user()->tenant_id,
                $validated
            );
            return ApiResponse::created($review, '评价提交成功');
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error('ALREADY_REVIEWED', $e->getMessage(), 422);
        }
    }

    public function reviewUpdate(int $reviewId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rating' => 'sometimes|integer|min:1|max:5',
            'content' => 'nullable|string|max:2000',
        ]);

        try {
            return ApiResponse::success(
                $this->marketplaceService->updateReview($reviewId, $request->user()->id, $validated),
                '评价更新成功'
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error('FORBIDDEN', $e->getMessage(), 403);
        }
    }

    public function reviewDestroy(int $reviewId, Request $request): JsonResponse
    {
        try {
            $this->marketplaceService->deleteReview($reviewId, $request->user()->id);
            return ApiResponse::success(null, '评价已删除');
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error('FORBIDDEN', $e->getMessage(), 403);
        }
    }

    public function reviewReply(int $reviewId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string|max:2000',
        ]);

        return ApiResponse::created(
            $this->marketplaceService->replyToReview($reviewId, $request->user()->id, $validated['content']),
            '回复成功'
        );
    }

    public function reviewStats(int $appId): JsonResponse
    {
        return ApiResponse::success(
            $this->marketplaceService->getReviewStats($appId)
        );
    }

    public function reviewModerate(int $reviewId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
        ]);

        return ApiResponse::success(
            $this->marketplaceService->reviewReview($reviewId, $request->user()->id, $validated['action']),
            $validated['action'] === 'approve' ? '评价已通过' : '评价已拒绝'
        );
    }

    // ══════════════════════════════════════════
    //  Banner/推荐位
    // ══════════════════════════════════════════

    public function banners(): JsonResponse
    {
        return ApiResponse::success(
            $this->marketplaceService->getActiveBanners()
        );
    }

    public function bannersAdmin(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 20), 100);
        return ApiResponse::paginated(
            $this->marketplaceService->getBanners($request->only(['is_active']), $perPage)
        );
    }

    public function bannerStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:500',
            'image_url' => 'required|string|max:500',
            'link_type' => 'required|in:app,category,url',
            'link_value' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        return ApiResponse::created(
            $this->marketplaceService->createBanner(array_merge($validated, [
                'created_by' => $request->user()->id,
            ])),
            'Banner 创建成功'
        );
    }

    public function bannerUpdate(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'subtitle' => 'nullable|string|max:500',
            'image_url' => 'sometimes|string|max:500',
            'link_type' => 'sometimes|in:app,category,url',
            'link_value' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        return ApiResponse::success(
            $this->marketplaceService->updateBanner($id, $validated),
            'Banner 更新成功'
        );
    }

    public function bannerDestroy(int $id): JsonResponse
    {
        $this->marketplaceService->deleteBanner($id);
        return ApiResponse::success(null, 'Banner 已删除');
    }

    // ══════════════════════════════════════════
    //  统计与分析
    // ══════════════════════════════════════════

    public function analytics(int $appId, Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->marketplaceService->getAppAnalytics($appId, $request->input('period', '7d'))
        );
    }
}
