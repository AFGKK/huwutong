<?php

use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

/**
 * MVP 模式默认改为关闭：登录后显示全部侧边栏；开启后才收敛为核心菜单。
 */
return new class extends Migration
{
    public function up(): void
    {
        SiteSetting::updateOrCreate(
            ['key' => 'mvp_mode_enabled'],
            [
                'group' => 'interface',
                'value' => '0',
                'type' => 'switch',
                'description' => 'MVP 模式（开启后后台侧边栏仅显示核心菜单；关闭则显示全部）',
                'is_public' => false,
            ]
        );

        Cache::forget('site_settings_all');
    }

    public function down(): void
    {
        SiteSetting::where('key', 'mvp_mode_enabled')->update(['value' => '1']);
        Cache::forget('site_settings_all');
    }
};
