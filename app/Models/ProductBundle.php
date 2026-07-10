<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @mixin IdeHelperProductBundle
 */
class ProductBundle extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'slug', 'description', 'image',
        'bundle_price', 'original_price', 'currency', 'billing_period',
        'stock', 'max_purchase_per_user',
        'is_active', 'is_featured', 'sort_order', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'bundle_price' => 'decimal:2',
            'original_price' => 'decimal:2',
            'stock' => 'integer',
            'max_purchase_per_user' => 'integer',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ProductBundle $bundle) {
            if (empty($bundle->slug)) {
                $bundle->slug = Str::slug($bundle->name) . '-' . Str::random(6);
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BundleItem::class, 'product_bundle_id');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(BundlePurchase::class, 'product_bundle_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function getDiscountPercentAttribute(): float
    {
        if (!$this->original_price || $this->original_price <= 0) return 0;
        return round((1 - $this->bundle_price / $this->original_price) * 100, 1);
    }

    public function getSavingsAttribute(): float
    {
        return round(($this->original_price ?? 0) - $this->bundle_price, 2);
    }

    public function hasStock(): bool
    {
        if ($this->stock === null) return true;
        return $this->stock > 0;
    }

    public function decrementStock(int $quantity = 1): void
    {
        if ($this->stock !== null) {
            $this->decrement('stock', $quantity);
        }
    }
}
