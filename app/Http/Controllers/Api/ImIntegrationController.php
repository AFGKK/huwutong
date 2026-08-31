<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\SlackService;
use App\Services\DingTalkService;
use App\Services\WeComService;
use App\Services\LarkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImIntegrationController extends Controller
{
    public function __construct(
        protected SlackService $slackService,
        protected DingTalkService $dingTalkService,
        protected WeComService $weComService,
        protected LarkService $larkService,
    ) {}

    /**
     * 发送测试消息
     */
    public function testSlack(Request $request): JsonResponse
    {
        $data = $request->validate(['webhook_url' => 'required|url']);
        $result = $this->slackService->testConnection($data['webhook_url']);
        return $result['success']
            ? ApiResponse::success(null, $result['message'])
            : ApiResponse::error('TEST_FAILED', $result['message'], 400);
    }

    public function testDingTalk(Request $request): JsonResponse
    {
        $data = $request->validate(['webhook_url' => 'required|url']);
        $result = $this->dingTalkService->testConnection($data['webhook_url']);
        return $result['success']
            ? ApiResponse::success(null, $result['message'])
            : ApiResponse::error('TEST_FAILED', $result['message'], 400);
    }

    public function testWeCom(Request $request): JsonResponse
    {
        $data = $request->validate(['webhook_url' => 'required|url']);
        $result = $this->weComService->testConnection($data['webhook_url']);
        return $result['success']
            ? ApiResponse::success(null, $result['message'])
            : ApiResponse::error('TEST_FAILED', $result['message'], 400);
    }

    public function testFeishu(Request $request): JsonResponse
    {
        $data = $request->validate(['webhook_url' => 'required|url']);

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(10)->post($data['webhook_url'], [
                'msg_type' => 'interactive',
                'card' => [
                    'header' => [
                        'title' => ['tag' => 'plain_text', 'content' => '🔄 飞书连接测试'],
                        'template' => 'green',
                    ],
                    'elements' => [
                        ['tag' => 'div', 'text' => ['tag' => 'lark_md', 'content' => "测试时间: " . now()->format('Y-m-d H:i:s') . "\n状态: **连接成功** ✅"]],
                        ['tag' => 'hr'],
                        ['tag' => 'note', 'elements' => [
                            ['tag' => 'plain_text', 'content' => '互物通 · IM 集成测试'],
                        ]],
                    ],
                ],
            ]);

            if ($response->successful()) {
                return ApiResponse::success(null, __("app.im_integration.msg_0bee3b53"));
            }

            return ApiResponse::error('TEST_FAILED', __("app.im_integration.msg_a9641ef5"), 400);
        } catch (\Exception $e) {
            return ApiResponse::error('TEST_FAILED', __("app.im_integration.msg_f3ae29b5") . $e->getMessage(), 400);
        }
    }

    /**
     * 发送通知到指定 IM 渠道
     */
    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel' => 'required|string|in:slack,dingtalk,wecom,feishu',
            'webhook_url' => 'required|url',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'severity' => 'nullable|string|in:info,low,medium,high,critical',
        ]);

        $service = match ($data['channel']) {
            'slack' => $this->slackService,
            'dingtalk' => $this->dingTalkService,
            'wecom' => $this->weComService,
            'feishu' => $this->larkService,
        };

        if ($data['channel'] === 'feishu') {
            // 飞书使用 webhook 方式发送
            $ok = $this->larkService->sendWebhookMessage(
                (object) ['bot_webhook_url' => $data['webhook_url']],
                $data['title'],
                $data['content'],
                $data['severity'] ?? 'info',
            );
        } else {
            $ok = $service->send(
                $data['webhook_url'],
                $data['title'],
                $data['content'],
                $data['severity'] ?? 'info',
            );
        }

        return $ok
            ? ApiResponse::success(null, __("app.im_integration.msg_282c355b"))
            : ApiResponse::error('SEND_FAILED', __("app.im_integration.msg_419f6b96"), 500);
    }
}
