<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\CannedReply;
use App\Models\ChangeLog;
use App\Models\CollaborationPreference;
use App\Models\Note;
use App\Models\Watchlist;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * 团队协作服务
 *
 * 提供内部笔记、变更日志、活动流、快速回复、关注等团队协作核心功能。
 */
class TeamCollaborationService
{
    // ═══════════════════════════════════════════════════
    //  内部笔记
    // ═══════════════════════════════════════════════════

    /**
     * 获取实体的笔记列表
     */
    public function getNotes(Model $subject, array $filters = []): Collection
    {
        $query = Note::with('user')
            ->where('notable_type', $subject->getMorphClass())
            ->where('notable_id', $subject->getKey())
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at');

        if (isset($filters['is_internal'])) {
            $query->where('is_internal', $filters['is_internal']);
        }

        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        return $query->get();
    }

    /**
     * 创建笔记
     */
    public function createNote(Model $subject, array $data): Note
    {
        $note = Note::create([
            'notable_type' => $subject->getMorphClass(),
            'notable_id' => $subject->getKey(),
            'user_id' => auth()->id(),
            'content' => $data['content'],
            'mentions' => $data['mentions'] ?? [],
            'attachments' => $data['attachments'] ?? [],
            'is_pinned' => $data['is_pinned'] ?? false,
            'is_internal' => $data['is_internal'] ?? true,
        ]);

        // 记录活动
        Activity::record(
            $subject,
            'note_created',
            auth()->user()?->name . ' 添加了笔记',
            metadata: ['note_id' => $note->id, 'is_internal' => $note->is_internal],
        );

        return $note->load('user');
    }

    /**
     * 更新笔记
     */
    public function updateNote(Note $note, array $data): Note
    {
        $note->update([
            'content' => $data['content'] ?? $note->content,
            'mentions' => $data['mentions'] ?? $note->mentions,
            'attachments' => $data['attachments'] ?? $note->attachments,
            'is_pinned' => $data['is_pinned'] ?? $note->is_pinned,
            'is_internal' => $data['is_internal'] ?? $note->is_internal,
        ]);

        if ($note->notable) {
            Activity::record(
                $note->notable,
                'note_updated',
                auth()->user()?->name . ' 更新了笔记',
                metadata: ['note_id' => $note->id],
            );
        }

        return $note->fresh()->load('user');
    }

    /**
     * 删除笔记
     */
    public function deleteNote(Note $note): bool
    {
        $subject = $note->notable;

        $result = $note->delete();

        if ($result && $subject) {
            Activity::record(
                $subject,
                'note_deleted',
                auth()->user()?->name . ' 删除了笔记',
            );
        }

        return $result;
    }

    /**
     * 切换笔记置顶
     */
    public function togglePin(Note $note): bool
    {
        $note->update(['is_pinned' => !$note->is_pinned]);
        return $note->is_pinned;
    }

    // ═══════════════════════════════════════════════════
    //  变更日志
    // ═══════════════════════════════════════════════════

    /**
     * 获取实体的变更日志
     */
    public function getChangeLogs(Model $subject, int $limit = 50): Collection
    {
        return ChangeLog::with('user')
            ->where('changelogable_type', $subject->getMorphClass())
            ->where('changelogable_id', $subject->getKey())
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * 记录变更
     */
    public function recordChange(
        Model   $subject,
        string  $event,
        ?string $field = null,
        mixed   $oldValue = null,
        mixed   $newValue = null,
        ?string $description = null,
        ?array  $context = [],
    ): ChangeLog {
        $log = ChangeLog::record(
            $subject,
            $event,
            $field,
            $oldValue,
            $newValue,
            $description,
            $context,
        );

        // 同时记录活动流
        $userName = auth()->user()?->name ?? '系统';
        $desc = $description ?? "{$userName} 执行了 {$event}";
        if ($field) {
            $desc .= " ({$field})";
        }

        Activity::record(
            $subject,
            'status_changed',
            $desc,
            metadata: ['event' => $event, 'field' => $field, 'changelog_id' => $log->id],
        );

        return $log;
    }

    // ═══════════════════════════════════════════════════
    //  活动流
    // ═══════════════════════════════════════════════════

    /**
     * 获取全局活动流
     */
    public function getActivityFeed(?int $tenantId = null, array $filters = [], int $perPage = 20): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = Activity::with('user');

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        if (!empty($filters['types'])) {
            $query->whereIn('type', (array) $filters['types']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to'] . ' 23:59:59');
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    /**
     * 获取用户个人活动流
     */
    public function getUserActivityFeed(int $userId, int $limit = 20): Collection
    {
        return Activity::with('user')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    // ═══════════════════════════════════════════════════
    //  快捷回复
    // ═══════════════════════════════════════════════════

    /**
     * 获取用户的快捷回复列表
     */
    public function getCannedReplies(?int $tenantId = null, ?string $category = null): Collection
    {
        $query = CannedReply::with('user');

        // 用户自己的 + 团队共享的
        $query->where(function ($q) {
            $q->where('user_id', auth()->id())
              ->orWhere('is_shared', true);
        });

        if ($tenantId) {
            $query->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)
                  ->orWhereNull('tenant_id');
            });
        }

        if ($category) {
            $query->where('category', $category);
        }

        return $query->orderBy('category')->orderBy('title')->get();
    }

    /**
     * 创建快捷回复
     */
    public function createCannedReply(array $data): CannedReply
    {
        return CannedReply::create([
            'user_id' => auth()->id(),
            'tenant_id' => auth()->user()?->tenant_id,
            'title' => $data['title'],
            'content' => $data['content'],
            'category' => $data['category'] ?? 'general',
            'is_shared' => $data['is_shared'] ?? false,
        ]);
    }

    /**
     * 更新快捷回复
     */
    public function updateCannedReply(CannedReply $reply, array $data): CannedReply
    {
        $reply->update($data);
        return $reply->fresh();
    }

    /**
     * 删除快捷回复
     */
    public function deleteCannedReply(CannedReply $reply): bool
    {
        return $reply->delete();
    }

    // ═══════════════════════════════════════════════════
    //  关注/订阅
    // ═══════════════════════════════════════════════════

    /**
     * 获取用户关注的实体列表
     */
    public function getWatchlist(int $userId, ?string $type = null): Collection
    {
        $query = Watchlist::with('watchable')
            ->where('user_id', $userId);

        if ($type) {
            $query->where('watchable_type', $type);
        }

        return $query->orderByDesc('created_at')->get();
    }

    /**
     * 切换关注状态
     */
    public function toggleWatch(Model $subject, ?string $reason = 'manual'): array
    {
        $isWatching = Watchlist::toggle(auth()->id(), $subject, $reason);

        return [
            'is_watching' => $isWatching,
            'message' => $isWatching ? '已关注' : '已取消关注',
        ];
    }

    /**
     * 检查是否关注
     */
    public function isWatching(Model $subject): bool
    {
        return Watchlist::isWatching(auth()->id(), $subject);
    }

    // ═══════════════════════════════════════════════════
    //  协作偏好
    // ═══════════════════════════════════════════════════

    /**
     * 获取用户协作偏好
     */
    public function getPreferences(int $userId): CollaborationPreference
    {
        return CollaborationPreference::forUser($userId);
    }

    /**
     * 更新协作偏好
     */
    public function updatePreferences(int $userId, array $data): CollaborationPreference
    {
        $prefs = CollaborationPreference::forUser($userId);
        $prefs->update($data);
        return $prefs->fresh();
    }

    // ═══════════════════════════════════════════════════
    //  多实体笔记/活动批量查询
    // ═══════════════════════════════════════════════════

    /**
     * 批量获取多个实体的笔记数量
     */
    public function getNoteCounts(string $type, array $ids): array
    {
        return Note::where('notable_type', $type)
            ->whereIn('notable_id', $ids)
            ->selectRaw('notable_id, count(*) as count')
            ->groupBy('notable_id')
            ->pluck('count', 'notable_id')
            ->toArray();
    }

    /**
     * 批量获取多个实体的最后活动时间
     */
    public function getLastActivityTimestamps(string $type, array $ids): array
    {
        return Activity::where('subject_type', $type)
            ->whereIn('subject_id', $ids)
            ->selectRaw('subject_id, max(created_at) as last_activity')
            ->groupBy('subject_id')
            ->pluck('last_activity', 'subject_id')
            ->map(fn($v) => (string) $v)
            ->toArray();
    }
}
