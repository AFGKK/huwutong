<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\TeamsWebhook;
use App\Services\TeamsNotifierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TeamsNotifierController extends Controller
{
    public function __construct(protected TeamsNotifierService $teamsNotifier) {}

    /**
     * 仪表盘
     */
    public function dashboard(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->teamsNotifier->getDashboard($request->user()->tenant_id)
        );
    }

    /**
     * Webhook 列表
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->teamsNotifier->getWebhooks($request->user()->tenant_id)
        );
    }

    /**
     * 创建 Webhook
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'webhook_url' => 'required|url|max:500',
            'notification_type' => 'required|string|in:all,activation,alert,expiry',
            'is_active' => 'nullable|boolean',
            'filters' => 'nullable|array',
            'description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.teams_notifier.validation_failed'), $validator->errors()->toArray());
        }

        $data = $validator->validated();
        $data['tenant_id'] = $request->user()->tenant_id;
        $data['is_active'] = $data['is_active'] ?? true;

        $webhook = $this->teamsNotifier->createWebhook($data);
        return ApiResponse::created($webhook, __('app.api.teams_notifier.webhook_created'));
    }

    /**
     * 更新 Webhook
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $webhook = TeamsWebhook::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:100',
            'webhook_url' => 'sometimes|url|max:500',
            'notification_type' => 'sometimes|string|in:all,activation,alert,expiry',
            'is_active' => 'nullable|boolean',
            'filters' => 'nullable|array',
            'description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.teams_notifier.validation_failed'), $validator->errors()->toArray());
        }

        $webhook = $this->teamsNotifier->updateWebhook($webhook, $validator->validated());
        return ApiResponse::success($webhook, __('app.api.teams_notifier.config_updated'));
    }

    /**
     * 删除 Webhook
     */
    public function destroy(int $id): JsonResponse
    {
        $webhook = TeamsWebhook::findOrFail($id);
        $this->teamsNotifier->deleteWebhook($webhook);
        return ApiResponse::success(null, __('app.api.teams_notifier.config_deleted'));
    }

    /**
     * 测试连接
     */
    public function test(int $id): JsonResponse
    {
        $webhook = TeamsWebhook::findOrFail($id);
        $result = $this->teamsNotifier->testConnection($webhook);
        return $result['success']
            ? ApiResponse::success($result, __('app.api.teams_notifier.connection_ok'))
            : ApiResponse::error($result['message'], 400);
    }

    /**
     * 发送测试消息
     */
    public function sendTestMessage(Request $request, int $id): JsonResponse
    {
        $webhook = TeamsWebhook::findOrFail($id);

        $result = $this->teamsNotifier->send(
            $webhook->tenant_id,
            $webhook->notification_type === 'all' ? 'alert' : $webhook->notification_type,
            __('app.api.teams_notifier.test_title'),
            __('app.api.teams_notifier.test_body'),
            [
                ['title' => __('app.api.teams_notifier.field_channel'), 'value' => $webhook->name],
                ['title' => __('app.api.teams_notifier.field_type'), 'value' => $webhook->notification_type],
                ['title' => __('app.api.teams_notifier.field_time'), 'value' => now()->format('Y-m-d H:i:s')],
            ],
            $webhook->id
        );

        return $result['sent'] > 0
            ? ApiResponse::success(null, __('app.api.teams_notifier.test_sent'))
            : ApiResponse::error($result['errors'][0] ?? __('app.api.teams_notifier.send_failed'), 400);
    }

    /**
     * 发送激活通知（手动）
     */
    public function sendActivation(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'license_key' => 'required|string|max:100',
            'product_name' => 'required|string|max:200',
            'customer_name' => 'required|string|max:200',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.teams_notifier.validation_failed'), $validator->errors()->toArray());
        }

        $result = $this->teamsNotifier->sendActivationSuccess(
            $request->user()->tenant_id,
            $request->input('license_key'),
            $request->input('product_name'),
            $request->input('customer_name')
        );

        return ApiResponse::success($result);
    }

    /**
     * 发送告警通知（手动）
     */
    public function sendAlert(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:200',
            'message' => 'required|string|max:2000',
            'severity' => 'nullable|string|in:info,warning,critical',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.teams_notifier.validation_failed'), $validator->errors()->toArray());
        }

        $result = $this->teamsNotifier->sendAlert(
            $request->user()->tenant_id,
            $request->input('title'),
            $request->input('message'),
            $request->input('severity', 'warning')
        );

        return ApiResponse::success($result);
    }

    /**
     * 发送日志
     */
    public function logs(Request $request): JsonResponse
    {
        $params = array_merge($request->all(), [
            'tenant_id' => $request->user()->tenant_id,
        ]);
        return ApiResponse::success($this->teamsNotifier->getLogs($params));
    }

    /**
     * 配置信息
     */
    public function config(): JsonResponse
    {
        $notificationTypes = collect(config('teams-notifier.notification_types'))
            ->map(fn ($item) => [
                'key' => $item['label'],
                'label' => $item['label'],
                'enabled' => $item['enabled'],
                'title' => $item['title'],
            ])->values();

        return ApiResponse::success([
            'notification_types' => $notificationTypes,
            'theme_colors' => config('teams-notifier.theme_colors'),
            'rate_limit' => config('teams-notifier.rate_limit_per_minute'),
        ]);
    }
}
