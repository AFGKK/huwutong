<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "pricing_plans columns:\n";
$cols = \Illuminate\Support\Facades\Schema::getColumnListing('pricing_plans');
foreach ($cols as $c) echo "  - $c\n";

echo "\ncoupons columns:\n";
if (\Illuminate\Support\Facades\Schema::hasTable('coupons')) {
    $cols = \Illuminate\Support\Facades\Schema::getColumnListing('coupons');
    foreach ($cols as $c) echo "  - $c\n";
} else {
    echo "  TABLE NOT FOUND\n";
}

echo "\naudit_action_dicts:\n";
echo "table exists: " . (\Illuminate\Support\Facades\Schema::hasTable('audit_action_dicts') ? 'Y' : 'N') . "\n";
