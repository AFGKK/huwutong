<?php

use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

/**
 * 将 brand.primary_color 与 interface.page_primary_color 对齐为同一主题色。
 * 以 primary_color 为准；若仅有 page_primary_color 则回写 primary_color。
 */
return new class extends Migration
{
    public function up(): void
    {
        $primary = SiteSetting::where('key', 'primary_color')->first();
        $page = SiteSetting::where('key', 'page_primary_color')->first();

        $color = null;
        if ($primary && $primary->value !== null && $primary->value !== '') {
            $color = $primary->value;
        } elseif ($page && $page->value !== null && $page->value !== '') {
            $color = $page->value;
        } else {
            $color = '#409EFF';
        }

        SiteSetting::updateOrCreate(
            ['key' => 'primary_color'],
            [
                'group' => $primary?->group ?: 'brand',
                'value' => $color,
                'type' => 'color',
                'description' => $primary?->description ?: '主题色',
                'is_public' => true,
            ]
        );

        SiteSetting::updateOrCreate(
            ['key' => 'page_primary_color'],
            [
                'group' => $page?->group ?: 'interface',
                'value' => $color,
                'type' => 'color',
                'description' => $page?->description ?: '前端页面主题色 (按钮/链接/高亮)',
                'is_public' => true,
            ]
        );

        Cache::forget('site_settings_all');
    }

    public function down(): void
    {
        // 不可逆：仅同步值，不回滚
    }
};
