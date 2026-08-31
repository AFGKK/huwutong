<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 竞品对比页服务 (M2-100)
 *
 * 优先读 SiteSetting `compare_page` JSON，缺失时回退 config/compare-page.php。
 */
class ComparePageService
{
    public const SETTING_KEY = 'compare_page';

    /**
     * 原始配置（供公开页 / 管理编辑）
     */
    public function rawConfig(): array
    {
        $fromDb = $this->loadFromSiteSetting();
        if (is_array($fromDb) && $fromDb !== []) {
            return $this->normalizeConfig($fromDb);
        }

        return $this->normalizeConfig(config('compare-page', []));
    }

    /**
     * 获取竞品对比数据
     */
    public function getComparison(): array
    {
        $config = $this->rawConfig();
        $competitors = $config['competitors'] ?? [];
        $dimensions = $config['dimensions'] ?? [];
        $comparisonData = $config['comparison_data'] ?? [];

        $matrix = [];
        foreach ($dimensions as $key => $dimension) {
            $row = [
                'key' => $key,
                'label' => $dimension['label'] ?? $key,
                'type' => $dimension['type'] ?? 'text',
                'huwutong' => $this->formatValue($comparisonData[$key]['huwutong'] ?? '-', $dimension['type'] ?? 'text'),
            ];
            foreach ($competitors as $compKey => $comp) {
                $row[$compKey] = $this->formatValue($comparisonData[$key][$compKey] ?? '-', $dimension['type'] ?? 'text');
            }
            $matrix[] = $row;
        }

        return [
            'competitors' => $competitors,
            'dimensions' => $dimensions,
            'matrix' => $matrix,
            'seo' => $config['seo'] ?? [],
            'source' => $this->loadFromSiteSetting() ? 'site_setting' : 'config',
        ];
    }

    /**
     * 保存完整配置到 SiteSetting
     */
    public function update(array $payload): array
    {
        $current = $this->rawConfig();
        $merged = [
            'competitors' => $payload['competitors'] ?? $current['competitors'] ?? [],
            'dimensions' => $payload['dimensions'] ?? $current['dimensions'] ?? [],
            'comparison_data' => $payload['comparison_data'] ?? $current['comparison_data'] ?? [],
            'seo' => $payload['seo'] ?? $current['seo'] ?? [],
        ];
        $merged = $this->normalizeConfig($merged);

        SiteSetting::updateOrCreate(
            ['key' => self::SETTING_KEY],
            [
                'group' => 'marketing',
                'value' => json_encode($merged, JSON_UNESCAPED_UNICODE),
                'type' => 'textarea',
                'description' => '竞品对比页内容（JSON）',
                'is_public' => false,
            ]
        );

        Cache::forget('site_settings_all');

        return $merged;
    }

    /**
     * 从文件配置同步到 DB（幂等种子）
     */
    public function syncFromConfigFile(bool $force = false): array
    {
        $existing = $this->loadFromSiteSetting();
        if ($existing !== null && ! $force) {
            return $existing;
        }

        return $this->update(config('compare-page', []));
    }

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
            'supported' => ! empty($value) && $value !== '-',
        ];
    }

    public function getAdvantages(): array
    {
        $config = $this->rawConfig();
        $dimensions = $config['dimensions'] ?? [];
        $comparisonData = $config['comparison_data'] ?? [];
        $competitors = $config['competitors'] ?? [];

        $advantages = [];

        foreach ($dimensions as $key => $dimension) {
            $hwValue = $comparisonData[$key]['huwutong'] ?? false;
            $type = $dimension['type'] ?? 'text';
            if ($type === 'boolean' && ! $hwValue) {
                continue;
            }

            $allSupported = true;
            foreach ($competitors as $compKey => $comp) {
                $compVal = $comparisonData[$key][$compKey] ?? false;
                if ($type === 'boolean' && $compVal) {
                    $allSupported = false;
                    break;
                }
            }

            if ($type === 'boolean') {
                if (! $allSupported) {
                    $advantages[] = [
                        'feature' => $dimension['label'] ?? $key,
                        'type' => 'unique',
                        'description' => '互物通是唯一支持 '.($dimension['label'] ?? $key).' 的平台',
                    ];
                }
            } elseif ($type === 'text' && is_string($hwValue)) {
                $advantages[] = [
                    'feature' => $dimension['label'] ?? $key,
                    'type' => 'best',
                    'description' => $hwValue,
                ];
            }
        }

        return $advantages;
    }

    public function getCompetitors(): array
    {
        return $this->rawConfig()['competitors'] ?? [];
    }

    protected function loadFromSiteSetting(): ?array
    {
        try {
            if (! function_exists('site_setting')) {
                return null;
            }
            $raw = site_setting(self::SETTING_KEY, '');
            if ($raw === '' || $raw === null) {
                // 直接查库，避免空字符串被别名逻辑干扰
                $row = SiteSetting::where('key', self::SETTING_KEY)->value('value');
                $raw = $row ?? '';
            }
            if (! is_string($raw) || trim($raw) === '') {
                return null;
            }
            $decoded = json_decode($raw, true);
            if (! is_array($decoded)) {
                return null;
            }

            return $decoded;
        } catch (\Throwable $e) {
            Log::debug('ComparePageService load failed: '.$e->getMessage());

            return null;
        }
    }

    protected function normalizeConfig(array $config): array
    {
        return [
            'competitors' => is_array($config['competitors'] ?? null) ? $config['competitors'] : [],
            'dimensions' => is_array($config['dimensions'] ?? null) ? $config['dimensions'] : [],
            'comparison_data' => is_array($config['comparison_data'] ?? null) ? $config['comparison_data'] : [],
            'seo' => is_array($config['seo'] ?? null) ? $config['seo'] : [],
        ];
    }
}
