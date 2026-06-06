<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class KbCategory extends Model
{
    protected $fillable = [
        'parent_id', 'name', 'slug', 'description',
        'sort_order', 'is_active', 'locale',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (KbCategory $category) {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name) . '-' . Str::random(5);
            }
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(__CLASS__, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(__CLASS__, 'parent_id');
    }

    public function articles(): HasMany
    {
        return $this->hasMany(KbArticle::class, 'category_id');
    }

    public function publishedArticles(): HasMany
    {
        return $this->hasMany(KbArticle::class, 'category_id')
            ->where('status', 'published');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    public function articleCount(): int
    {
        return $this->articles()->where('status', 'published')->count();
    }
}
