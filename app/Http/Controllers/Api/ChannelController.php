<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\ChannelCategory;
use App\Models\ChannelMember;
use App\Models\ChannelMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Events\ChannelMessageSent;

class ChannelController extends Controller
{
    // ════════════════════════════════════════════
    // CHANNEL-001: 频道 CRUD
    // ════════════════════════════════════════════

    public function index(): JsonResponse
    {
        $myId = auth()->id();
        $myChannels = Channel::whereHas('members', fn($q) => $q->where('user_id', $myId))
            ->with('category', 'latestMessage.user:id,name', 'members.user:id,name')
            ->orderBy('last_message_at', 'desc')
            ->get();

        return ApiResponse::success($myChannels);
    }

    public function show(int $id): JsonResponse
    {
        $channel = Channel::with('category', 'creator:id,name,avatar', 'members.user:id,name,avatar')
            ->findOrFail($id);

        $myId = auth()->id();
        $myMembership = ChannelMember::where('channel_id', $id)->where('user_id', $myId)->first();

        return ApiResponse::success([
            'channel' => $channel,
            'member_count' => $channel->members->count(),
            'is_member' => $myMembership !== null,
            'my_role' => $myMembership?->role ?? null,
            'is_muted' => $myMembership?->is_muted ?? false,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'type' => 'nullable|in:public,private',
            'icon' => 'nullable|string|max:50',
            'category_id' => 'nullable|integer|exists:channel_categories,id',
        ]);

        $slug = Str::slug($validated['name']);
        // 确保 slug 唯一
        $baseSlug = $slug;
        $counter = 1;
        while (Channel::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter++;
        }

        $channel = Channel::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? '',
            'type' => $validated['type'] ?? 'public',
            'icon' => $validated['icon'] ?? '#',
            'created_by' => auth()->id(),
            'category_id' => $validated['category_id'] ?? null,
        ]);

        // 创建者自动加入并设为 owner
        ChannelMember::create([
            'channel_id' => $channel->id,
            'user_id' => auth()->id(),
            'role' => 'owner',
        ]);

        return ApiResponse::success($channel->load('creator:id,name'), '频道已创建', 201);
    }

    // ════════════════════════════════════════════
    // CHANNEL-002: 加入/离开频道
    // ════════════════════════════════════════════

    public function join(int $id): JsonResponse
    {
        $channel = Channel::where('type', 'public')->findOrFail($id);
        $myId = auth()->id();

        $existing = ChannelMember::where('channel_id', $id)->where('user_id', $myId)->first();
        if ($existing) {
            return ApiResponse::error('ALREADY_MEMBER', '已经是频道成员');
        }

        ChannelMember::create(['channel_id' => $id, 'user_id' => $myId, 'role' => 'member']);

        return ApiResponse::success(null, '已加入频道');
    }

    public function leave(int $id): JsonResponse
    {
        $myId = auth()->id();
        $member = ChannelMember::where('channel_id', $id)->where('user_id', $myId)->firstOrFail();

        if ($member->role === 'owner') {
            return ApiResponse::error('IS_OWNER', '频道所有者不能离开，请先转让所有权');
        }

        $member->delete();
        return ApiResponse::success(null, '已离开频道');
    }

    // ════════════════════════════════════════════
    // CHANNEL-003: 频道消息
    // ════════════════════════════════════════════

    public function messages(int $channelId, Request $request): JsonResponse
    {
        $channel = Channel::findOrFail($channelId);
        $myId = auth()->id();

        $isMember = ChannelMember::where('channel_id', $channelId)->where('user_id', $myId)->exists();
        if (!$isMember && $channel->type === 'private') {
            return ApiResponse::error('FORBIDDEN', '你不是频道成员', 403);
        }

        $query = ChannelMessage::with('user:id,name,avatar')
            ->with('channelReplyTo.user:id,name,avatar')
            ->where('channel_id', $channelId);

        if ($beforeId = $request->input('before_id')) {
            $query->where('id', '<', $beforeId);
        }

        $perPage = min((int) $request->input('per_page', 50), 100);
        $messages = $query->orderBy('id', 'desc')->paginate($perPage);

        // 获取其他成员的最近阅读时间，用于判断消息是否已读
        $otherReadAt = ChannelMember::where('channel_id', $channelId)
            ->where('user_id', '!=', $myId)
            ->max('last_read_at');

        // 标记已读
        ChannelMember::where('channel_id', $channelId)->where('user_id', $myId)
            ->update(['last_read_at' => now()]);

        // 为每条消息添加已读状态
        $messages->getCollection()->transform(function ($msg) use ($otherReadAt) {
            if ($otherReadAt && $msg->created_at <= $otherReadAt) {
                $msg->setAttribute('_read', true);
            } else {
                $msg->setAttribute('_read', false);
            }
            return $msg;
        });

        return ApiResponse::paginated($messages);
    }

    public function sendMessage(int $channelId, Request $request): JsonResponse
    {
        $request->validate([
            'content' => 'nullable|string|max:5000',
            'message_type' => 'nullable|string|max:20',
        ]);

        $channel = Channel::findOrFail($channelId);
        $myId = auth()->id();

        $isMember = ChannelMember::where('channel_id', $channelId)->where('user_id', $myId)->exists();
        if (!$isMember) {
            return ApiResponse::error('FORBIDDEN', '你不是频道成员', 403);
        }

        $content = $request->input('content', '');
        $messageType = $request->input('message_type', 'text');
        $attachments = $request->input('attachments');

        // Handle file upload
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->store('channel-files/' . $channelId, 'public');
            $attachments = [[
                'url' => '/storage/' . $path,
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime' => $file->getMimeType(),
            ]];
            if (str_starts_with($file->getMimeType(), 'image/')) {
                $messageType = 'image';
            } elseif (str_starts_with($file->getMimeType(), 'video/')) {
                $messageType = 'video';
            } else {
                $messageType = 'file';
            }
            $content = $file->getClientOriginalName();
        }

        $msg = ChannelMessage::create([
            'channel_id' => $channelId,
            'user_id' => $myId,
            'content' => $content,
            'message_type' => $messageType,
            'attachments' => $attachments,
            'reply_to_id' => $request->input('reply_to_id'),
        ]);

        $channel->update(['last_message_at' => now()]);

        $msg->setAttribute('_read', false);
        // 触发 AI 群主持人
        ChannelMessageSent::dispatch($msg);
        return ApiResponse::success($msg->load(['user:id,name,avatar', 'channelReplyTo.user:id,name,avatar']), '已发送', 201);
    }

    // ════════════════════════════════════════════
    // CHANNEL-010: 消息操作（删除/撤回）
    // ════════════════════════════════════════════

    public function deleteMessage(int $channelId, int $messageId): JsonResponse
    {
        $channel = Channel::findOrFail($channelId);
        $myId = auth()->id();
        $membership = ChannelMember::where('channel_id', $channelId)->where('user_id', $myId)->first();

        if (!$membership) {
            return ApiResponse::error('FORBIDDEN', '你不是频道成员', 403);
        }

        $message = ChannelMessage::where('channel_id', $channelId)->findOrFail($messageId);

        // 管理员或消息发送者可以删除
        if ($message->user_id !== $myId && !in_array($membership->role, ['owner', 'admin'])) {
            return ApiResponse::error('FORBIDDEN', '无权删除此消息', 403);
        }

        $message->delete();

        return ApiResponse::success(null, '消息已删除');
    }

    public function recallMessage(int $channelId, int $messageId): JsonResponse
    {
        $channel = Channel::findOrFail($channelId);
        $myId = auth()->id();

        $message = ChannelMessage::where('channel_id', $channelId)->findOrFail($messageId);

        if ($message->user_id !== $myId) {
            return ApiResponse::error('FORBIDDEN', '只能撤回自己的消息', 403);
        }

        // 只能撤回 2 分钟内的消息
        if ($message->created_at->diffInMinutes(now()) > 2) {
            return ApiResponse::error('TIMEOUT', '超过 2 分钟的消息不能撤回', 400);
        }

        $message->update(['is_recalled' => true]);

        return ApiResponse::success(null, '消息已撤回');
    }

    // ════════════════════════════════════════════
    // CHANNEL-004: 公开频道浏览
    // ════════════════════════════════════════════

    public function browse(Request $request): JsonResponse
    {
        $query = Channel::where('type', 'public')->where('is_active', true)
            ->withCount('members')
            ->with('category');

        if ($q = $request->input('q')) {
            $query->where(function($qry) use ($q) {
                $qry->where('name', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        if ($categoryId = $request->input('category_id')) {
            $query->where('category_id', $categoryId);
        }

        return ApiResponse::paginated($query->orderBy('last_message_at', 'desc')->paginate(20));
    }

    // ════════════════════════════════════════════
    // CHANNEL-005: 频道分类
    // ════════════════════════════════════════════

    public function categories(): JsonResponse
    {
        $categories = ChannelCategory::withCount('channels')->orderBy('sort_order')->get();
        return ApiResponse::success($categories);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $category = ChannelCategory::create([
            'name' => $validated['name'],
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return ApiResponse::success($category, '分类已创建', 201);
    }

    public function updateCategory(int $id, Request $request): JsonResponse
    {
        $category = ChannelCategory::findOrFail($id);
        $category->update($request->validate([
            'name' => 'required|string|max:100',
            'sort_order' => 'nullable|integer|min:0',
        ]));
        return ApiResponse::success($category->fresh(), '分类已更新');
    }

    public function destroyCategory(int $id): JsonResponse
    {
        $category = ChannelCategory::withCount('channels')->findOrFail($id);
        if ($category->channels_count > 0) {
            return ApiResponse::error('HAS_CHANNELS', '该分类下有频道，无法删除', 400);
        }
        $category->delete();
        return ApiResponse::success(null, '分类已删除');
    }

    // ════════════════════════════════════════════
    // 频道头像上传
    // ════════════════════════════════════════════

    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,gif,webp|max:2048',
            'channel_id' => 'required|integer|exists:channels,id',
        ]);

        $channel = Channel::findOrFail($request->input('channel_id'));
        $myId = auth()->id();
        $membership = ChannelMember::where('channel_id', $channel->id)->where('user_id', $myId)->first();

        if (!$membership || !in_array($membership->role, ['owner', 'admin'])) {
            return ApiResponse::error('FORBIDDEN', '只有频道管理员可以修改头像', 403);
        }

        $file = $request->file('avatar');
        $path = $file->store('channels', 'public');

        if (!$path) {
            return ApiResponse::error('头像上传失败', 500);
        }

        $url = '/storage/' . $path;
        $channel->update(['avatar' => $url]);

        return ApiResponse::success(['avatar' => $url], '头像已更新');
    }

    // ════════════════════════════════════════════
    // CHANNEL-006: 频道管理（管理员）
    // ════════════════════════════════════════════

    public function update(int $id, Request $request): JsonResponse
    {
        $channel = Channel::findOrFail($id);
        $myId = auth()->id();
        $membership = ChannelMember::where('channel_id', $id)->where('user_id', $myId)->first();

        if (!$membership || !in_array($membership->role, ['owner', 'admin'])) {
            return ApiResponse::error('FORBIDDEN', '只有频道管理员可以修改', 403);
        }

        $channel->update($request->only(['name', 'description', 'icon', 'avatar', 'category_id']));
        return ApiResponse::success($channel->fresh(), '已更新');
    }

    public function destroy(int $id): JsonResponse
    {
        $channel = Channel::findOrFail($id);
        $membership = ChannelMember::where('channel_id', $id)->where('user_id', auth()->id())->first();

        if (!$membership || $membership->role !== 'owner') {
            return ApiResponse::error('FORBIDDEN', '只有频道所有者可以删除', 403);
        }

        $channel->messages()->delete();
        $channel->members()->delete();
        $channel->delete();

        return ApiResponse::success(null, '频道已删除');
    }

    public function members(int $channelId): JsonResponse
    {
        Channel::findOrFail($channelId);
        $members = ChannelMember::where('channel_id', $channelId)
            ->with('user:id,name,avatar')
            ->get();

        return ApiResponse::success($members);
    }

    // ════════════════════════════════════════════
    // CHANNEL-007: 消息置顶/取消置顶
    // ════════════════════════════════════════════

    public function pinMessage(int $channelId, int $messageId): JsonResponse
    {
        $channel = Channel::findOrFail($channelId);
        $myId = auth()->id();
        $membership = ChannelMember::where('channel_id', $channelId)->where('user_id', $myId)->first();

        if (!$membership || !in_array($membership->role, ['owner', 'admin'])) {
            return ApiResponse::error('FORBIDDEN', '只有频道管理员可以置顶消息', 403);
        }

        $message = ChannelMessage::where('channel_id', $channelId)->findOrFail($messageId);
        $message->update(['is_pinned' => true]);

        return ApiResponse::success($message->fresh(), '消息已置顶');
    }

    public function unpinMessage(int $channelId, int $messageId): JsonResponse
    {
        $channel = Channel::findOrFail($channelId);
        $myId = auth()->id();
        $membership = ChannelMember::where('channel_id', $channelId)->where('user_id', $myId)->first();

        if (!$membership || !in_array($membership->role, ['owner', 'admin'])) {
            return ApiResponse::error('FORBIDDEN', '只有频道管理员可以取消置顶', 403);
        }

        $message = ChannelMessage::where('channel_id', $channelId)->findOrFail($messageId);
        $message->update(['is_pinned' => false]);

        return ApiResponse::success($message->fresh(), '已取消置顶');
    }

    public function pinnedMessages(int $channelId): JsonResponse
    {
        Channel::findOrFail($channelId);
        $messages = ChannelMessage::where('channel_id', $channelId)
            ->where('is_pinned', true)
            ->with('user:id,name,avatar')
            ->orderBy('updated_at', 'desc')
            ->get();

        return ApiResponse::success($messages);
    }

    // ════════════════════════════════════════════
    // CHANNEL-008: 通知设置
    // ════════════════════════════════════════════

    public function toggleMute(int $channelId): JsonResponse
    {
        Channel::findOrFail($channelId);
        $myId = auth()->id();
        $member = ChannelMember::where('channel_id', $channelId)->where('user_id', $myId)->firstOrFail();

        $member->update(['is_muted' => !$member->is_muted]);

        return ApiResponse::success([
            'is_muted' => $member->fresh()->is_muted,
        ], $member->is_muted ? '已开启免打扰' : '已关闭免打扰');
    }

    // ════════════════════════════════════════════
    // CHANNEL-009: 成员角色管理
    // ════════════════════════════════════════════

    public function updateMemberRole(int $channelId, int $memberId, Request $request): JsonResponse
    {
        $channel = Channel::findOrFail($channelId);
        $myId = auth()->id();
        $myMembership = ChannelMember::where('channel_id', $channelId)->where('user_id', $myId)->first();

        if (!$myMembership || $myMembership->role !== 'owner') {
            return ApiResponse::error('FORBIDDEN', '只有频道所有者可以管理成员角色', 403);
        }

        $member = ChannelMember::where('channel_id', $channelId)->findOrFail($memberId);

        if ($member->user_id === $myId) {
            return ApiResponse::error('SELF', '不能修改自己的角色');
        }

        if ($member->role === 'owner') {
            return ApiResponse::error('IS_OWNER', '不能修改所有者的角色');
        }

        $validated = $request->validate([
            'role' => 'required|in:admin,member',
        ]);

        $member->update(['role' => $validated['role']]);

        return ApiResponse::success($member->fresh()->load('user:id,name'), '角色已更新');
    }

    public function transferOwnership(int $channelId, Request $request): JsonResponse
    {
        $channel = Channel::findOrFail($channelId);
        $myId = auth()->id();
        $myMembership = ChannelMember::where('channel_id', $channelId)->where('user_id', $myId)->first();

        if (!$myMembership || $myMembership->role !== 'owner') {
            return ApiResponse::error('FORBIDDEN', '只有频道所有者可以转让', 403);
        }

        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $targetId = $validated['user_id'];

        if ($targetId === $myId) {
            return ApiResponse::error('SELF', '不能转让给自己');
        }

        $targetMember = ChannelMember::where('channel_id', $channelId)->where('user_id', $targetId)->firstOrFail();

        // 原 owner 降为 admin
        $myMembership->update(['role' => 'admin']);
        // 目标提升为 owner
        $targetMember->update(['role' => 'owner']);
        // 更新 channel 的 created_by
        $channel->update(['created_by' => $targetId]);

        return ApiResponse::success(null, '频道已转让');
    }

    public function kickMember(int $channelId, int $memberId): JsonResponse
    {
        $channel = Channel::findOrFail($channelId);
        $myId = auth()->id();
        $myMembership = ChannelMember::where('channel_id', $channelId)->where('user_id', $myId)->first();

        if (!$myMembership || !in_array($myMembership->role, ['owner', 'admin'])) {
            return ApiResponse::error('FORBIDDEN', '只有管理员可以踢出成员', 403);
        }

        $target = ChannelMember::where('channel_id', $channelId)->findOrFail($memberId);

        if ($target->role === 'owner') {
            return ApiResponse::error('FORBIDDEN', '不能踢出频道所有者', 403);
        }

        // 管理员不能踢出其他管理员
        if ($myMembership->role === 'admin' && $target->role === 'admin') {
            return ApiResponse::error('FORBIDDEN', '管理员不能踢出其他管理员', 403);
        }

        $target->delete();

        return ApiResponse::success(null, '成员已移出');
    }

    public function searchMessages(int $channelId, Request $request): JsonResponse
    {
        $channel = Channel::findOrFail($channelId);
        $myId = auth()->id();
        $isMember = ChannelMember::where('channel_id', $channelId)->where('user_id', $myId)->exists();

        if (!$isMember) {
            return ApiResponse::error('FORBIDDEN', '你不是频道成员', 403);
        }

        $validated = $request->validate([
            'q' => 'required|string|max:100',
        ]);

        $q = $validated['q'];

        $messages = ChannelMessage::where('channel_id', $channelId)
            ->where('content', 'like', "%{$q}%")
            ->with('user:id,name,avatar')
            ->orderBy('created_at', 'desc')
            ->paginate(30);

        return ApiResponse::paginated($messages);
    }
}
