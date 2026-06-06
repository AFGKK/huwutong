<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RenewalEscalation;
use App\Models\Subscription;
use App\Services\RenewalPipelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RetentionController extends Controller
{
    public function __construct(
        protected RenewalPipelineService $pipelineService,
    ) {}

    /**
     * 获取续费失败统计
     */
    public function failureStats(): JsonResponse
    {
        $this->authorize('viewAny', Subscription::class);

        return response()->json([
            'success' => true,
            'data' => $this->pipelineService->getFailureStats(),
        ]);
    }

    /**
     * 获取指定订阅的失败历史
     */
    public function subscriptionFailures(Subscription $subscription): JsonResponse
    {
        $this->authorize('view', $subscription);

        return response()->json([
            'success' => true,
            'data' => $this->pipelineService->getSubscriptionFailureHistory($subscription),
        ]);
    }

    /**
     * 手动触发重试
     */
    public function manualRetry(Subscription $subscription): JsonResponse
    {
        $this->authorize('update', $subscription);

        if ($subscription->status !== 'active' && $subscription->status !== 'grace') {
            return response()->json([
                'success' => false,
                'message' => '当前状态不允许重试续费',
            ], 422);
        }

        $billingService = app(\App\Services\BillingService::class);
        $result = $billingService->manualRenew($subscription);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => '续费重试成功',
            ]);
        }

        // 记录到流水线
        if (isset($result['invoice'])) {
            $this->pipelineService->handleRenewalFailure(
                $subscription,
                $result['invoice'],
                $result['error'] ?? 'manual_retry_failed'
            );
        }

        return response()->json([
            'success' => false,
            'message' => '续费重试失败: ' . ($result['error'] ?? '未知错误'),
        ], 502);
    }

    /**
     * 获取待处理的人工介入列表
     */
    public function pendingEscalations(): JsonResponse
    {
        $this->authorize('viewAny', Subscription::class);

        $escalations = RenewalEscalation::with(['subscription.customer:id,name'])
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $escalations,
        ]);
    }

    /**
     * 解决人工介入
     */
    public function resolveEscalation(Request $request, int $escalationId): JsonResponse
    {
        $this->authorize('update', Subscription::class);

        $validator = Validator::make($request->all(), [
            'resolution_note' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $result = $this->pipelineService->resolveEscalation(
            $escalationId,
            $request->input('resolution_note'),
        );

        return response()->json([
            'success' => $result,
            'message' => $result ? '已处理' : '操作失败',
        ]);
    }
}
