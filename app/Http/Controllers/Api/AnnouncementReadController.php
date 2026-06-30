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
                'name' => $r->user?->name ?? '用户#' . $r->user_id,
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
}
