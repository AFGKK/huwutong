<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperSavedSearch
 */
class SavedSearch extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'page',
        'filters',
        'columns',
        'sort',
        'is_shared',
        'sort_order',
        'icon',
        'color',
        'usage_count',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'columns' => 'array',
            'sort' => 'array',
            'is_shared' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 可用页面类型（扩展为更全面的搜索范围）
     */
    public static array $pages = [
        'licenses',
        'customers',
        'tickets',
        'products',
        'invoices',
        'subscriptions',
        'global',
    ];

    /**
     * 页面图标映射
     */
    public static array $pageIcons = [
        'licenses' => 'Key',
        'customers' => 'User',
        'tickets' => 'ChatDotSquare',
        'products' => 'Goods',
        'invoices' => 'Document',
        'subscriptions' => 'Coin',
        'global' => 'Search',
    ];

    /**
     * 页面中文名映射
     */
    public static array $pageLabels = [
        'licenses' => '许可证',
        'customers' => '客户',
        'tickets' => '工单',
        'products' => '产品',
        'invoices' => '发票',
        'subscriptions' => '订阅',
        'global' => '全局搜索',
    ];

    /**
     * 记录一次使用
     */
    public function recordUsage(): void
    {
        $this->increment('usage_count');
        $this->update(['last_used_at' => now()]);
    }

    /**
     * 获取常用搜索（按使用次数排序）
     */
    public static function frequentlyUsed(int $userId, int $limit = 5): array
    {
        return self::where('user_id', $userId)
            ->where('usage_count', '>', 0)
            ->orderByDesc('usage_count')
            ->orderByDesc('last_used_at')
            ->limit($limit)
            ->get()
            ->all();
    }
}
