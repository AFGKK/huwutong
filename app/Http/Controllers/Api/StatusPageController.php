<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StatusComponent;
use App\Models\StatusIncident;
use App\Models\StatusSubscriber;
use App\Services\StatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * 公开状态页 API
 *
 * 无需认证的公共端点 + 管理端认证端点。
 * 用于 status.huwutong.com 展示系统实时状态。
 */
class StatusPageController extends Controller
{
    public function __construct(
        protected StatusService $statusService,
    ) {}

    // ========================
    // 公开端点（无需认证）
    // ========================

    /**
     * 系统状态概览
     */
    public function index(): JsonResponse
    {
        $overview = $this->statusService->getOverview();

        return response()->json([
            'success' => true,
            'data' => $overview,
        ]);
    }

    /**
     * 状态历史
     */
    public function history(Request $request): JsonResponse
    {
        $days = min((int) $request->input('days', 30), 90);

        return response()->json([
            'success' => true,
            'data' => $this->statusService->getHistory($days),
        ]);
    }

    /**
     * 订阅状态通知
     */
    public function subscribe(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $subscriber = $this->statusService->subscribe($request->input('email'));

            return response()->json([
                'success' => true,
                'message' => __('app.api.status_page.subscribed'),
                'data' => ['email' => $subscriber->email],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => __('app.api.status_page.subscribe_failed', ['error' => $e->getMessage()]),
            ], 500);
        }
    }

    /**
     * 退订
     */
    public function unsubscribe(string $token): JsonResponse
    {
        $result = $this->statusService->unsubscribe($token);

        if (!$result) {
            return response()->json(['success' => false, 'message' => __('app.api.status_page.unsubscribe_invalid')], 404);
        }

        return response()->json(['success' => true, 'message' => __('app.api.status_page.unsubscribed')]);
    }

    // ========================
    // 管理端点（需要认证）
    // ========================

    /**
     * 组件列表
     */
    public function components(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StatusComponent::class);

        $components = StatusComponent::byGroup()->get();

        return response()->json(['success' => true, 'data' => $components]);
    }

    /**
     * 创建组件
     */
    public function storeComponent(Request $request): JsonResponse
    {
        $this->authorize('create', StatusComponent::class);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'slug' => 'required|string|max:100|unique:status_components,slug',
            'description' => 'sometimes|nullable|string|max:500',
            'group' => 'sometimes|string|max:50',
            'sort_order' => 'sometimes|integer|min:0',
            'is_public' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $component = StatusComponent::create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => __('app.api.status_page.component_created'),
            'data' => $component,
        ], 201);
    }

    /**
     * 更新组件
     */
    public function updateComponent(Request $request, StatusComponent $component): JsonResponse
    {
        $this->authorize('update', $component);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:100',
            'description' => 'sometimes|nullable|string|max:500',
            'group' => 'sometimes|string|max:50',
            'status' => 'sometimes|in:operational,degraded_performance,partial_outage,major_outage,unknown',
            'sort_order' => 'sometimes|integer|min:0',
            'is_public' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $component->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => __('app.api.status_page.component_updated'),
            'data' => $component,
        ]);
    }

    /**
     * 删除组件
     */
    public function destroyComponent(StatusComponent $component): JsonResponse
    {
        $this->authorize('delete', $component);

        $component->delete();

        return response()->json(['success' => true, 'message' => __('app.api.status_page.component_deleted')]);
    }

    /**
     * 事件列表（管理端）
     */
    public function incidents(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StatusIncident::class);

        $query = StatusIncident::with('components', 'updates');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('severity')) {
            $query->where('severity', $request->input('severity'));
        }

        $incidents = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));

        return response()->json(['success' => true, 'data' => $incidents]);
    }

    /**
     * 事件详情
     */
    public function showIncident(StatusIncident $incident): JsonResponse
    {
        $this->authorize('view', $incident);

        $incident->load('components', 'updates');

        return response()->json(['success' => true, 'data' => $incident]);
    }

    /**
     * 创建事件
     */
    public function storeIncident(Request $request): JsonResponse
    {
        $this->authorize('create', StatusIncident::class);

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:200',
            'description' => 'sometimes|string',
            'severity' => 'required|in:minor,major,critical',
            'is_public' => 'sometimes|boolean',
            'component_ids' => 'sometimes|array',
            'component_ids.*' => 'exists:status_components,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $incident = $this->statusService->createIncident($validator->validated());

        return response()->json([
            'success' => true,
            'message' => __('app.api.status_page.incident_created'),
            'data' => $incident->load('components', 'updates'),
        ], 201);
    }

    /**
     * 更新事件状态
     */
    public function updateIncidentStatus(Request $request, StatusIncident $incident): JsonResponse
    {
        $this->authorize('update', $incident);

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:investigating,identified,monitoring,resolved,postmortem',
            'message' => 'required|string|max:1000',
            'component_statuses' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $update = $this->statusService->updateIncidentStatus(
            $incident,
            $request->input('status'),
            $request->input('message'),
            $request->input('component_statuses')
        );

        return response()->json([
            'success' => true,
            'message' => __('app.api.status_page.incident_updated'),
            'data' => $incident->fresh()->load('components', 'updates'),
        ]);
    }

    /**
     * 删除事件
     */
    public function destroyIncident(StatusIncident $incident): JsonResponse
    {
        $this->authorize('delete', $incident);

        // 恢复关联组件状态
        $incident->components()->update(['status' => 'operational']);
        $incident->delete();

        return response()->json(['success' => true, 'message' => __('app.api.status_page.incident_deleted')]);
    }

    /**
     * 订阅者列表
     */
    public function subscribers(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StatusSubscriber::class);

        $query = StatusSubscriber::query();
        if ($request->filled('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        $subscribers = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));

        return response()->json(['success' => true, 'data' => $subscribers]);
    }

    /**
     * 执行健康检查
     */
    public function runChecks(): JsonResponse
    {
        $this->authorize('viewAny', StatusComponent::class);

        $results = $this->statusService->runHealthChecks();

        return response()->json([
            'success' => true,
            'data' => $results,
        ]);
    }

    /**
     * 统计
     */
    public function stats(): JsonResponse
    {
        $this->authorize('viewAny', StatusComponent::class);

        return response()->json([
            'success' => true,
            'data' => $this->statusService->getStats(),
        ]);
    }
}
