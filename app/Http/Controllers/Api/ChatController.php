<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Services\ChatDialogEngineService;
use App\Services\IntentRecognizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    public function __construct(
        protected ChatDialogEngineService $chatService,
        protected IntentRecognizer $intentRecognizer,
    ) {}

    /**
     * 发送消息（非流式）
     */
    public function send(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|min:1|max:2000',
            'session_id' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $result = $this->chatService->processMessage(
            $request->input('message'),
            $request->input('session_id'),
            ['user_id' => $request->user()?->id]
        );

        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * 发送消息（流式，SSE）
     */
    public function sendStream(Request $request): StreamedResponse
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|min:1|max:2000',
            'session_id' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $message = $request->input('message');
        $sessionId = $request->input('session_id');

        $response = new StreamedResponse(function () use ($message, $sessionId) {
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('X-Accel-Buffering: no');

            foreach ($this->chatService->processMessageStreamed($message, $sessionId) as $chunk) {
                echo "data: {$chunk}\n\n";
                ob_flush();
                flush();
            }

            echo "data: [DONE]\n\n";
            ob_flush();
            flush();
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }

    /**
     * 获取对话历史
     */
    public function history(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'session_id' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $messages = $this->chatService->getHistory($request->input('session_id'));

        return response()->json(['success' => true, 'data' => $messages]);
    }

    /**
     * 满意度反馈
     */
    public function feedback(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'message_id' => 'required|integer|exists:rag_messages,id',
            'satisfied' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $this->chatService->recordSatisfaction(
            $request->input('message_id'),
            $request->input('satisfied')
        );

        return response()->json(['success' => true, 'message' => '感谢您的反馈']);
    }

    /**
     * 获取支持的意图列表
     */
    public function intents(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->intentRecognizer->getIntents(),
        ]);
    }

    /**
     * 统计
     */
    public function stats(): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\RagConversation::class);

        return response()->json([
            'success' => true,
            'data' => $this->chatService->getStats(),
        ]);
    }

    /**
     * Handoff 转人工规则配置
     */
    public function handoffConfig(): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\RagConversation::class);

        return response()->json([
            'success' => true,
            'data' => $this->resolveHandoffConfig(),
        ]);
    }

    /**
     * 保存 Handoff 转人工规则配置
     */
    public function saveHandoffConfig(Request $request): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\RagConversation::class);

        $validated = $request->validate([
            'confidence_threshold' => 'required|numeric|min:0|max:1',
            'timeout_seconds' => 'required|integer|min:30|max:300',
            'escalate_intents' => 'array',
            'escalate_intents.*' => 'string|max:100',
        ]);

        SiteSetting::updateOrCreate(
            ['key' => 'chat_handoff_config'],
            [
                'group' => 'ai',
                'value' => json_encode($validated, JSON_UNESCAPED_UNICODE),
                'type' => 'json',
                'description' => 'AI 转人工 Handoff 规则',
                'is_public' => false,
            ]
        );

        return response()->json(['success' => true, 'message' => 'Handoff 配置已保存', 'data' => $validated]);
    }

    /**
     * @return array{confidence_threshold: float, timeout_seconds: int, escalate_intents: array<int, string>}
     */
    protected function resolveHandoffConfig(): array
    {
        $defaults = [
            'confidence_threshold' => 0.35,
            'timeout_seconds' => 120,
            'escalate_intents' => ['refund_request', 'complaint'],
        ];

        $raw = SiteSetting::where('key', 'chat_handoff_config')->value('value');
        if (! $raw) {
            return $defaults;
        }

        $stored = json_decode($raw, true);
        if (! is_array($stored)) {
            return $defaults;
        }

        return array_merge($defaults, $stored);
    }
}
