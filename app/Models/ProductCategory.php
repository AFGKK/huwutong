<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductCategory extends Model
{
    protected $fillable = [
        'tenant_id', 'name', 'slug', 'description', 'icon', 'image_url',
        'parent_id', 'sort_order', 'is_active', 'meta_title', 'meta_description',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * 获取分类树（含子分类）
     */
    public static function tree(int $tenantId = null): array
    {
        $query = self::with(['children' => fn($q) => $q->orderBy('sort_order')])
            ->withCount('products')
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->orWhereNull('tenant_id')
            ->root()
            ->orderBy('sort_order');

        return $query->get()->toArray();
    }

    /**
     * 获取所有子分类 ID（含自身）
     */
    public function descendantIds(): array
    {
        $ids = [$this->id];
        foreach ($this->children as $child) {
            $ids = array_merge($ids, $child->descendantIds());
        }
        return $ids;
    }
}
