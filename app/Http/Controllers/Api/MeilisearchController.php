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
            'index' => 'required|in:products,kb_articles,marketplace_apps,forum_posts,blog_posts,oa_articles,users',
        ]);

        try {
            $result = $this->meilisearch->setupIndex($validated['index']);
            return response()->json(['success' => true, 'data' => $result, 'message' => '索引配置已更新']);
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
            'message' => $deleted ? '索引已删除' : '删除失败',
        ]);
    }

    /**
     * 同步数据到 Meilisearch
     */
    public function sync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'nullable|in:products,kb_articles,marketplace_apps,forum_posts,blog_posts,oa_articles,users,all',
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
     * 统一搜索（跨所有索引）
     */
    public function unifiedSearch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => 'required|string|max:200',
            'limit' => 'nullable|integer|min:1|max:20',
        ]);

        try {
            $result = $this->meilisearch->unifiedSearch($validated['q'], [
                'limit' => $validated['limit'] ?? 5,
            ]);
            return response()->json(['success' => true, 'data' => $result]);
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
        if (!$this->meilisearch->isAvailable()) {
            return response()->json(['success' => true, 'data' => [
                'products' => Product::count(),
                'kb_articles' => KbArticle::where('status', 'published')->count(),
                'marketplace_apps' => MarketplaceApp::where('status', 'published')->count(),
                'forum_posts' => ForumPost::where('status', 'published')->count(),
                'blog_posts' => BlogPost::where('is_published', true)->count(),
                'oa_articles' => OaArticle::where('status', 'published')->count(),
                'users' => User::where('status', 'active')->count(),
                'meilisearch_available' => false,
            ]]);
        }

        $indexes = $this->meilisearch->getIndexes();
        $indexMap = collect($indexes)->keyBy('uid');

        return response()->json(['success' => true, 'data' => [
            'products' => ['in_db' => Product::count(), 'in_meili' => ($indexMap->get(config('meilisearch.indexes.products.name'))['number_of_documents'] ?? 0)],
            'kb_articles' => ['in_db' => KbArticle::where('status', 'published')->count(), 'in_meili' => ($indexMap->get(config('meilisearch.indexes.kb_articles.name'))['number_of_documents'] ?? 0)],
            'marketplace_apps' => ['in_db' => MarketplaceApp::where('status', 'published')->count(), 'in_meili' => ($indexMap->get(config('meilisearch.indexes.marketplace_apps.name'))['number_of_documents'] ?? 0)],
            'forum_posts' => ['in_db' => ForumPost::where('status', 'published')->count(), 'in_meili' => ($indexMap->get(config('meilisearch.indexes.forum_posts.name'))['number_of_documents'] ?? 0)],
            'blog_posts' => ['in_db' => BlogPost::where('is_published', true)->count(), 'in_meili' => ($indexMap->get(config('meilisearch.indexes.blog_posts.name'))['number_of_documents'] ?? 0)],
            'oa_articles' => ['in_db' => OaArticle::where('status', 'published')->count(), 'in_meili' => ($indexMap->get(config('meilisearch.indexes.oa_articles.name'))['number_of_documents'] ?? 0)],
            'users' => ['in_db' => User::where('status', 'active')->count(), 'in_meili' => ($indexMap->get(config('meilisearch.indexes.users.name'))['number_of_documents'] ?? 0)],
            'meilisearch_available' => true,
        ]]);
    }
}
