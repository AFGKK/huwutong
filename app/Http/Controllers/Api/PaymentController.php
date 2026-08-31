<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 支付记录管理 (M1.1-27)
 *
 * payments 表的管理后台 API
 */
class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    /**
     * 支付仪表盘
     * GET /api/v1/admin/payments/dashboard
     */
    public function dashboard(): JsonResponse
    {
        return ApiResponse::success($this->paymentService->getDashboard(), __('app.payment.dashboard_retrieved'));
    }

    /**
     * 支付记录列表
     * GET /api/v1/admin/payments
     */
    public function index(Request $request): JsonResponse
    {
        $params = $request->only([
            'status', 'channel', 'search', 'date_from', 'date_to',
            'amount_min', 'amount_max', 'tenant_id', 'per_page', 'page',
        ]);
        return ApiResponse::success($this->paymentService->getPayments($params), __('app.payment.records_retrieved'));
    }

    /**
     * 支付记录详情
     * GET /api/v1/admin/payments/{payment}
     */
    public function show(Payment $payment): JsonResponse
    {
        $payment->load(['tenant', 'order', 'user', 'customer']);
        return ApiResponse::success($payment, __("app.payment.msg_f860d0a5"));
    }

    /**
     * 退款
     * POST /api/v1/admin/payments/{payment}/refund
     */
    public function refund(Request $request, Payment $payment): JsonResponse
    {
        $request->validate([
            'amount' => 'nullable|numeric|min:0.01',
        ]);

        $result = $this->paymentService->refund($payment->id, $request->float('amount'));

        if (!$result['success']) {
            return ApiResponse::success(null, $result['message'], false, 422);
        }

        return ApiResponse::success($result['data'] ?? null, $result['message']);
    }

    /**
     * 支付趋势
     * GET /api/v1/admin/payments/trend
     */
    public function trend(Request $request): JsonResponse
    {
        $days = min((int) $request->input('days', 30), 365);
        return ApiResponse::success($this->paymentService->getTrend($days), __('app.payment.trends_retrieved'));
    }
}
