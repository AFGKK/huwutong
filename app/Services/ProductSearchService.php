<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductSku;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * 商品搜索/筛选/排序服务 (M2-156 🛒)
 *
 * 覆盖：
 * - 全文搜索（商品名+描述）
 * - 多维筛选（分类/标签/价格/上架时间/计费周期）
 * - 多维度排序（销量/价格/上新/评分）
 * - 搜索结果高亮
 * - 搜索历史管理
 * - 热门搜索词
 */
class ProductSearchService
{
    /**
     * 高级搜索
     */
    public function search(array $params): array
    {
        $query = ProductSku::with(['product', 'product.tags'])
            ->where('is_active', true);

        // ── 全文搜索 ──
        if ($search = $params['search'] ?? null) {
            $query->where(function (Builder $q) use ($search) {
                // SKU 名 + 编码
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku_code', 'like', "%{$search}%");
                // 关联商品名 + 描述
                $q->orWhereHas('product', function (Builder $pq) use ($search) {
                    $pq->where('name', 'like', "%{$search}%")
                       ->orWhere('description', 'like', "%{$search}%");
                });
            });

            // 记录搜索历史
            $this->recordSearchHistory($search);
        }

        // ── 标签筛选 ──
        if ($tagIds = $params['tags'] ?? null) {
            $tagIds = is_array($tagIds) ? $tagIds : explode(',', $tagIds);
            $query->whereHas('product.tags', function (Builder $tq) use ($tagIds) {
                $tq->whereIn('tags.id', $tagIds);
            });
        }

        // ── 产品分类（按 product_id 筛选） ──
        if ($productId = $params['product_id'] ?? null) {
            $query->where('product_id', $productId);
        }

        // ── 计费周期筛选 ──
        if ($billingCycle = $params['billing_cycle'] ?? null) {
            $query->where('billing_cycle', $billingCycle);
        }

        // ── 价格区间 ──
        if (isset($params['price_min'])) {
            $query->where('price', '>=', (float) $params['price_min']);
        }
        if (isset($params['price_max'])) {
            $query->where('price', '<=', (float) $params['price_max']);
        }

        // ── 上架时间范围 ──
        if ($createdAfter = $params['created_after'] ?? null) {
            $query->where('created_at', '>=', $createdAfter);
        }
        if ($createdBefore = $params['created_before'] ?? null) {
            $query->where('created_at', '<=', $createdBefore);
        }

        // ── 排序 ──
        $sort = $params['sort'] ?? '-sold_count';
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $field = ltrim($sort, '-');
        $allowedSorts = ['price', 'created_at', 'sold_count', 'name'];
        $query->orderBy(in_array($field, $allowedSorts) ? $field : 'sold_count', $direction);

        $perPage = min((int) ($params['per_page'] ?? 20), 100);

        $results = $query->paginate($perPage);

        // ── 搜索结果高亮处理 ──
        $highlight = $params['search'] ?? null;
        $items = collect($results->items())->map(function ($sku) use ($highlight) {
            return $this->applyHighlight($sku, $highlight);
        })->toArray();

        $paginated = $results->toArray();
        $paginated['data'] = $items;

        return $paginated;
    }

    /**
     * 搜索建议（自动补全）
     */
    public function suggest(string $query, int $limit = 5): array
    {
        if (mb_strlen($query) < 1) {
            return [];
        }

        // 从商品名匹配
        $products = Product::where('is_active', true)
            ->where('name', 'like', "{$query}%")
            ->limit($limit)
            ->pluck('name')
            ->toArray();

        // 从 SKU 名匹配
        $skus = ProductSku::where('is_active', true)
            ->where('name', 'like', "{$query}%")
            ->limit($limit)
            ->pluck('name')
            ->toArray();

        return array_values(array_unique(array_merge($products, $skus)));
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
        if (!$userId) {
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

    /**
     * 记录搜索日志
     */
    protected function recordSearchHistory(string $keyword): void
    {
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

        // 清除热门搜索缓存，下次自动刷新
        Cache::forget('product_search:hot_terms');
    }

    /**
     * 搜索结果高亮
     */
    protected function applyHighlight(ProductSku $sku, ?string $keyword): array
    {
        $data = $sku->toArray();

        if (!$keyword) {
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
