<?php

namespace App\Models\Concerns;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasTags
{
    /**
     * Boot trait: cascade delete pivot when model is deleted.
     */
    protected static function bootHasTags(): void
    {
        static::deleting(function ($model) {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }
            $model->tags()->detach();
        });
    }

    /**
     * 获取该模型的所有标签
     */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    /**
     * 同步标签（按名称数组）
     */
    public function syncTags(array $tagNames, ?string $group = null): array
    {
        $tagIds = [];
        foreach ($tagNames as $name) {
            $tag = Tag::getOrCreate(trim($name), $group);
            $tagIds[] = $tag->id;
        }
        $this->tags()->sync($tagIds);
        return $tagIds;
    }

    /**
     * 附加标签
     */
    public function attachTag(string|Tag $tag): void
    {
        $id = $tag instanceof Tag ? $tag->id : (Tag::getOrCreate($tag))->id;
        $this->tags()->syncWithoutDetaching([$id]);
    }

    /**
     * 移除标签
     */
    public function detachTag(string|Tag $tag): void
    {
        $id = $tag instanceof Tag ? $tag->id : Tag::where('slug', slugify($tag))->value('id');
        if ($id) {
            $this->tags()->detach($id);
        }
    }

    /**
     * 判断是否有指定标签
     */
    public function hasTag(string $slug): bool
    {
        return $this->tags()->where('slug', $slug)->exists();
    }
}
