<?php

namespace App\Services;

use App\Models\CustomerFeedback;
use App\Models\FeatureVote;
use App\Models\FeedbackTag;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FeedbackService
{
    public function list(array $filters = [], int $perPage = 20)
    {
        $query = CustomerFeedback::with(['customer:id,name', 'user:id,name', 'assignee:id,name', 'tags'])
            ->orderBy('created_at', 'desc');

        if (!empty($filters['status'])) $query->where('status', $filters['status']);
        if (!empty($filters['type'])) $query->where('type', $filters['type']);
        if (!empty($filters['priority'])) $query->where('priority', $filters['priority']);
        if (!empty($filters['assigned_to'])) $query->where('assigned_to', $filters['assigned_to']);
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('message', 'like', "%{$filters['search']}%")
                  ->orWhere('subject', 'like', "%{$filters['search']}%");
            });
        }
        if (!empty($filters['rating_min'])) $query->where('rating', '>=', $filters['rating_min']);
        if (!empty($filters['rating_max'])) $query->where('rating', '<=', $filters['rating_max']);
        if (!empty($filters['date_from'])) $query->whereDate('created_at', '>=', $filters['date_from']);
        if (!empty($filters['date_to'])) $query->whereDate('created_at', '<=', $filters['date_to']);
        if (!empty($filters['tag_id'])) {
            $query->whereHas('tags', fn($q) => $q->where('feedback_tags.id', $filters['tag_id']));
        }

        return $query->paginate($perPage);
    }

    public function create(array $data, User $user): CustomerFeedback
    {
        $data['user_id'] = $user->id;
        $data['customer_id'] = $user->customer?->id;
        $data['tenant_id'] = $user->tenant_id ?? null;

        // 自动收集浏览器/系统上下文
        $request = request();
        $data['user_agent'] = $request->userAgent();
        $data['ip_address'] = $request->ip();
        $data['language'] = $request->getPreferredLanguage();
        $data['screen_resolution'] = $data['screen_resolution'] ?? null;

        return DB::transaction(function () use ($data) {
            $tags = $data['tags'] ?? [];
            unset($data['tags']);

            $feedback = CustomerFeedback::create($data);

            if (!empty($tags)) {
                $feedback->tags()->attach($tags);
            }

            return $feedback->fresh()->load(['tags']);
        });
    }

    public function update(int $id, array $data): CustomerFeedback
    {
        $feedback = CustomerFeedback::findOrFail($id);

        return DB::transaction(function () use ($feedback, $data) {
            $tags = $data['tags'] ?? null;
            unset($data['tags']);

            $feedback->update($data);

            if ($tags !== null) {
                $feedback->tags()->sync($tags);
            }

            return $feedback->fresh()->load(['customer:id,name', 'user:id,name', 'assignee:id,name', 'tags']);
        });
    }

    public function assign(int $id, int $userId): CustomerFeedback
    {
        $feedback = CustomerFeedback::findOrFail($id);
        $feedback->update([
            'assigned_to' => $userId,
            'assigned_at' => now(),
            'status' => $feedback->status === 'new' ? 'under_review' : $feedback->status,
        ]);
        return $feedback->fresh()->load(['customer:id,name', 'user:id,name', 'assignee:id,name', 'tags']);
    }

    public function reply(int $id, string $message): CustomerFeedback
    {
        $feedback = CustomerFeedback::findOrFail($id);
        $feedback->update([
            'admin_reply' => $message,
            'replied_at' => now(),
            'replied_by' => auth()->id(),
            'status' => 'acknowledged',
        ]);
        return $feedback->fresh()->load(['customer:id,name', 'user:id,name', 'assignee:id,name', 'tags']);
    }

    public function resolve(int $id, string $resolution = 'resolved'): CustomerFeedback
    {
        $feedback = CustomerFeedback::findOrFail($id);
        $feedback->update([
            'status' => in_array($resolution, CustomerFeedback::STATUSES) ? $resolution : 'resolved',
            'resolved_at' => now(),
        ]);
        return $feedback->fresh();
    }

    public function getStats(): array
    {
        return [
            'total' => CustomerFeedback::count(),
            'by_status' => CustomerFeedback::selectRaw('status, count(*) as count')
                ->groupBy('status')->pluck('count', 'status'),
            'by_type' => CustomerFeedback::selectRaw('type, count(*) as count')
                ->groupBy('type')->pluck('count', 'type'),
            'by_priority' => CustomerFeedback::selectRaw('priority, count(*) as count')
                ->groupBy('priority')->pluck('count', 'priority'),
            'avg_rating' => CustomerFeedback::whereNotNull('rating')->avg('rating'),
            'new_today' => CustomerFeedback::whereDate('created_at', today())->count(),
            'unresolved' => CustomerFeedback::whereNotIn('status', ['resolved', 'closed', 'wont_fix'])->count(),
        ];
    }

    // ═════════ 标签管理 ═════════

    public function listTags()
    {
        return FeedbackTag::orderBy('name')->get();
    }

    public function createTag(array $data): FeedbackTag
    {
        return FeedbackTag::create($data);
    }

    // ═════════ 门户专用 ═════════

    public function myFeedback(User $user, array $filters = [], int $perPage = 20)
    {
        $query = CustomerFeedback::where('user_id', $user->id)
            ->orderBy('created_at', 'desc');

        if (!empty($filters['status'])) $query->where('status', $filters['status']);

        return $query->paginate($perPage);
    }

    // ═════════ 投票系统 ═════════

    /**
     * 对反馈进行投票（点赞/点踩）
     */
    public function vote(int $feedbackId, User $user, int $vote): array
    {
        if (!in_array($vote, [1, -1])) {
            $vote = 1;
        }

        $feedback = CustomerFeedback::findOrFail($feedbackId);
        $tenantId = $feedback->tenant_id ?? $user->tenant_id;

        $existing = FeatureVote::where('feedback_id', $feedbackId)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            if ($existing->vote === $vote) {
                // 相同投票 -> 取消投票
                $existing->delete();
                $action = 'removed';
            } else {
                // 改变投票方向
                $existing->update(['vote' => $vote]);
                $action = 'changed';
            }
        } else {
            FeatureVote::create([
                'feedback_id' => $feedbackId,
                'user_id' => $user->id,
                'tenant_id' => $tenantId,
                'vote' => $vote,
            ]);
            $action = 'added';
        }

        $voteCount = FeatureVote::where('feedback_id', $feedbackId)->sum('vote');

        return [
            'action' => $action,
            'vote' => $vote,
            'vote_count' => (int)$voteCount,
            'user_vote' => $this->getUserVote($feedbackId, $user->id),
        ];
    }

    /**
     * 获取用户的投票状态
     */
    public function getUserVote(int $feedbackId, int $userId): ?int
    {
        $vote = FeatureVote::where('feedback_id', $feedbackId)
            ->where('user_id', $userId)
            ->first();

        return $vote?->vote;
    }

    /**
     * 获取反馈列表（含投票统计）
     */
    public function listWithVotes(array $filters = [], int $perPage = 20, ?int $currentUserId = null)
    {
        $query = CustomerFeedback::with([
            'customer:id,name',
            'user:id,name',
            'assignee:id,name',
            'tags',
        ])->withCount([
            'votes as upvotes_count' => fn($q) => $q->where('vote', 1),
            'votes as downvotes_count' => fn($q) => $q->where('vote', -1),
        ])->orderBy('created_at', 'desc');

        if (!empty($filters['status'])) $query->where('status', $filters['status']);
        if (!empty($filters['type'])) $query->where('type', $filters['type']);
        if (!empty($filters['priority'])) $query->where('priority', $filters['priority']);
        if (!empty($filters['assigned_to'])) $query->where('assigned_to', $filters['assigned_to']);
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('message', 'like', "%{$filters['search']}%")
                  ->orWhere('subject', 'like', "%{$filters['search']}%");
            });
        }
        if (!empty($filters['rating_min'])) $query->where('rating', '>=', $filters['rating_min']);
        if (!empty($filters['rating_max'])) $query->where('rating', '<=', $filters['rating_max']);
        if (!empty($filters['date_from'])) $query->whereDate('created_at', '>=', $filters['date_from']);
        if (!empty($filters['date_to'])) $query->whereDate('created_at', '<=', $filters['date_to']);
        if (!empty($filters['tag_id'])) {
            $query->whereHas('tags', fn($q) => $q->where('feedback_tags.id', $filters['tag_id']));
        }

        // 投票排序
        if (!empty($filters['sort'])) {
            if ($filters['sort'] === 'votes') {
                $query->orderByDesc('upvotes_count');
            } elseif ($filters['sort'] === 'recent') {
                $query->orderByDesc('created_at');
            }
        }

        $result = $query->paginate($perPage)->toArray();

        // 如果当前用户已登录，注入用户的投票状态
        if ($currentUserId) {
            $result['data'] = array_map(function ($item) use ($currentUserId) {
                if (is_array($item)) {
                    $item['vote_count'] = ($item['upvotes_count'] ?? 0) - ($item['downvotes_count'] ?? 0);
                    $item['user_vote'] = $this->getUserVote($item['id'], $currentUserId);
                }
                return $item;
            }, $result['data']);
        }

        return $result;
    }

    /**
     * 获取投票统计
     */
    public function getVoteStats(): array
    {
        return [
            'total_votes' => FeatureVote::count(),
            'total_upvotes' => FeatureVote::where('vote', 1)->count(),
            'total_downvotes' => FeatureVote::where('vote', -1)->count(),
            'most_voted' => CustomerFeedback::withCount('votes')
                ->orderByDesc('votes_count')
                ->limit(5)
                ->get(['id', 'subject', 'type'])->toArray(),
        ];
    }
}
