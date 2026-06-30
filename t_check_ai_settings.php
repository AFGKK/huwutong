<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$settings = \Illuminate\Support\Facades\DB::table('site_settings')->where('group', 'ai')->get();
echo "AI settings count: " . $settings->count() . "\n";
foreach ($settings as $s) {
    echo "  - {$s->key}: " . substr($s->value, 0, 30) . "\n";
}
echo "\nAll setting groups:\n";
$groups = \Illuminate\Support\Facades\DB::table('site_settings')->select('group')->distinct()->pluck('group');
echo $groups->implode(', ') . "\n";
