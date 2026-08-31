<?php

namespace App\Services;

use App\Models\PortalBrandingConfig;

/**
 * 门户品牌化服务
 *
 * 管理客户门户的自定义主题、品牌配置、CSS 变量生成
 */
class PortalBrandingService
{
    /**
     * 获取租户的品牌配置（当前语言）
     */
    public function getConfig(?int $tenantId, string $locale = 'zh-CN'): ?PortalBrandingConfig
    {
        $query = PortalBrandingConfig::where('is_active', true);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $query->where('locale', $locale);

        $config = $query->first();

        // 回退到默认
        if (!$config) {
            $config = PortalBrandingConfig::where('is_default', true)->first();
        }

        return $config;
    }

    /**
     * 获取或创建租户的品牌配置
     */
    public function getOrCreateConfig(int $tenantId, string $locale = 'zh-CN'): PortalBrandingConfig
    {
        $config = PortalBrandingConfig::where('tenant_id', $tenantId)
            ->where('locale', $locale)
            ->first();

        if (!$config) {
            $default = PortalBrandingConfig::where('is_default', true)->first();

            $config = PortalBrandingConfig::create([
                'tenant_id' => $tenantId,
                'locale' => $locale,
                'brand_name' => $default?->brand_name,
                'primary_color' => $default?->primary_color ?? '#0f172a',
                'secondary_color' => $default?->secondary_color ?? '#67c23a',
                'background_color' => $default?->background_color ?? '#f5f7fa',
                'text_color' => $default?->text_color ?? '#303133',
                'link_color' => $default?->link_color ?? '#0f172a',
                'header_bg_color' => $default?->header_bg_color ?? '#ffffff',
                'sidebar_bg_color' => $default?->sidebar_bg_color ?? '#304156',
                'sidebar_text_color' => $default?->sidebar_text_color ?? '#bfcbd9',
                'button_radius' => $default?->button_radius ?? '4px',
                'is_active' => true,
            ]);
        }

        return $config;
    }

    /**
     * 更新品牌配置
     */
    public function updateConfig(int $tenantId, string $locale, array $data): PortalBrandingConfig
    {
        $config = $this->getOrCreateConfig($tenantId, $locale);
        $config->update($data);
        return $config->fresh();
    }

    /**
     * 重置为默认配置
     */
    public function resetToDefault(int $tenantId, string $locale = 'zh-CN'): PortalBrandingConfig
    {
        $config = PortalBrandingConfig::where('tenant_id', $tenantId)
            ->where('locale', $locale)
            ->first();

        if ($config) {
            $default = PortalBrandingConfig::where('is_default', true)->first();
            if ($default) {
                $config->update($default->only([
                    'brand_name', 'brand_slogan', 'logo_url', 'favicon_url',
                    'primary_color', 'secondary_color', 'background_color',
                    'text_color', 'link_color', 'header_bg_color',
                    'sidebar_bg_color', 'sidebar_text_color',
                    'button_radius', 'font_family',
                    'custom_css', 'header_html', 'footer_html',
                    'login_page_title', 'login_page_subtitle', 'login_bg_image',
                    'footer_text', 'links', 'social_links',
                ]));
            }
        }

        return $this->getOrCreateConfig($tenantId, $locale);
    }

    /**
     * 获取品牌 CSS 变量字符串
     */
    public function getCssVariables(?int $tenantId, string $locale = 'zh-CN'): string
    {
        $config = $this->getConfig($tenantId, $locale);

        if (!$config) {
            return '';
        }

        $vars = $config->toCssVariables();
        $lines = [':root {'];
        foreach ($vars as $key => $value) {
            $lines[] = "  {$key}: {$value};";
        }
        $lines[] = '}';

        if ($config->custom_css) {
            $lines[] = '';
            $lines[] = $config->custom_css;
        }

        return implode("\n", $lines);
    }

    /**
     * 获取品牌配置（含 CSS 变量展开）
     */
    public function getBrandingData(?int $tenantId, string $locale = 'zh-CN'): array
    {
        $config = $this->getConfig($tenantId, $locale);

        if (!$config) {
            return [
                'config' => null,
                'css_variables' => [],
                'css_string' => '',
            ];
        }

        return [
            'config' => $config->toArray(),
            'css_variables' => $config->toCssVariables(),
            'css_string' => $this->getCssVariables($tenantId, $locale),
        ];
    }

    /**
     * 获取所有可用主题模板
     */
    public function getThemeTemplates(): array
    {
        return [
            [
                'id' => 'default',
                'name' => 'Slate',
                'primary_color' => '#0f172a',
                'secondary_color' => '#67c23a',
                'background_color' => '#f5f7fa',
                'text_color' => '#303133',
                'sidebar_bg_color' => '#304156',
            ],
            [
                'id' => 'ocean',
                'name' => '海洋蓝',
                'primary_color' => '#1890ff',
                'secondary_color' => '#52c41a',
                'background_color' => '#e6f7ff',
                'text_color' => '#262626',
                'sidebar_bg_color' => '#003a8c',
            ],
            [
                'id' => 'forest',
                'name' => '森林绿',
                'primary_color' => '#13c2c2',
                'secondary_color' => '#73d13d',
                'background_color' => '#f0fff0',
                'text_color' => '#1f1f1f',
                'sidebar_bg_color' => '#00474f',
            ],
            [
                'id' => 'sunset',
                'name' => '日落橙',
                'primary_color' => '#fa8c16',
                'secondary_color' => '#f5222d',
                'background_color' => '#fff7e6',
                'text_color' => '#2c2c2c',
                'sidebar_bg_color' => '#872300',
            ],
            [
                'id' => 'purple',
                'name' => '优雅紫',
                'primary_color' => '#722ed1',
                'secondary_color' => '#eb2f96',
                'background_color' => '#f9f0ff',
                'text_color' => '#1f1f1f',
                'sidebar_bg_color' => '#391063',
            ],
            [
                'id' => 'dark',
                'name' => '深色模式',
                'primary_color' => '#177ddc',
                'secondary_color' => '#49aa19',
                'background_color' => '#141414',
                'text_color' => '#e8e8e8',
                'sidebar_bg_color' => '#1f1f1f',
            ],
        ];
    }
}
