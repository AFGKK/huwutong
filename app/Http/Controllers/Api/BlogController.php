<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\BlogRead;
use App\Models\Favorite;
use App\Models\Like;
use App\Models\OfficialAccount;
use App\Models\OaFollower;
use App\Services\BlogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BlogController extends Controller
{
    public function __construct(
        protected BlogService $blogService,
    ) {}

    // ─── 管理端CRUD ───

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->blogService->listPosts(
                $request->only(['type', 'status', 'search', 'tag', 'per_page']),
            )
        );
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::success($this->blogService->getPost($id));
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'type' => 'required|string|in:blog,changelog,release_note',
            'content' => 'required|string',
            'slug' => 'nullable|string|max:255|unique:blog_posts,slug',
            'excerpt' => 'nullable|string|max:500',
            'featured_image' => 'nullable|string|max:500',
            'author' => 'nullable|string|max:100',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'version' => 'nullable|string|max:30',
            'is_published' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error(__('app.api.blog.validation_failed'), 422, $validator->errors()->toArray());
        }

        return ApiResponse::created($this->blogService->createPost($request->all()), __('app.api.blog.post_created'));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $post = BlogPost::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'type' => 'sometimes|string|in:blog,changelog,release_note',
            'content' => 'sometimes|string',
            'slug' => 'sometimes|string|max:255|unique:blog_posts,slug,' . $id,
            'excerpt' => 'nullable|string|max:500',
            'featured_image' => 'nullable|string|max:500',
            'author' => 'nullable|string|max:100',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'version' => 'nullable|string|max:30',
            'is_published' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error(__('app.api.blog.validation_failed'), 422, $validator->errors()->toArray());
        }

        return ApiResponse::success($this->blogService->updatePost($post, $request->all()), __('app.api.blog.post_updated'));
    }

    public function destroy(int $id): JsonResponse
    {
        $post = BlogPost::findOrFail($id);
        $this->blogService->deletePost($post);
        return ApiResponse::success(null, __('app.api.blog.deleted'));
    }

    // ─── 操作 ───

    public function togglePublish(int $id): JsonResponse
    {
        $post = BlogPost::findOrFail($id);
        return ApiResponse::success($this->blogService->togglePublish($post));
    }

    public function toggleFeatured(int $id): JsonResponse
    {
        $post = BlogPost::findOrFail($id);
        return ApiResponse::success($this->blogService->toggleFeatured($post));
    }

    // ─── 批量操作 ───

    public function batchDelete(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) return ApiResponse::error('NO_IDS', __('app.api.blog.select_posts'), 422);
        $count = BlogPost::whereIn('id', $ids)->delete();
        return ApiResponse::success(['deleted' => $count], __('app.api.blog.deleted_n', ['count' => $count]));
    }

    public function batchPublish(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) return ApiResponse::error('NO_IDS', __('app.api.blog.select_posts'), 422);
        $count = BlogPost::whereIn('id', $ids)->where('is_published', false)->update([
            'is_published' => true, 'published_at' => now(),
        ]);
        return ApiResponse::success(['published' => $count], __('app.api.blog.published_n', ['count' => $count]));
    }

    public function batchCategory(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);
        $categoryId = $request->input('category_id');
        if (empty($ids)) return ApiResponse::error('NO_IDS', __('app.api.blog.select_posts'), 422);
        $count = BlogPost::whereIn('id', $ids)->update(['category_id' => $categoryId]);
        return ApiResponse::success(['updated' => $count], __('app.api.blog.category_updated_n', ['count' => $count]));
    }

    // ─── 导出 ───

    public function exportCsv(Request $request)
    {
        $filters = $request->only(['type', 'status', 'search', 'category_id']);
        $posts = $this->blogService->listPosts($filters, 10000)->items();

        $filename = 'blog-export-' . now()->format('YmdHis') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($posts) {
            $handle = fopen('php://output', 'w');
            // BOM for Excel UTF-8
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, __('app.api.blog.csv_headers'));
            foreach ($posts as $p) {
                fputcsv($handle, [
                    $p->id,
                    $p->title,
                    $p->type,
                    $p->category?->name ?? '',
                    $p->author ?? '',
                    is_array($p->tags) ? implode('; ', $p->tags) : '',
                    $p->is_published ? __('app.api.blog.status_published') : __('app.api.blog.status_draft'),
                    $p->published_at ?? '',
                    strip_tags($p->excerpt ?? ''),
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ─── 公开API ───

    public function publishedList(Request $request, ?string $type = null): JsonResponse
    {
        $limit = $request->input('limit', 10);
        $categoryId = $request->input('category_id');
        return ApiResponse::success($this->blogService->getPublishedPosts($type, $limit, $categoryId));
    }

    public function showBySlug(string $slug): JsonResponse
    {
        return ApiResponse::success($this->blogService->getPostBySlug($slug));
    }

    public function latestChangelog(): JsonResponse
    {
        return ApiResponse::success($this->blogService->getLatestChangelog());
    }

    public function featured(): JsonResponse
    {
        return ApiResponse::success($this->blogService->getFeaturedPosts());
    }

    public function relatedPosts(int $id): JsonResponse
    {
        return ApiResponse::success($this->blogService->getRelatedPosts($id));
    }

    public function recordView(Request $request, int $id): JsonResponse
    {
        $post = BlogPost::find($id);
        if (!$post) {
            return ApiResponse::error('NOT_FOUND', __('app.api.blog.post_missing'), 404);
        }
        $post->increment('views_count');
        // 记录用户阅读历史
        try {
            \App\Models\BlogRead::create([
                'blog_id' => $id,
                'user_id' => $request->user()?->id,
                'ip' => $request->ip(),
            ]);
        } catch (\Throwable $e) {
            // 忽略重复等错误
        }
        return ApiResponse::success(['views_count' => $post->fresh()->views_count]);
    }

    /**
     * 公开分类列表
     */
    public function publicCategories(): JsonResponse
    {
        return ApiResponse::success($this->blogService->getPublicCategories());
    }

    // ─── RSS ───

    public function rss(string $feedType = 'all')
    {
        $xml = $this->blogService->generateRss($feedType);

        return response($xml, 200, [
            'Content-Type' => 'application/rss+xml; charset=UTF-8',
        ]);
    }

    // ─── 统计 ───

    public function stats(): JsonResponse
    {
        return ApiResponse::success($this->blogService->getStats());
    }

    // ─── 订阅管理 (M3-57) ───

    /**
     * 订阅统计 (管理端)
     */
    public function subscriptionStats(): JsonResponse
    {
        return ApiResponse::success($this->blogService->getSubscriptionStats());
    }

    // ─── 分类管理 ───

    /**
     * 分类列表
     */
    public function categories(): JsonResponse
    {
        return ApiResponse::success(
            \App\Models\BlogCategory::active()->ordered()->withCount('posts')->get()
        );
    }

    /**
     * 创建分类
     */
    public function storeCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'slug' => 'nullable|string|max:120|unique:blog_categories,slug',
            'description' => 'nullable|string|max:500',
            'color' => 'nullable|string|max:30',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $category = \App\Models\BlogCategory::create($validated);

        return ApiResponse::created($category, __('app.api.blog.category_created'));
    }

    /**
     * 更新分类
     */
    public function updateCategory(int $id, Request $request): JsonResponse
    {
        $category = \App\Models\BlogCategory::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'slug' => 'nullable|string|max:120|unique:blog_categories,slug,' . $id,
            'description' => 'nullable|string|max:500',
            'color' => 'nullable|string|max:30',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $category->update($validated);

        return ApiResponse::success($category->fresh(), __('app.api.blog.category_updated'));
    }

    /**
     * 删除分类
     */
    public function destroyCategory(int $id): JsonResponse
    {
        $category = \App\Models\BlogCategory::findOrFail($id);

        if ($category->posts()->count() > 0) {
            return ApiResponse::error('CATEGORY_IN_USE', __('app.api.blog.category_in_use'), 422);
        }

        $category->delete();

        return ApiResponse::success(null, __('app.api.blog.category_deleted'));
    }

    /**
     * 创建订阅 (公开)
     */
    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'name' => 'nullable|string|max:100',
            'subscribed_types' => 'required|array|min:1',
            'subscribed_types.*' => 'in:blog,changelog,release_note',
            'frequency' => 'nullable|in:instant,daily,weekly',
        ]);

        $sub = $this->blogService->createSubscription($validated);

        // 发送验证邮件
        try {
            \Illuminate\Support\Facades\Mail::to($sub->email)->send(
                new \App\Mail\BlogSubscriptionVerify($sub)
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Blog subscribe verify mail failed', [
                'email' => $sub->email,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => __('app.api.blog.subscribe_confirm_sent'),
            'data' => ['email' => $sub->email],
        ], 201);
    }

    /**
     * 验证订阅 (公开)
     */
    public function verifySubscription(string $token)
    {
        $result = $this->blogService->verifySubscription($token);

        if ($result['success']) {
            return redirect('/blog?verified=1');
        }

        return redirect('/blog?verified=0');
    }

    /**
     * 取消订阅 (公开)
     */
    public function unsubscribe(string $token): JsonResponse
    {
        $result = $this->blogService->unsubscribe($token);
        return response()->json($result);
    }

    /**
     * Changelog 按版本分组 (公开)
     */
    public function changelogByVersion(): JsonResponse
    {
        $posts = BlogPost::published()
            ->ofType('changelog')
            ->orderByDesc('published_at')
            ->get()
            ->groupBy(fn($p) => $p->version ?? __('app.api.blog.uncategorized'));

        return ApiResponse::success($posts);
    }

    /**
     * 订阅者列表 (管理端)
     */
    public function subscriptionList(): JsonResponse
    {
        $subs = \App\Models\BlogSubscription::orderByDesc('created_at')
            ->paginate(request('per_page', 20));

        return ApiResponse::success($subs);
    }

    // ─── 关注功能 (按作者关注，统一 Follow 模型) ───

    /**
     * 关注博客作者
     */
    public function follow(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return ApiResponse::error('UNAUTHENTICATED', __('app.api.blog.not_logged_in'), 401);
        }

        $authorId = $request->input('author_id');
        if (!$authorId) {
            return ApiResponse::error('AUTHOR_REQUIRED', __('app.api.blog.author_id_required'), 422);
        }

        $author = User::find($authorId);
        if (!$author) {
            return ApiResponse::error(__('app.api.blog.author_missing'), 404);
        }

        $type = 'App\\Models\\User';

        if (\App\Models\Follow::where('user_id', $user->id)
            ->where('followable_type', $type)
            ->where('followable_id', $authorId)->exists()) {
            return ApiResponse::error(__("app.blog.msg_f4f38064"), __('app.api.blog.already_following'), 422);
        }

        \App\Models\Follow::create(['user_id' => $user->id, 'followable_type' => $type, 'followable_id' => $authorId]);

        return ApiResponse::success([
            'followers_count' => \App\Models\Follow::where('followable_type', $type)->where('followable_id', $authorId)->count(),
            'is_following' => true,
        ], __('app.api.blog.follow_ok'));
    }

    /**
     * 取消关注作者
     */
    public function unfollow(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return ApiResponse::error('UNAUTHENTICATED', __('app.api.blog.not_logged_in'), 401);
        }

        $authorId = $request->input('author_id');
        if (!$authorId) {
            return ApiResponse::error(__('app.api.blog.author_id_missing'), 422);
        }

        $type = 'App\\Models\\User';
        \App\Models\Follow::where('user_id', $user->id)
            ->where('followable_type', $type)
            ->where('followable_id', $authorId)->delete();

        return ApiResponse::success([
            'followers_count' => \App\Models\Follow::where('followable_type', $type)->where('followable_id', $authorId)->count(),
            'is_following' => false,
        ], __('app.api.blog.unfollowed'));
    }

    /**
     * 关注状态
     */
    public function followStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        $authorId = $request->input('author_id');
        if (!$authorId) {
            return ApiResponse::error(__('app.api.blog.author_id_missing'), 422);
        }

        $type = 'App\\Models\\User';
        $isFollowing = $user ? \App\Models\Follow::where('user_id', $user->id)
            ->where('followable_type', $type)
            ->where('followable_id', $authorId)->exists() : false;

        return ApiResponse::success([
            'is_following' => $isFollowing,
            'followers_count' => \App\Models\Follow::where('followable_type', $type)->where('followable_id', $authorId)->count(),
        ]);
    }

    /**
     * 作者粉丝数 (公开)
     */
    public function authorFollowerCount(int $authorId): JsonResponse
    {
        $type = 'App\\Models\\User';
        return ApiResponse::success([
            'followers_count' => \App\Models\Follow::where('followable_type', $type)->where('followable_id', $authorId)->count(),
        ]);
    }

    // ── AI 生成摘要 ──
    public function generateSummary(int $id): JsonResponse
    {
        $post = BlogPost::findOrFail($id);
        try {
            $service = app(\App\Services\BlogSummaryService::class);
            $summary = $service->generate($post);
            return ApiResponse::success([
                'excerpt' => $summary,
                'generated' => str_starts_with($summary, '[AI]'),
            ]);
        } catch (\Throwable $e) {
            return ApiResponse::error(__('app.api.blog.summary_failed'), 500);
        }
    }

    // ─── 博客互动方法（统一使用 Like / Favorite 模型）───

    /**
     * 点赞/取消点赞
     */
    public function toggleLike(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $type = 'App\\Models\\BlogPost';
        $existing = \App\Models\Like::where('user_id', $user->id)
            ->where('likeable_type', $type)
            ->where('likeable_id', $id)
            ->first();

        if ($existing) {
            $existing->delete();
            $liked = false;
        } else {
            \App\Models\Like::create(['user_id' => $user->id, 'likeable_type' => $type, 'likeable_id' => $id]);
            $liked = true;
        }

        $likesCount = \App\Models\Like::where('likeable_type', $type)->where('likeable_id', $id)->count();

        return ApiResponse::success(['liked' => $liked, 'likes_count' => $likesCount]);
    }

    /**
     * 收藏/取消收藏
     */
    public function toggleFavorite(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $type = 'App\\Models\\BlogPost';
        $existing = \App\Models\Favorite::where('user_id', $user->id)
            ->where('favorable_type', $type)
            ->where('favorable_id', $id)
            ->first();

        if ($existing) {
            $existing->delete();
            $favored = false;
        } else {
            \App\Models\Favorite::create(['user_id' => $user->id, 'favorable_type' => $type, 'favorable_id' => $id]);
            $favored = true;
        }

        return ApiResponse::success(['favored' => $favored]);
    }

    /**
     * 稍后阅读切换
     */
    public function toggleReadLater(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $existing = \App\Models\BlogRead::where('blog_id', $id)->where('user_id', $user->id)->first();

        if ($existing) {
            $existing->delete();
            $saved = false;
        } else {
            \App\Models\BlogRead::create(['blog_id' => $id, 'user_id' => $user->id]);
            $saved = true;
        }

        return ApiResponse::success(['saved' => $saved]);
    }

    /**
     * 稍后阅读状态
     */
    public function readLaterStatus(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $saved = \App\Models\BlogRead::where('blog_id', $id)->where('user_id', $user->id)->exists();
        return ApiResponse::success(['saved' => $saved]);
    }

    /**
     * 记录分享
     */
    public function recordShare(Request $request, int $id): JsonResponse
    {
        return ApiResponse::success(['shared' => true]);
    }

    /**
     * 互动状态（点赞/收藏/稍后读）
     */
    public function interactionStatus(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $type = 'App\\Models\\BlogPost';

        $liked = \App\Models\Like::where('user_id', $user->id)
            ->where('likeable_type', $type)->where('likeable_id', $id)->exists();
        $favored = \App\Models\Favorite::where('user_id', $user->id)
            ->where('favorable_type', $type)->where('favorable_id', $id)->exists();
        $readLater = \App\Models\BlogRead::where('blog_id', $id)->where('user_id', $user->id)->exists();

        return ApiResponse::success([
            'liked' => $liked,
            'favored' => $favored,
            'read_later' => $readLater,
        ]);
    }
}
