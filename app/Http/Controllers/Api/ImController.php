<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\CannedReply;
use App\Models\ConversationMessage;
use App\Models\ConversationParticipant;
use App\Models\ConversationTag;
use App\Models\SensitiveWord;
use App\Models\User;
use App\Models\UserConversation;
use App\Models\UserFriend;
use App\Models\UserOnlineStatus;
use App\Models\UserReport;
use App\Services\SensitiveWordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImController extends Controller
{
    // ════════════════════════════════════════════
    // ADMIN-002: IM 用户管理
    // ════════════════════════════════════════════

    public function users(Request $request): JsonResponse
    {
        $query = User::whereExists(function($q) {
            $q->selectRaw('1')->from('conversation_participants')
                ->whereColumn('conversation_participants.user_id', 'users.id')
                ->whereNull('conversation_participants.deleted_at');
        });

        if ($q = $request->input('q')) {
            $query->where(function($qry) use ($q) {
                $qry->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $users = $query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 20));

        $users->getCollection()->transform(function($user) {
            $user->total_convs = ConversationParticipant::where('user_id', $user->id)
                ->whereNull('deleted_at')->count();
            $user->total_msgs = ConversationMessage::where('sender_id', $user->id)->count();
            return $user;
        });

        return ApiResponse::paginated($users);
    }

    public function userDetail(int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $totalConvs = ConversationParticipant::where('user_id', $id)->whereNull('deleted_at')->count();
        $totalMsgs = ConversationMessage::where('sender_id', $id)->count();
        $online = UserOnlineStatus::where('user_id', $id)->first();
        $friendCount = UserFriend::where(function($q) use ($id) {
            $q->where('user_id', $id)->orWhere('friend_id', $id);
        })->where('status', 'accepted')->count();

        return ApiResponse::success([
            'user' => $user,
            'online' => $online ? ($online->is_online ? 'online' : 'offline') : 'offline',
            'friend_count' => $friendCount,
            'total_msgs' => $totalMsgs,
            'total_convs' => $totalConvs,
            'last_active' => $online?->last_active_at,
        ]);
    }

    // ════════════════════════════════════════════
    // ADMIN-003: 群组管理
    // ════════════════════════════════════════════

    public function groups(Request $request): JsonResponse
    {
        $query = UserConversation::where('type', 'group')
            ->withCount('participants as member_count')
            ->with('creator:id,name');

        if ($q = $request->input('q')) {
            $query->where('name', 'like', "%{$q}%");
        }

        return ApiResponse::paginated(
            $query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 20))
        );
    }

    public function groupDetail(int $id): JsonResponse
    {
        $group = UserConversation::where('type', 'group')
            ->with(['participants.user:id,name,email', 'creator:id,name'])
            ->findOrFail($id);

        $msgCount = ConversationMessage::where('conversation_id', $id)->count();

        return ApiResponse::success([
            'group' => $group,
            'member_count' => $group->participants->count(),
            'total_messages' => $msgCount,
        ]);
    }

    public function dismissGroup(int $id): JsonResponse
    {
        $group = UserConversation::where('type', 'group')->findOrFail($id);
        ConversationParticipant::where('conversation_id', $id)->update(['deleted_at' => now()]);
        $group->update(['deleted_at' => now()]);
        return ApiResponse::success(null, __("app.im_admin.msg_4c805273"));
    }

    // ════════════════════════════════════════════
    // ADMIN-004: 消息审计
    // ════════════════════════════════════════════

    public function messageAudit(Request $request): JsonResponse
    {
        $query = ConversationMessage::with('sender:id,name', 'conversation:id,name,type')
            ->whereNull('deleted_at');

        if ($userId = $request->input('user_id')) {
            $query->where('sender_id', $userId);
        }
        if ($convId = $request->input('conversation_id')) {
            $query->where('conversation_id', $convId);
        }
        if ($type = $request->input('message_type')) {
            $query->where('message_type', $type);
        }
        if ($q = $request->input('q')) {
            $query->where('content', 'like', "%{$q}%");
        }
        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        return ApiResponse::paginated(
            $query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 20))
        );
    }

    public function deleteMessage(int $id): JsonResponse
    {
        $msg = ConversationMessage::findOrFail($id);
        $msg->update(['deleted_at' => now(), 'content' => '[管理员已删除]']);
        return ApiResponse::success(null, __("app.im_admin.msg_cb126a6e"));
    }

    // ════════════════════════════════════════════
    // ADMIN-005: 数据看板
    // ════════════════════════════════════════════

    public function dashboard(): JsonResponse
    {
        $today = now()->startOfDay();
        $weekAgo = now()->subDays(7);

        $totalUsers = ConversationParticipant::whereNull('deleted_at')->distinct('user_id')->count('user_id');
        $totalGroups = UserConversation::where('type', 'group')->whereNull('deleted_at')->count();
        $totalConvs = UserConversation::whereNull('deleted_at')->count();
        $totalMsgs = ConversationMessage::whereNull('deleted_at')->count();
        $todayMsgs = ConversationMessage::whereNull('deleted_at')->where('created_at', '>=', $today)->count();
        $weekMsgs = ConversationMessage::whereNull('deleted_at')->where('created_at', '>=', $weekAgo)->count();
        $activeUsers = ConversationMessage::whereNull('deleted_at')
            ->where('created_at', '>=', $weekAgo)
            ->distinct('sender_id')->count('sender_id');
        $reports = UserReport::count();

        $trend = collect();
        for ($i = 6; $i >= 0; $i--) {
            $day = now()->subDays($i)->startOfDay();
            $count = ConversationMessage::whereNull('deleted_at')
                ->where('created_at', '>=', $day)
                ->where('created_at', '<', $day->copy()->addDay())
                ->count();
            $trend->push(['date' => $day->format('m-d'), 'count' => $count]);
        }

        return ApiResponse::success([
            'total_users' => $totalUsers,
            'total_groups' => $totalGroups,
            'total_conversations' => $totalConvs,
            'total_messages' => $totalMsgs,
            'today_messages' => $todayMsgs,
            'week_messages' => $weekMsgs,
            'active_users_7d' => $activeUsers,
            'pending_reports' => $reports,
            'message_trend' => $trend,
        ]);
    }

    // ════════════════════════════════════════════
    // ADMIN-007: 举报管理
    // ════════════════════════════════════════════

    public function reports(Request $request): JsonResponse
    {
        $query = UserReport::with('reporter:id,name', 'reportable');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        return ApiResponse::paginated(
            $query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 20))
        );
    }

    public function resolveReport(int $id, Request $request): JsonResponse
    {
        $report = UserReport::findOrFail($id);
        $report->update([
            'status' => 'resolved',
            'handled_by' => auth()->id(),
            'admin_note' => $request->input('note', ''),
            'handled_at' => now(),
        ]);
        return ApiResponse::success(null, __("app.im_admin.msg_f6685574"));
    }

    // ════════════════════════════════════════════
    // ADMIN-008: 会话管理
    // ════════════════════════════════════════════

    public function conversations(Request $request): JsonResponse
    {
        $query = UserConversation::with([
            'participants.user:id,name,avatar' => fn($q) => $q->whereNull('deleted_at'),
        ])->withCount(['messages', 'participants']);

        if ($q = $request->input('q')) {
            $query->where(function($q2) use ($q) {
                $q2->where('name', 'like', "%{$q}%")
                    ->orWhereHas('participants.user', fn($u) => $u->where('name', 'like', "%{$q}%"));
            });
        }

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        return ApiResponse::paginated(
            $query->latest('updated_at')->paginate($request->input('per_page', 20))
        );
    }

    public function conversationDetail(int $id): JsonResponse
    {
        $conv = UserConversation::with([
            'creator:id,name',
            'participants.user:id,name,avatar',
        ])->withCount('messages')
        ->findOrFail($id);

        $recentMessages = ConversationMessage::where('conversation_id', $id)
            ->with('sender:id,name')
            ->latest()
            ->take(20)
            ->get()
            ->map(fn($m) => [
                'id' => $m->id,
                'sender' => $m->sender ? ['id' => $m->sender->id, 'name' => $m->sender->name] : null,
                'content' => $m->content,
                'message_type' => $m->message_type,
                'created_at' => $m->created_at,
            ]);

        return ApiResponse::success([
            'conversation' => $conv,
            'recent_messages' => $recentMessages,
        ]);
    }

    public function deleteConversation(int $id): JsonResponse
    {
        $conv = UserConversation::findOrFail($id);
        ConversationMessage::where('conversation_id', $id)->delete();
        ConversationParticipant::where('conversation_id', $id)->delete();
        $conv->delete();
        return ApiResponse::success(null, __("app.im_admin.msg_ecf41f26"));
    }

    public function banUser(int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $user->update(['status' => 'banned']);
        return ApiResponse::success(null, __("app.im_admin.msg_2763cffb"));
    }

    public function unbanUser(int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $user->update(['status' => 'active']);
        return ApiResponse::success(null, __("app.im_admin.msg_c44e531b"));
    }

    // ════════════════════════════════════════════
    // IM 增强功能（原 ImEnhanceController）
    // ════════════════════════════════════════════

    // ── 快捷回复 ──
    public function cannedIndex(Request $request): JsonResponse
    {
        $query = CannedReply::with('user:id,name');
        if ($request->input('mine')) {
            $query->where(function ($q) {
                $q->where('user_id', auth()->id())->orWhere('is_shared', true);
            });
        }
        if ($request->filled('category')) $query->where('category', $request->input('category'));
        return ApiResponse::success($query->orderBy('category')->orderBy('id')->get());
    }

    public function cannedStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => 'nullable|string|max:50',
            'title' => 'required|string|max:100',
            'content' => 'required|string',
            'shortcuts' => 'nullable|array',
            'is_shared' => 'nullable|boolean',
        ]);
        $validated['user_id'] = auth()->id();
        $reply = CannedReply::create($validated);
        return ApiResponse::success($reply, __('app.api.im.quick_reply_created'), 201);
    }

    public function cannedUpdate(int $id, Request $request): JsonResponse
    {
        $reply = CannedReply::findOrFail($id);
        $validated = $request->validate([
            'category' => 'nullable|string|max:50',
            'title' => 'sometimes|string|max:100',
            'content' => 'sometimes|string',
            'shortcuts' => 'nullable|array',
            'is_shared' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);
        $reply->update($validated);
        return ApiResponse::success($reply->fresh(), __('app.api.im.updated'));
    }

    public function cannedDestroy(int $id): JsonResponse
    {
        CannedReply::findOrFail($id)->delete();
        return ApiResponse::success(null, __('app.api.im.deleted'));
    }

    // ── 会话标签 ──
    public function tagIndex(): JsonResponse
    {
        return ApiResponse::success(ConversationTag::orderBy('sort_order')->get());
    }

    public function tagStore(Request $request): JsonResponse
    {
        $validated = $request->validate(['name' => 'required|string|max:50|unique:conversation_tags,name', 'color' => 'nullable|string|max:20']);
        return ApiResponse::success(ConversationTag::create($validated), __('app.api.im.tag_created'), 201);
    }

    public function tagUpdate(int $id, Request $request): JsonResponse
    {
        $tag = ConversationTag::findOrFail($id);
        $validated = $request->validate(['name' => 'sometimes|string|max:50|unique:conversation_tags,name,'.$id, 'color' => 'nullable|string|max:20', 'is_active' => 'nullable|boolean']);
        $tag->update($validated);
        return ApiResponse::success($tag->fresh(), __('app.api.im.updated'));
    }

    public function tagDestroy(int $id): JsonResponse
    {
        ConversationTag::findOrFail($id)->delete();
        return ApiResponse::success(null, __('app.api.im.deleted'));
    }

    public function tagAssign(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'conversation_type' => 'required|string|in:live_chat,handoff,user_chat',
            'conversation_id' => 'required|integer',
            'tag_ids' => 'required|array',
            'tag_ids.*' => 'exists:conversation_tags,id',
        ]);
        \DB::table('conversation_tag_assignments')
            ->where('conversation_type', $validated['conversation_type'])
            ->where('conversation_id', $validated['conversation_id'])
            ->delete();
        foreach ($validated['tag_ids'] as $tagId) {
            \DB::table('conversation_tag_assignments')->insert([
                'conversation_type' => $validated['conversation_type'],
                'conversation_id' => $validated['conversation_id'],
                'tag_id' => $tagId,
                'assigned_by' => auth()->id(),
            ]);
        }
        return ApiResponse::success(null, __('app.api.im.tag_assigned'));
    }

    public function tagGetAssigned(Request $request): JsonResponse
    {
        $validated = $request->validate(['conversation_type' => 'required|string', 'conversation_id' => 'required|integer']);
        $tags = \DB::table('conversation_tag_assignments')
            ->join('conversation_tags', 'tag_id', '=', 'conversation_tags.id')
            ->where('conversation_type', $validated['conversation_type'])
            ->where('conversation_id', $validated['conversation_id'])
            ->get(['conversation_tags.id', 'conversation_tags.name', 'conversation_tags.color']);
        return ApiResponse::success($tags);
    }

    // ── 客服组（CS AgentGroup，非用户群 /im-admin/groups）已退役 ──
    public function groupIndex(): JsonResponse
    {
        return $this->csDeskRetired(__('app.api.im.cs_groups_retired'));
    }

    public function groupStore(Request $request): JsonResponse
    {
        return $this->csDeskRetired(__('app.api.im.cs_groups_retired'));
    }

    public function groupUpdate(int $id, Request $request): JsonResponse
    {
        return $this->csDeskRetired(__('app.api.im.cs_groups_retired'));
    }

    public function groupDestroy(int $id): JsonResponse
    {
        return $this->csDeskRetired(__('app.api.im.cs_groups_retired'));
    }

    public function groupAddMember(Request $request): JsonResponse
    {
        return $this->csDeskRetired(__('app.api.im.cs_groups_retired'));
    }

    public function groupRemoveMember(int $groupId, int $userId): JsonResponse
    {
        return $this->csDeskRetired(__('app.api.im.cs_groups_retired'));
    }

    // ── CS 自动回复规则已退役（用户自动回复：/user-chat/auto-reply） ──
    public function ruleIndex(): JsonResponse
    {
        return $this->csDeskRetired(__('app.api.im.cs_auto_reply_retired'));
    }

    public function ruleStore(Request $request): JsonResponse
    {
        return $this->csDeskRetired(__('app.api.im.cs_auto_reply_retired'));
    }

    public function ruleUpdate(int $id, Request $request): JsonResponse
    {
        return $this->csDeskRetired(__('app.api.im.cs_auto_reply_retired'));
    }

    public function ruleDestroy(int $id): JsonResponse
    {
        return $this->csDeskRetired(__('app.api.im.cs_auto_reply_retired'));
    }

    /**
     * Live Chat 桌面相关 IM 接口已退役
     */
    protected function liveChatRetired(): JsonResponse
    {
        return $this->csDeskRetired(__('app.api.live_chat_api.disabled'));
    }

    /** CS 客服桌面遗留接口（客服组 / 自动回复规则 / 绩效看板） */
    protected function csDeskRetired(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], 410);
    }

    // ── 聊天记录导出（原 Live Chat；已退役） ──
    public function exportConversation(int $id): JsonResponse
    {
        return $this->liveChatRetired();
    }

    // ── 文件上传 ──
    public function uploadChatFile(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|max:20480']);
        $path = $request->file('file')->store('chat-attachments', 'public');
        return ApiResponse::success([
            'url' => Storage::url($path),
            'name' => $request->file('file')->getClientOriginalName(),
            'size' => $request->file('file')->getSize(),
            'mime' => $request->file('file')->getMimeType(),
        ]);
    }

    // ── 客服绩效看板已退役 ──
    public function agentPerformance(Request $request): JsonResponse
    {
        return $this->csDeskRetired(__('app.api.im.agent_performance_retired'));
    }

    // ── 消息已读（原 Live Chat；已退役） ──
    public function markAsRead(Request $request): JsonResponse
    {
        return $this->liveChatRetired();
    }

    // ── 会话置顶/取消置顶（原 Live Chat；已退役） ──
    public function togglePin(int $id): JsonResponse
    {
        return $this->liveChatRetired();
    }

    // ── 会话免打扰切换（原 Live Chat；已退役） ──
    public function toggleMute(int $id): JsonResponse
    {
        return $this->liveChatRetired();
    }

    // ── 删除会话（原 Live Chat；已退役） ──
    public function softDeleteConv(int $id): JsonResponse
    {
        return $this->liveChatRetired();
    }

    // ── 恢复会话（原 Live Chat；已退役） ──
    public function restoreConv(int $id): JsonResponse
    {
        return $this->liveChatRetired();
    }

    // ── 草稿保存（原 Live Chat；已退役） ──
    public function saveDraft(int $id, Request $request): JsonResponse
    {
        return $this->liveChatRetired();
    }

    // ── 消息搜索（仅 user-chat；Live Chat 范围已移除） ──
    public function searchMessages(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => 'required|string|min:1|max:200',
            'keyword' => 'nullable|string|min:1|max:200',
            'conversation_id' => 'nullable|integer',
            'scope' => 'nullable|string|in:all,livechat,userchat',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $keyword = $validated['keyword'] ?? $validated['q'] ?? '';
        $scope = $validated['scope'] ?? 'all';
        $perPage = $validated['per_page'] ?? 20;
        $userId = auth()->id();

        // livechat 范围已退役：livechat 返回空；all / userchat 只搜 user-chat
        if ($scope === 'livechat') {
            return ApiResponse::success(['data' => [], 'total' => 0]);
        }

        $userQuery = ConversationMessage::where('content', 'like', '%' . $keyword . '%')
            ->where(function ($q) use ($userId) {
                $q->where('sender_id', $userId)
                  ->orWhereHas('conversation', function ($cq) use ($userId) {
                      $cq->whereHas('participants', fn($pq) => $pq->where('user_id', $userId));
                  });
            });
        if (!empty($validated['conversation_id'])) {
            $userQuery->where('conversation_id', $validated['conversation_id']);
        }
        $userResults = $userQuery->orderBy('created_at', 'desc')->limit($perPage)->get();
        $sorted = $userResults->map(fn($m) => [
            'id' => $m->id,
            'content' => $m->content,
            'conversation_id' => $m->conversation_id,
            'conversation_name' => $m->conversation?->name ?? __('app.api.im.chat'),
            'sender_type' => $m->sender_id === $userId ? 'self' : 'other',
            'created_at' => $m->created_at,
            'source' => 'userchat',
        ])->values();

        return ApiResponse::success([
            'data' => $sorted,
            'total' => $sorted->count(),
        ]);
    }

    // ── 未读会话列表（原 Live Chat；已退役） ──
    public function unreadConversations(): JsonResponse
    {
        return ApiResponse::success([
            'total_unread' => 0,
            'conversations' => [],
        ]);
    }

    // ── 桌面通知配置（原 Live Chat；已退役） ──
    public function notifyConfig(Request $request): JsonResponse
    {
        return ApiResponse::success(['has_unread' => false, 'unread_count' => 0]);
    }

    // ── IM Dashboard 聚合统计（user-chat 页内看板；不含 CS KPI） ──
    public function imDashboard(): JsonResponse
    {
        $activeSince = now()->subDay();

        $totalConversations = UserConversation::query()->count();
        $todayMessages = ConversationMessage::query()
            ->whereDate('created_at', today())
            ->count();
        $activeUsers = (int) (ConversationMessage::query()
            ->where('created_at', '>=', $activeSince)
            ->toBase()
            ->selectRaw('COUNT(DISTINCT sender_id) as aggregate')
            ->value('aggregate') ?? 0);
        $activeConvs = UserConversation::query()
            ->where(function ($q) use ($activeSince) {
                $q->where('last_message_at', '>=', $activeSince)
                    ->orWhere('updated_at', '>=', $activeSince);
            })
            ->count();
        $todayConversations = UserConversation::query()
            ->whereDate('created_at', today())
            ->count();

        $cannedCount = 0;
        $tagsCount = 0;

        if (\Illuminate\Support\Facades\Schema::hasTable('canned_replies')) {
            $cannedCount = CannedReply::count();
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('conversation_tags')) {
            $tagsCount = ConversationTag::count();
        }

        return ApiResponse::success([
            'total_conversations' => $totalConversations,
            'today_messages' => $todayMessages,
            'active_users' => $activeUsers,
            'total_canned' => $cannedCount,
            'active_conversations' => $activeConvs,
            'today_conversations' => $todayConversations,
            'canned_replies_count' => $cannedCount,
            'tags_count' => $tagsCount,
        ]);
    }

    // ── 敏感词管理 ──
    public function sensitiveIndex(Request $request): JsonResponse
    {
        $query = SensitiveWord::query();
        if ($cat = $request->input('category')) $query->byCategory($cat);
        if ($q = $request->input('q')) $query->where('word', 'like', "%{$q}%");
        return ApiResponse::success($query->orderBy('word')->paginate(50));
    }

    public function sensitiveStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'word' => 'required|string|max:100|unique:sensitive_words,word',
            'replacement' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:50',
            'severity' => 'nullable|in:low,medium,high,critical',
        ]);
        $word = SensitiveWord::create([
            'word' => $validated['word'],
            'replacement' => $validated['replacement'] ?? '***',
            'category' => $validated['category'] ?? 'general',
            'severity' => $validated['severity'] ?? 'medium',
        ]);
        SensitiveWordService::clearCache();
        return ApiResponse::success($word, __('app.api.im.sensitive_added'), 201);
    }

    public function sensitiveUpdate(int $id, Request $request): JsonResponse
    {
        $word = SensitiveWord::findOrFail($id);
        $word->update($request->validate([
            'word' => 'sometimes|string|max:100|unique:sensitive_words,word,'.$id,
            'replacement' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:50',
            'severity' => 'nullable|in:low,medium,high,critical',
            'is_active' => 'nullable|boolean',
        ]));
        SensitiveWordService::clearCache();
        return ApiResponse::success($word->fresh(), __('app.api.im.updated'));
    }

    public function sensitiveDestroy(int $id): JsonResponse
    {
        SensitiveWord::findOrFail($id)->delete();
        SensitiveWordService::clearCache();
        return ApiResponse::success(null, __('app.api.im.deleted'));
    }

    public function sensitiveTest(Request $request): JsonResponse
    {
        $request->validate(['text' => 'required|string|max:2000']);
        $result = app(SensitiveWordService::class)->check($request->input('text'));
        return ApiResponse::success($result);
    }

    /**
     * 批量导入敏感词
     */
    public function sensitiveImport(Request $request): JsonResponse
    {
        $request->validate([
            'words' => 'required|array|max:5000',
            'words.*.word' => 'required|string|max:100',
            'words.*.replacement' => 'nullable|string|max:100',
            'words.*.category' => 'nullable|string|max:50',
            'words.*.severity' => 'nullable|in:low,medium,high,critical',
        ]);

        $imported = 0;
        $skipped = 0;
        foreach ($request->input('words') as $item) {
            try {
                SensitiveWord::firstOrCreate(
                    ['word' => $item['word']],
                    [
                        'replacement' => $item['replacement'] ?? '***',
                        'category' => $item['category'] ?? 'general',
                        'severity' => $item['severity'] ?? 'medium',
                    ]
                );
                $imported++;
            } catch (\Exception $e) {
                $skipped++;
            }
        }
        SensitiveWordService::clearCache();
        return ApiResponse::success([
            'imported' => $imported,
            'skipped' => $skipped,
        ], __('app.api.im.import_result', ['imported' => $imported, 'skipped' => $skipped]));
    }

    /**
     * 导出敏感词
     */
    public function sensitiveExport(Request $request): JsonResponse
    {
        $words = SensitiveWord::orderBy('word')->get(['word', 'replacement', 'category', 'severity', 'is_active']);
        return ApiResponse::success($words);
    }
}
