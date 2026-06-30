<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Check how LlmService reads the provider config
$provider = \Illuminate\Support\Facades\DB::table('llm_providers')->where('slug', 'deepseek')->first();
if ($provider) {
    echo "LLM Provider: {$provider->name} (slug: {$provider->slug})\n";
    echo "API Base: {$provider->api_base}\n";
    echo "API Key: " . (empty($provider->api_key) ? 'EMPTY' : substr($provider->api_key, 0, 8) . '...') . "\n";
    echo "Default Model: {$provider->default_model}\n";
    echo "Is Active: " . ($provider->is_active ? 'Yes' : 'No') . "\n";
}

// Check env
echo "\nDEEPSEEK_API_KEY from env: " . (env('DEEPSEEK_API_KEY') ? substr(env('DEEPSEEK_API_KEY'), 0, 8) . '...' : 'NOT SET') . "\n";

// Check site_settings
$key = \Illuminate\Support\Facades\DB::table('site_settings')->where('key', 'deepseek_api_key')->first();
if ($key) {
    echo "site_settings deepseek_api_key: " . (empty($key->value) ? 'EMPTY' : substr($key->value, 0, 8) . '...') . "\n";
}
$base = \Illuminate\Support\Facades\DB::table('site_settings')->where('key', 'deepseek_api_base')->first();
if ($base) {
    echo "site_settings deepseek_api_base: {$base->value}\n";
}

// Try to directly test the DeepSeek adapter
try {
    $adapter = app(App\Services\LlmService::class)->getAdapter(['provider' => 'deepseek']);
    echo "\nAdapter class: " . get_class($adapter) . "\n";
    
    // Check if adapter has the API key
    $ref = new ReflectionClass($adapter);
    foreach ($ref->getProperties(\ReflectionProperty::IS_PRIVATE | \ReflectionProperty::IS_PROTECTED) as $prop) {
        $prop->setAccessible(true);
        $val = $prop->getValue($adapter);
        if (is_string($val) && strlen($val) > 10) {
            echo "  {$prop->getName()}: " . substr($val, 0, 20) . "...\n";
        }
    }
} catch (\Throwable $e) {
    echo "Error getting adapter: " . $e->getMessage() . "\n";
}
