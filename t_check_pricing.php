<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Check migration records
$rows = \Illuminate\Support\Facades\DB::table('migrations')->where('migration', 'like', '%pricing%')->orWhere('migration', 'like', '%coupon%')->get();
echo "pricing/coupon migrations:\n";
foreach ($rows as $r) {
    echo "  {$r->migration} (batch {$r->batch})\n";
}

// Check the migration files
echo "\nMigration files for pricing/coupon:\n";
foreach (glob(__DIR__ . '/database/migrations/*pricing*') as $f) {
    echo "  " . basename($f) . "\n";
}
foreach (glob(__DIR__ . '/database/migrations/*coupon*') as $f) {
    echo "  " . basename($f) . "\n";
}

// Get the actual table create SQL
echo "\nShow create table pricing_plans:\n";
$sql = \Illuminate\Support\Facades\DB::select("SHOW CREATE TABLE pricing_plans");
if ($sql) {
    echo $sql[0]->{'Create Table'} . "\n\n";
}

echo "Show create table coupons:\n";
$sql = \Illuminate\Support\Facades\DB::select("SHOW CREATE TABLE coupons");
if ($sql) {
    echo $sql[0]->{'Create Table'} . "\n";
}
