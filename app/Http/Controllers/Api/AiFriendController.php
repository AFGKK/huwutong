<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AiFriendProfile;
use App\Models\AiFriendLlmConfig;
use App\Models\User;
use App\Models\UserAiContact;
use App\Models\UserConversation;
use App\Models\ConversationMessage;
use App\Models\ConversationParticipant;
use App\Services\AiFriendOrchestrator;
use App\Services\LlmService;
use App\Services\SemanticCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AiFriendController extends Controller
{
    protected AiFriendOrchestrator $orchestrator;

    public function __construct(AiFriendOrchestrator $orchestrator)
    {
        $this->orchestrator = $orchestrator;
    }

    // ════════════════════════════════════════════
    // 管理后台：创建/编辑/发布 AI 好友
    // ════════════════════════════════════════════

    public function adminIndex(): JsonResponse
    {
        $friends = AiFriendProfile::with(['user', 'llmConfig'])->orderBy('created_at', 'desc')->paginate(20);
        return ApiResponse::paginated($friends);
    }

    public function adminStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'category' => 'nullable|string|in:assistant,translator,writer,custom_service,custom,commentator,moderator,reviewer',
            'description' => 'nullable|string|max:500',
            'welcome_message' => 'nullable|string|max:500',
            'visibility' => 'nullable|in:global,tenant,private',
            'avatar_url' => 'nullable|string|max:2000',
            // LLM 配置
            'provider' => 'required|in:openai,deepseek,claude,ollama,custom',
            'model_name' => 'required|string|max:100',
            'api_base_url' => 'nullable|string|max:500',
            'api_key' => 'nullable|string|max:500',
            'system_prompt' => 'nullable|string|max:5000',
            'temperature' => 'nullable|numeric|min:0|max:2',
            'max_tokens' => 'nullable|integer|min:100|max:32000',
            'context_window' => 'nullable|integer|min:5|max:100',
        ]);

        $myId = auth()->id();

        // 创建用户账号（AI 好友身份）
        $aiUser = User::create([
            'name' => $validated['name'],
            'email' => 'ai_friend_' . uniqid() . '@huwutong.ai',
            'user_type' => 'ai_friend',
            'password' => bcrypt(uniqid()),
            'status' => 'active',
        ]);

        // 生成头像：优先使用自定义头像
        if (!empty($validated['avatar_url'])) {
            $aiUser->avatar = $validated['avatar_url'];
        } else {
            $firstChar = mb_substr($validated['name'], 0, 1);
            $aiUser->avatar = 'https://ui-avatars.com/api/?name=' . urlencode($validated['name']) . '&background=409eff&color=fff&size=80';
        }
        $aiUser->save();

        // 创建 AI 好友档案
        $profile = AiFriendProfile::create([
            'user_id' => $aiUser->id,
            'visibility' => $validated['visibility'] ?? 'global',
            'creator_id' => $myId,
            'category' => $validated['category'] ?? 'assistant',
            'welcome_message' => $validated['welcome_message'] ?? '你好！我是' . $validated['name'] . '，有什么可以帮助你的吗？',
            'description' => $validated['description'] ?? '',
        ]);

        // 创建 LLM 配置
        $encryptedKey = null;
        if (!empty($validated['api_key'])) {
            $encryptedKey = encrypt($validated['api_key']);
        }
        AiFriendLlmConfig::create([
            'ai_friend_id' => $profile->id,
            'provider' => $validated['provider'],
            'model_name' => $validated['model_name'],
            'api_base_url' => $validated['api_base_url'] ?? null,
            'api_key_encrypted' => $encryptedKey,
            'system_prompt' => $validated['system_prompt'] ?? '你是一个友好的 AI 助手。',
            'temperature' => $validated['temperature'] ?? 0.7,
            'max_tokens' => $validated['max_tokens'] ?? 2048,
            'context_window' => $validated['context_window'] ?? 20,
        ]);

        return ApiResponse::success($profile->load('user', 'llmConfig'), 'AI 好友已创建', 201);
    }

    /**
     * 上传 AI 好友头像
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,gif,webp|max:2048',
        ]);

        $file = $request->file('avatar');
        $path = $file->store('ai-friends', 'public');

        if (!$path) {
            return ApiResponse::error('头像上传失败', 500);
        }

        $url = '/storage/' . $path;

        return ApiResponse::success(['url' => $url], '头像上传成功');
    }

    public function adminUpdate(int $id, Request $request): JsonResponse
    {
        $profile = AiFriendProfile::with('llmConfig', 'user')->findOrFail($id);

        $profile->update($request->only(['category', 'description', 'welcome_message', 'visibility']));

        if ($request->has('name') && $profile->user) {
            $profile->user->update(['name' => $request->input('name')]);
        }

        if ($profile->llmConfig) {
            $llmData = $request->only(['provider', 'model_name', 'api_base_url', 'system_prompt', 'temperature', 'max_tokens', 'context_window']);
            if ($request->has('api_key')) {
                $llmData['api_key_encrypted'] = encrypt($request->input('api_key'));
            }
            $profile->llmConfig->update($llmData);
        }

        return ApiResponse::success($profile->fresh()->load('user', 'llmConfig'), '已更新');
    }

    public function adminPublish(int $id): JsonResponse
    {
        $profile = AiFriendProfile::findOrFail($id);
        $profile->update(['published_at' => now()]);

        // AIF-003: 全局分发 - 懒加载方式（好友列表 API 合并）
        // 实际线上可用队列异步写入

        return ApiResponse::success(null, '已发布');
    }

    public function adminTest(int $id, Request $request, LlmService $llm): JsonResponse
    {
        $profile = AiFriendProfile::with('llmConfig', 'user')->findOrFail($id);
        if (!$profile->llmConfig) {
            return ApiResponse::error('请先配置模型', 400);
        }

        $result = $this->orchestrator->forFriend($profile)->testConnection();
        return $result['success']
            ? ApiResponse::success($result, '连接成功')
            : ApiResponse::error($result['message'], 400);
    }

    public function adminConversations(int $id): JsonResponse
    {
        $profile = AiFriendProfile::with('user')->findOrFail($id);

        // 查找所有与该 AI 好友的会话
        $convs = UserConversation::where('type', 'ai_friend')
            ->whereHas('participants', fn($q) => $q->where('user_id', $profile->user_id))
            ->with(['participants.user:id,name,avatar', 'messages' => fn($q) => $q->with('sender:id,name')->latest()->take(10)])
            ->withCount('messages')
            ->latest('updated_at')
            ->get()
            ->map(fn($conv) => [
                'id' => $conv->id,
                'user' => $conv->participants->first(fn($p) => $p->user_id !== $profile->user_id)?->user,
                'messages_count' => $conv->messages_count,
                'last_message' => $conv->messages->first()?->content,
                'last_time' => $conv->messages->first()?->created_at,
                'updated_at' => $conv->updated_at,
            ]);

        return ApiResponse::success($convs);
    }

    // ════════════════════════════════════════════

    public function myAiFriends(): JsonResponse
    {
        $myId = auth()->id();

        // 全局可见的已发布 AI 好友（懒加载：不需要预先写入 user_ai_contacts）
        $globalFriends = AiFriendProfile::with(['user', 'llmConfig'])
            ->global()
            ->get()
            ->map(function ($f) {
                return [
                    'id' => $f->id,
                    'user_id' => $f->user_id,
                    'name' => $f->user->name ?? 'AI 助手',
                    'avatar' => $f->user->avatar ?? '',
                    'category' => $f->category,
                    'description' => $f->description,
                    'welcome_message' => $f->welcome_message,
                    'is_ai_friend' => true,
                    'online_status' => 'online',
                ];
            });

        // 用户自建的私人 AI 好友
        $myContacts = UserAiContact::where('user_id', $myId)
            ->where('is_hidden', false)
            ->with('aiFriend.user')
            ->get()
            ->map(function ($c) {
                $f = $c->aiFriend;
                if (!$f) return null;
                return [
                    'id' => $f->id,
                    'user_id' => $f->user_id,
                    'name' => $c->remark_name ?? $f->user->name ?? 'AI 助手',
                    'avatar' => $f->user->avatar ?? '',
                    'category' => $f->category,
                    'description' => $f->description,
                    'welcome_message' => $f->welcome_message,
                    'is_ai_friend' => true,
                    'online_status' => 'online',
                    'source' => $c->source,
                ];
            })->filter()->values();

        // 合并：全局 + 个人（去重）
        $all = $globalFriends->concat($myContacts)->unique('id')->values();

        return ApiResponse::success($all);
    }

    // ════════════════════════════════════════════
    // 用户端：与 AI 好友对话（AIF-004）
    // ════════════════════════════════════════════

    public function chat(int $friendId, Request $request): JsonResponse
    {
        $validated = $request->validate(['message' => 'required|string|max:2000']);
        $myId = auth()->id();

        $profile = AiFriendProfile::with('llmConfig', 'user')->findOrFail($friendId);
        if (!$profile->llmConfig) {
            return ApiResponse::error('该 AI 好友暂未配置', 400);
        }

        // 查找或创建会话
        $conv = UserConversation::where('type', 'ai_friend')
            ->whereHas('participants', fn($q) => $q->where('user_id', $myId))
            ->whereHas('participants', fn($q) => $q->where('user_id', $profile->user_id))
            ->first();

        if (!$conv) {
            $conv = UserConversation::create([
                'type' => 'ai_friend',
                'name' => $profile->user->name ?? 'AI 好友',
                'created_by' => $myId,
            ]);
            ConversationParticipant::create(['conversation_id' => $conv->id, 'user_id' => $myId, 'role' => 'member']);
            ConversationParticipant::create(['conversation_id' => $conv->id, 'user_id' => $profile->user_id, 'role' => 'ai']);
        }

        // 保存用户消息
        ConversationMessage::create([
            'conversation_id' => $conv->id,
            'sender_id' => $myId,
            'content' => $validated['message'],
            'message_type' => 'text',
            'client_msg_id' => 'aif-user-' . uniqid(),
        ]);

        // 生成 AI 回复
        try {
            $result = $this->orchestrator->forFriend($profile)->generate($conv->id, $validated['message']);
            $reply = $result['content'] ?? '抱歉，我暂时无法回答。';
        } catch (\Throwable $e) {
            $reply = 'AI 暂时不可用，请稍后再试。';
            Log::error('[AiFriend] chat error: ' . $e->getMessage());
        }

        // 保存 AI 回复（DOM-004: AI 生成标识）
        $aiMsg = ConversationMessage::create([
            'conversation_id' => $conv->id,
            'sender_id' => $profile->user_id,
            'content' => $reply . "\n\n— 🤖 *AI 生成*",
            'message_type' => 'ai_reply',
            'client_msg_id' => 'aif-bot-' . uniqid(),
        ]);

        UserConversation::where('id', $conv->id)->update([
            'last_message_at' => now(),
            'last_read_at' => now(),
        ]);

        return ApiResponse::success([
            'reply' => $reply,
            'conversation_id' => $conv->id,
            'message' => $aiMsg->load('sender:id,name'),
        ]);
    }

    // ════════════════════════════════════════════
    // 用户端：流式对话 SSE
    // ════════════════════════════════════════════

    public function chatStream(int $friendId, Request $request): StreamedResponse
    {
        $validated = $request->validate(['message' => 'required|string|max:2000']);
        $myId = auth()->id();

        $profile = AiFriendProfile::with('llmConfig', 'user')->findOrFail($friendId);
        if (!$profile->llmConfig) {
            throw new \RuntimeException('AI 好友未配置');
        }

        // 查找或创建会话
        $conv = UserConversation::where('type', 'ai_friend')
            ->whereHas('participants', fn($q) => $q->where('user_id', $myId))
            ->whereHas('participants', fn($q) => $q->where('user_id', $profile->user_id))
            ->first();

        if (!$conv) {
            $conv = UserConversation::create([
                'type' => 'ai_friend',
                'name' => $profile->user->name ?? 'AI 好友',
                'created_by' => $myId,
            ]);
            ConversationParticipant::create(['conversation_id' => $conv->id, 'user_id' => $myId, 'role' => 'member']);
            ConversationParticipant::create(['conversation_id' => $conv->id, 'user_id' => $profile->user_id, 'role' => 'ai']);
        }

        // 保存用户消息
        ConversationMessage::create([
            'conversation_id' => $conv->id,
            'sender_id' => $myId,
            'content' => $validated['message'],
            'message_type' => 'text',
            'client_msg_id' => 'aif-user-' . uniqid(),
        ]);

        return response()->stream(function () use ($profile, $conv, $myId) {
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('X-Accel-Buffering: no');

            try {
                $stream = $this->orchestrator->forFriend($profile)->generateStream($conv->id, request()->input('message'));
                $fullContent = '';
                foreach ($stream as $chunk) {
                    $text = $chunk['content'] ?? '';
                    $fullContent .= $text;
                    echo "data: " . json_encode(['type' => 'chunk', 'content' => $text]) . "\n\n";
                    ob_flush();
                    flush();
                }

                // 保存 AI 回复（DOM-004）
                $aiContent = $fullContent . "\n\n— 🤖 *AI 生成*";
                ConversationMessage::create([
                    'conversation_id' => $conv->id,
                    'sender_id' => $profile->user_id,
                    'content' => $aiContent,
                    'message_type' => 'ai_reply',
                    'client_msg_id' => 'aif-bot-' . uniqid(),
                ]);

                UserConversation::where('id', $conv->id)->update([
                    'last_message_at' => now(),
                    'last_read_at' => now(),
                ]);

                echo "data: " . json_encode(['type' => 'done', 'content' => $fullContent]) . "\n\n";
            } catch (\Throwable $e) {
                echo "data: " . json_encode(['type' => 'error', 'message' => $e->getMessage()]) . "\n\n";
            }
            ob_flush();
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    // ════════════════════════════════════════════
    // 用户自建 AI 好友（BYOK）
    // ════════════════════════════════════════════

    public function createPersonal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'provider' => 'required|in:openai,deepseek,claude,custom',
            'model_name' => 'required|string|max:100',
            'api_key' => 'required|string|max:500',
            'api_base_url' => 'nullable|string|max:500',
            'system_prompt' => 'nullable|string|max:5000',
            'avatar_url' => 'nullable|string|max:2000',
        ]);

        $myId = auth()->id();

        // 创建 AI 用户
        $aiUser = User::create([
            'name' => $validated['name'],
            'email' => 'ai_friend_personal_' . $myId . '_' . uniqid() . '@local.ai',
            'user_type' => 'ai_friend',
            'password' => bcrypt(uniqid()),
            'status' => 'active',
        ]);
        // 生成头像：优先使用自定义头像
        if (!empty($validated['avatar_url'])) {
            $aiUser->avatar = $validated['avatar_url'];
        } else {
            $aiUser->avatar = 'https://ui-avatars.com/api/?name=' . urlencode($validated['name']) . '&background=67c23a&color=fff&size=80';
        }
        $aiUser->save();

        // 创建档案（private 仅自己可见）
        $profile = AiFriendProfile::create([
            'user_id' => $aiUser->id,
            'visibility' => 'private',
            'creator_id' => $myId,
            'category' => 'custom',
            'welcome_message' => '你好！我是你的私人 AI 好友 ' . $validated['name'] . '。',
            'description' => '个人创建的 AI 好友',
            'published_at' => now(),
        ]);

        // LLM 配置
        AiFriendLlmConfig::create([
            'ai_friend_id' => $profile->id,
            'provider' => $validated['provider'],
            'model_name' => $validated['model_name'],
            'api_base_url' => $validated['api_base_url'] ?? null,
            'api_key_encrypted' => encrypt($validated['api_key']),
            'system_prompt' => $validated['system_prompt'] ?? '你是一个友好的 AI 助手。',
            'temperature' => 0.7,
            'max_tokens' => 2048,
            'context_window' => 20,
        ]);

        // 写入联系人
        UserAiContact::create([
            'user_id' => $myId,
            'ai_friend_id' => $profile->id,
            'source' => 'user_created',
        ]);

        return ApiResponse::success($profile->load('user', 'llmConfig'), '私人 AI 好友已创建', 201);
    }

    public function togglePin(int $id): JsonResponse
    {
        $myId = auth()->id();
        $contact = UserAiContact::where('user_id', $myId)->where('ai_friend_id', $id)->first();
        if (!$contact) return ApiResponse::error('未找到联系人', 404);
        $contact->update(['is_pinned' => !$contact->is_pinned]);
        return ApiResponse::success(['is_pinned' => $contact->fresh()->is_pinned]);
    }

    public function toggleHide(int $id): JsonResponse
    {
        $myId = auth()->id();
        $contact = UserAiContact::where('user_id', $myId)->where('ai_friend_id', $id)->first();
        if (!$contact) return ApiResponse::error('未找到联系人', 404);
        $contact->update(['is_hidden' => !$contact->is_hidden]);
        return ApiResponse::success(['is_hidden' => $contact->fresh()->is_hidden]);
    }

    /**
     * 删除个人 AI 好友
     */
    public function deletePersonal(int $id): JsonResponse
    {
        $myId = auth()->id();
        $contact = UserAiContact::where('user_id', $myId)->where('ai_friend_id', $id)->first();
        if (!$contact) return ApiResponse::error('未找到联系人', 404);

        $profile = AiFriendProfile::find($id);
        if (!$profile) return ApiResponse::error('AI 好友不存在', 404);
        if ($profile->visibility === 'global') {
            return ApiResponse::error('不能删除平台 AI 好友', 403);
        }

        // 删除关联数据
        $contact->delete();
        if ($profile->user) {
            $profile->user->delete();
        }
        $profile->llmConfig()->delete();
        $profile->delete();

        return ApiResponse::success(null, 'AI 好友已删除');
    }
}
