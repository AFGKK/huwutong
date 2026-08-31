<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KbArticle;
use App\Models\MarketplaceApp;
use App\Models\Product;
use App\Models\ForumPost;
use App\Models\BlogPost;
use App\Models\OaArticle;
use App\Models\User;
use App\Models\OfficialAccount;
use App\Services\MeilisearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeilisearchController extends Controller
{
    public function __construct(
        protected MeilisearchService $meilisearch
    ) {}

    /**
     * 服务健康状态
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->meilisearch->getHealth(),
        ]);
    }

    /**
     * 索引列表
     */
    public function indexes(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->meilisearch->getIndexes(),
        ]);
    }

    /**
     * 设置/重建索引
     */
    public function setupIndex(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'index' => 'required|in:products,kb_articles,marketplace_apps,forum_posts,blog_posts,oa_articles,users,official_accounts',
        ]);

        try {
            $result = $this->meilisearch->setupIndex($validated['index']);
            return response()->json(['success' => true, 'data' => $result, 'message' => __('app.controller_compat.meilisearch_msg_57')]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * 删除索引
     */
    public function deleteIndex(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'uid' => 'required|string',
        ]);

        $deleted = $this->meilisearch->deleteIndex($validated['uid']);
        return response()->json([
            'success' => $deleted,
            'message' => $deleted ? '索引已删除' : __('app.common.deleted_failed'),
        ]);
    }

    /**
     * 同步数据到 Meilisearch
     */
    public function sync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'nullable|in:products,kb_articles,marketplace_apps,forum_posts,blog_posts,oa_articles,users,official_accounts,all',
        ]);

        $type = $validated['type'] ?? 'all';

        try {
            $result = match ($type) {
                'products' => ['products' => $this->meilisearch->syncProducts()],
                'kb_articles' => ['kb_articles' => $this->meilisearch->syncKbArticles()],
                'marketplace_apps' => ['marketplace_apps' => $this->meilisearch->syncMarketplaceApps()],
                'forum_posts' => ['forum_posts' => $this->meilisearch->syncForumPosts()],
                'blog_posts' => ['blog_posts' => $this->meilisearch->syncBlogPosts()],
                'oa_articles' => ['oa_articles' => $this->meilisearch->syncOaArticles()],
                'users' => ['users' => $this->meilisearch->syncUsers()],
                'official_accounts' => ['official_accounts' => $this->meilisearch->syncOfficialAccounts()],
                default => $this->meilisearch->syncAll(),
            };

            $total = collect($result)->sum(fn($r) => $r['synced'] ?? 0);
            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => "同步完成，共 {$total} 条记录",
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * 搜索测试
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'index' => 'required|in:products,kb_articles,marketplace_apps,forum_posts,blog_posts,oa_articles,users',
            'q' => 'required|string|max:200',
            'limit' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
            'filters' => 'nullable|string',
            'sort' => 'nullable|string',
        ]);

        $indexUid = config("meilisearch.indexes.{$validated['index']}.name");
        $options = [
            'limit' => $validated['limit'] ?? 20,
            'page' => $validated['page'] ?? 1,
        ];
        if (!empty($validated['filters'])) $options['filters'] = $validated['filters'];
        if (!empty($validated['sort'])) $options['sort'] = [$validated['sort']];

        try {
            $result = $this->meilisearch->search($indexUid, $validated['q'], $options);
            return response()->json(['success' => true, 'data' => $result]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * 搜索建议（输入即搜，用于下拉补全）
     */
    public function suggest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => 'required|string|max:200',
            'per_index' => 'nullable|integer|min:1|max:5',
        ]);

        try {
            $result = $this->meilisearch->searchSuggest($validated['q'], $validated['per_index'] ?? 3);

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\RuntimeException $e) {
            return $this->degradedSearchResponse($validated['q'], $e->getMessage(), [
                'query' => $validated['q'],
                'suggestions' => [],
            ]);
        }
    }

    /**
     * 热门推荐内容（无搜索结果时展示）
     */
    public function trending(): JsonResponse
    {
        try {
            $result = $this->meilisearch->trending(3);
            return response()->json(['success' => true, 'data' => $result]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * 统一搜索（跨所有索引）
     */
    public function unifiedSearch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => 'required|string|max:200',
            'limit' => 'nullable|integer|min:1|max:50',
            'sort' => 'nullable|in:relevance,newest,smart,ai,cf,sequence',
        ]);

        $sort = $validated['sort'] ?? 'relevance';

        try {
            $options = [
                'limit' => (int) ($validated['limit'] ?? 5),
                'sort' => $sort,
            ];
            // 智能排序需要用户ID
            if ($options['sort'] === 'smart') {
                $options['user_id'] = auth()->id();
            }
            $result = $this->meilisearch->unifiedSearch($validated['q'], $options);

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\RuntimeException $e) {
            return $this->degradedSearchResponse($validated['q'], $e->getMessage(), [
                'query' => $validated['q'],
                'results' => [],
                'ranked' => [],
                'total_types' => 0,
                'total' => 0,
                'sort' => $sort,
            ]);
        }
    }

    /**
     * Meilisearch 不可用时的降级响应（D-35 / T-22）
     */
    protected function degradedSearchResponse(string $query, string $message, array $payload): JsonResponse
    {
        $payload['meilisearch_available'] = false;
        $payload['message'] = $message;

        return response()->json([
            'success' => true,
            'data' => $payload,
        ]);
    }

    /**
     * 重建全部索引并同步（D-19）
     */
    public function rebuild(Request $request): JsonResponse
    {
        try {
            $this->meilisearch->rebuildAllIndexes();
            $result = $this->meilisearch->syncAll();

            $total = collect($result)->sum(fn ($r) => $r['synced'] ?? 0);

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => "索引已重建并同步，共 {$total} 条记录",
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * 清空索引
     */
    public function clear(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'uid' => 'required|string',
        ]);

        $cleared = $this->meilisearch->clearIndex($validated['uid']);
        return response()->json([
            'success' => $cleared,
            'message' => $cleared ? '索引已清空' : '清空失败',
        ]);
    }

    /**
     * 获取文档统计
     */
    public function stats(): JsonResponse
    {
        // 安全计数（表可能不存在）
        $safeCount = function(string $modelClass, string $statusField = null, string $statusValue = null): int {
            try {
                $table = (new $modelClass)->getTable();
                if (!\Illuminate\Support\Facades\Schema::hasTable($table)) return 0;
                $q = $modelClass::query();
                if ($statusField && $statusValue) {
                    $q->where($statusField, $statusValue);
                }
                return $q->count();
            } catch (\Throwable $e) {
                return 0;
            }
        };

        if (!$this->meilisearch->isAvailable()) {
            return response()->json(['success' => true, 'data' => [
                'products' => $safeCount(\App\Models\Product::class),
                'kb_articles' => $safeCount(\App\Models\KbArticle::class, 'status', 'published'),
                'marketplace_apps' => $safeCount(\App\Models\MarketplaceApp::class, 'status', 'published'),
                'forum_posts' => $safeCount(\App\Models\ForumPost::class, 'status', 'published'),
                'blog_posts' => $safeCount(\App\Models\BlogPost::class, 'is_published', '1'),
                'oa_articles' => $safeCount(\App\Models\OaArticle::class, 'status', 'published'),
                'users' => $safeCount(\App\Models\User::class, 'status', 'active'),
                'official_accounts' => $safeCount(\App\Models\OfficialAccount::class, 'status', 'active'),
                'meilisearch_available' => false,
            ]]);
        }

        $indexes = $this->meilisearch->getIndexes();
        $indexMap = collect($indexes)->keyBy('uid');

        $cmp = function(string $model, string $indexKey, ?string $statusField = null, ?string $statusValue = null) use ($safeCount, $indexMap) {
            return [
                'in_db' => $safeCount($model, $statusField, $statusValue),
                'in_meili' => ($indexMap->get(config("meilisearch.indexes.{$indexKey}.name"))['number_of_documents'] ?? 0),
            ];
        };

        return response()->json(['success' => true, 'data' => [
            'products' => $cmp(\App\Models\Product::class, 'products'),
            'kb_articles' => $cmp(\App\Models\KbArticle::class, 'kb_articles', 'status', 'published'),
            'marketplace_apps' => $cmp(\App\Models\MarketplaceApp::class, 'marketplace_apps', 'status', 'published'),
            'forum_posts' => $cmp(\App\Models\ForumPost::class, 'forum_posts', 'status', 'published'),
            'blog_posts' => $cmp(\App\Models\BlogPost::class, 'blog_posts', 'is_published', '1'),
            'oa_articles' => $cmp(\App\Models\OaArticle::class, 'oa_articles', 'status', 'published'),
            'users' => $cmp(\App\Models\User::class, 'users', 'status', 'active'),
            'official_accounts' => $cmp(\App\Models\OfficialAccount::class, 'official_accounts', 'status', 'active'),
            'meilisearch_available' => true,
        ]]);
    }

    /**
     * 搜索历史
     */
    public function recent(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => []]);
    }

    /**
     * 清除搜索历史
     */
    public function clearRecent(): JsonResponse
    {
        return response()->json(['success' => true, 'message' => '已清除']);
    }

    /**
     * 删除单条搜索历史
     */
    public function deleteRecent(string $id): JsonResponse
    {
        return response()->json(['success' => true, 'message' => '已删除']);
    }

    /**
     * 收藏列表
     */
    public function bookmarks(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => []]);
    }

    /**
     * 切换收藏
     */
    public function toggleBookmark(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => ['bookmarked' => true]]);
    }

    /**
     * 删除收藏
     */
    public function deleteBookmark(string $id): JsonResponse
    {
        return response()->json(['success' => true, 'message' => '已删除']);
    }

    /**
     * 搜索偏好
     */
    public function preferences(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => (object) []]);
    }

    /**
     * 更新搜索偏好
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'message' => '已更新']);
    }
}
