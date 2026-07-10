<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperCustomEmoji
 */
class CustomEmoji extends Model
{
    protected $table = 'custom_emojis';

    protected $fillable = [
        'shortcode',
        'image_url',
        'category',
        'aliases',
        'sort_order',
        'is_active',
        'uploaded_by',
        'usage_count',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'usage_count' => 'integer',
        ];
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeByCategory($q, $cat)
    {
        return $q->where('category', $cat);
    }

    /**
     * 获取别名数组
     */
    public function getAliasList(): array
    {
        return $this->aliases ? array_map('trim', explode(',', $this->aliases)) : [];
    }

    /**
     * 注册使用（递增计数）
     */
    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }
}
