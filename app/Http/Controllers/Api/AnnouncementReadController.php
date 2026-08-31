<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\ConversationParticipant;
use Illuminate\Http\Request;

class AnnouncementReadController extends Controller
{
    /**
     * 已读记录列表
     */
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'announcement_id' => 'required|integer|exists:announcements,id',
            'user_id' => 'nullable|integer|exists:users,id',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $announcement = Announcement::findOrFail($validated['announcement_id']);
        if ($denied = $this->denyUnlessMember($request, $announcement->conversation_id)) {
            return $denied;
        }

        $query = AnnouncementRead::where('announcement_id', $validated['announcement_id'])
            ->with('user:id,name,avatar');

        if (!empty($validated['user_id'])) {
            $query->where('user_id', $validated['user_id']);
        }

        return ApiResponse::paginated(
            $query->orderByDesc('read_at')->paginate($validated['per_page'] ?? 20)
        );
    }

    /**
     * 创建/标记已读记录
     */
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'announcement_id' => 'required|integer|exists:announcements,id',
            'user_id' => 'nullable|integer|exists:users,id',
        ]);

        $announcement = Announcement::findOrFail($validated['announcement_id']);
        $targetUserId = $validated['user_id'] ?? $request->user()->id;

        if ($targetUserId !== $request->user()->id && !$this->isAdmin($request)) {
            return ApiResponse::error('FORBIDDEN', __("app.announcement_read.msg_8ee3ed15"), 403);
        }

        if ($denied = $this->denyUnlessMember($request, $announcement->conversation_id, $targetUserId)) {
            return $denied;
        }

        $read = AnnouncementRead::firstOrCreate([
            'announcement_id' => $announcement->id,
            'user_id' => $targetUserId,
        ], ['read_at' => now()]);

        return ApiResponse::success(
            $read->load('user:id,name,avatar'),
            __('app.announcement_read.marked_read'),
            $read->wasRecentlyCreated ? 201 : 200
        );
    }

    /**
     * 删除已读记录
     */
    public function destroy(int $id, Request $request): \Illuminate\Http\JsonResponse
    {
        $read = AnnouncementRead::with('announcement')->findOrFail($id);

        if ($read->user_id !== $request->user()->id && !$this->isAdmin($request)) {
            return ApiResponse::error('FORBIDDEN', __("app.announcement_read.msg_4e2e1826"), 403);
        }

        if ($denied = $this->denyUnlessMember($request, $read->announcement->conversation_id)) {
            return $denied;
        }

        $read->delete();

        return ApiResponse::success(null, __("app.announcement_read.msg_4fabc768"));
    }

    /**
     * 获取公告的已读进度
     */
    public function readProgress(int $announcementId): \Illuminate\Http\JsonResponse
    {
        $announcement = Announcement::with('conversation')->findOrFail($announcementId);
        $totalMembers = ConversationParticipant::where('conversation_id', $announcement->conversation_id)
            ->whereNull('deleted_at')->count();

        $reads = AnnouncementRead::where('announcement_id', $announcementId)
            ->with('user:id,name,avatar')
            ->get();

        $readCount = $reads->count();
        $progressPercent = $totalMembers > 0 ? round(($readCount / $totalMembers) * 100) : 0;

        return ApiResponse::success([
            'announcement_id' => $announcementId,
            'title' => $announcement->title,
            'total_members' => $totalMembers,
            'read_count' => $readCount,
            'unread_count' => $totalMembers - $readCount,
            'progress_percent' => $progressPercent,
            'read_by' => $reads->map(fn($r) => [
                'user_id' => $r->user_id,
                'name' => $r->user?->name ?? __('app.announcement_read.user_prefix') . $r->user_id,
                'avatar' => $r->user?->avatar,
                'read_at' => $r->read_at,
            ]),
        ]);
    }

    /**
     * 获取所有公告的阅读统计
     */
    public function announcementStats(Request $request): \Illuminate\Http\JsonResponse
    {
        $convId = $request->input('conversation_id');
        $query = Announcement::query();
        if ($convId) {
            $query->where('conversation_id', $convId);
        }

        $announcements = $query->withCount('reads')->latest()->paginate(20);

        $announcements->getCollection()->transform(function ($ann) {
            $totalMembers = ConversationParticipant::where('conversation_id', $ann->conversation_id)
                ->whereNull('deleted_at')->count();
            return [
                'id' => $ann->id,
                'title' => $ann->title,
                'sender' => $ann->sender?->name ?? '系统',
                'created_at' => $ann->created_at,
                'read_count' => $ann->reads_count,
                'total_members' => $totalMembers,
                'read_percent' => $totalMembers > 0 ? round(($ann->reads_count / $totalMembers) * 100) : 0,
            ];
        });

        return ApiResponse::success($announcements);
    }

    private function isAdmin(Request $request): bool
    {
        $user = $request->user();
        return $user && $user->hasAnyRole(['admin', 'super-admin']);
    }

    private function denyUnlessMember(Request $request, int $conversationId, ?int $userId = null): ?\Illuminate\Http\JsonResponse
    {
        $userId = $userId ?? $request->user()->id;
        $isMember = ConversationParticipant::where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->exists();

        if (!$isMember && !$this->isAdmin($request)) {
            return ApiResponse::error('FORBIDDEN', __("app.announcement_read.msg_b5b76f03"), 403);
        }

        return null;
    }
}
