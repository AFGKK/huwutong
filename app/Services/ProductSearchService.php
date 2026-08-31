<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductSku;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 商品搜索/筛选/排序服务 (M2-156 🛒)
 *
 * D-35: 默认走 Meilisearch，不可用时降级 MySQL LIKE。
 */
class ProductSearchService
{
    public function __construct(
        protected MeilisearchService $meilisearch,
    ) {}

    /**
     * 高级搜索
     */
    public function search(array $params): LengthAwarePaginator
    {
        $search = trim((string) ($params['search'] ?? ''));
        $productIds = $search !== '' ? $this->resolveProductIds($search, $params) : null;

        if ($productIds !== null && $productIds === []) {
            return $this->emptyPaginator($params);
        }

        $query = ProductSku::with(['product', 'product.tags'])
            ->where('is_active', true);

        if ($productIds !== null) {
            $query->whereIn('product_id', $productIds);
            $this->applyMeiliRelevanceOrder($query, $productIds, $params);
        } elseif ($search !== '') {
            $this->applyDatabaseSearch($query, $search);
        }

        $this->applyFilters($query, $params);
        $this->applySort($query, $params, $productIds !== null);

        $perPage = min((int) ($params['per_page'] ?? config('product-search.search.per_page', 20)), config('product-search.search.max_per_page', 100));

        $results = $query->paginate($perPage);

        if ($search !== '') {
            $this->recordSearchHistory($search);
        }

        $highlight = $search !== '' ? $search : null;
        $results->getCollection()->transform(function ($sku) use ($highlight) {
            return $this->applyHighlight($sku, $highlight);
        });

        return $results;
    }

    /**
     * 搜索建议（自动补全）
     */
    public function suggest(string $query, int $limit = 5): array
    {
        if (mb_strlen($query) < 1) {
            return [];
        }

        if ($this->usesMeilisearch()) {
            try {
                $result = $this->meilisearch->searchProducts($query, ['limit' => $limit]);
                $names = collect($result['hits'] ?? [])
                    ->pluck('name')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                if ($names !== []) {
                    return $names;
                }
            } catch (\Throwable $e) {
                Log::warning('Meilisearch 商品 suggest 降级: ' . $e->getMessage());
            }
        }

        return $this->suggestFromDatabase($query, $limit);
    }

    /**
     * Meili 全文检索得到 product_id 列表；不可用时返回 null（走 DB LIKE）
     *
     * @return array<int>|null
     */
    public function resolveProductIds(string $search, array $filters = []): ?array
    {
        if (! $this->usesMeilisearch()) {
            return null;
        }

        try {
            $meiliFilters = ['is_active' => true];
            if ($categoryId = $filters['category_id'] ?? null) {
                $meiliFilters['category_id'] = (int) $categoryId;
            }
            if (isset($filters['price_min'])) {
                $meiliFilters['price_min'] = (float) $filters['price_min'];
            }
            if (isset($filters['price_max'])) {
                $meiliFilters['price_max'] = (float) $filters['price_max'];
            }

            $sort = $this->mapSortToMeili((string) ($filters['sort'] ?? '-sold_count'));
            if ($sort) {
                $meiliFilters['sort'] = $sort;
            }

            $result = $this->meilisearch->searchProductsForService(
                $search,
                $meiliFilters,
                500,
                1,
            );

            return collect($result['hits'] ?? [])
                ->pluck('id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::warning('Meilisearch 商品搜索降级: ' . $e->getMessage());

            return null;
        }
    }

    public function usesMeilisearch(): bool
    {
        return config('product-search.engine') === 'meilisearch'
            && $this->meilisearch->isAvailable();
    }

    /**
     * 获取热门搜索词
     */
    public function getHotSearchTerms(int $limit = 10): array
    {
        return Cache::remember('product_search:hot_terms', 3600, function () use ($limit) {
            return DB::table('product_search_logs')
                ->select('keyword', DB::raw('COUNT(*) as count'))
                ->where('created_at', '>=', now()->subDays(7))
                ->groupBy('keyword')
                ->orderByDesc('count')
                ->limit($limit)
                ->pluck('keyword')
                ->toArray();
        });
    }

    /**
     * 获取用户的搜索历史
     */
    public function getSearchHistory(?int $userId, int $limit = 10): array
    {
        if (! $userId) {
            return [];
        }

        return DB::table('product_search_logs')
            ->where('user_id', $userId)
            ->select('keyword', DB::raw('MAX(created_at) as last_searched'))
            ->groupBy('keyword')
            ->orderByDesc('last_searched')
            ->limit($limit)
            ->pluck('keyword')
            ->toArray();
    }

    /**
     * 清除用户搜索历史
     */
    public function clearSearchHistory(int $userId): void
    {
        DB::table('product_search_logs')
            ->where('user_id', $userId)
            ->delete();
    }

    /**
     * 获取可用标签列表（用于筛选）
     */
    public function getFilterTags(): array
    {
        return Tag::whereHas('products')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'color', 'group'])
            ->toArray();
    }

    protected function applyDatabaseSearch(Builder $query, string $search): void
    {
        $query->where(function (Builder $q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('sku_code', 'like', "%{$search}%")
                ->orWhereHas('product', function (Builder $pq) use ($search) {
                    $pq->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
        });
    }

    protected function applyFilters(Builder $query, array $params): void
    {
        if ($tagIds = $params['tags'] ?? null) {
            $tagIds = is_array($tagIds) ? $tagIds : explode(',', (string) $tagIds);
            $query->whereHas('product.tags', function (Builder $tq) use ($tagIds) {
                $tq->whereIn('tags.id', $tagIds);
            });
        }

        if ($productId = $params['product_id'] ?? null) {
            $query->where('product_id', $productId);
        }

        if ($categoryId = $params['category_id'] ?? null) {
            $query->whereHas('product', fn (Builder $q) => $q->where('category_id', $categoryId));
        }

        if ($billingCycle = $params['billing_cycle'] ?? null) {
            $query->where('billing_cycle', $billingCycle);
        }

        if (isset($params['price_min'])) {
            $query->where('price', '>=', (float) $params['price_min']);
        }
        if (isset($params['price_max'])) {
            $query->where('price', '<=', (float) $params['price_max']);
        }

        if ($createdAfter = $params['created_after'] ?? null) {
            $query->where('created_at', '>=', $createdAfter);
        }
        if ($createdBefore = $params['created_before'] ?? null) {
            $query->where('created_at', '<=', $createdBefore);
        }
    }

    protected function applySort(Builder $query, array $params, bool $fromMeili): void
    {
        $sort = $params['sort'] ?? '-sold_count';

        if ($fromMeili && ! $this->hasExplicitSort($params)) {
            return;
        }

        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $field = ltrim($sort, '-');
        $allowedSorts = ['price', 'created_at', 'sold_count', 'name'];
        $query->orderBy(in_array($field, $allowedSorts) ? $field : 'sold_count', $direction);
    }

    protected function applyMeiliRelevanceOrder(Builder $query, array $productIds, array $params): void
    {
        if ($this->hasExplicitSort($params)) {
            return;
        }

        if ($productIds === []) {
            return;
        }

        $ids = implode(',', $productIds);
        $query->orderByRaw("array_position(ARRAY[{$ids}]::bigint[], product_id)");
    }

    protected function hasExplicitSort(array $params): bool
    {
        return array_key_exists('sort', $params) && ($params['sort'] ?? '') !== '-sold_count';
    }

    protected function mapSortToMeili(string $sort): ?string
    {
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $field = ltrim($sort, '-');

        return match ($field) {
            'sold_count', 'sales_count' => "sales_count:{$direction}",
            'price' => "base_price:{$direction}",
            'created_at' => "created_at:{$direction}",
            default => null,
        };
    }

    protected function suggestFromDatabase(string $query, int $limit): array
    {
        $products = Product::where('is_active', true)
            ->where('name', 'like', "{$query}%")
            ->limit($limit)
            ->pluck('name')
            ->toArray();

        $skus = ProductSku::where('is_active', true)
            ->where('name', 'like', "{$query}%")
            ->limit($limit)
            ->pluck('name')
            ->toArray();

        return array_values(array_unique(array_merge($products, $skus)));
    }

    protected function emptyPaginator(array $params): LengthAwarePaginator
    {
        $perPage = min((int) ($params['per_page'] ?? 20), 100);
        $page = max(1, (int) ($params['page'] ?? 1));

        return new LengthAwarePaginator([], 0, $perPage, $page);
    }

    /**
     * 记录搜索日志
     */
    protected function recordSearchHistory(string $keyword): void
    {
        if (! config('product-search.logging.enabled', true)) {
            return;
        }

        $userId = auth()->id();

        try {
            DB::table('product_search_logs')->insert([
                'keyword' => mb_substr($keyword, 0, 100),
                'user_id' => $userId,
                'ip' => request()->ip(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // 静默失败，不阻塞搜索
        }

        Cache::forget('product_search:hot_terms');
    }

    /**
     * 搜索结果高亮
     */
    protected function applyHighlight(ProductSku $sku, ?string $keyword): array
    {
        $data = $sku->toArray();

        if (! $keyword) {
            return $data;
        }

        $data['name_highlighted'] = $this->highlightText($data['name'] ?? '', $keyword);
        $data['sku_code_highlighted'] = $this->highlightText($data['sku_code'] ?? '', $keyword);

        if ($sku->relationLoaded('product') && $sku->product) {
            $productName = $sku->product->name ?? '';
            $data['product_name_highlighted'] = $this->highlightText($productName, $keyword);
        }

        return $data;
    }

    /**
     * 高亮文本中的关键词
     */
    protected function highlightText(string $text, string $keyword): string
    {
        if (empty($text) || empty($keyword)) {
            return e($text);
        }

        $escaped = preg_quote(e($keyword), '/');

        return preg_replace(
            "/({$escaped})/iu",
            '<mark class="search-highlight">$1</mark>',
            e($text)
        );
    }
}
