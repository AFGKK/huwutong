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
use App\Models\UserConversation;
use App\Models\Channel;
use App\Models\ChannelMember;
use App\Events\OaArticlePublished;
use App\Events\OaSubmissionCreated;
use App\Services\AiRecommendationService;
use App\Services\BehaviorSequenceService;
use App\Services\SensitiveWordService;
use App\Services\UserChatConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OfficialAccountController extends Controller
{
    protected const OA_MORPH = 'App\\Models\\OfficialAccount';

    public function __construct(
        protected UserChatConversationService $chatConversations,
    ) {}
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
            return ApiResponse::notFound(__('app.api.oa.article_missing'));
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

    public function myOwnedAccounts(): JsonResponse
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
            $msg = $account->status === 'pending' ? __('app.api.oa.pending_review') : __('app.api.oa.disabled');
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
            return ApiResponse::error(__('app.api.oa.schedule_required'), 422);
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

        $msg = $status === 'published' ? __('app.api.oa.published') : ($status === 'scheduled' ? __('app.api.oa.scheduled') : __('app.api.oa.draft_saved'));
        return ApiResponse::success($article, $msg, 201);
    }

    public function updateArticle(int $id, Request $request): JsonResponse
    {
        $article = OaArticle::whereHas('account', fn($q) => $q->where('owner_id', auth()->id()))
            ->findOrFail($id);

        // 只能修改一次
        if ($article->edited_at) {
            return ApiResponse::error(__('app.api.oa.edit_once'), 422);
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

        return ApiResponse::success($article->fresh(), __('app.api.oa.updated'));
    }

    public function deleteArticle(int $id): JsonResponse
    {
        $article = OaArticle::whereHas('account', fn($q) => $q->where('owner_id', auth()->id()))
            ->findOrFail($id);
        $article->delete();
        return ApiResponse::success(null, __('app.api.oa.deleted'));
    }

    public function togglePinArticle(int $id): JsonResponse
    {
        $article = OaArticle::whereHas('account', fn($q) => $q->where('owner_id', auth()->id()))
            ->findOrFail($id);
        $article->update(['is_pinned' => !$article->is_pinned]);
        return ApiResponse::success(['is_pinned' => $article->fresh()->is_pinned], __('app.api.oa.updated'));
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
        if (!$userId) return ApiResponse::error(__('app.api.oa.login_required'), 401);

        $read = OaArticleRead::where('article_id', $articleId)
            ->where('user_id', $userId)
            ->latest()
            ->first();

        if (!$read) return ApiResponse::error(__('app.api.oa.read_missing'), 404);

        $update = [];
        if ($request->has('read_duration')) $update['read_duration'] = $request->input('read_duration');
        if ($request->has('scroll_depth'))  $update['scroll_depth'] = $request->input('scroll_depth');
        if ($request->has('completed'))     $update['completed'] = $request->boolean('completed');

        if (!empty($update)) {
            $read->update($update);
        }

        return ApiResponse::success(null, __('app.api.oa.recorded'));
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
                return ApiResponse::error(__('app.api.oa.menu_two_levels'), 422);
            }
        }

        // 校验：一级菜单最多3个，二级菜单每个一级最多5个
        if (empty($validated['parent_id'])) {
            $count = OaMenu::where('account_id', $accountId)->whereNull('parent_id')->count();
            if ($count >= 3) {
                return ApiResponse::error(__('app.api.oa.menu_l1_max'), 422);
            }
        } else {
            $count = OaMenu::where('parent_id', $validated['parent_id'])->count();
            if ($count >= 5) {
                return ApiResponse::error(__('app.api.oa.menu_l2_max'), 422);
            }
        }

        $validated['account_id'] = $accountId;
        $validated['type'] ??= 'click';
        $validated['is_active'] ??= true;
        $validated['sort_order'] ??= 0;

        $menu = OaMenu::create($validated);
        return ApiResponse::success($menu->load('children'), __('app.api.oa.menu_created'), 201);
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
        return ApiResponse::success($menu->fresh()->load('children'), __('app.api.oa.menu_updated'));
    }

    public function deleteMenu(int $menuId): JsonResponse
    {
        $menu = OaMenu::whereHas('account', fn($q) => $q->where('owner_id', auth()->id()))
            ->findOrFail($menuId);
        // 删除子菜单
        $menu->children()->delete();
        $menu->delete();
        return ApiResponse::success(null, __('app.api.oa.menu_deleted'));
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

        return ApiResponse::success($material, __('app.api.oa.material_created'), 201);
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

        return ApiResponse::success($material, __('app.api.oa.material_uploaded'), 201);
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
        return ApiResponse::success($material->fresh(), __('app.api.oa.updated'));
    }

    public function deleteMaterial(int $materialId): JsonResponse
    {
        $material = OaMaterial::whereHas('account', fn($q) => $q->where('owner_id', auth()->id()))
            ->findOrFail($materialId);
        $material->delete();
        return ApiResponse::success(null, __('app.api.oa.deleted'));
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

        return ApiResponse::success($msg, __('app.api.oa.reply_sent'), 201);
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

        return ApiResponse::success($msg->load('user:id,name,avatar'), __('app.api.oa.msg_sent'), 201);
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
                    $validated['type'] === 'welcome' ? __('app.api.oa.welcome_exists') : __('app.api.oa.default_exists'),
                    422
                );
            }
        }

        $reply = OaAutoReply::create($validated);

        return ApiResponse::success($reply, __('app.api.oa.auto_created'), 201);
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

        return ApiResponse::success($reply->fresh(), __('app.api.oa.auto_updated'));
    }

    public function deleteAutoReply(int $replyId): JsonResponse
    {
        $reply = OaAutoReply::whereHas('account', fn($q) => $q->where('owner_id', auth()->id()))
            ->findOrFail($replyId);
        $reply->delete();

        return ApiResponse::success(null, __('app.api.oa.auto_deleted'));
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
            return ApiResponse::error('FORBIDDEN', __('app.api.oa.collection_owner'), 403);
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
        ], __('app.api.oa.collection_created'));
    }

    // 更新合集
    public function updateCollection(int $id, Request $request): JsonResponse
    {
        $collection = OaCollection::with('account')->findOrFail($id);
        if ($collection->account->owner_id !== auth()->id()) {
            return ApiResponse::error('FORBIDDEN', __('app.api.oa.forbidden'), 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'description' => 'nullable|string|max:500',
            'cover_image' => 'nullable|string|max:500',
        ]);

        $collection->update($validated);
        return ApiResponse::success($collection, __('app.api.oa.updated'));
    }

    // 删除合集（文章保留，collection_id 置空）
    public function deleteCollection(int $id): JsonResponse
    {
        $collection = OaCollection::with('account')->findOrFail($id);
        if ($collection->account->owner_id !== auth()->id()) {
            return ApiResponse::error('FORBIDDEN', __('app.api.oa.forbidden'), 403);
        }

        OaArticle::where('collection_id', $id)->update(['collection_id' => null]);
        $collection->delete();
        return ApiResponse::success(null, __('app.api.oa.collection_deleted'));
    }

    // 将文章移入/移出合集
    public function setArticleCollection(int $articleId, Request $request): JsonResponse
    {
        $article = OaArticle::with('account')->findOrFail($articleId);
        if ($article->account->owner_id !== auth()->id()) {
            return ApiResponse::error('FORBIDDEN', __('app.api.oa.owner_only'), 403);
        }

        $validated = $request->validate([
            'collection_id' => 'nullable|integer|exists:oa_collections,id',
        ]);

        $article->update(['collection_id' => $validated['collection_id'] ?? null]);

        return ApiResponse::success([
            'collection_id' => $article->collection_id,
        ], __('app.api.oa.updated'));
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
            return ApiResponse::error('ALREADY_EXISTS', __('app.api.oa.reading_exists'));
        }

        $maxSort = OaReadingListItem::where('user_id', $myId)->max('sort_order') ?? 0;

        $item = OaReadingListItem::create([
            'user_id' => $myId,
            'article_id' => $validated['article_id'],
            'notes' => $validated['notes'] ?? null,
            'sort_order' => $maxSort + 1,
        ]);

        return ApiResponse::success(['id' => $item->id], __('app.api.oa.reading_added'));
    }

    // 从阅读清单移除
    public function removeFromReadingList(int $articleId): JsonResponse
    {
        OaReadingListItem::where('user_id', auth()->id())
            ->where('article_id', $articleId)
            ->delete();

        return ApiResponse::success(null, __('app.api.oa.reading_removed'));
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
        return ApiResponse::success($item, __('app.api.oa.updated'));
    }

    // ════════════════════════════════════════════
    // 付费文章
    // ════════════════════════════════════════════

    // 购买/解锁付费文章
    public function purchaseArticle(int $articleId): JsonResponse
    {
        $article = OaArticle::where('status', 'published')->findOrFail($articleId);
        if (!$article->is_paid) {
            return ApiResponse::error('NOT_PAID_ARTICLE', __('app.api.oa.not_paid'), 400);
        }

        $userId = auth()->id();

        // 已经购买过
        if ($article->isPurchasedBy($userId)) {
            return ApiResponse::success(null, __('app.api.oa.unlocked'));
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
            return ApiResponse::success(null, __('app.api.oa.unlocked_owner'));
        }

        $price = (float) $article->price;
        if ($price <= 0) {
            return ApiResponse::error('INVALID_PRICE', __('app.api.oa.invalid_price'), 400);
        }

        if ($article->price_type === 'points') {
            // 积分支付
            $spent = UserPoint::spend($userId, $price, __('app.api.oa.spend_unlock', ['title' => $article->title]));
            if (!$spent) {
                return ApiResponse::error(__('app.api.oa.insufficient_points'), 400);
            }

            // 给文章作者增加积分（扣除平台手续费10%）
            $authorId = $article->author_id;
            if ($authorId && $authorId !== $userId) {
                $authorPoints = (int) floor($price * 0.9);
                if ($authorPoints > 0) {
                    UserPoint::earn($authorId, $authorPoints, __('app.api.oa.earn_tip', ['title' => $article->title]));
                }
            }

            OaArticlePurchase::create([
                'article_id' => $articleId,
                'user_id'    => $userId,
                'price'      => $price,
                'price_type' => 'points',
                'status'     => 'completed',
            ]);

            return ApiResponse::success(null, __('app.api.oa.unlocked_spent'));
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
                'message'     => __('app.api.oa.order_created_pay'),
                // 接入支付网关后返回支付链接
                // 'pay_url'  => $payUrl,
            ], __('app.api.oa.order_created'));
        }

        return ApiResponse::error('UNSUPPORTED_PRICE_TYPE', __('app.api.oa.unsupported_pay'), 400);
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
            return ApiResponse::error(__('app.api.oa.withdraw_insufficient'), 400);
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

        return ApiResponse::success($withdrawal, __('app.api.oa.withdraw_submitted'));
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
            __('app.api.oa.poll_created'),
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
            return ApiResponse::error('POLL_CLOSED', __('app.api.oa.poll_ended'), 400);
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
                return ApiResponse::error('INVALID_OPTION', __('app.api.oa.poll_invalid_option'), 400);
            }
        }

        // 检查最大选择数
        if ($poll->type === 'single' && count($submittedIds) > 1) {
            return ApiResponse::error(__('app.api.oa.poll_single'), 400);
        }
        if (count($submittedIds) > ($poll->max_choices ?? 1)) {
            return ApiResponse::error(__('app.api.oa.poll_max', ['n' => $poll->max_choices ?? 1]), 400);
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

        return ApiResponse::success($targetPoll, __('app.api.oa.poll_ok'));
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
        return ApiResponse::success($platformAccount, __('app.api.oa.platform_bound'), 201);
    }

    // 更新平台账号
    public function updatePlatformAccount(int $platformId, Request $request): JsonResponse
    {
        $pa = OaPlatformAccount::whereHas('account', fn($q) => $q->where('owner_id', auth()->id()))->findOrFail($platformId);
        $pa->update($request->only(['label', 'app_id', 'app_secret', 'platform_user_id', 'platform_user_name', 'is_active']));
        return ApiResponse::success($pa->fresh(), __('app.api.oa.updated'));
    }

    // 删除平台账号
    public function deletePlatformAccount(int $platformId): JsonResponse
    {
        $pa = OaPlatformAccount::whereHas('account', fn($q) => $q->where('owner_id', auth()->id()))->findOrFail($platformId);
        $pa->distributions()->delete();
        $pa->delete();
        return ApiResponse::success(null, __('app.api.oa.platform_deleted'));
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

        // 调用平台 API 进行实际分发
        try {
            $platform = $validated['platform'];
            $result = match ($platform) {
                'wechat' => $this->distributeToWechat($article, $pa),
                'weibo' => $this->distributeToWeibo($article, $pa),
                'twitter' => $this->distributeToTwitter($article, $pa),
                default => throw new \InvalidArgumentException(__('app.api.oa.unsupported_platform', ['platform' => $platform])),
            };

            $dist->update([
                'status' => 'success',
                'external_id' => $result['external_id'] ?? ('dist_' . $dist->id),
                'external_url' => $result['external_url'] ?? '',
                'published_at' => now(),
            ]);
            return ApiResponse::success($dist->fresh(), __('app.api.oa.distributed', ['name' => $pa->platform_user_name ?: $platform]));
        } catch (\Exception $e) {
            $dist->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            Log::warning('文章分发失败', [
                'dist_id' => $dist->id,
                'platform' => $validated['platform'],
                'error' => $e->getMessage(),
            ]);
            return ApiResponse::error('DISTRIBUTE_FAILED', __('app.api.oa.distribute_failed', ['error' => $e->getMessage()]), 500);
        }
    }

    /**
     * 分发到微信公众号（素材接口）
     */
    private function distributeToWechat(OaArticle $article, OaPlatformAccount $pa): array
    {
        if (empty($pa->app_id) || empty($pa->access_token)) {
            // 尝试刷新 token
            $pa = $this->refreshWechatToken($pa);
        }

        // 构建微信图文素材
        $body = [
            'articles' => [[
                'title' => $article->title,
                'thumb_media_id' => $article->cover_image ? $this->uploadWechatImage($article->cover_image, $pa) : '',
                'author' => $article->author ?? '',
                'digest' => Str::limit(strip_tags($article->content ?? ''), 120),
                'show_cover_pic' => $article->cover_image ? 1 : 0,
                'content' => $this->buildWechatContent($article->content ?? ''),
                'content_source_url' => $article->source_url ?? '',
                'need_open_comment' => 1,
                'only_fans_can_comment' => 0,
            ]],
        ];

        $response = Http::timeout(15)
            ->post("https://api.weixin.qq.com/cgi-bin/draft/add?access_token={$pa->access_token}", $body);

        $data = $response->json();
        if (!empty($data['errcode']) && $data['errcode'] !== 0) {
            throw new \RuntimeException(__('app.api.oa.wechat_upload_fail', ['error' => $data['errmsg'] ?? 'unknown']));
        }

        return [
            'external_id' => $data['media_id'] ?? '',
            'external_url' => '',
        ];
    }

    /**
     * 分发到微博
     */
    private function distributeToWeibo(OaArticle $article, OaPlatformAccount $pa): array
    {
        if (empty($pa->access_token)) {
            throw new \RuntimeException(__('app.api.oa.weibo_unauthorized'));
        }

        // 微博文字内容（含链接）
        $shortUrl = $article->source_url ?: url('/oa-article/' . $article->id);
        $text = mb_strlen($article->title) > 100
            ? mb_substr($article->title, 0, 100) . '... ' . $shortUrl
            : $article->title . ' ' . $shortUrl;

        $response = Http::timeout(15)
            ->asForm()->post('https://api.weibo.com/2/statuses/update.json', [
                'access_token' => $pa->access_token,
                'status' => $text,
            ]);

        $data = $response->json();
        if (!empty($data['error_code'])) {
            throw new \RuntimeException(__('app.api.oa.weibo_publish_fail', ['error' => $data['error'] ?? 'unknown']));
        }

        return [
            'external_id' => (string)($data['id'] ?? ''),
            'external_url' => "https://weibo.com/{$pa->platform_user_id}/{$data['id']}" ?? '',
        ];
    }

    /**
     * 分发到 Twitter/X
     */
    private function distributeToTwitter(OaArticle $article, OaPlatformAccount $pa): array
    {
        if (empty($pa->access_token) || empty($pa->app_secret)) {
            throw new \RuntimeException(__('app.api.oa.twitter_unauthorized'));
        }

        $shortUrl = $article->source_url ?: url('/oa-article/' . $article->id);
        $text = mb_strlen($article->title) > 200
            ? mb_substr($article->title, 0, 200) . '... ' . $shortUrl
            : $article->title . ' ' . $shortUrl;

        // Twitter API v2
        $response = Http::timeout(15)
            ->withToken($pa->access_token)
            ->post('https://api.twitter.com/2/tweets', [
                'text' => $text,
            ]);

        $data = $response->json();
        if ($response->status() !== 201) {
            $errMsg = $data['detail'] ?? ($data['title'] ?? 'unknown');
            throw new \RuntimeException(__('app.api.oa.twitter_publish_fail', ['error' => $errMsg]));
        }

        return [
            'external_id' => $data['data']['id'] ?? '',
            'external_url' => "https://twitter.com/user/status/{$data['data']['id']}" ?? '',
        ];
    }

    /**
     * 刷新微信 access_token
     */
    private function refreshWechatToken(OaPlatformAccount $pa): OaPlatformAccount
    {
        if (empty($pa->app_id) || empty($pa->app_secret)) {
            throw new \RuntimeException(__('app.api.oa.wechat_creds_missing'));
        }

        $response = Http::timeout(10)->get('https://api.weixin.qq.com/cgi-bin/token', [
            'grant_type' => 'client_credential',
            'appid' => $pa->app_id,
            'secret' => $pa->app_secret,
        ]);

        $data = $response->json();
        if (empty($data['access_token'])) {
            throw new \RuntimeException(__('app.api.oa.wechat_token_fail', ['error' => $data['errmsg'] ?? 'unknown']));
        }

        $pa->update([
            'access_token' => $data['access_token'],
            'token_expires_at' => now()->addSeconds($data['expires_in'] ?? 7200),
        ]);

        return $pa->fresh();
    }

    /**
     * 上传图片到微信素材库
     */
    private function uploadWechatImage(string $imageUrl, OaPlatformAccount $pa): string
    {
        try {
            $imageContent = Http::timeout(10)->get($imageUrl)->body();
            $tempPath = tempnam(sys_get_temp_dir(), 'wx_') . '.jpg';
            file_put_contents($tempPath, $imageContent);

            $response = Http::timeout(15)->attach(
                'media', file_get_contents($tempPath), basename($tempPath)
            )->post("https://api.weixin.qq.com/cgi-bin/material/add_material?access_token={$pa->access_token}&type=image");

            @unlink($tempPath);

            $data = $response->json();
            return $data['media_id'] ?? '';
        } catch (\Throwable $e) {
            Log::warning('微信图片上传失败', ['error' => $e->getMessage()]);
            return '';
        }
    }

    /**
     * 构建微信图文内容（处理外链跳转）
     */
    private function buildWechatContent(string $html): string
    {
        // 微信不支持外链跳转，转换为纯文本链接
        $html = preg_replace_callback('/<a\s+[^>]*href="([^"]+)"[^>]*>(.*?)<\/a>/i', fn ($m) => __('app.api.oa.wechat_link_fmt', ['text' => $m[2], 'url' => $m[1]]), $html);
        // 微信不支持 iframe
        $html = preg_replace('/<iframe[^>]*><\/iframe>/i', '', $html);
        return $html;
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
        $validated['color'] ??= '#0f172a';
        $maxSort = OaFollowerTag::where('account_id', $accountId)->max('sort_order') ?? 0;
        $validated['sort_order'] = $maxSort + 1;
        $tag = OaFollowerTag::create($validated);
        return ApiResponse::success($tag, __('app.api.oa.tag_created'), 201);
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
        return ApiResponse::success($tag->fresh(), __('app.api.oa.tag_updated'));
    }

    // 删除标签
    public function deleteFollowerTag(int $tagId): JsonResponse
    {
        $tag = OaFollowerTag::whereHas('account', fn($q) => $q->where('owner_id', auth()->id()))
            ->findOrFail($tagId);
        OaFollowerTagRelation::where('tag_id', $tagId)->delete();
        $tag->delete();
        return ApiResponse::success(null, __('app.api.oa.tag_deleted'));
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
            return ApiResponse::error('FORBIDDEN', __('app.api.oa.forbidden_follower'), 403);
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
        return ApiResponse::success($relations, __('app.api.oa.tag_updated'));
    }

    // 获取粉丝的标签
    public function followerTagRelations(int $followerId): JsonResponse
    {
        $follower = \App\Models\Follow::with('followable')->findOrFail($followerId);
        if (!$follower->followable || $follower->followable->owner_id !== auth()->id()) {
            return ApiResponse::error('FORBIDDEN', __('app.api.oa.forbidden_view'), 403);
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

    // ════════════════════════════════════════════
    // 关注 / 文章互动 / 分享
    // ════════════════════════════════════════════

    public function myFollowedIds(): JsonResponse
    {
        $ids = Follow::where('user_id', auth()->id())
            ->where('followable_type', self::OA_MORPH)
            ->pluck('followable_id');

        return ApiResponse::success($ids);
    }

    public function myAccounts(): JsonResponse
    {
        $ids = Follow::where('user_id', auth()->id())
            ->where('followable_type', self::OA_MORPH)
            ->pluck('followable_id');

        $accounts = OfficialAccount::whereIn('id', $ids)
            ->where('status', 'active')
            ->orderByDesc('id')
            ->get();

        return ApiResponse::success($accounts);
    }

    public function articles(int $id, Request $request): JsonResponse
    {
        $account = OfficialAccount::where('status', 'active')->findOrFail($id);

        $query = OaArticle::where('account_id', $account->id)
            ->where('status', 'published')
            ->with('account:id,name,slug,avatar');

        if ($request->input('sort') === 'hot') {
            $query->withCount('likes')->orderByDesc('likes_count')->orderByDesc('id');
        } else {
            $query->orderByDesc('is_pinned')->orderByDesc('published_at')->orderByDesc('id');
        }

        return ApiResponse::paginated($query->paginate((int) $request->input('per_page', 20)));
    }

    public function follow(int $id): JsonResponse
    {
        $account = OfficialAccount::where('status', 'active')->findOrFail($id);
        $userId = auth()->id();

        if ((int) $account->owner_id === (int) $userId) {
            return ApiResponse::error('INVALID', __('app.api.oa.cannot_follow_self'), 422);
        }

        $existing = Follow::where('user_id', $userId)
            ->where('followable_type', self::OA_MORPH)
            ->where('followable_id', $account->id)
            ->first();

        if ($existing) {
            return ApiResponse::success(['following' => true], __('app.api.oa.already_following'));
        }

        Follow::create([
            'user_id' => $userId,
            'followable_type' => self::OA_MORPH,
            'followable_id' => $account->id,
        ]);

        return ApiResponse::success(['following' => true], __('app.api.oa.follow_ok'));
    }

    public function unfollow(int $id): JsonResponse
    {
        Follow::where('user_id', auth()->id())
            ->where('followable_type', self::OA_MORPH)
            ->where('followable_id', $id)
            ->delete();

        return ApiResponse::success(['following' => false], __('app.api.oa.unfollowed'));
    }

    public function toggleLike(int $articleId): JsonResponse
    {
        $article = OaArticle::where('status', 'published')->findOrFail($articleId);
        $userId = auth()->id();

        $existing = OaArticleLike::whereUserId($userId)->whereArticleId($article->id)->first();

        if ($existing) {
            $existing->delete();

            return ApiResponse::success(['liked' => false]);
        }

        OaArticleLike::create([
            'user_id' => $userId,
            'likeable_id' => $article->id,
        ]);

        return ApiResponse::success(['liked' => true]);
    }

    public function toggleFavorite(int $articleId): JsonResponse
    {
        $article = OaArticle::where('status', 'published')->findOrFail($articleId);
        $userId = auth()->id();

        $existing = OaFavorite::whereUserId($userId)->whereArticleId($article->id)->first();

        if ($existing) {
            $existing->delete();

            return ApiResponse::success(['favorited' => false]);
        }

        OaFavorite::create([
            'user_id' => $userId,
            'favorable_id' => $article->id,
        ]);

        return ApiResponse::success(['favorited' => true]);
    }

    public function myFavoriteArticles(Request $request): JsonResponse
    {
        $articleIds = OaFavorite::whereUserId(auth()->id())->pluck('favorable_id');

        $query = OaArticle::whereIn('id', $articleIds)
            ->where('status', 'published')
            ->with('account:id,name,slug,avatar')
            ->orderByDesc('published_at');

        return ApiResponse::paginated($query->paginate((int) $request->input('per_page', 20)));
    }

    public function myLikedArticles(Request $request): JsonResponse
    {
        $articleIds = OaArticleLike::whereUserId(auth()->id())->pluck('likeable_id');

        $query = OaArticle::whereIn('id', $articleIds)
            ->where('status', 'published')
            ->with('account:id,name,slug,avatar')
            ->orderByDesc('published_at');

        return ApiResponse::paginated($query->paginate((int) $request->input('per_page', 20)));
    }

    public function share(int $articleId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target' => 'required|in:plaza,chat,channel,copy,wechat,weibo',
            'conversation_id' => 'required_if:target,chat|integer|exists:user_conversations,id',
            'channel_id' => 'required_if:target,channel|integer|exists:channels,id',
        ]);

        $article = OaArticle::with('account:id,name,slug,avatar')->findOrFail($articleId);
        $userId = auth()->id();
        $target = $validated['target'];
        $shareUrl = url('/build/oa-article/' . $article->id);
        $shareText = ($article->account?->name ? '【' . $article->account->name . '】' : '') . $article->title;

        OaArticleShare::create([
            'article_id' => $article->id,
            'user_id' => $userId,
            'platform' => $target,
        ]);

        if ($target === 'plaza') {
            $content = __('app.api.oa.share_plaza_content', [
                'name' => $article->account?->name,
                'title' => $article->title,
                'summary' => mb_substr(strip_tags((string) $article->summary ?: $article->content), 0, 300),
            ]);

            ForumPost::create([
                'user_id' => $userId,
                'title' => mb_substr($article->title, 0, 200),
                'content' => $content,
                'images' => $article->cover_image ? [$article->cover_image] : null,
                'status' => 'published',
                'template' => 'discuss',
            ]);

            return ApiResponse::success(null, __('app.api.oa.shared_plaza'));
        }

        if ($target === 'chat') {
            $convId = (int) $validated['conversation_id'];
            $isParticipant = ConversationParticipant::where('conversation_id', $convId)
                ->where('user_id', $userId)
                ->whereNull('deleted_at')
                ->exists();

            if (! $isParticipant) {
                return ApiResponse::error('FORBIDDEN', __('app.api.oa.not_chat_participant'), 403);
            }

            $conv = UserConversation::findOrFail($convId);
            $this->chatConversations->pushTextMessage(
                $conv,
                $userId,
                __('app.api.oa.oa_article_msg', ['title' => $article->title]),
                [
                    'from_oa_article' => true,
                    'article_id' => $article->id,
                    'account_id' => $article->account_id,
                    'account_name' => $article->account?->name,
                    'share_url' => $shareUrl,
                ],
                'oa-share-' . uniqid()
            );

            return ApiResponse::success(null, __('app.api.oa.shared_chat'));
        }

        if ($target === 'channel') {
            $channelId = (int) $validated['channel_id'];
            $isMember = ChannelMember::where('channel_id', $channelId)
                ->where('user_id', $userId)
                ->exists();

            if (! $isMember) {
                return ApiResponse::error('FORBIDDEN', __('app.api.oa.not_channel_member'), 403);
            }

            ChannelMessage::create([
                'channel_id' => $channelId,
                'user_id' => $userId,
                'content' => __('app.api.oa.oa_article_channel', ['title' => $article->title, 'url' => $shareUrl]),
                'message_type' => 'text',
                'metadata' => [
                    'from_oa_article' => true,
                    'article_id' => $article->id,
                    'account_id' => $article->account_id,
                    'share_url' => $shareUrl,
                ],
            ]);

            return ApiResponse::success(null, __('app.api.oa.shared_channel'));
        }

        return ApiResponse::success([
            'share_text' => $shareText,
            'share_url' => $shareUrl,
        ]);
    }

    public function articleCommentsPublic(int $id): JsonResponse
    {
        $article = OaArticle::where('status', 'published')->findOrFail($id);

        $comments = OaComment::with(['user:id,name,avatar,region', 'replies.user:id,name,avatar,region'])
            ->where('article_id', $id)
            ->whereNull('parent_id')
            ->where('status', 'approved')
            ->orderBy('is_pinned', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(20);

        // 处理头像URL
        $comments->getCollection()->transform(function ($c) {
            $c->user && $c->user->avatar && $c->user->avatar = (str_starts_with($c->user->avatar, 'http') ? $c->user->avatar : asset('storage/' . $c->user->avatar));
            $c->replies && $c->replies->each(fn($r) => $r->user && $r->user->avatar && $r->user->avatar = (str_starts_with($r->user->avatar, 'http') ? $r->user->avatar : asset('storage/' . $r->user->avatar)));
            $c->likes_count = $c->likes()->count();
            $c->is_liked = false; // 游客无点赞状态
            return $c;
        });

        return ApiResponse::paginated($comments);
    }

    public function addComment(int $articleId, Request $request): JsonResponse
    {
        $request->validate(['content' => 'required|string|max:1000', 'image' => 'nullable|string|max:500']);
        $article = OaArticle::with('account')->where('status', 'published')->findOrFail($articleId);
        // 号主评论自动通过，其余需要审核
        $isOwner = $article->account->owner_id === auth()->id();
        $comment = OaComment::create([
            'article_id' => $articleId,
            'user_id' => auth()->id(),
            'content' => $request->input('content'),
            'image' => $request->input('image'),
            'status' => $isOwner ? 'approved' : 'pending',
        ]);
        return ApiResponse::success($comment->load('user:id,name,avatar'), $isOwner ? '评论成功' : '评论已提交，等待审核', 201);
    }

    public function toggleCommentLike(int $commentId): JsonResponse
    {
        $comment = OaComment::findOrFail($commentId);
        $myId = auth()->id();
        $existing = OaCommentLike::where('comment_id', $commentId)->where('user_id', $myId)->first();
        if ($existing) {
            $existing->delete();
            return ApiResponse::success(['liked' => false, 'likes_count' => $comment->likes()->count()]);
        }
        OaCommentLike::create(['comment_id' => $commentId, 'user_id' => $myId]);
        return ApiResponse::success(['liked' => true, 'likes_count' => $comment->likes()->count()]);
    }

    public function submitArticle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account_id' => 'required|integer|exists:official_accounts,id',
            'title' => 'required|string|max:200',
            'content' => 'required|string',
            'cover_image' => 'nullable|string|max:500',
            'images' => 'nullable|array',
            'summary' => 'nullable|string|max:300',
            'is_original' => 'boolean',
            'allow_comments' => 'boolean',
            'tags' => 'nullable|array',
            'seo_title' => 'nullable|string|max:70',
            'seo_description' => 'nullable|string|max:160',
        ]);

        $submission = OaSubmission::create([
            'account_id' => $validated['account_id'],
            'user_id' => auth()->id(),
            'title' => $validated['title'],
            'content' => $validated['content'],
            'cover_image' => $validated['cover_image'] ?? null,
            'summary' => $validated['summary'] ?? null,
        ]);

        // 触发 AI 自动审核
        OaSubmissionCreated::dispatch($submission);

        return ApiResponse::success($submission, '投稿已提交，等待审核', 201);
    }

    public function mySubmissions(): JsonResponse
    {
        $submissions = OaSubmission::with('account:id,name,avatar')
            ->where('user_id', auth()->id())
            ->orderBy('id', 'desc')
            ->paginate(20);
        return ApiResponse::paginated($submissions);
    }

    public function pendingSubmissions(int $accountId): JsonResponse
    {
        $account = OfficialAccount::where('owner_id', auth()->id())->findOrFail($accountId);
        $submissions = OaSubmission::with('user:id,name,avatar')
            ->where('account_id', $accountId)
            ->where('status', 'pending')
            ->orderBy('id', 'desc')
            ->paginate(20);
        $stats = [
            'pending' => OaSubmission::where('account_id', $accountId)->where('status', 'pending')->count(),
            'approved' => OaSubmission::where('account_id', $accountId)->where('status', 'approved')->count(),
            'rejected' => OaSubmission::where('account_id', $accountId)->where('status', 'rejected')->count(),
        ];
        return ApiResponse::success([
            'submissions' => $submissions->items(),
            'stats' => $stats,
            'pagination' => [
                'current_page' => $submissions->currentPage(),
                'last_page' => $submissions->lastPage(),
                'total' => $submissions->total(),
                'per_page' => $submissions->perPage(),
            ],
        ]);
    }

    public function reviewSubmission(int $id, Request $request): JsonResponse
    {
        $submission = OaSubmission::with('account')->findOrFail($id);
        $account = $submission->account;

        if ($account->owner_id !== auth()->id()) {
            return ApiResponse::error('FORBIDDEN', '你不是该公众号的所有者', 403);
        }

        $action = $request->input('action');

        if ($action === 'approve') {
            $submission->update(['status' => 'approved', 'reviewer_id' => auth()->id(), 'reviewed_at' => now()]);

            // 创建正式文章
            $article = OaArticle::create([
                'account_id' => $submission->account_id,
                'author_id' => $submission->user_id,
                'title' => $submission->title,
                'content' => $submission->content,
                'cover_image' => $submission->cover_image,
                'summary' => $submission->summary,
                'status' => 'published',
                'source_submission_id' => $submission->id,
                'published_at' => now(),
            ]);

            // 触发 AI 自动评论
            OaArticlePublished::dispatch($article);

            return ApiResponse::success($article, '投稿已通过并发布');
        }

        if ($action === 'reject') {
            $submission->update([
                'status' => 'rejected',
                'reviewer_id' => auth()->id(),
                'reviewed_at' => now(),
                'reject_reason' => $request->input('reject_reason', '未通过审核'),
            ]);
            return ApiResponse::success($submission, '已拒绝投稿');
        }

        return ApiResponse::error('INVALID_ACTION', '无效操作', 400);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'avatar' => 'nullable',
            'cover_image' => 'nullable',
            'category_id' => 'nullable|integer|exists:oa_categories,id',
        ]);

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('oa-avatars', 'public');
            $validated['avatar'] = asset('storage/' . $path);
        } elseif (isset($validated['avatar']) && ! is_string($validated['avatar'])) {
            unset($validated['avatar']);
        }

        if ($request->hasFile('cover_image')) {
            $path = $request->file('cover_image')->store('oa-covers', 'public');
            $validated['cover_image'] = asset('storage/' . $path);
        } elseif (isset($validated['cover_image']) && ! is_string($validated['cover_image'])) {
            unset($validated['cover_image']);
        }

        $slug = Str::slug($validated['name']);
        // 中文等非 ASCII 名称 Str::slug 会得到空串，需生成可用 slug，否则唯一索引会挡住后续创建
        if ($slug === '') {
            $slug = 'oa-'.Str::lower(Str::random(10));
        }
        $baseSlug = $slug;
        $counter = 1;
        while (OfficialAccount::where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter++;
        }

        $account = OfficialAccount::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? '',
            'avatar' => $validated['avatar'] ?? null,
            'cover_image' => $validated['cover_image'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'owner_id' => auth()->id(),
            'status' => 'active',
        ]);

        // 创建者自动关注
        Follow::firstOrCreate([
            'user_id' => auth()->id(),
            'followable_type' => OfficialAccount::class,
            'followable_id' => $account->id,
        ]);

        return ApiResponse::success($account->load('category'), '公众号已创建', 201);
    }

    public function update(int $id, Request $request): JsonResponse
    {
        $account = OfficialAccount::where('owner_id', auth()->id())->findOrFail($id);

        $data = $request->only(['description', 'avatar', 'cover_image']);

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('oa-avatars', 'public');
            $data['avatar'] = asset('storage/' . $path);
        }
        if ($request->hasFile('cover_image')) {
            $path = $request->file('cover_image')->store('oa-covers', 'public');
            $data['cover_image'] = asset('storage/' . $path);
        }
        if ($request->filled('category_id')) {
            $data['category_id'] = (int) $request->input('category_id');
        }

        // 名称修改：每年最多3次
        if ($request->has('name') && $request->input('name') !== $account->name) {
            $settings = $account->settings ?? [];
            $nameUpdates = $settings['name_updates'] ?? [];
            // 过滤出本年内的修改记录
            $yearAgo = now()->subYear();
            $recentUpdates = array_filter($nameUpdates, fn($ts) => $ts >= $yearAgo->timestamp);
            if (count($recentUpdates) >= 3) {
                return ApiResponse::error('ERROR', '公众号名称每年仅能修改3次', 422);
            }
            $data['name'] = $request->input('name');
            $recentUpdates[] = now()->timestamp;
            $settings['name_updates'] = $recentUpdates;
            $data['settings'] = $settings;
        }

        $account->update($data);
        return ApiResponse::success($account->fresh()->load('category'), '已更新');
    }

    public function editInfo(int $id): JsonResponse
    {
        $account = OfficialAccount::where('owner_id', auth()->id())->with('category')->findOrFail($id);
        $settings = $account->settings ?? [];
        $nameUpdates = $settings['name_updates'] ?? [];
        $yearAgo = now()->subYear();
        $recentUpdates = array_filter($nameUpdates, fn($ts) => $ts >= $yearAgo->timestamp);
        return ApiResponse::success([
            'id' => $account->id,
            'name' => $account->name,
            'slug' => $account->slug,
            'description' => $account->description,
            'avatar' => $account->avatar,
            'cover_image' => $account->cover_image,
            'category' => $account->category,
            'category_id' => $account->category_id,
            'name_change_count' => count($recentUpdates),
            'name_change_limit' => 3,
        ]);
    }

    public function dashboard(int $id): JsonResponse
    {
        $account = OfficialAccount::withCount([
            'followers',
            'articles' => fn($q) => $q->where('status', 'published'),
        ])->findOrFail($id);

        $totalLikes = \App\Models\Like::where('likeable_type', 'App\Models\OaArticle')
            ->whereHasMorph('likeable', [\App\Models\OaArticle::class], fn($q) => $q->where('account_id', $id))->count();
        $totalReads = OaArticleRead::whereHas('article', fn($q) => $q->where('account_id', $id))->count();
        $totalShares = OaArticleShare::whereHas('article', fn($q) => $q->where('account_id', $id))->count();
        $totalComments = OaComment::whereHas('article', fn($q) => $q->where('account_id', $id))
            ->whereNull('parent_id')->count();

        $pendingSubmissions = OaSubmission::where('account_id', $id)
            ->where('status', 'pending')->count();

        $todayFollowers = \App\Models\Follow::where('followable_type', 'App\Models\OfficialAccount')
            ->where('followable_id', $id)
            ->whereDate('created_at', today())->count();

        $isOwner = auth()->id() === $account->owner_id;

        // ── 增长趋势数据 ──
        $articleIds = OaArticle::where('account_id', $id)->pluck('id');
        $days = 14;
        $followerTrend = [];
        $readTrend = [];
        $shareTrend = [];
        $likeTrend = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = today()->subDays($i);
            $dateStr = $date->format('Y-m-d');

            $followerTrend[] = [
                'date' => $dateStr,
                'count' => \App\Models\Follow::where('followable_type', 'App\Models\OfficialAccount')
                    ->where('followable_id', $id)
                    ->whereDate('created_at', $date)->count(),
                'cumulative' => \App\Models\Follow::where('followable_type', 'App\Models\OfficialAccount')
                    ->where('followable_id', $id)
                    ->whereDate('created_at', '<=', $date)->count(),
            ];

            $readTrend[] = [
                'date' => $dateStr,
                'count' => OaArticleRead::whereIn('article_id', $articleIds)
                    ->whereDate('created_at', $date)->count(),
            ];

            $shareTrend[] = [
                'date' => $dateStr,
                'count' => OaArticleShare::whereIn('article_id', $articleIds)
                    ->whereDate('created_at', $date)->count(),
            ];

            $likeTrend[] = [
                'date' => $dateStr,
                'count' => \App\Models\Like::where('likeable_type', 'App\Models\OaArticle')
                    ->whereIn('likeable_id', $articleIds)
                    ->whereDate('created_at', $date)->count(),
            ];
        }

        $yesterdayReads = $readTrend[count($readTrend) - 2]['count'] ?? 0;
        $readChangeRate = $yesterdayReads > 0
            ? round((($todayFollowers - $yesterdayReads) / $yesterdayReads) * 100, 1)
            : 0;

        return ApiResponse::success([
            'followers_count' => $account->followers_count,
            'articles_count' => $account->articles_count,
            'total_likes' => $totalLikes,
            'total_reads' => $totalReads,
            'total_shares' => $totalShares,
            'total_comments' => $totalComments,
            'pending_submissions' => $pendingSubmissions,
            'today_new_followers' => $todayFollowers,
            'yesterday_reads' => $yesterdayReads,
            'read_change_rate' => $readChangeRate,
            'trends' => [
                'followers' => $followerTrend,
                'reads' => $readTrend,
                'shares' => $shareTrend,
                'likes' => $likeTrend,
            ],
            'is_owner' => $isOwner,
        ]);
    }

    public function comments(int $id): JsonResponse
    {
        $account = OfficialAccount::findOrFail($id);
        $articleIds = OaArticle::where('account_id', $id)->pluck('id');

        $comments = OaComment::with(['user:id,name,avatar,region', 'article:id,title', 'replies.user:id,name,avatar,region'])
            ->whereIn('article_id', $articleIds)
            ->whereNull('parent_id')
            ->orderBy('id', 'desc')
            ->paginate(20)
            ->through(function ($c) {
                $c->user && $c->user->avatar && $c->user->avatar = (str_starts_with($c->user->avatar, 'http') ? $c->user->avatar : asset('storage/' . $c->user->avatar));
                $c->replies && $c->replies->each(fn($r) => $r->user && $r->user->avatar && $r->user->avatar = (str_starts_with($r->user->avatar, 'http') ? $r->user->avatar : asset('storage/' . $r->user->avatar)));
                return $c;
            });

        return ApiResponse::paginated($comments);
    }

    public function replyComment(int $commentId, Request $request): JsonResponse
    {
        $request->validate(['content' => 'required|string|max:1000']);
        $parent = OaComment::with('article')->findOrFail($commentId);
        $reply = OaComment::create([
            'article_id' => $parent->article_id,
            'user_id' => auth()->id(),
            'content' => $request->input('content'),
            'parent_id' => $parent->id,
            'status' => 'approved',
        ]);
        return ApiResponse::success($reply->load('user:id,name,avatar'), '回复成功', 201);
    }

    public function deleteComment(int $commentId): JsonResponse
    {
        $comment = OaComment::with('article')->findOrFail($commentId);
        // 号主可删除，作者可删除自己的
        $account = OfficialAccount::where('owner_id', auth()->id())
            ->where('id', $comment->article->account_id)->first();
        if (!$account && $comment->user_id !== auth()->id()) {
            return ApiResponse::error('ERROR', '无权删除', 403);
        }
        $comment->replies()->delete();
        $comment->delete();
        return ApiResponse::success(null, '已删除');
    }

    public function approveComment(int $commentId): JsonResponse
    {
        $comment = OaComment::with('article.account')->findOrFail($commentId);
        if ($comment->article->account->owner_id !== auth()->id()) {
            return ApiResponse::error('ERROR', '无权操作', 403);
        }
        $comment->update(['status' => 'approved']);
        return ApiResponse::success($comment->fresh(), '评论已通过');
    }

    public function rejectComment(int $commentId): JsonResponse
    {
        $comment = OaComment::with('article.account')->findOrFail($commentId);
        if ($comment->article->account->owner_id !== auth()->id()) {
            return ApiResponse::error('ERROR', '无权操作', 403);
        }
        $comment->update(['status' => 'rejected']);
        return ApiResponse::success($comment->fresh(), '评论已拒绝');
    }

    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|image|max:2048']);
        $path = $request->file('file')->store('oa-avatars', 'public');
        $url = asset('storage/' . $path);
        return ApiResponse::success(['url' => $url], '上传成功');
    }

    public function allCategories(): JsonResponse
    {
        $categories = OaCategory::withCount('accounts')->orderBy('sort_order')->get();
        return ApiResponse::success($categories);
    }

    public function createCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'icon' => 'nullable|string|max:50',
            'sort_order' => 'nullable|integer|min:0',
        ]);
        $cat = OaCategory::create($validated);
        return ApiResponse::success($cat, '分类已创建', 201);
    }

    public function updateCategory(int $id, Request $request): JsonResponse
    {
        $cat = OaCategory::findOrFail($id);
        $cat->update($request->only(['name', 'icon', 'sort_order', 'is_active']));
        return ApiResponse::success($cat->fresh(), '已更新');
    }

    public function deleteCategory(int $id): JsonResponse
    {
        $cat = OaCategory::findOrFail($id);
        if ($cat->accounts()->count() > 0) {
            return ApiResponse::error('ERROR', '该分类下有公众号，无法删除', 422);
        }
        $cat->delete();
        return ApiResponse::success(null, '已删除');
    }


    public function toggleArticleStatus(int $articleId): JsonResponse
    {
        $article = OaArticle::with('account')->findOrFail($articleId);
        if ($article->account->owner_id !== auth()->id()) {
            return ApiResponse::error('FORBIDDEN', '无权操作', 403);
        }

        if ($article->status === 'published') {
            $article->update(['status' => 'draft']);
            return ApiResponse::success($article->fresh(), '已下架');
        }

        $article->update([
            'status' => 'published',
            'published_at' => $article->published_at ?: now(),
        ]);

        return ApiResponse::success($article->fresh(), '已发布');
    }

    public function appeal(int $id, Request $request): JsonResponse
    {
        $account = OfficialAccount::where('owner_id', auth()->id())->findOrFail($id);
        if ($account->status !== 'suspended') {
            return ApiResponse::error('INVALID_STATUS', '仅被封禁的互物号可申诉', 422);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $settings = $account->settings ?? [];
        $settings['appeal_reason'] = $validated['reason'];
        $settings['appealed_at'] = now()->toDateTimeString();
        unset($settings['appeal_rejected_reason']);
        $account->update(['settings' => $settings]);

        return ApiResponse::success($account->fresh(), '申诉已提交');
    }

    public function applyVerify(int $id, Request $request): JsonResponse
    {
        $account = OfficialAccount::where('owner_id', auth()->id())->findOrFail($id);
        if ($account->verified_at) {
            return ApiResponse::error('ALREADY_VERIFIED', '该互物号已认证', 422);
        }

        $validated = $request->validate([
            'type' => 'required|in:enterprise,personal',
            'name' => 'required|string|max:100',
            'reason' => 'required|string|max:500',
        ]);

        $settings = $account->settings ?? [];
        $settings['verify_request'] = [
            'type' => $validated['type'],
            'name' => $validated['name'],
            'reason' => $validated['reason'],
            'submitted_at' => now()->toDateTimeString(),
            'rejected' => false,
        ];
        $account->update(['settings' => $settings]);

        return ApiResponse::success($account->fresh(), '认证申请已提交');
    }

}
