<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperPortalBrandingConfig
 */
class PortalBrandingConfig extends Model
{
    protected $table = 'portal_branding_configs';

    protected $fillable = [
        'tenant_id', 'locale', 'brand_name', 'brand_slogan',
        'logo_url', 'favicon_url',
        'primary_color', 'secondary_color', 'background_color',
        'text_color', 'link_color', 'header_bg_color',
        'sidebar_bg_color', 'sidebar_text_color',
        'button_radius', 'font_family',
        'custom_css', 'header_html', 'footer_html',
        'login_page_title', 'login_page_subtitle', 'login_bg_image',
        'footer_text', 'links', 'social_links',
        'is_active', 'is_default',
    ];

    protected function casts(): array
    {
        return [
            'links' => 'array',
            'social_links' => 'array',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * 生成 CSS 变量映射
     */
    public function toCssVariables(): array
    {
        return [
            '--brand-primary' => $this->primary_color ?? '#409eff',
            '--brand-secondary' => $this->secondary_color ?? '#67c23a',
            '--brand-background' => $this->background_color ?? '#f5f7fa',
            '--brand-text' => $this->text_color ?? '#303133',
            '--brand-link' => $this->link_color ?? '#409eff',
            '--brand-header-bg' => $this->header_bg_color ?? '#ffffff',
            '--brand-sidebar-bg' => $this->sidebar_bg_color ?? '#304156',
            '--brand-sidebar-text' => $this->sidebar_text_color ?? '#bfcbd9',
            '--brand-button-radius' => $this->button_radius ?? '4px',
        ];
    }
}
