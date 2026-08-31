<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\ResaleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ResaleController extends Controller
{
    public function __construct(
        protected ResaleService $resaleService
    ) {}

    // ─── 挂牌管理 ───

    /**
     * 创建挂牌
     */
    public function createListing(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'license_id' => 'required|integer|exists:licenses,id',
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:5000',
            'asking_price' => 'required|numeric|min:0.01',
            'currency' => 'nullable|string|size:3',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'expires_at' => 'nullable|date|after:today',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.resale.validation_failed'), $validator->errors()->toArray());
        }

        try {
            $listing = $this->resaleService->createListing(
                $request->user()->tenant_id,
                $request->input('license_id'),
                $request->user()->customer_id ?? $request->input('seller_customer_id'),
                $validator->validated(),
            );
            return ApiResponse::success($listing, __('app.api.resale.listing_created'));
        } catch (\RuntimeException $e) {
            return ApiResponse::error('CREATE_FAILED', $e->getMessage(), 400);
        }
    }

    /**
     * 更新挂牌
     */
    public function updateListing(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:200',
            'description' => 'nullable|string|max:5000',
            'asking_price' => 'nullable|numeric|min:0.01',
            'currency' => 'nullable|string|size:3',
            'expires_at' => 'nullable|date|after:today',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.resale.validation_failed'), $validator->errors()->toArray());
        }

        try {
            $listing = $this->resaleService->updateListing($id, $validator->validated());
            return ApiResponse::success($listing, __('app.api.resale.listing_updated'));
        } catch (\RuntimeException $e) {
            return ApiResponse::error('UPDATE_FAILED', $e->getMessage(), 400);
        }
    }

    /**
     * 发布挂牌
     */
    public function publishListing(int $id)
    {
        try {
            $listing = $this->resaleService->publishListing($id);
            return ApiResponse::success($listing, __('app.api.resale.listing_submitted'));
        } catch (\RuntimeException $e) {
            return ApiResponse::error('PUBLISH_FAILED', $e->getMessage(), 400);
        }
    }

    /**
     * 审核挂牌
     */
    public function reviewListing(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|string|in:approve,reject',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.resale.validation_failed'), $validator->errors()->toArray());
        }

        try {
            $listing = $this->resaleService->reviewListing(
                $id,
                $request->user()->id,
                $request->input('action'),
                $request->input('notes'),
            );
            $msg = $request->input('action') === 'approve' ? __('app.api.resale.listing_approved') : __('app.api.resale.listing_rejected');
            return ApiResponse::success($listing, $msg);
        } catch (\RuntimeException $e) {
            return ApiResponse::error('REVIEW_FAILED', $e->getMessage(), 400);
        }
    }

    /**
     * 取消挂牌
     */
    public function cancelListing(int $id)
    {
        try {
            $listing = $this->resaleService->cancelListing($id);
            return ApiResponse::success($listing, __('app.api.resale.listing_cancelled'));
        } catch (\RuntimeException $e) {
            return ApiResponse::error('CANCEL_FAILED', $e->getMessage(), 400);
        }
    }

    // ─── 市场浏览 ───

    /**
     * 浏览二级市场
     */
    public function browseMarketplace(Request $request)
    {
        $result = $this->resaleService->browseMarketplace(
            $request->user()->tenant_id,
            $request->only(['search', 'min_price', 'max_price', 'currency', 'product_id', 'sort', 'order']),
            $request->input('per_page', 20),
        );

        return ApiResponse::success($result);
    }

    /**
     * 获取挂牌详情
     */
    public function getListingDetail(int $id)
    {
        try {
            $listing = $this->resaleService->getListingDetail($id);
            return ApiResponse::success($listing);
        } catch (\Exception $e) {
            return ApiResponse::error('NOT_FOUND', __('app.api.resale.listing_not_found'), 404);
        }
    }

    /**
     * 获取卖家的挂牌列表
     */
    public function getSellerListings(Request $request)
    {
        $result = $this->resaleService->getSellerListings(
            $request->user()->tenant_id,
            $request->user()->customer_id ?? $request->input('customer_id'),
            $request->input('status'),
            $request->input('per_page', 20),
        );

        return ApiResponse::success($result);
    }

    // ─── 交易流程 ───

    /**
     * 购买挂牌
     */
    public function purchaseListing(Request $request, int $listingId)
    {
        try {
            $transaction = $this->resaleService->purchaseListing(
                $listingId,
                $request->user()->customer_id ?? $request->input('buyer_customer_id'),
            );
            return ApiResponse::success($transaction, __('app.api.resale.transaction_created'));
        } catch (\RuntimeException $e) {
            return ApiResponse::error('PURCHASE_FAILED', $e->getMessage(), 400);
        }
    }

    /**
     * 确认付款
     */
    public function confirmPayment(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'payment_method' => 'required|string|max:50',
            'payment_reference' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.resale.validation_failed'), $validator->errors()->toArray());
        }

        try {
            $transaction = $this->resaleService->confirmPayment(
                $id,
                $request->input('payment_method'),
                $request->input('payment_reference'),
            );
            return ApiResponse::success($transaction, __('app.api.resale.payment_confirmed'));
        } catch (\RuntimeException $e) {
            return ApiResponse::error('PAYMENT_FAILED', $e->getMessage(), 400);
        }
    }

    /**
     * 卖家确认交易
     */
    public function sellerConfirm(Request $request, int $id)
    {
        try {
            $transaction = $this->resaleService->sellerConfirm($id, $request->user()->id);
            return ApiResponse::success($transaction, __('app.api.resale.seller_confirmed'));
        } catch (\RuntimeException $e) {
            return ApiResponse::error('CONFIRM_FAILED', $e->getMessage(), 400);
        }
    }

    /**
     * 执行 License 转移
     */
    public function executeTransfer(Request $request, int $id)
    {
        try {
            $transaction = $this->resaleService->executeTransfer($id, $request->user()->id);
            return ApiResponse::success($transaction, __('app.api.resale.ownership_transferred'));
        } catch (\RuntimeException $e) {
            return ApiResponse::error('TRANSFER_FAILED', $e->getMessage(), 400);
        }
    }

    /**
     * 取消交易
     */
    public function cancelTransaction(Request $request, int $id)
    {
        try {
            $transaction = $this->resaleService->cancelTransaction($id, $request->user()->id);
            return ApiResponse::success($transaction, __('app.api.resale.transaction_cancelled'));
        } catch (\RuntimeException $e) {
            return ApiResponse::error('CANCEL_FAILED', $e->getMessage(), 400);
        }
    }

    // ─── 辅助功能 ───

    /**
     * 获取可挂牌的 License
     */
    public function getSellableLicenses(Request $request)
    {
        $licenses = $this->resaleService->getSellableLicenses(
            $request->user()->tenant_id,
            $request->user()->customer_id ?? $request->input('customer_id'),
        );

        return ApiResponse::success($licenses);
    }

    // ─── 统计 ───

    /**
     * 市场统计数据
     */
    public function marketStats(Request $request)
    {
        $stats = $this->resaleService->getMarketStats($request->user()->tenant_id);
        return ApiResponse::success($stats);
    }

    /**
     * 卖家统计数据
     */
    public function sellerStats(Request $request)
    {
        $stats = $this->resaleService->getSellerStats(
            $request->user()->tenant_id,
            $request->user()->customer_id ?? $request->input('customer_id'),
        );
        return ApiResponse::success($stats);
    }
}
