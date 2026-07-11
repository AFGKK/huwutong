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

        // 排序
        $sortField = $request->input('sort', '-created_at');
        $direction = str_starts_with($sortField, '-') ? 'desc' : 'asc';
        $query->orderBy('created_at', $direction);

        $perPage = min((int) $request->input('per_page', 20), 100);

        return ApiResponse::paginated($query->paginate($perPage));
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

        return ApiResponse::success([
            'count' => $count,
            'critical_count' => $criticalCount,
        ]);
    }

    /**
     * 标记已读
     */
    public function markRead(int $id, Request $request): JsonResponse
    {
        $notification = Notification::where('id', $id)
            ->where(function ($q) use ($request) {
                $q->where('user_id', $request->user()->id)
                  ->orWhere('tenant_id', $request->user()->tenant_id);
            })
            ->firstOrFail();

        $notification->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return ApiResponse::success($notification, '已标记为已读');
    }

    /**
     * 标记全部已读
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $count = Notification::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
              ->orWhere(function ($sub) use ($user) {
                  $sub->whereNull('user_id')
                      ->where('tenant_id', $user->tenant_id);
              });
        })->where('is_read', false)->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return ApiResponse::success(['affected' => $count], "已标记 {$count} 条为已读");
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

        return ApiResponse::success(null, '操作成功');
    }

    /**
     * 删除单条
     */
    public function destroy(int $id, Request $request): JsonResponse
    {
        $notification = Notification::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $notification->delete();

        return ApiResponse::success(null, '通知已删除');
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

        return ApiResponse::success($prefs, '通知偏好已更新');
    }
}
