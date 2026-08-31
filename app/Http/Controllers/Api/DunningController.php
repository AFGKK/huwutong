<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\DunningQueue;
use App\Models\DunningStrategy;
use App\Models\DunningLog;
use App\Models\Invoice;
use App\Services\DunningEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DunningController extends Controller
{
    public function __construct(
        protected DunningEngineService $dunningEngine,
    ) {}

    /**
     * 催缴看板数据
     *
     * GET /api/dunning/dashboard
     */
    public function dashboard(): JsonResponse
    {
        $data = $this->dunningEngine->getDashboardData();

        return ApiResponse::success($data);
    }

    /**
     * 催缴队列列表
     *
     * GET /api/dunning/queue?status=&subscription_id=&customer_id=&page=
     */
    public function queue(Request $request): JsonResponse
    {
        $query = DunningQueue::with([
            'subscription:id,plan,status,price',
            'customer:id,name',
            'customer.user:id,name,email',
            'invoice:id,invoice_no,amount,due_at,status',
            'strategy:id,name',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('subscription_id')) {
            $query->where('subscription_id', $request->input('subscription_id'));
        }
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->input('customer_id'));
        }
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->whereHas('customer', fn($cq) => $cq->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('invoice', fn($iq) => $iq->where('invoice_no', 'like', "%{$search}%"));
            });
        }

        $items = $query->orderByRaw("CASE status WHEN 'pending' THEN 1 WHEN 'in_progress' THEN 2 WHEN 'failed' THEN 3 WHEN 'resolved' THEN 4 END")
            ->orderBy('next_action_at')
            ->paginate($request->input('per_page', 20));

        return ApiResponse::paginated($items);
    }

    /**
     * 催缴队列项详情
     *
     * GET /api/dunning/queue/{dunningQueue}
     */
    public function showQueue(DunningQueue $dunningQueue): JsonResponse
    {
        $dunningQueue->load([
            'subscription.customer',
            'subscription.product:id,name',
            'customer.user',
            'invoice',
            'strategy',
            'logs' => fn($q) => $q->latest()->limit(50),
        ]);

        return ApiResponse::success($dunningQueue);
    }

    /**
     * 催缴日志列表
     *
     * GET /api/dunning/logs?queue_id=&action_taken=&page=
     */
    public function logs(Request $request): JsonResponse
    {
        $query = DunningLog::with([
            'dunningQueue:id,status',
            'subscription:id,plan',
            'invoice:id,invoice_no',
        ]);

        if ($request->filled('queue_id')) {
            $query->where('dunning_queue_id', $request->input('queue_id'));
        }
        if ($request->filled('action_taken')) {
            $query->where('action_taken', $request->input('action_taken'));
        }
        if ($request->filled('subscription_id')) {
            $query->where('subscription_id', $request->input('subscription_id'));
        }

        $items = $query->orderByDesc('actioned_at')
            ->paginate($request->input('per_page', 50));

        return ApiResponse::paginated($items);
    }

    /**
     * 催缴策略列表
     *
     * GET /api/dunning/strategies
     */
    public function strategies(): JsonResponse
    {
        $strategies = DunningStrategy::orderBy('sort_order')
            ->get();

        return ApiResponse::success($strategies);
    }

    /**
     * 创建催缴策略
     *
     * POST /api/dunning/strategies
     */
    public function storeStrategy(Request $request): JsonResponse
    {
        if ($request->user()->cannot('admin')) {
            return ApiResponse::error('FORBIDDEN', __('app.api.dunning.forbidden'), 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:100|unique:dunning_strategies,slug',
            'description' => 'nullable|string',
            'stages' => 'required|array|min:1',
            'stages.*.day' => 'required|integer|min:0',
            'stages.*.action' => 'required|string|in:send_reminder,send_warning,retry_payment,downgrade,suspend,escalate',
            'stages.*.channel' => 'required|string|in:email,sms,email_and_sms,payment_gateway,none',
            'stages.*.subject' => 'nullable|string|max:255',
            'max_attempts' => 'sometimes|integer|min:1|max:20',
            'applicable_plans' => 'nullable|array',
            'sort_order' => 'sometimes|integer|min:0',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $strategy = DunningStrategy::create($validator->validated());

        return ApiResponse::created($strategy, __('app.api.dunning.strategy_created'));
    }

    /**
     * 更新催缴策略
     *
     * PUT /api/dunning/strategies/{dunningStrategy}
     */
    public function updateStrategy(Request $request, DunningStrategy $dunningStrategy): JsonResponse
    {
        if ($request->user()->cannot('admin')) {
            return ApiResponse::error('FORBIDDEN', __('app.api.dunning.forbidden'), 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'stages' => 'sometimes|array|min:1',
            'stages.*.day' => 'required|integer|min:0',
            'stages.*.action' => 'required|string|in:send_reminder,send_warning,retry_payment,downgrade,suspend,escalate',
            'stages.*.channel' => 'required|string|in:email,sms,email_and_sms,payment_gateway,none',
            'stages.*.subject' => 'nullable|string|max:255',
            'max_attempts' => 'sometimes|integer|min:1|max:20',
            'is_active' => 'sometimes|boolean',
            'applicable_plans' => 'nullable|array',
            'sort_order' => 'sometimes|integer|min:0',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $dunningStrategy->update($validator->validated());

        return ApiResponse::success($dunningStrategy->fresh(), __('app.api.dunning.strategy_updated'));
    }

    /**
     * 删除催缴策略
     *
     * DELETE /api/dunning/strategies/{dunningStrategy}
     */
    public function destroyStrategy(Request $request, DunningStrategy $dunningStrategy): JsonResponse
    {
        if ($request->user()->cannot('admin')) {
            return ApiResponse::error('FORBIDDEN', __('app.api.dunning.forbidden'), 403);
        }

        if ($dunningStrategy->queueItems()->whereIn('status', ['pending', 'in_progress'])->exists()) {
            return ApiResponse::error('IN_USE', __('app.api.dunning.strategy_in_use'), 422);
        }

        $dunningStrategy->delete();

        return ApiResponse::success(null, __('app.api.dunning.strategy_deleted'));
    }

    /**
     * 手动将订阅加入催缴队列
     *
     * POST /api/dunning/enqueue
     */
    public function enqueue(Request $request): JsonResponse
    {
        if ($request->user()->cannot('admin')) {
            return ApiResponse::error('FORBIDDEN', __('app.api.dunning.forbidden'), 403);
        }

        $validator = Validator::make($request->all(), [
            'subscription_id' => 'required|exists:subscriptions,id',
            'invoice_id' => 'nullable|exists:invoices,id',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        try {
            $item = $this->dunningEngine->manualEnqueue(
                $request->input('subscription_id'),
                $request->input('invoice_id'),
            );

            return ApiResponse::created($item, __('app.api.dunning.queue_enqueued'));
        } catch (\Throwable $e) {
            return ApiResponse::error('ENQUEUE_FAILED', $e->getMessage(), 422);
        }
    }

    /**
     * 手动触发催缴运行
     *
     * POST /api/dunning/run
     */
    public function run(Request $request): JsonResponse
    {
        if ($request->user()->cannot('admin')) {
            return ApiResponse::error('FORBIDDEN', __('app.api.dunning.forbidden'), 403);
        }

        $stats = $this->dunningEngine->processDunningRun();

        return ApiResponse::success($stats, __('app.api.dunning.run_completed'));
    }

    /**
     * 扫描逾期发票并入队列
     *
     * POST /api/dunning/scan-overdue
     */
    public function scanOverdue(Request $request): JsonResponse
    {
        if ($request->user()->cannot('admin')) {
            return ApiResponse::error('FORBIDDEN', __('app.api.dunning.forbidden'), 403);
        }

        $result = $this->dunningEngine->enqueueAllOverdueInvoices();

        return ApiResponse::success($result, __('app.api.dunning.scan_completed'));
    }

    /**
     * 从队列移除（解决）
     *
     * POST /api/dunning/queue/{dunningQueue}/resolve?status=paid|resolved
     */
    public function resolve(Request $request, DunningQueue $dunningQueue): JsonResponse
    {
        if ($request->user()->cannot('admin')) {
            return ApiResponse::error('FORBIDDEN', __('app.api.dunning.forbidden'), 403);
        }

        $status = $request->input('status', 'resolved');
        $dunningQueue->update([
            'status' => in_array($status, ['paid', 'resolved', 'cancelled']) ? $status : 'resolved',
            'resolved_at' => now(),
            'next_action_at' => null,
        ]);

        // 记录日志
        DunningLog::create([
            'dunning_queue_id' => $dunningQueue->id,
            'subscription_id' => $dunningQueue->subscription_id,
            'invoice_id' => $dunningQueue->invoice_id,
            'attempt_number' => $dunningQueue->attempt_count + 1,
            'action_taken' => 'resolve',
            'channel' => 'none',
            'success' => true,
        ]);

        return ApiResponse::success($dunningQueue->fresh(), __('app.api.dunning.item_resolved'));
    }
}
