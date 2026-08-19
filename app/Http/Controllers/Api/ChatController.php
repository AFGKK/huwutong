<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

        return response()->json(['success' => true, 'message' => __('app.controller_compat.chat_msg_121')]);
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
     * Handoff 规则配置已退役（IM 已无 Handoff 页；升级时创建工单）
     */
    public function handoffConfig(): JsonResponse
    {
        return $this->handoffConfigRetired();
    }

    /**
     * 保存 Handoff 规则配置已退役
     */
    public function saveHandoffConfig(Request $request): JsonResponse
    {
        return $this->handoffConfigRetired();
    }

    protected function handoffConfigRetired(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => __('app.api.im.handoff_config_retired'),
        ], 410);
    }
}
