<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * M2-128 收益通知历史查询 API
 *
 * 为代理商/推客提供通知历史查询、标记已读等功能。
 */
class EarningNotificationController extends Controller
{
    /**
     * 获取当前用户的通知列表
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $type = $request->input('type'); // 可选：按通知类型筛选
        $isRead = $request->input('is_read'); // 可选：true/false
        $perPage = min((int) $request->input('per_page', 20), 100);

        $query = Notification::where('user_id', $user->id);

        // 仅返回佣金相关通知
        $earningTypes = [
            'commission_credited',
            'commission_released',
            'payout_status',
            'monthly_report',
            'threshold_reached',
            'negative_balance',
        ];

        if ($type && in_array($type, $earningTypes)) {
            $query->where('type', $type);
        } else {
            $query->whereIn('type', $earningTypes);
        }

        if ($isRead !== null) {
            $query->where('is_read', filter_var($isRead, FILTER_VALIDATE_BOOLEAN));
        }

        $notifications = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $notifications->items(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'unread_count' => Notification::where('user_id', $user->id)
                    ->whereIn('type', $earningTypes)
                    ->where('is_read', false)
                    ->count(),
            ],
        ]);
    }

    /**
     * 标记为已读
     */
    public function markAsRead(Request $request, Notification $notification): JsonResponse
    {
        $user = $request->user();

        if ($notification->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => __('app.common.no_permission')], 403);
        }

        $notification->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => __('app.controller_compat.earning_notification_msg_86'),
            'data' => $notification->fresh(),
        ]);
    }

    /**
     * 批量标记已读
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $type = $request->input('type'); // 可选：只标记某种类型

        $query = Notification::where('user_id', $user->id)
            ->where('is_read', false);

        $earningTypes = [
            'commission_credited',
            'commission_released',
            'payout_status',
            'monthly_report',
            'threshold_reached',
            'negative_balance',
        ];

        if ($type && in_array($type, $earningTypes)) {
            $query->where('type', $type);
        } else {
            $query->whereIn('type', $earningTypes);
        }

        $count = $query->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "已标记 {$count} 条通知为已读",
            'data' => ['marked_count' => $count],
        ]);
    }

    /**
     * 获取通知统计数据
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        $earningTypes = [
            'commission_credited',
            'commission_released',
            'payout_status',
            'monthly_report',
            'threshold_reached',
            'negative_balance',
        ];

        $stats = [];
        foreach ($earningTypes as $type) {
            $stats[$type] = [
                'total' => Notification::where('user_id', $user->id)
                    ->where('type', $type)
                    ->count(),
                'unread' => Notification::where('user_id', $user->id)
                    ->where('type', $type)
                    ->where('is_read', false)
                    ->count(),
            ];
        }

        $stats['all'] = [
            'total' => Notification::where('user_id', $user->id)
                ->whereIn('type', $earningTypes)
                ->count(),
            'unread' => Notification::where('user_id', $user->id)
                ->whereIn('type', $earningTypes)
                ->where('is_read', false)
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * 获取通知偏好设置
     */
    public function preferences(Request $request): JsonResponse
    {
        $user = $request->user();

        $prefs = $user->notificationPreference;

        return response()->json([
            'success' => true,
            'data' => $prefs ?: [
                'user_id' => $user->id,
                'channels' => ['database' => ['database', 'mail']],
                'types' => [
                    'commission_credited' => ['database', 'mail'],
                    'commission_released' => ['database', 'mail'],
                    'payout_status' => ['database', 'mail'],
                    'monthly_report' => ['mail'],
                    'threshold_reached' => ['database', 'mail'],
                    'negative_balance' => ['database', 'mail', 'sms'],
                ],
            ],
        ]);
    }

    /**
     * 更新通知偏好设置
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = validator($request->all(), [
            'types' => 'required|array',
            'types.*' => 'array',
            'types.*.*' => 'string|in:database,mail,sms',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $prefs = $user->notificationPreference()->firstOrNew([
            'user_id' => $user->id,
        ]);

        $prefs->types = $request->input('types');
        $prefs->channels = $request->input('channels', $prefs->channels ?? [
            'database', 'mail',
        ]);
        $prefs->save();

        return response()->json([
            'success' => true,
            'message' => __('app.controller_compat.earning_notification_msg_229'),
            'data' => $prefs->fresh(),
        ]);
    }
}
