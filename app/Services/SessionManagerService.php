<?php

namespace App\Services;

use App\Models\UserSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Session 管理服务 (M1.4-30)
 *
 * 管理用户活跃会话：查看列表、详情、远程踢出。
 */
class SessionManagerService
{
    /**
     * 分页查询活跃会话列表
     */
    public function getSessions(array $params = []): array
    {
        $query = UserSession::with('user')
            ->orderByDesc('last_activity_at');

        if (!empty($params['user_id'])) {
            $query->where('user_id', $params['user_id']);
        }
        if (!empty($params['search'])) {
            $s = $params['search'];
            $query->where(function ($q) use ($s) {
                $q->where('ip_address', 'like', "%{$s}%")
                  ->orWhere('browser', 'like', "%{$s}%")
                  ->orWhere('os', 'like', "%{$s}%")
                  ->orWhere('device_type', 'like', "%{$s}%")
                  ->orWhere('location', 'like', "%{$s}%");
            });
        }
        if (isset($params['is_current'])) {
            $query->where('is_current', filter_var($params['is_current'], FILTER_VALIDATE_BOOLEAN));
        }
        if (!empty($params['device_type'])) {
            $query->where('device_type', $params['device_type']);
        }
        if (!empty($params['date_from'])) {
            $query->where('last_activity_at', '>=', $params['date_from']);
        }
        if (!empty($params['date_to'])) {
            $query->where('last_activity_at', '<=', $params['date_to'] . ' 23:59:59');
        }

        $perPage = min((int) ($params['per_page'] ?? 15), 100);
        $page = (int) ($params['page'] ?? 1);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'items' => $paginator->items(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    /**
     * 获取仪表盘数据
     */
    public function getDashboard(): array
    {
        $totalSessions = UserSession::count();
        $activeSessions = UserSession::where('is_current', true)->count();
        $uniqueUsers = UserSession::distinct('user_id')->count('user_id');
        $todaySessions = UserSession::where('last_activity_at', '>=', now()->startOfDay())->count();

        // 设备类型分布
        $deviceStats = UserSession::select('device_type', DB::raw('count(*) as total'))
            ->groupBy('device_type')
            ->get()
            ->pluck('total', 'device_type')
            ->toArray();

        return [
            'total_sessions' => $totalSessions,
            'active_sessions' => $activeSessions,
            'unique_users' => $uniqueUsers,
            'today_sessions' => $todaySessions,
            'device_stats' => $deviceStats,
        ];
    }

    /**
     * 获取单条会话详情
     */
    public function getSessionDetail(int $id): ?UserSession
    {
        return UserSession::with('user')->find($id);
    }

    /**
     * 远程踢出会话
     */
    public function terminateSession(int $id, int $requestUserId): array
    {
        $session = UserSession::findOrFail($id);

        if ($session->is_current && $session->user_id === $requestUserId) {
            return ['success' => false, 'message' => __('app.common.cannot_kick_own_session')];
        }

        $session->delete();

        Log::info('会话已被管理员踢出', [
            'session_id' => $session->session_id,
            'user_id' => $session->user_id,
            'terminated_by' => $requestUserId,
        ]);

        // 使 Laravel session 失效
        DB::table('sessions')->where('id', $session->session_id)->delete();

        return ['success' => true, 'message' => __('app.common.session_kicked')];
    }

    /**
     * 批量踢出
     */
    public function batchTerminate(array $ids, int $requestUserId): array
    {
        $results = ['success' => 0, 'failed' => 0, 'errors' => []];

        foreach ($ids as $id) {
            try {
                $result = $this->terminateSession((int) $id, $requestUserId);
                if ($result['success']) {
                    $results['success']++;
                } else {
                    $results['failed']++;
                    $results['errors'][] = ['id' => $id, 'message' => $result['message']];
                }
            } catch (\Throwable $e) {
                $results['failed']++;
                $results['errors'][] = ['id' => $id, 'message' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * 踢出指定用户的所有会话
     */
    public function terminateUserSessions(int $userId, int $requestUserId): array
    {
        $sessions = UserSession::where('user_id', $userId)
            ->where(function ($q) use ($requestUserId) {
                $q->where('user_id', '!=', $requestUserId)
                  ->orWhere('is_current', false);
            })
            ->get();

        $count = 0;
        foreach ($sessions as $session) {
            $session->delete();
            DB::table('sessions')->where('id', $session->session_id)->delete();
            $count++;
        }

        Log::info('用户所有会话已被管理员踢出', [
            'target_user_id' => $userId,
            'terminated_by' => $requestUserId,
            'count' => $count,
        ]);

        return ['success' => true, 'message' => __('app.common.sessions_kicked', ['count' => $count])];
    }
}
