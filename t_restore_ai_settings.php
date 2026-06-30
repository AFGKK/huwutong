<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Restore AI settings
$aiSettings = [
    ['group' => 'ai', 'key' => 'llm_default_provider', 'value' => 'deepseek', 'type' => 'select', 'description' => '默认 AI 提供商', 'options' => json_encode(['deepseek','openai','claude','tongyi','wenxin','glm','ollama']), 'is_public' => false],
    ['group' => 'ai', 'key' => 'deepseek_api_key', 'value' => '', 'type' => 'password', 'description' => 'DeepSeek API Key', 'is_public' => false],
    ['group' => 'ai', 'key' => 'deepseek_api_base', 'value' => 'https://api.deepseek.com', 'type' => 'text', 'description' => 'DeepSeek API 地址', 'is_public' => false],
    ['group' => 'ai', 'key' => 'llm_temperature', 'value' => '0.7', 'type' => 'number', 'description' => 'AI 回复温度 (0-2)', 'is_public' => false],
    ['group' => 'ai', 'key' => 'llm_max_tokens', 'value' => '4096', 'type' => 'number', 'description' => '最大 Token 数', 'is_public' => false],
    ['group' => 'ai', 'key' => 'ai_chat_enabled', 'value' => '1', 'type' => 'boolean', 'description' => '启用 AI 对话', 'is_public' => true],
    ['group' => 'ai', 'key' => 'ai_review_enabled', 'value' => '1', 'type' => 'boolean', 'description' => '启用发送前 AI 预审', 'is_public' => true],
    ['group' => 'ai', 'key' => 'memory_enabled', 'value' => '1', 'type' => 'boolean', 'description' => '启用长期记忆', 'is_public' => true],
    ['group' => 'ai', 'key' => 'proactive_insight_enabled', 'value' => '1', 'type' => 'boolean', 'description' => '启用主动洞察', 'is_public' => true],
];

foreach ($aiSettings as $s) {
    $exists = \Illuminate\Support\Facades\DB::table('site_settings')->where('key', $s['key'])->exists();
    if (!$exists) {
        $s['created_at'] = now();
        $s['updated_at'] = now();
        \Illuminate\Support\Facades\DB::table('site_settings')->insert($s);
        echo "Inserted: {$s['key']}\n";
    } else {
        echo "Exists: {$s['key']}\n";
    }
}

// Also restore llm_providers
if (\Illuminate\Support\Facades\Schema::hasTable('llm_providers')) {
    $exists = \Illuminate\Support\Facades\DB::table('llm_providers')->where('slug', 'deepseek')->exists();
    if (!$exists) {
        \Illuminate\Support\Facades\DB::table('llm_providers')->insert([
            'name' => 'DeepSeek',
            'slug' => 'deepseek',
            'driver' => 'deepseek',
            'api_base' => 'https://api.deepseek.com',
            'api_key' => env('DEEPSEEK_API_KEY', ''),
            'models' => json_encode([
                ['id' => 'deepseek-chat', 'name' => 'DeepSeek-V3', 'description' => '最新版 V3 对话模型'],
                ['id' => 'deepseek-reasoner', 'name' => 'DeepSeek-R1', 'description' => '深度推理模型'],
            ]),
            'default_model' => 'deepseek-chat',
            'is_active' => true,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        echo "Inserted: llm_provider deepseek\n";
    }
}

echo "\nDone. AI settings restored.\n";
