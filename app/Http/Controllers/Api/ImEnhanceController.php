<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AgentGroup;
use App\Models\AgentPerformanceLog;
use App\Models\AutoReplyRule;
use App\Models\CannedReply;
use App\Models\SensitiveWord;
use App\Services\SensitiveWordService;
use App\Models\ConversationTag;
use App\Models\LiveChatConversation;
use App\Models\LiveChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImEnhanceController extends Controller
{
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
        return ApiResponse::success($reply, '快捷回复已创建', 201);
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
        return ApiResponse::success($reply->fresh(), '已更新');
    }

    public function cannedDestroy(int $id): JsonResponse
    {
        CannedReply::findOrFail($id)->delete();
        return ApiResponse::success(null, '已删除');
    }

    // ── 会话标签 ──
    public function tagIndex(): JsonResponse
    {
        return ApiResponse::success(ConversationTag::orderBy('sort_order')->get());
    }

    public function tagStore(Request $request): JsonResponse
    {
        $validated = $request->validate(['name' => 'required|string|max:50|unique:conversation_tags,name', 'color' => 'nullable|string|max:20']);
        return ApiResponse::success(ConversationTag::create($validated), '标签已创建', 201);
    }

    public function tagUpdate(int $id, Request $request): JsonResponse
    {
        $tag = ConversationTag::findOrFail($id);
        $validated = $request->validate(['name' => 'sometimes|string|max:50|unique:conversation_tags,name,'.$id, 'color' => 'nullable|string|max:20', 'is_active' => 'nullable|boolean']);
        $tag->update($validated);
        return ApiResponse::success($tag->fresh(), '已更新');
    }

    public function tagDestroy(int $id): JsonResponse
    {
        ConversationTag::findOrFail($id)->delete();
        return ApiResponse::success(null, '已删除');
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
        return ApiResponse::success(null, '标签已分配');
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

    // ── 客服组 ──
    public function groupIndex(): JsonResponse
    {
        return ApiResponse::success(AgentGroup::with('members.user:id,name')->orderBy('sort_order')->get());
    }

    public function groupStore(Request $request): JsonResponse
    {
        $validated = $request->validate(['name' => 'required|string|max:50|unique:agent_groups,name', 'description' => 'nullable|string|max:255', 'color' => 'nullable|string|max:20']);
        return ApiResponse::success(AgentGroup::create($validated), '客服组已创建', 201);
    }

    public function groupUpdate(int $id, Request $request): JsonResponse
    {
        $group = AgentGroup::findOrFail($id);
        $validated = $request->validate(['name' => 'sometimes|string|max:50|unique:agent_groups,name,'.$id, 'description' => 'nullable|string|max:255', 'color' => 'nullable|string|max:20', 'is_active' => 'nullable|boolean']);
        $group->update($validated);
        return ApiResponse::success($group->fresh()->load('members.user:id,name'), '已更新');
    }

    public function groupDestroy(int $id): JsonResponse
    {
        AgentGroup::findOrFail($id)->delete();
        return ApiResponse::success(null, '已删除');
    }

    public function groupAddMember(Request $request): JsonResponse
    {
        $validated = $request->validate(['group_id' => 'required|exists:agent_groups,id', 'user_id' => 'required|exists:users,id', 'role' => 'nullable|string|in:leader,member']);
        \App\Models\AgentGroupMember::firstOrCreate(
            ['group_id' => $validated['group_id'], 'user_id' => $validated['user_id']],
            ['role' => $validated['role'] ?? 'member']
        );
        return ApiResponse::success(null, '成员已添加');
    }

    public function groupRemoveMember(int $groupId, int $userId): JsonResponse
    {
        \App\Models\AgentGroupMember::where('group_id', $groupId)->where('user_id', $userId)->delete();
        return ApiResponse::success(null, '成员已移除');
    }

    // ── 自动回复规则 ──
    public function ruleIndex(): JsonResponse
    {
        return ApiResponse::success(AutoReplyRule::with('agentGroup:id,name')->orderBy('priority', 'desc')->get());
    }

    public function ruleStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'trigger_type' => 'nullable|string|in:keyword,regex,all',
            'trigger_value' => 'nullable|string',
            'match_mode' => 'nullable|string|in:exact,contains,regex',
            'reply_content' => 'required|string',
            'agent_group_id' => 'nullable|exists:agent_groups,id',
            'priority' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);
        return ApiResponse::success(AutoReplyRule::create($validated), '规则已创建', 201);
    }

    public function ruleUpdate(int $id, Request $request): JsonResponse
    {
        $rule = AutoReplyRule::findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'trigger_type' => 'nullable|string|in:keyword,regex,all',
            'trigger_value' => 'nullable|string',
            'match_mode' => 'nullable|string|in:exact,contains,regex',
            'reply_content' => 'sometimes|string',
            'agent_group_id' => 'nullable|exists:agent_groups,id',
            'priority' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);
        $rule->update($validated);
        return ApiResponse::success($rule->fresh()->load('agentGroup:id,name'), '已更新');
    }

    public function ruleDestroy(int $id): JsonResponse
    {
        AutoReplyRule::findOrFail($id)->delete();
        return ApiResponse::success(null, '已删除');
    }

    // ── 聊天记录导出 ──
    public function exportConversation(int $id): \Illuminate\Http\Response
    {
        $conv = LiveChatConversation::findOrFail($id);
        $messages = LiveChatMessage::where('live_chat_conversation_id', $id)->orderBy('created_at')->get();

        $html = "<!DOCTYPE html><html><head><meta charset='utf-8'><title>对话记录 #{$id}</title>";
        $html .= "<style>body{font-family:sans-serif;max-width:800px;margin:auto;padding:20px}.msg{margin:12px 0;padding:10px;border-radius:8px}.user{background:#e8f4fd}.agent{background:#f0fdf0}.system{background:#f5f5f5;color:#999;text-align:center;font-size:12px}.time{font-size:11px;color:#999}</style></head><body>";
        $html .= "<h2>对话记录 #{$id}</h2><p>状态: {$conv->status} | 时间: {$conv->created_at}</p><hr>";

        foreach ($messages as $msg) {
            $role = $msg->is_from_agent ? 'agent' : ($msg->is_system ? 'system' : 'user');
            $html .= "<div class='msg {$role}'>";
            $html .= "<strong>" . ($msg->is_from_agent ? '客服' : ($msg->is_system ? '系统' : '用户')) . "</strong>";
            $html .= "<span class='time'> {$msg->created_at}</span>";
            $html .= "<div>" . nl2br(e($msg->content)) . "</div>";
            if ($msg->attachments) {
                foreach (json_decode($msg->attachments, true) ?? [] as $att) {
                    $html .= "<div><a href='{$att['url']}'>📎 {$att['name']}</a></div>";
                }
            }
            $html .= "</div>";
        }
        $html .= "</body></html>";

        return response($html, 200, ['Content-Type' => 'text/html; charset=utf-8', 'Content-Disposition' => "attachment; filename=\"conversation-{$id}.html\""]);
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

    // ── 客服绩效看板 ──
    public function agentPerformance(Request $request): JsonResponse
    {
        $days = min((int) $request->input('days', 30), 90);
        $logs = AgentPerformanceLog::with('user:id,name')
            ->where('log_date', '>=', now()->subDays($days))
            ->orderBy('log_date')
            ->get();

        $summary = [
            'total_conversations' => $logs->sum('conversations_count'),
            'total_messages' => $logs->sum('messages_count'),
            'avg_response_seconds' => $logs->avg('avg_response_seconds') ? round($logs->avg('avg_response_seconds')) : 0,
            'avg_satisfaction' => $logs->avg('satisfaction_score') ? round($logs->avg('satisfaction_score'), 2) : null,
        ];

        // 按客服汇总
        $byAgent = $logs->groupBy('user_id')->map(function ($items, $uid) {
            $user = $items->first()->user;
            return [
                'user_id' => $uid,
                'user_name' => $user?->name ?? "用户 #{$uid}",
                'conversations' => $items->sum('conversations_count'),
                'messages' => $items->sum('messages_count'),
                'avg_response' => round($items->avg('avg_response_seconds')),
                'satisfaction' => round($items->avg('satisfaction_score'), 2),
                'handoffs' => $items->sum('handoffs_count'),
            ];
        })->values();

        // 趋势
        $trend = $logs->groupBy('log_date')->map(function ($items, $date) {
            return ['date' => $date, 'conversations' => $items->sum('conversations_count'), 'messages' => $items->sum('messages_count')];
        })->values()->sortBy('date')->values();

        return ApiResponse::success(compact('summary', 'byAgent', 'trend'));
    }

    // ── 消息已读 ──
    public function markAsRead(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'conversation_id' => 'required|integer',
            'message_ids' => 'nullable|array',
            'message_ids.*' => 'integer',
        ]);

        $query = LiveChatMessage::where('conversation_id', $validated['conversation_id'])
            ->where('is_read', false)
            ->where('sender_type', '!=', 'agent');

        if (!empty($validated['message_ids'])) {
            $query->whereIn('id', $validated['message_ids']);
        }

        $count = $query->update(['is_read' => true, 'read_at' => now()]);

        // 重置未读计数
        LiveChatConversation::where('id', $validated['conversation_id'])->update(['unread_count' => 0]);

        return ApiResponse::success(['marked_read' => $count]);
    }

    // ── 会话置顶/取消置顶 ──
    public function togglePin(int $id): JsonResponse
    {
        $conv = LiveChatConversation::findOrFail($id);
        $conv->update(['is_pinned' => !$conv->is_pinned]);
        return ApiResponse::success(['is_pinned' => $conv->fresh()->is_pinned], $conv->is_pinned ? '已置顶' : '已取消置顶');
    }

    // ── 会话免打扰切换 ──
    public function toggleMute(int $id): JsonResponse
    {
        $conv = LiveChatConversation::findOrFail($id);
        $conv->update(['is_muted' => !$conv->is_muted]);
        return ApiResponse::success(['is_muted' => $conv->fresh()->is_muted], $conv->is_muted ? '已开启免打扰' : '已关闭免打扰');
    }

    // ── 删除会话（软删除） ──
    public function softDeleteConv(int $id): JsonResponse
    {
        $conv = LiveChatConversation::findOrFail($id);
        $conv->update(['deleted_at' => now()]);
        return ApiResponse::success(null, '会话已删除');
    }

    // ── 恢复会话 ──
    public function restoreConv(int $id): JsonResponse
    {
        LiveChatConversation::withTrashed()->findOrFail($id)->update(['deleted_at' => null]);
        return ApiResponse::success(null, '会话已恢复');
    }

    // ── 草稿保存 ──
    public function saveDraft(int $id, Request $request): JsonResponse
    {
        $conv = LiveChatConversation::findOrFail($id);
        $validated = $request->validate(['draft_content' => 'nullable|string|max:5000']);
        $conv->update(['draft_content' => $validated['draft_content'] ?? null]);
        return ApiResponse::success(null, '草稿已保存');
    }

    // ── 消息搜索 ──
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

        $results = collect();

        // 搜索客服对话消息
        if (in_array($scope, ['all', 'livechat'])) {
            $liveQuery = \App\Models\LiveChatMessage::with('conversation')
                ->where('content', 'like', '%' . $keyword . '%');
            if (!empty($validated['conversation_id'])) {
                $liveQuery->where('conversation_id', $validated['conversation_id']);
            }
            $liveResults = $liveQuery->orderBy('created_at', 'desc')->limit($perPage)->get();
            $results = $results->concat($liveResults->map(fn($m) => [
                'id' => $m->id,
                'content' => $m->content,
                'conversation_id' => $m->conversation_id,
                'conversation_name' => $m->conversation?->name ?? '客服对话',
                'sender_type' => $m->sender_type ?? 'user',
                'created_at' => $m->created_at,
                'source' => 'livechat',
            ]));
        }

        // 搜索用户聊天消息
        if (in_array($scope, ['all', 'userchat'])) {
            $userQuery = \App\Models\ConversationMessage::where('content', 'like', '%' . $keyword . '%')
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
            $results = $results->concat($userResults->map(fn($m) => [
                'id' => $m->id,
                'content' => $m->content,
                'conversation_id' => $m->conversation_id,
                'conversation_name' => $m->conversation?->name ?? '聊天',
                'sender_type' => $m->sender_id === $userId ? 'self' : 'other',
                'created_at' => $m->created_at,
                'source' => 'userchat',
            ]));
        }

        // 按时间排序
        $sorted = $results->sortByDesc('created_at')->values()->take($perPage);

        return ApiResponse::success([
            'data' => $sorted,
            'total' => $sorted->count(),
        ]);
    }

    // ── 未读会话列表 ──
    public function unreadConversations(): JsonResponse
    {
        $convs = LiveChatConversation::whereNull('deleted_at')
            ->where('unread_count', '>', 0)
            ->orderBy('is_pinned', 'desc')
            ->orderBy('updated_at', 'desc')
            ->get(['id', 'status', 'unread_count', 'is_pinned', 'updated_at']);

        return ApiResponse::success([
            'total_unread' => $convs->sum('unread_count'),
            'conversations' => $convs,
        ]);
    }

    // ── 桌面通知配置 ──
    public function notifyConfig(Request $request): JsonResponse
    {
        // 保存用户通知偏好（前端通过 localStorage 控制声音/桌面通知）
        // 后端只需返回当前会话未读数供前端判断
        $unread = LiveChatConversation::whereNull('deleted_at')->where('unread_count', '>', 0)->count();
        return ApiResponse::success(['has_unread' => $unread > 0, 'unread_count' => $unread]);
    }

    // ── IM Dashboard 聚合统计 ──
    public function imDashboard(): JsonResponse
    {
        $activeConvs = LiveChatConversation::whereIn('status', ['active', 'waiting'])->count();
        $pendingHandoffs = \App\Models\LiveChatHandoff::where('status', 'pending')->count();
        $totalToday = LiveChatConversation::whereDate('created_at', today())->count();
        $avgRating = LiveChatConversation::whereNotNull('rating')->avg('rating');

        return ApiResponse::success([
            'active_conversations' => $activeConvs,
            'pending_handoffs' => $pendingHandoffs,
            'today_conversations' => $totalToday,
            'avg_rating' => $avgRating ? round($avgRating, 1) : null,
            'canned_replies_count' => CannedReply::count(),
            'agent_groups_count' => AgentGroup::count(),
            'auto_rules_count' => AutoReplyRule::count(),
            'tags_count' => ConversationTag::count(),
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
        return ApiResponse::success($word, '敏感词已添加', 201);
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
        return ApiResponse::success($word->fresh(), '已更新');
    }

    public function sensitiveDestroy(int $id): JsonResponse
    {
        SensitiveWord::findOrFail($id)->delete();
        SensitiveWordService::clearCache();
        return ApiResponse::success(null, '已删除');
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
        ], "成功导入 {$imported} 条，跳过 {$skipped} 条");
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
