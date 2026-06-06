<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Models\WebhookEndpoint;
use App\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

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

        return ApiResponse::created($endpoint, 'Webhook 端点创建成功');
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
            return ApiResponse::notFound('Webhook 端点不存在');
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
            return ApiResponse::notFound('Webhook 端点不存在');
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

        return ApiResponse::success($endpoint->fresh(), 'Webhook 端点已更新');
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
            return ApiResponse::notFound('Webhook 端点不存在');
        }

        // 软删除：标记为 inactive 而非实际删除
        $endpoint->update([
            'is_active' => false,
            'is_paused' => true,
        ]);

        return ApiResponse::success(null, 'Webhook 端点已停用');
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
            return ApiResponse::notFound('Webhook 端点不存在');
        }

        $pause = ! $endpoint->is_paused;
        $this->webhookService->togglePause($endpoint, $pause);

        return ApiResponse::success([
            'is_paused' => $pause,
            'paused_at' => $endpoint->fresh()->paused_at,
        ], $pause ? '端点已暂停' : '端点已恢复');
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
            return ApiResponse::notFound('Webhook 端点不存在');
        }

        try {
            $result = $this->webhookService->testEndpoint($endpoint);

            return ApiResponse::success($result, $result['success'] ? '连接测试成功' : '连接测试失败');
        } catch (\Throwable $e) {
            return ApiResponse::error('TEST_FAILED', '连接测试异常: ' . $e->getMessage(), 500);
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
            ['value' => '*', 'label' => '全部事件'],
            ['value' => 'license.activated', 'label' => 'License 激活'],
            ['value' => 'license.deactivated', 'label' => 'License 停用'],
            ['value' => 'license.revoked', 'label' => 'License 吊销'],
            ['value' => 'license.expired', 'label' => 'License 过期'],
            ['value' => 'license.suspended', 'label' => 'License 暂停'],
            ['value' => 'license.restored', 'label' => 'License 恢复'],
            ['value' => 'license.frozen', 'label' => 'License 冻结'],
            ['value' => 'license.refunded', 'label' => 'License 退款'],
            ['value' => 'license.blacklisted', 'label' => 'License 加入黑名单'],
            ['value' => 'subscription.created', 'label' => '订阅创建'],
            ['value' => 'subscription.cancelled', 'label' => '订阅取消'],
            ['value' => 'subscription.renewed', 'label' => '订阅续费'],
            ['value' => 'subscription.expiring', 'label' => '订阅即将到期'],
            ['value' => 'subscription.payment_failed', 'label' => '订阅支付失败'],
            ['value' => 'customer.created', 'label' => '客户创建'],
            ['value' => 'customer.updated', 'label' => '客户更新'],
            ['value' => 'device.activated', 'label' => '设备激活'],
            ['value' => 'device.deactivated', 'label' => '设备停用'],
            ['value' => 'device.exceeded', 'label' => '设备超限'],
            ['value' => 'user.login', 'label' => '用户登录'],
            ['value' => 'user.mfa_enabled', 'label' => '用户启用 MFA'],
            ['value' => 'ticket.created', 'label' => '工单创建'],
            ['value' => 'ticket.updated', 'label' => '工单更新'],
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
