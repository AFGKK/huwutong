<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;
    protected $fillable = [
        'name', 'slug', 'description', 'version', 'modules', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'modules' => 'array',
            'is_active' => 'boolean',
        ];
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
}
