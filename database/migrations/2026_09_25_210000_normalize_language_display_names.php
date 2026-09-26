<?php

use App\Models\Language;
use Illuminate\Database\Migrations\Migration;

/**
 * 补全 languages 展示名，避免下拉仅显示 locale 代码
 */
return new class extends Migration
{
    public function up(): void
    {
        $map = [
            'zh_CN' => ['name' => '简体中文', 'native_name' => '简体中文', 'flag' => '🇨🇳', 'sort_order' => 1, 'is_default' => true],
            'en' => ['name' => 'English', 'native_name' => 'English', 'flag' => '🇺🇸', 'sort_order' => 2, 'is_default' => false],
            'ja' => ['name' => '日本語', 'native_name' => '日本語', 'flag' => '🇯🇵', 'sort_order' => 3, 'is_default' => false],
            'zh-TW' => ['name' => '繁體中文', 'native_name' => '繁體中文', 'flag' => '🇭🇰', 'sort_order' => 4, 'is_default' => false],
            'ko' => ['name' => '한국어', 'native_name' => '한국어', 'flag' => '🇰🇷', 'sort_order' => 5, 'is_default' => false],
        ];

        foreach ($map as $locale => $attrs) {
            Language::updateOrCreate(
                ['locale' => $locale],
                array_merge($attrs, ['is_active' => true]),
            );
        }
    }

    public function down(): void
    {
        // 展示名修复无需回滚
    }
};
