<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Page extends Model
{
    protected $fillable = [
        'slug', 'title', 'content', 'locale', 'status',
        'meta', 'version', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /**
     * 发布
     */
    public function publish(): void
    {
        $this->update([
            'status' => 'published',
            'version' => $this->version + 1,
            'published_at' => now(),
        ]);
    }

    /**
     * 撤回为草稿
     */
    public function draft(): void
    {
        $this->update([
            'status' => 'draft',
            'published_at' => null,
        ]);
    }

    public function seoMetadata(): MorphOne
    {
        return $this->morphOne(SeoMetadata::class, 'seoable');
    }
}
