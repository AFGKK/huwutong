<?php

use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            // 页面布局配置
            [
                'group' => 'interface',
                'key' => 'page_width',
                'value' => 'max-w-6xl',
                'type' => 'select',
                'options' => json_encode(['max-w-4xl' => '窄 (1024px)', 'max-w-5xl' => '中 (1100px)', 'max-w-6xl' => '宽 (1152px)', 'max-w-7xl' => '超宽 (1280px)']),
                'description' => '前端页面内容区宽度',
                'is_public' => true,
            ],
            [
                'group' => 'interface',
                'key' => 'page_primary_color',
                'value' => '#2563eb',
                'type' => 'color',
                'options' => null,
                'description' => '前端页面主题色 (按钮/链接/高亮)',
                'is_public' => true,
            ],
            [
                'group' => 'interface',
                'key' => 'page_background',
                'value' => '#f9fafb',
                'type' => 'color',
                'options' => null,
                'description' => '前端页面背景色',
                'is_public' => true,
            ],
            [
                'group' => 'interface',
                'key' => 'page_content_bg',
                'value' => '#ffffff',
                'type' => 'color',
                'options' => null,
                'description' => '前端页面内容卡片背景色',
                'is_public' => true,
            ],
            [
                'group' => 'interface',
                'key' => 'page_font_size',
                'value' => '16px',
                'type' => 'select',
                'options' => json_encode(['14px' => '小', '15px' => '默认', '16px' => '大', '18px' => '超大']),
                'description' => '前端页面正文字体大小',
                'is_public' => true,
            ],
        ];

        foreach ($settings as $s) {
            SiteSetting::firstOrCreate(
                ['key' => $s['key']],
                $s
            );
        }
    }

    public function down(): void
    {
        SiteSetting::whereIn('key', [
            'page_width', 'page_primary_color', 'page_background',
            'page_content_bg', 'page_font_size',
        ])->delete();
    }
};
