<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== 产品数据 ===\n";
$products = \Illuminate\Support\Facades\DB::table('products')->get();
foreach ($products as $p) {
    echo "  - {$p->id}: {$p->name} (slug: {$p->slug})\n";
}
echo "Total: " . $products->count() . "\n\n";

echo "=== 客户数据 ===\n";
$customers = \Illuminate\Support\Facades\DB::table('customers')->get();
foreach ($customers as $c) {
    echo "  - {$c->id}: {$c->name} ({$c->email})\n";
}
echo "Total: " . $customers->count() . "\n\n";

echo "=== License 数据 ===\n";
$licenses = \Illuminate\Support\Facades\DB::table('licenses')->get();
foreach ($licenses as $l) {
    echo "  - {$l->id}: {$l->license_key} ({$l->status})\n";
}
echo "Total: " . $licenses->count() . "\n";
