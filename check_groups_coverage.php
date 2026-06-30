<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\SiteSetting;

$groups = SiteSetting::select('group')->distinct()->pluck('group')->toArray();
sort($groups);

echo "=== Site Setting Groups (" . count($groups) . ") ===\n";
foreach ($groups as $g) {
    $c = SiteSetting::where('group', $g)->count();
    echo "  $g ($c items)\n";
}

echo "\n=== Config PHP Files ===\n";
$files = glob(__DIR__ . '/config/*.php');
sort($files);
$configNames = [];
foreach ($files as $f) {
    $name = basename($f, '.php');
    $configNames[] = $name;
    echo "  $name.php\n";
}

echo "\n=== Config files WITHOUT a matching setting group ===\n";
foreach ($configNames as $name) {
    // Skip Laravel core configs
    $skip = ['app','auth','broadcasting','cache','cors','database','filesystems',
             'hashing','logging','mail','queue','services','session','scout',
             'sanctum','telescope'];
    if (in_array($name, $skip)) continue;
    
    if (!in_array($name, $groups)) {
        echo "  ⚠️  $name.php → no matching group\n";
    }
}

file_put_contents(__DIR__ . '/groups_check_result.txt', ob_get_contents() ?: '');
