<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductComparison extends Model
{
    protected $fillable = [
        'user_id', 'session_id', 'name',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductComparisonItem::class, 'comparison_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_comparison_items', 'comparison_id', 'product_id')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }
}
