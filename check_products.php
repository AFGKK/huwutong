<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$products = App\Models\Product::whereNotNull('merchant_id')->limit(5)->get(['id','slug','name']);
foreach ($products as $p) {
    echo "{$p->id} => '{$p->slug}' ({$p->name})\n";
}
// Also show the product show route
echo "\nChecking product route...\n";
$routes = Illuminate\Support\Facades\Route::getRoutes();
foreach ($routes->getRoutes() as $r) {
    if (strpos($r->uri, 'products') !== false && strpos($r->uri, '{') !== false) {
        echo $r->uri . "\n";
    }
}
