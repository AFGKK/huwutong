<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ConversationMessage;
use App\Models\ConversationParticipant;
use App\Models\User;
use App\Models\UserConversation;
use App\Models\UserFriend;
use App\Models\UserOnlineStatus;
use App\Models\UserReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImAdminController extends Controller
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

        // 附加消息数和会话数
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
        return ApiResponse::success(null, '群组已解散');
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
        return ApiResponse::success(null, '消息已删除');
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

        // 消息趋势（近7天）
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
        return ApiResponse::success(null, '举报已处理');
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
        return ApiResponse::success(null, '会话已删除');
    }

    public function banUser(int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $user->update(['status' => 'banned']);
        return ApiResponse::success(null, '用户已封禁');
    }

    public function unbanUser(int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $user->update(['status' => 'active']);
        return ApiResponse::success(null, '用户已解封');
    }
}
