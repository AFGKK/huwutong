<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\CsmTask;
use App\Services\CsmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CsmController extends Controller
{
    public function __construct(
        protected CsmService $csmService,
    ) {}

    // ─── 仪表盘 ───

    public function dashboard(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->csmService->getDashboard($request->user()->tenant_id)
        );
    }

    // ─── 客户列表（含健康评分） ───

    public function customers(Request $request): JsonResponse
    {
        return ApiResponse::paginated(
            $this->csmService->getCustomersWithHealth(
                $request->user()->tenant_id,
                $request->only(['health_level', 'search', 'churn_risk']),
                $request->get('sort', '-health_score'),
                (int) $request->get('per_page', 20)
            )
        );
    }

    // ─── 客户详情 ───

    public function customerDetail(int $id, Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->csmService->getCustomerDetail($id)
        );
    }

    // ─── 健康评分 ───

    public function calculateHealthScore(int $id, Request $request): JsonResponse
    {
        $customer = \App\Models\Customer::where('tenant_id', $request->user()->tenant_id)->findOrFail($id);

        $score = $this->csmService->calculateHealthScore($customer);

        return ApiResponse::success($score, __("app.csm.msg_e05e0061"));
    }

    public function batchCalculateHealth(Request $request): JsonResponse
    {
        $results = $this->csmService->batchCalculateHealthScores($request->user()->tenant_id);

        return ApiResponse::success($results, __('app.csm.batch_health_processed', ['count' => count($results)]));
    }

    // ─── 任务管理 ───

    public function tasks(Request $request): JsonResponse
    {
        return ApiResponse::paginated(
            $this->csmService->getTasks(
                $request->user()->tenant_id,
                $request->only(['status', 'assigned_to', 'priority', 'category']),
                (int) $request->get('per_page', 20)
            )
        );
    }

    public function storeTask(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'title' => 'required|string|max:200',
            'description' => 'nullable|string',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'category' => 'nullable|in:renewal,onboarding,support,review,checkin,custom',
            'assigned_to' => 'required|integer|exists:users,id',
            'due_at' => 'nullable|date',
        ]);

        $data['tenant_id'] = $request->user()->tenant_id;
        $data['created_by'] = $request->user()->id;
        $data['status'] = 'open';

        $task = $this->csmService->createTask($data);

        return ApiResponse::success($task->load('assignee:id,name', 'customer.user:id,name'), __('app.csm.task_created'), 201);
    }

    public function updateTask(Request $request, CsmTask $csmTask): JsonResponse
    {
        $data = $request->validate([
            'title' => 'string|max:200',
            'description' => 'nullable|string',
            'priority' => 'in:low,normal,high,urgent',
            'status' => 'in:open,in_progress,completed,cancelled',
            'assigned_to' => 'integer|exists:users,id',
            'due_at' => 'nullable|date',
        ]);

        $task = $this->csmService->updateTask($csmTask, $data);

        return ApiResponse::success($task->load('assignee:id,name'), __('app.csm.task_updated'));
    }

    public function deleteTask(CsmTask $csmTask): JsonResponse
    {
        $csmTask->delete();
        return ApiResponse::success(null, __("app.csm.msg_b2ae04aa"));
    }

    // ─── 沟通记录 ───

    public function communications(Request $request): JsonResponse
    {
        $query = \App\Models\CsmCommunication::where('tenant_id', $request->user()->tenant_id)
            ->with('customer.user:id,name', 'user:id,name')
            ->orderByDesc('contacted_at');

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->input('customer_id'));
        }

        return ApiResponse::paginated(
            $query->paginate(min((int) $request->get('per_page', 20), 100))
        );
    }

    public function storeCommunication(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'type' => 'required|in:call,email,meeting,note,chat',
            'subject' => 'nullable|string|max:200',
            'content' => 'nullable|string',
            'contacted_at' => 'nullable|date',
        ]);

        $data['tenant_id'] = $request->user()->tenant_id;
        $data['user_id'] = $request->user()->id;

        $comm = $this->csmService->recordCommunication($data);

        return ApiResponse::success($comm->load('user:id,name'), __('app.csm.comm_record_saved'), 201);
    }

    // ─── 续费提醒生成 ───

    public function createRenewalReminders(Request $request): JsonResponse
    {
        $count = $this->csmService->createRenewalReminders($request->user()->tenant_id);

        return ApiResponse::success(['created' => $count], __("app.csm.msg_63e5eb07"));
    }

    public function healthTrend(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->csmService->getHealthTrend(
                $request->user()->tenant_id,
                (int) $request->get('days', 90)
            )
        );
    }

    public function renewalCalendar(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->csmService->getRenewalCalendar(
                $request->user()->tenant_id,
                $request->get('year_month')
            )
        );
    }

    public function activityTimeline(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->csmService->getActivityTimeline(
                $request->user()->tenant_id,
                $request->filled('customer_id') ? (int) $request->input('customer_id') : null,
                (int) $request->get('limit', 50)
            )
        );
    }
}
