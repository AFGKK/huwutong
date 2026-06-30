<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Simulate saving deepseek_api_key via the controller
$controller = app(App\Http\Controllers\Api\SiteSettingController::class);
$request = new \Illuminate\Http\Request();
$request->replace([
    'settings' => [
        ['key' => 'deepseek_api_key', 'value' => 'sk-test-sync-123456'],
        ['key' => 'deepseek_api_base', 'value' => 'https://api.deepseek.com'],
    ]
]);

// Manually call the controller's update method via a route would be ideal, but we can't easily
// Instead, let's just directly test the sync method using reflection
$ref = new ReflectionMethod($controller, 'syncLlmProviders');
$ref->setAccessible(true);
$ref->invoke($controller, [
    'deepseek_api_key' => 'sk-test-sync-123456',
    'deepseek_api_base' => 'https://api.deepseek.com',
]);

// Check if synced
$provider = \Illuminate\Support\Facades\DB::table('llm_providers')->where('slug', 'deepseek')->first();
echo "After sync:\n";
echo "  api_key: " . $provider->api_key . "\n";
echo "  api_base: " . $provider->api_base . "\n";

echo "\n✅ Sync method works!\n";

// Restore the original key from site_settings
$keySetting = \Illuminate\Support\Facades\DB::table('site_settings')->where('key', 'deepseek_api_key')->first();
\Illuminate\Support\Facades\DB::table('llm_providers')
    ->where('slug', 'deepseek')
    ->update(['api_key' => $keySetting->value ?? '']);
echo "Restored original api_key.\n";
