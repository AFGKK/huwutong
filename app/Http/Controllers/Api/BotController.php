<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ConversationMessage;
use App\Models\ConversationParticipant;
use App\Models\UserConversation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class BotController extends Controller
{
    // ════════════════════════════════════════════
    // BOT-001~006: Bot 机器人系统
    // ════════════════════════════════════════════

    public function __construct()
    {
    }

    // BOT-001: 注册机器人
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:bots,name',
            'description' => 'nullable|string|max:500',
            'webhook_url' => 'nullable|url|max:500',
            'commands' => 'nullable|array',
            'commands.*.command' => 'required|string|max:50',
            'commands.*.description' => 'nullable|string|max:200',
        ]);

        $bot = \App\Models\Bot::create([
            'user_id' => auth()->id(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? '',
            'webhook_url' => $validated['webhook_url'] ?? '',
            'token' => \Illuminate\Support\Str::random(64),
            'commands' => $validated['commands'] ?? [],
            'is_active' => true,
            'is_public' => false,
        ]);

        return ApiResponse::success($bot, __("app.bot.msg_41dd9d0c"), 201);
    }

    // BOT-002: 生成/刷新 Token
    public function refreshToken(int $id): JsonResponse
    {
        $bot = \App\Models\Bot::where('user_id', auth()->id())->findOrFail($id);
        $bot->update(['token' => \Illuminate\Support\Str::random(64)]);
        return ApiResponse::success(['token' => $bot->token], __("app.bot.msg_20995de7"));
    }

    // BOT-003: 机器人列表
    public function index(): JsonResponse
    {
        $bots = \App\Models\Bot::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get();
        return ApiResponse::success($bots);
    }

    // BOT-004: 公开机器人市场
    public function marketplace(Request $request): JsonResponse
    {
        $query = \App\Models\Bot::where('is_public', true)->where('is_active', true);
        if ($q = $request->input('q')) {
            $query->where('name', 'like', "%{$q}%");
        }
        return ApiResponse::paginated($query->paginate(20));
    }

    // BOT-005: Webhook 接收
    public function webhook(Request $request): JsonResponse
    {
        $token = $request->bearerToken();
        $bot = \App\Models\Bot::where('token', $token)->where('is_active', true)->first();
        if (!$bot) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        $event = $request->input('event', 'message');
        $data = $request->input('data', []);

        // 处理消息事件
        if ($event === 'message' && !empty($data['content']) && !empty($data['conversation_id'])) {
            $msg = ConversationMessage::create([
                'conversation_id' => $data['conversation_id'],
                'sender_id' => $bot->user_id,
                'content' => $data['content'],
                'message_type' => $data['message_type'] ?? 'text',
                'client_msg_id' => 'bot-' . uniqid(),
            ]);

            // 如果有 webhook_url，转发消息
            if ($bot->webhook_url) {
                Http::timeout(5)->post($bot->webhook_url, [
                    'event' => 'message_sent',
                    'bot' => $bot->name,
                    'data' => ['message_id' => $msg->id, 'content' => $msg->content],
                ]);
            }

            return ApiResponse::success($msg->load('sender:id,name'), __('app.bot.message_sent'), 201);
        }

        return ApiResponse::success(null, 'Event ignored');
    }

    // BOT-006: 执行命令
    public function executeCommand(Request $request): JsonResponse
    {
        $request->validate([
            'conversation_id' => 'required|integer',
            'command' => 'required|string|max:500',
        ]);

        $text = $request->input('command');
        $convId = $request->input('conversation_id');

        // 解析 @bot 命令
        if (!preg_match('/^@(\w+)\s+(.+)/', $text, $matches)) {
            return ApiResponse::error('INVALID_COMMAND', __("app.bot.msg_c9a9b7cb"), 400);
        }

        $botName = $matches[1];
        $commandBody = $matches[2];

        $bot = \App\Models\Bot::where('name', $botName)->where('is_active', true)->first();
        if (!$bot) {
            return ApiResponse::error('BOT_NOT_FOUND', __("app.bot.msg_893b5dfa"), 404);
        }

        // 调用 Webhook
        if ($bot->webhook_url) {
            try {
                $response = Http::timeout(10)->post($bot->webhook_url, [
                    'event' => 'command',
                    'bot' => $bot->name,
                    'data' => [
                        'command' => $commandBody,
                        'conversation_id' => $convId,
                        'user_id' => auth()->id(),
                    ],
                ]);
                $reply = $response->json('reply', '');
            } catch (\Throwable $e) {
                $reply = '机器人暂时无法响应';
            }
        } else {
            $reply = '收到指令，但机器人未配置响应';
        }

        // 如果机器人返回了回复，发送到会话
        if ($reply) {
            $msg = ConversationMessage::create([
                'conversation_id' => $convId,
                'sender_id' => $bot->user_id,
                'content' => $reply,
                'message_type' => 'text',
                'client_msg_id' => 'bot-' . uniqid(),
            ]);
            return ApiResponse::success($msg->load('sender:id,name'), $reply);
        }

        return ApiResponse::success(null, __("app.bot.msg_553f2d98"));
    }
}
