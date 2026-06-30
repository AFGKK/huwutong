<?php

namespace App\Services;

use App\Models\KbArticle;
use App\Models\MarketplaceApp;
use App\Models\Product;
use App\Models\ForumPost;
use App\Models\BlogPost;
use App\Models\OaArticle;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Meilisearch 搜索引擎集成服务
 *
 * 提供索引管理、数据同步、全文搜索功能，
 * 作为现有 MySQL LIKE 搜索的增强替代。
 */
class MeilisearchService
{
    protected ?\Meilisearch\Client $client = null;
    protected bool $available = false;

    public function __construct()
    {
        $this->boot();
    }

    /**
     * 初始化 Meilisearch 客户端
     */
    protected function boot(): void
    {
        $host = config('meilisearch.host');
        $apiKey = config('meilisearch.api_key');

        if (empty($host)) {
            return;
        }

        try {
            $this->client = new \Meilisearch\Client($host, $apiKey);
            $this->client->health();
            $this->available = true;
        } catch (\Throwable $e) {
            $this->available = false;
            Log::warning('Meilisearch 连接失败: ' . $e->getMessage());
        }
    }

    /**
     * 检查 Meilisearch 是否可用
     */
    public function isAvailable(): bool
    {
        return $this->available;
    }

    /**
     * 获取原始客户端（供高级操作使用）
     */
    public function client(): ?\Meilisearch\Client
    {
        return $this->client;
    }

    // ══════════════════════════════════════════
    //  索引管理
    // ══════════════════════════════════════════

    /**
     * 获取所有索引列表
     */
    public function getIndexes(): array
    {
        if (!$this->available) return [];

        try {
            $indexes = $this->client->getIndexes()->getResults();
            $result = [];
            foreach ($indexes as $index) {
                $stats = $this->client->index($index->getUid())->stats();
                $result[] = [
                    'uid' => $index->getUid(),
                    'primary_key' => $index->getPrimaryKey(),
                    'number_of_documents' => $stats['numberOfDocuments'] ?? 0,
                    'last_update' => $stats['lastUpdate'] ?? null,
                    'searchable' => $this->getIndexSettings($index->getUid())['searchableAttributes'] ?? [],
                    'filterable' => $this->getIndexSettings($index->getUid())['filterableAttributes'] ?? [],
                ];
            }
            return $result;
        } catch (\Throwable $e) {
            Log::error('获取 Meilisearch 索引列表失败: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 初始化/重建索引配置
     */
    public function setupIndex(string $indexKey): array
    {
        $config = config("meilisearch.indexes.{$indexKey}");
        if (!$config) {
            throw new \RuntimeException("未知索引: {$indexKey}");
        }

        try {
            // 创建或更新索引
            $index = $this->client->createIndex($config['name'], ['primaryKey' => $config['primary_key']]);
            $indexUid = $config['name'];

            // 设置可搜索属性
            $this->client->index($indexUid)->updateSearchableAttributes($config['searchable_attributes']);
            // 设置过滤属性
            $this->client->index($indexUid)->updateFilterableAttributes($config['filterable_attributes']);
            // 设置排序属性
            $this->client->index($indexUid)->updateSortableAttributes($config['sortable_attributes']);

            return ['uid' => $indexUid, 'status' => 'configured'];
        } catch (\Throwable $e) {
            // 索引已存在时更新设置
            try {
                $indexUid = $config['name'];
                $this->client->index($indexUid)->updateSearchableAttributes($config['searchable_attributes']);
                $this->client->index($indexUid)->updateFilterableAttributes($config['filterable_attributes']);
                $this->client->index($indexUid)->updateSortableAttributes($config['sortable_attributes']);
                return ['uid' => $indexUid, 'status' => 'updated'];
            } catch (\Throwable $e2) {
                throw new \RuntimeException('设置索引失败: ' . $e2->getMessage());
            }
        }
    }

    /**
     * 删除索引
     */
    public function deleteIndex(string $uid): bool
    {
        try {
            $this->client->deleteIndex($uid);
            return true;
        } catch (\Throwable $e) {
            Log::error("删除 Meilisearch 索引失败: {$uid}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 获取索引设置
     */
    public function getIndexSettings(string $uid): array
    {
        try {
            return $this->client->index($uid)->getSettings();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * 获取服务状态
     */
    public function getHealth(): array
    {
        if (!$this->available) {
            return ['status' => 'unavailable', 'version' => null];
        }

        try {
            $health = $this->client->health();
            $version = $this->client->version();
            $stats = $this->client->getStats();

            return [
                'status' => $health['status'] ?? 'unknown',
                'version' => $version,
                'stats' => $stats,
                'indexes' => $this->getIndexes(),
            ];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage(), 'version' => null];
        }
    }

    // ══════════════════════════════════════════
    //  数据同步
    // ══════════════════════════════════════════

    /**
     * 同步商品数据到 Meilisearch
     */
    public function syncProducts(?int $chunkSize = null): array
    {
        $chunkSize = $chunkSize ?: config('meilisearch.sync.chunk_size', 100);
        $indexUid = config('meilisearch.indexes.products.name', 'products');
        $total = 0;

        try {
            Product::with(['category:id,name', 'merchant:id,name'])->chunk($chunkSize, function ($products) use ($indexUid, &$total) {
                $documents = $products->map(fn($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'slug' => $p->slug,
                    'description' => $p->description,
                    'long_description' => strip_tags($p->long_description ?? ''),
                    'tags' => is_array($p->tags) ? $p->tags : [],
                    'category_id' => $p->category_id,
                    'category_name' => $p->category?->name,
                    'is_active' => $p->is_active,
                    'is_sellable' => $p->is_sellable,
                    'base_price' => (float) ($p->base_price ?? 0),
                    'sales_count' => $p->sales_count ?? 0,
                    'merchant_id' => $p->merchant_id,
                    'merchant_name' => $p->merchant?->name,
                    'image_url' => $p->image_url,
                    'created_at' => $p->created_at?->toIso8601String(),
                    'updated_at' => $p->updated_at?->toIso8601String(),
                ])->toArray();

                $this->client->index($indexUid)->addDocuments($documents);
                $total += count($documents);
            });

            return ['index' => $indexUid, 'synced' => $total];
        } catch (\Throwable $e) {
            Log::error('同步商品到 Meilisearch 失败: ' . $e->getMessage());
            throw new \RuntimeException('同步商品失败: ' . $e->getMessage());
        }
    }

    /**
     * 同步知识库文章到 Meilisearch
     */
    public function syncKbArticles(?int $chunkSize = null): array
    {
        $chunkSize = $chunkSize ?: config('meilisearch.sync.chunk_size', 100);
        $indexUid = config('meilisearch.indexes.kb_articles.name', 'kb_articles');
        $total = 0;

        try {
            KbArticle::with(['category:id,name'])
                ->chunk($chunkSize, function ($articles) use ($indexUid, &$total) {
                    $documents = $articles->map(fn($a) => [
                        'id' => $a->id,
                        'title' => $a->title,
                        'content' => strip_tags($a->content ?? ''),
                        'excerpt' => $a->excerpt,
                        'tags' => is_array($a->tags) ? $a->tags : [],
                        'category_id' => $a->category_id,
                        'category_name' => $a->category?->name,
                        'status' => $a->status,
                        'locale' => $a->locale,
                        'author_id' => $a->author_id,
                        'view_count' => $a->view_count ?? 0,
                        'helpful_count' => $a->helpful_count ?? 0,
                        'created_at' => $a->created_at?->toIso8601String(),
                        'published_at' => $a->published_at?->toIso8601String(),
                        'updated_at' => $a->updated_at?->toIso8601String(),
                    ])->toArray();

                    $this->client->index($indexUid)->addDocuments($documents);
                    $total += count($documents);
                });

            return ['index' => $indexUid, 'synced' => $total];
        } catch (\Throwable $e) {
            Log::error('同步知识库到 Meilisearch 失败: ' . $e->getMessage());
            throw new \RuntimeException('同步知识库失败: ' . $e->getMessage());
        }
    }

    /**
     * 同步所有索引
     */
    public function syncAll(): array
    {
        $results = [];
        $results['products'] = $this->syncProducts();
        $results['kb_articles'] = $this->syncKbArticles();
        $results['marketplace_apps'] = $this->syncMarketplaceApps();
        $results['forum_posts'] = $this->syncForumPosts();
        $results['blog_posts'] = $this->syncBlogPosts();
        $results['oa_articles'] = $this->syncOaArticles();
        $results['users'] = $this->syncUsers();
        return $results;
    }

    /**
     * 同步应用市场应用到 Meilisearch
     */
    public function syncMarketplaceApps(?int $chunkSize = null): array
    {
        $chunkSize = $chunkSize ?: config('meilisearch.sync.chunk_size', 100);
        $indexUid = config('meilisearch.indexes.marketplace_apps.name', 'marketplace_apps');
        $total = 0;

        try {
            MarketplaceApp::with(['developer:id,display_name,avatar'])->chunk($chunkSize, function ($apps) use ($indexUid, &$total) {
                $documents = $apps->map(fn($a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'slug' => $a->slug,
                    'short_description' => $a->short_description,
                    'description' => strip_tags($a->description ?? ''),
                    'category' => $a->category,
                    'status' => $a->status,
                    'pricing_type' => $a->pricing_type,
                    'price' => (float) ($a->price ?? 0),
                    'developer_id' => $a->developer_id,
                    'developer_name' => $a->developer?->display_name,
                    'developer_avatar' => $a->developer?->avatar,
                    'icon_url' => $a->icon_url,
                    'install_count' => $a->install_count ?? 0,
                    'avg_rating' => (float) ($a->avg_rating ?? 0),
                    'current_version' => $a->current_version,
                    'created_at' => $a->created_at?->toIso8601String(),
                    'published_at' => $a->published_at?->toIso8601String(),
                    'updated_at' => $a->updated_at?->toIso8601String(),
                ])->toArray();

                $this->client->index($indexUid)->addDocuments($documents);
                $total += count($documents);
            });

            return ['index' => $indexUid, 'synced' => $total];
        } catch (\Throwable $e) {
            Log::error('同步应用市场到 Meilisearch 失败: ' . $e->getMessage());
            throw new \RuntimeException('同步应用市场失败: ' . $e->getMessage());
        }
    }

    /**
     * 搜索应用市场应用
     */
    public function searchMarketplaceApps(string $query, array $options = []): array
    {
        $indexUid = config('meilisearch.indexes.marketplace_apps.name', 'marketplace_apps');
        return $this->search($indexUid, $query, $options);
    }

    /**
     * 同步论坛帖子到 Meilisearch
     */
    public function syncForumPosts(?int $chunkSize = null): array
    {
        $chunkSize = $chunkSize ?: config('meilisearch.sync.chunk_size', 100);
        $indexUid = config('meilisearch.indexes.forum_posts.name', 'forum_posts');
        $total = 0;

        try {
            ForumPost::with(['user:id,name,avatar'])->where('status', 'published')->chunk($chunkSize, function ($posts) use ($indexUid, &$total) {
                $documents = $posts->map(fn($p) => [
                    'id' => $p->id,
                    'title' => $p->title,
                    'content' => strip_tags($p->content ?? ''),
                    'tags' => is_array($p->tags) ? $p->tags : [],
                    'category_id' => $p->category_id,
                    'user_id' => $p->user_id,
                    'user_name' => $p->user?->name,
                    'user_avatar' => $p->user?->avatar,
                    'status' => $p->status,
                    'views_count' => $p->views_count ?? 0,
                    'likes_count' => $p->likes_count ?? 0,
                    'created_at' => $p->created_at?->toIso8601String(),
                    'updated_at' => $p->updated_at?->toIso8601String(),
                ])->toArray();

                $this->client->index($indexUid)->addDocuments($documents);
                $total += count($documents);
            });

            return ['index' => $indexUid, 'synced' => $total];
        } catch (\Throwable $e) {
            Log::error('同步论坛到 Meilisearch 失败: ' . $e->getMessage());
            throw new \RuntimeException('同步论坛失败: ' . $e->getMessage());
        }
    }

    /**
     * 同步博客文章到 Meilisearch
     */
    public function syncBlogPosts(?int $chunkSize = null): array
    {
        $chunkSize = $chunkSize ?: config('meilisearch.sync.chunk_size', 100);
        $indexUid = config('meilisearch.indexes.blog_posts.name', 'blog_posts');
        $total = 0;

        try {
            BlogPost::with(['authorUser:id,name,avatar'])->where('is_published', true)->chunk($chunkSize, function ($posts) use ($indexUid, &$total) {
                $documents = $posts->map(fn($p) => [
                    'id' => $p->id,
                    'title' => $p->title,
                    'content' => strip_tags($p->content ?? ''),
                    'excerpt' => $p->excerpt,
                    'tags' => is_array($p->tags) ? $p->tags : [],
                    'author' => $p->author,
                    'author_id' => $p->author_id,
                    'author_avatar' => $p->authorUser?->avatar,
                    'category_id' => $p->category_id,
                    'is_published' => $p->is_published,
                    'featured_image' => $p->featured_image,
                    'created_at' => $p->created_at?->toIso8601String(),
                    'published_at' => $p->published_at?->toIso8601String(),
                ])->toArray();

                $this->client->index($indexUid)->addDocuments($documents);
                $total += count($documents);
            });

            return ['index' => $indexUid, 'synced' => $total];
        } catch (\Throwable $e) {
            Log::error('同步博客到 Meilisearch 失败: ' . $e->getMessage());
            throw new \RuntimeException('同步博客失败: ' . $e->getMessage());
        }
    }

    /**
     * 同步公众号文章到 Meilisearch
     */
    public function syncOaArticles(?int $chunkSize = null): array
    {
        $chunkSize = $chunkSize ?: config('meilisearch.sync.chunk_size', 100);
        $indexUid = config('meilisearch.indexes.oa_articles.name', 'oa_articles');
        $total = 0;

        try {
            OaArticle::with(['author:id,name,avatar', 'account:id,name,avatar'])->where('status', 'published')->chunk($chunkSize, function ($articles) use ($indexUid, &$total) {
                $documents = $articles->map(fn($a) => [
                    'id' => $a->id,
                    'title' => $a->title,
                    'content' => strip_tags($a->content ?? ''),
                    'summary' => $a->summary,
                    'tags' => is_array($a->tags) ? $a->tags : [],
                    'account_id' => $a->account_id,
                    'account_name' => $a->account?->name,
                    'account_avatar' => $a->account?->avatar,
                    'author_id' => $a->author_id,
                    'author_name' => $a->author?->name,
                    'author_avatar' => $a->author?->avatar,
                    'status' => $a->status,
                    'cover_image' => $a->cover_image,
                    'created_at' => $a->created_at?->toIso8601String(),
                    'published_at' => $a->published_at?->toIso8601String(),
                ])->toArray();

                $this->client->index($indexUid)->addDocuments($documents);
                $total += count($documents);
            });

            return ['index' => $indexUid, 'synced' => $total];
        } catch (\Throwable $e) {
            Log::error('同步公众号到 Meilisearch 失败: ' . $e->getMessage());
            throw new \RuntimeException('同步公众号失败: ' . $e->getMessage());
        }
    }

    /**
     * 同步用户到 Meilisearch
     */
    public function syncUsers(?int $chunkSize = null): array
    {
        $chunkSize = $chunkSize ?: config('meilisearch.sync.chunk_size', 100);
        $indexUid = config('meilisearch.indexes.users.name', 'users');
        $total = 0;

        try {
            User::where('status', 'active')->chunk($chunkSize, function ($users) use ($indexUid, &$total) {
                $documents = $users->map(fn($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'avatar' => $u->avatar,
                    'status' => $u->status,
                    'created_at' => $u->created_at?->toIso8601String(),
                ])->toArray();

                $this->client->index($indexUid)->addDocuments($documents);
                $total += count($documents);
            });

            return ['index' => $indexUid, 'synced' => $total];
        } catch (\Throwable $e) {
            Log::error('同步用户到 Meilisearch 失败: ' . $e->getMessage());
            throw new \RuntimeException('同步用户失败: ' . $e->getMessage());
        }
    }

    /**
     * 统一搜索 — 跨所有索引搜索
     */
    public function unifiedSearch(string $query, array $options = []): array
    {
        $limit = $options['limit'] ?? 5;
        $indexes = array_keys(config('meilisearch.indexes', []));
        $results = [];
        $rankedHits = [];
        $labelMap = [
            'products' => '商品', 'kb_articles' => '帮助中心', 'marketplace_apps' => '应用市场',
            'forum_posts' => '广场', 'blog_posts' => '博客', 'oa_articles' => '公众号', 'users' => '用户',
        ];

        foreach ($indexes as $indexKey) {
            $indexUid = config("meilisearch.indexes.{$indexKey}.name");
            if (!$indexUid) continue;

            try {
                $searchResult = $this->search($indexUid, $query, [
                    'limit' => $limit,
                    'highlight' => ['title', 'name', 'content', 'description'],
                    'show_ranking_score' => true,
                    'matches_position' => false,
                ]);
                if (!empty($searchResult['hits'])) {
                    $results[$indexKey] = [
                        'total' => $searchResult['total'],
                        'hits' => $searchResult['hits'],
                    ];
                    // 收集到权重排序列表
                    foreach ($searchResult['hits'] as $hit) {
                        $hit['_content_type'] = $indexKey;
                        $hit['_content_label'] = $labelMap[$indexKey] ?? $indexKey;
                        $rankedHits[] = $hit;
                    }
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        // 按 Meilisearch 权重分数降序排列
        usort($rankedHits, fn($a, $b) => ($b['_rankingScore'] ?? 0) <=> ($a['_rankingScore'] ?? 0));

        return [
            'query' => $query,
            'results' => $results,
            'ranked' => $rankedHits,
            'total_types' => count($results),
            'total' => count($rankedHits),
        ];
    }

    // ══════════════════════════════════════════
    //  搜索
    // ══════════════════════════════════════════

    /**
     * 搜索商品
     */
    public function searchProducts(string $query, array $options = []): array
    {
        $indexUid = config('meilisearch.indexes.products.name', 'products');
        return $this->search($indexUid, $query, $options);
    }

    /**
     * 搜索知识库文章
     */
    public function searchKbArticles(string $query, array $options = []): array
    {
        $indexUid = config('meilisearch.indexes.kb_articles.name', 'kb_articles');
        return $this->search($indexUid, $query, $options);
    }

    /**
     * 通用搜索
     */
    public function search(string $indexUid, string $query, array $options = []): array
    {
        if (!$this->available) {
            throw new \RuntimeException('Meilisearch 不可用');
        }

        $limit = $options['limit'] ?? config('meilisearch.search.limit', 20);
        $page = $options['page'] ?? 1;
        $offset = ($page - 1) * $limit;

        $searchParams = [
            'limit' => $limit,
            'offset' => $offset,
            'attributesToHighlight' => $options['highlight'] ?? ['*'],
            'showMatchesPosition' => $options['matches_position'] ?? config('meilisearch.search.matches_position', true),
            'showRankingScore' => $options['show_ranking_score'] ?? config('meilisearch.search.show_ranking_score', false),
        ];

        // 过滤条件
        if (!empty($options['filters'])) {
            $searchParams['filter'] = $options['filters'];
        }

        // 排序
        if (!empty($options['sort'])) {
            $searchParams['sort'] = $options['sort'];
        }

        // 分面搜索
        if (!empty($options['facets'])) {
            $searchParams['facetsDistribution'] = $options['facets'];
        }

        try {
            $result = $this->client->index($indexUid)->search($query, $searchParams);
            return [
                'hits' => $result->getHits(),
                'total' => $result->getEstimatedTotalHits(),
                'limit' => $limit,
                'page' => $page,
                'processing_time_ms' => $result->getProcessingTimeMs(),
                'query' => $result->getQuery(),
                'facet_distribution' => $result->getFacetDistribution(),
            ];
        } catch (\Throwable $e) {
            Log::error("Meilisearch 搜索失败 [{$indexUid}]: " . $e->getMessage());
            throw new \RuntimeException('搜索失败: ' . $e->getMessage());
        }
    }

    /**
     * 搜索建议（前缀搜索）
     */
    public function suggest(string $indexUid, string $query, int $limit = 5): array
    {
        return $this->search($indexUid, $query, [
            'limit' => $limit,
            'highlight' => [],
            'matches_position' => false,
        ]);
    }

    // ══════════════════════════════════════════
    //  文档管理
    // ══════════════════════════════════════════

    /**
     * 添加/更新单个文档
     */
    public function addDocument(string $indexUid, array $document): bool
    {
        try {
            $this->client->index($indexUid)->addDocuments([$document]);
            return true;
        } catch (\Throwable $e) {
            Log::error("Meilisearch 添加文档失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 批量添加/更新文档
     */
    public function addDocuments(string $indexUid, array $documents): bool
    {
        try {
            $this->client->index($indexUid)->addDocuments($documents);
            return true;
        } catch (\Throwable $e) {
            Log::error("Meilisearch 批量添加文档失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 删除文档
     */
    public function deleteDocument(string $indexUid, $documentId): bool
    {
        try {
            $this->client->index($indexUid)->deleteDocument($documentId);
            return true;
        } catch (\Throwable $e) {
            Log::error("Meilisearch 删除文档失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 清空索引
     */
    public function clearIndex(string $indexUid): bool
    {
        try {
            $this->client->index($indexUid)->deleteAllDocuments();
            return true;
        } catch (\Throwable $e) {
            Log::error("Meilisearch 清空索引失败: " . $e->getMessage());
            return false;
        }
    }

    // ══════════════════════════════════════════
    //  搜索集成（替换现有 Service 中的 LIKE）
    // ══════════════════════════════════════════

    /**
     * 获取商品搜索服务使用的 Meilisearch 搜索结果
     * 供 ProductSearchService 调用
     */
    public function searchProductsForService(string $query, array $filters = [], int $perPage = 20, int $page = 1): array
    {
        $options = [
            'limit' => $perPage,
            'page' => $page,
        ];

        // 构建过滤表达式
        $filterParts = [];
        if (isset($filters['category_id'])) {
            $filterParts[] = "category_id = {$filters['category_id']}";
        }
        if (isset($filters['is_active'])) {
            $filterParts[] = 'is_active = ' . ($filters['is_active'] ? 'true' : 'false');
        }
        if (isset($filters['is_sellable'])) {
            $filterParts[] = 'is_sellable = ' . ($filters['is_sellable'] ? 'true' : 'false');
        }
        if (!empty($filters['price_min'])) {
            $filterParts[] = "base_price >= {$filters['price_min']}";
        }
        if (!empty($filters['price_max'])) {
            $filterParts[] = "base_price <= {$filters['price_max']}";
        }

        if (!empty($filterParts)) {
            $options['filters'] = implode(' AND ', $filterParts);
        }

        // 排序
        if (!empty($filters['sort'])) {
            $options['sort'] = [$filters['sort']];
        }

        return $this->searchProducts($query, $options);
    }

    /**
     * 获取知识库搜索服务使用的 Meilisearch 搜索结果
     * 供 KnowledgeBaseService 调用
     */
    public function searchKbForService(string $query, array $filters = [], int $perPage = 20, int $page = 1): array
    {
        $options = [
            'limit' => $perPage,
            'page' => $page,
        ];

        $filterParts = [];
        if (isset($filters['category_id'])) {
            $filterParts[] = "category_id = {$filters['category_id']}";
        }
        if (isset($filters['status'])) {
            $filterParts[] = "status = '{$filters['status']}'";
        }
        if (isset($filters['locale'])) {
            $filterParts[] = "locale = '{$filters['locale']}'";
        }

        if (!empty($filterParts)) {
            $options['filters'] = implode(' AND ', $filterParts);
        }

        return $this->searchKbArticles($query, $options);
    }
}
