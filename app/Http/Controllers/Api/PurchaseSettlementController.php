<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\ContentPurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 内容付费结算管理（管理后台）
 *
 * 管理员可在后台手动确认 pending 的金额支付，
 * 将收益转入作者的 EarningsAccount。
 */
class PurchaseSettlementController extends Controller
{
    public function __construct(
        protected ContentPurchaseService $settlementService,
    ) {}

    /**
     * 确认 OA 文章购买（金额支付）
     */
    public function confirmOa(int $purchaseId): JsonResponse
    {
        try {
            $result = $this->settlementService->confirmOaPurchase($purchaseId);
            if ($result['success']) {
                return ApiResponse::success($result, $result['message']);
            }
            return ApiResponse::error('SETTLEMENT_FAILED', $result['message'], 400);
        } catch (\Throwable $e) {
            return ApiResponse::error('SETTLEMENT_ERROR', $e->getMessage(), 500);
        }
    }

    /**
     * 确认社区帖子购买（金额支付）
     */
    public function confirmForum(int $purchaseId): JsonResponse
    {
        try {
            $result = $this->settlementService->confirmForumPurchase($purchaseId);
            if ($result['success']) {
                return ApiResponse::success($result, $result['message']);
            }
            return ApiResponse::error('SETTLEMENT_FAILED', $result['message'], 400);
        } catch (\Throwable $e) {
            return ApiResponse::error('SETTLEMENT_ERROR', $e->getMessage(), 500);
        }
    }

    /**
     * 批量结算所有待处理的金额支付
     */
    public function settleAll(): JsonResponse
    {
        try {
            $results = $this->settlementService->settleAllPending();
            $msg = "OA: {$results['oa']} 笔, 论坛: {$results['forum']} 笔";
            if ($results['errors']) {
                $msg .= ', 错误: ' . implode('; ', $results['errors']);
            }
            return ApiResponse::success($results, $msg);
        } catch (\Throwable $e) {
            return ApiResponse::error('BATCH_SETTLEMENT_ERROR', $e->getMessage(), 500);
        }
    }
}
