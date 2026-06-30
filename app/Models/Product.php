<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory, \App\Models\Concerns\HasProductTranslations, \App\Models\Concerns\HasTags;
    protected $fillable = [
        'name', 'slug', 'category_id', 'description', 'long_description', 'image_url', 'images',
        'version', 'modules', 'is_active', 'user_id', 'merchant_id',
        'is_sellable', 'base_price', 'sales_count', 'tags',
        'demo_enabled', 'demo_images', 'is_featured',
    ];

    protected $appends = ['review_stats'];

    protected function casts(): array
    {
        return [
            'modules' => 'array',
            'images' => 'array',
            'tags' => 'array',
            'is_active' => 'boolean',
            'is_sellable' => 'boolean',
            'demo_enabled' => 'boolean',
            'demo_images' => 'array',
        'is_featured' => 'boolean',
        ];
    }

    public function demos()
    {
        return $this->hasMany(ProductDemo::class)->orderBy('sort_order');
    }

    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function licenses()
    {
        return $this->hasMany(License::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function featureFlags()
    {
        return $this->belongsToMany(FeatureFlag::class, 'product_feature_flag');
    }

    public function specGroups()
    {
        return $this->hasMany(ProductSpecGroup::class);
    }

    public function specValues()
    {
        return $this->hasMany(ProductSpecValue::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function skus()
    {
        return $this->hasMany(ProductSku::class);
    }

    public function reviews()
    {
        return $this->hasMany(ProductReview::class);
    }

    public function approvedReviews()
    {
        return $this->hasMany(ProductReview::class)->where('status', 'approved');
    }

    public function getReviewStatsAttribute(): array
    {
        $reviews = $this->relationLoaded('reviews') ? $this->reviews : null;
        if (!$reviews) return ['avg_rating' => 0, 'total' => 0, 'distribution' => []];
        $total = $reviews->count();
        if ($total === 0) return ['avg_rating' => 0, 'total' => 0, 'distribution' => []];
        $avg = round($reviews->avg('rating'), 1);
        $dist = [];
        for ($i = 1; $i <= 5; $i++) {
            $count = $reviews->where('rating', $i)->count();
            $dist[$i] = ['count' => $count, 'percent' => $total > 0 ? round($count / $total * 100) : 0];
        }
        return ['avg_rating' => $avg, 'total' => $total, 'distribution' => $dist];
    }
}
