<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Models\WebhookEndpoint;
use App\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

/**
 * Webhook 端点管理控制器
 *
 * 提供 Webhook 端点的 CRUD 操作、暂停/恢复、连接测试能力。
 * 所有操作受租户隔离保护。
 */
class WebhookEndpointController extends Controller
{
    public function __construct(
        protected WebhookService $webhookService,
    ) {}

    /**
     * 获取端点列表
     *
     * GET /api/webhook-endpoints
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
            'is_active' => 'nullable|boolean',
            'is_paused' => 'nullable|boolean',
        ]);

        $query = WebhookEndpoint::withCount('events')
            ->where('tenant_id', $request->user()->tenant_id);

        if (isset($data['is_active'])) {
            $query->where('is_active', $data['is_active']);
        }

        if (isset($data['is_paused'])) {
            $query->where('is_paused', $data['is_paused']);
        }

        $endpoints = $query->latest()->paginate(min($data['per_page'] ?? 20, 100));

        return ApiResponse::paginated($endpoints);
    }

    /**
     * 创建端点
     *
     * POST /api/webhook-endpoints
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|url|max:500',
            'events' => 'required|array|min:1',
            'events.*' => 'required|string|max:100',
            'secret' => 'nullable|string|min:16|max:64',
        ]);

        $tenantId = $request->user()->tenant_id;

        $endpoint = WebhookEndpoint::create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'url' => $data['url'],
            'events' => $data['events'],
            'secret' => $data['secret'] ?? $this->generateSecret(),
            'is_active' => true,
            'is_paused' => false,
        ]);

        return ApiResponse::created($endpoint, __('app.api.webhook.created'));
    }

    /**
     * 获取端点详情
     *
     * GET /api/webhook-endpoints/{endpoint}
     */
    public function show(int $id): JsonResponse
    {
        $endpoint = $this->findEndpoint($id);

        if (! $endpoint) {
            return ApiResponse::notFound(__('app.api.webhook.not_found'));
        }

        $endpoint->loadCount('events');
        $endpoint->load(['events' => fn($q) => $q->latest()->limit(10)]);

        return ApiResponse::success($endpoint);
    }

    /**
     * 更新端点
     *
     * PUT /api/webhook-endpoints/{endpoint}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $endpoint = $this->findEndpoint($id);

        if (! $endpoint) {
            return ApiResponse::notFound(__('app.api.webhook.not_found'));
        }

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'url' => 'sometimes|url|max:500',
            'events' => 'sometimes|array|min:1',
            'events.*' => 'required|string|max:100',
            'secret' => 'nullable|string|min:16|max:64',
            'is_active' => 'sometimes|boolean',
        ]);

        if (isset($data['secret'])) {
            if (empty($data['secret'])) {
                unset($data['secret']);
            }
        } else {
            unset($data['secret']);
        }

        $endpoint->update($data);

        return ApiResponse::success($endpoint->fresh(), __('app.api.webhook.updated'));
    }

    /**
     * 删除端点
     *
     * DELETE /api/webhook-endpoints/{endpoint}
     */
    public function destroy(int $id): JsonResponse
    {
        $endpoint = $this->findEndpoint($id);

        if (! $endpoint) {
            return ApiResponse::notFound(__('app.api.webhook.not_found'));
        }

        // 软删除：标记为 inactive 而非实际删除
        $endpoint->update([
            'is_active' => false,
            'is_paused' => true,
        ]);

        return ApiResponse::success(null, __('app.api.webhook.disabled'));
    }

    /**
     * 切换暂停/恢复状态
     *
     * POST /api/webhook-endpoints/{endpoint}/toggle-pause
     */
    public function togglePause(int $id): JsonResponse
    {
        $endpoint = $this->findEndpoint($id);

        if (! $endpoint) {
            return ApiResponse::notFound(__('app.api.webhook.not_found'));
        }

        $pause = ! $endpoint->is_paused;
        $this->webhookService->togglePause($endpoint, $pause);

        return ApiResponse::success([
            'is_paused' => $pause,
            'paused_at' => $endpoint->fresh()->paused_at,
        ], $pause ? __('app.api.webhook.paused') : __('app.api.webhook.resumed'));
    }

    /**
     * 测试连接端点
     *
     * POST /api/webhook-endpoints/{endpoint}/test
     */
    public function test(int $id): JsonResponse
    {
        $endpoint = $this->findEndpoint($id);

        if (! $endpoint) {
            return ApiResponse::notFound(__('app.api.webhook.not_found'));
        }

        try {
            $result = $this->webhookService->testEndpoint($endpoint);

            return ApiResponse::success($result, $result['success'] ? __('app.api.webhook.test_ok') : __('app.api.webhook.test_fail'));
        } catch (\Throwable $e) {
            return ApiResponse::error('TEST_FAILED', __('app.api.webhook.test_error', ['error' => $e->getMessage()]), 500);
        }
    }

    /**
     * 获取可用事件类型列表
     *
     * GET /api/webhook-endpoints/event-types
     */
    public function eventTypes(): JsonResponse
    {
        $types = [
            ['value' => '*', 'label' => __('app.api.webhook.evt_all')],
            ['value' => 'license.activated', 'label' => __('app.api.webhook.evt_license_activated')],
            ['value' => 'license.deactivated', 'label' => __('app.api.webhook.evt_license_deactivated')],
            ['value' => 'license.revoked', 'label' => __('app.api.webhook.evt_license_revoked')],
            ['value' => 'license.expired', 'label' => __('app.api.webhook.evt_license_expired')],
            ['value' => 'license.suspended', 'label' => __('app.api.webhook.evt_license_suspended')],
            ['value' => 'license.restored', 'label' => __('app.api.webhook.evt_license_restored')],
            ['value' => 'license.frozen', 'label' => __('app.api.webhook.evt_license_frozen')],
            ['value' => 'license.refunded', 'label' => __('app.api.webhook.evt_license_refunded')],
            ['value' => 'license.blacklisted', 'label' => __('app.api.webhook.evt_license_blacklisted')],
            ['value' => 'subscription.created', 'label' => __('app.api.webhook.evt_sub_created')],
            ['value' => 'subscription.cancelled', 'label' => __('app.api.webhook.evt_sub_cancelled')],
            ['value' => 'subscription.renewed', 'label' => __('app.api.webhook.evt_sub_renewed')],
            ['value' => 'subscription.expiring', 'label' => __('app.api.webhook.evt_sub_expiring')],
            ['value' => 'subscription.payment_failed', 'label' => __('app.api.webhook.evt_sub_payment_failed')],
            ['value' => 'customer.created', 'label' => __('app.api.webhook.evt_customer_created')],
            ['value' => 'customer.updated', 'label' => __('app.api.webhook.evt_customer_updated')],
            ['value' => 'device.activated', 'label' => __('app.api.webhook.evt_device_activated')],
            ['value' => 'device.deactivated', 'label' => __('app.api.webhook.evt_device_deactivated')],
            ['value' => 'device.exceeded', 'label' => __('app.api.webhook.evt_device_exceeded')],
            ['value' => 'user.login', 'label' => __('app.api.webhook.evt_user_login')],
            ['value' => 'user.mfa_enabled', 'label' => __('app.api.webhook.evt_user_mfa')],
            ['value' => 'ticket.created', 'label' => __('app.api.webhook.evt_ticket_created')],
            ['value' => 'ticket.updated', 'label' => __('app.api.webhook.evt_ticket_updated')],
        ];

        return ApiResponse::success($types);
    }

    /**
     * 查找端点并验证租户归属
     */
    protected function findEndpoint(int $id): ?WebhookEndpoint
    {
        $endpoint = WebhookEndpoint::find($id);

        if (! $endpoint) {
            return null;
        }

        // 租户隔离
        if ($endpoint->tenant_id !== auth()->user()?->tenant_id) {
            return null;
        }

        return $endpoint;
    }

    /**
     * 生成随机密钥
     */
    protected function generateSecret(): string
    {
        return 'whsec_' . bin2hex(random_bytes(24));
    }
}
