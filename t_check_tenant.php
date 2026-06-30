<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Tenants:\n";
$tenants = \Illuminate\Support\Facades\DB::table('tenants')->get();
foreach ($tenants as $t) {
    print_r((array)$t);
}

echo "\nUser 1:\n";
$user = \Illuminate\Support\Facades\DB::table('users')->where('id', 1)->first();
if ($user) {
    print_r((array)$user);
}
