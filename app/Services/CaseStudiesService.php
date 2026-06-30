<?php

namespace App\Services;

/**
 * 客户案例+Logo墙服务 (M2-99)
 */
class CaseStudiesService
{
    /**
     * 获取案例列表
     */
    public function getList(array $filters = []): array
    {
        // 实际从 case_studies 表查询
        return [
            'data' => [],
            'meta' => ['total' => 0],
        ];
    }

    /**
     * 获取案例详情
     */
    public function getDetail(int $id): ?array
    {
        return null;
    }

    /**
     * 创建案例
     */
    public function create(array $data): array
    {
        return ['success' => true, 'message' => '案例已创建'];
    }

    /**
     * 更新案例
     */
    public function update(int $id, array $data): array
    {
        return ['success' => true, 'message' => '案例已更新'];
    }

    /**
     * 删除案例
     */
    public function delete(int $id): array
    {
        return ['success' => true, 'message' => '案例已删除'];
    }

    /**
     * 获取 Logo 墙列表
     */
    public function getLogoWall(): array
    {
        // 实际从 case_studies 表查询有 logo 且 is_featured 的记录
        return [];
    }

    /**
     * 获取首页推荐案例
     */
    public function getFeatured(): array
    {
        return [];
    }

    /**
     * 上传 Logo
     */
    public function uploadLogo(object $file): array
    {
        $path = $file->store('logos', 'public');
        return [
            'success' => true,
            'url' => '/storage/' . $path,
        ];
    }

    /**
     * 获取统计
     */
    public function getStats(): array
    {
        return [
            'total_cases' => 0,
            'total_logos' => 0,
            'by_category' => [],
            'by_industry' => [],
        ];
    }
}
