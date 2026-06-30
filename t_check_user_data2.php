<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== 客户表列名 ===\n";
print_r(\Illuminate\Support\Facades\Schema::getColumnListing('customers'));

echo "\n=== 客户数据 ===\n";
$customers = \Illuminate\Support\Facades\DB::table('customers')->get();
foreach ($customers as $c) {
    print_r((array)$c);
}
echo "Total: " . $customers->count() . "\n\n";

echo "=== License 表列名 ===\n";
print_r(\Illuminate\Support\Facades\Schema::getColumnListing('licenses'));

echo "\n=== License 数据 ===\n";
$licenses = \Illuminate\Support\Facades\DB::table('licenses')->get();
foreach ($licenses as $l) {
    print_r((array)$l);
}
echo "Total: " . $licenses->count() . "\n";
