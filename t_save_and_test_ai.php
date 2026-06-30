<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$apiKey = 'sk-6b5a39ea99e849bfb8a51815e3e31a85';

// Save to site_settings
\Illuminate\Support\Facades\DB::table('site_settings')
    ->where('key', 'deepseek_api_key')
    ->update(['value' => $apiKey]);

// Sync to llm_providers
\Illuminate\Support\Facades\DB::table('llm_providers')
    ->where('slug', 'deepseek')
    ->update(['api_key' => $apiKey]);

echo "Key saved and synced!\n";

// Test LlmService
$service = app(App\Services\LlmService::class);
$resp = $service->chat(
    [['role' => 'user', 'content' => '你好，用一句话回复']],
    ['model' => 'deepseek-chat', 'provider' => 'deepseek']
);
echo "AI Response: " . ($resp['content'] ?? 'No response') . "\n";
if (isset($resp['usage'])) {
    echo "Tokens: " . json_encode($resp['usage']) . "\n";
}
