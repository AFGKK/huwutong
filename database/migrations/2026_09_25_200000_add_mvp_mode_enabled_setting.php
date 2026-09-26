<?php

use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

/**
 * 系统设置 · 界面：MVP 模式开关（控制后台侧边栏是否仅显示核心菜单）
 */
return new class extends Migration
{
    public function up(): void
    {
        SiteSetting::updateOrCreate(
            ['key' => 'mvp_mode_enabled'],
            [
                'group' => 'interface',
                'value' => '1',
                'type' => 'switch',
                'description' => 'MVP 模式（开启后后台侧边栏仅显示核心菜单）',
                'is_public' => false,
            ]
        );

        Cache::forget('site_settings_all');
    }

    public function down(): void
    {
        SiteSetting::where('key', 'mvp_mode_enabled')->delete();
        Cache::forget('site_settings_all');
    }
};
