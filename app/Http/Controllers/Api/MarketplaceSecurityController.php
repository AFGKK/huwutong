<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\MarketplaceApp;
use App\Models\MarketplaceAppReview;
use App\Services\MarketplaceContentSecurityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketplaceSecurityController extends Controller
{
    protected MarketplaceContentSecurityService $securityService;

    public function __construct(MarketplaceContentSecurityService $securityService)
    {
        $this->securityService = $securityService;
    }

    public function stats(): JsonResponse
    {
        return ApiResponse::success($this->securityService->getSecurityStats());
    }

    public function scanApp(int $appId): JsonResponse
    {
        $app = MarketplaceApp::findOrFail($appId);
        return ApiResponse::success([
            'app_id' => $app->id,
            'app_name' => $app->name,
            'violations' => $this->securityService->scanApp($app),
        ]);
    }

    public function scanReview(int $reviewId): JsonResponse
    {
        $review = MarketplaceAppReview::with('app:id,name')->findOrFail($reviewId);
        return ApiResponse::success([
            'review_id' => $review->id,
            'app_name' => $review->app->name ?? '-',
            'violations' => $this->securityService->scanReview($review),
        ]);
    }

    public function scanAllApps(): JsonResponse
    {
        $results = $this->securityService->scanAllApps();
        return ApiResponse::success([
            'total_flagged' => count($results),
            'results' => $results,
        ]);
    }

    public function scanAllReviews(): JsonResponse
    {
        $results = $this->securityService->scanAllPendingReviews();
        return ApiResponse::success([
            'total_flagged' => count($results),
            'results' => $results,
        ]);
    }
}
