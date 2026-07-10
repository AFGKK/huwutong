<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\Follow;
use App\Models\OfficialAccount;
use App\Models\OaFollower;
use App\Models\OaArticle;
use App\Models\OaArticleLike;
use App\Models\OaArticleRead;
use App\Models\OaArticleShare;
use App\Models\OaSubmission;
use App\Models\Product;
use App\Models\ConversationMessage;
use App\Models\ConversationParticipant;
use App\Models\ForumPost;
use App\Models\ChannelMessage;
use App\Models\OaAutoReply;
use App\Models\OaMenu;
use App\Models\OaMaterial;
use App\Models\OaMessage;
use App\Models\OaFavorite;
use App\Models\OaComment;
use App\Models\OaCommentLike;
use App\Models\OaCollection;
use App\Models\OaReadingListItem;
use App\Models\OaCategory;
use App\Models\OaFollowerTag;
use App\Models\OaFollowerTagRelation;
use App\Models\OaArticlePurchase;
use App\Models\OaArticleEarning;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\PollVote;
use App\Models\OaPlatformAccount;
use App\Models\OaArticleDistribution;
use App\Models\EarningsAccount;
use App\Models\UserPoint;
use App\Events\OaArticlePublished;
use App\Events\OaSubmissionCreated;
use App\Services\AiRecommendationService;
use App\Services\BehaviorSequenceService;
use App\Services\SensitiveWordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OfficialAccountController extends Controller
{
    /**
     * 公开：获取互物号列表
     * GET /api/official-accounts/public
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 20);
        $sort = $request->input('sort', 'followers');

        $query = OfficialAccount::where('status', 'active');

        // 排序
        if ($sort === 'articles') {
            $query->withCount('articles')->orderBy('articles_count', 'desc');
        } elseif ($sort === 'newest') {
            $query->latest();
        } else {
            $query->withCount('followers')->orderBy('followers_count', 'desc');
        }

        $accounts = $query->paginate($perPage);

        return ApiResponse::success($accounts);
    }

    /**
     * 搜索互物号
     * GET /api/official-accounts/search
     */
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));

        $query = OfficialAccount::where('status', 'active');

        if ($q !== '') {
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('slug', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        $accounts = $query->orderByDesc('id')->limit(20)->get(['id', 'name', 'slug', 'avatar', 'description']);

        return ApiResponse::success($accounts);
    }

    /**
     * 公开：文章详情
     * GET /api/official-accounts/articles/{articleId}
     */
    public function articleDetail(int $articleId): JsonResponse
    {
        $article = OaArticle::where('status', 'published')
            ->with('account:id,name,slug,avatar,description')
            ->find($articleId);

        if (! $article) {
            return ApiResponse::notFound('文章不存在');
        }

        return ApiResponse::success($article);
    }

    /**
     * 公开：获取所有文章
     * GET /api/official-accounts/public/articles
     */
    public function allArticles(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 20);

        $articles = OaArticle::where('status', 'published')
            ->with('account:id,name,slug,avatar,description')
            ->latest()
            ->paginate($perPage);

        return ApiResponse::success($articles);
    }

    /**
     * 公开：获取分类列表
     * GET /api/official-accounts/public/categories
     */
    public function categories(): JsonResponse
    {
        $categories = \App\Models\OaCategory::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'icon']);

        return ApiResponse::success($categories);
    }

    function myOwnedAccounts(): JsonResponse
    {
        $accounts = OfficialAccount::where('owner_id', auth()->id())
            ->withCount(['followers', 'articles' => fn($q) => $q->where('status', 'published')])
            ->get()
            ->map(function ($account) {
                $articleIds = OaArticle::where('account_id', $account->id)->pluck('id');

                $totalReads = OaArticleRead::whereIn('article_id', $articleIds)->count();
                $totalLikes = \App\Models\Like::where('likeable_type', 'App\\Models\\OaArticle')
                    ->whereIn('likeable_id', $articleIds)->count();

                // 近7天 vs 前7天
                $reads7d = OaArticleRead::whereIn('article_id', $articleIds)
                    ->where('created_at', '>=', now()->subDays(7))->count();
                $readsPrev7d = OaArticleRead::whereIn('article_id', $articleIds)
                    ->whereBetween('created_at', [now()->subDays(14), now()->subDays(7)])->count();
                $likes7d = \App\Models\Like::where('likeable_type', 'App\\Models\\OaArticle')
                    ->whereIn('likeable_id', $articleIds)
                    ->where('created_at', '>=', now()->subDays(7))->count();
                $likesPrev7d = \App\Models\Like::where('likeable_type', 'App\\Models\\OaArticle')
                    ->whereIn('likeable_id', $articleIds)
                    ->whereBetween('created_at', [now()->subDays(14), now()->subDays(7)])->count();

                // 近7天每日阅读趋势
                $readsTrend = [];
                for ($i = 6; $i >= 0; $i--) {
                    $date = today()->subDays($i);
                    $readsTrend[] = [
                        'date' => $date->format('m/d'),
                        'count' => OaArticleRead::whereIn('article_id', $articleIds)
                            ->whereDate('created_at', $date)->count(),
                    ];
                }

                return [
                    'id' => $account->id,
                    'name' => $account->name,
                    'slug' => $account->slug,
                    'status' => $account->status,
                    'description' => $account->description,
                    'avatar' => $account->avatar,
                    'category_id' => $account->category_id,
                    'followers_count' => $account->followers_count,
                    'articles_count' => $account->articles_count,
                    'total_reads' => $totalReads,
                    'total_likes' => $totalLikes,
                    'reads_7d' => $reads7d,
                    'likes_7d' => $likes7d,
                    'reads_growth' => $readsPrev7d > 0 ? round(($reads7d - $readsPrev7d) / $readsPrev7d * 100) : ($reads7d > 0 ? 100 : 0),
                    'likes_growth' => $likesPrev7d > 0 ? round(($likes7d - $likesPrev7d) / $likesPrev7d * 100) : ($likes7d > 0 ? 100 : 0),
                    'reads_trend' => $readsTrend,
                    'is_verified' => $account->is_verified,
                    'verified_info' => $account->is_verified ? ($account->settings['verified_info'] ?? null) : null,
                ];
            });

        return ApiResponse::success($accounts);
    }

    // ── 粉丝列表 ──
    public function followers(int $id, Request $request): JsonResponse
    {
        $account = OfficialAccount::where('owner_id', auth()->id())->findOrFail($id);
        $query = \App\Models\Follow::where('followable_type', 'App\\Models\\OfficialAccount')
            ->where('followable_id', $id)
            ->with('user:id,name,avatar,email')
            ->orderBy('created_at', 'desc');

        if ($q = $request->input('q')) {
            $query->whereHas('user', fn($u) => $u->where('name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%"));
        }

        return ApiResponse::paginated($query->paginate($request->input('per_page', 20)));
    }

    public function createArticle(int $id, Request $request): JsonResponse
    {
        $account = OfficialAccount::where('owner_id', auth()->id())->findOrFail($id);

        if ($account->status !== 'active') {
            $msg = $account->status === 'pending' ? '该互物号正在审核中，审核通过后才能发布文章' : '该互物号已被禁用，无法发布文章';
            return ApiResponse::error('ACCOUNT_NOT_ACTIVE', $msg, 422);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'content' => 'required|string',
            'cover_image' => 'nullable|string|max:500',
            'images' => 'nullable|array',
            'summary' => 'nullable|string|max:300',
            'tags' => 'nullable|array',
            'is_pinned' => 'nullable|boolean',
            'is_original' => 'boolean',
            'allow_comments' => 'boolean',
            'status' => 'nullable|in:draft,published,scheduled',
            'scheduled_at' => 'nullable|date|after:now',
        ]);

        $status = $validated['status'] ?? 'draft';
        $scheduledAt = $validated['scheduled_at'] ?? null;

        // 定时发布必须同时设置 scheduled_at
        if ($status === 'scheduled' && !$scheduledAt) {
            return ApiResponse::error('定时发布必须设置发布时间', 422);
        }

        $article = OaArticle::create([
            'account_id' => $id,
            'author_id' => auth()->id(),
            'title' => $validated['title'],
            'content' => $validated['content'],
            'cover_image' => $validated['cover_image'] ?? null,
            'summary' => $validated['summary'] ?? null,
            'tags' => $validated['tags'] ?? null,
            'is_pinned' => $validated['is_pinned'] ?? false,
            'status' => $status,
            'published_at' => $status === 'published' ? now() : null,
            'scheduled_at' => $status === 'scheduled' ? $scheduledAt : null,
        ]);

        // 触发 AI 自动评论（全自动）
        if ($status === 'published') {
            OaArticlePublished::dispatch($article);
        }

        $msg = $status === 'published' ? '文章已发布' : ($status === 'scheduled' ? '已设置定时发布' : '草稿已保存');
        return ApiResponse::success($article, $msg, 201);
    }

    public function updateArticle(int $id, Request $request): JsonResponse
    {
        $article = OaArticle::whereHas('account', fn($q) => $q->where('owner_id', auth()->id()))
            ->findOrFail($id);

        // 只能修改一次
        if ($article->edited_at) {
            return ApiResponse::error('文章仅可修改一次，已被编辑过', 422);
        }

        $updateData = $request->only(['title', 'content', 'cover_image', 'images', 'summary', 'tags', 'is_pinned', 'is_original', 'allow_comments']);
        $updateData['edited_at'] = now();

        if ($request->has('scheduled_at')) {
            $updateData['scheduled_at'] = $request->input('scheduled_at');
            if ($request->input('scheduled_at')) {
                $updateData['status'] = 'scheduled';
            }
        }

        $article->update($updateData);

        if ($request->has('status') && $request->input('status') === 'published' && $article->status !== 'published') {
            $article->update(['status' => 'published', 'published_at' => now(), 'scheduled_at' => null]);
            // 触发 AI 自动评论
            OaArticlePublished::dispatch($article->fresh());
        }

        return ApiResponse::success($article->fresh(), '已更新');
    }

    public function deleteArticle(int $id): JsonResponse
    {
        $article = OaArticle::whereHas('account', fn($q) => $q->where('owner_id', auth()->id()))
            ->findOrFail($id);
        $article->delete();
        return ApiResponse::success(null, '已删除');
    }

    public function togglePinArticle(int $id): JsonResponse
    {
        $article = OaArticle::whereHas('account', fn($q) => $q->where('owner_id', auth()->id()))
            ->findOrFail($id);
        $article->update(['is_pinned' => !$article->is_pinned]);
        return ApiResponse::success(['is_pinned' => $article->fresh()->is_pinned], '已更新');
    }

    // ── 更新阅读行为数据 ──
    public function updateReadBehavior(int $articleId, Request $request): JsonResponse
    {
        $request->validate([
            'read_duration' => 'nullable|integer|min:0|max:86400',
            'scroll_depth'  => 'nullable|integer|min:0|max:100',
            'completed'     => 'nullable|boolean',
        ]);

        $userId = auth()->id();
        if (!$userId) return ApiResponse::error('请先登录', 401);

        $read = OaArticleRead::where('article_id', $articleId)
            ->where('user_id', $userId)
            ->latest()
            ->first();

        if (!$read) return ApiResponse::error('未找到阅读记录', 404);

        $update = [];
        if ($request->has('read_duration')) $update['read_duration'] = $request->input('read_duration');
        if ($request->has('scroll_depth'))  $update['scroll_depth'] = $request->input('scroll_depth');
        if ($request->has('completed'))     $update['completed'] = $request->boolean('completed');

        if (!empty($update)) {
            $read->update($update);
        }

        return ApiResponse::success(null, '已记录');
    }

    // ── 文章阅读留存曲线 ──
    public function articleRetention(int $articleId): JsonResponse
    {
        $article = OaArticle::whereHas('account', fn($q) => $q->where('owner_id', auth()->id()))
            ->findOrFail($articleId);

        // 滚动深度分布
        $scrollBuckets = [
            '0-25%'  => OaArticleRead::where('article_id', $articleId)->where('scroll_depth', '>=', 0)->where('scroll_depth', '<=', 25)->count(),
            '25-50%' => OaArticleRead::where('article_id', $articleId)->where('scroll_depth', '>', 25)->where('scroll_depth', '<=', 50)->count(),
            '50-75%' => OaArticleRead::where('article_id', $articleId)->where('scroll_depth', '>', 50)->where('scroll_depth', '<=', 75)->count(),
            '75-100%'=> OaArticleRead::where('article_id', $articleId)->where('scroll_depth', '>', 75)->count(),
        ];

        // 阅读时长分布（秒）
        $durationBuckets = [
            '<10s'  => OaArticleRead::where('article_id', $articleId)->where('read_duration', '>=', 0)->where('read_duration', '<', 10)->count(),
            '10-30s'=> OaArticleRead::where('article_id', $articleId)->where('read_duration', '>=', 10)->where('read_duration', '<', 30)->count(),
            '30-60s'=> OaArticleRead::where('article_id', $articleId)->where('read_duration', '>=', 30)->where('read_duration', '<', 60)->count(),
            '1-3m'  => OaArticleRead::where('article_id', $articleId)->where('read_duration', '>=', 60)->where('read_duration', '<', 180)->count(),
            '3m+'   => OaArticleRead::where('article_id', $articleId)->where('read_duration', '>=', 180)->count(),
        ];

        // 完读率 = completed / 总记录
        $totalReads = OaArticleRead::where('article_id', $articleId)->count();
        $completedCount = OaArticleRead::where('article_id', $articleId)->where('completed', true)->count();
        $completionRate = $totalReads > 0 ? round(($completedCount / $totalReads) * 100, 1) : 0;

        // 平均阅读时长
        $avgDuration = (float) OaArticleRead::where('article_id', $articleId)
            ->whereNotNull('read_duration')
            ->avg('read_duration');

        return ApiResponse::success([
            'scroll_distribution' => $scrollBuckets,
            'duration_distribution' => $durationBuckets,
            'completion_rate'    => $completionRate,
            'completed_count'    => $completedCount,
            'total_reads'        => $totalReads,
            'avg_read_duration'  => round($avgDuration, 1),
        ]);
    }

    public function articleStats(int $articleId): JsonResponse
    {
        $article = OaArticle::whereHas('account', fn($q) => $q->where('owner_id', auth()->id()))
            ->withCount(['likes', 'reads', 'shares', 'favorites', 'comments' => fn($q) => $q->where('status', 'approved')])
            ->findOrFail($articleId);

        // 近7天阅读趋势
        $sevenDaysAgo = now()->subDays(6)->startOfDay();
        $dailyReads = OaArticleRead::where('article_id', $articleId)
            ->where('created_at', '>=', $sevenDaysAgo)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date');

        // 填充空白日期
        $readTrend = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $readTrend[] = [
                'date' => $date,
                'count' => (int) ($dailyReads[$date] ?? 0),
            ];
        }

        // 今日阅读
        $todayReads = OaArticleRead::where('article_id', $articleId)
            ->whereDate('created_at', today())
            ->count();

        // 昨日阅读
        $yesterdayReads = OaArticleRead::where('article_id', $articleId)
            ->whereDate('created_at', today()->subDay())
            ->count();

        // 点赞用户列表（最近10个）
        $recentLikers = OaArticleLike::where('article_id', $articleId)
            ->with('user:id,name,avatar')
            ->latest()
            ->take(10)
            ->get()
            ->map(fn($l) => [
                'id' => $l->user_id,
                'name' => $l->user?->name,
                'avatar' => $l->user?->avatar
                    ? (str_starts_with($l->user->avatar, 'http') ? $l->user->avatar : asset('storage/' . $l->user->avatar))
                    : null,
            ]);

        return ApiResponse::success([
            'article_id' => $article->id,
            'title' => $article->title,
            'published_at' => $article->published_at,
            'likes_count' => $article->likes_count,
            'reads_count' => $article->reads_count,
            'shares_count' => $article->shares_count,
            'favorites_count' => $article->favorites_count ?? 0,
            'comments_count' => $article->comments_count ?? 0,
            'today_reads' => $todayReads,
            'yesterday_reads' => $yesterdayReads,
            'read_trend' => $readTrend,
            'recent_likers' => $recentLikers,
        ]);
    }

    // ════════════════════════════════════════════
    // 自定义菜单管理
    // ════════════════════════════════════════════

    public function menus(int $accountId): JsonResponse
    {
        $account = OfficialAccount::findOrFail($accountId);
        $menus = OaMenu::where('account_id', $accountId)
            ->whereNull('parent_id')
            ->with(['children' => fn($q) => $q->orderBy('sort_order')->where('is_active', true)])
            ->orderBy('sort_order')
            ->where('is_active', true)
            ->get();
        return ApiResponse::success($menus);
    }

    public function storeMenu(int $accountId, Request $request): JsonResponse
    {
        $account = OfficialAccount::where('owner_id', auth()->id())->findOrFail($accountId);

        $validated = $request->validate([
            'name' => 'required|string|max:40',
            'type' => 'nullable|string|in:click,view,miniprogram',
            'key' => 'nullable|string|max:128',
            'parent_id' => 'nullable|integer|exists:oa_menus,id',
            'app_id' => 'nullable|string|max:50',
            'page_path' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        // 校验：父菜单必须在同一个互物号下
        if (!empty($validated['parent_id'])) {
            $parent = OaMenu::where('account_id', $accountId)->findOrFail($validated['parent_id']);
            if ($parent->parent_id !== null) {
                return ApiResponse::error('只支持两级菜单', 422);
            }
        }

        // 校验：一级菜单最多3个，二级菜单每个一级最多5个
        if (empty($validated['parent_id'])) {
            $count = OaMenu::where('account_id', $accountId)->whereNull('parent_id')->count();
            if ($count >= 3) {
                return ApiResponse::error('一级菜单最多3个', 422);
            }
        } else {
            $count = OaMenu::where('parent_id', $validated['parent_id'])->count();
            if ($count >= 5) {
                return ApiResponse::error('二级菜单最多5个', 422);
            }
        }

        $validated['account_id'] = $accountId;
        $validated['type'] ??= 'click';
        $validated['is_active'] ??= true;
        $validated['sort_order'] ??= 0;

        $menu = OaMenu::create($validated);
        return ApiResponse::success($menu->load('children'), '菜单已创建', 201);
    }

    public function updateMenu(int $menuId, Request $request): JsonResponse
    {
        $menu = OaMenu::whereHas('account', fn($q) => $q->where('owner_id', auth()->id()))
            ->findOrFail($menuId);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:40',
            'type' => 'nullable|string|in:click,view,miniprogram',
            'key' => 'nullable|string|max:128',
            'app_id' => 'nullable|string|max:50',
            'page_path' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $menu->update($validated);
        return ApiResponse::success($menu->fresh()->load('children'), '菜单已更新');
    }

    public function deleteMenu(int $menuId): JsonResponse
    {
        $menu = OaMenu::whereHas('account', fn($q) => $q->where('owner_id', auth()->id()))
            ->findOrFail($menuId);
        // 删除子菜单
        $menu->children()->delete();
        $menu->delete();
        return ApiResponse::success(null, '菜单已删除');
    }

    // ════════════════════════════════════════════
    // 素材管理
    // ════════════════════════════════════════════

    public function materials(int $accountId, Request $request): JsonResponse
    {
        $account = OfficialAccount::where('owner_id', auth()->id())->findOrFail($accountId);

        $query = OaMaterial::where('account_id', $accountId);

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }
        if ($group = $request->input('group')) {
            $query->where('group', $group);
        }
        if ($q = $request->input('q')) {
            $query->where(function($q2) use ($q) {
                $q2->where('file_name', 'like', "%{$q}%")
                    ->orWhere('content', 'like', "%{$q}%");
            });
        }

        return ApiResponse::success(
            $query->orderBy('id', 'desc')->paginate($request->input('per_page', 30))
        );
    }

    public function storeMaterial(int $accountId, Request $request): JsonResponse
    {
        $account = OfficialAccount::where('owner_id', auth()->id())->findOrFail($accountId);

        $validated = $request->validate([
            'type' => 'required|string|in:text',
            'content' => 'required_without:file_url|string',
            'file_url' => 'nullable|string|max:500',
            'group' => 'nullable|string|max:50',
        ]);

        $validated['account_id'] = $accountId;
        $material = OaMaterial::create($validated);

        return ApiResponse::success($material, '素材已创建', 201);
    }

    public function uploadMaterial(int $accountId, Request $request): JsonResponse
    {
        $account = OfficialAccount::where('owner_id', auth()->id())->findOrFail($accountId);

        $request->validate([
            'file' => 'required|image|max:10240',
            'group' => 'nullable|string|max:50',
        ]);

        $file = $request->file('file');
        $path = $file->store('oa-materials', 'public');

        $material = OaMaterial::create([
            'account_id' => $accountId,
            'type' => 'image',
            'file_url' => asset('storage/' . $path),
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'group' => $request->input('group'),
        ]);

        return ApiResponse::success($material, '素材已上传', 201);
    }

    public function updateMaterial(int $materialId, Request $request): JsonResponse
    {
        $material = OaMaterial::whereHas('account', fn($q) => $q->where('owner_id', auth()->id()))
            ->findOrFail($materialId);

        $validated = $request->validate([
            'content' => 'nullable|string',
            'group' => 'nullable|string|max:50',
        ]);

        $material->update($validated);
        return ApiResponse::success($material->fresh(), '已更新');
    }

    public function deleteMaterial(int $materialId): JsonResponse
    {
        $material = OaMaterial::whereHas('account', fn($q) => $q->where('owner_id', auth()->id()))
            ->findOrFail($materialId);
        $material->delete();
        return ApiResponse::success(null, '已删除');
    }

    public function qrCode(int $accountId): JsonResponse
    {
        $account = OfficialAccount::findOrFail($accountId);

        $followUrl = url('/build/user-chat?follow_oa=' . $account->id);
        $slugUrl = $account->slug ? url('/oa/' . $account->slug) : null;

        return ApiResponse::success([
            'account_id' => $account->id,
            'name' => $account->name,
            'avatar' => $account->avatar
                ? (str_starts_with($account->avatar, 'http') ? $account->avatar : asset('storage/' . $account->avatar))
                : null,
            'follow_url' => $followUrl,
            'slug_url' => $slugUrl,
            'qr_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($followUrl),
            'download_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=600x600&data=' . urlencode($followUrl),
            'followers_count' => $account->followers()->count(),
        ]);
    }

    // ════════════════════════════════════════════
    // 关注者消息系统
    // ════════════════════════════════════════════

    public function conversations(int $accountId): JsonResponse
    {
        // 获取所有有消息记录的关注者列表（号主用）
        $account = OfficialAccount::where('owner_id', auth()->id())->findOrFail($accountId);

        $userIds = OaMessage::where('account_id', $accountId)
            ->select('user_id')
            ->distinct()
            ->pluck('user_id');

        $users = User::whereIn('id', $userIds)->get()->keyBy('id');

        $conversations = [];
        foreach ($userIds as $uid) {
            $lastMsg = OaMessage::where('account_id', $accountId)
                ->where('user_id', $uid)
                ->latest()
                ->first();

            $unread = OaMessage::where('account_id', $accountId)
                ->where('user_id', $uid)
                ->where('direction', 'in')
                ->where('is_read', false)
                ->count();

            $user = $users->get($uid);
            $conversations[] = [
                'user_id' => $uid,
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'avatar' => $user->avatar
                        ? (str_starts_with($user->avatar, 'http') ? $user->avatar : asset('storage/' . $user->avatar))
                        : null,
                ] : null,
                'last_message' => $lastMsg?->content,
                'last_time' => $lastMsg?->created_at,
                'unread_count' => $unread,
                'direction' => $lastMsg?->direction,
            ];
        }

        // 按最后消息时间降序
        usort($conversations, fn($a, $b) => strtotime($b['last_time'] ?? '0') - strtotime($a['last_time'] ?? '0'));

        return ApiResponse::success($conversations);
    }

    public function conversationMessages(int $accountId, int $userId): JsonResponse
    {
        $account = OfficialAccount::where('owner_id', auth()->id())->findOrFail($accountId);

        // 标记为已读
        OaMessage::where('account_id', $accountId)
            ->where('user_id', $userId)
            ->where('direction', 'in')
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $messages = OaMessage::where('account_id', $accountId)
            ->where('user_id', $userId)
            ->with('user:id,name,avatar')
            ->orderBy('created_at')
            ->get()
            ->map(fn($m) => [
                'id' => $m->id,
                'direction' => $m->direction,
                'content' => $m->content,
                'content_type' => $m->content_type,
                'media_url' => $m->media_url,
                'is_read' => $m->is_read,
                'reply_to_id' => $m->reply_to_id,
                'created_at' => $m->created_at,
            ]);

        return ApiResponse::success($messages);
    }

    public function replyConversation(int $accountId, int $userId, Request $request): JsonResponse
    {
        $account = OfficialAccount::where('owner_id', auth()->id())->findOrFail($accountId);

        $validated = $request->validate([
            'content' => 'required|string|max:2000',
            'reply_to_id' => 'nullable|integer|exists:oa_messages,id',
        ]);

        $msg = OaMessage::create([
            'account_id' => $accountId,
            'user_id' => $userId,
            'direction' => 'out',
            'content' => $validated['content'],
            'reply_to_id' => $validated['reply_to_id'] ?? null,
        ]);

        return ApiResponse::success($msg, '回复已发送', 201);
    }

    public function unreadMessageCount(int $accountId): JsonResponse
    {
        $account = OfficialAccount::findOrFail($accountId);
        $isOwner = auth()->id() === $account->owner_id;

        if (!$isOwner) {
            return ApiResponse::success(['count' => 0]);
        }

        $count = OaMessage::where('account_id', $accountId)
            ->where('direction', 'in')
            ->where('is_read', false)
            ->count();

        return ApiResponse::success(['count' => $count]);
    }

    public function sendMessage(int $accountId, Request $request): JsonResponse
    {
        // 关注者发送消息给互物号（无需号主权限）
        $account = OfficialAccount::findOrFail($accountId);

        $validated = $request->validate([
            'content' => 'required_without:media_url|string|max:2000',
            'content_type' => 'nullable|string|in:text,image',
            'media_url' => 'nullable|string|max:500',
        ]);

        $msg = OaMessage::create([
            'account_id' => $accountId,
            'user_id' => auth()->id(),
            'direction' => 'in',
            'content' => $validated['content'] ?? '',
            'content_type' => $validated['content_type'] ?? 'text',
            'media_url' => $validated['media_url'] ?? null,
        ]);

        // 触发自动回复
        if (!empty($validated['content'])) {
            $autoReply = $this->getMatchingAutoReply($accountId, $validated['content']);
            if ($autoReply) {
                OaMessage::create([
                    'account_id' => $accountId,
                    'user_id' => auth()->id(),
                    'direction' => 'out',
                    'content' => $autoReply['content'],
                    'content_type' => $autoReply['content_type'],
                    'media_url' => $autoReply['media_url'] ?? null,
                    'reply_to_id' => $msg->id,
                ]);
            }
        }

        return ApiResponse::success($msg->load('user:id,name,avatar'), '消息已发送', 201);
    }

    private function getMatchingAutoReply(int $accountId, string $message): ?array
    {
        // 先匹配关键词
        $keywordReplies = OaAutoReply::where('account_id', $accountId)
            ->where('type', 'keyword')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        foreach ($keywordReplies as $reply) {
            if ($reply->match_type === 0) {
                if (mb_strtolower($message) === mb_strtolower($reply->keyword)) {
                    return [
                        'content' => $reply->content,
                        'content_type' => $reply->content_type,
                        'media_url' => $reply->media_url,
                    ];
                }
            } else {
                if (mb_strpos(mb_strtolower($message), mb_strtolower($reply->keyword)) !== false) {
                    return [
                        'content' => $reply->content,
                        'content_type' => $reply->content_type,
                        'media_url' => $reply->media_url,
                    ];
                }
            }
        }

        // 再匹配默认回复
        $defaultReply = OaAutoReply::where('account_id', $accountId)
            ->where('type', 'default')
            ->where('is_active', true)
            ->first();
        if ($defaultReply) {
            return [
                'content' => $defaultReply->content,
                'content_type' => $defaultReply->content_type,
                'media_url' => $defaultReply->media_url,
            ];
        }

        return null;
    }

    // ════════════════════════════════════════════

    public function autoReplies(int $accountId, Request $request): JsonResponse
    {
        $account = OfficialAccount::where('owner_id', auth()->id())->findOrFail($accountId);
        $type = $request->input('type'); // optional: welcome, keyword, default

        $query = OaAutoReply::where('account_id', $accountId)->orderBy('sort_order');

        if ($type) {
            $query->where('type', $type);
        }

        return ApiResponse::success($query->get());
    }

    public function createAutoReply(int $accountId, Request $request): JsonResponse
    {
        $account = OfficialAccount::where('owner_id', auth()->id())->findOrFail($accountId);

        $validated = $request->validate([
            'type' => 'required|string|in:welcome,keyword,default',
            'keyword' => 'nullable|string|max:100',
            'match_type' => 'nullable|integer|in:0,1',
            'content' => 'required|string',
            'content_type' => 'nullable|string|in:text,image,article',
            'media_url' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $validated['account_id'] = $accountId;
        $validated['match_type'] ??= 0;
        $validated['content_type'] ??= 'text';
        $validated['is_active'] ??= true;
        $validated['sort_order'] ??= 0;

        // welcome/default 类型每个账号只允许一个
        if (in_array($validated['type'], ['welcome', 'default'])) {
            $existing = OaAutoReply::where('account_id', $accountId)
                ->where('type', $validated['type'])
                ->exists();
            if ($existing) {
                return ApiResponse::error(
                    $validated['type'] === 'welcome' ? '已存在关注回复，请编辑或删除后重试' : '已存在默认回复，请编辑或删除后重试',
                    422
                );
            }
        }

        $reply = OaAutoReply::create($validated);

        return ApiResponse::success($reply, '自动回复已创建', 201);
    }

    public function updateAutoReply(int $replyId, Request $request): JsonResponse
    {
        $reply = OaAutoReply::whereHas('account', fn($q) => $q->where('owner_id', auth()->id()))
            ->findOrFail($replyId);

        $validated = $request->validate([
            'type' => 'sometimes|string|in:welcome,keyword,default',
            'keyword' => 'nullable|string|max:100',
            'match_type' => 'nullable|integer|in:0,1',
            'content' => 'sometimes|string',
            'content_type' => 'nullable|string|in:text,image,article',
            'media_url' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $reply->update($validated);

        return ApiResponse::success($reply->fresh(), '自动回复已更新');
    }

    public function deleteAutoReply(int $replyId): JsonResponse
    {
        $reply = OaAutoReply::whereHas('account', fn($q) => $q->where('owner_id', auth()->id()))
            ->findOrFail($replyId);
        $reply->delete();

        return ApiResponse::success(null, '自动回复已删除');
    }

    // ════════════════════════════════════════════
    // 自动回复触发 (无需认证 - 用于消息系统)
    // ════════════════════════════════════════════

    public function triggerAutoReply(int $accountId, Request $request): JsonResponse
    {
        $account = OfficialAccount::findOrFail($accountId);
        $message = $request->input('content', '');

        // 1. 先尝试关键词匹配
        if (!empty($message)) {
            $keywordReplies = OaAutoReply::where('account_id', $accountId)
                ->where('type', 'keyword')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();

            foreach ($keywordReplies as $reply) {
                if ($reply->match_type === 0) {
                    // 精确匹配
                    if (mb_strtolower($message) === mb_strtolower($reply->keyword)) {
                        return ApiResponse::success($this->formatAutoReply($reply));
                    }
                } else {
                    // 模糊匹配
                    if (mb_strpos(mb_strtolower($message), mb_strtolower($reply->keyword)) !== false) {
                        return ApiResponse::success($this->formatAutoReply($reply));
                    }
                }
            }

            // 2. 没有关键词匹配，尝试默认回复
            $defaultReply = OaAutoReply::where('account_id', $accountId)
                ->where('type', 'default')
                ->where('is_active', true)
                ->first();
            if ($defaultReply) {
                return ApiResponse::success($this->formatAutoReply($defaultReply));
            }
        }

        // 3. 返回关注回复（作为欢迎信息）
        $welcomeReply = OaAutoReply::where('account_id', $accountId)
            ->where('type', 'welcome')
            ->where('is_active', true)
            ->first();

        if ($welcomeReply) {
            return ApiResponse::success($this->formatAutoReply($welcomeReply));
        }

        return ApiResponse::success(null);
    }

    private function formatAutoReply(OaAutoReply $reply): array
    {
        return [
            'id' => $reply->id,
            'type' => $reply->type,
            'content' => $reply->content,
            'content_type' => $reply->content_type,
            'media_url' => $reply->media_url,
        ];
    }

    // ── 文章排行榜 ──
    public function ranking(Request $request): JsonResponse
    {
        $period = $request->input('period', 'week'); // week | month
        $accountId = $request->input('account_id');
        $sort = $request->input('sort', 'reads'); // reads | likes

        $query = OaArticle::where('status', 'published')
            ->with('account:id,name,avatar')
            ->withCount(['likes', 'reads']);

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        if ($period === 'week') {
            $query->where('published_at', '>=', now()->subDays(7));
        } elseif ($period === 'month') {
            $query->where('published_at', '>=', now()->subDays(30));
        }

        if ($sort === 'likes') {
            $query->orderBy('likes_count', 'desc');
        } else {
            $query->orderBy('reads_count', 'desc');
        }

        $articles = $query->limit(10)->get();

        return ApiResponse::success($articles->map(fn($a, $i) => [
            'rank' => $i + 1,
            'id' => $a->id,
            'title' => $a->title,
            'summary' => $a->summary,
            'cover_image' => $this->normalizeOaUrl($a->cover_image),
            'reads_count' => $a->reads_count,
            'likes_count' => $a->likes_count,
            'published_at' => $a->published_at,
            'account' => $a->account ? ['id' => $a->account->id, 'name' => $a->account->name, 'avatar' => $a->account->avatar ? (str_starts_with($a->account->avatar, 'http') ? $a->account->avatar : asset('storage/' . $a->account->avatar)) : null] : null,
        ]));
    }

    // ── 推荐互物号（按关注数排序） ──
    public function recommendedChannels(): JsonResponse
    {
        $myId = auth()->id();

        $channels = OfficialAccount::query()
            ->withCount('followers')
            ->withCount(['articles' => fn($q) => $q->where('status', 'published')])
            ->orderBy('followers_count', 'desc')
            ->limit(6)
            ->get()
            ->map(fn($ch) => [
                'id' => $ch->id,
                'name' => $ch->name,
                'avatar' => $ch->avatar ? (str_starts_with($ch->avatar, 'http') ? $ch->avatar : asset('storage/' . $ch->avatar)) : null,
                'description' => $ch->description,
                'category' => $ch->category?->name,
                'is_verified' => (bool)$ch->is_verified,
                'verified_info' => $ch->is_verified ? ($ch->settings['verified_info'] ?? null) : null,
                'followers_count' => $ch->followers_count,
                'articles_count' => $ch->articles_count,
                'is_following' => $myId ? \App\Models\Follow::where('user_id', $myId)
                    ->where('followable_type', 'App\\Models\\OfficialAccount')
                    ->where('followable_id', $ch->id)->exists() : false,
            ]);

        return ApiResponse::success($channels);
    }

    // ── 热门标签 ──
    public function popularTags(): JsonResponse
    {
        $articles = OaArticle::where('status', 'published')
            ->whereNotNull('tags')
            ->select('tags')
            ->limit(200)
            ->get();

        $tagCounts = [];
        foreach ($articles as $a) {
            if ($a->tags && is_array($a->tags)) {
                foreach ($a->tags as $tag) {
                    $tagCounts[$tag] = ($tagCounts[$tag] ?? 0) + 1;
                }
            }
        }
        arsort($tagCounts);
        $tags = array_slice($tagCounts, 0, 20);

        return ApiResponse::success(array_map(fn($count, $name) => [
            'name' => $name,
            'count' => $count,
        ], $tags, array_keys($tags)));
    }

    // ── 文章内容合规预检 ──
    public function scanContent(Request $request): JsonResponse
    {
        $request->validate([
            'title'   => 'nullable|string|max:500',
            'content' => 'nullable|string',
        ]);

        $text = ($request->input('title') ?? '') . "\n" . strip_tags($request->input('content') ?? '');
        $text = mb_substr($text, 0, 50000);

        $result = app(SensitiveWordService::class)->check($text);

        return ApiResponse::success([
            'hasSensitive' => $result['hasSensitive'],
            'matched'      => $result['matched'],
            'totalChecked' => mb_strlen($text),
        ]);
    }

    // ── 个性化推荐 ──
    public function recommendations(): JsonResponse
    {
        $myId = auth()->id();

        // 获取用户最近阅读的50篇文章的标签
        $recentTagCounts = [];
        $recentReads = OaArticleRead::where('user_id', $myId)
            ->whereHas('article', fn($q) => $q->where('status', 'published'))
            ->with('article:id,tags')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        foreach ($recentReads as $read) {
            if ($read->article && $read->article->tags) {
                foreach ($read->article->tags as $tag) {
                    $recentTagCounts[$tag] = ($recentTagCounts[$tag] ?? 0) + 1;
                }
            }
        }

        // 按标签频率排序，取前10个标签
        arsort($recentTagCounts);
        $topTags = array_keys(array_slice($recentTagCounts, 0, 10));

        if (empty($topTags)) {
            // 没有阅读记录，返回热门文章
            $articles = OaArticle::where('status', 'published')
                ->with('author:id,name,avatar')
                ->with('account:id,name,avatar')
                ->withCount(['likes', 'reads'])
                ->orderBy('reads_count', 'desc')
                ->limit(10)
                ->get();
        } else {
            // 找包含这些标签且用户未读的文章
            $readIds = OaArticleRead::where('user_id', $myId)
                ->distinct('article_id')
                ->pluck('article_id')
                ->toArray();

            $articles = OaArticle::where('status', 'published')
                ->whereNotIn('id', $readIds)
                ->with('author:id,name,avatar')
                ->with('account:id,name,avatar')
                ->withCount(['likes', 'reads'])
                ->where(function($q) use ($topTags) {
                    foreach ($topTags as $tag) {
                        $q->orWhereJsonContains('tags', $tag);
                    }
                })
                ->orderBy('reads_count', 'desc')
                ->limit(10)
                ->get();
        }

        return ApiResponse::success($articles->map(fn($a) => [
            'id' => $a->id,
            'title' => $a->title,
            'summary' => $a->summary,
            'cover_image' => $a->cover_image,
            'tags' => $a->tags,
            'reads_count' => $a->reads_count,
            'likes_count' => $a->likes_count,
            'published_at' => $a->published_at,
            'author' => $a->author ? ['id' => $a->author->id, 'name' => $a->author->name, 'avatar' => $a->author->avatar ? (str_starts_with($a->author->avatar, 'http') ? $a->author->avatar : asset('storage/' . $a->author->avatar)) : null] : null,
            'account' => $a->account ? ['id' => $a->account->id, 'name' => $a->account->name, 'avatar' => $a->account->avatar ? (str_starts_with($a->account->avatar, 'http') ? $a->account->avatar : asset('storage/' . $a->account->avatar)) : null] : null,
            'match_tags' => $topTags ? array_intersect($a->tags ?? [], $topTags) : [],
        ]));
    }

    // ── 文章合集管理 ──

    // 获取互物号的所有合集
    public function collections(int $accountId): JsonResponse
    {
        $account = OfficialAccount::findOrFail($accountId);
        $collections = OaCollection::where('account_id', $accountId)
            ->withCount('articles')
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get();

        return ApiResponse::success($collections->map(fn($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'description' => $c->description,
            'cover_image' => $c->cover_image,
            'sort_order' => $c->sort_order,
            'articles_count' => $c->articles_count,
        ]));
    }

    // 创建合集
    public function createCollection(int $accountId, Request $request): JsonResponse
    {
        $account = OfficialAccount::findOrFail($accountId);
        if ($account->owner_id !== auth()->id()) {
            return ApiResponse::error('FORBIDDEN', '只有号主才能创建合集', 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'cover_image' => 'nullable|string|max:500',
        ]);

        $maxSort = OaCollection::where('account_id', $accountId)->max('sort_order') ?? 0;

        $collection = OaCollection::create([
            'account_id' => $accountId,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'cover_image' => $validated['cover_image'] ?? null,
            'sort_order' => $maxSort + 1,
        ]);

        return ApiResponse::success([
            'id' => $collection->id,
            'name' => $collection->name,
        ], '合集已创建');
    }

    // 更新合集
    public function updateCollection(int $id, Request $request): JsonResponse
    {
        $collection = OaCollection::with('account')->findOrFail($id);
        if ($collection->account->owner_id !== auth()->id()) {
            return ApiResponse::error('FORBIDDEN', '无权限', 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'description' => 'nullable|string|max:500',
            'cover_image' => 'nullable|string|max:500',
        ]);

        $collection->update($validated);
        return ApiResponse::success($collection, '已更新');
    }

    // 删除合集（文章保留，collection_id 置空）
    public function deleteCollection(int $id): JsonResponse
    {
        $collection = OaCollection::with('account')->findOrFail($id);
        if ($collection->account->owner_id !== auth()->id()) {
            return ApiResponse::error('FORBIDDEN', '无权限', 403);
        }

        OaArticle::where('collection_id', $id)->update(['collection_id' => null]);
        $collection->delete();
        return ApiResponse::success(null, '合集已删除');
    }

    // 将文章移入/移出合集
    public function setArticleCollection(int $articleId, Request $request): JsonResponse
    {
        $article = OaArticle::with('account')->findOrFail($articleId);
        if ($article->account->owner_id !== auth()->id()) {
            return ApiResponse::error('FORBIDDEN', '只有号主才能操作', 403);
        }

        $validated = $request->validate([
            'collection_id' => 'nullable|integer|exists:oa_collections,id',
        ]);

        $article->update(['collection_id' => $validated['collection_id'] ?? null]);

        return ApiResponse::success([
            'collection_id' => $article->collection_id,
        ], '已更新');
    }

    // ── 阅读清单 ──

    // 获取我的阅读清单
    public function myReadingList(): JsonResponse
    {
        $myId = auth()->id();
        $items = OaReadingListItem::where('user_id', $myId)
            ->with(['article' => fn($q) => $q->with('author:id,name,avatar')->with('account:id,name')])
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->paginate(20);

        $items->getCollection()->transform(fn($item) => [
            'id' => $item->id,
            'article_id' => $item->article_id,
            'notes' => $item->notes,
            'sort_order' => $item->sort_order,
            'created_at' => $item->created_at,
            'article' => $item->article ? [
                'id' => $item->article->id,
                'title' => $item->article->title,
                'summary' => $item->article->summary,
                'cover_image' => $item->article->cover_image,
                'author' => $item->article->author ? ['id' => $item->article->author->id, 'name' => $item->article->author->name] : null,
                'account' => $item->article->account ? ['id' => $item->article->account->id, 'name' => $item->article->account->name] : null,
            ] : null,
        ]);

        return ApiResponse::paginated($items);
    }

    // 添加到阅读清单
    public function addToReadingList(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'article_id' => 'required|integer|exists:oa_articles,id',
            'notes' => 'nullable|string|max:500',
        ]);

        $myId = auth()->id();

        if (OaReadingListItem::where('user_id', $myId)->where('article_id', $validated['article_id'])->exists()) {
            return ApiResponse::error('ALREADY_EXISTS', '已在阅读清单中');
        }

        $maxSort = OaReadingListItem::where('user_id', $myId)->max('sort_order') ?? 0;

        $item = OaReadingListItem::create([
            'user_id' => $myId,
            'article_id' => $validated['article_id'],
            'notes' => $validated['notes'] ?? null,
            'sort_order' => $maxSort + 1,
        ]);

        return ApiResponse::success(['id' => $item->id], '已添加到阅读清单');
    }

    // 从阅读清单移除
    public function removeFromReadingList(int $articleId): JsonResponse
    {
        OaReadingListItem::where('user_id', auth()->id())
            ->where('article_id', $articleId)
            ->delete();

        return ApiResponse::success(null, '已从阅读清单移除');
    }

    // 检查文章是否在阅读清单中
    public function readingListStatus(int $articleId): JsonResponse
    {
        $exists = OaReadingListItem::where('user_id', auth()->id())
            ->where('article_id', $articleId)
            ->exists();

        return ApiResponse::success(['in_list' => $exists]);
    }

    // 更新笔记
    public function updateReadingListItem(int $id, Request $request): JsonResponse
    {
        $item = OaReadingListItem::where('user_id', auth()->id())->findOrFail($id);
        $item->update($request->only(['notes', 'sort_order']));
        return ApiResponse::success($item, '已更新');
    }

    // ════════════════════════════════════════════
    // 付费文章
    // ════════════════════════════════════════════

    // 购买/解锁付费文章
    public function purchaseArticle(int $articleId): JsonResponse
    {
        $article = OaArticle::where('status', 'published')->findOrFail($articleId);
        if (!$article->is_paid) {
            return ApiResponse::error('NOT_PAID_ARTICLE', '该文章无需付费', 400);
        }

        $userId = auth()->id();

        // 已经购买过
        if ($article->isPurchasedBy($userId)) {
            return ApiResponse::success(null, '已解锁');
        }

        // 号主免费
        if ($article->account && $article->account->owner_id === $userId) {
            OaArticlePurchase::create([
                'article_id' => $articleId,
                'user_id'    => $userId,
                'price'      => $article->price,
                'price_type' => $article->price_type,
                'status'     => 'completed',
            ]);
            return ApiResponse::success(null, '已解锁（号主免费）');
        }

        $price = (float) $article->price;
        if ($price <= 0) {
            return ApiResponse::error('INVALID_PRICE', '价格无效', 400);
        }

        if ($article->price_type === 'points') {
            // 积分支付
            $spent = UserPoint::spend($userId, $price, "付费解锁文章: {$article->title}");
            if (!$spent) {
                return ApiResponse::error('积分余额不足', 400);
            }

            // 给文章作者增加积分（扣除平台手续费10%）
            $authorId = $article->author_id;
            if ($authorId && $authorId !== $userId) {
                $authorPoints = (int) floor($price * 0.9);
                if ($authorPoints > 0) {
                    UserPoint::earn($authorId, $authorPoints, "文章被打赏: {$article->title}");
                }
            }

            OaArticlePurchase::create([
                'article_id' => $articleId,
                'user_id'    => $userId,
                'price'      => $price,
                'price_type' => 'points',
                'status'     => 'completed',
            ]);

            return ApiResponse::success(null, '🎉 已解锁，积分已扣除');
        }

        // 金额支付（返回支付链接，前端跳转）
        if ($article->price_type === 'money') {
            // 检查作者是否有收益账户，没有则自动创建
            $authorId = $article->author_id;
            if ($authorId) {
                EarningsAccount::firstOrCreate(
                    ['user_id' => $authorId, 'type' => 'agent'],
                    ['pending_balance' => 0, 'available_balance' => 0, 'total_withdrawn' => 0, 'status' => 'active']
                );
            }

            $platformFee = round($price * 0.1, 2);
            $netAmount = round($price - $platformFee, 2);

            // 记录购买（状态 pending，待支付确认后改为 completed）
            $purchase = OaArticlePurchase::create([
                'article_id' => $articleId,
                'user_id'    => $userId,
                'price'      => $price,
                'price_type' => 'money',
                'status'     => 'pending',
            ]);

            // 记录待结算收益
            OaArticleEarning::create([
                'article_id'     => $articleId,
                'buyer_id'       => $userId,
                'author_id'      => $authorId,
                'price'          => $price,
                'price_type'     => 'money',
                'platform_fee'   => $platformFee,
                'net_amount'     => $netAmount,
                'status'         => 'pending',
                'purchase_table' => 'oa_article_purchases',
                'purchase_id'    => $purchase->id,
            ]);

            // 生成支付单号（对接支付网关时使用）
            $tradeNo = 'OA' . date('YmdHis') . str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);

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

    // 检查当前用户是否已购买文章
    public function articlePurchaseStatus(int $articleId): JsonResponse
    {
        $article = OaArticle::findOrFail($articleId);
        $userId = auth()->id();

        if (!$article->is_paid) {
            return ApiResponse::success(['is_paid' => false, 'purchased' => null]);
        }

        $purchased = $article->isPurchasedBy($userId);
        $isOwner = $article->account && $article->account->owner_id === $userId;

        return ApiResponse::success([
            'is_paid'     => true,
            'purchased'   => $purchased || $isOwner,
            'price'       => (float) $article->price,
            'price_type'  => $article->price_type,
            'balance'     => $userId ? (float) optional(UserPoint::where('user_id', $userId)->first())->balance : 0,
        ]);
    }

    // ── 作者收益查询 ──
    public function myArticleEarnings(): JsonResponse
    {
        $userId = auth()->id();

        $stats = [
            'total_points'   => (float) OaArticleEarning::where('author_id', $userId)
                ->where('price_type', 'points')->sum('net_amount'),
            'total_money'    => (float) OaArticleEarning::where('author_id', $userId)
                ->where('price_type', 'money')->where('status', 'settled')->sum('net_amount'),
            'pending_money'  => (float) OaArticleEarning::where('author_id', $userId)
                ->where('price_type', 'money')->where('status', 'pending')->sum('net_amount'),
            'purchase_count' => OaArticleEarning::where('author_id', $userId)->count(),
        ];

        $earningsAccount = EarningsAccount::where('user_id', $userId)
            ->where('type', 'agent')->first()
            ?? EarningsAccount::where('user_id', $userId)
                ->where('type', 'oa_article')->first();
        $stats['earnings_account'] = $earningsAccount ? [
            'available_balance' => (float) $earningsAccount->available_balance,
            'pending_balance'   => (float) $earningsAccount->pending_balance,
        ] : null;

        $recent = OaArticleEarning::where('author_id', $userId)
            ->with('article:id,title')->with('buyer:id,name')
            ->latest()->take(20)->get()
            ->map(fn($e) => [
                'id'            => $e->id,
                'article_title' => $e->article?->title,
                'buyer_name'    => $e->buyer?->name,
                'price'         => (float) $e->price,
                'net_amount'    => (float) $e->net_amount,
                'price_type'    => $e->price_type,
                'status'        => $e->status,
                'created_at'    => $e->created_at,
            ]);

        return ApiResponse::success(['stats' => $stats, 'recent' => $recent]);
    }

    // ── 作者申请提现 ──
    public function requestEarningsWithdrawal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount'           => 'required|numeric|min:1|max:999999',
            'channel'          => 'required|string|in:bank,alipay,wechat',
            'channel_account'  => 'nullable|string|max:200',
            'bank_name'        => 'nullable|string|max:100',
            'bank_account_name' => 'nullable|string|max:100',
            'bank_account_no'  => 'nullable|string|max:50',
            'alipay_account'   => 'nullable|string|max:100',
            'wechat_account'   => 'nullable|string|max:100',
        ]);

        $userId = auth()->id();
        $earningsAccount = EarningsAccount::where('user_id', $userId)
            ->where('type', 'agent')->first()
            ?? EarningsAccount::where('user_id', $userId)
                ->where('type', 'oa_article')->first();

        if (!$earningsAccount || $earningsAccount->available_balance < $validated['amount']) {
            return ApiResponse::error('可提现余额不足', 400);
        }

        $fee = round($validated['amount'] * 0.01, 2);
        $netAmount = round($validated['amount'] - $fee, 2);

        $withdrawal = \App\Models\Withdrawal::create([
            'earnings_account_id' => $earningsAccount->id,
            'user_id'             => $userId,
            'amount'              => $validated['amount'],
            'fee'                 => $fee,
            'net_amount'          => $netAmount,
            'channel'             => $validated['channel'],
            'channel_account'     => $validated['channel_account'] ?? null,
            'bank_name'           => $validated['bank_name'] ?? null,
            'bank_account_name'   => $validated['bank_account_name'] ?? null,
            'bank_account_no'     => $validated['bank_account_no'] ?? null,
            'alipay_account'      => $validated['alipay_account'] ?? null,
            'wechat_account'      => $validated['wechat_account'] ?? null,
            'status'              => 'pending_review',
        ]);

        $earningsAccount->decrement('available_balance', $validated['amount']);
        $earningsAccount->increment('frozen_amount', $validated['amount']);

        return ApiResponse::success($withdrawal, '提现申请已提交，等待审核');
    }

    // ── 我的提现记录 ──
    public function myEarningsWithdrawals(): JsonResponse
    {
        $earningsAccount = EarningsAccount::where('user_id', auth()->id())
            ->where('type', 'agent')->first()
            ?? EarningsAccount::where('user_id', auth()->id())
                ->where('type', 'oa_article')->first();
        if (!$earningsAccount) {
            return ApiResponse::success(['data' => []]);
        }
        $withdrawals = \App\Models\Withdrawal::where('earnings_account_id', $earningsAccount->id)
            ->latest()->paginate(20);
        return ApiResponse::paginated($withdrawals);
    }

    // ════════════════════════════════════════════
    // 文章投票/问卷
    // ════════════════════════════════════════════

    // 创建文章投票
    public function createArticlePoll(int $articleId, Request $request): JsonResponse
    {
        $article = OaArticle::whereHas('account', fn($q) => $q->where('owner_id', auth()->id()))
            ->findOrFail($articleId);

        $validated = $request->validate([
            'question'     => 'required|string|max:500',
            'type'         => 'required|in:single,multiple',
            'options'      => 'required|array|min:2|max:20',
            'options.*'    => 'required|string|max:200',
            'max_choices'  => 'nullable|integer|min:1|max:20',
            'is_hide_results' => 'nullable|boolean',
        ]);

        $poll = Poll::create([
            'oa_article_id'   => $articleId,
            'creator_id'      => auth()->id(),
            'question'        => $validated['question'],
            'type'            => $validated['type'],
            'max_choices'     => $validated['max_choices'] ?? 1,
            'is_hide_results' => $validated['is_hide_results'] ?? false,
        ]);

        foreach ($validated['options'] as $i => $label) {
            PollOption::create([
                'poll_id'    => $poll->id,
                'label'      => $label,
                'sort_order' => $i,
            ]);
        }

        return ApiResponse::success(
            $poll->load('options'),
            '投票已创建',
            201
        );
    }

    // 获取文章的所有投票
    public function articlePolls(int $articleId): JsonResponse
    {
        OaArticle::findOrFail($articleId);

        $polls = Poll::where('oa_article_id', $articleId)
            ->with(['options' => fn($q) => $q->withCount('votes')->orderBy('sort_order')])
            ->orderBy('id')
            ->get()
            ->map(function ($poll) {
                $totalVotes = $poll->options->sum('votes_count');
                $myVote = auth()->id()
                    ? PollVote::where('poll_id', $poll->id)
                        ->where('user_id', auth()->id())
                        ->pluck('option_id')
                        ->toArray()
                    : [];

                return [
                    'id'           => $poll->id,
                    'question'     => $poll->question,
                    'type'         => $poll->type,
                    'max_choices'  => $poll->max_choices,
                    'is_hide_results' => (bool) $poll->is_hide_results,
                    'is_closed'    => (bool) $poll->is_closed,
                    'my_votes'     => $myVote,
                    'total_votes'  => $totalVotes,
                    'options'      => $poll->options->map(fn($o) => [
                        'id'         => $o->id,
                        'label'      => $o->label,
                        'votes_count' => $o->votes_count,
                        'percentage' => $totalVotes > 0 ? round(($o->votes_count / $totalVotes) * 100, 1) : 0,
                    ]),
                    'created_at'   => $poll->created_at,
                ];
            });

        return ApiResponse::success($polls);
    }

    // 投票
    public function votePoll(int $pollId, Request $request): JsonResponse
    {
        $poll = Poll::findOrFail($pollId);
        if ($poll->is_closed) {
            return ApiResponse::error('POLL_CLOSED', '投票已结束', 400);
        }

        $validated = $request->validate([
            'option_ids' => 'required|array|min:1',
            'option_ids.*' => 'integer|exists:poll_options,id',
        ]);

        $userId = auth()->id();

        // 检查选项是否属于该投票
        $validOptionIds = PollOption::where('poll_id', $pollId)->pluck('id')->toArray();
        $submittedIds = $validated['option_ids'];
        foreach ($submittedIds as $oid) {
            if (!in_array($oid, $validOptionIds)) {
                return ApiResponse::error('INVALID_OPTION', '无效的投票选项', 400);
            }
        }

        // 检查最大选择数
        if ($poll->type === 'single' && count($submittedIds) > 1) {
            return ApiResponse::error('单选投票只能选择一个选项', 400);
        }
        if (count($submittedIds) > ($poll->max_choices ?? 1)) {
            return ApiResponse::error('最多选择 ' . ($poll->max_choices ?? 1) . ' 项', 400);
        }

        // 清除旧投票
        PollVote::where('poll_id', $pollId)->where('user_id', $userId)->delete();

        // 记录新投票
        foreach ($submittedIds as $oid) {
            PollVote::create([
                'poll_id'   => $pollId,
                'option_id' => $oid,
                'user_id'   => $userId,
            ]);
        }

        // 返回最新结果
        $polls = $this->articlePolls($poll->oa_article_id);
        // 从原始响应中提取数据
        $data = $polls->getData();
        $targetPoll = collect($data->data)->firstWhere('id', $pollId);

        return ApiResponse::success($targetPoll, '投票成功');
    }

    // ════════════════════════════════════════════
    // 跨平台分发
    // ════════════════════════════════════════════

    // 获取互物号的平台账号列表
    public function platformAccounts(int $accountId): JsonResponse
    {
        $account = OfficialAccount::where('owner_id', auth()->id())->findOrFail($accountId);
        $accounts = OaPlatformAccount::where('account_id', $accountId)->where('is_active', true)->get();
        return ApiResponse::success($accounts);
    }

    // 绑定平台账号
    public function storePlatformAccount(int $accountId, Request $request): JsonResponse
    {
        $account = OfficialAccount::where('owner_id', auth()->id())->findOrFail($accountId);
        $validated = $request->validate([
            'platform' => 'required|string|in:wechat_mp,weibo,zhihu,toutiao,other|max:50',
            'label' => 'nullable|string|max:100',
            'app_id' => 'nullable|string|max:100',
            'app_secret' => 'nullable|string|max:500',
            'platform_user_id' => 'nullable|string|max:100',
            'platform_user_name' => 'nullable|string|max:100',
        ]);
        $validated['account_id'] = $accountId;
        $platformAccount = OaPlatformAccount::create($validated);
        return ApiResponse::success($platformAccount, '平台账号已绑定', 201);
    }

    // 更新平台账号
    public function updatePlatformAccount(int $platformId, Request $request): JsonResponse
    {
        $pa = OaPlatformAccount::whereHas('account', fn($q) => $q->where('owner_id', auth()->id()))->findOrFail($platformId);
        $pa->update($request->only(['label', 'app_id', 'app_secret', 'platform_user_id', 'platform_user_name', 'is_active']));
        return ApiResponse::success($pa->fresh(), '已更新');
    }

    // 删除平台账号
    public function deletePlatformAccount(int $platformId): JsonResponse
    {
        $pa = OaPlatformAccount::whereHas('account', fn($q) => $q->where('owner_id', auth()->id()))->findOrFail($platformId);
        $pa->distributions()->delete();
        $pa->delete();
        return ApiResponse::success(null, '平台账号已删除');
    }

    // 分发文章到指定平台
    public function distributeArticle(int $articleId, Request $request): JsonResponse
    {
        $article = OaArticle::whereHas('account', fn($q) => $q->where('owner_id', auth()->id()))->findOrFail($articleId);
        $validated = $request->validate([
            'platform_account_id' => 'required|integer|exists:oa_platform_accounts,id',
            'platform' => 'required|string|max:50',
        ]);
        $pa = OaPlatformAccount::where('id', $validated['platform_account_id'])
            ->where('account_id', $article->account_id)->firstOrFail();

        // 创建分发记录
        $dist = OaArticleDistribution::create([
            'article_id' => $articleId,
            'platform_account_id' => $pa->id,
            'platform' => $validated['platform'],
            'status' => 'pending',
        ]);

        // 模拟分发（实际需对接各平台 API）
        try {
            // TODO: 对接各平台 API 进行实际分发
            // 微信: 使用公众号素材接口
            // 微博: 使用微博内容发布接口
            $dist->update([
                'status' => 'success',
                'external_id' => 'mock_' . $dist->id,
                'external_url' => url('/oa-article/' . $articleId),
                'published_at' => now(),
            ]);
            return ApiResponse::success($dist->fresh(), '已分发到 ' . $pa->platform_user_name ?: $validated['platform']);
        } catch (\Exception $e) {
            $dist->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            return ApiResponse::error('DISTRIBUTE_FAILED', '分发失败: ' . $e->getMessage(), 500);
        }
    }

    // 获取文章分发记录
    public function articleDistributions(int $articleId): JsonResponse
    {
        $article = OaArticle::whereHas('account', fn($q) => $q->where('owner_id', auth()->id()))->findOrFail($articleId);
        $dists = OaArticleDistribution::where('article_id', $articleId)
            ->with('platformAccount:id,platform,platform_user_name,platform_avatar,label')
            ->latest()->get();
        return ApiResponse::success($dists);
    }

    // ════════════════════════════════════════════
    // 粉丝标签系统
    // ════════════════════════════════════════════

    // 获取账号的所有标签
    public function followerTags(int $accountId): JsonResponse
    {
        $account = OfficialAccount::where('owner_id', auth()->id())->findOrFail($accountId);
        $tags = OaFollowerTag::where('account_id', $accountId)
            ->withCount('relations')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        return ApiResponse::success($tags);
    }

    // 创建标签
    public function createFollowerTag(int $accountId, Request $request): JsonResponse
    {
        $account = OfficialAccount::where('owner_id', auth()->id())->findOrFail($accountId);
        $validated = $request->validate([
            'name'  => 'required|string|max:50',
            'color' => 'nullable|string|max:20',
        ]);
        $validated['account_id'] = $accountId;
        $validated['color'] ??= '#409eff';
        $maxSort = OaFollowerTag::where('account_id', $accountId)->max('sort_order') ?? 0;
        $validated['sort_order'] = $maxSort + 1;
        $tag = OaFollowerTag::create($validated);
        return ApiResponse::success($tag, '标签已创建', 201);
    }

    // 更新标签
    public function updateFollowerTag(int $tagId, Request $request): JsonResponse
    {
        $tag = OaFollowerTag::whereHas('account', fn($q) => $q->where('owner_id', auth()->id()))
            ->findOrFail($tagId);
        $validated = $request->validate([
            'name'       => 'sometimes|string|max:50',
            'color'      => 'nullable|string|max:20',
            'sort_order' => 'nullable|integer|min:0',
        ]);
        $tag->update($validated);
        return ApiResponse::success($tag->fresh(), '标签已更新');
    }

    // 删除标签
    public function deleteFollowerTag(int $tagId): JsonResponse
    {
        $tag = OaFollowerTag::whereHas('account', fn($q) => $q->where('owner_id', auth()->id()))
            ->findOrFail($tagId);
        OaFollowerTagRelation::where('tag_id', $tagId)->delete();
        $tag->delete();
        return ApiResponse::success(null, '标签已删除');
    }

    // 给粉丝打标签
    public function assignFollowerTags(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'follower_id' => 'required|integer|exists:follows,id',
            'tag_ids'     => 'required|array',
            'tag_ids.*'   => 'integer|exists:oa_follower_tags,id',
        ]);
        $followerId = $validated['follower_id'];
        // 验证该 follower 属于当前用户管理的账号
        $follower = \App\Models\Follow::with('followable')->findOrFail($followerId);
        if (!$follower->followable || $follower->followable->owner_id !== auth()->id()) {
            return ApiResponse::error('FORBIDDEN', '无权操作该粉丝', 403);
        }
        // 清除旧标签再重新分配
        OaFollowerTagRelation::where('follower_id', $followerId)->delete();
        $relations = [];
        foreach ($validated['tag_ids'] as $tagId) {
            $relations[] = OaFollowerTagRelation::create([
                'tag_id'      => $tagId,
                'follower_id' => $followerId,
            ]);
        }
        return ApiResponse::success($relations, '标签已更新');
    }

    // 获取粉丝的标签
    public function followerTagRelations(int $followerId): JsonResponse
    {
        $follower = \App\Models\Follow::with('followable')->findOrFail($followerId);
        if (!$follower->followable || $follower->followable->owner_id !== auth()->id()) {
            return ApiResponse::error('FORBIDDEN', '无权查看', 403);
        }
        $tagIds = OaFollowerTagRelation::where('follower_id', $followerId)
            ->pluck('tag_id');
        $tags = OaFollowerTag::whereIn('id', $tagIds)->get();
        return ApiResponse::success($tags);
    }

    // 按标签统计粉丝
    public function followerTagStats(int $accountId): JsonResponse
    {
        $account = OfficialAccount::where('owner_id', auth()->id())->findOrFail($accountId);
        $tags = OaFollowerTag::where('account_id', $accountId)
            ->withCount('relations')
            ->orderBy('relations_count', 'desc')
            ->get();
        return ApiResponse::success($tags);
    }
}
