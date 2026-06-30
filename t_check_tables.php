<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$rows = \Illuminate\Support\Facades\DB::select('SHOW TABLES');
foreach ($rows as $row) {
    $name = array_values((array)$row)[0];
    if (str_contains($name, 'conversation') || str_contains($name, 'user_chat')) {
        echo "$name\n";
    }
}
