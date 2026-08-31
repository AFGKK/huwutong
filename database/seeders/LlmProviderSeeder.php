<?php

namespace Database\Seeders;

use App\Models\LlmProvider;
use Illuminate\Database\Seeder;

class LlmProviderSeeder extends Seeder
{
    public function run(): void
    {
        $localEnabled = filter_var(env('LOCAL_LLM_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
        $ollamaModel = env('OLLAMA_DEFAULT_MODEL', 'qwen2.5:7b');

        $providers = [
            [
                'name' => 'Ollama (Local)',
                'slug' => 'ollama',
                'driver' => 'ollama',
                'api_base' => env('OLLAMA_API_BASE', 'http://127.0.0.1:11434'),
                'api_key' => '',
                'models' => json_encode([
                    ['id' => $ollamaModel, 'name' => $ollamaModel, 'description' => '本地 Ollama 默认模型'],
                ]),
                'default_model' => $ollamaModel,
                'config' => json_encode(['temperature' => 0.7, 'num_predict' => 4096]),
                'sort_order' => 0,
                'is_active' => $localEnabled,
                'is_fallback' => false,
            ],
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
                'is_fallback' => $localEnabled,
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
            LlmProvider::updateOrCreate(
                ['slug' => $p['slug']],
                $p
            );
        }
    }
}
