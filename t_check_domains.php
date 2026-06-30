<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$table = 'custom_domains';
echo "Table exists: " . (\Illuminate\Support\Facades\Schema::hasTable($table) ? 'Y' : 'N') . "\n";

if (\Illuminate\Support\Facades\Schema::hasTable($table)) {
    $domains = \Illuminate\Support\Facades\DB::table($table)->get();
    echo "Count: " . $domains->count() . "\n";
    foreach ($domains as $d) {
        print_r((array)$d);
    }
}
