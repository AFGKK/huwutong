<?php

namespace App\Services;

use App\Models\Tag;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TagService
{
    /**
     * 获取所有标签，支持按分组分组
     */
    public function getAll(?string $group = null, ?string $search = null): Collection
    {
        $query = Tag::orderBy('group')->orderBy('name');

        if ($group) {
            $query->where('group', $group);
        }
        if ($search) {
            $query->search($search);
        }

        return $query->get();
    }

    /**
     * 按分组聚合所有标签
     */
    public function getGrouped(): Collection
    {
        return Tag::orderBy('group')->orderBy('name')->get()->groupBy(function ($tag) {
            return $tag->group ?? '_ungrouped';
        });
    }

    /**
     * 创建标签
     */
    public function create(array $data): Tag
    {
        $data['slug'] = Tag::generateUniqueSlug($data['name']);

        return DB::transaction(function () use ($data) {
            $tag = Tag::create($data);

            Log::info('标签已创建', [
                'tag_id' => $tag->id,
                'name' => $tag->name,
                'group' => $tag->group,
            ]);

            return $tag;
        });
    }

    /**
     * 更新标签
     */
    public function update(Tag $tag, array $data): Tag
    {
        return DB::transaction(function () use ($tag, $data) {
            if (isset($data['name']) && $data['name'] !== $tag->name) {
                $data['slug'] = Tag::generateUniqueSlug($data['name']);
            }

            $tag->update($data);

            Log::info('标签已更新', [
                'tag_id' => $tag->id,
                'name' => $tag->name,
            ]);

            return $tag->fresh();
        });
    }

    /**
     * 删除标签（系统标签不允许删除）
     */
    public function delete(Tag $tag): bool
    {
        if ($tag->is_system) {
throw new \RuntimeException(__("app.tag.system_tag_cannot_delete"));
        }

        return DB::transaction(function () use ($tag) {
            // 自动 cascade 删除 taggables 关联
            $tag->delete();

            Log::info('标签已删除', [
                'tag_id' => $tag->id,
                'name' => $tag->name,
            ]);

            return true;
        });
    }

    /**
     * 批量创建预置标签（用于系统初始化/seeder）
     */
    public function seedPresetTags(): void
    {
        $presets = [
            // 优先级
            ['name' => '紧急', 'slug' => 'urgent', 'color' => '#F56C6C', 'group' => 'priority', 'is_system' => true],
            ['name' => '高', 'slug' => 'high', 'color' => '#E6A23C', 'group' => 'priority', 'is_system' => true],
            ['name' => '中', 'slug' => 'medium', 'color' => '#0f172a', 'group' => 'priority', 'is_system' => true],
            ['name' => '低', 'slug' => 'low', 'color' => '#909399', 'group' => 'priority', 'is_system' => true],

            // 工单状态
            ['name' => '待处理', 'slug' => 'pending', 'color' => '#F56C6C', 'group' => 'status', 'is_system' => true],
            ['name' => '处理中', 'slug' => 'processing', 'color' => '#E6A23C', 'group' => 'status', 'is_system' => true],
            ['name' => '已解决', 'slug' => 'resolved', 'color' => '#67C23A', 'group' => 'status', 'is_system' => true],

            // 类型标签
            ['name' => 'Bug', 'slug' => 'bug', 'color' => '#F56C6C', 'group' => 'type', 'is_system' => false],
            ['name' => '功能请求', 'slug' => 'feature-request', 'color' => '#0f172a', 'group' => 'type', 'is_system' => false],
            ['name' => '咨询', 'slug' => 'inquiry', 'color' => '#67C23A', 'group' => 'type', 'is_system' => false],

            // License 相关
            ['name' => 'VIP 客户', 'slug' => 'vip', 'color' => '#E040FB', 'group' => 'tier', 'is_system' => false],
            ['name' => '试用用户', 'slug' => 'trial', 'color' => '#00BCD4', 'group' => 'tier', 'is_system' => false],
            ['name' => '即将过期', 'slug' => 'expiring-soon', 'color' => '#FF9800', 'group' => 'alert', 'is_system' => false],
        ];

        foreach ($presets as $data) {
            Tag::firstOrCreate(
                ['slug' => $data['slug']],
                $data
            );
        }
    }

    /**
     * 获取最常用的标签（按关联数量排序）
     */
    public function getPopular(int $limit = 10): Collection
    {
        return Tag::withCount('tickets')
            ->withCount('licenses')
            ->withCount('customers')
            ->orderByDesc(\DB::raw('(SELECT COUNT(*) FROM taggables WHERE taggables.tag_id = tags.id)'))
            ->limit($limit)
            ->get();
    }
}
