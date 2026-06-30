<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\License;
use App\Models\Product;
use App\Models\RecentSearch;
use App\Models\SearchBookmark;
use App\Models\SearchIndex;
use App\Models\SearchPreference;
use App\Models\Subscription;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;

/**
 * 全局搜索引擎服务
 *
 * 提供：
 * - 跨模块统一搜索（所有实体类型）
 * - 搜索索引管理（重建/增量更新）
 * - 最近搜索记录
 * - 搜索收藏
 * - 搜索建议
 * - 搜索偏好设置
 */
class GlobalSearchService
{
    // ─── 统一搜索 ───

    /**
     * 执行统一搜索
     */
    public function search(int $tenantId, int $userId, string $query, array $options = []): array
    {
        $query = trim($query);
        $types = $options['types'] ?? [];
        $page = (int) ($options['page'] ?? 1);
        $perPage = (int) ($options['per_page'] ?? 20);
        $filters = $options['filters'] ?? [];

        if (strlen($query) < 1) {
            return $this->emptyResult();
        }

        // 1. 从索引搜索
        $results = $this->searchIndex($tenantId, $query, $types, $filters, $page, $perPage);

        // 2. 如果索引结果不足，从源表实时搜索
        if (count($results['items']) < 5 && strlen($query) >= 2) {
            $liveResults = $this->searchLive($tenantId, $query, $types, $filters);
            $results = $this->mergeResults($results, $liveResults, $perPage);
        }

        // 3. 记录最近搜索
        if (!empty($results['items'])) {
            $this->recordRecentSearch($userId, $query, $types, $results['total']);
        }

        return $results;
    }

    /**
     * 从搜索索引查询
     */
    protected function searchIndex(int $tenantId, string $query, array $types, array $filters, int $page, int $perPage): array
    {
        $qb = SearchIndex::where('tenant_id', $tenantId);

        // 全文搜索
        $qb->where(function ($q) use ($query) {
            $q->where('title', 'like', "%{$query}%")
              ->orWhere('content', 'like', "%{$query}%")
              ->orWhere('identifier', 'like', "%{$query}%");
        });

        if (!empty($types)) {
            $qb->whereIn('resource_type', $types);
        }

        // 额外过滤
        if (!empty($filters['status'])) {
            $qb->where('status', $filters['status']);
        }
        if (!empty($filters['tag'])) {
            $qb->whereJsonContains('tags', $filters['tag']);
        }
        if (!empty($filters['date_from'])) {
            $qb->where('updated_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $qb->where('updated_at', '<=', $filters['date_to'] . ' 23:59:59');
        }

        $qb->orderByDesc('weight')->orderByDesc('updated_at');

        $total = $qb->count();
        $items = $qb->skip(($page - 1) * $perPage)->take($perPage)->get();

        return [
            'items' => $items->map(function ($idx) {
                return $this->formatResult($idx);
            })->all(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'source' => 'index',
        ];
    }

    /**
     * 从源表实时搜索
     */
    protected function searchLive(int $tenantId, string $query, array $types, array $filters): array
    {
        $items = [];
        $targets = !empty($types) ? $types : SearchIndex::RESOURCE_TYPES;

        foreach ($targets as $type) {
            $limit = 5;
            $results = match ($type) {
                'license' => $this->searchLicenses($tenantId, $query, $limit),
                'customer' => $this->searchCustomers($tenantId, $query, $limit),
                'product' => $this->searchProducts($tenantId, $query, $limit),
                'ticket' => $this->searchTickets($tenantId, $query, $limit),
                'invoice' => $this->searchInvoices($tenantId, $query, $limit),
                'subscription' => $this->searchSubscriptions($tenantId, $query, $limit),
                default => [],
            };

            foreach ($results as $r) {
                $items[] = $r;
            }
        }

        return [
            'items' => $items,
            'total' => count($items),
            'source' => 'live',
        ];
    }

    // ─── 各实体搜索 ───

    protected function searchLicenses(int $tenantId, string $query, int $limit): array
    {
        return License::where('tenant_id', $tenantId)
            ->where(function ($q) use ($query) {
                $q->where('license_key', 'like', "%{$query}%");
            })
            ->limit($limit)
            ->get()
            ->map(fn($l) => [
                'type' => 'license',
                'id' => $l->id,
                'title' => "License: {$l->license_key}",
                'description' => "类型: {$l->type}, 状态: {$l->status}",
                'identifier' => $l->license_key,
                'status' => $l->status,
                'url' => "/licenses/{$l->id}",
                'icon' => 'Key',
                'resource_id' => $l->id,
                'weight' => 80,
            ])
            ->all();
    }

    protected function searchCustomers(int $tenantId, string $query, int $limit): array
    {
        return Customer::where('tenant_id', $tenantId)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('email', 'like', "%{$query}%")
                  ->orWhere('company', 'like', "%{$query}%");
            })
            ->limit($limit)
            ->get()
            ->map(fn($c) => [
                'type' => 'customer',
                'id' => $c->id,
                'title' => $c->name,
                'description' => $c->email . ($c->company ? " - {$c->company}" : ''),
                'identifier' => $c->email,
                'status' => $c->status ?? 'active',
                'url' => "/customers/{$c->id}",
                'icon' => 'User',
                'resource_id' => $c->id,
                'weight' => 70,
            ])
            ->all();
    }

    protected function searchProducts(int $tenantId, string $query, int $limit): array
    {
        return Product::where('tenant_id', $tenantId)
            ->where('name', 'like', "%{$query}%")
            ->limit($limit)
            ->get()
            ->map(fn($p) => [
                'type' => 'product',
                'id' => $p->id,
                'title' => $p->name,
                'description' => $p->description ?? '',
                'identifier' => $p->slug ?? '',
                'status' => $p->is_active ? 'active' : 'inactive',
                'url' => "/products/{$p->id}",
                'icon' => 'Goods',
                'resource_id' => $p->id,
                'weight' => 60,
            ])
            ->all();
    }

    protected function searchTickets(int $tenantId, string $query, int $limit): array
    {
        return Ticket::where('tenant_id', $tenantId)
            ->where(function ($q) use ($query) {
                $q->where('subject', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%");
            })
            ->limit($limit)
            ->get()
            ->map(fn($t) => [
                'type' => 'ticket',
                'id' => $t->id,
                'title' => $t->subject,
                'description' => "优先级: {$t->priority}, 状态: {$t->status}",
                'identifier' => "#{$t->id}",
                'status' => $t->status,
                'url' => "/tickets/{$t->id}",
                'icon' => 'ChatDotSquare',
                'resource_id' => $t->id,
                'weight' => 50,
            ])
            ->all();
    }

    protected function searchInvoices(int $tenantId, string $query, int $limit): array
    {
        return Invoice::where('tenant_id', $tenantId)
            ->where(function ($q) use ($query) {
                $q->where('invoice_no', 'like', "%{$query}%");
            })
            ->limit($limit)
            ->get()
            ->map(fn($i) => [
                'type' => 'invoice',
                'id' => $i->id,
                'title' => "Invoice: {$i->invoice_no}",
                'description' => "金额: ¥{$i->amount}, 状态: {$i->status}",
                'identifier' => $i->invoice_no,
                'status' => $i->status,
                'url' => "/invoices/{$i->id}",
                'icon' => 'Document',
                'resource_id' => $i->id,
                'weight' => 40,
            ])
            ->all();
    }

    protected function searchSubscriptions(int $tenantId, string $query, int $limit): array
    {
        return Subscription::where('tenant_id', $tenantId)
            ->where(function ($q) use ($query) {
                $q->where('plan', 'like', "%{$query}%");
            })
            ->limit($limit)
            ->get()
            ->map(fn($s) => [
                'type' => 'subscription',
                'id' => $s->id,
                'title' => "订阅: {$s->plan}",
                'description' => "状态: {$s->status}, 周期: {$s->billing_period}",
                'identifier' => "{$s->plan}",
                'status' => $s->status,
                'url' => "/subscriptions/{$s->id}",
                'icon' => 'Coin',
                'resource_id' => $s->id,
                'weight' => 30,
            ])
            ->all();
    }

    // ─── 结果格式化 ───

    protected function formatResult(SearchIndex $idx): array
    {
        return [
            'type' => $idx->resource_type,
            'id' => $idx->id,
            'resource_id' => $idx->resource_id,
            'title' => $idx->title,
            'description' => $idx->content ? substr($idx->content, 0, 200) : '',
            'identifier' => $idx->identifier,
            'status' => $idx->status,
            'tags' => $idx->tags,
            'url' => $idx->url,
            'icon' => $this->typeIcon($idx->resource_type),
            'weight' => $idx->weight,
            'source' => 'index',
        ];
    }

    protected function mergeResults(array $primary, array $secondary, int $perPage): array
    {
        $existingIds = [];
        foreach ($primary['items'] as $item) {
            $key = $item['type'] . '_' . $item['resource_id'];
            $existingIds[$key] = true;
        }

        foreach ($secondary['items'] as $item) {
            $key = $item['type'] . '_' . $item['resource_id'];
            if (!isset($existingIds[$key])) {
                $primary['items'][] = $item;
                $existingIds[$key] = true;
            }
        }

        $primary['total'] = count($primary['items']);
        $primary['items'] = array_slice($primary['items'], 0, $perPage);

        return $primary;
    }

    // ─── 搜索建议 ───

    /**
     * 获取搜索建议（基于索引前缀匹配）
     */
    public function suggestions(int $tenantId, string $query, int $limit = 8): array
    {
        if (strlen($query) < 1) return [];

        $results = SearchIndex::where('tenant_id', $tenantId)
            ->where('title', 'like', "{$query}%")
            ->orderByDesc('weight')
            ->limit($limit)
            ->get(['title', 'resource_type', 'identifier']);

        $suggestions = [];
        foreach ($results as $r) {
            $suggestions[] = [
                'text' => $r->title,
                'type' => $r->resource_type,
                'identifier' => $r->identifier,
            ];
        }

        return $suggestions;
    }

    // ─── 搜索索引管理 ───

    /**
     * 重建某个实体的搜索索引
     */
    public function rebuildIndex(string $resourceType, int $tenantId): int
    {
        $count = 0;
        $chunkSize = 100;

        // 清除旧索引
        SearchIndex::where('tenant_id', $tenantId)
            ->where('resource_type', $resourceType)
            ->delete();

        $items = match ($resourceType) {
            'license' => License::where('tenant_id', $tenantId)->chunk($chunkSize, function ($records) use ($tenantId, &$count) {
                foreach ($records as $r) {
                    $this->indexResource('license', $r->id, $tenantId, [
                        'title' => "License: {$r->license_key}",
                        'content' => "类型: {$r->type}, 状态: {$r->status}, 座位: {$r->seats}",
                        'identifier' => $r->license_key,
                        'status' => $r->status,
                        'weight' => 80,
                        'url' => "/licenses/{$r->id}",
                    ]);
                    $count++;
                }
            }),
            'customer' => Customer::where('tenant_id', $tenantId)->chunk($chunkSize, function ($records) use ($tenantId, &$count) {
                foreach ($records as $r) {
                    $this->indexResource('customer', $r->id, $tenantId, [
                        'title' => $r->name,
                        'content' => "{$r->email} - {$r->company}",
                        'identifier' => $r->email,
                        'status' => $r->status ?? 'active',
                        'weight' => 70,
                        'url' => "/customers/{$r->id}",
                    ]);
                    $count++;
                }
            }),
            'product' => Product::where('tenant_id', $tenantId)->chunk($chunkSize, function ($records) use ($tenantId, &$count) {
                foreach ($records as $r) {
                    $this->indexResource('product', $r->id, $tenantId, [
                        'title' => $r->name,
                        'content' => $r->description ?? '',
                        'identifier' => $r->slug ?? '',
                        'status' => $r->is_active ? 'active' : 'inactive',
                        'weight' => 60,
                        'url' => "/products/{$r->id}",
                    ]);
                    $count++;
                }
            }),
            'ticket' => Ticket::where('tenant_id', $tenantId)->chunk($chunkSize, function ($records) use ($tenantId, &$count) {
                foreach ($records as $r) {
                    $this->indexResource('ticket', $r->id, $tenantId, [
                        'title' => $r->subject,
                        'content' => substr($r->description ?? '', 0, 500),
                        'identifier' => "#{$r->id}",
                        'status' => $r->status,
                        'weight' => 50,
                        'url' => "/tickets/{$r->id}",
                    ]);
                    $count++;
                }
            }),
            'invoice' => Invoice::where('tenant_id', $tenantId)->chunk($chunkSize, function ($records) use ($tenantId, &$count) {
                foreach ($records as $r) {
                    $this->indexResource('invoice', $r->id, $tenantId, [
                        'title' => "Invoice: {$r->invoice_no}",
                        'content' => "金额: ¥{$r->amount}, 状态: {$r->status}",
                        'identifier' => $r->invoice_no,
                        'status' => $r->status,
                        'weight' => 40,
                        'url' => "/invoices/{$r->id}",
                    ]);
                    $count++;
                }
            }),
            'subscription' => Subscription::where('tenant_id', $tenantId)->chunk($chunkSize, function ($records) use ($tenantId, &$count) {
                foreach ($records as $r) {
                    $this->indexResource('subscription', $r->id, $tenantId, [
                        'title' => "订阅: {$r->plan}",
                        'content' => "状态: {$r->status}, 周期: {$r->billing_period}, 价格: ¥{$r->price}",
                        'identifier' => $r->plan,
                        'status' => $r->status,
                        'weight' => 30,
                        'url' => "/subscriptions/{$r->id}",
                    ]);
                    $count++;
                }
            }),
            default => null,
        };

        return $count;
    }

    /**
     * 重建所有索引
     */
    public function rebuildAll(int $tenantId): array
    {
        $results = [];
        foreach (SearchIndex::RESOURCE_TYPES as $type) {
            try {
                $count = $this->rebuildIndex($type, $tenantId);
                $results[$type] = $count;
            } catch (\Exception $e) {
                $results[$type] = "error: {$e->getMessage()}";
            }
        }
        return $results;
    }

    /**
     * 索引单个资源
     */
    public function indexResource(string $type, int $resourceId, int $tenantId, array $data): SearchIndex
    {
        return SearchIndex::updateOrCreate(
            ['tenant_id' => $tenantId, 'resource_type' => $type, 'resource_id' => $resourceId],
            [
                'title' => $data['title'] ?? '',
                'content' => $data['content'] ?? null,
                'identifier' => $data['identifier'] ?? null,
                'status' => $data['status'] ?? null,
                'tags' => $data['tags'] ?? null,
                'metadata' => $data['metadata'] ?? null,
                'url' => $data['url'] ?? null,
                'weight' => $data['weight'] ?? 0,
            ]
        );
    }

    // ─── 最近搜索 ───

    public function getRecentSearches(int $userId, int $limit = 10): array
    {
        return RecentSearch::where('user_id', $userId)
            ->orderByDesc('searched_at')
            ->limit($limit)
            ->get()
            ->all();
    }

    public function recordRecentSearch(int $userId, string $query, array $types, int $resultCount): void
    {
        RecentSearch::create([
            'user_id' => $userId,
            'query' => substr($query, 0, 500),
            'resource_type' => !empty($types) ? implode(',', $types) : null,
            'result_count' => $resultCount,
            'searched_at' => now(),
        ]);

        // 保持最近搜索不超过 50 条
        RecentSearch::where('user_id', $userId)
            ->orderByDesc('searched_at')
            ->skip(50)
            ->take(100)
            ->delete();
    }

    public function clearRecentSearches(int $userId): void
    {
        RecentSearch::where('user_id', $userId)->delete();
    }

    public function deleteRecentSearch(int $id, int $userId): void
    {
        RecentSearch::where('id', $id)->where('user_id', $userId)->delete();
    }

    // ─── 搜索收藏 ───

    public function getBookmarks(int $userId, int $tenantId): array
    {
        return SearchBookmark::where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->get()
            ->all();
    }

    public function toggleBookmark(int $userId, int $tenantId, string $type, int $resourceId, ?string $label = null): array
    {
        $existing = SearchBookmark::where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->where('resource_type', $type)
            ->where('resource_id', $resourceId)
            ->first();

        if ($existing) {
            $existing->delete();
            return ['bookmarked' => false, 'message' => '已取消收藏'];
        }

        SearchBookmark::create([
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'resource_type' => $type,
            'resource_id' => $resourceId,
            'label' => $label,
        ]);

        return ['bookmarked' => true, 'message' => '已收藏'];
    }

    public function deleteBookmark(int $id, int $userId): void
    {
        SearchBookmark::where('id', $id)->where('user_id', $userId)->delete();
    }

    // ─── 搜索偏好 ───

    public function getPreferences(int $userId): SearchPreference
    {
        return SearchPreference::forUser($userId);
    }

    public function updatePreferences(int $userId, array $data): SearchPreference
    {
        $prefs = SearchPreference::forUser($userId);
        $prefs->update($data);
        return $prefs->fresh();
    }

    // ─── 仪表盘 ───

    public function getDashboard(int $userId, int $tenantId): array
    {
        $indexCount = SearchIndex::where('tenant_id', $tenantId)->count();
        $recentCount = RecentSearch::where('user_id', $userId)->count();
        $bookmarkCount = SearchBookmark::where('user_id', $userId)->count();

        $byType = SearchIndex::where('tenant_id', $tenantId)
            ->selectRaw('resource_type, COUNT(*) as cnt')
            ->groupBy('resource_type')
            ->orderByDesc('cnt')
            ->get()
            ->pluck('cnt', 'resource_type')
            ->toArray();

        $recent = $this->getRecentSearches($userId, 5);
        $bookmarks = $this->getBookmarks($userId, $tenantId);
        $prefs = $this->getPreferences($userId);

        return [
            'stats' => [
                'total_indexed' => $indexCount,
                'total_recent' => $recentCount,
                'total_bookmarks' => $bookmarkCount,
            ],
            'by_type' => $byType,
            'recent_searches' => $recent,
            'bookmarks' => $bookmarks,
            'preferences' => $prefs,
        ];
    }

    // ─── 辅助 ───

    protected function typeIcon(string $type): string
    {
        $icons = [
            'license' => 'Key',
            'customer' => 'User',
            'product' => 'Goods',
            'ticket' => 'ChatDotSquare',
            'invoice' => 'Document',
            'subscription' => 'Coin',
            'user' => 'Avatar',
            'api_key' => 'Lock',
            'webhook' => 'Link',
            'log' => 'List',
            'device' => 'Monitor',
        ];
        return $icons[$type] ?? 'Search';
    }

    protected function emptyResult(): array
    {
        return ['items' => [], 'total' => 0, 'source' => 'none'];
    }
}
