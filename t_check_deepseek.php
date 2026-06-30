<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$tables = ['site_settings','llm_providers','llm_models','settings'];
foreach ($tables as $t) {
    echo "$t: " . (\Illuminate\Support\Facades\Schema::hasTable($t) ? 'Y' : 'N') . "\n";
}

if (\Illuminate\Support\Facades\Schema::hasTable('site_settings')) {
    $r = \Illuminate\Support\Facades\DB::table('site_settings')->where('key', 'deepseek_api_key')->first();
    echo "\ndeeepseek_api_key in site_settings: " . ($r ? 'found (value='.$r->value.')' : 'NOT FOUND') . "\n";
}
if (\Illuminate\Support\Facades\Schema::hasTable('llm_providers')) {
    $r = \Illuminate\Support\Facades\DB::table('llm_providers')->where('slug', 'deepseek')->first();
    echo "llm_provider deepseek: " . ($r ? 'found (api_key='.$r->api_key.')' : 'NOT FOUND') . "\n";
}
echo "\nAlso checking .env for DEEPSEEK_API_KEY...\n";
$env = file_get_contents(__DIR__ . '/.env');
if (preg_match('/DEEPSEEK_API_KEY=(.*)/', $env, $m)) {
    echo "DEEPSEEK_API_KEY: " . ($m[1] ? trim($m[1]) : 'empty') . "\n";
} else {
    echo "DEEPSEEK_API_KEY: NOT FOUND in .env\n";
}
