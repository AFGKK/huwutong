<?php

namespace App\Services;

use App\Models\KbArticle;
use App\Models\KbCategory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * 帮助中心/知识库 CMS 服务
 *
 * 管理知识库分类、文章、版本历史、反馈等
 */
class KnowledgeBaseService
{
    /**
     * 获取分类树
     */
    public function getCategoryTree(string $locale = 'zh-CN'): array
    {
        return Cache::remember("kb_category_tree_{$locale}", 3600, function () use ($locale) {
            $categories = KbCategory::active()
                ->where('locale', $locale)
                ->orderBy('sort_order')
                ->get();

            return $this->buildTree($categories);
        });
    }

    /**
     * 搜索文章
     */
    public function searchArticles(string $query, array $filters = []): array
    {
        if (config('product-search.engine') === 'meilisearch') {
            $meili = app(MeilisearchService::class);
            if ($meili->isAvailable()) {
                try {
                    return $this->searchArticlesViaMeili($meili, $query, $filters);
                } catch (\Throwable $e) {
                    // 降级 MySQL
                }
            }
        }

        $articles = KbArticle::published()
            ->search($query)
            ->when($filters['category_id'] ?? null, fn ($q, $v) => $q->where('category_id', $v))
            ->when($filters['locale'] ?? null, fn ($q, $v) => $q->where('locale', $v))
            ->with('category:id,name')
            ->orderBy('helpful_count', 'desc')
            ->paginate($filters['per_page'] ?? 15);

        return [
            'articles' => $articles,
            'suggestions' => $this->getSearchSuggestions($query),
            'engine' => 'database',
        ];
    }

    protected function searchArticlesViaMeili(MeilisearchService $meili, string $query, array $filters): array
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $page = max(1, (int) ($filters['page'] ?? 1));

        $meiliFilters = ['status' => 'published'];
        if ($categoryId = $filters['category_id'] ?? null) {
            $meiliFilters['category_id'] = (int) $categoryId;
        }
        if ($locale = $filters['locale'] ?? null) {
            $meiliFilters['locale'] = $locale;
        }

        $result = $meili->searchKbForService($query, $meiliFilters, $perPage, $page);
        $ids = collect($result['hits'] ?? [])->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();

        if ($ids === []) {
            $articles = KbArticle::published()->whereRaw('1 = 0')->paginate($perPage, ['*'], 'page', $page);
        } else {
            $idsCsv = implode(',', $ids);
            $articles = KbArticle::published()
                ->whereIn('id', $ids)
                ->with('category:id,name')
                ->orderByRaw("array_position(ARRAY[{$idsCsv}]::bigint[], id)")
                ->paginate($perPage, ['*'], 'page', $page);
        }

        return [
            'articles' => $articles,
            'suggestions' => collect($result['hits'] ?? [])->pluck('title')->filter()->take(5)->values()->all(),
            'engine' => 'meilisearch',
            'total' => $result['total'] ?? $articles->total(),
        ];
    }

    /**
     * 获取推荐文章（相关文章基于标签匹配）
     */
    public function getRelatedArticles(KbArticle $article, int $limit = 5): array
    {
        $tags = $article->tags ?? [];

        if (empty($tags)) {
            return KbArticle::published()
                ->where('category_id', $article->category_id)
                ->where('id', '!=', $article->id)
                ->limit($limit)
                ->get()
                ->toArray();
        }

        return KbArticle::published()
            ->where('id', '!=', $article->id)
            ->where(function ($q) use ($tags) {
                foreach ($tags as $tag) {
                    $q->orWhereJsonContains('tags', $tag);
                }
            })
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * 创建文章
     */
    public function createArticle(array $data): KbArticle
    {
        return DB::transaction(function () use ($data) {
            $article = KbArticle::create($data);

            // 创建初始版本
            $article->createVersion('初始版本');

            $this->clearCache();

            return $article;
        });
    }

    /**
     * 更新文章
     */
    public function updateArticle(KbArticle $article, array $data, ?string $changeSummary = null): KbArticle
    {
        return DB::transaction(function () use ($article, $data, $changeSummary) {
            $oldContent = $article->content;

            $article->update($data);

            // 如果内容变更，创建新版本
            if (isset($data['content']) && $data['content'] !== $oldContent) {
                $article->createVersion($changeSummary ?? '更新内容');
            }

            $this->clearCache();

            return $article->fresh();
        });
    }

    /**
     * 发布文章
     */
    public function publishArticle(KbArticle $article): KbArticle
    {
        $article->update([
            'status' => 'published',
            'published_at' => $article->published_at ?? now(),
        ]);

        $this->clearCache();
        return $article->fresh();
    }

    /**
     * 归档文章
     */
    public function archiveArticle(KbArticle $article): KbArticle
    {
        $article->update(['status' => 'archived']);
        $this->clearCache();
        return $article->fresh();
    }

    /**
     * 记录反馈
     */
    public function recordFeedback(KbArticle $article, bool $isHelpful, ?string $comment = null, ?string $sessionId = null): void
    {
        $article->feedback()->create([
            'is_helpful' => $isHelpful,
            'comment' => $comment,
            'session_id' => $sessionId,
        ]);

        if ($isHelpful) {
            $article->increment('helpful_count');
        } else {
            $article->increment('unhelpful_count');
        }
    }

    /**
     * 获取搜索建议
     */
    protected function getSearchSuggestions(string $query): array
    {
        // 基于热门搜索提供建议
        $popularTags = KbArticle::published()
            ->whereJsonLength('tags', '>', 0)
            ->limit(5)
            ->get()
            ->pluck('tags')
            ->flatten()
            ->unique()
            ->take(5)
            ->toArray();

        $popularArticles = KbArticle::published()
            ->orderBy('view_count', 'desc')
            ->limit(3)
            ->get(['id', 'title'])
            ->toArray();

        return [
            'popular_tags' => $popularTags,
            'popular_articles' => $popularArticles,
        ];
    }

    /**
     * 构建分类树
     */
    protected function buildTree($categories, $parentId = null): array
    {
        $tree = [];
        foreach ($categories as $category) {
            if ($category->parent_id === $parentId) {
                $children = $this->buildTree($categories, $category->id);
                $tree[] = [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                    'article_count' => $category->articleCount(),
                    'children' => $children,
                ];
            }
        }
        return $tree;
    }

    /**
     * 清除缓存
     */
    protected function clearCache(): void
    {
        Cache::forget('kb_category_tree_zh-CN');
        Cache::forget('kb_category_tree_en');
    }
}
