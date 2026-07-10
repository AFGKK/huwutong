<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ForumCategory;
use App\Models\ForumFollow;
use App\Models\ForumLike;
use App\Models\ForumPoll;
use App\Models\ForumPollOption;
use App\Models\ForumPollVote;
use App\Models\ForumPost;
use App\Models\ForumReply;
use App\Models\ForumTag;
use App\Models\ForumFavorite;
use App\Models\ForumFavoriteCollection;
use App\Models\ConversationMessage;
use App\Services\PostModerationService;
use App\Models\ConversationParticipant;
use App\Models\EarningsAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MomentController extends Controller
{
    // ── 广场列表（全系统公开·小红书信息流） ──
    public function index(Request $request): JsonResponse
    {
        $tab = $request->input('tab', $request->input('sort', 'all'));
        $q = $request->input('q', '');
        $categoryId = $request->input('category_id');
        $tag = $request->input('tag');
        $myId = auth()->id();

        $query = ForumPost::where(function($q) {
                $q->where('status', 'published')->orWhereNull('status');
            })
            ->with('user:id,name,avatar')
            ->with('tags')
            ->with('reactions')
            ->with([
                'likes' => fn($q) => $q->where('user_id', $myId),
                'favorites' => fn($q) => $q->where('user_id', $myId),
            ]);

        if ($tag) {
            $query->whereHas('tags', fn($t) => $t->where('slug', $tag));
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        if ($q) {
            $query->where(function($q2) use ($q) {
                $q2->where('content', 'like', "%{$q}%")
                    ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%{$q}%"))
                    ->orWhereHas('replies', fn($r) => $r->where('content', 'like', "%{$q}%"));
            })->orderByRaw('CASE WHEN is_pinned THEN 1 ELSE 0 END DESC')->orderBy('created_at', 'desc');
        }

        if ($tab === 'pinned') {
            $query->where('is_pinned', true);
        } elseif ($tab === 'hot') {
            $query->orderBy('is_pinned', 'desc')->orderBy('likes_count', 'desc')->orderBy('created_at', 'desc');
        } elseif ($tab === 'weekly_hot') {
            $query->where('created_at', '>=', now()->subDays(7))
                ->orderBy('is_pinned', 'desc')
                ->orderBy('likes_count', 'desc')
                ->orderBy('replies_count', 'desc');
        } elseif ($tab === 'smart' && $myId) {
            // ── 智能推荐：标签匹配 + 热度（合并 AI + 序列预测） ──
            $interactedIds = ForumLike::where('user_id', $myId)->pluck('post_id')
                ->merge(ForumReply::where('user_id', $myId)->pluck('post_id'))
                ->unique()->values()->toArray();

            $interestTags = [];
            if (!empty($interactedIds)) {
                $taggedPosts = ForumPost::whereIn('id', $interactedIds)
                    ->with('tags')
                    ->get();
                foreach ($taggedPosts as $post) {
                    foreach ($post->tags as $tag) {
                        $interestTags[$tag->name] = ($interestTags[$tag->name] ?? 0) + 1;
                    }
                }
                arsort($interestTags);
                $interestTags = array_keys(array_slice($interestTags, 0, 10));
            }

            if (!empty($interestTags)) {
                $query->where(function($q) use ($interestTags) {
                    foreach ($interestTags as $tag) {
                        $q->orWhereHas('tags', fn($t) => $t->where('name', $tag));
                    }
                });
                $tagCases = [];
                foreach ($interestTags as $tag) {
                    $escaped = addslashes($tag);
                    $tagCases[] = "EXISTS (SELECT 1 FROM forum_post_tag fpt JOIN forum_tags ft ON ft.id = fpt.tag_id WHERE fpt.post_id = forum_posts.id AND ft.name = '{$escaped}')";
                }
                if (!empty($tagCases)) {
                    $query->orderByRaw('(' . implode(' + ', $tagCases) . ') DESC');
                }
            }
            $query->orderBy('is_pinned', 'desc')
                  ->orderByRaw('(likes_count * 0.5 + replies_count * 0.3 + views_count * 0.1) DESC')
                  ->orderBy('created_at', 'desc');

        } elseif ($tab === 'recommended' && $myId) {
            // ── 协同推荐：兴趣相似用户的热门帖子 ──
            $likedIds = ForumLike::where('user_id', $myId)->pluck('post_id');
            $repliedIds = ForumReply::where('user_id', $myId)->pluck('post_id');
            $interactedIds = $likedIds->merge($repliedIds)->unique()->values()->toArray();

            if (!empty($interactedIds)) {
                $neighborIds = ForumLike::whereIn('post_id', $interactedIds)
                    ->where('user_id', '!=', $myId)
                    ->distinct('user_id')
                    ->pluck('user_id')
                    ->merge(
                        ForumReply::whereIn('post_id', $interactedIds)
                            ->where('user_id', '!=', $myId)
                            ->distinct('user_id')
                            ->pluck('user_id')
                    )->unique()->take(100)->values()->toArray();

                if (!empty($neighborIds)) {
                    $neighborStr = implode(',', $neighborIds);
                    $query->whereIn('user_id', $neighborIds)
                          ->orWhere('likes_count', '>', 0);
                    $query->orderByRaw("CASE WHEN user_id IN ({$neighborStr}) THEN 0 ELSE 1 END");
                }
            }
            $query->orderBy('is_pinned', 'desc')->orderBy('likes_count', 'desc')->orderBy('created_at', 'desc');

        } else {
            $query->orderBy('is_pinned', 'desc')->orderBy('created_at', 'desc');
        }

        $posts = $query->paginate(20);
        $posts->getCollection()->transform(fn($p) => $this->transformPost($p, $myId));

        return ApiResponse::paginated($posts);
    }

    /**
     * 统一的帖子数据转换
     */
    protected function transformPost($p, $myId): array
    {
        $pollData = null;
        if ($p->relationLoaded('poll')) {
            $poll = $p->poll;
            if ($poll) {
                $pollData = [
                    'id' => $poll->id,
                    'question' => $poll->question,
                    'is_multiple' => $poll->is_multiple,
                    'total_votes' => $poll->votes()->count(),
                    'options' => $poll->options->map(function ($opt) use ($myId, $poll) {
                        $voteCount = $opt->votes()->count();
                        return [
                            'id' => $opt->id,
                            'label' => $opt->label,
                            'votes' => $voteCount,
                            'percent' => 0,
                            'voted' => ForumPollVote::where('option_id', $opt->id)->where('user_id', $myId)->exists(),
                        ];
                    })->values(),
                    'voted' => ForumPollVote::where('poll_id', $poll->id)->where('user_id', $myId)->exists(),
                    'expires_at' => $poll->expires_at?->toDateTimeString(),
                ];
                $total = $pollData['total_votes'] ?: 1;
                foreach ($pollData['options'] as &$opt) {
                    $opt['percent'] = round(($opt['votes'] / $total) * 100);
                }
            }
        } else {
            // 手动加载 poll
            $poll = ForumPoll::with('options')->where('post_id', $p->id)->first();
            if ($poll) {
                $pollData = [
                    'id' => $poll->id,
                    'question' => $poll->question,
                    'is_multiple' => $poll->is_multiple,
                    'total_votes' => ForumPollVote::where('poll_id', $poll->id)->count(),
                    'options' => $poll->options->map(function ($opt) use ($myId, $poll) {
                        $voteCount = ForumPollVote::where('option_id', $opt->id)->count();
                        return [
                            'id' => $opt->id,
                            'label' => $opt->label,
                            'votes' => $voteCount,
                            'percent' => 0,
                            'voted' => ForumPollVote::where('option_id', $opt->id)->where('user_id', $myId)->exists(),
                        ];
                    })->values(),
                    'voted' => ForumPollVote::where('poll_id', $poll->id)->where('user_id', $myId)->exists(),
                    'expires_at' => $poll->expires_at?->toDateTimeString(),
                ];
                $total = $pollData['total_votes'] ?: 1;
                foreach ($pollData['options'] as &$opt) {
                    $opt['percent'] = round(($opt['votes'] / $total) * 100);
                }
            }
        }

        return [
            'id' => $p->id,
            'content' => $p->content,
            'images' => $this->normalizeImages($p->images),
            'video' => $p->video,
            'category_id' => $p->category_id,
            'likes_count' => $p->likes_count,
            'favorites_count' => $p->favorites_count,
            'replies_count' => $p->replies_count,
            'views_count' => $p->views_count,
            'created_at' => $p->created_at,
            'poll' => $pollData,
            'tags' => $p->relationLoaded('tags') ? $p->tags->map(fn($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
            ])->values() : [],
            'status' => $p->status,
            'scheduled_at' => $p->scheduled_at?->toDateTimeString(),
            'template' => $p->template ?? 'discuss',
            'is_pinned' => $p->is_pinned ?? false,
            'is_paid' => $p->is_paid ?? false,
            'price' => $p->price,
            'price_type' => $p->price_type ?? 'points',
            'content_preview' => $p->content_preview,
            'has_purchased' => $myId ? $p->isPurchasedBy($myId) : false,
            'user' => $p->user ? [
                'id' => $p->user->id,
                'name' => $p->user->name,
                'avatar_url' => $p->user->avatar
                    ? (str_starts_with($p->user->avatar, 'http') ? $p->user->avatar : url('storage/' . $p->user->avatar))
                    : null,
                'level' => \App\Models\UserForumLevel::where('user_id', $p->user->id)->value('level') ?? 1,
            ] : null,
            'user_id' => $p->user_id,
            'is_liked' => $p->likes->isNotEmpty(),
            'is_favorited' => $p->favorites->isNotEmpty(),
            'reactions' => $p->relationLoaded('reactions') ? $this->buildReactionData($p->reactions) : null,
        ];
    }

    /**
     * 构建反应数据
     */
    protected function buildReactionData($reactions): array
    {
        $summary = [];
        $myId = auth()->id();
        $myReaction = null;

        foreach ($reactions as $r) {
            $summary[$r->reaction] = ($summary[$r->reaction] ?? 0) + 1;
            if ($myId && $r->user_id === $myId) {
                $myReaction = $r->reaction;
            }
        }

        return [
            'summary' => $summary,
            'total' => array_sum($summary),
            'my_reaction' => $myReaction,
        ];
    }

    /**
     * 规范化图片 URL：将生产域名替换为当前环境域名，过滤无效路径
     */
    protected function normalizeImages($images): array
    {
        if (!$images) return [];
        
        $urls = is_string($images) ? json_decode($images, true) ?? [$images] : $images;
        $productionDomain = 'http://88.huwutong.com';
        $currentOrigin = url('/');
        $valid = [];

        foreach ($urls as $url) {
            if (!is_string($url) || empty($url)) continue;
            // 替换生产域名为本地域名
            $normalized = str_replace($productionDomain, $currentOrigin, $url);
            // 过滤头像路径被误存为帖子图片
            if (str_contains($normalized, '/oa-avatars/') || str_contains($normalized, '/avatars/')) continue;
            $valid[] = $normalized;
        }

        return $valid;
    }

    // ── 我的帖子（含草稿和定时） ──
    public function myPosts(Request $request): JsonResponse
    {
        $myId = auth()->id();
        $status = $request->input('status'); // 可选筛选 draft/published/scheduled
        $query = ForumPost::where('user_id', $myId);
        if ($status) {
            $query->where('status', $status);
        }
        $posts = $query->with('user:id,name,avatar')
            ->with('tags')
            ->withCount('replies')
            ->withCount('favorites')
            ->with([
                'likes' => fn($q) => $q->where('user_id', $myId),
                'favorites' => fn($q) => $q->where('user_id', $myId),
            ])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $posts->getCollection()->transform(fn($p) => $this->transformPost($p, $myId));

        return ApiResponse::paginated($posts);
    }

    // ── 帖子详情 ──
    public function show(int $id): JsonResponse
    {
        $myId = auth()->id();
        $post = ForumPost::with('user:id,name,avatar')
            ->with('poll.options')
            ->with('tags')
            ->withCount('replies')
            ->withCount('favorites')
            ->with([
                'likes' => fn($q) => $q->where('user_id', $myId),
                'favorites' => fn($q) => $q->where('user_id', $myId),
            ])
            ->findOrFail($id);

        $post->increment('views_count');

        return ApiResponse::success($this->transformPost($post, $myId));
    }

    // ── 公开帖子详情（无需登录） ──
    public function showPublic(int $id): JsonResponse
    {
        $post = ForumPost::where(function($q) {
                $q->where('status', 'published')->orWhereNull('status');
            })
            ->with('user:id,name,avatar')
            ->with('poll.options')
            ->with('tags')
            ->withCount('replies')
            ->withCount('favorites')
            ->findOrFail($id);

        $post->increment('views_count');

        $pollData = null;
        $poll = $post->poll;
        if ($poll) {
            $pollData = [
                'id' => $poll->id,
                'question' => $poll->question,
                'is_multiple' => $poll->is_multiple,
                'total_votes' => ForumPollVote::where('poll_id', $poll->id)->count(),
                'options' => $poll->options->map(function ($opt) {
                    $voteCount = ForumPollVote::where('option_id', $opt->id)->count();
                    return [
                        'id' => $opt->id,
                        'label' => $opt->label,
                        'votes' => $voteCount,
                        'percent' => 0,
                    ];
                })->values(),
                'voted' => false,
                'expires_at' => $poll->expires_at?->toDateTimeString(),
            ];
            $total = $pollData['total_votes'] ?: 1;
            foreach ($pollData['options'] as &$opt) {
                $opt['percent'] = round(($opt['votes'] / $total) * 100);
            }
        }

        return ApiResponse::success([
            'id' => $post->id,
            'content' => $post->content,
            'images' => $post->images,
            'video' => $post->video,
            'category_id' => $post->category_id,
            'likes_count' => $post->likes_count,
            'favorites_count' => $post->favorites_count,
            'replies_count' => $post->replies_count,
            'views_count' => $post->views_count,
            'created_at' => $post->created_at,
            'poll' => $pollData,
            'tags' => $post->tags->map(fn($t) => ['id' => $t->id, 'name' => $t->name, 'slug' => $t->slug]),
            'user' => $post->user ? [
                'id' => $post->user->id,
                'name' => $post->user->name,
                'avatar_url' => $post->user->avatar
                    ? (str_starts_with($post->user->avatar, 'http') ? $post->user->avatar : asset('storage/' . $post->user->avatar))
                    : null,
            ] : null,
        ]);
    }

    // ── 公开评论列表（无需登录） ──
    public function commentsPublic(int $id): JsonResponse
    {
        $post = ForumPost::findOrFail($id);
        $comments = ForumReply::where('post_id', $id)
            ->whereNull('parent_id')
            ->with('user:id,name,avatar')
            ->with(['replies' => fn($q) => $q->with('user:id,name,avatar')->orderBy('created_at')])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return ApiResponse::paginated($comments);
    }

    // ── 发布帖子 ──
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string|max:5000',
            'images' => 'nullable|array',
            'images.*' => 'string|max:500',
            'category_id' => 'nullable|integer|exists:forum_categories,id',
            'poll' => 'nullable|array',
            'poll.question' => 'required_with:poll|string|max:500',
            'poll.options' => 'required_with:poll|array|min:2|max:20',
            'poll.options.*' => 'required|string|max:200',
            'poll.is_multiple' => 'nullable|boolean',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:30',
            'status' => 'nullable|in:draft,published,scheduled',
            'scheduled_at' => 'nullable|date|after:now',
            'template' => 'nullable|in:discuss,poll,qa,checkin,announce',
            'is_paid' => 'nullable|boolean',
            'price' => 'required_if:is_paid,true|nullable|numeric|min:1|max:99999',
            'price_type' => 'nullable|in:points,money',
            'content_preview' => 'nullable|string|max:500',
        ]);

        $status = $validated['status'] ?? 'published';

        $content = $validated['content'];
        $post = ForumPost::create([
            'user_id' => auth()->id(),
            'content' => $content,
            'images' => $this->handleImageUploads($request),
            'category_id' => $validated['category_id'] ?? null,
            'title' => mb_substr(strip_tags($content), 0, 200),
            'status' => $status,
            'scheduled_at' => $validated['scheduled_at'] ?? null,
            'template' => $validated['template'] ?? 'discuss',
            'is_paid' => $validated['is_paid'] ?? false,
            'price' => ($validated['is_paid'] ?? false) ? ($validated['price'] ?? 0) : null,
            'price_type' => $validated['price_type'] ?? 'points',
            'content_preview' => $validated['content_preview'] ?? null,
        ]);

        // 处理标签
        if ($request->has('tags') && is_array($validated['tags'])) {
            $tagIds = [];
            foreach ($validated['tags'] as $tagName) {
                $tagName = trim($tagName);
                if (empty($tagName)) continue;
                $slug = \Illuminate\Support\Str::slug($tagName);
                $tag = ForumTag::firstOrCreate(
                    ['slug' => $slug],
                    ['name' => $tagName]
                );
                $tagIds[] = $tag->id;
            }
            if (!empty($tagIds)) {
                $post->tags()->sync($tagIds);
            }
        }

        // 创建投票
        if ($request->has('poll') && !empty($validated['poll']['question']) && !empty($validated['poll']['options'])) {
            $poll = ForumPoll::create([
                'post_id' => $post->id,
                'question' => $validated['poll']['question'],
                'is_multiple' => $validated['poll']['is_multiple'] ?? false,
            ]);
            foreach ($validated['poll']['options'] as $i => $label) {
                ForumPollOption::create([
                    'poll_id' => $poll->id,
                    'label' => $label,
                    'sort_order' => $i,
                ]);
            }
        }

        // AI 自动审核（仅对公开发布的帖子）
        if ($status === 'published') {
            try {
                $moderation = app(PostModerationService::class);
                $result = $moderation->inspectPost($post);
                if (!$result['passed']) {
                    \Illuminate\Support\Facades\Log::info('[Moment] AI审核拦截', [
                        'post_id' => $post->id,
                        'reason' => $result['reason'],
                    ]);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('[Moment] AI审核异常: ' . $e->getMessage());
            }
        }

        if ($status === 'published') {
            \App\Models\UserForumLevel::earn(auth()->id(), \App\Models\UserForumLevel::EXP_POST, '发布帖子');
        }

        return ApiResponse::success($post->load('user:id,name,avatar', 'tags'), $status === 'draft' ? '已保存到草稿箱' : ($status === 'scheduled' ? '已定时' : '已发布'), 201);
    }
    // ── 分类列表 ──
    public function categories(): JsonResponse
    {
        $cats = ForumCategory::orderBy('sort_order')->get(['id', 'name', 'icon']);
        return ApiResponse::success($cats);
    }
    // ── 编辑帖子 ──
    public function update(int $id, Request $request): JsonResponse
    {
        $post = ForumPost::findOrFail($id);
        if ($post->user_id !== auth()->id()) {
            return ApiResponse::error('FORBIDDEN', '只能编辑自己的帖子', 403);
        }

        $validated = $request->validate([
            'content' => 'nullable|string|max:5000',
            'images' => 'nullable|array',
            'images.*' => 'string|max:500',
            'video' => 'nullable|string|max:500',
            'category_id' => 'nullable|integer|exists:forum_categories,id',
            'status' => 'nullable|in:draft,published,scheduled',
            'scheduled_at' => 'nullable|date|after:now',
        ]);

        if (isset($validated['content'])) $post->content = $validated['content'];
        if ($request->hasFile('images')) {
            $post->images = $this->handleImageUploads($request);
        } elseif (array_key_exists('images', $validated)) {
            $post->images = $validated['images'];
        }
        if (array_key_exists('video', $validated)) $post->video = $validated['video'];
        if (array_key_exists('category_id', $validated)) $post->category_id = $validated['category_id'];
        if (isset($validated['status'])) $post->status = $validated['status'];
        if (array_key_exists('scheduled_at', $validated)) $post->scheduled_at = $validated['scheduled_at'];
        $post->save();

        return ApiResponse::success($post->load('user:id,name,avatar', 'tags'), '已更新');
    }

    // ── 点赞/取消点赞 ──
    public function toggleLike(int $id): JsonResponse
    {
        $post = ForumPost::findOrFail($id);
        $myId = auth()->id();

        $existing = ForumLike::where('user_id', $myId)
            ->where('likeable_type', ForumPost::class)
            ->where('likeable_id', $id)->first();

        if ($existing) {
            $existing->delete();
            $post->decrement('likes_count');
            return ApiResponse::success(['liked' => false, 'likes_count' => $post->fresh()->likes_count], '已取消点赞');
        }

        ForumLike::create([
            'user_id' => $myId,
            'likeable_type' => ForumPost::class,
            'likeable_id' => $id,
        ]);
        $post->increment('likes_count');
        if ($post->user_id !== auth()->id()) {
            \App\Models\UserForumLevel::earn($post->user_id, \App\Models\UserForumLevel::EXP_LIKE_RECEIVED, '收到点赞');
        }
        return ApiResponse::success(['liked' => true, 'likes_count' => $post->fresh()->likes_count], '已点赞');
    }

    // ── 置顶/取消置顶（管理员） ──
    public function togglePin(int $id): JsonResponse
    {
        $post = ForumPost::findOrFail($id);
        $user = auth()->user();

        // 检查管理员权限
        if (!$user || !$user->hasRole('admin')) {
            return ApiResponse::error('无权操作', 403);
        }

        $post->is_pinned = !$post->is_pinned;
        $post->save();

        return ApiResponse::success([
            'is_pinned' => $post->is_pinned,
        ], $post->is_pinned ? '已置顶' : '已取消置顶');
    }

    // ── 管理后台：帖子列表 ──
    public function adminIndex(Request $request): JsonResponse
    {
        $query = ForumPost::with('user:id,name,avatar')
            ->withCount('replies')
            ->withCount('favorites')
            ->orderBy('created_at', 'desc');

        if ($q = $request->input('q')) {
            $query->where('content', 'like', "%{$q}%");
        }
        if ($request->input('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->input('pinned') !== null) {
            $query->where('is_pinned', $request->boolean('pinned'));
        }

        return ApiResponse::paginated($query->paginate($request->input('per_page', 20)));
    }

    // ── 管理后台：帖子详情 ──
    public function adminShow(int $id): JsonResponse
    {
        $post = ForumPost::with('user:id,name,avatar,email')
            ->with('replies.user:id,name,avatar')
            ->with('reactions')
            ->withCount('replies')
            ->withCount('favorites')
            ->findOrFail($id);
        return ApiResponse::success($post);
    }

    // ── 管理后台：删除帖子 ──
    public function adminDestroy(int $id): JsonResponse
    {
        $post = ForumPost::findOrFail($id);
        $post->replies()->delete();
        $post->favorites()->delete();
        $post->delete();
        return ApiResponse::success(null, '帖子已删除');
    }

    // ── 表情反应 ──
    public function toggleReaction(int $id, Request $request): JsonResponse
    {
        $request->validate(['reaction' => 'required|string|in:like,love,laugh,amazed,sad,angry']);

        $post = ForumPost::findOrFail($id);
        $myId = auth()->id();
        $reaction = $request->input('reaction');

        $existing = \App\Models\ForumReaction::where('post_id', $id)
            ->where('user_id', $myId)
            ->where('reaction', $reaction)->first();

        if ($existing) {
            $existing->delete();
            // 取消旧的 like 计数（如果 reaction 是 like）
            if ($reaction === 'like') $post->decrement('likes_count');
            return ApiResponse::success([
                'reacted' => false,
                'reaction' => $reaction,
                'reactions' => $this->getReactionSummary($id),
            ], '已取消反应');
        }

        // 先删除该用户对该帖的所有其他反应（一人一帖只能有一种反应）
        \App\Models\ForumReaction::where('post_id', $id)
            ->where('user_id', $myId)->delete();

        \App\Models\ForumReaction::create([
            'post_id' => $id,
            'user_id' => $myId,
            'reaction' => $reaction,
        ]);

        // 如果是 like 反应，同步更新 likes_count
        if ($reaction === 'like') $post->increment('likes_count');

        return ApiResponse::success([
            'reacted' => true,
            'reaction' => $reaction,
            'reactions' => $this->getReactionSummary($id),
        ], '已反应');
    }

    /**
     * 获取帖子的反应汇总
     */
    protected function getReactionSummary(int $postId): array
    {
        $counts = \App\Models\ForumReaction::where('post_id', $postId)
            ->selectRaw('reaction, COUNT(*) as count')
            ->groupBy('reaction')
            ->pluck('count', 'reaction')
            ->toArray();

        $total = array_sum($counts);
        $myId = auth()->id();
        $myReaction = null;
        if ($myId) {
            $myReaction = \App\Models\ForumReaction::where('post_id', $postId)
                ->where('user_id', $myId)->value('reaction');
        }

        return [
            'summary' => $counts,
            'total' => $total,
            'my_reaction' => $myReaction,
        ];
    }

    // ── 收藏/取消收藏 ──
    public function toggleFavorite(int $id, Request $request): JsonResponse
    {
        $post = ForumPost::findOrFail($id);
        $myId = auth()->id();

        $existing = \App\Models\ForumFavorite::where('user_id', $myId)
            ->where('post_id', $id)->first();

        if ($existing) {
            $existing->delete();
            return ApiResponse::success(['favorited' => false, 'favorites_count' => $post->fresh()->favorites()->count()], '已取消收藏');
        }

        $fav = \App\Models\ForumFavorite::create([
            'user_id' => $myId,
            'post_id' => $id,
            'collection_id' => $request->input('collection_id'),
        ]);

        return ApiResponse::success([
            'favorited' => true,
            'favorites_count' => $post->fresh()->favorites()->count(),
            'collection_id' => $fav->collection_id,
        ], '已收藏');
    }

    // ── 评论帖子 ──
    public function comment(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate(['content' => 'required|string|max:1000']);
        $post = ForumPost::findOrFail($id);

        $reply = ForumReply::create([
            'post_id' => $id,
            'user_id' => auth()->id(),
            'content' => $validated['content'],
        ]);

        $post->increment('replies_count');
        \App\Models\UserForumLevel::earn(auth()->id(), \App\Models\UserForumLevel::EXP_COMMENT, '发表评论');

        return ApiResponse::success($reply->load('user:id,name,avatar'), '评论已发布', 201);
    }

    // ── 回复评论（楼中楼） ──
    public function replyComment(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate(['content' => 'required|string|max:1000']);
        $parent = ForumReply::findOrFail($id);
        $post = ForumPost::findOrFail($parent->post_id);

        $reply = ForumReply::create([
            'post_id' => $parent->post_id,
            'parent_id' => $parent->id,
            'user_id' => auth()->id(),
            'content' => $validated['content'],
        ]);

        $post->increment('replies_count');

        return ApiResponse::success($reply->load('user:id,name,avatar'), '回复已发布', 201);
    }

    // ── 获取评论列表 ──
    public function comments(int $id): JsonResponse
    {
        $post = ForumPost::findOrFail($id);
        // 浏览量 +1
        $post->increment('views_count');
        $comments = ForumReply::where('post_id', $id)
            ->whereNull('parent_id') // 只取顶级评论
            ->with('user:id,name,avatar')
            ->with(['replies' => fn($q) => $q->with('user:id,name,avatar')->orderBy('created_at')])
            ->orderBy('created_at')
            ->get();

        return ApiResponse::success($comments);
    }

    // ── 删除帖子 ──
    public function destroy(int $id): JsonResponse
    {
        $post = ForumPost::findOrFail($id);
        if ($post->user_id !== auth()->id()) {
            return ApiResponse::error('FORBIDDEN', '只能删除自己的', 403);
        }
        $post->replies()->delete();
        ForumLike::where('likeable_type', ForumPost::class)->where('likeable_id', $id)->delete();
        $post->delete();
        return ApiResponse::success(null, '已删除');
    }

    // ── 付费帖子购买（与互物号文章付费逻辑一致） ──
    public function purchasePost(int $id, Request $request): JsonResponse
    {
        $post = ForumPost::findOrFail($id);
        if (!$post->is_paid) {
            return ApiResponse::error('NOT_PAID', '该帖子非付费内容');
        }

        $userId = auth()->id();
        $user = auth()->user();

        // 已经购买过
        if ($post->isPurchasedBy($userId)) {
            return ApiResponse::success(null, '已解锁');
        }

        // 作者本人免费
        if ($post->user_id === $userId) {
            \App\Models\ForumPostPurchase::create([
                'post_id'    => $post->id,
                'user_id'    => $userId,
                'price'      => $post->price,
                'price_type' => $post->price_type,
                'status'     => 'completed',
                'paid_at'    => now(),
            ]);
            return ApiResponse::success(null, '已解锁（作者免费）');
        }

        $price = (float) $post->price;
        if ($price <= 0) {
            return ApiResponse::error('INVALID_PRICE', '价格无效', 400);
        }

        if ($post->price_type === 'points') {
            // 积分支付
            $spent = \App\Models\UserPoint::spend($userId, $price, "付费解锁帖子: #{$post->id}");
            if (!$spent) {
                return ApiResponse::error('INSUFFICIENT_POINTS', '积分余额不足', 400);
            }

            // 给作者增加积分（扣除平台手续费 10%）
            $authorId = $post->user_id;
            if ($authorId && $authorId !== $userId) {
                $authorPoints = (int) floor($price * 0.9);
                if ($authorPoints > 0) {
                    \App\Models\UserPoint::earn($authorId, $authorPoints, "帖子被打赏: #{$post->id}");
                }
            }

            \App\Models\ForumPostPurchase::create([
                'post_id'    => $post->id,
                'user_id'    => $userId,
                'price'      => $price,
                'price_type' => 'points',
                'status'     => 'completed',
                'paid_at'    => now(),
            ]);

            return ApiResponse::success([
                'has_purchased' => true,
                'content'       => $post->content,
            ], '🎉 已解锁，积分已扣除');
        }

        // 金额支付（返回支付信息，前端跳转支付）
        if ($post->price_type === 'money') {
            $authorId = $post->user_id;

            // 检查作者是否有收益账户，没有则自动创建
            if ($authorId) {
                EarningsAccount::firstOrCreate(
                    ['user_id' => $authorId, 'type' => 'forum_post'],
                    ['pending_balance' => 0, 'available_balance' => 0, 'total_withdrawn' => 0, 'status' => 'active']
                );
            }

            $platformFee = round($price * 0.1, 2);
            $netAmount = round($price - $platformFee, 2);

            // 记录购买（状态 pending，待支付确认后改为 completed）
            $purchase = \App\Models\ForumPostPurchase::create([
                'post_id'    => $post->id,
                'user_id'    => $userId,
                'price'      => $price,
                'price_type' => 'money',
                'status'     => 'pending',
                'paid_at'    => null,
            ]);

            // 记录待结算收益
            \App\Models\ForumPostEarning::create([
                'post_id'        => $post->id,
                'buyer_id'       => $userId,
                'author_id'      => $authorId,
                'price'          => $price,
                'price_type'     => 'money',
                'platform_fee'   => $platformFee,
                'net_amount'     => $netAmount,
                'status'         => 'pending',
                'purchase_table' => 'forum_post_purchases',
                'purchase_id'    => $purchase->id,
            ]);

            // 生成支付单号
            $tradeNo = 'FP' . date('YmdHis') . str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);

            return ApiResponse::success([
                'purchase_id' => $purchase->id,
                'trade_no'    => $tradeNo,
                'price'       => $price,
                'price_type'  => 'money',
                'status'      => 'pending',
                'fee'         => $platformFee,
                'net_amount'  => $netAmount,
                'message'     => '订单已创建，请完成支付',
                // 接入支付网关后返回支付链接
                // 'pay_url'  => $payUrl,
            ], '订单已创建');
        }

        return ApiResponse::error('UNSUPPORTED_PRICE_TYPE', '不支持的支付类型', 400);
    }

    // ── 用户等级信息 ──
    public function userLevel(int $userId): JsonResponse
    {
        $level = \App\Models\UserForumLevel::firstOrCreate(['user_id' => $userId]);
        return ApiResponse::success($level->progress());
    }

    /**
     * 处理 FormData 上传的图片文件，返回 URL 数组
     */
    protected function handleImageUploads(Request $request): ?array
    {
        if (!$request->hasFile('images')) return null;

        $urls = [];
        foreach ($request->file('images') as $file) {
            if ($file->isValid()) {
                $path = $file->store('moments', 'public');
                $urls[] = url('storage/' . $path);
            }
        }
        return empty($urls) ? null : $urls;
    }

    // ── 上传图片 ──
    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|image|max:10240']);
        $path = $request->file('file')->store('moments', 'public');
        $url = asset('storage/' . $path);
        return ApiResponse::success(['url' => $url], '上传成功');
    }

    // ── 上传视频 ──
    public function uploadVideo(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|mimes:mp4,webm,ogg,mov|max:102400']);
        $path = $request->file('file')->store('moments/videos', 'public');
        $url = asset('storage/' . $path);
        return ApiResponse::success(['url' => $url], '上传成功');
    }

    // ── 转发帖子到聊天 ──
    public function forward(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_conversation_id' => 'required|integer|exists:user_conversations,id',
        ]);

        $post = ForumPost::with('user:id,name')->findOrFail($id);
        $myId = auth()->id();
        $targetConvId = $validated['target_conversation_id'];

        // 验证参与
        $isParticipant = ConversationParticipant::where('conversation_id', $targetConvId)
            ->where('user_id', $myId)->whereNull('deleted_at')->exists();
        if (!$isParticipant) {
            return ApiResponse::error('FORBIDDEN', '你不是目标会话的参与者', 403);
        }

        $content = '🌐 广场帖子：' . ($post->user->name ?? '用户') . ' — ' . mb_substr($post->content, 0, 100);

        ConversationMessage::create([
            'conversation_id' => $targetConvId,
            'sender_id' => $myId,
            'message_type' => 'text',
            'content' => $content,
            'metadata' => [
                'from_plaza' => true,
                'plaza_post_id' => $post->id,
                'plaza_author' => $post->user->name ?? '用户',
                'plaza_content' => mb_substr($post->content, 0, 200),
            ],
            'client_msg_id' => 'plaza-' . uniqid(),
        ]);

        return ApiResponse::success(null, '已转发');
    }

    public function deleteComment(int $commentId): JsonResponse
    {
        $comment = ForumReply::findOrFail($commentId);
        if ($comment->user_id !== auth()->id()) {
            return ApiResponse::error('FORBIDDEN', '无权删除', 403);
        }
        // 删除子回复
        ForumReply::where('parent_id', $commentId)->delete();
        $comment->delete();
        return ApiResponse::success(null, '评论已删除');
    }

    // ── 关注用户 ──
    public function followUser(int $targetUserId): JsonResponse
    {
        $myId = auth()->id();
        if ($myId === $targetUserId) {
            return ApiResponse::error('SELF', '不能关注自己');
        }
        if (ForumFollow::where('user_id', $myId)->where('target_user_id', $targetUserId)->exists()) {
            return ApiResponse::error('ALREADY', '已经关注了该用户');
        }
        ForumFollow::create(['user_id' => $myId, 'target_user_id' => $targetUserId]);
        return ApiResponse::success(null, '关注成功');
    }

    // ── 取消关注用户 ──
    public function unfollowUser(int $targetUserId): JsonResponse
    {
        ForumFollow::where('user_id', auth()->id())->where('target_user_id', $targetUserId)->delete();
        return ApiResponse::success(null, '已取消关注');
    }

    // ── 关注状态 ──
    public function followStatus(int $targetUserId): JsonResponse
    {
        $myId = auth()->id();
        $isFollowing = ForumFollow::where('user_id', $myId)->where('target_user_id', $targetUserId)->exists();
        $followerCount = ForumFollow::where('target_user_id', $targetUserId)->count();
        return ApiResponse::success([
            'is_following' => $isFollowing,
            'follower_count' => $followerCount,
        ]);
    }

    // ── 推荐关注用户 ──
    public function suggestedUsers(): JsonResponse
    {
        $myId = auth()->id();
        $followedIds = $myId ? ForumFollow::where('user_id', $myId)->pluck('target_user_id')->push($myId)->toArray() : [0];

        $users = \App\Models\User::select('users.id', 'users.name', 'users.avatar')
            ->selectRaw('COUNT(forum_posts.id) as posts_count')
            ->selectRaw('COALESCE(SUM(forum_posts.likes_count), 0) as total_likes')
            ->join('forum_posts', 'users.id', '=', 'forum_posts.user_id')
            ->where('forum_posts.status', 'published')
            ->whereNotIn('users.id', $followedIds)
            ->groupBy('users.id', 'users.name', 'users.avatar')
            ->orderByDesc('total_likes')
            ->orderByDesc('posts_count')
            ->limit(5)
            ->get()
            ->map(fn($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'avatar' => $u->avatar
                    ? (str_starts_with($u->avatar, 'http') ? $u->avatar : url('storage/' . $u->avatar))
                    : null,
                'posts_count' => (int) $u->posts_count,
            ]);

        return ApiResponse::success($users);
    }

    // ── 关注的人动态 ──
    public function followingFeed(Request $request): JsonResponse
    {
        $myId = auth()->id();
        $followedIds = ForumFollow::where('user_id', $myId)->pluck('target_user_id');

        if ($followedIds->isEmpty()) {
            return ApiResponse::success(['items' => [], 'total' => 0]);
        }

        $posts = ForumPost::where(function($q) {
                $q->where('status', 'published')->orWhereNull('status');
            })
            ->with('user:id,name,avatar')
            ->with('poll.options')
            ->with('tags')
            ->withCount('replies')
            ->withCount('favorites')
            ->with([
                'likes' => fn($q) => $q->where('user_id', $myId),
                'favorites' => fn($q) => $q->where('user_id', $myId),
            ])
            ->whereIn('user_id', $followedIds)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $posts->getCollection()->transform(fn($p) => $this->transformPost($p, $myId));

        return ApiResponse::paginated($posts);
    }

    // ── 个性化推荐（基于相似用户喜欢的内容） ──
    public function personalizedRecommendations(): JsonResponse
    {
        $myId = auth()->id();
        $limit = 5;

        if (!$myId) {
            // 未登录：返回热门帖子
            $posts = ForumPost::where('status', 'published')
                ->orderBy('likes_count', 'desc')->limit($limit)->get();
            return ApiResponse::success($posts->map(fn($p) => $this->transformPost($p, 0)));
        }

        // 获取我点赞过的帖子作者ID
        $likedPostIds = \App\Models\ForumLike::where('user_id', $myId)->pluck('post_id');
        $likedUserIds = ForumPost::whereIn('id', $likedPostIds)->pluck('user_id')->unique();

        if ($likedPostIds->isEmpty()) {
            // 没有点赞记录：返回热门帖子
            $posts = ForumPost::where('status', 'published')
                ->with('user:id,name,avatar')->withCount('replies')
                ->orderBy('likes_count', 'desc')->limit($limit)->get();
            return ApiResponse::success($posts->map(fn($p) => $this->transformPost($p, $myId)));
        }

        // 找同样点赞过这些帖子的其他用户
        $similarUserIds = \App\Models\ForumLike::whereIn('post_id', $likedPostIds)
            ->where('user_id', '!=', $myId)
            ->pluck('user_id')
            ->unique();

        // 推荐这些相似用户的热门帖子（排除已赞过的）
        $query = ForumPost::where('status', 'published')
            ->with('user:id,name,avatar')
            ->withCount('replies')
            ->withCount('favorites')
            ->with(['likes' => fn($q) => $q->where('user_id', $myId)])
            ->whereNotIn('id', $likedPostIds);

        if ($similarUserIds->isNotEmpty()) {
            $query->whereIn('user_id', $similarUserIds)
                ->orderBy('likes_count', 'desc');
        } else {
            // 无相似用户：推荐同作者的其他帖子
            $query->whereIn('user_id', $likedUserIds)
                ->orderBy('created_at', 'desc');
        }

        $posts = $query->limit($limit)->get();
        return ApiResponse::success($posts->map(fn($p) => $this->transformPost($p, $myId)));
    }

    // ── 投票 ──
    public function vote(int $postId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'option_id' => 'required|integer|exists:forum_poll_options,id',
        ]);

        $myId = auth()->id();
        $poll = ForumPoll::where('post_id', $postId)->firstOrFail();
        $option = ForumPollOption::where('poll_id', $poll->id)->findOrFail($validated['option_id']);

        // 检查是否已投过
        if (ForumPollVote::where('poll_id', $poll->id)->where('user_id', $myId)->exists()) {
            return ApiResponse::error('ALREADY_VOTED', '你已经投过票了');
        }

        ForumPollVote::create([
            'poll_id' => $poll->id,
            'option_id' => $option->id,
            'user_id' => $myId,
        ]);

        // 返回更新后的投票结果
        $totalVotes = ForumPollVote::where('poll_id', $poll->id)->count();
        $options = ForumPollOption::where('poll_id', $poll->id)->get()->map(function ($opt) use ($myId) {
            $vc = ForumPollVote::where('option_id', $opt->id)->count();
            return [
                'id' => $opt->id,
                'label' => $opt->label,
                'votes' => $vc,
                'percent' => 0,
                'voted' => ForumPollVote::where('option_id', $opt->id)->where('user_id', $myId)->exists(),
            ];
        });
        $t = $totalVotes ?: 1;
        foreach ($options as &$opt) { $opt['percent'] = round(($opt['votes'] / $t) * 100); }

        return ApiResponse::success([
            'total_votes' => $totalVotes,
            'options' => $options,
            'voted' => true,
        ], '投票成功');
    }

    // ── 收藏夹管理 ──

    // 获取用户的所有收藏夹及其数量
    public function favoriteCollections(): JsonResponse
    {
        $myId = auth()->id();
        $collections = ForumFavoriteCollection::where('user_id', $myId)
            ->withCount('favorites')
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get();

        // 未分类收藏
        $uncategorized = ForumFavorite::where('user_id', $myId)
            ->whereNull('collection_id')
            ->count();

        return ApiResponse::success([
            'collections' => $collections->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'icon' => $c->icon,
                'sort_order' => $c->sort_order,
                'favorites_count' => $c->favorites_count,
            ]),
            'uncategorized_count' => $uncategorized,
        ]);
    }

    // 创建收藏夹
    public function createCollection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'icon' => 'nullable|string|max:10',
        ]);

        $maxSort = ForumFavoriteCollection::where('user_id', auth()->id())->max('sort_order') ?? 0;

        $collection = ForumFavoriteCollection::create([
            'user_id' => auth()->id(),
            'name' => $validated['name'],
            'icon' => $validated['icon'] ?? '📁',
            'sort_order' => $maxSort + 1,
        ]);

        return ApiResponse::success([
            'id' => $collection->id,
            'name' => $collection->name,
            'icon' => $collection->icon,
        ], '收藏夹已创建');
    }

    // 更新收藏夹
    public function updateCollection(int $id, Request $request): JsonResponse
    {
        $collection = ForumFavoriteCollection::where('user_id', auth()->id())->findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'icon' => 'sometimes|string|max:10',
        ]);

        $collection->update($validated);
        return ApiResponse::success($collection, '已更新');
    }

    // 删除收藏夹（收藏不会丢失，变为未分类）
    public function deleteCollection(int $id): JsonResponse
    {
        $collection = ForumFavoriteCollection::where('user_id', auth()->id())->findOrFail($id);
        ForumFavorite::where('collection_id', $id)->update(['collection_id' => null]);
        $collection->delete();
        return ApiResponse::success(null, '收藏夹已删除');
    }

    // 移动收藏到收藏夹
    public function moveFavorite(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'post_id' => 'required|integer|exists:forum_posts,id',
            'collection_id' => 'nullable|integer|exists:forum_favorite_collections,id',
        ]);

        $myId = auth()->id();
        $fav = ForumFavorite::where('user_id', $myId)
            ->where('post_id', $validated['post_id'])
            ->firstOrFail();

        $fav->update(['collection_id' => $validated['collection_id']]);

        return ApiResponse::success(['collection_id' => $fav->collection_id], '已移动');
    }

    // 获取用户收藏的帖子（可按收藏夹筛选）
    public function myFavorites(Request $request): JsonResponse
    {
        $myId = auth()->id();
        $collectionId = $request->input('collection_id');

        $query = ForumFavorite::where('user_id', $myId)
            ->with(['post' => fn($q) => $q->with('user:id,name,avatar')->with('tags'), 'collection']);

        if ($collectionId) {
            $query->where('collection_id', $collectionId);
        }

        $favorites = $query->orderByDesc('created_at')->paginate(20);

        $favorites->getCollection()->transform(fn($fav) => [
            'id' => $fav->id,
            'post_id' => $fav->post_id,
            'collection_id' => $fav->collection_id,
            'collection' => $fav->collection ? ['id' => $fav->collection->id, 'name' => $fav->collection->name, 'icon' => $fav->collection->icon] : null,
            'created_at' => $fav->created_at,
            'post' => $fav->post ? [
                'id' => $fav->post->id,
                'content' => $fav->post->content,
                'images' => $fav->post->images,
                'likes_count' => $fav->post->likes_count,
                'replies_count' => $fav->post->replies_count,
                'views_count' => $fav->post->views_count,
                'created_at' => $fav->post->created_at,
                'tags' => $fav->post->tags ? $fav->post->tags->map(fn($t) => ['id' => $t->id, 'name' => $t->name, 'slug' => $t->slug]) : [],
                'user' => $fav->post->user ? [
                    'id' => $fav->post->user->id,
                    'name' => $fav->post->user->name,
                    'avatar_url' => $fav->post->user->avatar
                        ? (str_starts_with($fav->post->user->avatar, 'http') ? $fav->post->user->avatar : asset('storage/' . $fav->post->user->avatar))
                        : null,
                ] : null,
            ] : null,
        ]);

        return ApiResponse::paginated($favorites);
    }

    // ── 我的数据统计 ──
    public function myStats(): JsonResponse
    {
        $myId = auth()->id();

        $postsCount = ForumPost::where('user_id', $myId)->count();
        $totalLikes = ForumPost::where('user_id', $myId)->sum('likes_count');
        $totalFavorites = ForumPost::where('user_id', $myId)->sum('favorites_count');
        $totalReplies = ForumPost::where('user_id', $myId)->sum('replies_count');
        $totalViews = ForumPost::where('user_id', $myId)->sum('views_count');

        return ApiResponse::success([
            'posts_count' => $postsCount,
            'total_likes' => $totalLikes,
            'total_favorites' => $totalFavorites,
            'total_replies' => $totalReplies,
            'total_views' => $totalViews,
        ]);
    }

    // ── 草稿箱列表 ──
    public function drafts(): JsonResponse
    {
        $myId = auth()->id();
        $posts = ForumPost::where('user_id', $myId)
            ->where('status', 'draft')
            ->with('tags')
            ->orderBy('updated_at', 'desc')
            ->paginate(20);

        $posts->getCollection()->transform(fn($p) => [
            'id' => $p->id,
            'content' => $p->content,
            'images' => $p->images,
            'category_id' => $p->category_id,
            'status' => $p->status,
            'updated_at' => $p->updated_at,
            'created_at' => $p->created_at,
            'tags' => $p->tags ? $p->tags->map(fn($t) => ['id' => $t->id, 'name' => $t->name, 'slug' => $t->slug]) : [],
        ]);

        return ApiResponse::paginated($posts);
    }

    // ── 定时列表 ──
    public function scheduled(): JsonResponse
    {
        $myId = auth()->id();
        $posts = ForumPost::where('user_id', $myId)
            ->where('status', 'scheduled')
            ->with('tags')
            ->orderBy('scheduled_at')
            ->paginate(20);

        $posts->getCollection()->transform(fn($p) => [
            'id' => $p->id,
            'content' => $p->content,
            'images' => $p->images,
            'category_id' => $p->category_id,
            'status' => $p->status,
            'scheduled_at' => $p->scheduled_at?->toDateTimeString(),
            'updated_at' => $p->updated_at,
            'created_at' => $p->created_at,
            'tags' => $p->tags ? $p->tags->map(fn($t) => ['id' => $t->id, 'name' => $t->name, 'slug' => $t->slug]) : [],
        ]);

        return ApiResponse::paginated($posts);
    }

    // ── 热门话题标签 ──
    public function trendingTags(): JsonResponse
    {
        // 优先从数据库标签查询
        $tags = ForumTag::withCount('posts')
            ->orderBy('posts_count', 'desc')
            ->orderBy('sort_order')
            ->limit(20)
            ->get()
            ->map(fn($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'posts_count' => $t->posts_count,
            ]);

        // 如果没有标签数据，从帖子内容提取热门关键词作为 fallback
        if ($tags->isEmpty()) {
            $keywords = $this->extractTrendingFromPosts();
            return ApiResponse::success($keywords);
        }

        return ApiResponse::success($tags);
    }

    /**
     * 从帖子内容提取热门关键词（标签表为空时的 fallback）
     */
    protected function extractTrendingFromPosts(): array
    {
        $posts = ForumPost::whereIn('status', ['published', null])
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        // 常见社区分类词典
        $dictionary = [
            '求助', '分享', '讨论', '教程', '公告', '提问', '建议',
            '反馈', '经验', '推荐', '评测', '避坑', '求助', '开源',
            '更新', '版本', 'bug', 'BUG', '功能', '设计', '开发',
            '部署', '配置', '安装', '升级', '迁移', '兼容', '优化',
            'AI', 'API', '前端', '后端', '全栈', '数据库', '服务器',
        ];

        $wordCount = [];
        foreach ($posts as $post) {
            $text = strip_tags($post->content);
            // 匹配中英文关键词
            preg_match_all('/#([\x{4e00}-\x{9fa5}\w\-]+)/u', $text, $hashtags);
            foreach ($hashtags[1] ?? [] as $tag) {
                $key = mb_strtolower($tag);
                $wordCount[$key] = ($wordCount[$key] ?? 0) + 1;
            }
            // 匹配词典中的关键词
            foreach ($dictionary as $word) {
                if (mb_strpos($text, $word) !== false) {
                    $wordCount[$word] = ($wordCount[$word] ?? 0) + 1;
                }
            }
        }

        // 排序取前 10 个
        arsort($wordCount);
        $result = [];
        $i = 0;
        foreach (array_slice($wordCount, 0, 10) as $name => $count) {
            $i++;
            $result[] = [
                'id' => $i * -1, // 负 ID 表示是临时提取的
                'name' => $name,
                'slug' => \Illuminate\Support\Str::slug($name),
                'posts_count' => $count,
            ];
        }

        return $result;
    }

    // ── 举报广场帖子（AI 自动审核） ──
    public function reportPost(int $id, Request $request): JsonResponse
    {
        $post = ForumPost::findOrFail($id);

        $validated = $request->validate([
            'reason' => 'required|string|in:spam,harassment,pornographic,illegal,other',
            'description' => 'nullable|string|max:1000',
        ]);

        // 创建举报记录
        $report = \App\Models\UserReport::create([
            'reporter_id' => auth()->id(),
            'reportable_type' => ForumPost::class,
            'reportable_id' => $post->id,
            'reason' => $validated['reason'],
            'description' => $validated['description'] ?? null,
            'status' => 'pending',
        ]);

        // AI 自动审核
        try {
            $moderation = app(PostModerationService::class);
            $result = $moderation->reviewReport($report);

            return ApiResponse::success([
                'report_id' => $report->id,
                'action' => $result['action'],
                'message' => $result['message'],
            ], '举报已提交' . ($result['action'] !== 'skipped' ? '，AI 已自动处理' : ''));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[Moment] 举报AI审核异常: ' . $e->getMessage());
            return ApiResponse::success([
                'report_id' => $report->id,
                'action' => 'pending',
                'message' => '举报已提交，等待人工审核',
            ], '举报已提交');
        }
    }

    // ── 贡献榜（本周 / 总榜 Top 用户） ──
    public function topContributors(): JsonResponse
    {
        $users = \App\Models\User::select('users.id', 'users.name', 'users.avatar')
            ->selectRaw('COUNT(forum_posts.id) as posts_count')
            ->selectRaw('COALESCE(SUM(forum_posts.likes_count), 0) as likes_count')
            ->selectRaw('COALESCE(SUM(forum_posts.replies_count), 0) as replies_count')
            ->join('forum_posts', 'users.id', '=', 'forum_posts.user_id')
            ->where('forum_posts.status', 'published')
            ->groupBy('users.id', 'users.name', 'users.avatar')
            ->orderByDesc('likes_count')
            ->orderByDesc('posts_count')
            ->limit(10)
            ->get()
            ->map(fn($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'avatar' => $u->avatar
                    ? (str_starts_with($u->avatar, 'http') ? $u->avatar : url('storage/' . $u->avatar))
                    : null,
                'posts_count' => (int) $u->posts_count,
                'likes_count' => (int) $u->likes_count,
            ]);

        return ApiResponse::success($users);
    }

    // ── 智能标签推荐 ──
    public function tagSuggestions(Request $request): JsonResponse
    {
        $request->validate(['content' => 'required|string|max:5000']);

        try {
            $service = app(\App\Services\TagSuggestionService::class);
            $tags = $service->suggest($request->input('content'), 5);

            return ApiResponse::success([
                'tags' => $tags,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[Moment] 标签推荐异常: ' . $e->getMessage());
            return ApiResponse::success(['tags' => []]);
        }
    }
}
