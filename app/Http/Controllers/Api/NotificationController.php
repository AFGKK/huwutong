<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\NotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * 通知列表（当前用户）
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Notification::where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhere(function ($sub) use ($user) {
                      $sub->whereNull('user_id')
                          ->where('tenant_id', $user->tenant_id);
                  });
            });

        // 筛选
        if ($request->filled('filter.type')) {
            $query->where('type', $request->input('filter.type'));
        }
        if ($request->filled('type')) {
            $types = explode(',', $request->input('type'));
            $query->whereIn('type', $types);
        }
        if ($request->filled('filter.is_read')) {
            $isRead = $request->boolean('filter.is_read');
            $query->where('is_read', $isRead);
        }
        if ($request->filled('unread')) {
            $query->where('is_read', false);
        }
        if ($request->boolean('interactions_only')) {
            $query->whereIn('type', Notification::INTERACTION_TYPES);
        }

        // 排序
        $sortField = $request->input('sort', '-created_at');
        $direction = str_starts_with($sortField, '-') ? 'desc' : 'asc';
        $query->orderByDesc('updated_at')->orderBy('created_at', $direction);

        $perPage = min((int) $request->input('per_page', 20), 100);

        return ApiResponse::paginated($query->paginate($perPage));
    }

    /**
     * 互动通知聚合（收到的赞 / 评论和@ / 新增关注）
     */
    public function interactions(Request $request): JsonResponse
    {
        $user = $request->user();
        $group = $request->input('group', 'all'); // all|likes|comments|follows

        $typeMap = [
            'likes' => ['interaction_like'],
            'comments' => ['interaction_comment', 'interaction_mention'],
            'follows' => ['interaction_follow'],
            'all' => Notification::INTERACTION_TYPES,
        ];
        $types = $typeMap[$group] ?? Notification::INTERACTION_TYPES;

        $base = Notification::where('user_id', $user->id)
            ->whereIn('type', Notification::INTERACTION_TYPES);

        $unreadByGroup = [
            'likes' => (clone $base)->where('type', 'interaction_like')->where('is_read', false)->count(),
            'comments' => (clone $base)->whereIn('type', ['interaction_comment', 'interaction_mention'])->where('is_read', false)->count(),
            'follows' => (clone $base)->where('type', 'interaction_follow')->where('is_read', false)->count(),
        ];
        $unreadByGroup['all'] = array_sum($unreadByGroup);

        $query = Notification::where('user_id', $user->id)
            ->whereIn('type', $types)
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at');

        if ($request->filled('unread')) {
            $query->where('is_read', false);
        }

        $perPage = min((int) $request->input('per_page', 30), 100);
        $page = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
                'groups' => [
                    ['key' => 'all', 'unread' => $unreadByGroup['all']],
                    ['key' => 'likes', 'unread' => $unreadByGroup['likes']],
                    ['key' => 'comments', 'unread' => $unreadByGroup['comments']],
                    ['key' => 'follows', 'unread' => $unreadByGroup['follows']],
                ],
            ],
        ]);
    }

    /**
     * 未读数量
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        $baseQuery = function ($q) use ($user) {
            $q->where(function ($sub) use ($user) {
                $sub->where('user_id', $user->id)
                  ->orWhere(function ($inner) use ($user) {
                      $inner->whereNull('user_id')
                          ->where('tenant_id', $user->tenant_id);
                  });
            })->where('is_read', false);
        };

        $count = Notification::where($baseQuery)->count();

        $criticalCount = Notification::where($baseQuery)
            ->whereIn('type', ['app_suspended', 'app_force_update', 'system_alert'])
            ->count();

        $interactionCount = Notification::where('user_id', $user->id)
            ->whereIn('type', Notification::INTERACTION_TYPES)
            ->where('is_read', false)
            ->count();

        $systemBase = Notification::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
                ->orWhere(function ($sub) use ($user) {
                    $sub->whereNull('user_id')
                        ->where('tenant_id', $user->tenant_id);
                });
        })->whereNotIn('type', array_merge(Notification::INTERACTION_TYPES, Notification::IM_TYPES));

        $systemCount = (clone $systemBase)->where('is_read', false)->count();
        $systemTotal = (clone $systemBase)->count();

        $interactionLatest = Notification::where('user_id', $user->id)
            ->whereIn('type', Notification::INTERACTION_TYPES)
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->first(['title', 'content', 'updated_at', 'created_at']);

        $systemLatest = (clone $systemBase)
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->first(['title', 'content', 'updated_at', 'created_at']);

        return ApiResponse::success([
            'count' => $count,
            'critical_count' => $criticalCount,
            'interaction_count' => $interactionCount,
            'system_count' => $systemCount,
            'system_total' => $systemTotal,
            'interaction_preview' => $interactionLatest
                ? ($interactionLatest->content ?: $interactionLatest->title)
                : null,
            'system_preview' => $systemLatest
                ? ($systemLatest->content ?: $systemLatest->title)
                : null,
        ]);
    }

    /**
     * 标记已读
     */
    public function markRead(int $notification, Request $request): JsonResponse
    {
        $row = Notification::where('id', $notification)
            ->where(function ($q) use ($request) {
                $q->where('user_id', $request->user()->id)
                  ->orWhere('tenant_id', $request->user()->tenant_id);
            })
            ->firstOrFail();

        $row->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return ApiResponse::success($row, __("app.notification.msg_2d149186"));
    }

    /**
     * 标记全部已读
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Notification::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
              ->orWhere(function ($sub) use ($user) {
                  $sub->whereNull('user_id')
                      ->where('tenant_id', $user->tenant_id);
              });
        })->where('is_read', false);

        if ($request->filled('type')) {
            $types = explode(',', (string) $request->input('type'));
            $query->whereIn('type', $types);
        }
        if ($request->boolean('interactions_only')) {
            $query->whereIn('type', Notification::INTERACTION_TYPES);
        }

        $count = $query->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return ApiResponse::success(['affected' => $count], __("app.notification.msg_b9c2d3e0"));
    }

    /**
     * 批量操作
     */
    public function batch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:notifications,id',
            'action' => 'required|in:read,delete',
        ]);

        $user = $request->user();
        $query = Notification::whereIn('id', $validated['ids'])
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhere('tenant_id', $user->tenant_id);
            });

        match ($validated['action']) {
            'read' => $query->update(['is_read' => true, 'read_at' => now()]),
            'delete' => $query->delete(),
        };

        return ApiResponse::success(null, __("app.notification.msg_33130f5c"));
    }

    /**
     * 删除单条
     */
    public function destroy(int $notification, Request $request): JsonResponse
    {
        $row = Notification::where('id', $notification)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $row->delete();

        return ApiResponse::success(null, __("app.notification.msg_d4a8eb79"));
    }

    /**
     * 通知偏好设置
     */
    public function preferences(Request $request): JsonResponse
    {
        $user = $request->user();
        $prefs = NotificationPreference::firstOrCreate(
            ['user_id' => $user->id],
            [
                'channels' => ['in_app' => true, 'email' => true],
                'types' => [
                    'expiry_warning' => ['in_app' => true, 'email' => true],
                    'status_change' => ['in_app' => true, 'email' => true],
                    'system' => ['in_app' => true, 'email' => true],
                    'license_activation' => ['in_app' => true, 'email' => false],
                ],
            ]
        );

        return ApiResponse::success($prefs);
    }

    /**
     * 更新通知偏好
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'channels' => 'sometimes|array',
            'channels.in_app' => 'boolean',
            'channels.email' => 'boolean',
            'types' => 'sometimes|array',
            'quiet_hours_start' => 'nullable|string|max:5',
            'quiet_hours_end' => 'nullable|string|max:5',
        ]);

        $user = $request->user();
        $prefs = NotificationPreference::firstOrCreate(['user_id' => $user->id]);

        if (isset($validated['channels'])) {
            $channels = array_merge($prefs->channels ?? [], $validated['channels']);
            $prefs->channels = $channels;
        }

        if (isset($validated['types'])) {
            $types = array_merge($prefs->types ?? [], $validated['types']);
            $prefs->types = $types;
        }

        if (array_key_exists('quiet_hours_start', $validated)) {
            $prefs->quiet_hours_start = $validated['quiet_hours_start'];
        }
        if (array_key_exists('quiet_hours_end', $validated)) {
            $prefs->quiet_hours_end = $validated['quiet_hours_end'];
        }

        $prefs->save();

        return ApiResponse::success($prefs, __("app.notification.msg_bb908a18"));
    }
}
