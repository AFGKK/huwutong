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
            ['value' => 'license.activated', 'label' => __('app.api.webhook.evt_license_activated'), 'group' => 'license'],
            ['value' => 'license.deactivated', 'label' => __('app.api.webhook.evt_license_deactivated'), 'group' => 'license'],
            ['value' => 'license.revoked', 'label' => __('app.api.webhook.evt_license_revoked'), 'group' => 'license'],
            ['value' => 'license.expired', 'label' => __('app.api.webhook.evt_license_expired'), 'group' => 'license'],
            ['value' => 'license.suspended', 'label' => __('app.api.webhook.evt_license_suspended'), 'group' => 'license'],
            ['value' => 'license.restored', 'label' => __('app.api.webhook.evt_license_restored'), 'group' => 'license'],
            ['value' => 'license.frozen', 'label' => __('app.api.webhook.evt_license_frozen'), 'group' => 'license'],
            ['value' => 'license.refunded', 'label' => __('app.api.webhook.evt_license_refunded'), 'group' => 'license'],
            ['value' => 'license.blacklisted', 'label' => __('app.api.webhook.evt_license_blacklisted'), 'group' => 'license'],
            ['value' => 'subscription.created', 'label' => __('app.api.webhook.evt_sub_created'), 'group' => 'subscription'],
            ['value' => 'subscription.cancelled', 'label' => __('app.api.webhook.evt_sub_cancelled'), 'group' => 'subscription'],
            ['value' => 'subscription.renewed', 'label' => __('app.api.webhook.evt_sub_renewed'), 'group' => 'subscription'],
            ['value' => 'subscription.expiring', 'label' => __('app.api.webhook.evt_sub_expiring'), 'group' => 'subscription'],
            ['value' => 'subscription.payment_failed', 'label' => __('app.api.webhook.evt_sub_payment_failed'), 'group' => 'subscription'],
            ['value' => 'customer.created', 'label' => __('app.api.webhook.evt_customer_created'), 'group' => 'customer'],
            ['value' => 'customer.updated', 'label' => __('app.api.webhook.evt_customer_updated'), 'group' => 'customer'],
            ['value' => 'device.activated', 'label' => __('app.api.webhook.evt_device_activated'), 'group' => 'device'],
            ['value' => 'device.deactivated', 'label' => __('app.api.webhook.evt_device_deactivated'), 'group' => 'device'],
            ['value' => 'device.exceeded', 'label' => __('app.api.webhook.evt_device_exceeded'), 'group' => 'device'],
            ['value' => 'user.login', 'label' => __('app.api.webhook.evt_user_login'), 'group' => 'user'],
            ['value' => 'user.mfa_enabled', 'label' => __('app.api.webhook.evt_user_mfa'), 'group' => 'user'],
            ['value' => 'ticket.created', 'label' => __('app.api.webhook.evt_ticket_created'), 'group' => 'ticket'],
            ['value' => 'ticket.updated', 'label' => __('app.api.webhook.evt_ticket_updated'), 'group' => 'ticket'],
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
                return ApiResponse::error('NOT_FOUND', __('app.api.webhook_sim.endpoint_missing'), 404);
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
                'description' => $data['description'] ?? __('app.api.webhook_sim.default_desc'),
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
            ], __('app.api.webhook_sim.sent'));
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
                    'message' => __('app.api.webhook_sim.no_match_msg'),
                ], __('app.api.webhook_sim.no_match'));
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
                    'description' => $data['description'] ?? __('app.api.webhook_sim.default_desc'),
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
            ], __('app.api.webhook_sim.sent_n', ['count' => $dispatchCount]));
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
                'reason' => __('app.api.webhook_sim.sim_reason'),
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
                    'name' => __('app.api.webhook_sim.sample_customer'),
                    'email' => 'customer@example.com',
                ],
            ]),
            'customer' => array_merge($base, [
                'customer' => [
                    'id' => 1,
                    'name' => __('app.api.webhook_sim.sample_customer'),
                    'email' => 'customer@example.com',
                    'company' => __('app.api.webhook_sim.sample_company'),
                ],
            ]),
            'device' => array_merge($base, [
                'device' => [
                    'id' => 1,
                    'fingerprint' => 'DEVICE-FP-001',
                    'name' => __('app.api.webhook_sim.sample_device'),
                    'platform' => 'linux',
                ],
                'license' => $sampleLicenseData,
            ]),
            'user' => array_merge($base, [
                'user' => [
                    'id' => 1,
                    'name' => __('app.api.webhook_sim.sample_admin'),
                    'email' => 'admin@example.com',
                ],
            ]),
            'ticket' => array_merge($base, [
                'ticket' => [
                    'id' => 1,
                    'subject' => __('app.api.webhook_sim.sample_ticket'),
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
            'license.activated' => ['desc' => __('app.api.webhook_sim.desc_license_activated'), 'sample' => $this->generateSamplePayload('license.activated', null)],
            'license.deactivated' => ['desc' => __('app.api.webhook_sim.desc_license_deactivated'), 'sample' => $this->generateSamplePayload('license.deactivated', null)],
            'license.expired' => ['desc' => __('app.api.webhook_sim.desc_license_expired'), 'sample' => $this->generateSamplePayload('license.expired', null)],
            'subscription.created' => ['desc' => __('app.api.webhook_sim.desc_sub_created'), 'sample' => $this->generateSamplePayload('subscription.created', null)],
            'subscription.cancelled' => ['desc' => __('app.api.webhook_sim.desc_sub_cancelled'), 'sample' => $this->generateSamplePayload('subscription.cancelled', null)],
            'customer.created' => ['desc' => __('app.api.webhook_sim.desc_customer_created'), 'sample' => $this->generateSamplePayload('customer.created', null)],
            'device.activated' => ['desc' => __('app.api.webhook_sim.desc_device_activated'), 'sample' => $this->generateSamplePayload('device.activated', null)],
            'user.login' => ['desc' => __('app.api.webhook_sim.desc_user_login'), 'sample' => $this->generateSamplePayload('user.login', null)],
        ];

        if (! isset($info[$eventType])) {
            return ApiResponse::success([
                'event_type' => $eventType,
                'desc' => __('app.api.webhook_sim.desc_custom'),
                'sample' => $this->generateSamplePayload($eventType, null),
            ]);
        }

        return ApiResponse::success(array_merge(['event_type' => $eventType], $info[$eventType]));
    }
}
