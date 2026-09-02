<?php

namespace App\Services;

use App\Models\FooterNavItem;
use Illuminate\Support\Facades\Log;

/**
 * 页脚导航配置服务 (M2-85)
 */
class FooterNavService
{
    /**
     * 获取所有页脚链接（按组）
     */
    public function getAll(): array
    {
        $items = FooterNavItem::ordered()->get();
        return [
            'items' => $items->toArray(),
            'grouped' => $items->groupBy('group')->toArray(),
        ];
    }

    /**
     * 获取前端可见的页脚链接
     */
    public function getPublic(): array
    {
        $items = FooterNavItem::active()->ordered()->get()->groupBy('group');

        $result = [];
        foreach (['footer', 'social', 'bottom'] as $group) {
            $result[$group] = $items->get($group, collect())->toArray();
        }

        return $result;
    }

    /**
     * 创建链接
     */
    public function create(array $data): FooterNavItem
    {
        $maxOrder = FooterNavItem::max('sort_order') ?? 0;
        $data['sort_order'] = $data['sort_order'] ?? ($maxOrder + 10);

        return FooterNavItem::create($data);
    }

    /**
     * 更新链接
     */
    public function update(int $id, array $data): FooterNavItem
    {
        $item = FooterNavItem::findOrFail($id);
        $item->update($data);
        return $item->fresh();
    }

    /**
     * 删除链接
     */
    public function delete(int $id): bool
    {
        return FooterNavItem::findOrFail($id)->delete() ? true : false;
    }

    /**
     * 批量更新排序（拖拽排序）
     */
    public function reorder(array $items): bool
    {
        try {
            foreach ($items as $index => $itemData) {
                $id = $itemData['id'] ?? null;
                $sortOrder = $itemData['sort_order'] ?? (($index + 1) * 10);

                if ($id) {
                    FooterNavItem::where('id', $id)->update(['sort_order' => $sortOrder]);
                }
            }
            return true;
        } catch (\Exception $e) {
            Log::warning('Footer nav reorder failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 切换启用/禁用
     */
    public function toggle(int $id): FooterNavItem
    {
        $item = FooterNavItem::findOrFail($id);
        $item->update(['is_active' => !$item->is_active]);
        return $item->fresh();
    }

    /**
     * 批量初始化默认链接
     */
    public function initDefaults(): int
    {
        $defaults = config('footer-nav.default_links', []);
        $count = 0;

        foreach ($defaults as $link) {
            $existing = FooterNavItem::where('label', $link['label'])->first();
            if ($existing) {
                $legalPaths = collect($defaults)
                    ->filter(fn (array $item): bool => in_array($item['url'] ?? '', ['/terms', '/privacy', '/security-policy', '/cookie-policy'], true))
                    ->pluck('url')
                    ->all();
                if (in_array($existing->url, $legalPaths, true) && ($existing->group ?? '') !== ($link['group'] ?? '')) {
                    $existing->update([
                        'group' => $link['group'],
                        'sort_order' => $link['sort_order'] ?? $existing->sort_order,
                    ]);
                }

                continue;
            }
            $this->create($link);
            $count++;
        }

        return $count;
    }

    /**
     * 获取链接类型选项
     */
    public function getLinkTypes(): array
    {
        return config('footer-nav.link_types', []);
    }

    /**
     * 获取社交媒体平台选项
     */
    public function getSocialPlatforms(): array
    {
        return config('footer-nav.social_platforms', []);
    }
}
