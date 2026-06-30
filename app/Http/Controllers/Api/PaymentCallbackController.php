<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\PaymentCallbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 支付回调处理中心 (M2-144 🛒)
 */
class PaymentCallbackController extends Controller
{
    public function __construct(protected PaymentCallbackService $callbackService) {}

    /**
     * 接收支付回调（公开端点，由支付网关调用）
     */
    public function receive(Request $request, string $gateway): JsonResponse
    {
        $payload = $request->all();
        $result = $this->callbackService->handle($payload, $gateway);

        if ($result['success']) {
            return response()->json(['code' => 'SUCCESS', 'message' => 'ok']);
        }

        // 回调失败也要返回200给网关（避免重试风暴），但记录错误
        Log::channel('payment')->error('支付回调处理失败', [
            'gateway' => $gateway,
            'error' => $result['error'] ?? 'unknown',
        ]);

        return response()->json(['code' => 'FAIL', 'message' => $result['error'] ?? '处理失败']);
    }

    // ─── 以下为管理端点（需认证） ───

    /**
     * 统计
     */
    public function stats(): JsonResponse
    {
        return ApiResponse::success($this->callbackService->getStats());
    }

    /**
     * 回调列表
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->callbackService->getCallbacks($request->all())
        );
    }

    /**
     * 重试失败回调
     */
    public function retry(Request $request, int $id): JsonResponse
    {
        $result = $this->callbackService->retry($id);
        if ($result['success']) {
            return ApiResponse::success($result, '重试成功');
        }
        return ApiResponse::error('RETRY_FAILED', $result['message'] ?? '重试失败', 400);
    }

    /**
     * 批量重试失败回调
     */
    public function batchRetry(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:payment_callbacks,id',
        ]);

        $results = [];
        foreach ($validated['ids'] as $id) {
            $results[] = [
                'callback_id' => $id,
                'result' => $this->callbackService->retry($id),
            ];
        }

        return ApiResponse::success($results, '批量重试完成');
    }

    /**
     * 模拟回调（开发测试用）
     */
    public function simulate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'gateway' => 'required|string|in:mock,stripe,alipay,wechat,paypal',
            'event_type' => 'required|string|in:payment_success,payment_failed,refund,chargeback',
            'order_id' => 'required|integer|exists:orders,id',
            'amount' => 'nullable|numeric',
            'transaction_id' => 'nullable|string',
        ]);

        $payload = [
            'event_id' => 'sim_' . uniqid(),
            'event_type' => $validated['event_type'],
            'order_id' => $validated['order_id'],
            'transaction_id' => $validated['transaction_id'] ?? 'sim_txn_' . uniqid(),
            'merchant_order_no' => \App\Models\Order::find($validated['order_id'])?->order_no,
            'amount' => $validated['amount'],
            'currency' => 'CNY',
        ];

        $result = $this->callbackService->handle($payload, $validated['gateway']);

        if ($result['success']) {
            return ApiResponse::success($result, '模拟回调处理完成');
        }
        return ApiResponse::error('SIMULATION_FAILED', $result['error'] ?? '模拟失败', 500);
    }
}
