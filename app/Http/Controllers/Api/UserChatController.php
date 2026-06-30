<?php

namespace App\Http\Controllers\Api;

use App\Events\ChatMessageSent;
use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ConversationMessage;
use App\Models\ConversationParticipant;
use App\Models\ForumPost;
use App\Models\User;
use App\Models\UserConversation;
use App\Models\UserFriend;
use App\Models\UserReport;
use App\Models\UserPrivacySetting;
use App\Models\ConversationPoll;
use App\Models\ConversationPollVote;
use App\Models\UserOnlineStatus;
use App\Models\FriendGroup;
use App\Models\MessageReaction;
use App\Models\MessageFavorite;
use App\Models\MessagePending;
use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\GroupInvite;
use App\Models\SensitiveWord;
use App\Models\CardConversionTracking;
use App\Services\SensitiveWordService;
use App\Services\LinkPreviewService;
use App\Services\LlmService;
use App\Services\TranslationEngineService;
use App\Services\SemanticCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserChatController extends Controller
{
    /**
     * 创建或获取单聊会话
     */
    public function createConversation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'participant_ids' => 'required|array|min:1|max:50',
            'participant_ids.*' => 'exists:users,id',
        ]);

        $myId = auth()->id();
        $allIds = array_unique(array_merge([$myId], $validated['participant_ids']));
        sort($allIds);

        // 单聊：查找是否已有会话
        if (count($allIds) === 2) {
            $existing = DB::table('conversation_participants AS cp1')
                ->join('conversation_participants AS cp2', 'cp1.conversation_id', '=', 'cp2.conversation_id')
                ->where('cp1.user_id', $allIds[0])
                ->where('cp2.user_id', $allIds[1])
                ->where('cp1.deleted_at', null)
                ->where('cp2.deleted_at', null)
                ->join('user_conversations', 'cp1.conversation_id', '=', 'user_conversations.id')
                ->where('user_conversations.type', 'private')
                ->select('user_conversations.id')
                ->first();

            if ($existing) {
                return ApiResponse::success($this->loadConversation($existing->id));
            }
        }

        // 创建新会话
        $conv = UserConversation::create([
            'type' => count($allIds) === 2 ? 'private' : 'group',
            'created_by' => $myId,
        ]);

        foreach ($allIds as $uid) {
            $role = ($uid === $myId && count($allIds) > 2) ? 'creator' : 'member';
            ConversationParticipant::create(['conversation_id' => $conv->id, 'user_id' => $uid, 'role' => $role]);
        }

        return ApiResponse::success($this->loadConversation($conv->id), '会话已创建', 201);
    }

    /**
     * 我的会话列表
     */
    public function myConversations(): JsonResponse
    {
        $myId = auth()->id();
        $participations = ConversationParticipant::where('user_id', $myId)
            ->whereNull('deleted_at')
            ->with(['conversation.lastMessage.sender:id,name,avatar', 'conversation.participants.user:id,name,avatar'])
            ->orderBy('is_pinned', 'desc')
            ->orderBy('updated_at', 'desc')
            ->get();

        $result = $participations->map(function ($p) {
            $conv = $p->conversation;
            if (!$conv) return null;
            $otherUsers = $conv->participants->filter(fn($cp) => $cp->user_id !== auth()->id())->pluck('user');
            $isSelfConv = $conv->participants->count() === 1 && $conv->participants->first()?->user_id === auth()->id();
            return [
                'id' => $conv->id,
                'type' => $conv->type,
                'name' => $isSelfConv ? '📁 文件传输助手' : ($conv->type === 'private' ? ($otherUsers->first()?->name ?? '用户') : $conv->name),
                'avatar' => $conv->type === 'private' ? ($otherUsers->first()?->avatar_url ?? '') : '',
                'last_message' => $conv->lastMessage ? [
                    'content' => $conv->lastMessage->content,
                    'sender_name' => $conv->lastMessage->sender?->name ?? '',
                    'created_at' => $conv->lastMessage->created_at,
                ] : null,
                'unread_count' => $p->unread_count,
                'is_pinned' => $p->is_pinned,
                'is_muted' => $p->is_muted,
                'is_archived' => !is_null($p->archived_at),
                'is_hidden' => $p->is_hidden,
                'my_role' => $p->role,
                'updated_at' => $conv->updated_at,
                'participants' => $conv->participants->map(fn($cp) => [
                    'id' => $cp->user_id,
                    'name' => $cp->user?->name ?? '',
                    'avatar' => $cp->user?->avatar_url ?? '',
                ]),
            ];
        })->filter()->values();

        return ApiResponse::success($result);
    }

    /**
     * 获取或创建文件传输助手（与自己的会话）
     */
    public function selfConversation(): JsonResponse
    {
        $myId = auth()->id();

        // 查找已有的单人会话
        $existingConv = DB::table('conversation_participants')
            ->select('conversation_id')
            ->where('conversation_participants.user_id', $myId)
            ->whereNull('conversation_participants.deleted_at')
            ->whereRaw('(SELECT COUNT(*) FROM conversation_participants cp2 WHERE cp2.conversation_id = conversation_participants.conversation_id AND cp2.deleted_at IS NULL) = 1')
            ->join('user_conversations', 'conversation_participants.conversation_id', '=', 'user_conversations.id')
            ->where('user_conversations.type', 'private')
            ->whereNull('user_conversations.deleted_at')
            ->value('conversation_id');

        if ($existingConv) {
            return ApiResponse::success($this->loadConversation($existingConv));
        }

        // 创建新的单人会话
        $conv = UserConversation::create([
            'type' => 'private',
            'created_by' => $myId,
        ]);
        ConversationParticipant::create(['conversation_id' => $conv->id, 'user_id' => $myId]);

        return ApiResponse::success($this->loadConversation($conv->id), '文件传输助手已创建', 201);
    }

    /**
     * 发送消息
     */
    public function sendMessage(int $convId, Request $request): JsonResponse
    {
        $conv = UserConversation::findOrFail($convId);
        $myId = auth()->id();

        // 验证是否参与者
        $isParticipant = ConversationParticipant::where('conversation_id', $convId)
            ->where('user_id', $myId)->whereNull('deleted_at')->exists();
        if (!$isParticipant) {
            return ApiResponse::error('你不是该会话的参与者');
        }

        // 群聊权限校验
        if ($conv->type === 'group') {
            $messageType = $request->input('message_type', 'text');
            if (in_array($messageType, ['file', 'image', 'voice']) && !$conv->userCan('send_file', $myId)) {
                return ApiResponse::error('你没有权限在此群发送文件');
            }
            if ($messageType === 'card' && !$conv->userCan('send_card', $myId)) {
                return ApiResponse::error('你没有权限在此群发送卡片');
            }
        }

        // 私聊检查是否为好友
        if ($conv->type === 'private') {
            $otherParticipant = ConversationParticipant::where('conversation_id', $convId)
                ->where('user_id', '!=', $myId)->first();
            if ($otherParticipant) {
                $isFriend = UserFriend::where('status', 'accepted')
                    ->where(function ($q) use ($myId, $otherParticipant) {
                        $q->where(['requester_id' => $myId, 'addressee_id' => $otherParticipant->user_id])
                          ->orWhere(['requester_id' => $otherParticipant->user_id, 'addressee_id' => $myId]);
                    })->exists();
                if (!$isFriend) {
                    return ApiResponse::error('请先添加对方为好友');
                }
            }
        }

        // 慢速模式检查
        if ($conv->slow_mode_interval > 0) {
            $participant = ConversationParticipant::where('conversation_id', $convId)
                ->where('user_id', $myId)->first();
            if ($participant && $participant->slow_mode_until && $participant->slow_mode_until->isFuture()) {
                $waitSeconds = now()->diffInSeconds($participant->slow_mode_until);
                return ApiResponse::error("慢速模式中，请 {$waitSeconds} 秒后再发");
            }
        }

        $validated = $request->validate([
            'content' => 'required_if:message_type,text|string|max:10000',
            'message_type' => 'nullable|string|in:text,image,file,voice,contact,location',
            'attachments' => 'nullable|array',
            'reply_to_id' => 'nullable|exists:conversation_messages,id',
            'client_msg_id' => 'nullable|string|max:64',
            'confirmed' => 'nullable|boolean',
            'expires_in_minutes' => 'nullable|integer|min:1|max:43200',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $validated['message_type'] ??= 'text';
        $validated['conversation_id'] = $convId;
        $validated['sender_id'] = $myId;

        // PRAC-006: 记录二次确认时间
        if (!empty($validated['confirmed'])) {
            $validated['confirmed_at'] = now();
        }
        unset($validated['confirmed']);

        // 消息幂等去重
        if (!empty($validated['client_msg_id'])) {
            $existing = ConversationMessage::where('conversation_id', $convId)
                ->where('client_msg_id', $validated['client_msg_id'])->first();
            if ($existing) {
                return ApiResponse::success($existing->load('sender:id,name,avatar'), '消息已存在（幂等）');
            }
        }

        // 敏感词过滤
        $svc = app(SensitiveWordService::class);
        if (!empty($validated['content'])) {
            $filter = $svc->check($validated['content']);
            if ($filter['hasSensitive']) {
                $validated['content'] = $filter['replaced'];
            }
        }
        // 过滤附件中的文件名
        if (!empty($validated['attachments'])) {
            foreach ($validated['attachments'] as &$att) {
                if (!empty($att['name'])) {
                    $filter = $svc->check($att['name']);
                    if ($filter['hasSensitive']) {
                        $att['name'] = $filter['replaced'];
                    }
                }
            }
        }
        // 过滤 metadata 中的文本字段
        if (!empty($validated['metadata'])) {
            foreach (['title', 'description', 'remark', 'note'] as $metaField) {
                if (!empty($validated['metadata'][$metaField])) {
                    $filter = $svc->check($validated['metadata'][$metaField]);
                    if ($filter['hasSensitive']) {
                        $validated['metadata'][$metaField] = $filter['replaced'];
                    }
                }
            }
        }

        // ── AI-044 发送前 AI 预审 ──
        if (config('ai-message-review.enabled', true)
            && $validated['message_type'] === 'text'
            && !empty($validated['content'])
        ) {
            try {
                $reviewService = app(\App\Services\AiMessageReviewService::class);
                $reviewResult = $reviewService->review($validated['content']);
                if (!empty($reviewResult['warnings'])) {
                    $validated['metadata'] = array_merge($validated['metadata'] ?? [], [
                        'ai_review' => [
                            'review_id' => $reviewResult['review_id'],
                            'level' => $reviewResult['level'],
                            'action' => $reviewResult['action'],
                            'pass' => $reviewResult['pass'],
                            'warnings' => $reviewResult['warnings'],
                            'categories' => $reviewResult['categories'],
                            'reviewed_at' => now()->toDateTimeString(),
                        ],
                    ]);
                    // 如果用户选择了"强制发送"，记录到 review_override
                    if ($request->boolean('review_override')) {
                        $validated['metadata']['ai_review']['overridden'] = true;
                        $validated['metadata']['ai_review']['overridden_at'] = now()->toDateTimeString();
                    }
                }
            } catch (\Throwable $e) {
                // AI 预审失败静默降级，不影响消息发送
                \Illuminate\Support\Facades\Log::warning('AI 预审跳过', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // ── 消息定时销毁：计算 expires_at ──
        if (config('message-expiry.enabled', true)) {
            if ($request->filled('expires_in_minutes')) {
                $validated['expires_at'] = now()->addMinutes((int) $request->input('expires_in_minutes'));
            } elseif (empty($validated['expires_at'])) {
                // 根据消息类型应用默认 TTL
                $type = $validated['message_type'] ?? 'text';
                $ttlDays = config("message-expiry.default_ttl.{$type}");
                if ($ttlDays !== null) {
                    $validated['expires_at'] = now()->addDays((int) $ttlDays);
                }
            }
        }

        $msg = ConversationMessage::create($validated);

        // MSG-005: 初始状态
        $msg->update(['deliver_status' => 'sent']);

        // 慢速模式：更新发言时间
        if ($conv->slow_mode_interval > 0) {
            ConversationParticipant::where('conversation_id', $convId)
                ->where('user_id', $myId)
                ->update(['slow_mode_until' => now()->addSeconds($conv->slow_mode_interval)]);
        }

        // 更新会话最后消息
        $conv->update(['last_message_id' => $msg->id, 'last_message_at' => now(), 'updated_at' => now()]);

        // 增加其他参与者的未读计数
        $otherParticipants = ConversationParticipant::where('conversation_id', $convId)
            ->where('user_id', '!=', $myId)->whereNull('deleted_at')->get();
        foreach ($otherParticipants as $p) {
            $p->increment('unread_count');
        }

        // 实时推送
        $allParticipantIds = $otherParticipants->pluck('user_id')->push($myId)->unique()->values()->toArray();
        event(new ChatMessageSent($msg->load('sender:id,name,avatar'), $allParticipantIds));

        // AI-016: 自动评估消息紧急度（仅文本消息）
        if ($validated['message_type'] === 'text' && !empty($validated['content'])) {
            try {
                $urgencyResult = $this->evaluateUrgencyInternal($validated['content']);
                $msg->update(['metadata' => array_merge($msg->metadata ?? [], [
                    'priority' => $urgencyResult['priority'],
                    'bypass_dnd' => $urgencyResult['bypass_dnd'],
                ])]);
            } catch (\Throwable $e) {
                // 静默失败，不影响消息发送
            }
        }

        // PRAC-004: 触发自动回复
        if ($validated['message_type'] === 'text' && !empty($validated['content'])) {
            try {
                app(\App\Services\UserAutoReplyService::class)->checkAndReply($msg->id);
            } catch (\Throwable $e) {
                // 静默失败
            }
        }

        return ApiResponse::success($msg->load('sender:id,name,avatar'), '消息已发送', 201);
    }

    /**
     * 获取消息历史
     */
    public function getMessages(int $convId, Request $request): JsonResponse
    {
        UserConversation::findOrFail($convId);
        $perPage = min((int) $request->input('per_page', 50), 200);
        $beforeId = $request->input('before_id');

        $query = ConversationMessage::with('sender:id,name,avatar')
            ->where('conversation_id', $convId)
            ->whereNull('deleted_at');

        if ($beforeId) {
            $query->where('id', '<', $beforeId);
        }

        $messages = $query->orderBy('id', 'desc')->paginate($perPage);

        return ApiResponse::paginated($messages);
    }

    /**
     * 标记已读
     */
    public function markRead(int $convId): JsonResponse
    {
        $myId = auth()->id();
        $participant = ConversationParticipant::where('conversation_id', $convId)
            ->where('user_id', $myId)->firstOrFail();

        $participant->update(['unread_count' => 0, 'last_read_at' => now()]);

        return ApiResponse::success(null, '已标记已读');
    }

    /**
     * 搜索用户
     */
    public function searchUsers(Request $request): JsonResponse
    {
        $keyword = $request->input('q') ?? $request->input('keyword', '');
        if (empty($keyword) || mb_strlen($keyword) > 100) {
            return ApiResponse::success([]);
        }
        $users = User::where('id', '!=', auth()->id())
            ->where(function ($q) use ($keyword) {
                $q->where('name', 'like', '%' . $keyword . '%')
                  ->orWhere('email', 'like', '%' . $keyword . '%')
                  ->orWhere('phone', 'like', '%' . $keyword . '%');
            })
            ->limit(20)
            ->get(['id', 'name', 'email', 'avatar']);

        return ApiResponse::success($users);
    }

    /**
     * 置顶/取消置顶
     */
    public function togglePin(int $convId): JsonResponse
    {
        $p = ConversationParticipant::where('conversation_id', $convId)->where('user_id', auth()->id())->firstOrFail();
        $p->update(['is_pinned' => !$p->is_pinned]);
        return ApiResponse::success(['is_pinned' => $p->fresh()->is_pinned]);
    }

    /**
     * 免打扰切换
     */
    public function toggleMute(int $convId): JsonResponse
    {
        $p = ConversationParticipant::where('conversation_id', $convId)->where('user_id', auth()->id())->firstOrFail();
        $p->update(['is_muted' => !$p->is_muted]);
        return ApiResponse::success(['is_muted' => $p->fresh()->is_muted]);
    }

    /**
     * 删除会话
     */
    public function deleteConversation(int $convId): JsonResponse
    {
        ConversationParticipant::where('conversation_id', $convId)
            ->where('user_id', auth()->id())
            ->update(['deleted_at' => now()]);
        return ApiResponse::success(null, '会话已删除');
    }

    // ── 会话归档 ──

    /**
     * 归档会话
     */
    public function archiveConversation(int $convId): JsonResponse
    {
        ConversationParticipant::where('conversation_id', $convId)
            ->where('user_id', auth()->id())
            ->update(['archived_at' => now()]);
        return ApiResponse::success(null, '会话已归档');
    }

    /**
     * 取消归档
     */
    public function unarchiveConversation(int $convId): JsonResponse
    {
        ConversationParticipant::where('conversation_id', $convId)
            ->where('user_id', auth()->id())
            ->update(['archived_at' => null]);
        return ApiResponse::success(null, '已取消归档');
    }

    /**
     * 批量归档（30天无更新会话）
     */
    public function batchArchive(Request $request): JsonResponse
    {
        $myId = auth()->id();
        $days = min((int) $request->input('days', 30), 365);
        $convIds = $request->input('conversation_ids', []);

        $query = ConversationParticipant::where('user_id', $myId)
            ->whereNull('deleted_at')
            ->whereNull('archived_at');

        if (!empty($convIds)) {
            $query->whereIn('conversation_id', $convIds);
        } else {
            // 自动归档：超过 N 天无更新的会话
            $cutoff = now()->subDays($days);
            $query->whereHas('conversation', function ($q) use ($cutoff) {
                $q->where(function ($sub) use ($cutoff) {
                    $sub->whereNull('last_message_at')
                        ->orWhere('last_message_at', '<', $cutoff);
                });
            });
        }

        $count = $query->update(['archived_at' => now()]);

        return ApiResponse::success(['archived_count' => $count], "已归档 {$count} 个会话");
    }

    /**
     * 获取归档会话列表
     */
    public function archivedConversations(): JsonResponse
    {
        $convIds = ConversationParticipant::where('user_id', auth()->id())
            ->whereNotNull('archived_at')
            ->whereNull('deleted_at')
            ->pluck('conversation_id');

        $convs = UserConversation::whereIn('id', $convIds)
            ->orderByDesc('last_message_at')
            ->get();

        return ApiResponse::success($convs);
    }

    // ── 私密空间 / 隐藏会话 ──

    /**
     * 隐藏会话
     */
    public function hideConversation(int $convId): JsonResponse
    {
        ConversationParticipant::where('conversation_id', $convId)
            ->where('user_id', auth()->id())
            ->update(['is_hidden' => true, 'hidden_at' => now()]);
        return ApiResponse::success(null, '会话已隐藏');
    }

    /**
     * 取消隐藏
     */
    public function unhideConversation(int $convId): JsonResponse
    {
        ConversationParticipant::where('conversation_id', $convId)
            ->where('user_id', auth()->id())
            ->update(['is_hidden' => false, 'hidden_at' => null]);
        return ApiResponse::success(null, '已取消隐藏');
    }

    /**
     * 获取隐藏会话列表（需 PIN 验证）
     */
    public function hiddenConversations(): JsonResponse
    {
        $convIds = ConversationParticipant::where('user_id', auth()->id())
            ->where('is_hidden', true)
            ->whereNull('deleted_at')
            ->pluck('conversation_id');

        $convs = UserConversation::whereIn('id', $convIds)
            ->orderByDesc('last_message_at')
            ->get();

        return ApiResponse::success($convs);
    }

    /**
     * 设置私密空间 PIN
     */
    public function setPrivacyPin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pin' => 'required|string|min:4|max:20|regex:/^[0-9]+$/',
            'current_pin' => 'nullable|string|max:20',
        ]);

        $myId = auth()->id();

        // 如果已设置 PIN，需要验证旧 PIN
        $existing = \App\Models\UserPrivacySetting::where('user_id', $myId)->first();
        if ($existing && $existing->privacy_pin) {
            if (empty($validated['current_pin']) || !password_verify($validated['current_pin'], $existing->privacy_pin)) {
                return ApiResponse::error('PIN_MISMATCH', '当前 PIN 不正确', 422);
            }
        }

        \App\Models\UserPrivacySetting::updateOrCreate(
            ['user_id' => $myId],
            ['privacy_pin' => bcrypt($validated['pin'])]
        );

        return ApiResponse::success(null, '私密空间 PIN 已设置');
    }

    /**
     * 验证私密空间 PIN
     */
    public function verifyPrivacyPin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pin' => 'required|string|max:20',
        ]);

        $setting = \App\Models\UserPrivacySetting::where('user_id', auth()->id())->first();

        if (!$setting || !$setting->privacy_pin) {
            return ApiResponse::error('PIN_NOT_SET', '未设置 PIN', 400);
        }

        if (!password_verify($validated['pin'], $setting->privacy_pin)) {
            return ApiResponse::error('PIN_INCORRECT', 'PIN 不正确', 422);
        }

        // 保存验证状态到 session（有效期为当前请求）
        session(['privacy_pin_verified_' . auth()->id() => true]);

        return ApiResponse::success([
            'verified' => true,
            'expires_in' => 3600,
        ], '验证成功');
    }

    /**
     * 检查是否已设置 PIN
     */
    public function privacyPinStatus(): JsonResponse
    {
        $setting = \App\Models\UserPrivacySetting::where('user_id', auth()->id())->first();
        return ApiResponse::success([
            'has_pin' => $setting && !empty($setting->privacy_pin),
            'verified' => session('privacy_pin_verified_' . auth()->id(), false),
        ]);
    }

    // ── 好友系统 ──

    /**
     * 发送好友申请
     */
    public function addFriend(Request $request): JsonResponse
    {
        $request->validate(['user_id' => 'required|exists:users,id']);
        $myId = auth()->id();
        $targetId = (int) $request->input('user_id');

        if ($myId === $targetId) {
            return ApiResponse::error('SELF_ADD', '不能添加自己为好友');
        }

        // 检查是否已经是好友
        $existing = UserFriend::where(function ($q) use ($myId, $targetId) {
            $q->where(['requester_id' => $myId, 'addressee_id' => $targetId])
              ->orWhere(['requester_id' => $targetId, 'addressee_id' => $myId]);
        })->first();

        if ($existing) {
            if ($existing->status === 'accepted') return ApiResponse::error('ALREADY_FRIENDS', '已经是好友了');
            if ($existing->status === 'pending') return ApiResponse::error('ALREADY_SENT', '已发送过申请，请等待回复');
            if ($existing->status === 'blocked') return ApiResponse::error('BLOCKED', '无法发送好友申请');
            // rejected → 重新发送
            $existing->update(['status' => 'pending']);
            return ApiResponse::success(null, '好友申请已重新发送');
        }

        UserFriend::create(['requester_id' => $myId, 'addressee_id' => $targetId, 'status' => 'pending']);
        return ApiResponse::success(null, '好友申请已发送', 201);
    }

    /**
     * 处理好友申请（同意/拒绝）
     */
    public function handleFriendRequest(int $friendId, Request $request): JsonResponse
    {
        $action = $request->input('status', $request->input('action', ''));
        if (!in_array($action, ['accept', 'reject', 'accepted', 'rejected'])) {
            return ApiResponse::error('INVALID_ACTION', '无效的操作');
        }
        $newStatus = in_array($action, ['accept', 'accepted']) ? 'accepted' : 'rejected';
        $friend = UserFriend::where('id', $friendId)->where('addressee_id', auth()->id())->firstOrFail();
        $friend->update(['status' => $newStatus]);
        return ApiResponse::success(null, $newStatus === 'accepted' ? '已同意好友申请' : '已拒绝好友申请');
    }

    /**
     * 删除好友/取消申请
     */
    public function removeFriend(int $friendId): JsonResponse
    {
        $friend = UserFriend::where('id', $friendId)
            ->where(function ($q) {
                $q->where('requester_id', auth()->id())->orWhere('addressee_id', auth()->id());
            })->firstOrFail();
        $friend->delete();
        return ApiResponse::success(null, '已删除');
    }

    /**
     * 我的好友列表
     */
    public function myFriends(): JsonResponse
    {
        $myId = auth()->id();
        $friends = UserFriend::where('status', 'accepted')
            ->where(function ($q) use ($myId) {
                $q->where('requester_id', $myId)->orWhere('addressee_id', $myId);
            })
            ->with(['requester:id,name,avatar', 'addressee:id,name,avatar'])
            ->get()
            ->map(function ($f) use ($myId) {
                $user = $f->requester_id === $myId ? $f->addressee : $f->requester;
                return ['id' => $user->id, 'name' => $user->name, 'avatar' => $user->avatar_url ?? '', 'remark' => $f->remark];
            });

        return ApiResponse::success($friends);
    }

    /**
     * 收到的好友申请
     */
    public function pendingRequests(): JsonResponse
    {
        $myId = auth()->id();
        $requests = UserFriend::where('addressee_id', $myId)->where('status', 'pending')
            ->with('requester:id,name,avatar')->get()
            ->map(fn($r) => [
                'id' => $r->id,
                'sender' => ['id' => $r->requester->id, 'name' => $r->requester->name, 'avatar' => $r->requester->avatar_url ?? ''],
                'user' => ['id' => $r->requester->id, 'name' => $r->requester->name, 'avatar' => $r->requester->avatar_url ?? ''],
            ]);
        return ApiResponse::success($requests);
    }

    /**
     * 设置好友备注
     */
    public function setFriendRemark(int $friendId, Request $request): JsonResponse
    {
        $validated = $request->validate(['remark' => 'nullable|string|max:100']);
        UserFriend::where('id', $friendId)
            ->where(function ($q) { $q->where('requester_id', auth()->id())->orWhere('addressee_id', auth()->id()); })
            ->firstOrFail()->update(['remark' => $validated['remark']]);
        return ApiResponse::success(null, '备注已更新');
    }

    // ── 在线状态 ──

    /**
     * 心跳更新在线状态（前端定时调用）
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $myId = auth()->id();
        $status = $request->input('status', 'online');
        if (!in_array($status, ['online', 'busy', 'invisible'])) $status = 'online';
        UserOnlineStatus::updateOrCreate(
            ['user_id' => $myId],
            ['is_online' => $status !== 'invisible', 'custom_status' => $status, 'last_seen_at' => now(), 'device_info' => request()->userAgent()]
        );
        return ApiResponse::success(null);
    }

    /**
     * 获取好友在线状态
     */
    public function friendsOnlineStatus(): JsonResponse
    {
        $myId = auth()->id();
        $friendIds = UserFriend::where('status', 'accepted')
            ->where(function ($q) use ($myId) { $q->where('requester_id', $myId)->orWhere('addressee_id', $myId); })
            ->get()->map(fn($f) => $f->requester_id === $myId ? $f->addressee_id : $f->requester_id);

        $statuses = UserOnlineStatus::whereIn('user_id', $friendIds)
            ->get(['user_id', 'is_online', 'last_seen_at'])
            ->keyBy('user_id');

        return ApiResponse::success($statuses);
    }

    // ── 好友分组 ──

    public function createGroup(Request $request): JsonResponse
    {
        $validated = $request->validate(['name' => 'required|string|max:50']);
        $group = FriendGroup::create(['user_id' => auth()->id(), 'name' => $validated['name']]);
        return ApiResponse::success($group, '分组已创建', 201);
    }

    public function myGroups(): JsonResponse
    {
        return ApiResponse::success(FriendGroup::where('user_id', auth()->id())->orderBy('sort_order')->get());
    }

    public function updateGroup(int $id, Request $request): JsonResponse
    {
        $group = FriendGroup::where('id', $id)->where('user_id', auth()->id())->firstOrFail();
        $group->update($request->validate(['name' => 'sometimes|string|max:50', 'sort_order' => 'nullable|integer|min:0']));
        return ApiResponse::success($group->fresh(), '已更新');
    }

    public function deleteGroup(int $id): JsonResponse
    {
        FriendGroup::where('id', $id)->where('user_id', auth()->id())->delete();
        // 将该分组下的好友移到未分组
        \DB::table('user_friends')
            ->where(function ($q) { $q->where('requester_id', auth()->id())->orWhere('addressee_id', auth()->id()); })
            ->where('friend_group', $id)
            ->update(['friend_group' => null]);
        return ApiResponse::success(null, '已删除');
    }

    public function setFriendGroup(int $friendId, Request $request): JsonResponse
    {
        $validated = $request->validate(['group_id' => 'nullable|exists:friend_groups,id']);
        UserFriend::where('id', $friendId)
            ->where(function ($q) { $q->where('requester_id', auth()->id())->orWhere('addressee_id', auth()->id()); })
            ->firstOrFail()->update(['friend_group' => $validated['group_id']]);
        return ApiResponse::success(null, '分组已设置');
    }

    // ── 增强好友列表（含在线状态+分组） ──

    public function myFriendsEnhanced(): JsonResponse
    {
        $myId = auth()->id();
        $friends = UserFriend::where('status', 'accepted')
            ->where(function ($q) use ($myId) { $q->where('requester_id', $myId)->orWhere('addressee_id', $myId); })
            ->with(['requester:id,name,avatar', 'addressee:id,name,avatar'])
            ->get()->map(function ($f) use ($myId) {
                $user = $f->requester_id === $myId ? $f->addressee : $f->requester;
                return [
                    'friend_id' => $f->id,
                    'id' => $user->id,
                    'name' => $user->name,
                    'avatar' => $user->avatar_url ?? '',
                    'remark' => $f->remark,
                    'group_id' => $f->friend_group,
                ];
            });

        // 获取在线状态
        $friendIds = $friends->pluck('id');
        $onlineStatuses = UserOnlineStatus::whereIn('user_id', $friendIds)->get()->keyBy('user_id');

        $data = [
            'friends' => $friends->map(fn($f) => array_merge($f, [
                'is_online' => $onlineStatuses->get($f['id'])?->is_online ?? false,
                'last_seen' => $onlineStatuses->get($f['id'])?->last_seen_at,
            ])),
            'groups' => FriendGroup::where('user_id', $myId)->orderBy('sort_order')->get(),
        ];

        return ApiResponse::success($data);
    }

    // ── Emoji 表情回复 ──

    public function reactions(int $messageId): JsonResponse
    {
        $reactions = MessageReaction::where('message_id', $messageId)
            ->with('user:id,name')
            ->get()
            ->groupBy('reaction')
            ->map(fn($items, $emoji) => [
                'emoji' => $emoji,
                'count' => $items->count(),
                'users' => $items->pluck('user.name'),
                'me' => $items->contains('user_id', auth()->id()),
            ])
            ->values();

        return ApiResponse::success($reactions);
    }

    public function addReaction(int $messageId, Request $request): JsonResponse
    {
        $validated = $request->validate(['reaction' => 'required|string|max:50']);
        $myId = auth()->id();

        $existing = MessageReaction::where('message_id', $messageId)
            ->where('user_id', $myId)
            ->where('reaction', $validated['reaction'])
            ->first();

        if ($existing) {
            // 已存在则取消（切换效果）
            $existing->delete();
            return ApiResponse::success(['action' => 'removed', 'reaction' => $validated['reaction']], '已取消反应');
        }

        MessageReaction::create([
            'message_id' => $messageId,
            'user_id' => $myId,
            'reaction' => $validated['reaction'],
        ]);

        return ApiResponse::success(['action' => 'added', 'reaction' => $validated['reaction']], '已添加反应', 201);
    }

    // ── 消息撤回 ──

    public function recallMessage(int $messageId): JsonResponse
    {
        $msg = ConversationMessage::findOrFail($messageId);
        $myId = auth()->id();

        // 判断用户角色
        $participant = ConversationParticipant::where('conversation_id', $msg->conversation_id)
            ->where('user_id', $myId)->first();
        $isAdmin = $participant && in_array($participant->role, ['creator', 'admin']);

        // 普通用户只能撤回自己的消息
        if (!$isAdmin && $msg->sender_id !== $myId) {
            return ApiResponse::error('无权撤回他人的消息');
        }

        // 普通用户有 2 分钟时间限制，管理员无限制
        if (!$isAdmin && $msg->created_at->diffInMinutes(now()) > 2) {
            return ApiResponse::error('超过2分钟无法撤回');
        }

        if ($msg->is_recalled) {
            return ApiResponse::error('消息已被撤回');
        }

        $msg->update(['is_recalled' => true, 'content' => null]);

        // 广播撤回事件
        $participants = ConversationParticipant::where('conversation_id', $msg->conversation_id)
            ->whereNull('deleted_at')->pluck('user_id');
        event(new \App\Events\ChatMessageSent(
            $msg->load('sender:id,name'),
            $participants->toArray()
        ));

        return ApiResponse::success(null, '消息已撤回');
    }

    // ── 删除消息 ──

    public function deleteMessage(int $messageId): JsonResponse
    {
        $msg = ConversationMessage::where('id', $messageId)
            ->where('sender_id', auth()->id())
            ->firstOrFail();

        if ($msg->deleted_at) {
            return ApiResponse::error('消息已被删除');
        }

        $msg->update(['deleted_at' => now(), 'deleted_by' => auth()->id()]);

        return ApiResponse::success(null, '消息已删除');
    }

    // ── 黑名单 ──

    public function blockUser(int $userId): JsonResponse
    {
        if ($userId === auth()->id()) return ApiResponse::error('不能拉黑自己');

        $user = User::findOrFail($userId);

        // 更新好友关系为 blocked
        UserFriend::where(function ($q) use ($userId) {
            $q->where(['requester_id' => auth()->id(), 'addressee_id' => $userId])
              ->orWhere(['requester_id' => $userId, 'addressee_id' => auth()->id()]);
        })->delete();

        UserFriend::create([
            'requester_id' => auth()->id(),
            'addressee_id' => $userId,
            'status' => 'blocked',
        ]);

        return ApiResponse::success(null, '已拉黑用户');
    }

    public function unblockUser(int $userId): JsonResponse
    {
        UserFriend::where('requester_id', auth()->id())
            ->where('addressee_id', $userId)
            ->where('status', 'blocked')
            ->delete();

        return ApiResponse::success(null, '已取消拉黑');
    }

    public function blockedList(): JsonResponse
    {
        $blocked = UserFriend::where('requester_id', auth()->id())
            ->where('status', 'blocked')
            ->with('addressee:id,name,avatar')
            ->get()
            ->map(fn($r) => ['id' => $r->addressee->id, 'name' => $r->addressee->name]);

        return ApiResponse::success($blocked);
    }

    // ── 消息编辑 ──

    public function editMessage(int $messageId, Request $request): JsonResponse
    {
        $msg = ConversationMessage::where('id', $messageId)
            ->where('sender_id', auth()->id())
            ->firstOrFail();

        $validated = $request->validate(['content' => 'required|string|max:10000']);
        $msg->update(['content' => $validated['content'], 'is_edited' => true]);

        return ApiResponse::success($msg->fresh(), '消息已编辑');
    }

    // ── 收藏消息 ──

    public function toggleFavorite(int $messageId): JsonResponse
    {
        $myId = auth()->id();
        ConversationMessage::findOrFail($messageId);

        $existing = MessageFavorite::where('user_id', $myId)
            ->where('message_id', $messageId)->first();

        if ($existing) {
            $existing->delete();
            return ApiResponse::success(['favorited' => false], '已取消收藏');
        }

        MessageFavorite::create(['user_id' => $myId, 'message_id' => $messageId]);
        return ApiResponse::success(['favorited' => true], '已收藏', 201);
    }

    public function myFavorites(): JsonResponse
    {
        $favorites = MessageFavorite::where('user_id', auth()->id())
            ->with('message.sender:id,name', 'message.conversation:id,type')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($f) => [
                'id' => $f->id,
                'message_id' => $f->message_id,
                'content' => $f->message->content,
                'sender_name' => $f->message->sender?->name ?? '',
                'conversation_name' => $f->message->conversation?->name ?? '',
                'created_at' => $f->created_at,
            ]);

        return ApiResponse::success($favorites);
    }

    // ── OPR-008: 消息稍后处理/标记 ──

    public function togglePending(int $messageId): JsonResponse
    {
        $myId = auth()->id();
        ConversationMessage::findOrFail($messageId);

        $existing = MessagePending::where('user_id', $myId)
            ->where('message_id', $messageId)->first();

        if ($existing) {
            $existing->delete();
            return ApiResponse::success(['pending' => false], '已取消待处理');
        }

        MessagePending::create(['user_id' => $myId, 'message_id' => $messageId]);
        return ApiResponse::success(['pending' => true], '已标记待处理', 201);
    }

    // ── 消息置顶 ──
    public function pinMessage(int $messageId): JsonResponse
    {
        $msg = ConversationMessage::findOrFail($messageId);
        $myId = auth()->id();

        // 验证操作者是否为会话成员
        $isParticipant = ConversationParticipant::where('conversation_id', $msg->conversation_id)
            ->where('user_id', $myId)->exists();
        if (!$isParticipant) {
            return ApiResponse::error('您不是该会话成员');
        }

        if ($msg->is_pinned) {
            return ApiResponse::error('该消息已置顶');
        }

        $msg->update([
            'is_pinned' => true,
            'pinned_at' => now(),
            'pinned_by' => $myId,
        ]);

        // 发送系统消息
        $user = auth()->user();
        ConversationMessage::create([
            'conversation_id' => $msg->conversation_id,
            'sender_id' => $myId,
            'message_type' => 'system',
            'content' => "📌 {$user->name} 置顶了一条消息",
        ]);

        return ApiResponse::success($msg, '消息已置顶');
    }

    public function unpinMessage(int $messageId): JsonResponse
    {
        $msg = ConversationMessage::findOrFail($messageId);
        $myId = auth()->id();

        $isParticipant = ConversationParticipant::where('conversation_id', $msg->conversation_id)
            ->where('user_id', $myId)->exists();
        if (!$isParticipant) {
            return ApiResponse::error('您不是该会话成员');
        }

        if (!$msg->is_pinned) {
            return ApiResponse::error('该消息未置顶');
        }

        $msg->update(['is_pinned' => false, 'pinned_at' => null, 'pinned_by' => null]);

        $user = auth()->user();
        ConversationMessage::create([
            'conversation_id' => $msg->conversation_id,
            'sender_id' => $myId,
            'message_type' => 'system',
            'content' => "📌 {$user->name} 取消了消息置顶",
        ]);

        return ApiResponse::success($msg, '已取消置顶');
    }

    public function pinnedMessages(int $convId): JsonResponse
    {
        $messages = ConversationMessage::where('conversation_id', $convId)
            ->where('is_pinned', true)
            ->with('sender:id,name', 'pinner:id,name')
            ->orderByDesc('pinned_at')
            ->get();

        return ApiResponse::success($messages);
    }

    public function listPending(): JsonResponse
    {
        $pendings = MessagePending::where('user_id', auth()->id())
            ->with('message.sender:id,name', 'message.conversation:id,name,type')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'message_id' => $p->message_id,
                'content' => $p->message->content,
                'message_type' => $p->message->message_type,
                'sender_name' => $p->message->sender?->name ?? '',
                'conversation_id' => $p->message->conversation_id,
                'conversation_name' => $p->message->conversation?->name ?? '',
                'created_at' => $p->created_at,
            ]);

        return ApiResponse::success($pendings);
    }

    // ── 已读详情 ──

    public function messageReadStatus(int $messageId): JsonResponse
    {
        $msg = ConversationMessage::findOrFail($messageId);
        $participants = ConversationParticipant::where('conversation_id', $msg->conversation_id)
            ->whereNull('deleted_at')
            ->with('user:id,name')
            ->get()
            ->map(function ($p) use ($msg) {
                $read = $p->last_read_at && $p->last_read_at >= $msg->created_at;
                return [
                    'user_id' => $p->user_id,
                    'name' => $p->user?->name ?? '',
                    'is_read' => $read,
                    'read_at' => $read ? $p->last_read_at : null,
                ];
            });

        return ApiResponse::success($participants);
    }

    // ── 群禁言 ──

    public function muteMember(int $convId, int $userId, Request $request): JsonResponse
    {
        $conv = UserConversation::findOrFail($convId);
        if ($conv->type !== 'group') return ApiResponse::error('仅群聊支持');

        $myId = auth()->id();
        $myParticipant = ConversationParticipant::where('conversation_id', $convId)
            ->where('user_id', $myId)->firstOrFail();

        $minutes = min((int) $request->input('minutes', 60), 1440);

        $target = ConversationParticipant::where('conversation_id', $convId)
            ->where('user_id', $userId)->firstOrFail();
        $target->update(['is_muted_until' => now()->addMinutes($minutes)]);

        return ApiResponse::success(null, "已禁言 {$minutes} 分钟");
    }

    public function unmuteMember(int $convId, int $userId): JsonResponse
    {
        ConversationParticipant::where('conversation_id', $convId)
            ->where('user_id', $userId)
            ->update(['is_muted_until' => null]);

        return ApiResponse::success(null, '已解除禁言');
    }

    /**
     * 获取链接预览信息
     */
    public function linkPreview(Request $request): JsonResponse
    {
        $request->validate(['url' => 'required|url|max:2048']);

        $service = app(LinkPreviewService::class);
        $preview = $service->getPreview($request->input('url'));

        if (!$preview) {
            return ApiResponse::success(['url' => $request->input('url'), 'title' => $request->input('url')], '无法获取预览');
        }

        return ApiResponse::success($preview);
    }

    // ── 慢速模式 ──

    /**
     * 设置群聊慢速模式
     */
    public function setSlowMode(int $convId, Request $request): JsonResponse
    {
        $conv = UserConversation::findOrFail($convId);
        if ($conv->type !== 'group') {
            return ApiResponse::error('仅群聊支持慢速模式');
        }

        // 验证是群成员
        $isMember = ConversationParticipant::where('conversation_id', $convId)
            ->where('user_id', auth()->id())->exists();
        if (!$isMember) {
            return ApiResponse::error('您不是该群成员', 403);
        }

        $validated = $request->validate([
            'interval' => 'required|integer|min:0|max:86400', // 0=关闭, 最大24小时
        ]);

        $conv->update(['slow_mode_interval' => $validated['interval']]);

        // 关闭时清除所有成员的 slow_mode_until
        if ($validated['interval'] === 0) {
            ConversationParticipant::where('conversation_id', $convId)
                ->update(['slow_mode_until' => null]);
        }

        return ApiResponse::success([
            'slow_mode_interval' => $conv->fresh()->slow_mode_interval,
        ], $validated['interval'] > 0 ? '慢速模式已开启' : '慢速模式已关闭');
    }

    /**
     * 获取慢速模式状态
     */
    public function getSlowMode(int $convId): JsonResponse
    {
        $conv = UserConversation::findOrFail($convId);
        $isMember = ConversationParticipant::where('conversation_id', $convId)
            ->where('user_id', auth()->id())->exists();
        if (!$isMember) {
            return ApiResponse::error('您不是该群成员', 403);
        }

        return ApiResponse::success([
            'enabled' => $conv->slow_mode_interval > 0,
            'interval' => $conv->slow_mode_interval,
        ]);
    }

    // ── 群公告 ──

    /**
     * 发布群公告
     */
    public function createAnnouncement(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'conversation_id' => 'required|integer|exists:user_conversations,id',
            'title' => 'required|string|max:200',
            'content' => 'required|string|max:10000',
        ]);

        $conv = UserConversation::findOrFail($validated['conversation_id']);
        if ($conv->type !== 'group') {
            return ApiResponse::error('仅群聊可发布公告');
        }

        // 验证用户是群成员
        $isMember = ConversationParticipant::where('conversation_id', $conv->id)
            ->where('user_id', auth()->id())->exists();
        if (!$isMember) {
            return ApiResponse::error('您不是该群成员', 403);
        }

        $announcement = Announcement::create([
            'conversation_id' => $conv->id,
            'sender_id' => auth()->id(),
            'title' => $validated['title'],
            'content' => $validated['content'],
        ]);

        // 发布者自动标记已读
        AnnouncementRead::create([
            'announcement_id' => $announcement->id,
            'user_id' => auth()->id(),
            'read_at' => now(),
        ]);

        // 发送系统消息到聊天
        $msg = ConversationMessage::create([
            'conversation_id' => $conv->id,
            'sender_id' => auth()->id(),
            'message_type' => 'system',
            'content' => "📢 发布公告：{$validated['title']}",
        ]);
        broadcast(new ChatMessageSent($msg))->toOthers();

        $conv->update(['last_message_id' => $msg->id, 'last_message_at' => now()]);

        return ApiResponse::success($announcement->load('sender:id,name'), '公告已发布', 201);
    }

    /**
     * 获取会话公告列表
     */
    public function conversationAnnouncements(int $convId): JsonResponse
    {
        $conv = UserConversation::findOrFail($convId);
        $isMember = ConversationParticipant::where('conversation_id', $conv->id)
            ->where('user_id', auth()->id())->exists();
        if (!$isMember) {
            return ApiResponse::error('您不是该群成员', 403);
        }

        $announcements = Announcement::where('conversation_id', $convId)
            ->with('sender:id,name')
            ->withCount('reads')
            ->orderByDesc('created_at')
            ->paginate(20);

        // 标记当前用户是否已读
        $announcements->getCollection()->transform(function ($a) {
            $a->is_read = $a->isReadBy(auth()->id());
            return $a;
        });

        return ApiResponse::paginated($announcements);
    }

    /**
     * 获取公告详情（含已读成员名单）
     */
    public function announcementDetail(int $id): JsonResponse
    {
        $announcement = Announcement::with(['sender:id,name', 'reads.user:id,name'])->findOrFail($id);

        $isMember = ConversationParticipant::where('conversation_id', $announcement->conversation_id)
            ->where('user_id', auth()->id())->exists();
        if (!$isMember) {
            return ApiResponse::error('您不是该群成员', 403);
        }

        $totalMembers = ConversationParticipant::where('conversation_id', $announcement->conversation_id)->count();
        $readUsers = $announcement->reads->map(fn($r) => ['id' => $r->user_id, 'name' => $r->user->name ?? '用户', 'read_at' => $r->read_at]);

        return ApiResponse::success([
            'id' => $announcement->id,
            'title' => $announcement->title,
            'content' => $announcement->content,
            'sender' => $announcement->sender,
            'created_at' => $announcement->created_at,
            'total_members' => $totalMembers,
            'read_count' => $readUsers->count(),
            'unread_count' => $totalMembers - $readUsers->count(),
            'read_users' => $readUsers,
        ]);
    }

    /**
     * 标记公告已读
     */
    public function markAnnouncementRead(int $id): JsonResponse
    {
        $announcement = Announcement::findOrFail($id);

        $isMember = ConversationParticipant::where('conversation_id', $announcement->conversation_id)
            ->where('user_id', auth()->id())->exists();
        if (!$isMember) {
            return ApiResponse::error('您不是该群成员', 403);
        }

        AnnouncementRead::firstOrCreate([
            'announcement_id' => $announcement->id,
            'user_id' => auth()->id(),
        ], ['read_at' => now()]);

        return ApiResponse::success(null, '已标记已读');
    }

    // ── 群邀请 ──

    /**
     * 生成群邀请链接
     */
    public function createGroupInvite(int $convId, Request $request): JsonResponse
    {
        $conv = UserConversation::findOrFail($convId);

        // 权限校验：邀请
        if (!$conv->userCan('invite', auth()->id())) {
            return ApiResponse::error('你没有权限生成邀请链接');
        }
        if ($conv->type !== 'group') {
            return ApiResponse::error('仅群聊可生成邀请');
        }

        $isMember = ConversationParticipant::where('conversation_id', $convId)
            ->where('user_id', auth()->id())->exists();
        if (!$isMember) {
            return ApiResponse::error('您不是该群成员', 403);
        }

        $validated = $request->validate([
            'expires_in_hours' => 'nullable|integer|min:1|max:720', // 30天
            'max_uses' => 'nullable|integer|min:0|max:10000',
        ]);

        $invite = GroupInvite::create([
            'conversation_id' => $convId,
            'created_by' => auth()->id(),
            'token' => GroupInvite::generateToken(),
            'expires_at' => $validated['expires_in_hours'] ?? null ? now()->addHours($validated['expires_in_hours']) : null,
            'max_uses' => $validated['max_uses'] ?? 0,
        ]);

        $inviteUrl = url("/im/invite/{$invite->token}");

        return ApiResponse::success([
            'id' => $invite->id,
            'token' => $invite->token,
            'url' => $inviteUrl,
            'expires_at' => $invite->expires_at,
            'max_uses' => $invite->max_uses,
            'use_count' => 0,
        ], '邀请链接已生成', 201);
    }

    /**
     * 获取群邀请列表
     */
    public function groupInvites(int $convId): JsonResponse
    {
        $conv = UserConversation::findOrFail($convId);
        $isMember = ConversationParticipant::where('conversation_id', $convId)
            ->where('user_id', auth()->id())->exists();
        if (!$isMember) {
            return ApiResponse::error('您不是该群成员', 403);
        }

        $invites = GroupInvite::where('conversation_id', $convId)
            ->with('creator:id,name')
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($inv) {
                $inv->is_valid = $inv->isValid();
                $inv->url = url("/im/invite/{$inv->token}");
                return $inv;
            });

        return ApiResponse::success($invites);
    }

    /**
     * 查询邀请信息（公开）
     */
    public function inviteInfo(string $token): JsonResponse
    {
        $invite = GroupInvite::where('token', $token)->with('conversation')->first();
        if (!$invite || !$invite->isValid()) {
            return ApiResponse::error('邀请链接无效或已过期');
        }

        $memberCount = ConversationParticipant::where('conversation_id', $invite->conversation_id)->count();

        return ApiResponse::success([
            'group_name' => $invite->conversation->name,
            'member_count' => $memberCount,
            'created_by' => $invite->creator->name ?? '未知',
        ]);
    }

    /**
     * 通过邀请加入群聊
     */
    public function joinViaInvite(string $token): JsonResponse
    {
        $invite = GroupInvite::where('token', $token)->first();
        if (!$invite || !$invite->isValid()) {
            return ApiResponse::error('邀请链接无效或已过期');
        }

        $convId = $invite->conversation_id;
        $myId = auth()->id();

        // 检查是否已是成员
        $existing = ConversationParticipant::where('conversation_id', $convId)
            ->where('user_id', $myId)->exists();
        if ($existing) {
            return ApiResponse::success(['conversation_id' => $convId], '您已是该群成员');
        }

        // 加入群聊
        ConversationParticipant::create([
            'conversation_id' => $convId,
            'user_id' => $myId,
        ]);

        // 增加使用次数
        $invite->increment('use_count');

        // 发送系统消息
        $user = auth()->user();
        $msg = ConversationMessage::create([
            'conversation_id' => $convId,
            'sender_id' => $myId,
            'message_type' => 'system',
            'content' => "🔗 {$user->name} 通过邀请链接加入群聊",
        ]);
        broadcast(new ChatMessageSent($msg))->toOthers();

        return ApiResponse::success(['conversation_id' => $convId], '已加入群聊');
    }

    /**
     * 删除/撤销邀请链接
     */
    public function revokeInvite(int $inviteId): JsonResponse
    {
        $invite = GroupInvite::findOrFail($inviteId);
        $conv = UserConversation::findOrFail($invite->conversation_id);

        $isMember = ConversationParticipant::where('conversation_id', $invite->conversation_id)
            ->where('user_id', auth()->id())->exists();
        if (!$isMember) {
            return ApiResponse::error('您不是该群成员', 403);
        }

        $invite->update(['is_active' => false]);

        return ApiResponse::success(null, '邀请已撤销');
    }

    // ── 入群审批 ──

    /**
     * 提交入群申请
     */
    public function submitJoinRequest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'conversation_id' => 'required|integer|exists:user_conversations,id',
            'message' => 'nullable|string|max:500',
        ]);

        $convId = $validated['conversation_id'];
        $myId = auth()->id();

        $conv = UserConversation::findOrFail($convId);
        if ($conv->type !== 'group') {
            return ApiResponse::error('只有群聊支持入群申请');
        }

        // 已是成员
        $existing = ConversationParticipant::where('conversation_id', $convId)
            ->where('user_id', $myId)->exists();
        if ($existing) {
            return ApiResponse::error('您已是该群成员');
        }

        // 检查是否有待处理的申请
        $pending = GroupJoinRequest::where('conversation_id', $convId)
            ->where('user_id', $myId)->where('status', 'pending')->exists();
        if ($pending) {
            return ApiResponse::error('您已提交过申请，请等待审核');
        }

        // 如果未开启审批，直接加入
        if (!$conv->join_approval) {
            ConversationParticipant::create(['conversation_id' => $convId, 'user_id' => $myId]);
            $user = auth()->user();
            ConversationMessage::create([
                'conversation_id' => $convId, 'sender_id' => $myId,
                'message_type' => 'system', 'content' => "🔗 {$user->name} 加入了群聊",
            ]);
            return ApiResponse::success(['conversation_id' => $convId], '已加入群聊');
        }

        $request = GroupJoinRequest::create([
            'conversation_id' => $convId,
            'user_id' => $myId,
            'message' => $validated['message'],
        ]);

        return ApiResponse::success($request, '入群申请已提交，请等待管理员审核', 201);
    }

    /**
     * 待审批的入群申请列表
     */
    public function pendingJoinRequests(int $convId): JsonResponse
    {
        $conv = UserConversation::findOrFail($convId);
        $this->checkGroupAdmin($conv);

        $requests = GroupJoinRequest::with('user:id,name,avatar')
            ->where('conversation_id', $convId)
            ->where('status', 'pending')
            ->latest()
            ->get();

        return ApiResponse::success($requests);
    }

    /**
     * 审批入群申请
     */
    public function handleJoinRequest(int $requestId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
        ]);

        $joinRequest = GroupJoinRequest::with('conversation')->findOrFail($requestId);
        $conv = $joinRequest->conversation;

        $this->checkGroupAdmin($conv);

        if ($joinRequest->status !== 'pending') {
            return ApiResponse::error('该申请已处理');
        }

        $adminId = auth()->id();

        if ($validated['action'] === 'approve') {
            ConversationParticipant::create([
                'conversation_id' => $joinRequest->conversation_id,
                'user_id' => $joinRequest->user_id,
            ]);

            $user = $joinRequest->user;
            ConversationMessage::create([
                'conversation_id' => $joinRequest->conversation_id,
                'sender_id' => $joinRequest->user_id,
                'message_type' => 'system',
                'content' => "✅ {$user->name} 通过入群审核加入了群聊",
            ]);

            $joinRequest->update([
                'status' => 'approved',
                'handled_by' => $adminId,
                'handled_at' => now(),
            ]);

            return ApiResponse::success(null, '已同意该申请');
        } else {
            $joinRequest->update([
                'status' => 'rejected',
                'handled_by' => $adminId,
                'handled_at' => now(),
            ]);

            return ApiResponse::success(null, '已拒绝该申请');
        }
    }

    /**
     * 开启/关闭入群审批
     */
    public function toggleJoinApproval(int $convId): JsonResponse
    {
        $conv = UserConversation::findOrFail($convId);
        $this->checkGroupAdmin($conv);

        $conv->update(['join_approval' => !$conv->join_approval]);

        return ApiResponse::success([
            'join_approval' => $conv->fresh()->join_approval,
        ], $conv->join_approval ? '已开启入群审批' : '已关闭入群审批');
    }

    /**
     * 获取群权限配置
     */
    public function getGroupPermissions(int $convId): JsonResponse
    {
        $conv = UserConversation::findOrFail($convId);
        $this->checkGroupAdmin($conv);

        return ApiResponse::success([
            'permissions' => $conv->getEffectivePermissions(),
        ]);
    }

    /**
     * 更新群权限配置
     */
    public function updateGroupPermissions(int $convId, Request $request): JsonResponse
    {
        $conv = UserConversation::findOrFail($convId);
        $this->checkGroupAdmin($conv);

        $validated = $request->validate([
            'permissions' => 'required|array',
            'permissions.invite' => 'nullable|in:admin,all',
            'permissions.mention_all' => 'nullable|in:admin,all',
            'permissions.send_file' => 'nullable|in:admin,all',
            'permissions.send_card' => 'nullable|in:admin,all',
            'permissions.edit_group' => 'nullable|in:creator,admin',
            'permissions.pin_message' => 'nullable|in:admin,all',
        ]);

        $conv->update(['permissions' => $validated['permissions']]);

        return ApiResponse::success([
            'permissions' => $conv->fresh()->getEffectivePermissions(),
        ], '群权限已更新');
    }

    /**
     * 检查是否为群管理员
     */
    protected function checkGroupAdmin(UserConversation $conv): void
    {
        if ($conv->type !== 'group') {
            throw new \RuntimeException('不是群聊');
        }

        $participant = ConversationParticipant::where('conversation_id', $conv->id)
            ->where('user_id', auth()->id())->first();

        if (!$participant || !in_array($participant->role, ['creator', 'admin'])) {
            throw new \RuntimeException('只有群主/管理员可执行此操作');
        }
    }

    // ── 群管理增强 ──

    /**
     * 踢出群成员
     */
    public function kickMember(int $convId, int $userId): JsonResponse
    {
        $conv = UserConversation::findOrFail($convId);
        $myId = auth()->id();

        $myRole = ConversationParticipant::where('conversation_id', $convId)->where('user_id', $myId)->value('role');
        if (!in_array($myRole, ['creator', 'admin'])) {
            return ApiResponse::error('仅群主和管理员可踢人', 403);
        }

        $target = ConversationParticipant::where('conversation_id', $convId)->where('user_id', $userId)->first();
        if (!$target || $target->role === 'creator') {
            return ApiResponse::error('无法踢出群主', 403);
        }

        // 直接删除参与者记录
        $target->delete();

        // 发送系统消息
        $user = auth()->user();
        $kicked = \App\Models\User::find($userId);
        ConversationMessage::create([
            'conversation_id' => $convId, 'sender_id' => $myId, 'message_type' => 'system',
            'content' => "👢 {$user->name} 将 {$kicked->name} 移出了群聊",
        ]);

        return ApiResponse::success(null, '已移出群聊');
    }

    /**
     * 主动退群
     */
    public function leaveGroup(int $convId): JsonResponse
    {
        $conv = UserConversation::findOrFail($convId);
        $myId = auth()->id();

        $participant = ConversationParticipant::where('conversation_id', $convId)->where('user_id', $myId)->first();
        if (!$participant) return ApiResponse::error('您不是群成员', 403);
        if ($participant->role === 'creator') {
            return ApiResponse::error('群主无法退群，请先转让群主', 403);
        }

        $participant->delete();
        $user = auth()->user();
        ConversationMessage::create([
            'conversation_id' => $convId, 'sender_id' => $myId, 'message_type' => 'system',
            'content' => "🚪 {$user->name} 退出了群聊",
        ]);

        return ApiResponse::success(null, '已退出群聊');
    }

    /**
     * 转让群主
     */
    public function transferOwner(int $convId, Request $request): JsonResponse
    {
        $request->validate(['user_id' => 'required|integer|exists:users,id']);
        $conv = UserConversation::findOrFail($convId);
        $myId = auth()->id();
        $newOwnerId = $request->input('user_id');

        $myPart = ConversationParticipant::where('conversation_id', $convId)->where('user_id', $myId)->first();
        if (!$myPart || $myPart->role !== 'creator') {
            return ApiResponse::error('仅群主可转让', 403);
        }

        $newPart = ConversationParticipant::where('conversation_id', $convId)->where('user_id', $newOwnerId)->first();
        if (!$newPart) return ApiResponse::error('目标用户不是群成员', 404);

        DB::transaction(function () use ($convId, $myId, $newOwnerId) {
            ConversationParticipant::where('conversation_id', $convId)->where('user_id', $myId)->update(['role' => 'member']);
            ConversationParticipant::where('conversation_id', $convId)->where('user_id', $newOwnerId)->update(['role' => 'creator']);
        });

        $newOwner = \App\Models\User::find($newOwnerId);
        ConversationMessage::create([
            'conversation_id' => $convId, 'sender_id' => $myId, 'message_type' => 'system',
            'content' => "👑 群主已转让给 {$newOwner->name}",
        ]);

        return ApiResponse::success(null, '群主已转让');
    }

    /**
     * 设置/取消管理员
     */
    public function setAdmin(int $convId, Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'is_admin' => 'required|boolean',
        ]);
        $conv = UserConversation::findOrFail($convId);
        $myId = auth()->id();

        $myPart = ConversationParticipant::where('conversation_id', $convId)->where('user_id', $myId)->first();
        if (!$myPart || $myPart->role !== 'creator') {
            return ApiResponse::error('仅群主可设置管理员', 403);
        }

        $target = ConversationParticipant::where('conversation_id', $convId)->where('user_id', $request->input('user_id'))->first();
        if (!$target) return ApiResponse::error('目标用户不是群成员', 404);
        if ($target->role === 'creator') return ApiResponse::error('无法修改群主角色', 403);

        $newRole = $request->input('is_admin') ? 'admin' : 'member';
        $target->update(['role' => $newRole]);
        $user = \App\Models\User::find($request->input('user_id'));

        ConversationMessage::create([
            'conversation_id' => $convId, 'sender_id' => $myId, 'message_type' => 'system',
            'content' => $request->input('is_admin') ? "🔰 {$user->name} 被设为管理员" : "📋 {$user->name} 被取消管理员",
        ]);

        return ApiResponse::success(['role' => $newRole], $request->input('is_admin') ? '已设为管理员' : '已取消管理员');
    }

    /**
     * 解散群聊
     */
    public function dismissGroup(int $convId): JsonResponse
    {
        $conv = UserConversation::findOrFail($convId);
        $myId = auth()->id();

        $myPart = ConversationParticipant::where('conversation_id', $convId)->where('user_id', $myId)->first();
        if (!$myPart || $myPart->role !== 'creator') {
            return ApiResponse::error('仅群主可解散群聊', 403);
        }

        $conv->update(['deleted_at' => now()]);
        ConversationParticipant::where('conversation_id', $convId)->delete();
        ConversationMessage::where('conversation_id', $convId)->delete();

        return ApiResponse::success(null, '群聊已解散');
    }

    protected function loadConversation(int $id): array
    {
        $conv = UserConversation::with(['participants.user:id,name,avatar', 'lastMessage.sender:id,name'])->findOrFail($id);
        $myId = auth()->id();
        $otherUsers = $conv->participants->filter(fn($p) => $p->user_id !== $myId)->pluck('user');
        return [
            'id' => $conv->id,
            'type' => $conv->type,
            'name' => $conv->type === 'private' ? ($otherUsers->first()?->name ?? '用户') : $conv->name,
            'participants' => $conv->participants->map(fn($p) => [
                'id' => $p->user_id, 'name' => $p->user?->name ?? '', 'avatar' => $p->user?->avatar_url ?? '',
            ]),
        ];
    }

    // ── 用户举报 ──
    public function report(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reportable_type' => 'required|string|in:user,message,conversation,forum_post,article',
            'reportable_id' => 'required|integer',
            'reason' => 'required|string|in:spam,harassment,pornographic,illegal,impersonation,copyright,other',
            'description' => 'nullable|string|max:1000',
        ]);

        $typeMap = ['user' => User::class, 'message' => ConversationMessage::class, 'conversation' => UserConversation::class, 'forum_post' => ForumPost::class, 'article' => \App\Models\OaArticle::class];
        $morphClass = $typeMap[$validated['reportable_type']];

        // 检查是否已举报过相同内容（防止刷屏）
        $existing = UserReport::where('reporter_id', auth()->id())
            ->where('reportable_type', $morphClass)
            ->where('reportable_id', $validated['reportable_id'])
            ->whereIn('status', ['pending', 'investigating'])
            ->first();
        if ($existing) {
            return ApiResponse::error('您已举报过此内容，正在处理中', 409);
        }

        $report = UserReport::create([
            'reporter_id' => auth()->id(),
            'reportable_type' => $morphClass,
            'reportable_id' => $validated['reportable_id'],
            'reason' => $validated['reason'],
            'description' => $validated['description'] ?? null,
        ]);

        return ApiResponse::success($report, '举报已提交，感谢您的反馈', 201);
    }

    public function myReports(Request $request): JsonResponse
    {
        $reports = UserReport::where('reporter_id', auth()->id())
            ->with('reportable')
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));
        return ApiResponse::paginated($reports);
    }

    // ── PRES-003: 设置在线状态 ──
    public function setStatus(Request $request): JsonResponse
    {
        $validated = $request->validate(['status' => 'required|in:online,busy,invisible']);
        UserOnlineStatus::updateOrCreate(
            ['user_id' => auth()->id()],
            ['is_online' => $validated['status'] !== 'invisible', 'custom_status' => $validated['status'], 'last_seen_at' => now()]
        );
        return ApiResponse::success(['status' => $validated['status']], '状态已更新');
    }

    // ── MSG-005: 标记消息已送达 ──
    public function markDelivered(Request $request): JsonResponse
    {
        $ids = $request->input('message_ids', []);
        if (empty($ids)) return ApiResponse::success(null);
        ConversationMessage::whereIn('id', $ids)->where('sender_id', '!=', auth()->id())
            ->where('deliver_status', 'sent')
            ->update(['deliver_status' => 'delivered', 'delivered_at' => now()]);
        return ApiResponse::success(null);
    }

    // ── 标记消息已读（扩展版） ──
    public function markMessagesRead(Request $request): JsonResponse
    {
        $convId = $request->input('conversation_id');
        $ids = $request->input('message_ids', []);
        if (empty($ids) || !$convId) return ApiResponse::success(null);
        ConversationMessage::whereIn('id', $ids)->where('conversation_id', $convId)
            ->where('sender_id', '!=', auth()->id())
            ->update(['deliver_status' => 'read', 'read_at' => now()]);
        return ApiResponse::success(null);
    }

    // ── SEC-006: 隐私设置 ──
    public function getPrivacySettings(): JsonResponse
    {
        return ApiResponse::success(UserPrivacySetting::defaultFor(auth()->id()));
    }

    public function savePrivacySettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'friend_add_policy' => 'sometimes|in:everyone,need_question,nobody',
            'show_online_status' => 'sometimes|boolean',
            'show_read_receipt' => 'sometimes|boolean',
            'allow_stranger_message' => 'sometimes|boolean',
        ]);
        $settings = UserPrivacySetting::defaultFor(auth()->id());
        $settings->update($validated);
        return ApiResponse::success($settings->fresh(), '隐私设置已更新');
    }

    // ── GRP-004: 群资料管理 ──
    public function updateGroupProfile(int $id, Request $request): JsonResponse
    {
        $conv = UserConversation::findOrFail($id);
        if ($conv->type !== 'group') return ApiResponse::error('不是群聊', 400);
        $myId = auth()->id();
        $participant = ConversationParticipant::where('conversation_id', $id)->where('user_id', $myId)->first();
        if (!$participant || !in_array($participant->role, ['creator', 'admin'])) {
            return ApiResponse::error('无权限', 403);
        }
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'description' => 'nullable|string|max:500',
        ]);
        $conv->update($validated);
        return ApiResponse::success($conv->fresh(), '群资料已更新');
    }

    // ── OPR-011: 投票消息 ──
    public function createPoll(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'conversation_id' => 'required|exists:user_conversations,id',
            'question' => 'required|string|max:200',
            'options' => 'required|array|min:2|max:20',
            'options.*' => 'required|string|max:100',
            'type' => 'sometimes|in:single,multiple',
            'is_anonymous' => 'sometimes|boolean',
            'expires_at' => 'nullable|date|after:now',
        ]);
        $conv = UserConversation::findOrFail($validated['conversation_id']);
        if ($conv->type !== 'group') return ApiResponse::error('仅群聊可发起投票', 400);
        $options = array_map(fn($i, $v) => ['key' => 'opt_'.$i, 'label' => $v], array_keys($validated['options']), $validated['options']);
        $poll = ConversationPoll::create([
            'conversation_id' => $validated['conversation_id'],
            'creator_id' => auth()->id(),
            'question' => $validated['question'],
            'options' => $options,
            'type' => $validated['type'] ?? 'single',
            'is_anonymous' => $validated['is_anonymous'] ?? false,
            'expires_at' => $validated['expires_at'] ?? null,
        ]);
        return ApiResponse::success($poll->load('creator:id,name'), '投票已创建', 201);
    }

    public function getPoll(int $id): JsonResponse
    {
        $poll = ConversationPoll::with('creator:id,name')->findOrFail($id);
        $results = $poll->results();
        return ApiResponse::success([
            'poll' => $poll,
            'results' => $results,
            'hasVoted' => $poll->hasVoted(auth()->id()),
            'totalVotes' => $poll->votes()->count(),
        ]);
    }

    public function votePoll(int $id, Request $request): JsonResponse
    {
        $poll = ConversationPoll::findOrFail($id);
        if ($poll->is_closed || ($poll->expires_at && $poll->expires_at->isPast())) {
            return ApiResponse::error('投票已结束', 400);
        }
        if ($poll->hasVoted(auth()->id())) {
            return ApiResponse::error('您已投过票', 409);
        }
        $validated = $request->validate(['selected_options' => 'required|array|min:1']);
        $validKeys = collect($poll->options)->pluck('key')->toArray();
        foreach ($validated['selected_options'] as $opt) {
            if (!in_array($opt, $validKeys)) return ApiResponse::error('无效选项', 422);
        }
        if ($poll->type === 'single' && count($validated['selected_options']) > 1) {
            return ApiResponse::error('单选投票只能选一项', 422);
        }
        ConversationPollVote::create([
            'poll_id' => $id, 'user_id' => auth()->id(), 'selected_options' => $validated['selected_options'],
        ]);
        return ApiResponse::success(null, '投票成功');
    }

    public function closePoll(int $id): JsonResponse
    {
        $poll = ConversationPoll::findOrFail($id);
        if ($poll->creator_id !== auth()->id()) return ApiResponse::error('仅创建者可关闭', 403);
        $poll->update(['is_closed' => true]);
        return ApiResponse::success(null, '投票已关闭');
    }

    // ── AI-001: 智能回复建议（接入真实 LLM） ──
    public function smartReplies(int $convId, LlmService $llm): JsonResponse
    {
        $messages = ConversationMessage::where('conversation_id', $convId)
            ->where('sender_id', '!=', auth()->id())
            ->orderBy('created_at', 'desc')->take(10)->get()->reverse();
        if ($messages->isEmpty()) {
            return ApiResponse::success(['replies' => ['您好，有什么可以帮助您的？', '好的，请稍等', '谢谢']]);
        }
        $context = $messages->map(fn($m) => ($m->sender?->name ?? '对方').': '.$m->content)->implode("\n");
        $myName = auth()->user()?->name ?? '我';

        try {
            $result = $llm->chat([
                ['role' => 'system', 'content' => '你是一个智能聊天助手。根据对话上下文，生成3条简短自然的回复建议（每条不超过20字），用JSON数组格式返回，不要多余解释。'],
                ['role' => 'user', 'content' => "对话上下文：\n{$context}\n\n请生成3条{$myName}可以发送的回复建议，JSON数组格式。"],
            ], ['temperature' => 0.7], 'smart_replies');

            $content = $result['content'] ?? '[]';
            // 尝试从返回中提取 JSON 数组
            if (preg_match('/\[.*?\]/s', $content, $matches)) {
                $replies = json_decode($matches[0], true);
                if (is_array($replies) && count($replies) > 0) {
                    return ApiResponse::success(['replies' => array_slice($replies, 0, 3)]);
                }
            }
        } catch (\Throwable $e) {
            // LLM 不可用时降级
        }

        // 降级：基于关键词生成简单回复
        $lastText = $messages->last()->content ?? '';
        $fallbacks = ['好的，我明白了', '请稍等，我来处理', '感谢您的反馈'];
        if (str_contains($lastText, '?')) $fallbacks = ['我来帮您解答', '请详细描述一下', '好的，请稍等'];
        return ApiResponse::success(['replies' => $fallbacks]);
    }

    // ── AI-003: 聊天内容总结（接入真实 LLM） ──
    public function summarize(int $convId, LlmService $llm): JsonResponse
    {
        $limit = 50;
        $messages = ConversationMessage::where('conversation_id', $convId)
            ->whereNull('deleted_at')->orderBy('created_at', 'desc')->take($limit)->get()->reverse();
        if ($messages->isEmpty()) return ApiResponse::error('暂无消息可总结', 400);

        $lines = $messages->map(fn($m) => ($m->sender?->name ?? '用户').': '.$m->content)->implode("\n");

        try {
            $result = $llm->chat([
                ['role' => 'system', 'content' => '你是一个专业的对话总结助手。请根据对话内容生成结构化的中文总结，包含：1）对话主题；2）关键要点（分点列出）；3）达成的决策/结论；4）待办事项/后续行动。使用简洁的Markdown格式。'],
                ['role' => 'user', 'content' => "以下是一条对话记录（共{$messages->count()}条消息），请总结：\n\n{$lines}"],
            ], ['temperature' => 0.3], 'summarize');

            $summary = $result['content'] ?? '';
            if (mb_strlen($summary) > 10) {
                return ApiResponse::success([
                    'summary' => $summary,
                    'total' => count($messages),
                    'from_llm' => true,
                ]);
            }
        } catch (\Throwable $e) {
            // LLM 不可用时降级
        }

        // 降级：简单截取
        $text = mb_substr($lines, 0, 200).'…';
        return ApiResponse::success([
            'summary' => "共 {$messages->count()} 条消息\n\n{$text}",
            'total' => count($messages),
            'from_llm' => false,
        ]);
    }

    // ── AI-010: 流式 AI 对话（SSE） ──
    public function chatStreamSSE(int $convId, Request $request, LlmService $llm): StreamedResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:500',
            'mode' => 'nullable|in:chat,smart_reply,polish,translate,expand,formal,friendly',
            'save_message' => 'nullable|boolean',
        ]);
        $mode = $validated['mode'] ?? 'chat';
        $userMessage = $validated['message'];
        $saveMessage = $validated['save_message'] ?? ($mode === 'chat');

        // 获取最近上下文
        $recentMessages = ConversationMessage::where('conversation_id', $convId)
            ->whereNull('deleted_at')->orderBy('created_at', 'desc')->take(20)->get()->reverse();
        $contextLines = $recentMessages->map(fn($m) => ($m->sender?->name ?? '用户').': '.$m->content)->implode("\n");

        $systemPrompts = [
            'chat' => '你是一个友好的聊天助手。请根据对话上下文自然地回复用户，回复要简洁有用。',
            'smart_reply' => '你是一个回复建议助手。根据上下文生成3条简短回复建议（每条不超过20字），用JSON数组格式返回。',
            'polish' => '你是一个写作助手。请润色以下文本，使其更流畅、专业，保持原意，只返回润色后的文本。',
            'translate' => '你是一个翻译助手。请将以下文本翻译成中文，保持原意和语气，只返回翻译结果。',
            'expand' => '你是一个写作助手。请在保持原意的基础上扩写以下文本，使其内容更丰富、详细，只返回扩写后的文本。',
            'formal' => '你是一个写作助手。请将以下文本改写为正式、专业的语气，保持原意，只返回改写后的文本。',
            'friendly' => '你是一个写作助手。请将以下文本改写为友好、亲切的语气，保持原意，只返回改写后的文本。',
        ];

        $systemPrompt = $systemPrompts[$mode] ?? $systemPrompts['chat'];
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];
        if ($contextLines) {
            $messages[] = ['role' => 'user', 'content' => "对话上下文：\n{$contextLines}"];
            $messages[] = ['role' => 'assistant', 'content' => '好的，我已了解上下文。'];
        }
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $myId = auth()->id();

        return response()->stream(function () use ($llm, $messages, $convId, $myId, $userMessage, $saveMessage) {
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('X-Accel-Buffering: no');

            try {
                // 先保存用户消息（chat 模式）
                if ($saveMessage) {
                    $userClientId = 'ai-user-'.uniqid();
                    ConversationMessage::create([
                        'conversation_id' => $convId,
                        'sender_id' => $myId,
                        'content' => $userMessage,
                        'message_type' => 'text',
                        'client_msg_id' => $userClientId,
                    ]);
                }

                $stream = $llm->chatStream($messages, ['temperature' => 0.7]);
                $fullContent = '';
                foreach ($stream as $chunk) {
                    $text = $chunk['content'] ?? '';
                    $fullContent .= $text;
                    echo "data: " . json_encode(['type' => 'chunk', 'content' => $text]) . "\n\n";
                    ob_flush();
                    flush();
                }

                // 保存 AI 回复（chat 模式）
                if ($saveMessage && $fullContent) {
                    $msg = ConversationMessage::create([
                        'conversation_id' => $convId,
                        'sender_id' => $myId,
                        'content' => $fullContent,
                        'message_type' => 'ai_reply',
                        'client_msg_id' => 'ai-bot-'.uniqid(),
                    ]);

                    // 更新会话最后消息时间
                    UserConversation::where('conversation_id', $convId)
                        ->where('user_id', $myId)
                        ->update(['last_message_at' => now(), 'last_read_at' => now()]);

                    echo "data: " . json_encode(['type' => 'done', 'content' => $fullContent, 'message_id' => $msg->id]) . "\n\n";
                } else {
                    echo "data: " . json_encode(['type' => 'done', 'content' => $fullContent]) . "\n\n";
                }
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

    // ── AI-002: 未读消息摘要 ──
    public function unreadSummary(LlmService $llm): JsonResponse
    {
        $myId = auth()->id();

        // 获取所有有未读消息的会话
        $unreadConvs = \App\Models\ConversationParticipant::where('user_id', $myId)
            ->whereNull('deleted_at')
            ->whereHas('conversation', function($q) {
                $q->whereNotNull('last_message_at');
            })
            ->where(function($q) {
                $q->whereNull('last_read_at')
                  ->orWhereExists(function($sub) {
                      $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                          ->from('user_conversations')
                          ->whereColumn('user_conversations.id', 'conversation_participants.conversation_id')
                          ->whereRaw('user_conversations.last_message_at > conversation_participants.last_read_at');
                  });
            })
            ->with('conversation')
            ->get();

        if ($unreadConvs->isEmpty()) {
            return ApiResponse::success([
                'has_unread' => false,
                'summary' => '没有未读消息',
                'total_unread' => 0,
                'conversations' => [],
            ]);
        }

        $convIds = $unreadConvs->pluck('conversation_id');
        $totalUnread = $unreadConvs->count();

        // 获取每个会话最近的未读消息（最多取最近3条）
        $convSummaries = [];
        foreach ($unreadConvs->take(5) as $uc) {
            $conv = $uc->conversation;
            if (!$conv) continue;
            $lastMsg = ConversationMessage::where('conversation_id', $conv->id)
                ->whereNull('deleted_at')->orderBy('created_at', 'desc')->first();
            $recentMsgs = ConversationMessage::where('conversation_id', $conv->id)
                ->when($uc->last_read_at, fn($q) => $q->where('created_at', '>', $uc->last_read_at))
                ->whereNull('deleted_at')->orderBy('created_at', 'desc')->take(3)->get()->reverse();
            $snippet = $recentMsgs->map(fn($m) => ($m->sender?->name ?? '用户').': '.$m->content)->implode("\n");
            $convSummaries[] = [
                'id' => $conv->id,
                'name' => $conv->name ?? '会话',
                'last_message' => $lastMsg?->content ?? '',
                'snippet' => $snippet,
                'unread_count' => $recentMsgs->count(),
            ];
        }

        // 尝试用 LLM 生成摘要
        try {
            $listText = collect($convSummaries)->map(fn($c) => "【{$c['name']}】\n{$c['snippet']}")->implode("\n---\n");
            $result = $llm->chat([
                ['role' => 'system', 'content' => '你是一个消息摘要助手。根据以下各会话的未读消息，生成一两句话的总体摘要，说明哪些会话有更新、主要内容是什么。回复控制在100字以内。'],
                ['role' => 'user', 'content' => "以下是有未读消息的会话（共{$totalUnread}个会话）：\n\n{$listText}\n\n请生成总体摘要。"],
            ], ['temperature' => 0.3], 'unread_summary');
            $aiSummary = $result['content'] ?? '';
            if (mb_strlen($aiSummary) < 10) $aiSummary = "您有 {$totalUnread} 个会话的未读消息";
        } catch (\Throwable $e) {
            $aiSummary = "您有 {$totalUnread} 个会话的未读消息";
        }

        return ApiResponse::success([
            'has_unread' => true,
            'summary' => $aiSummary,
            'total_unread' => $totalUnread,
            'conversations' => $convSummaries,
        ]);
    }

    // ── SYNC-004: 消息漫游 ──
    public function syncMessages(Request $request): JsonResponse
    {
        $myId = auth()->id();
        $afterId = $request->input('after_id', 0);
        $limit = min((int) $request->input('per_page', 100), 200);

        $convIds = ConversationParticipant::where('user_id', $myId)->whereNull('deleted_at')->pluck('conversation_id');

        $messages = ConversationMessage::whereIn('conversation_id', $convIds)
            ->where('id', '>', $afterId)
            ->whereNull('deleted_at')
            ->with('sender:id,name')
            ->orderBy('id')
            ->take($limit)
            ->get();

        // 更新同步时间
        if ($messages->isNotEmpty()) {
            $lastId = $messages->last()->id;
            UserOnlineStatus::updateOrCreate(
                ['user_id' => $myId],
                ['last_sync_at' => now()]
            );
        }

        return ApiResponse::success([
            'messages' => $messages,
            'last_id' => $messages->last()?->id ?? $afterId,
            'has_more' => $messages->count() >= $limit,
        ]);
    }

    // ── SRCH-001/SRCH-004: 全文搜索 ──
    public function searchMessagesFulltext(Request $request): JsonResponse
    {
        $request->validate(['q' => 'required|string|min:1|max:100']);

        $myId = auth()->id();

        $q = $request->input('q');
        $convId = $request->input('conversation_id');
        $messageType = $request->input('message_type');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $perPage = min((int) $request->input('per_page', 20), 50);

        // 获取用户可访问的会话
        $convIds = ConversationParticipant::where('user_id', $myId)->whereNull('deleted_at')->pluck('conversation_id');

        $query = ConversationMessage::whereIn('conversation_id', $convIds)
            ->whereNull('deleted_at')
            ->whereRaw('MATCH(content) AGAINST(? IN BOOLEAN MODE)', [$q.'*']);

        // SRCH-004: 限定当前会话
        if ($convId && in_array($convId, $convIds->toArray())) {
            $query->where('conversation_id', $convId);
        }

        // 按消息类型过滤
        if ($messageType && in_array($messageType, ['text','image','file','voice','video','location','card','sticker'])) {
            $query->where('message_type', $messageType);
        }

        // 按日期范围过滤
        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $results = $query->with('sender:id,name')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return ApiResponse::paginated($results);
    }

    // MEDIA-012: 语音转文字
    public function transcribeVoice(int $messageId): JsonResponse
    {
        $msg = ConversationMessage::findOrFail($messageId);

        // 权限检查：必须是消息的发送者或接收者
        $myId = auth()->id();
        $isParticipant = ConversationParticipant::where('conversation_id', $msg->conversation_id)
            ->where('user_id', $myId)
            ->exists();
        if ($msg->sender_id !== $myId && !$isParticipant) {
            return ApiResponse::forbidden('无权操作此消息');
        }

        if ($msg->message_type !== 'voice') {
            return ApiResponse::error('不是语音消息', 422);
        }

        try {
            $asrService = app(\App\Services\AsrService::class);
            $transcript = $asrService->transcribe($msg);

            return ApiResponse::success([
                'transcript' => $transcript,
                'message_id' => $msg->id,
            ]);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        } catch (\Exception $e) {
            return ApiResponse::error('语音识别失败', 500);
        }
    }

    // ── AI-012: AI 助手单聊 ──
    public function createAIConversation(): JsonResponse
    {
        $myId = auth()->id();
        // 查找已有的 AI 会话
        $existing = UserConversation::where('type', 'ai')
            ->whereHas('participants', fn($q) => $q->where('user_id', $myId)->whereNull('deleted_at'))
            ->first();
        if ($existing) {
            return ApiResponse::success($this->loadConversation($existing->id));
        }

        // 创建新 AI 会话
        $conv = UserConversation::create([
            'type' => 'ai',
            'name' => 'AI 助手',
            'created_by' => $myId,
        ]);
        ConversationParticipant::create(['conversation_id' => $conv->id, 'user_id' => $myId, 'role' => 'member']);

        return ApiResponse::success($this->loadConversation($conv->id), 'AI 会话已创建', 201);
    }

    // ── AI-013: 群聊 @AI ──
    public function aiMention(int $convId, Request $request, LlmService $llm): JsonResponse
    {
        $validated = $request->validate(['message' => 'required|string|max:500']);
        $myId = auth()->id();

        $conv = UserConversation::findOrFail($convId);
        if ($conv->type !== 'group') {
            return ApiResponse::error('仅群聊支持 @AI', 400);
        }

        // 获取群上下文（最近 20 条消息 + 群名称）
        $recentMessages = ConversationMessage::where('conversation_id', $convId)
            ->whereNull('deleted_at')->orderBy('created_at', 'desc')->take(20)->get()->reverse();
        $contextLines = $recentMessages->map(fn($m) => ($m->sender?->name ?? '用户').': '.$m->content)->implode("\n");

        $userName = auth()->user()?->name ?? '用户';

        try {
            $result = $llm->chat([
                ['role' => 'system', 'content' => "你是一个群聊 AI 助手。群名称：{$conv->name}。请根据群聊上下文回答用户 @你的问题，回复要简洁、有用。"],
                ['role' => 'user', 'content' => "群聊上下文：\n{$contextLines}\n\n{$userName} 问：{$validated['message']}"],
            ], ['temperature' => 0.7], 'ai_mention');

            $reply = $result['content'] ?? '抱歉，暂时无法回答';
        } catch (\Throwable $e) {
            $reply = 'AI 暂时不可用，请稍后再试。';
        }

        // 保存 AI 回复为系统消息
        $msg = ConversationMessage::create([
            'conversation_id' => $convId,
            'sender_id' => $myId, // 用提问者身份发送，但标记特殊类型
            'content' => "🤖 @AI {$reply}",
            'message_type' => 'ai_reply',
            'client_msg_id' => 'ai-'.uniqid(),
        ]);

        \App\Models\UserConversation::where('conversation_id', $convId)->increment('unread_count');

        return ApiResponse::success([
            'reply' => $reply,
            'message' => $msg->load('sender:id,name'),
        ]);
    }

    // ── AI-005: 语义搜索（自然语言 → LLM 理解 → 全文检索）──
    public function semanticSearch(Request $request, LlmService $llm): JsonResponse
    {
        $request->validate(['q' => 'required|string|min:1|max:200']);
        $myId = auth()->id();
        $convIds = ConversationParticipant::where('user_id', $myId)->whereNull('deleted_at')->pluck('conversation_id');

        $q = $request->input('q');
        $perPage = min((int) $request->input('per_page', 20), 50);

        // Step 1: 用 LLM 将自然语言转为搜索关键词
        $keywords = $q;
        try {
            $result = $llm->chat([
                ['role' => 'system', 'content' => '你是一个搜索关键词提取助手。将用户的自然语言问题转换为2-4个搜索关键词，用空格分隔，只返回关键词。'],
                ['role' => 'user', 'content' => $q],
            ], ['temperature' => 0.1, 'max_tokens' => 50], 'semantic_search');
            $extracted = trim($result['content'] ?? '');
            if (strlen($extracted) > 2) {
                $keywords = $extracted;
            }
        } catch (\Throwable $e) {
            // 降级：直接使用原始查询
        }

        // Step 2: 用提取的关键词进行全文搜索
        $results = ConversationMessage::whereIn('conversation_id', $convIds)
            ->whereNull('deleted_at')
            ->where(function ($query) use ($keywords, $q) {
                // 尝试全文索引搜索
                try {
                    $query->whereRaw('MATCH(content) AGAINST(? IN BOOLEAN MODE)', [$keywords.'*']);
                } catch (\Throwable $e) {
                    // 降级：LIKE 搜索
                    $terms = explode(' ', $keywords);
                    foreach ($terms as $term) {
                        $query->where('content', 'like', "%{$term}%");
                    }
                }
            })
            ->with('sender:id,name')
            ->with('conversation:id,name,type')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return ApiResponse::success([
            'items' => $results->items(),
            'total' => $results->total(),
            'keywords' => $keywords,
            'has_more' => $results->hasMorePages(),
        ]);
    }

    // ── AI-004: AI 写作（保存润色/翻译后的消息）──
    public function saveAIMessage(int $convId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string|max:5000',
            'message_type' => 'nullable|string|max:20',
        ]);

        $msg = ConversationMessage::create([
            'conversation_id' => $convId,
            'sender_id' => auth()->id(),
            'content' => $validated['content'],
            'message_type' => $validated['message_type'] ?? 'ai_reply',
            'client_msg_id' => 'ai-save-'.uniqid(),
        ]);

        \App\Models\UserConversation::where('conversation_id', $convId)
            ->where('user_id', auth()->id())
            ->update(['last_message_at' => now(), 'last_read_at' => now()]);

        return ApiResponse::success($msg->load('sender:id,name'), '已保存', 201);
    }

    // ── AI-015: 自动提取待办/日程 ──
    public function extractTasks(int $convId, LlmService $llm): JsonResponse
    {
        $messages = ConversationMessage::where('conversation_id', $convId)
            ->whereNull('deleted_at')->orderBy('created_at', 'desc')->take(50)->get()->reverse();
        if ($messages->isEmpty()) return ApiResponse::error('暂无消息', 400);

        $lines = $messages->map(fn($m) => ($m->sender?->name ?? '用户').': '.$m->content)->implode("\n");

        try {
            $result = $llm->chat([
                ['role' => 'system', 'content' => '你是一个待办事项提取助手。从对话中提取所有待办事项、任务、日程安排，以JSON数组格式返回，每项包含：type(todo/event), title, deadline(如有), assignee(如有)。只返回JSON。'],
                ['role' => 'user', 'content' => "对话内容：\n{$lines}"],
            ], ['temperature' => 0.1], 'extract_tasks');

            $content = $result['content'] ?? '[]';
            if (preg_match('/\[.*?\]/s', $content, $matches)) {
                $tasks = json_decode($matches[0], true) ?: [];
            } else {
                $tasks = [];
            }
        } catch (\Throwable $e) {
            $tasks = [];
        }

        return ApiResponse::success([
            'tasks' => array_slice($tasks, 0, 10),
            'total' => count($messages),
        ]);
    }

    // ── AI-017: 对话主题标签 ──
    public function autoTagConversation(int $convId, LlmService $llm): JsonResponse
    {
        $messages = ConversationMessage::where('conversation_id', $convId)
            ->whereNull('deleted_at')->orderBy('created_at', 'desc')->take(30)->get()->reverse();
        if ($messages->isEmpty()) return ApiResponse::error('暂无消息', 400);

        $lines = $messages->map(fn($m) => ($m->sender?->name ?? '用户').': '.$m->content)->implode("\n")."\n\n共".count($messages)."条消息";

        try {
            $result = $llm->chat([
                ['role' => 'system', 'content' => '你是一个对话分类助手。根据内容给对话打1-3个中文标签（如：项目讨论/客户投诉/技术支持/促销活动/内部协作/问题反馈/产品咨询/合同审批/其他），用JSON数组格式返回。只返回标签数组。'],
                ['role' => 'user', 'content' => "对话内容：\n{$lines}"],
            ], ['temperature' => 0.1], 'auto_tag');

            $content = $result['content'] ?? '[]';
            if (preg_match('/\[.*?\]/s', $content, $matches)) {
                $tags = json_decode($matches[0], true) ?: [];
            } else {
                $tags = [];
            }
        } catch (\Throwable $e) {
            $tags = [];
        }

        return ApiResponse::success([
            'tags' => array_slice($tags, 0, 3),
            'conversation_id' => $convId,
        ]);
    }

    // ── AI-018: 智能会话分类 ──
    public function classifyConversations(LlmService $llm): JsonResponse
    {
        $myId = auth()->id();
        $convIds = ConversationParticipant::where('user_id', $myId)
            ->whereNull('deleted_at')->pluck('conversation_id');
        $convs = UserConversation::whereIn('id', $convIds)
            ->whereNotNull('last_message_at')
            ->orderBy('last_message_at', 'desc')
            ->take(20)->get();

        $classified = [];
        foreach ($convs as $conv) {
            $lastMsg = ConversationMessage::where('conversation_id', $conv->id)
                ->whereNull('deleted_at')->latest()->first();
            $category = 'other';
            $reason = '';

            if ($lastMsg) {
                $text = mb_strtolower($lastMsg->content ?? '');
                // 基于关键词的快速分类
                if (preg_match('/退款|投诉|赔偿|差评|故障|无法|坏了|报错/i', $text)) {
                    $category = 'urgent';
                    $reason = '包含投诉/故障关键词';
                } elseif (preg_match('/促销|优惠|折扣|买|价格|多少钱|套餐/i', $text)) {
                    $category = 'promotion';
                    $reason = '包含促销/购买关键词';
                } elseif (preg_match('/你好|在吗|请问|帮忙|谢谢|ok|好的/i', $text)) {
                    $category = 'normal';
                    $reason = '普通对话';
                } elseif (preg_match('/项目|方案|合同|审批|汇报|开会/i', $text)) {
                    $category = 'work';
                    $reason = '工作相关';
                } elseif (preg_match('/垃圾|广告|退订|spam/i', $text)) {
                    $category = 'spam';
                    $reason = '疑似垃圾消息';
                } else {
                    $category = 'other';
                    $reason = '其他';
                }
            }

            $classified[] = [
                'id' => $conv->id,
                'name' => $conv->name ?? '会话',
                'last_message' => $lastMsg?->content ?? '',
                'category' => $category,
                'reason' => $reason,
            ];
        }

        // 可选：用 LLM 增强分类
        $grouped = collect($classified)->groupBy('category')->map(fn($items, $cat) => [
            'category' => $cat,
            'count' => $items->count(),
            'conversations' => $items->take(5)->values(),
        ])->values();

        return ApiResponse::success([
            'categories' => $grouped,
            'total' => count($classified),
        ]);
    }

    // ── AI-016: 智能通知优先级 ──

    /**
     * 内部评估紧急度（消息发送时自动调用）
     */
    protected function evaluateUrgencyInternal(string $text): array
    {
        $urgentPatterns = [
            '紧急|立刻|马上|加急|ASAP|重要|严重|crisis|urgent|宕机|崩溃|故障|无法使用|数据丢失' => 'high',
            '请问|帮忙|咨询|请教|问题|疑问|help|issue|建议|反馈' => 'medium',
        ];

        $priority = 'low';
        $reason = '一般消息';
        foreach ($urgentPatterns as $pattern => $level) {
            if (preg_match('/'.$pattern.'/i', $text)) {
                $priority = $level;
                $reason = '关键词匹配';
                break;
            }
        }

        // LLM 增强
        if ($priority !== 'high') {
            try {
                $llm = app(LlmService::class);
                $priorityResult = $this->evaluatePriorityWithLlm($llm, $text);
                if ($priorityResult !== null) {
                    $priority = $priorityResult;
                    $reason = 'AI 分析';
                }
            } catch (\Throwable $e) {
                // 降级
            }
        }

        return [
            'priority' => $priority,
            'reason' => $reason,
            'should_notify' => $priority !== 'low',
            'bypass_dnd' => $priority === 'high',
        ];
    }

    /**
     * 公开 API：评估消息紧急度
     */
    public function evaluateUrgency(Request $request): JsonResponse
    {
        $request->validate(['message' => 'required|string|max:1000']);
        $result = $this->evaluateUrgencyInternal($request->input('message'));
        return ApiResponse::success($result);
    }

    /**
     * ── AI-044 发送前 AI 预审（前端调用，快速检查） ──
     */
    public function preReview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string|max:10000',
        ]);

        $reviewService = app(\App\Services\AiMessageReviewService::class);
        $result = $reviewService->quickCheck($validated['content']);

        return ApiResponse::success($result);
    }

    /**
     * ── AI-044 发送前 AI 预审（含 LLM 深度审查） ──
     */
    public function deepReview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string|max:10000',
        ]);

        $reviewService = app(\App\Services\AiMessageReviewService::class);
        $result = $reviewService->review($validated['content']);

        return ApiResponse::success($result);
    }

    /**
     * 使用 LLM 评估优先级
     */
    protected function evaluatePriorityWithLlm(LlmService $llm, string $text): ?string
    {
        try {
            $promptService = app(\App\Services\PromptTemplateService::class);
            $sentimentPrompt = $promptService->renderByCategory('sentiment', ['message' => $text]);
            $systemPrompt = $sentimentPrompt ?: '你是一个消息优先级判断助手。判断以下消息的紧急程度：high(紧急/需立即处理)、medium(需关注/可稍后)、low(普通/无需处理)。只返回 high/medium/low 之一。';

            $result = $llm->chat([
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $text],
            ], ['temperature' => 0.1, 'max_tokens' => 10], 'evaluate_urgency');

            $llmPriority = trim(strtolower($result['content'] ?? ''));
            if (in_array($llmPriority, ['high', 'medium', 'low'])) {
                return $llmPriority;
            }
        } catch (\Throwable $e) {
            // 降级
        }
        return null;
    }

    // ── AI-011: 语义缓存集成 ──
    public function cacheStats(SemanticCacheService $cache): JsonResponse
    {
        return ApiResponse::success($cache->stats('user_chat'));
    }

    public function clearCache(SemanticCacheService $cache): JsonResponse
    {
        $cache->clear('user_chat');
        return ApiResponse::success(null, '缓存已清除');
    }

    // ── 产品推荐与商品卡片 ──
    public function recommendProducts(Request $request, LlmService $llm): JsonResponse
    {
        $request->validate(['message' => 'required|string|max:1000']);
        $message = $request->input('message');

        // 获取可售卖产品列表
        $products = \App\Models\Product::where('is_active', true)
            ->where('is_sellable', true)
            ->select('id', 'name', 'slug', 'description', 'base_price', 'image_url', 'tags', 'sales_count')
            ->get();

        if ($products->isEmpty()) {
            return ApiResponse::success(['recommendations' => [], 'message' => '暂无可推荐产品']);
        }

        $productList = $products->map(fn($p) => "- {$p->name}（{$p->base_price}元）：{$p->description}")->implode("\n");

        try {
            $result = $llm->chat([
                ['role' => 'system', 'content' => "你是一个产品推荐助手。根据用户消息从以下产品中推荐最匹配的1-3个产品，只返回JSON数组格式：[{\"id\":产品ID,\"reason\":\"推荐理由\"}]"],
                ['role' => 'user', 'content' => "用户消息：{$message}\n\n可选产品：\n{$productList}"],
            ], ['temperature' => 0.3], 'product_recommend');

            $content = $result['content'] ?? '[]';
            $matched = [];
            if (preg_match('/\[.*?\]/s', $content, $matches)) {
                $decoded = json_decode($matches[0], true) ?: [];
                foreach ($decoded as $item) {
                    $product = $products->firstWhere('id', $item['id'] ?? 0);
                    if ($product) {
                        $matched[] = [
                            'id' => $product->id,
                            'name' => $product->name,
                            'description' => $product->description,
                            'price' => $product->base_price,
                            'image_url' => $product->image_url,
                            'slug' => $product->slug,
                            'reason' => $item['reason'] ?? '',
                            'deep_link' => "im://product?id={$product->id}",
                            'action_url' => "/build/products/{$product->id}",
                        ];
                    }
                }
            }

            if (empty($matched)) {
                // 降级：返回最热门产品
                $top = $products->sortByDesc('sales_count')->first();
                if ($top) {
                    $matched[] = [
                        'id' => $top->id,
                        'name' => $top->name,
                        'description' => $top->description,
                        'price' => $top->base_price,
                        'image_url' => $top->image_url,
                        'slug' => $top->slug,
                        'reason' => '热门推荐',
                        'deep_link' => "im://product?id={$top->id}",
                        'action_url' => "/build/products/{$top->id}",
                    ];
                }
            }

            return ApiResponse::success(['recommendations' => $matched]);
        } catch (\Throwable $e) {
            return ApiResponse::success(['recommendations' => [], 'message' => '推荐暂时不可用']);
        }
    }

    // ── 发送商品卡片消息 ──
    public function sendProductCard(int $convId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'content' => 'nullable|string|max:500',
            'trace_id' => 'nullable|string|max:64',
        ]);

        $product = \App\Models\Product::findOrFail($validated['product_id']);
        $traceId = $validated['trace_id'] ?? ('card-' . uniqid());

        // 构建卡片 JSON
        $cardData = [
            'type' => 'product_card',
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'description' => mb_substr($product->description ?? '', 0, 200),
                'price' => $product->base_price,
                'image_url' => $product->image_url,
                'deep_link' => "im://product?id={$product->id}",
                'action_url' => "/build/products/{$product->id}",
                'action_label' => '查看详情',
            ],
        ];

        $msg = ConversationMessage::create([
            'conversation_id' => $convId,
            'sender_id' => auth()->id(),
            'content' => $validated['content'] ?? "推荐产品：{$product->name}",
            'message_type' => 'card',
            'metadata' => $cardData,
            'client_msg_id' => 'card-' . uniqid(),
        ]);

        UserConversation::where('id', $convId)->update(['last_message_at' => now()]);

        // 记录发送事件
        try {
            CardConversionTracking::create([
                'trace_id' => $traceId,
                'card_type' => 'product_card',
                'message_id' => $msg->id,
                'sender_id' => auth()->id(),
                'event' => 'send',
            ]);
        } catch (\Exception $e) {
            // 追踪记录失败不影响主流程
        }

        return ApiResponse::success(
            $msg->load('sender:id,name')->setAttribute('trace_id', $traceId),
            '已发送',
            201
        );
    }

    // ── 发送订单卡片消息 ──
    public function sendOrderCard(int $convId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => 'required|string|max:100',
            'order_number' => 'required|string|max:100',
            'amount' => 'required|numeric',
            'status' => 'required|string|max:50',
            'content' => 'nullable|string|max:500',
            'trace_id' => 'nullable|string|max:64',
        ]);
        $traceId = $validated['trace_id'] ?? ('card-' . uniqid());

        $cardData = [
            'type' => 'order_card',
            'order' => [
                'order_id' => $request->input('order_id'),
                'order_number' => $request->input('order_number'),
                'amount' => $request->input('amount'),
                'status' => $request->input('status'),
                'deep_link' => "im://order?id=" . $request->input('order_id'),
                'action_url' => "/build/orders/" . $request->input('order_id'),
                'action_label' => '查看订单',
            ],
        ];

        $msg = ConversationMessage::create([
            'conversation_id' => $convId,
            'sender_id' => auth()->id(),
            'content' => $request->input('content') ?? "订单：{$request->input('order_number')}",
            'message_type' => 'card',
            'metadata' => $cardData,
            'client_msg_id' => 'card-' . uniqid(),
        ]);

        UserConversation::where('id', $convId)->update(['last_message_at' => now()]);

        // 记录发送事件
        try {
            CardConversionTracking::create([
                'trace_id' => $traceId,
                'card_type' => 'order_card',
                'message_id' => $msg->id,
                'sender_id' => auth()->id(),
                'event' => 'send',
            ]);
        } catch (\Exception $e) {
            // 追踪记录失败不影响主流程
        }

        return ApiResponse::success(
            $msg->load('sender:id,name')->setAttribute('trace_id', $traceId),
            '已发送',
            201
        );
    }

    // ── 发送自定义卡片消息（审批/待办等） ──
    public function sendCustomCard(int $convId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'card_type' => 'required|string|in:approval,todo,notice,info',
            'title' => 'required|string|max:200',
            'content' => 'nullable|string|max:500',
            'fields' => 'nullable|array',
            'fields.*.label' => 'required|string|max:100',
            'fields.*.value' => 'required|string|max:200',
            'actions' => 'nullable|array',
            'actions.*.label' => 'required|string|max:50',
            'actions.*.action' => 'required|string|max:100',
            'actions.*.type' => 'nullable|in:primary,default,danger',
            'trace_id' => 'nullable|string|max:64',
        ]);
        $traceId = $validated['trace_id'] ?? ('card-' . uniqid());

        $cardData = [
            'type' => "{$validated['card_type']}_card",
            'card' => [
                'title' => $validated['title'],
                'fields' => $validated['fields'] ?? [],
                'actions' => $validated['actions'] ?? [],
                'deep_link' => "im://{$validated['card_type']}",
            ],
        ];

        $msg = ConversationMessage::create([
            'conversation_id' => $convId,
            'sender_id' => auth()->id(),
            'content' => $validated['content'] ?? $validated['title'],
            'message_type' => 'card',
            'metadata' => $cardData,
            'client_msg_id' => 'card-' . uniqid(),
        ]);

        UserConversation::where('id', $convId)->update(['last_message_at' => now()]);

        // 记录发送事件
        try {
            CardConversionTracking::create([
                'trace_id' => $traceId,
                'card_type' => $validated['card_type'] . '_card',
                'message_id' => $msg->id,
                'sender_id' => auth()->id(),
                'event' => 'send',
            ]);
        } catch (\Exception $e) {
            // 追踪记录失败不影响主流程
        }

        return ApiResponse::success(
            $msg->load('sender:id,name')->setAttribute('trace_id', $traceId),
            '已发送',
            201
        );
    }

    // ── 卡片动作回调（按钮点击 → POST API） ──
    public function cardCallback(Request $request): JsonResponse
    {
        $request->validate([
            'message_id' => 'required|integer|exists:conversation_messages,id',
            'callback_id' => 'required|string|max:100',
            'payload' => 'nullable|array',
            'trace_id' => 'nullable|string|max:64',
        ]);

        $msg = ConversationMessage::findOrFail($request->input('message_id'));
        $callbackId = $request->input('callback_id');
        $payload = $request->input('payload', []);
        $myId = auth()->id();
        $traceId = $request->input('trace_id');

        // 根据 callback_id 分发处理
        $response = match ($callbackId) {
            'approve_approval' => $this->handleApproveApproval($payload),
            'reject_approval' => $this->handleRejectApproval($payload),
            'complete_todo' => $this->handleCompleteTodo($payload),
            'claim_coupon' => $this->handleClaimCoupon($payload, $myId),
            default => ['success' => true, 'message' => '回调已接收'],
        };

        // 记录转化追踪事件
        if ($response['success'] ?? false) {
            try {
                \App\Models\CardConversionTracking::create([
                    'trace_id' => $traceId ?? ('cb-' . uniqid()),
                    'card_type' => $msg->metadata['type'] ?? 'unknown',
                    'message_id' => $msg->id,
                    'sender_id' => $msg->sender_id,
                    'receiver_id' => $myId,
                    'event' => 'convert',
                    'callback_id' => $callbackId,
                    'payload' => $payload,
                ]);
            } catch (\Exception $e) {
                // 追踪记录失败不影响主流程
            }
        }

        $response['trace_id'] = $traceId ?? null;

        return ApiResponse::success($response, $response['message'] ?? '操作成功');
    }

    private function handleApproveApproval(array $payload): array
    {
        $approvalId = $payload['approval_id'] ?? 0;
        $messageId = $payload['message_id'] ?? 0;
        $userId = auth()->id();

        // 记录审批结果到消息元数据
        if ($messageId) {
            try {
                $msg = ConversationMessage::find($messageId);
                if ($msg) {
                    $meta = $msg->metadata ?? [];
                    $meta['approval_status'] = 'approved';
                    $meta['approved_by'] = $userId;
                    $meta['approved_at'] = now()->toIso8601String();
                    $msg->update(['metadata' => $meta]);
                }
            } catch (\Exception $e) {
                // 记录失败不影响返回
            }
        }

        return ['success' => true, 'message' => '已批准', 'approval_id' => $approvalId, 'status' => 'approved'];
    }

    private function handleRejectApproval(array $payload): array
    {
        $approvalId = $payload['approval_id'] ?? 0;
        $messageId = $payload['message_id'] ?? 0;
        $reason = $payload['reason'] ?? '未提供原因';
        $userId = auth()->id();

        if ($messageId) {
            try {
                $msg = ConversationMessage::find($messageId);
                if ($msg) {
                    $meta = $msg->metadata ?? [];
                    $meta['approval_status'] = 'rejected';
                    $meta['rejected_by'] = $userId;
                    $meta['rejected_at'] = now()->toIso8601String();
                    $meta['reject_reason'] = $reason;
                    $msg->update(['metadata' => $meta]);
                }
            } catch (\Exception $e) {
                // ignore
            }
        }

        return ['success' => true, 'message' => '已拒绝', 'approval_id' => $approvalId, 'status' => 'rejected'];
    }

    private function handleCompleteTodo(array $payload): array
    {
        $todoId = $payload['todo_id'] ?? 0;
        $messageId = $payload['message_id'] ?? 0;
        $userId = auth()->id();

        if ($messageId) {
            try {
                $msg = ConversationMessage::find($messageId);
                if ($msg) {
                    $meta = $msg->metadata ?? [];
                    $meta['todo_status'] = 'completed';
                    $meta['completed_by'] = $userId;
                    $meta['completed_at'] = now()->toIso8601String();
                    $msg->update(['metadata' => $meta]);
                }
            } catch (\Exception $e) {
                // ignore
            }
        }

        return ['success' => true, 'message' => '已完成', 'todo_id' => $todoId, 'status' => 'completed'];
    }

    private function handleClaimCoupon(array $payload, int $userId): array
    {
        $couponId = $payload['coupon_id'] ?? 0;
        $couponCode = $payload['coupon_code'] ?? '';

        // 记录领券记录
        try {
            \App\Models\CardConversionTracking::create([
                'trace_id' => 'claim-' . uniqid(),
                'card_type' => 'coupon',
                'message_id' => $payload['message_id'] ?? 0,
                'sender_id' => 0,
                'receiver_id' => $userId,
                'event' => 'claim_coupon',
                'callback_id' => 'claim_coupon',
                'payload' => ['coupon_id' => $couponId, 'coupon_code' => $couponCode],
            ]);
        } catch (\Exception $e) {
            // ignore
        }

        return ['success' => true, 'message' => '已领取', 'coupon_id' => $couponId];
    }

    // ── 发送文章卡片 ──
    public function sendArticleCard(int $convId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'article_id' => 'required|string|max:100',
            'title' => 'required|string|max:200',
            'summary' => 'nullable|string|max:500',
            'cover_url' => 'nullable|string|max:500',
            'author' => 'nullable|string|max:100',
            'action_url' => 'nullable|string|max:500',
        ]);

        $cardData = [
            'type' => 'article_card',
            'article' => [
                'id' => $validated['article_id'],
                'title' => $validated['title'],
                'summary' => $validated['summary'] ?? '',
                'cover_url' => $validated['cover_url'] ?? '',
                'author' => $validated['author'] ?? '',
                'deep_link' => "im://article?id={$validated['article_id']}",
                'action_url' => $validated['action_url'] ?? '',
                'action_label' => '阅读全文',
            ],
        ];

        $msg = ConversationMessage::create([
            'conversation_id' => $convId,
            'sender_id' => auth()->id(),
            'content' => $validated['title'],
            'message_type' => 'card',
            'metadata' => $cardData,
            'client_msg_id' => 'card-' . uniqid(),
        ]);

        UserConversation::where('id', $convId)->update(['last_message_at' => now()]);
        return ApiResponse::success($msg->load('sender:id,name'), '已发送', 201);
    }

    // ── 发送审批卡片 ──
    public function sendApprovalCard(int $convId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'approval_id' => 'required|string|max:100',
            'title' => 'required|string|max:200',
            'applicant' => 'nullable|string|max:100',
            'amount' => 'nullable|numeric',
            'reason' => 'nullable|string|max:500',
            'fields' => 'nullable|array',
        ]);

        $cardData = [
            'type' => 'approval_card',
            'approval' => [
                'id' => $validated['approval_id'],
                'title' => $validated['title'],
                'applicant' => $validated['applicant'] ?? '',
                'amount' => $validated['amount'] ?? 0,
                'reason' => $validated['reason'] ?? '',
                'fields' => $validated['fields'] ?? [],
                'deep_link' => "im://approval?id={$validated['approval_id']}",
            ],
            'actions' => [
                ['label' => '✅ 批准', 'action' => 'callback', 'callback_id' => 'approve_approval', 'type' => 'primary', 'payload' => ['approval_id' => $validated['approval_id']]],
                ['label' => '❌ 拒绝', 'action' => 'callback', 'callback_id' => 'reject_approval', 'type' => 'danger', 'payload' => ['approval_id' => $validated['approval_id']]],
            ],
        ];

        $msg = ConversationMessage::create([
            'conversation_id' => $convId,
            'sender_id' => auth()->id(),
            'content' => "[审批] {$validated['title']}",
            'message_type' => 'card',
            'metadata' => $cardData,
            'client_msg_id' => 'card-' . uniqid(),
        ]);

        UserConversation::where('id', $convId)->update(['last_message_at' => now()]);
        return ApiResponse::success($msg->load('sender:id,name'), '已发送', 201);
    }

    // ── 发送优惠券卡片 ──
    public function sendCouponCard(int $convId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'coupon_id' => 'required|string|max:100',
            'title' => 'required|string|max:200',
            'discount' => 'required|string|max:50',
            'expire_at' => 'nullable|string|max:50',
            'condition' => 'nullable|string|max:200',
        ]);

        $cardData = [
            'type' => 'coupon_card',
            'coupon' => [
                'id' => $validated['coupon_id'],
                'title' => $validated['title'],
                'discount' => $validated['discount'],
                'expire_at' => $validated['expire_at'] ?? '',
                'condition' => $validated['condition'] ?? '',
                'deep_link' => "im://coupon?id={$validated['coupon_id']}",
            ],
            'actions' => [
                ['label' => '🎫 立即领取', 'action' => 'callback', 'callback_id' => 'claim_coupon', 'type' => 'primary', 'payload' => ['coupon_id' => $validated['coupon_id']]],
            ],
        ];

        $msg = ConversationMessage::create([
            'conversation_id' => $convId,
            'sender_id' => auth()->id(),
            'content' => "[优惠券] {$validated['title']}",
            'message_type' => 'card',
            'metadata' => $cardData,
            'client_msg_id' => 'card-' . uniqid(),
        ]);

        UserConversation::where('id', $convId)->update(['last_message_at' => now()]);
        return ApiResponse::success($msg->load('sender:id,name'), '已发送', 201);
    }

    // ── 发送待办卡片 ──
    public function sendTodoCard(int $convId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'todo_id' => 'required|string|max:100',
            'title' => 'required|string|max:200',
            'deadline' => 'nullable|string|max:50',
            'assignee' => 'nullable|string|max:100',
            'priority' => 'nullable|in:low,medium,high,urgent',
        ]);

        $priorityColors = ['low' => 'info', 'medium' => 'warning', 'high' => 'danger', 'urgent' => 'danger'];

        $cardData = [
            'type' => 'todo_card',
            'todo' => [
                'id' => $validated['todo_id'],
                'title' => $validated['title'],
                'deadline' => $validated['deadline'] ?? '',
                'assignee' => $validated['assignee'] ?? '',
                'priority' => $validated['priority'] ?? 'medium',
                'deep_link' => "im://todo?id={$validated['todo_id']}",
            ],
            'actions' => [
                ['label' => '✅ 标记完成', 'action' => 'callback', 'callback_id' => 'complete_todo', 'type' => 'primary', 'payload' => ['todo_id' => $validated['todo_id']]],
                ['label' => '查看详情', 'action' => 'open_url', 'url' => "im://todo?id={$validated['todo_id']}", 'type' => 'default'],
            ],
        ];

        $msg = ConversationMessage::create([
            'conversation_id' => $convId,
            'sender_id' => auth()->id(),
            'content' => "[待办] {$validated['title']}",
            'message_type' => 'card',
            'metadata' => $cardData,
            'client_msg_id' => 'card-' . uniqid(),
        ]);

        UserConversation::where('id', $convId)->update(['last_message_at' => now()]);
        return ApiResponse::success($msg->load('sender:id,name'), '已发送', 201);
    }

    // ── 消息转发 ──
    public function forwardMessages(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message_ids' => 'required|array|min:1|max:50',
            'message_ids.*' => 'integer|exists:conversation_messages,id',
            'target_conversation_id' => 'required|integer|exists:user_conversations,id',
        ]);

        $myId = auth()->id();
        $targetConvId = $validated['target_conversation_id'];

        // 验证目标会话参与
        $isParticipant = ConversationParticipant::where('conversation_id', $targetConvId)
            ->where('user_id', $myId)->whereNull('deleted_at')->exists();
        if (!$isParticipant) {
            return ApiResponse::error('你不是目标会话的参与者');
        }

        $messages = ConversationMessage::whereIn('id', $validated['message_ids'])
            ->with('sender:id,name')
            ->get()
            ->keyBy('id');

        if (count($messages) !== count($validated['message_ids'])) {
            return ApiResponse::error('部分消息不存在');
        }

        // 单条转发 vs 合并转发
        if (count($messages) === 1) {
            $original = $messages->first();
            $newMsg = ConversationMessage::create([
                'conversation_id' => $targetConvId,
                'sender_id' => $myId,
                'message_type' => $original->message_type,
                'content' => $original->content,
                'attachments' => $original->attachments,
                'metadata' => array_merge($original->metadata ?? [], [
                    'forwarded' => true,
                    'original_sender' => $original->sender?->name ?? '用户',
                    'original_sender_id' => $original->sender_id,
                    'original_message_id' => $original->id,
                    'original_conversation_id' => $original->conversation_id,
                ]),
                'client_msg_id' => 'fwd-' . uniqid(),
            ]);
        } else {
            // 合并转发
            $forwardItems = $messages->map(fn($m) => [
                'sender' => $m->sender?->name ?? '用户',
                'content' => match ($m->message_type) {
                    'text' => mb_substr($m->content, 0, 200),
                    'image' => '[图片]',
                    'voice' => '[语音]',
                    'file' => '[文件]',
                    'card' => '[卡片]',
                    default => '[' . $m->message_type . ']',
                },
                'message_type' => $m->message_type,
                'time' => $m->created_at?->toTimeString(),
            ])->values()->toArray();

            $newMsg = ConversationMessage::create([
                'conversation_id' => $targetConvId,
                'sender_id' => $myId,
                'message_type' => 'forward',
                'content' => "合并转发 {$forwardItems} 条消息",
                'attachments' => $forwardItems,
                'metadata' => [
                    'forwarded' => true,
                    'merge_forward' => true,
                    'message_count' => count($forwardItems),
                    'original_message_ids' => $validated['message_ids'],
                ],
                'client_msg_id' => 'mfwd-' . uniqid(),
            ]);
        }

        UserConversation::where('id', $targetConvId)->update(['last_message_at' => now()]);

        return ApiResponse::success($newMsg->load('sender:id,name'), 
            count($messages) === 1 ? '消息已转发' : '合并转发成功',
            201);
    }

    /**
     * 获取可转发的会话列表
     */
    public function forwardableConversations(Request $request): JsonResponse
    {
        $myId = auth()->id();
        $search = $request->input('search', '');

        $convs = UserConversation::whereHas('participants', fn($q) => $q->where('user_id', $myId)->whereNull('deleted_at'))
            ->with(['participants.user:id,name,avatar', 'lastMessage'])
            ->where(function ($q) use ($search) {
                if ($search) {
                    $q->whereHas('participants.user', fn($sq) => $sq->where('name', 'like', "%{$search}%"));
                }
            })
            ->orderByDesc('updated_at')
            ->take(50)
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->name ?: $c->participants->filter(fn($p) => $p->user_id !== $myId)->first()?->user?->name ?? '会话',
                'type' => $c->type,
                'avatar' => $c->participants->filter(fn($p) => $p->user_id !== $myId)->first()?->user?->avatar ?? '',
            ]);

        return ApiResponse::success($convs);
    }

    public function translateMessage(int $messageId, Request $request, LlmService $llm): JsonResponse
    {
        $msg = ConversationMessage::findOrFail($messageId);
        $conv = UserConversation::findOrFail($msg->conversation_id);
        $isParticipant = ConversationParticipant::where('conversation_id', $conv->id)
            ->where('user_id', auth()->id())->exists();
        if (!$isParticipant) {
            return ApiResponse::error('无权访问', 403);
        }

        $targetLang = $request->input('target', 'zh-CN');
        $supported = ['zh-CN', 'en', 'ja', 'ko', 'fr', 'de', 'es', 'ru', 'th', 'vi'];
        if (!in_array($targetLang, $supported)) {
            return ApiResponse::error('不支持的目标语言', 422);
        }

        $text = $msg->content;
        if (empty($text)) {
            return ApiResponse::error('无可翻译的内容', 400);
        }

        $langNames = [
            'zh-CN' => '简体中文', 'en' => '英语', 'ja' => '日语',
            'ko' => '韩语', 'fr' => '法语', 'de' => '德语',
            'es' => '西班牙语', 'ru' => '俄语', 'th' => '泰语', 'vi' => '越南语',
        ];

        try {
            $result = $llm->chat([
                ['role' => 'system', 'content' => "你是一个专业翻译。请将以下文本翻译成{$langNames[$targetLang]}。只返回翻译结果，不要任何解释。"],
                ['role' => 'user', 'content' => $text],
            ], ['temperature' => 0.1]);

            $translated = $result['content'] ?? '';
            return ApiResponse::success([
                'original' => $text,
                'translated' => $translated,
                'target_lang' => $targetLang,
                'message_id' => $messageId,
            ]);
        } catch (\Throwable $e) {
            Log::error('[Translate] ' . $e->getMessage());
            return ApiResponse::error('翻译失败，请稍后重试', 500);
        }
    }

    public function translateConversation(int $convId, Request $request, LlmService $llm): JsonResponse
    {
        $conv = UserConversation::findOrFail($convId);
        $isParticipant = ConversationParticipant::where('conversation_id', $conv->id)
            ->where('user_id', auth()->id())->exists();
        if (!$isParticipant) {
            return ApiResponse::error('无权访问', 403);
        }

        $targetLang = $request->input('target', 'zh-CN');
        $supported = ['zh-CN', 'en', 'ja', 'ko', 'fr', 'de', 'es'];
        if (!in_array($targetLang, $supported)) {
            return ApiResponse::error('不支持的目标语言', 422);
        }

        // 获取最近 50 条文本消息
        $messages = ConversationMessage::where('conversation_id', $convId)
            ->where('message_type', 'text')
            ->whereNotNull('content')
            ->where('content', '!=', '')
            ->latest()
            ->take(50)
            ->get()
            ->reverse()
            ->values();

        if ($messages->isEmpty()) {
            return ApiResponse::error('没有可翻译的文本消息', 400);
        }

        // 拼接所有消息
        $text = $messages->map(fn($m, $i) => "[{$i}] " . ($m->sender?->name ?? '用户') . ": {$m->content}")->implode("\n\n");

        $langNames = ['zh-CN' => '简体中文', 'en' => '英语', 'ja' => '日语', 'ko' => '韩语', 'fr' => '法语', 'de' => '德语', 'es' => '西班牙语'];

        try {
            $result = $llm->chat([
                ['role' => 'system', 'content' => "你是一个专业翻译。请将会话中所有消息翻译成{$langNames[$targetLang]}。保持消息序号格式 [N]。只返回翻译结果。"],
                ['role' => 'user', 'content' => $text],
            ], ['temperature' => 0.1]);

            $translated = $result['content'] ?? '';
            return ApiResponse::success([
                'conversation_id' => $convId,
                'translated' => $translated,
                'target_lang' => $targetLang,
                'message_count' => $messages->count(),
            ]);
        } catch (\Throwable $e) {
            Log::error('[TranslateConversation] ' . $e->getMessage());
            return ApiResponse::error('翻译失败', 500);
        }
    }
}
