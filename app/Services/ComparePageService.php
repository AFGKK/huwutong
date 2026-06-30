<?php

namespace App\Services;

/**
 * 竞品对比页服务 (M2-100)
 */
class ComparePageService
{
    /**
     * 获取竞品对比数据
     */
    public function getComparison(): array
    {
        $competitors = config('compare-page.competitors', []);
        $dimensions = config('compare-page.dimensions', []);
        $comparisonData = config('compare-page.comparison_data', []);

        // 构建矩阵
        $matrix = [];
        foreach ($dimensions as $key => $dimension) {
            $row = [
                'key' => $key,
                'label' => $dimension['label'],
                'type' => $dimension['type'],
                'huwutong' => $this->formatValue($comparisonData[$key]['huwutong'] ?? '-', $dimension['type']),
            ];
            foreach ($competitors as $compKey => $comp) {
                $row[$compKey] = $this->formatValue($comparisonData[$key][$compKey] ?? '-', $dimension['type']);
            }
            $matrix[] = $row;
        }

        return [
            'competitors' => $competitors,
            'dimensions' => $dimensions,
            'matrix' => $matrix,
            'seo' => config('compare-page.seo', []),
        ];
    }

    /**
     * 格式化对比值
     */
    protected function formatValue($value, string $type): array
    {
        if ($type === 'boolean') {
            return [
                'raw' => $value,
                'display' => $value ? '✅' : '❌',
                'supported' => (bool) $value,
            ];
        }
        return [
            'raw' => $value,
            'display' => (string) $value,
            'supported' => !empty($value) && $value !== '-',
        ];
    }

    /**
     * 获取优势摘要
     */
    public function getAdvantages(): array
    {
        $dimensions = config('compare-page.dimensions', []);
        $comparisonData = config('compare-page.comparison_data', []);
        $competitors = config('compare-page.competitors', []);

        $advantages = [];

        foreach ($dimensions as $key => $dimension) {
            $hwValue = $comparisonData[$key]['huwutong'] ?? false;
            if ($dimension['type'] === 'boolean' && !$hwValue) {
                continue;
            }

            $allSupported = true;
            foreach ($competitors as $compKey => $comp) {
                $compVal = $comparisonData[$key][$compKey] ?? false;
                if ($dimension['type'] === 'boolean' && $compVal) {
                    $allSupported = false;
                    break;
                }
            }

            if ($dimension['type'] === 'boolean') {
                if (!$allSupported) {
                    $advantages[] = [
                        'feature' => $dimension['label'],
                        'type' => 'unique',
                        'description' => "互物通是唯一支持 {$dimension['label']} 的平台",
                    ];
                }
            } elseif ($dimension['type'] === 'text' && is_string($hwValue)) {
                $advantages[] = [
                    'feature' => $dimension['label'],
                    'type' => 'best',
                    'description' => $hwValue,
                ];
            }
        }

        return $advantages;
    }

    /**
     * 获取竞品列表
     */
    public function getCompetitors(): array
    {
        return config('compare-page.competitors', []);
    }
}
