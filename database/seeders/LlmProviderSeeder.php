<?php

namespace Database\Seeders;

use App\Models\LlmProvider;
use Illuminate\Database\Seeder;

class LlmProviderSeeder extends Seeder
{
    public function run(): void
    {
        $providers = [
            [
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
                'config' => json_encode(['temperature' => 0.7, 'max_tokens' => 4096]),
                'sort_order' => 1,
                'is_active' => true,
                'is_fallback' => false,
            ],
            [
                'name' => 'OpenAI (预留)',
                'slug' => 'openai',
                'driver' => 'openai',
                'api_base' => 'https://api.openai.com',
                'api_key' => '',
                'models' => json_encode([]),
                'default_model' => 'gpt-4o-mini',
                'config' => json_encode(['temperature' => 0.7, 'max_tokens' => 4096]),
                'sort_order' => 10,
                'is_active' => false,
                'is_fallback' => false,
            ],
        ];

        foreach ($providers as $p) {
            LlmProvider::firstOrCreate(
                ['slug' => $p['slug']],
                $p
            );
        }
    }
}
