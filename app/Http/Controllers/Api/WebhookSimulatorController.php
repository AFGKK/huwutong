<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\WebhookEndpoint;
use App\Models\WebhookEvent;
use App\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WebhookSimulatorController extends Controller
{
    public function __construct(
        protected WebhookService $webhookService,
    ) {}

    /**
     * 可用事件类型列表
     *
     * GET /api/webhook-simulator/event-types
     */
    public function eventTypes(): JsonResponse
    {
        $types = [
            ['value' => 'license.activated', 'label' => 'License 激活', 'group' => 'license'],
            ['value' => 'license.deactivated', 'label' => 'License 停用', 'group' => 'license'],
            ['value' => 'license.revoked', 'label' => 'License 吊销', 'group' => 'license'],
            ['value' => 'license.expired', 'label' => 'License 过期', 'group' => 'license'],
            ['value' => 'license.suspended', 'label' => 'License 暂停', 'group' => 'license'],
            ['value' => 'license.restored', 'label' => 'License 恢复', 'group' => 'license'],
            ['value' => 'license.frozen', 'label' => 'License 冻结', 'group' => 'license'],
            ['value' => 'license.refunded', 'label' => 'License 退款', 'group' => 'license'],
            ['value' => 'license.blacklisted', 'label' => 'License 加入黑名单', 'group' => 'license'],
            ['value' => 'subscription.created', 'label' => '订阅创建', 'group' => 'subscription'],
            ['value' => 'subscription.cancelled', 'label' => '订阅取消', 'group' => 'subscription'],
            ['value' => 'subscription.renewed', 'label' => '订阅续费', 'group' => 'subscription'],
            ['value' => 'subscription.expiring', 'label' => '订阅即将到期', 'group' => 'subscription'],
            ['value' => 'subscription.payment_failed', 'label' => '订阅支付失败', 'group' => 'subscription'],
            ['value' => 'customer.created', 'label' => '客户创建', 'group' => 'customer'],
            ['value' => 'customer.updated', 'label' => '客户更新', 'group' => 'customer'],
            ['value' => 'device.activated', 'label' => '设备激活', 'group' => 'device'],
            ['value' => 'device.deactivated', 'label' => '设备停用', 'group' => 'device'],
            ['value' => 'device.exceeded', 'label' => '设备超限', 'group' => 'device'],
            ['value' => 'user.login', 'label' => '用户登录', 'group' => 'user'],
            ['value' => 'user.mfa_enabled', 'label' => '用户启用 MFA', 'group' => 'user'],
            ['value' => 'ticket.created', 'label' => '工单创建', 'group' => 'ticket'],
            ['value' => 'ticket.updated', 'label' => '工单更新', 'group' => 'ticket'],
        ];

        return ApiResponse::success($types);
    }

    /**
     * 获取模拟目标端点列表
     *
     * GET /api/webhook-simulator/endpoints
     */
    public function endpoints(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $endpoints = WebhookEndpoint::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get(['id', 'name', 'url', 'events']);

        return ApiResponse::success($endpoints);
    }

    /**
     * 模拟触发 Webhook 事件
     *
     * POST /api/webhook-simulator/simulate
     * Body: { event_type: string, endpoint_id?: int, payload?: object, description?: string }
     */
    public function simulate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'event_type' => 'required|string|max:100',
            'endpoint_id' => 'nullable|integer|exists:webhook_endpoints,id',
            'payload' => 'nullable|array',
            'description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $data = $validator->validated();
        $tenantId = $request->user()->tenant_id;
        $user = $request->user();

        // 构建示例 payload
        $payload = $data['payload'] ?? $this->generateSamplePayload($data['event_type'], $tenantId);

        $dispatchCount = 0;
        $targetedEndpoint = null;

        if (! empty($data['endpoint_id'])) {
            // 定向发送到指定端点
            $endpoint = WebhookEndpoint::where('id', $data['endpoint_id'])
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->first();

            if (! $endpoint) {
                return ApiResponse::error('NOT_FOUND', '指定的端点不存在或不可用', 404);
            }

            $targetedEndpoint = [
                'id' => $endpoint->id,
                'name' => $endpoint->name,
                'url' => $endpoint->url,
            ];

            // 直接调用 sendToEndpoint 而不是走 dispatch（避免队列延迟 + 可以获得即时反馈）
            $event = WebhookEvent::create([
                'tenant_id' => $tenantId,
                'webhook_endpoint_id' => $endpoint->id,
                'event_type' => $data['event_type'],
                'payload' => $payload,
                'status' => 'pending',
                'is_simulated' => true,
                'description' => $data['description'] ?? 'Webhook 模拟器测试',
            ]);

            $result = $this->webhookService->sendToEndpoint($event, $endpoint);

            $event->refresh();

            $dispatchCount = 1;

            return ApiResponse::success([
                'event_id' => $event->id,
                'dispatched_to' => [$targetedEndpoint],
                'dispatch_count' => 1,
                'results' => [[
                    'endpoint' => $targetedEndpoint,
                    'success' => $result,
                    'status' => $event->status,
                    'status_code' => $event->status_code,
                    'response_body' => $event->response_body,
                    'attempts' => $event->attempts,
                    'created_at' => $event->created_at,
                ]],
            ], 'Webhook 模拟事件已发送');
        } else {
            // 广播到所有匹配的端点
            $endpoints = WebhookEndpoint::where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->where('is_paused', false)
                ->where(function ($q) use ($data) {
                    $q->whereJsonContains('events', $data['event_type'])
                      ->orWhereJsonContains('events', '*');
                })
                ->get();

            if ($endpoints->isEmpty()) {
                return ApiResponse::success([
                    'dispatched_to' => [],
                    'dispatch_count' => 0,
                    'message' => '没有匹配的 Webhook 端点订阅了此事件',
                ], '没有端点匹配');
            }

            $results = [];

            foreach ($endpoints as $endpoint) {
                $event = WebhookEvent::create([
                    'tenant_id' => $tenantId,
                    'webhook_endpoint_id' => $endpoint->id,
                    'event_type' => $data['event_type'],
                    'payload' => $payload,
                    'status' => 'pending',
                    'is_simulated' => true,
                    'description' => $data['description'] ?? 'Webhook 模拟器测试',
                ]);

                $success = $this->webhookService->sendToEndpoint($event, $endpoint);
                $event->refresh();
                $dispatchCount++;

                $results[] = [
                    'endpoint' => [
                        'id' => $endpoint->id,
                        'name' => $endpoint->name,
                        'url' => $endpoint->url,
                    ],
                    'success' => $success,
                    'status' => $event->status,
                    'status_code' => $event->status_code,
                    'response_body' => $event->response_body,
                    'attempts' => $event->attempts,
                    'created_at' => $event->created_at,
                ];
            }

            return ApiResponse::success([
                'event_id' => null,
                'dispatched_to' => $results,
                'dispatch_count' => $dispatchCount,
                'results' => $results,
            ], "Webhook 模拟事件已发送到 {$dispatchCount} 个端点");
        }
    }

    /**
     * 模拟历史记录
     *
     * GET /api/webhook-simulator/history
     */
    public function history(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $query = WebhookEvent::where('tenant_id', $tenantId)
            ->where('is_simulated', true)
            ->with('endpoint:id,name,url')
            ->orderBy('created_at', 'desc');

        $perPage = min((int) $request->input('per_page', 20), 100);
        $search = $request->input('search');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('event_type', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($eventType = $request->input('event_type')) {
            $query->where('event_type', $eventType);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        return ApiResponse::paginated($query->paginate($perPage));
    }

    /**
     * 为事件类型生成示例 payload
     */
    protected function generateSamplePayload(string $eventType, ?int $tenantId): array
    {
        $base = [
            'tenant_id' => $tenantId,
            'timestamp' => now()->toIso8601String(),
        ];

        // 尝试查找一个真实的 License、客户等数据
        $sampleLicense = $tenantId
            ? License::where('tenant_id', $tenantId)->first()
            : License::first();

        $sampleLicenseData = $sampleLicense ? [
            'id' => $sampleLicense->id,
            'license_key' => $sampleLicense->license_key,
            'type' => $sampleLicense->type,
            'status' => $sampleLicense->status,
            'expires_at' => $sampleLicense->expires_at?->toDateString(),
        ] : [
            'id' => 1,
            'license_key' => 'HWT-XXXX-XXXX-XXXX',
            'type' => 'standard',
            'status' => 'active',
            'expires_at' => now()->addYear()->toDateString(),
        ];

        $prefix = explode('.', $eventType)[0];

        return match ($prefix) {
            'license' => array_merge($base, [
                'license' => $sampleLicenseData,
                'previous_status' => 'pending',
                'reason' => '模拟触发',
            ]),
            'subscription' => array_merge($base, [
                'subscription' => [
                    'id' => 1,
                    'plan' => 'enterprise',
                    'status' => 'active',
                    'ends_at' => now()->addDays(30)->toDateString(),
                ],
                'customer' => [
                    'id' => 1,
                    'name' => '示例客户',
                    'email' => 'customer@example.com',
                ],
            ]),
            'customer' => array_merge($base, [
                'customer' => [
                    'id' => 1,
                    'name' => '示例客户',
                    'email' => 'customer@example.com',
                    'company' => '示例公司',
                ],
            ]),
            'device' => array_merge($base, [
                'device' => [
                    'id' => 1,
                    'fingerprint' => 'DEVICE-FP-001',
                    'name' => '示例设备',
                    'platform' => 'linux',
                ],
                'license' => $sampleLicenseData,
            ]),
            'user' => array_merge($base, [
                'user' => [
                    'id' => 1,
                    'name' => '管理员',
                    'email' => 'admin@example.com',
                ],
            ]),
            'ticket' => array_merge($base, [
                'ticket' => [
                    'id' => 1,
                    'subject' => '示例工单',
                    'status' => 'open',
                    'priority' => 'medium',
                ],
            ]),
            default => $base,
        };
    }

    /**
     * 获取关于某事件类型的模拟说明
     *
     * GET /api/webhook-simulator/event-info/{eventType}
     */
    public function eventInfo(string $eventType): JsonResponse
    {
        $info = [
            'license.activated' => ['desc' => '当 License 被激活时触发', 'sample' => $this->generateSamplePayload('license.activated', null)],
            'license.deactivated' => ['desc' => '当 License 被停用时触发', 'sample' => $this->generateSamplePayload('license.deactivated', null)],
            'license.expired' => ['desc' => '当 License 到期时触发', 'sample' => $this->generateSamplePayload('license.expired', null)],
            'subscription.created' => ['desc' => '当新订阅创建时触发', 'sample' => $this->generateSamplePayload('subscription.created', null)],
            'subscription.cancelled' => ['desc' => '当订阅取消时触发', 'sample' => $this->generateSamplePayload('subscription.cancelled', null)],
            'customer.created' => ['desc' => '当新客户创建时触发', 'sample' => $this->generateSamplePayload('customer.created', null)],
            'device.activated' => ['desc' => '当设备激活时触发', 'sample' => $this->generateSamplePayload('device.activated', null)],
            'user.login' => ['desc' => '当用户登录时触发', 'sample' => $this->generateSamplePayload('user.login', null)],
        ];

        if (! isset($info[$eventType])) {
            return ApiResponse::success([
                'event_type' => $eventType,
                'desc' => '自定义事件类型',
                'sample' => $this->generateSamplePayload($eventType, null),
            ]);
        }

        return ApiResponse::success(array_merge(['event_type' => $eventType], $info[$eventType]));
    }
}
