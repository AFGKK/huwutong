<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
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
use App\Events\OaArticlePublished;
use App\Events\OaSubmissionCreated;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OfficialAccountController extends Controller
{
    // ════════════════════════════════════════════
    // 公众号发现与关注
    // ════════════════════════════════════════════

    public function index(): JsonResponse
    {
        $myId = auth()->id();
        $accounts = OfficialAccount::where('status', 'active')
            ->with('owner:id,name')
            ->withCount('followers')
            ->get();

        $followedIds = \App\Models\Follow::where('user_id', $myId)
            ->where('followable_type', 'App\\Models\\OfficialAccount')
            ->whereIn('followable_id', $accounts->pluck('id'))
            ->pluck('followable_id')
            ->toArray();

        return ApiResponse::success(
            $accounts->map(fn($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'slug' => $a->slug,
                'description' => $a->description,
                'avatar' => $a->avatar,
                'cover_image' => $a->cover_image,
                'followers_count' => $a->followers_count,
                'is_following' => in_array($a->id, $followedIds),
            ])
        );
    }

    public function search(Request $request): JsonResponse
    {
        $q = $request->input('q', '');
        $type = $request->input('type', 'all'); // all, account, article
        $myId = auth()->id();

        $accounts = [];
        $articles = [];
        $products = [];
        $merchants = [];

        if (in_array($type, ['all', 'account', 'merchant'])) {
            $merchants = OfficialAccount::where('status', 'active')
                ->whereHas('products')
                ->when($q, fn($query) => $query->where(function($q2) use ($q) {
                    $q2->where('name', 'like', "%{$q}%")
                       ->orWhere('description', 'like', "%{$q}%");
                }))
                ->withCount('followers')
                ->with(['products' => fn($q) => $q->with('skus')->where('is_active', true)->limit(4)])
                ->limit(10)
                ->get()
                ->map(fn($m) => [
                    'id' => $m->id,
                    'type' => 'merchant',
                    'name' => $m->name,
                    'description' => $m->description,
                    'avatar' => $m->avatar,
                    'followers_count' => $m->followers_count,
                    'is_following' => $m->followers()->where('user_id', $myId)->exists(),
                    'products' => $m->products->map(fn($p) => [
                        'id' => $p->id,
                        'slug' => $p->slug,
                        'name' => $p->name,
                        'image_url' => $p->image_url,
                        'base_price' => $p->base_price,
                        'sku_price_min' => $p->relationLoaded('skus') && $p->skus->count() > 0 ? (float) $p->skus->min('price') : null,
                        'sku_price_max' => $p->relationLoaded('skus') && $p->skus->count() > 0 ? (float) $p->skus->max('price') : null,
                        'description' => $p->description,
                    ]),
                ]);
        }

        if (in_array($type, ['all', 'account'])) {
            $accounts = OfficialAccount::where('status', 'active')
                ->when($q, fn($query) => $query->where(function($q2) use ($q) {
                    $q2->where('name', 'like', "%{$q}%")
                       ->orWhere('description', 'like', "%{$q}%");
                }))
                ->withCount('followers')
                ->limit(20)
                ->get()
                ->map(fn($a) => [
                    'id' => $a->id,
                    'type' => 'account',
                    'name' => $a->name,
                    'description' => $a->description,
                    'avatar' => $a->avatar,
                    'cover_image' => $a->cover_image,
                    'followers_count' => $a->followers_count,
                    'is_following' => $a->followers()->where('user_id', $myId)->exists(),
                ]);
        }

        if (in_array($type, ['all', 'article'])) {
            $articles = OaArticle::where('status', 'published')
                ->with('author:id,name,avatar', 'account:id,name,avatar')
                ->when($q, fn($query) => $query->where(function($q2) use ($q) {
                    $q2->where('title', 'like', "%{$q}%")
                       ->orWhere('summary', 'like', "%{$q}%")
                       ->orWhere('content', 'like', "%{$q}%");
                }))
                ->withCount(['likes', 'reads'])
                ->orderBy('id', 'desc')
                ->limit(20)
                ->get()
                ->map(fn($a) => [
                    'id' => $a->id,
                    'type' => 'article',
                    'title' => $a->title,
                    'summary' => $a->summary,
                    'cover_image' => $a->cover_image,
                    'content' => $a->content,
                    'account_name' => $a->account?->name,
                    'account_avatar' => $a->account?->avatar,
                    'author_name' => $a->author?->name,
                    'message_type' => $this->detectArticleType($a),
                    'likes_count' => $a->likes_count,
                    'reads_count' => $a->reads_count,
                    'published_at' => $a->published_at,
                    'is_liked' => $a->likes()->where('user_id', $myId)->exists(),
                ]);
        }

        if (in_array($type, ['all', 'product'])) {
            $products = Product::with('skus')->with('category:id,name')
                ->where('is_active', true)
                ->when($q, fn($query) => $query->where(function($q2) use ($q) {
                    $q2->where('name', 'like', "%{$q}%")
                       ->orWhere('description', 'like', "%{$q}%");
                }))
                ->limit(20)
                ->get()
                ->map(fn($p) => [
                    'id' => $p->id,
                    'type' => 'product',
                    'name' => $p->name,
                    'slug' => $p->slug,
                    'description' => $p->description,
                    'image_url' => $p->image_url,
                    'images' => $p->images,
                    'base_price' => $p->base_price,
                    'sku_price_min' => $p->relationLoaded('skus') && $p->skus->count() > 0 ? (float) $p->skus->min('price') : null,
                    'sku_price_max' => $p->relationLoaded('skus') && $p->skus->count() > 0 ? (float) $p->skus->max('price') : null,
                    'sales_count' => $p->sales_count,
                    'tags' => $p->tags,
                    'category_name' => $p->category?->name,
                    'is_sellable' => $p->is_sellable,
                ]);
        }

        return ApiResponse::success([
            'accounts' => $accounts,
            'articles' => $articles,
            'products' => $products,
            'merchants' => $merchants,
            'total' => count($accounts) + count($articles) + count($products) + count($merchants),
        ]);
    }

    private function detectArticleType($article): string
    {
        $content = strip_tags($article->content ?? '');
        if (preg_match('/<video[^>]*>/i', $article->content ?? '')) return 'video';
        if (preg_match('/<audio[^>]*>/i', $article->content ?? '')) return 'audio';
        // Check for image count in content
        preg_match_all('/<img[^>]*>/i', $article->content ?? '', $imgMatches);
        if (count($imgMatches[0] ?? []) > 0) return count($imgMatches[0]) > 1 ? 'multi_image' : 'image';
        return 'text';
    }

    public function myAccounts(): JsonResponse
    {
        $myId = auth()->id();
        $followedIds = \App\Models\Follow::where('user_id', $myId)
            ->where('followable_type', 'App\\Models\\OfficialAccount')
            ->pluck('followable_id');
        $accounts = OfficialAccount::whereIn('id', $followedIds)
            ->where('status', 'active')
            ->where('owner_id', '!=', $myId)
            ->withCount(['followers', 'articles' => fn($q) => $q->where('status', 'published')])
            ->get()
            ->map(fn($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'slug' => $a->slug,
                'description' => $a->description,
                'avatar' => $a->avatar,
                'cover_image' => $a->cover_image,
                'followers_count' => $a->followers_count,
                'articles_count' => $a->articles_count,
                'is_following' => true,
                'latest_article' => $a->articles()->where('status', 'published')->latest()->first(),
            ]);
        return ApiResponse::success($accounts);
    }

    public function follow(int $id): JsonResponse
    {
        $account = OfficialAccount::where('status', 'active')->findOrFail($id);
        $myId = auth()->id();
        $type = 'App\\Models\\OfficialAccount';

        if (\App\Models\Follow::where('user_id', $myId)
            ->where('followable_type', $type)
            ->where('followable_id', $id)->exists()) {
            return ApiResponse::error('ALREADY_FOLLOWING', '已经关注了该公众号');
        }

        \App\Models\Follow::create(['user_id' => $myId, 'followable_type' => $type, 'followable_id' => $id]);
        return ApiResponse::success(null, '关注成功');
    }

    public function unfollow(int $id): JsonResponse
    {
        $myId = auth()->id();
        $type = 'App\\Models\\OfficialAccount';
        \App\Models\Follow::where('user_id', $myId)
            ->where('followable_type', $type)
            ->where('followable_id', $id)->delete();
        return ApiResponse::success(null, '已取消关注');
    }

    // ════════════════════════════════════════════
    // 文章管理
    // ════════════════════════════════════════════

    public function articles(int $accountId, Request $request): JsonResponse
    {
        $account = OfficialAccount::findOrFail($accountId);
        $isOwner = $account->owner_id === auth()->id();

        $query = OaArticle::where('account_id', $accountId)
            ->with('author:id,name,avatar')
            ->withCount(['likes', 'reads', 'shares', 'comments' => fn($q) => $q->whereNull('parent_id')->where('status', 'approved'), 'favorites']);

        // 非号主只能看到已发布的文章
        if (!$isOwner) {
            $query->where('status', 'published');
        } elseif ($status = $request->input('status')) {
            // 号主可按状态筛选
            $query->where('status', $status);
        }

        if ($beforeId = $request->input('before_id')) {
            $query->where('id', '<', $beforeId);
        }

        // 排序
        $query->orderBy('is_pinned', 'desc');
        if ($request->input('sort') === 'hot') {
            $query->orderBy('reads_count', 'desc')->orderBy('id', 'desc');
        } else {
            $query->orderBy('id', 'desc');
        }

        $articles = $query->paginate(min($request->input('per_page', 20), 50));

        $myId = auth()->id();
        // 预加载当前用户的点赞记录
        $likedIds = \App\Models\Like::where('user_id', $myId)
            ->where('likeable_type', 'App\\Models\\OaArticle')
            ->whereIn('likeable_id', $articles->pluck('id'))
            ->pluck('likeable_id')
            ->toArray();
        $favIds = \App\Models\Favorite::where('user_id', $myId)
            ->where('favorable_type', 'App\\Models\\OaArticle')
            ->whereIn('favorable_id', $articles->pluck('id'))
            ->pluck('favorable_id')
            ->toArray();
        // 当前用户已读文章
        $readIds = \App\Models\OaArticleRead::where('user_id', $myId)
            ->whereIn('article_id', $articles->pluck('id'))
            ->distinct('article_id')
            ->pluck('article_id')
            ->toArray();
        // 阅读清单
        $readingListIds = OaReadingListItem::where('user_id', $myId)
            ->whereIn('article_id', $articles->pluck('id'))
            ->pluck('article_id')
            ->toArray();

        $articles->getCollection()->transform(fn($a) => [
            'id' => $a->id,
            'title' => $a->title,
            'summary' => $a->summary,
            'cover_image' => $a->cover_image,
            'author' => $a->author ? [
                'id' => $a->author->id,
                'name' => $a->author->name,
                'avatar' => $a->author->avatar
                    ? (str_starts_with($a->author->avatar, 'http') ? $a->author->avatar : asset('storage/' . $a->author->avatar))
                    : null,
            ] : null,
            'tags' => $a->tags,
            'is_pinned' => $a->is_pinned,
            'is_original' => $a->is_original,
            'is_read' => in_array($a->id, $readIds),
            'in_reading_list' => in_array($a->id, $readingListIds),
            'collection_id' => $a->collection_id,
            'allow_comments' => $a->allow_comments,
            'images' => $a->images,
            'published_at' => $a->published_at,
            'edited_at' => $a->edited_at,
            'likes_count' => $a->likes_count,
            'reads_count' => $a->reads_count,
            'shares_count' => $a->shares_count,
            'comments_count' => $a->comments_count ?? 0,
            'favorites_count' => $a->favorites_count ?? 0,
            'is_liked' => in_array($a->id, $likedIds),
            'is_favorited' => in_array($a->id, $favIds),
        ]);

        return ApiResponse::paginated($articles);
    }

    public function articleDetail(int $id): JsonResponse
    {
        $article = OaArticle::where('status', 'published')
            ->with('author:id,name,avatar', 'account:id,name,avatar,description')
            ->withCount(['likes', 'reads', 'shares'])
            ->findOrFail($id);

        // 记录阅读
        OaArticleRead::create([
            'article_id' => $id,
            'user_id' => auth()->id(),
            'ip' => request()->ip(),
        ]);

        $myId = auth()->id();

        // 推荐相关文章（同标签 + 同账号最新）
        $related = [];
        if ($article->tags) {
            $related = OaArticle::where('id', '!=', $id)
                ->where('status', 'published')
                ->where('account_id', $article->account_id)
                ->where(function($q) use ($article) {
                    foreach ($article->tags as $tag) {
                        $q->orWhereJsonContains('tags', $tag);
                    }
                })
                ->take(3)
                ->get(['id', 'title', 'summary', 'cover_image', 'published_at'])
                ->toArray();
        }
        // 补充推荐文章（如果不够3篇）
        if (count($related) < 3) {
            $extra = OaArticle::where('id', '!=', $id)
                ->where('status', 'published')
                ->where('account_id', $article->account_id)
                ->whereNotIn('id', array_column($related, 'id'))
                ->latest('published_at')
                ->take(3 - count($related))
                ->get(['id', 'title', 'summary', 'cover_image', 'published_at'])
                ->toArray();
            $related = array_merge($related, $extra);
        }

        // 读者也在读（读了这篇文章的人还读了哪些）
        $alsoRead = [];
        $readerIds = OaArticleRead::where('article_id', $id)
            ->whereNotNull('user_id')
            ->distinct('user_id')
            ->pluck('user_id')
            ->take(20)
            ->toArray();

        if (!empty($readerIds)) {
            $alsoReadArticleIds = OaArticleRead::whereIn('user_id', $readerIds)
                ->where('article_id', '!=', $id)
                ->distinct('article_id')
                ->pluck('article_id')
                ->take(10)
                ->toArray();

            if (!empty($alsoReadArticleIds)) {
                $alsoRead = OaArticle::whereIn('id', $alsoReadArticleIds)
                    ->where('status', 'published')
                    ->with('account:id,name')
                    ->take(4)
                    ->get(['id', 'title', 'cover_image', 'account_id', 'published_at'])
                    ->toArray();
            }
        }

        // 上一篇 / 下一篇
        $prev = OaArticle::where('account_id', $article->account_id)
            ->where('status', 'published')
            ->where('published_at', '<', $article->published_at ?? $article->created_at)
            ->orderBy('published_at', 'desc')
            ->first(['id', 'title']);

        $next = OaArticle::where('account_id', $article->account_id)
            ->where('status', 'published')
            ->where('published_at', '>', $article->published_at ?? $article->created_at)
            ->orderBy('published_at', 'asc')
            ->first(['id', 'title']);

        // 评论
        $comments = OaComment::with(['user:id,name,avatar,region', 'replies.user:id,name,avatar,region'])
            ->where('article_id', $id)
            ->whereNull('parent_id')
            ->where('status', 'approved')
            ->orderBy('is_pinned', 'desc')
            ->orderBy('id', 'desc')
            ->take(50)
            ->get()
            ->map(function ($c) use ($myId) {
                $c->user && $c->user->avatar && $c->user->avatar = (str_starts_with($c->user->avatar, 'http') ? $c->user->avatar : asset('storage/' . $c->user->avatar));
                $c->replies && $c->replies->each(fn($r) => $r->user && $r->user->avatar && $r->user->avatar = (str_starts_with($r->user->avatar, 'http') ? $r->user->avatar : asset('storage/' . $r->user->avatar)));
                $c->likes_count = $c->likes()->count();
                $c->is_liked = $c->isLikedBy($myId);
                return $c;
            });

        return ApiResponse::success([
            'id' => $article->id,
            'title' => $article->title,
            'content' => $article->content,
            'summary' => $article->summary,
            'cover_image' => $article->cover_image,
            'author' => $article->author ? [
                'id' => $article->author->id,
                'name' => $article->author->name,
                'avatar' => $article->author->avatar
                    ? (str_starts_with($article->author->avatar, 'http') ? $article->author->avatar : asset('storage/' . $article->author->avatar))
                    : null,
            ] : null,
            'account' => $article->account ? [
                'id' => $article->account->id,
                'name' => $article->account->name,
                'avatar' => $article->account->avatar
                    ? (str_starts_with($article->account->avatar, 'http') ? $article->account->avatar : asset('storage/' . $article->account->avatar))
                    : null,
                'description' => $article->account->description,
            ] : null,
            'tags' => $article->tags,
            'is_pinned' => $article->is_pinned,
            'is_original' => $article->is_original,
            'collection_id' => $article->collection_id,
            'allow_comments' => $article->allow_comments,
            'images' => $article->images,
            'published_at' => $article->published_at,
            'likes_count' => $article->likes_count,
            'reads_count' => $article->reads_count + 1,
            'shares_count' => $article->shares_count,
            'is_liked' => $myId ? $article->isLikedBy($myId) : false,
            'is_favorited' => $myId ? \App\Models\Favorite::where('user_id', $myId)
                ->where('favorable_type', 'App\\Models\\OaArticle')
                ->where('favorable_id', $id)->exists() : false,
            'is_following' => $myId ? \App\Models\Follow::where('user_id', $myId)
                ->where('followable_type', 'App\\Models\\OfficialAccount')
                ->where('followable_id', $article->account_id)->exists() : false,
            'related_articles' => $related,
            'also_read_articles' => $alsoRead,
            'prev_article' => $prev ? ['id' => $prev->id, 'title' => $prev->title] : null,
            'next_article' => $next ? ['id' => $next->id, 'title' => $next->title] : null,
            'comments' => $comments,
        ]);
    }

    // ── 公开评论列表（无需登录） ──
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

    public function toggleFavorite(int $articleId): JsonResponse
    {
        $article = OaArticle::where('status', 'published')->findOrFail($articleId);
        $myId = auth()->id();
        $type = 'App\\Models\\OaArticle';
        $existing = \App\Models\Favorite::where('user_id', $myId)
            ->where('favorable_type', $type)
            ->where('favorable_id', $articleId)->first();
        if ($existing) {
            $existing->delete();
            return ApiResponse::success(['favorited' => false], '已取消收藏');
        }
        \App\Models\Favorite::create(['user_id' => $myId, 'favorable_type' => $type, 'favorable_id' => $articleId]);
    return ApiResponse::success(['favorited' => true], '已收藏');
}

// ── 我收藏的文章列表 ──
public function myFavoriteArticles(): JsonResponse
{
    $favorites = \App\Models\OaFavorite::with(['article' => fn($q) => $q->with('account:id,name,avatar'), 'article.author:id,name,avatar'])
        ->where('user_id', auth()->id())
        ->orderBy('id', 'desc')
        ->paginate(20);
    return ApiResponse::paginated($favorites);
}

    // ── 我点赞的文章列表 ──
    public function myLikedArticles(): JsonResponse
    {
        $likes = OaArticleLike::with(['article' => fn($q) => $q->with('account:id,name,avatar'), 'article.author:id,name,avatar'])
            ->where('user_id', auth()->id())
            ->orderBy('id', 'desc')
            ->paginate(20);
        return ApiResponse::paginated($likes);
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

    public function togglePinComment(int $commentId): JsonResponse
    {
        $comment = OaComment::with('article')->findOrFail($commentId);
        // Only account owner can pin
        $account = OfficialAccount::where('owner_id', auth()->id())
            ->where('id', $comment->article->account_id)->first();
        if (!$account) {
            return ApiResponse::error('无权操作', 403);
        }
        $comment->update(['is_pinned' => !$comment->is_pinned]);
        return ApiResponse::success(['is_pinned' => $comment->fresh()->is_pinned]);
    }

    public function toggleLike(int $id): JsonResponse
    {
        $article = OaArticle::where('status', 'published')->findOrFail($id);
        $myId = auth()->id();
        $type = 'App\\Models\\OaArticle';
        $like = \App\Models\Like::where('user_id', $myId)
            ->where('likeable_type', $type)
            ->where('likeable_id', $id)->first();
        if ($like) {
            $like->delete();
            return ApiResponse::success(['liked' => false], '已取消点赞');
        }
        \App\Models\Like::create(['user_id' => $myId, 'likeable_type' => $type, 'likeable_id' => $id]);
    return ApiResponse::success(['liked' => true], '点赞成功');
}

public function shareArticle(Request $request, int $id): JsonResponse
{
    $article = OaArticle::with('account:id,name')->where('status', 'published')->findOrFail($id);
    $myId = auth()->id();
        $platform = $request->input('platform', 'im');
        $target = $request->input('target'); // chat, plaza, channel, wechat, weibo

        // 记录分享
        OaArticleShare::create([
            'article_id' => $id,
            'user_id' => $myId,
            'platform' => $platform,
        ]);

        // 分享到聊天
        if ($target === 'chat') {
            $convId = $request->input('conversation_id');
            if (!$convId) return ApiResponse::error('缺少会话ID');

            $isParticipant = ConversationParticipant::where('conversation_id', $convId)
                ->where('user_id', $myId)->whereNull('deleted_at')->exists();
            if (!$isParticipant) return ApiResponse::error('你不是目标会话的参与者', 403);

            $shareUrl = url('/articles/' . $id);
            $content = '📰 推荐一篇文章：' . $article->title . "\n" . $shareUrl;

            ConversationMessage::create([
                'conversation_id' => $convId,
                'sender_id' => $myId,
                'message_type' => 'text',
                'content' => $content,
                'metadata' => [
                    'from_oa_share' => true,
                    'article_id' => $id,
                    'article_title' => $article->title,
                    'account_name' => $article->account?->name,
                ],
                'client_msg_id' => 'oashare-' . uniqid(),
            ]);
        }

        // 分享到广场
        if ($target === 'plaza') {
            $shareUrl = url('/articles/' . $id);
            ForumPost::create([
                'user_id' => $myId,
                'content' => '📰 推荐文章：' . $article->title . "\n\n" . ($article->summary ?? '') . "\n\n🔗 " . $shareUrl,
                'images' => $article->cover_image ? [$article->cover_image] : null,
                'title' => null,
            ]);
        }

        // 分享到圈子
        if ($target === 'channel') {
            $channelId = $request->input('channel_id');
            if (!$channelId) return ApiResponse::error('缺少圈子ID');

            $shareUrl = url('/articles/' . $id);
            $content = '📰 推荐文章：' . $article->title . "\n" . $shareUrl;

            \App\Models\ChannelMessage::create([
                'channel_id' => $channelId,
                'user_id' => $myId,
                'content' => $content,
                'message_type' => 'text',
            ]);
        }

        // 微信/微博 — 返回前端自行处理（通过 URL 分享或复制链接）
        $shareUrl = url('/articles/' . $id);
        $shareText = '推荐一篇文章：' . $article->title;

        return ApiResponse::success([
            'share_url' => $shareUrl,
            'share_text' => $shareText,
            'target' => $target,
        ], '分享成功');
    }

    // ════════════════════════════════════════════
    // 投稿系统
    // ════════════════════════════════════════════

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

    // ════════════════════════════════════════════
    // 管理端
    // ════════════════════════════════════════════

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'avatar' => 'nullable|string|max:500',
            'cover_image' => 'nullable|string|max:500',
            'category_id' => 'nullable|integer|exists:oa_categories,id',
        ]);

        $slug = Str::slug($validated['name']);
        $baseSlug = $slug;
        $counter = 1;
        while (OfficialAccount::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter++;
        }

        $account = OfficialAccount::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? '',
            'avatar' => $validated['avatar'] ?? null,
            'cover_image' => $validated['cover_image'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'owner_id' => auth()->id(),
        ]);

        // 创建者自动关注
        OaFollower::create(['account_id' => $account->id, 'user_id' => auth()->id()]);

        return ApiResponse::success($account->load('category'), '公众号已创建', 201);
    }

    public function update(int $id, Request $request): JsonResponse
    {
        $account = OfficialAccount::where('owner_id', auth()->id())->findOrFail($id);

        $data = $request->only(['description', 'avatar', 'cover_image']);

        // 名称修改：每年最多3次
        if ($request->has('name') && $request->input('name') !== $account->name) {
            $settings = $account->settings ?? [];
            $nameUpdates = $settings['name_updates'] ?? [];
            // 过滤出本年内的修改记录
            $yearAgo = now()->subYear();
            $recentUpdates = array_filter($nameUpdates, fn($ts) => $ts >= $yearAgo->timestamp);
            if (count($recentUpdates) >= 3) {
                return ApiResponse::error('公众号名称每年仅能修改3次', 422);
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

        $totalLikes = \App\Models\Like::where('likeable_type', 'App\\Models\\OaArticle')
            ->whereHasMorph('likeable', [\App\Models\OaArticle::class], fn($q) => $q->where('account_id', $id))->count();
        $totalReads = OaArticleRead::whereHas('article', fn($q) => $q->where('account_id', $id))->count();
        $totalShares = OaArticleShare::whereHas('article', fn($q) => $q->where('account_id', $id))->count();
        $totalComments = OaComment::whereHas('article', fn($q) => $q->where('account_id', $id))
            ->whereNull('parent_id')->count();

        $pendingSubmissions = OaSubmission::where('account_id', $id)
            ->where('status', 'pending')->count();

        $todayFollowers = \App\Models\Follow::where('followable_type', 'App\\Models\\OfficialAccount')
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
                'count' => \App\Models\Follow::where('followable_type', 'App\\Models\\OfficialAccount')
                    ->where('followable_id', $id)
                    ->whereDate('created_at', $date)->count(),
                'cumulative' => \App\Models\Follow::where('followable_type', 'App\\Models\\OfficialAccount')
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
                'count' => \App\Models\Like::where('likeable_type', 'App\\Models\\OaArticle')
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

    // ── 评论管理 ──
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
            return ApiResponse::error('无权删除', 403);
        }
        $comment->replies()->delete();
        $comment->delete();
        return ApiResponse::success(null, '已删除');
    }

    public function approveComment(int $commentId): JsonResponse
    {
        $comment = OaComment::with('article.account')->findOrFail($commentId);
        if ($comment->article->account->owner_id !== auth()->id()) {
            return ApiResponse::error('无权操作', 403);
        }
        $comment->update(['status' => 'approved']);
        return ApiResponse::success($comment->fresh(), '评论已通过');
    }

    public function rejectComment(int $commentId): JsonResponse
    {
        $comment = OaComment::with('article.account')->findOrFail($commentId);
        if ($comment->article->account->owner_id !== auth()->id()) {
            return ApiResponse::error('无权操作', 403);
        }
        $comment->update(['status' => 'rejected']);
        return ApiResponse::success($comment->fresh(), '评论已拒绝');
    }

    // ── 分类列表 ──
    public function categories(): JsonResponse
    {
        $categories = OaCategory::where('is_active', true)
            ->orderBy('sort_order')
            ->get();
        return ApiResponse::success($categories);
    }

    // ── 头像上传 ──
    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|image|max:2048']);
        $path = $request->file('file')->store('oa-avatars', 'public');
        $url = asset('storage/' . $path);
        return ApiResponse::success(['url' => $url], '上传成功');
    }

    // ── 管理员：全部分类（含禁用） ──
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
            return ApiResponse::error('该分类下有公众号，无法删除', 422);
        }
        $cat->delete();
        return ApiResponse::success(null, '已删除');
    }

    public function myOwnedAccounts(): JsonResponse
    {
        $accounts = OfficialAccount::where('owner_id', auth()->id())
            ->withCount(['followers', 'articles' => fn($q) => $q->where('status', 'published')])
            ->get();
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

    public function createArticle(int $accountId, Request $request): JsonResponse
    {
        $account = OfficialAccount::where('owner_id', auth()->id())->findOrFail($accountId);

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
            'account_id' => $accountId,
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

        // 校验：父菜单必须在同一个公众号下
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
        // 关注者发送消息给公众号（无需号主权限）
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
            'cover_image' => $a->cover_image,
            'reads_count' => $a->reads_count,
            'likes_count' => $a->likes_count,
            'published_at' => $a->published_at,
            'account' => $a->account ? ['id' => $a->account->id, 'name' => $a->account->name, 'avatar' => $a->account->avatar ? (str_starts_with($a->account->avatar, 'http') ? $a->account->avatar : asset('storage/' . $a->account->avatar)) : null] : null,
        ]));
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

    // 获取公众号的所有合集
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
}
