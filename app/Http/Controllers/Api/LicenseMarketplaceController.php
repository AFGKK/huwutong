<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\LicenseListing;
use App\Models\LicenseTransaction;
use App\Models\LicenseDispute;
use App\Services\LicenseMarketplaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * License 二级市场控制器 (M3-81)
 */
class LicenseMarketplaceController extends Controller
{
    public function __construct(
        protected LicenseMarketplaceService $marketplace,
    ) {}

    /**
     * 仪表盘
     * GET /api/admin/license-marketplace/dashboard
     */
    public function dashboard(): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        return ApiResponse::success($this->marketplace->getDashboard($tenantId));
    }

    // ═══════ 挂牌管理 ═══════

    /**
     * 挂牌列表
     * GET /api/admin/license-marketplace/listings
     */
    public function listings(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $filters = $request->only(['status', 'search', 'seller_id']);
        return ApiResponse::success($this->marketplace->listListings($tenantId, $filters));
    }

    /**
     * 创建挂牌
     * POST /api/admin/license-marketplace/listings
     */
    public function storeListing(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'license_id' => 'required|exists:licenses,id',
            'seller_customer_id' => 'required|exists:customers,id',
            'price' => 'required|numeric|min:1|max:999999',
            'notes' => 'nullable|string|max:2000',
        ]);

        try {
            $listing = $this->marketplace->createListing(
                $tenantId,
                $validated['license_id'],
                $validated['seller_customer_id'],
                $validated['price'],
                $validated['notes'] ?? null,
            );
            return ApiResponse::created($listing, __("app.license_marketplace.msg_e67ad50c"));
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return ApiResponse::error('LISTING_FAILED', $e->getMessage(), 422);
        }
    }

    /**
     * 审核通过
     * POST /api/admin/license-marketplace/listings/{listing}/approve
     */
    public function approveListing(Request $request, LicenseListing $listing): JsonResponse
    {
        $listing = $this->marketplace->approveListing($listing->id, auth()->id(), $request->input('review_notes'));
        return ApiResponse::success($listing, __("app.license_marketplace.msg_aa0e1349"));
    }

    /**
     * 审核拒绝
     * POST /api/admin/license-marketplace/listings/{listing}/reject
     */
    public function rejectListing(Request $request, LicenseListing $listing): JsonResponse
    {
        $validated = $request->validate(['reason' => 'required|string|max:2000']);
        $listing = $this->marketplace->rejectListing($listing->id, auth()->id(), $validated['reason']);
        return ApiResponse::success($listing, __("app.license_marketplace.msg_d02aa1f1"));
    }

    /**
     * 取消挂牌
     * POST /api/admin/license-marketplace/listings/{listing}/cancel
     */
    public function cancelListing(LicenseListing $listing): JsonResponse
    {
        $listing = $this->marketplace->cancelListing($listing->id);
        return ApiResponse::success($listing, __("app.license_marketplace.msg_43ff4eb1"));
    }

    // ═══════ 交易管理 ═══════

    /**
     * 执行购买
     * POST /api/admin/license-marketplace/listings/{listing}/purchase
     */
    public function purchase(Request $request, LicenseListing $listing): JsonResponse
    {
        $validated = $request->validate([
            'buyer_customer_id' => 'required|exists:customers,id',
        ]);

        try {
            $transaction = $this->marketplace->executePurchase($listing->id, $validated['buyer_customer_id']);
            return ApiResponse::success($transaction, __("app.license_marketplace.msg_694acbcf"));
        } catch (\Throwable $e) {
            return ApiResponse::error('PURCHASE_FAILED', $e->getMessage(), 422);
        }
    }

    /**
     * 交易列表
     * GET /api/admin/license-marketplace/transactions
     */
    public function transactions(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;

        $query = LicenseTransaction::where('tenant_id', $tenantId)
            ->with(['listing.license:id,license_key', 'buyer:id,name'])
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = $request->input('per_page', 20);
        return ApiResponse::paginated($query->paginate($perPage)->withQueryString());
    }

    // ═══════ 纠纷管理 ═══════

    /**
     * 纠纷列表
     * GET /api/admin/license-marketplace/disputes
     */
    public function disputes(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;

        $query = LicenseDispute::whereHas('transaction.listing', fn($q) => $q->where('tenant_id', $tenantId))
            ->with(['transaction:id,transaction_id', 'raiser:id,name'])
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = $request->input('per_page', 20);
        return ApiResponse::paginated($query->paginate($perPage)->withQueryString());
    }

    /**
     * 解决纠纷
     * POST /api/admin/license-marketplace/disputes/{dispute}/resolve
     */
    public function resolveDispute(Request $request, LicenseDispute $dispute): JsonResponse
    {
        $validated = $request->validate([
            'resolution' => 'required|in:refund_buyer,partial_refund,uphold_seller,compromise',
            'notes' => 'required|string|max:2000',
        ]);

        $dispute = $this->marketplace->resolveDispute($dispute->id, auth()->id(), $validated['resolution'], $validated['notes']);
        return ApiResponse::success($dispute, __("app.license_marketplace.msg_c60133ff"));
    }

    // ═══════ 卖家评分 ═══════

    /**
     * 卖家信用分
     * GET /api/admin/license-marketplace/seller-score/{customerId}
     */
    public function sellerScore(int $customerId): JsonResponse
    {
        return ApiResponse::success($this->marketplace->getSellerScore($customerId));
    }
}
