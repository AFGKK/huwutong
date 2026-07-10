<?php

namespace App\Services;

use App\Models\SeoMetadata;
use App\Models\UrlRedirect;
use App\Models\BlogPost;
use App\Models\KbArticle;
use App\Models\Page;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SeoService
{
    // ═══════════ SEO 元数据管理 ═══════════

    /**
     * 获取或创建某个模型的SEO元数据
     */
    public function getMetadataFor(Model $model): ?SeoMetadata
    {
        return SeoMetadata::where('seoable_type', get_class($model))
            ->where('seoable_id', $model->id)
            ->first();
    }

    /**
     * 更新或创建SEO元数据
     */
    public function upsertMetadata(Model $model, int $tenantId, array $data): SeoMetadata
    {
        $seo = SeoMetadata::updateOrCreate(
            [
                'seoable_type' => get_class($model),
                'seoable_id' => $model->id,
                'tenant_id' => $tenantId,
            ],
            [
                'meta_title' => $data['meta_title'] ?? null,
                'meta_description' => $data['meta_description'] ?? null,
                'meta_keywords' => $data['meta_keywords'] ?? null,
                'canonical_url' => $data['canonical_url'] ?? null,
                'og_title' => $data['og_title'] ?? null,
                'og_description' => $data['og_description'] ?? null,
                'og_image' => $data['og_image'] ?? null,
                'robots' => $data['robots'] ?? 'index,follow',
                'priority' => $data['priority'] ?? '0.5',
                'change_frequency' => $data['change_frequency'] ?? 'monthly',
                'json_ld' => $data['json_ld'] ?? null,
            ]
        );

        return $seo->fresh();
    }

    /**
     * 删除SEO元数据
     */
    public function deleteMetadata(Model $model): bool
    {
        return SeoMetadata::where('seoable_type', get_class($model))
            ->where('seoable_id', $model->id)
            ->delete() > 0;
    }

    /**
     * 获取所有可索引内容（用于站点地图）
     */
    public function getAllIndexableContent(int $tenantId): array
    {
        $entries = [];

        // Pages
        $pages = Page::where('status', 'published')
            ->get(['id', 'slug', 'updated_at']);
        foreach ($pages as $page) {
            $meta = $this->getMetadataFor($page);
            $entries[] = [
                'url' => "/pages/{$page->slug}",
                'updated_at' => $page->updated_at,
                'priority' => $meta?->priority ?? '0.8',
                'change_frequency' => $meta?->change_frequency ?? 'monthly',
                'title' => $meta?->meta_title,
            ];
        }

        // Blog Posts
        $posts = BlogPost::where('tenant_id', $tenantId)
            ->where('is_published', true)
            ->get(['id', 'slug', 'title', 'updated_at']);
        foreach ($posts as $post) {
            $meta = $this->getMetadataFor($post);
            $entries[] = [
                'url' => "/blog/{$post->slug}",
                'updated_at' => $post->updated_at,
                'priority' => $meta?->priority ?? '0.6',
                'change_frequency' => $meta?->change_frequency ?? 'weekly',
                'title' => $meta?->meta_title ?? $post->title,
            ];
        }

        // KB Articles（全局知识库，无 tenant_id）
        $articles = KbArticle::query()
            ->published()
            ->get(['id', 'slug', 'title', 'updated_at']);
        foreach ($articles as $article) {
            $meta = $this->getMetadataFor($article);
            $entries[] = [
                'url' => "/kb/{$article->slug}",
                'updated_at' => $article->updated_at,
                'priority' => $meta?->priority ?? '0.5',
                'change_frequency' => $meta?->change_frequency ?? 'monthly',
                'title' => $meta?->meta_title ?? $article->title,
            ];
        }

        return $entries;
    }

    /**
     * 生成站点地图 XML
     */
    public function generateSitemap(int $tenantId): string
    {
        $entries = $this->getAllIndexableContent($tenantId);
        $baseUrl = url('/');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($entries as $entry) {
            $xml .= "  <url>\n";
            $xml .= "    <loc>" . e($baseUrl . $entry['url']) . "</loc>\n";
            $xml .= "    <lastmod>" . $entry['updated_at']->toW3cString() . "</lastmod>\n";
            $xml .= "    <changefreq>" . $entry['change_frequency'] . "</changefreq>\n";
            $xml .= "    <priority>" . $entry['priority'] . "</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';
        return $xml;
    }

    // ═══════════ URL 重定向管理 ═══════════

    /**
     * 创建重定向
     */
    public function createRedirect(int $tenantId, array $data): UrlRedirect
    {
        $data['tenant_id'] = $tenantId;
        $data['source_url'] = '/' . ltrim($data['source_url'], '/');

        return UrlRedirect::create($data);
    }

    /**
     * 更新重定向
     */
    public function updateRedirect(int $id, array $data): UrlRedirect
    {
        $redirect = UrlRedirect::findOrFail($id);

        if (isset($data['source_url'])) {
            $data['source_url'] = '/' . ltrim($data['source_url'], '/');
        }

        $redirect->update($data);
        $this->clearRedirectCache($redirect->tenant_id);

        return $redirect->fresh();
    }

    /**
     * 删除重定向
     */
    public function deleteRedirect(int $id): bool
    {
        $redirect = UrlRedirect::findOrFail($id);
        $this->clearRedirectCache($redirect->tenant_id);
        return $redirect->delete();
    }

    /**
     * 列表重定向
     */
    public function listRedirects(int $tenantId, array $filters = [], int $perPage = 20)
    {
        $query = UrlRedirect::where('tenant_id', $tenantId)
            ->orderByDesc('hit_count');

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('source_url', 'like', "%{$filters['search']}%")
                  ->orWhere('target_url', 'like', "%{$filters['search']}%");
            });
        }
        if (!empty($filters['status_code'])) {
            $query->where('status_code', $filters['status_code']);
        }
        if (!empty($filters['is_active']) && $filters['is_active'] !== 'all') {
            $query->where('is_active', $filters['is_active'] === 'active');
        }

        return $query->paginate($perPage);
    }

    /**
     * 记录重定向命中
     */
    public function recordRedirectHit(UrlRedirect $redirect): void
    {
        $redirect->increment('hit_count');
        $redirect->update(['last_hit_at' => now()]);
    }

    /**
     * 查找并执行重定向
     */
    public function resolveRedirect(string $path, int $tenantId): ?UrlRedirect
    {
        // 先查缓存
        $cacheKey = "seo_redirect_{$tenantId}_{$path}";
        $redirectId = Cache::get($cacheKey);

        if ($redirectId) {
            $redirect = UrlRedirect::find($redirectId);
            if ($redirect && $redirect->is_active) {
                return $redirect;
            }
        }

        // 精确匹配
        $redirect = UrlRedirect::where('tenant_id', $tenantId)
            ->where('source_url', $path)
            ->where('is_active', true)
            ->first();

        if ($redirect) {
            $expiresAt = now()->addHours(1);
            if (function_exists('now')) {
                Cache::put($cacheKey, $redirect->id, $expiresAt);
            }
            return $redirect;
        }

        // 通配符匹配
        $redirect = UrlRedirect::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('is_wildcard', true)
            ->get()
            ->first(function ($r) use ($path) {
                $pattern = preg_quote($r->source_url, '/');
                $pattern = str_replace(['\*', '\?'], ['.*', '.'], $pattern);
                return preg_match('/^' . $pattern . '$/', $path);
            });

        if ($redirect) {
            return $redirect;
        }

        Cache::put($cacheKey, 0, now()->addMinutes(5));
        return null;
    }

    private function clearRedirectCache(int $tenantId): void
    {
        // 清除所有该租户的重定向缓存（使用通配方式没法精确清除，这里用tag方式或直接忽略）
    }

    // ═══════════ SEO 全局设置 ═══════════

    /**
     * 获取SEO全局设置建议
     */
    public function getGlobalSeoSuggestions(int $tenantId): array
    {
        $suggestions = [];

        // 检查页面是否有meta
        $pagesWithoutMeta = Page::where('status', 'published')
            ->whereDoesntHave('seoMetadata')
            ->count();
        if ($pagesWithoutMeta > 0) {
            $suggestions[] = [
                'type' => 'warning',
                'message' => "有 {$pagesWithoutMeta} 个已发布页面缺少SEO元数据",
            ];
        }

        // 检查重定向是否配置
        $redirectCount = UrlRedirect::where('tenant_id', $tenantId)->count();
        if ($redirectCount === 0) {
            $suggestions[] = [
                'type' => 'info',
                'message' => '尚未配置任何URL重定向规则',
            ];
        }

        // 博客SEO检查
        $postsWithoutMeta = BlogPost::where('is_published', true)
            ->whereDoesntHave('seoMetadata')
            ->count();
        if ($postsWithoutMeta > 0) {
            $suggestions[] = [
                'type' => 'info',
                'message' => "有 {$postsWithoutMeta} 篇博客文章缺少SEO元数据",
            ];
        }

        return $suggestions;
    }

    // ═══════════ 仪表盘统计 ═══════════

    public function getDashboardStats(int $tenantId): array
    {
        $totalMetadata = SeoMetadata::where('tenant_id', $tenantId)->count();
        $totalRedirects = UrlRedirect::where('tenant_id', $tenantId)->count();
        $activeRedirects = UrlRedirect::where('tenant_id', $tenantId)->where('is_active', true)->count();
        $totalHits = UrlRedirect::where('tenant_id', $tenantId)->sum('hit_count');
        $mostHit = UrlRedirect::where('tenant_id', $tenantId)
            ->orderByDesc('hit_count')
            ->first();

        return [
            'total_metadata' => $totalMetadata,
            'total_redirects' => $totalRedirects,
            'active_redirects' => $activeRedirects,
            'total_hits' => $totalHits,
            'most_hit_url' => $mostHit?->source_url,
            'most_hit_count' => $mostHit?->hit_count ?? 0,
        ];
    }

    /**
     * 批量导入重定向
     */
    public function bulkImportRedirects(int $tenantId, array $entries): array
    {
        $imported = 0;
        $skipped = 0;

        foreach ($entries as $entry) {
            if (empty($entry['source']) || empty($entry['target'])) {
                $skipped++;
                continue;
            }

            $exists = UrlRedirect::where('tenant_id', $tenantId)
                ->where('source_url', '/' . ltrim($entry['source'], '/'))
                ->exists();

            if (!$exists) {
                $this->createRedirect($tenantId, [
                    'source_url' => $entry['source'],
                    'target_url' => $entry['target'],
                    'status_code' => $entry['status_code'] ?? 301,
                    'is_active' => $entry['is_active'] ?? true,
                    'is_wildcard' => $entry['is_wildcard'] ?? false,
                    'notes' => $entry['notes'] ?? null,
                ]);
                $imported++;
            } else {
                $skipped++;
            }
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'total' => count($entries),
        ];
    }
}
