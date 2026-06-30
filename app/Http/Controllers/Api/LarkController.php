<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\LarkIntegration;
use App\Services\LarkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LarkController extends Controller
{
    public function __construct(
        protected LarkService $larkService,
    ) {}

    /**
     * 获取飞书集成配置
     * GET /api/admin/lark/config
     */
    public function config(): JsonResponse
    {
        $integration = $this->larkService->getIntegration();

        if (!$integration) {
            return ApiResponse::success([
                'configured' => false,
                'data' => null,
            ]);
        }

        return ApiResponse::success([
            'configured' => true,
            'data' => $integration->only([
                'id', 'name', 'is_enabled', 'app_id',
                'bot_webhook_url', 'notify_enabled',
                'created_at', 'updated_at',
            ]),
        ]);
    }

    /**
     * 保存飞书集成配置
     * POST /api/admin/lark/config
     */
    public function saveConfig(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:100',
            'is_enabled' => 'nullable|boolean',
            'app_id' => 'nullable|string|max:100',
            'app_secret' => 'nullable|string|max:500',
            'encrypt_key' => 'nullable|string|max:100',
            'verification_token' => 'nullable|string|max:100',
            'bot_webhook_url' => 'nullable|string|url|max:500',
            'notify_enabled' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('参数验证失败', $validator->errors()->toArray());
        }

        $integration = $this->larkService->saveIntegration($validator->validated());

        return ApiResponse::success([
            'configured' => true,
            'data' => $integration->only([
                'id', 'name', 'is_enabled', 'app_id',
                'bot_webhook_url', 'notify_enabled',
                'created_at', 'updated_at',
            ]),
        ], '飞书集成配置已保存');
    }

    /**
     * 测试飞书连接
     * POST /api/admin/lark/test
     */
    public function testConnection(): JsonResponse
    {
        $integration = $this->larkService->getIntegration();

        if (!$integration) {
            return ApiResponse::error('NOT_CONFIGURED', '请先配置飞书集成', 400);
        }

        $result = $this->larkService->testConnection($integration);

        if ($result['success']) {
            return ApiResponse::success($result, '连接测试通过');
        }

        return ApiResponse::error('CONNECTION_FAILED', $result['message'], 400, $result);
    }

    /**
     * 发送测试消息
     * POST /api/admin/lark/test-message
     */
    public function sendTestMessage(Request $request): JsonResponse
    {
        $integration = $this->larkService->getIntegration();

        if (!$integration) {
            return ApiResponse::error('NOT_CONFIGURED', '请先配置飞书集成', 400);
        }

        $validator = Validator::make($request->all(), [
            'type' => 'nullable|string|in:webhook,user,group',
            'target' => 'nullable|string|max:100',
            'message' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('参数验证失败', $validator->errors()->toArray());
        }

        $type = $request->input('type', 'webhook');
        $message = $request->input('message', '这是一条来自互物通的测试消息 ✅');

        $success = match ($type) {
            'user' => $this->larkService->sendUserMessage(
                $integration,
                $request->input('target', ''),
                '飞书集成测试',
                $message
            ),
            'group' => $this->larkService->sendGroupMessage(
                $integration,
                $request->input('target', ''),
                '飞书集成测试',
                $message
            ),
            default => $this->larkService->sendWebhookMessage(
                $integration,
                '飞书集成测试',
                $message,
                'info'
            ),
        };

        if ($success) {
            return ApiResponse::success(null, '测试消息已发送');
        }

        return ApiResponse::error('SEND_FAILED', '消息发送失败', 400);
    }

    /**
     * 获取飞书 API 参考信息
     * GET /api/admin/lark/reference
     */
    public function reference(): JsonResponse
    {
        return ApiResponse::success([
            'base_url' => LarkService::BASE_URL,
            'api_endpoints' => [
                'tenant_token' => '/auth/v3/tenant_access_token/internal',
                'send_user_message' => '/im/v1/messages?receive_id_type=open_id',
                'send_group_message' => '/im/v1/messages?receive_id_type=chat_id',
                'user_info' => '/authen/v1/user_info',
                'access_token' => '/authen/v1/access_token',
            ],
            'docs_url' => 'https://open.feishu.cn/document',
            'app_console_url' => 'https://open.feishu.cn/app',
        ]);
    }
}
