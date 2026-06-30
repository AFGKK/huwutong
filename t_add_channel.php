<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

\Illuminate\Support\Facades\DB::table('withdrawal_channels')->insert([
    'name' => '银行转账',
    'slug' => 'bank',
    'type' => 'bank',
    'min_amount' => 100,
    'max_amount' => 50000,
    'fee_rate' => 0.01,
    'fee_fixed' => 2,
    'is_active' => true,
    'sort_order' => 1,
    'created_at' => now(),
    'updated_at' => now(),
]);

echo "Added default withdrawal channel.\n";
$count = \Illuminate\Support\Facades\DB::table('withdrawal_channels')->count();
echo "Total channels: $count\n";
