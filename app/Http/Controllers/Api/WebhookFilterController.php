<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\WebhookEndpoint;
use App\Models\WebhookFilter;
use App\Services\WebhookFilterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Webhook 条件化过滤器控制器 (M2-53)
 */
class WebhookFilterController extends Controller
{
    public function __construct(
        protected WebhookFilterService $webhookFilter,
    ) {
    }

    /**
     * 获取端点的过滤器列表
     */
    public function index(int $endpointId): JsonResponse
    {
        $endpoint = WebhookEndpoint::where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($endpointId);

        return ApiResponse::success([
            'filters' => $this->webhookFilter->getFilters($endpoint->id),
        ]);
    }

    /**
     * 创建过滤器
     */
    public function store(Request $request, int $endpointId): JsonResponse
    {
        $endpoint = WebhookEndpoint::where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($endpointId);

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'conditions' => 'required|array|min:1|max:20',
            'conditions.*.field' => 'required|string|max:100',
            'conditions.*.operator' => 'required|string|in:equals,not_equals,contains,not_contains,starts_with,ends_with,in,not_in,greater_than,less_than,exists,not_exists,regex',
            'conditions.*.value' => 'nullable',
            'match_type' => 'nullable|string|in:all,any',
            'payload_template' => 'nullable|array',
            'is_active' => 'boolean',
            'priority' => 'nullable|integer|min:-100|max:100',
        ]);

        $userId = $request->user()?->id;

        try {
            $filter = $this->webhookFilter->createFilter($endpoint->id, $data, $userId);
            return ApiResponse::success(['filter' => $filter], __('app.api.webhook_filter.filter_created'));
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    /**
     * 更新过滤器
     */
    public function update(Request $request, int $endpointId, int $filterId): JsonResponse
    {
        $endpoint = WebhookEndpoint::where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($endpointId);

        $data = $request->validate([
            'name' => 'nullable|string|max:100',
            'conditions' => 'nullable|array|min:1|max:20',
            'conditions.*.field' => 'required_with:conditions|string|max:100',
            'conditions.*.operator' => 'required_with:conditions|string|in:equals,not_equals,contains,not_contains,starts_with,ends_with,in,not_in,greater_than,less_than,exists,not_exists,regex',
            'conditions.*.value' => 'nullable',
            'match_type' => 'nullable|string|in:all,any',
            'payload_template' => 'nullable|array',
            'is_active' => 'boolean',
            'priority' => 'nullable|integer|min:-100|max:100',
        ]);

        $filter = $this->webhookFilter->updateFilter($filterId, $data);
        return ApiResponse::success(['filter' => $filter], __('app.api.webhook_filter.filter_updated'));
    }

    /**
     * 删除过滤器
     */
    public function destroy(int $endpointId, int $filterId): JsonResponse
    {
        $endpoint = WebhookEndpoint::where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($endpointId);

        $this->webhookFilter->deleteFilter($filterId);
        return ApiResponse::success(null, __('app.api.webhook_filter.filter_deleted'));
    }

    /**
     * 测试过滤条件
     */
    public function testCondition(Request $request): JsonResponse
    {
        $data = $request->validate([
            'condition' => 'required|array',
            'condition.field' => 'required|string',
            'condition.operator' => 'required|string',
            'condition.value' => 'nullable',
            'test_payload' => 'required|array',
            'event_type' => 'nullable|string',
        ]);

        $result = $this->webhookFilter->testCondition(
            $data['condition'],
            $data['test_payload'],
            $data['event_type'] ?? 'test.event'
        );

        return ApiResponse::success($result);
    }

    /**
     * 批量测试
     */
    public function batchTest(Request $request, int $endpointId): JsonResponse
    {
        $endpoint = WebhookEndpoint::where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($endpointId);

        $data = $request->validate([
            'test_events' => 'required|array|min:1|max:20',
            'test_events.*.event_type' => 'required|string',
            'test_events.*.payload' => 'required|array',
        ]);

        $results = $this->webhookFilter->batchTest($endpoint->id, $data['test_events']);
        return ApiResponse::success(['results' => $results]);
    }

    /**
     * 获取支持的筛选选项
     */
    public function options(): JsonResponse
    {
        return ApiResponse::success(
            $this->webhookFilter->getFilterOptions()
        );
    }
}
