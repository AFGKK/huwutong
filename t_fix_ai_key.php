<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Get API key from site_settings (where user configured it)
$keySetting = \Illuminate\Support\Facades\DB::table('site_settings')->where('key', 'deepseek_api_key')->first();
$baseSetting = \Illuminate\Support\Facades\DB::table('site_settings')->where('key', 'deepseek_api_base')->first();

$apiKey = $keySetting->value ?? '';
$apiBase = $baseSetting->value ?? 'https://api.deepseek.com';

echo "site_settings deepseek_api_key: " . (empty($apiKey) ? 'EMPTY' : substr($apiKey, 0, 8) . '...') . "\n";
echo "site_settings deepseek_api_base: {$apiBase}\n";

// Update llm_providers to use the key from site_settings
$updated = \Illuminate\Support\Facades\DB::table('llm_providers')
    ->where('slug', 'deepseek')
    ->update([
        'api_key' => $apiKey,
        'api_base' => $apiBase,
    ]);

echo "llm_providers updated: " . ($updated ? 'Yes' : 'No') . "\n";

// Verify
$provider = \Illuminate\Support\Facades\DB::table('llm_providers')->where('slug', 'deepseek')->first();
echo "llm_providers api_key now: " . (empty($provider->api_key) ? 'EMPTY' : substr($provider->api_key, 0, 8) . '...') . "\n";

echo "\nNow testing DeepSeek API...\n";

try {
    $service = app(App\Services\LlmService::class);
    $resp = $service->chat(
        [['role' => 'user', 'content' => '你好！请用一句话介绍你自己']],
        ['model' => 'deepseek-chat', 'provider' => 'deepseek']
    );
    echo "Response: " . json_encode($resp, JSON_UNESCAPED_UNICODE) . "\n";
} catch (\Throwable $e) {
    echo get_class($e) . ': ' . $e->getMessage() . "\n";
}
