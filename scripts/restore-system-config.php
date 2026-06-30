<?php
/**
 * 数据库重置后恢复系统配置
 * 运行: php scripts/restore-system-config.php [--apply-branding]
 *
 * --apply-branding  将仍为旧默认值的站点名称/SEO 等更新为互物通品牌（不覆盖已自定义的值）
 */
require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Database\Seeders\SiteSettingsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$applyBranding = in_array('--apply-branding', $argv ?? [], true);

echo "=== 互物通系统配置恢复 ===\n\n";

// 1. 补全 site_settings 缺失项（firstOrCreate，不覆盖已有值）
echo "[1/5] 运行 SiteSettingsSeeder...\n";
(new SiteSettingsSeeder())->run();
$total = DB::table('site_settings')->count();
echo "      site_settings 共 {$total} 项\n";

// 2. 修复 AI 开关类型（boolean -> switch，便于前端渲染）
echo "[2/5] 修复 AI 开关字段类型...\n";
$fixed = DB::table('site_settings')
    ->whereIn('key', ['ai_chat_enabled', 'ai_review_enabled', 'memory_enabled', 'proactive_insight_enabled'])
    ->where('type', 'boolean')
    ->update(['type' => 'switch', 'updated_at' => now()]);
echo "      修复 {$fixed} 项\n";

// 3. 修复空 switch 值
echo "[3/5] 修复空 switch 值...\n";
$emptySwitches = DB::table('site_settings')
    ->where('type', 'switch')
    ->where(function ($q) {
        $q->whereNull('value')->orWhere('value', '');
    })
    ->update(['value' => '0', 'updated_at' => now()]);
echo "      修复 {$emptySwitches} 项\n";

// 4. 同步 DeepSeek API Key 到 llm_providers
echo "[4/5] 同步 AI 配置到 llm_providers...\n";
if (Schema::hasTable('llm_providers')) {
    $apiKey = DB::table('site_settings')->where('key', 'deepseek_api_key')->value('value');
    $apiBase = DB::table('site_settings')->where('key', 'deepseek_api_base')->value('value');
    if ($apiKey) {
        DB::table('llm_providers')->where('slug', 'deepseek')->update([
            'api_key' => $apiKey,
            'api_base' => $apiBase ?: 'https://api.deepseek.com',
            'is_active' => true,
            'updated_at' => now(),
        ]);
        echo "      DeepSeek API Key 已同步\n";
    } else {
        echo "      DeepSeek API Key 为空，跳过同步\n";
    }
}

// 5. 可选：将仍为旧默认值的字段更新为互物通品牌
if ($applyBranding) {
    echo "[5/5] 应用互物通品牌默认值（仅覆盖旧默认）...\n";
    $brandingUpdates = [
        'site_name' => ['old' => ['HWT License', ''], 'new' => '互物通'],
        'site_description' => ['old' => ['企业级授权管理系统', ''], 'new' => '企业级软件授权与 License 管理平台'],
        'site_slogan' => ['old' => ['保护您的软件资产', ''], 'new' => '让软件授权管理变得简单、安全、可靠'],
        'footer_copyright' => ['old' => ['© 2026 HWT License. All rights reserved.', ''], 'new' => '© 2026 互物通. All rights reserved.'],
        'seo_title' => ['old' => ['HWT License - 企业级授权管理系统', ''], 'new' => '互物通 - 企业级软件授权管理平台'],
        'seo_canonical_domain' => ['old' => ['', 'https://example.com'], 'new' => 'https://88.huwutong.com'],
        'contact_address' => ['old' => [''], 'new' => '上海市浦东新区张江高科技园区'],
    ];
    foreach ($brandingUpdates as $key => $rule) {
        $current = DB::table('site_settings')->where('key', $key)->value('value');
        if (in_array($current, $rule['old'], true)) {
            DB::table('site_settings')->where('key', $key)->update(['value' => $rule['new'], 'updated_at' => now()]);
            echo "      更新 {$key}\n";
        }
    }
    // 修复 robots.txt 中的 example.com
    $robots = DB::table('site_settings')->where('key', 'seo_robots_txt')->value('value');
    if ($robots && str_contains($robots, 'example.com')) {
        DB::table('site_settings')->where('key', 'seo_robots_txt')->update([
            'value' => str_replace('https://example.com/sitemap.xml', 'https://88.huwutong.com/sitemap.xml', $robots),
            'updated_at' => now(),
        ]);
        echo "      更新 seo_robots_txt\n";
    }
} else {
    echo "[5/5] 跳过品牌更新（加 --apply-branding 可应用互物通默认品牌）\n";
}

Cache::forget('site_settings_all');
Cache::forget('site_settings');

echo "\n=== 分组统计 ===\n";
$groups = DB::table('site_settings')->select('group', DB::raw('count(*) as c'))->groupBy('group')->orderBy('group')->get();
foreach ($groups as $g) {
    echo "  {$g->group}: {$g->c} 项\n";
}

echo "\n恢复完成。请运行: php import_pages.php --force\n";
