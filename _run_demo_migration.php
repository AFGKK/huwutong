<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// Check current state
echo "product_demos table: " . (Schema::hasTable('product_demos') ? "EXISTS" : "MISSING") . "\n";
echo "products.demo_enabled: " . (Schema::hasColumn('products', 'demo_enabled') ? "EXISTS" : "MISSING") . "\n";

// Run migration directly
if (!Schema::hasTable('product_demos')) {
    Schema::create('product_demos', function ($table) {
        $table->id();
        $table->foreignId('product_id')->constrained()->cascadeOnDelete();
        $table->string('platform');
        $table->string('site_url')->nullable();
        $table->string('account')->nullable();
        $table->string('password')->nullable();
        $table->integer('sort_order')->default(0);
        $table->timestamps();
        $table->index('product_id');
    });
    echo "Created product_demos table\n";
}

if (!Schema::hasColumn('products', 'demo_enabled')) {
    Schema::table('products', function ($table) {
        $table->boolean('demo_enabled')->default(false);
        $table->json('demo_images')->nullable();
    });
    echo "Added demo columns to products\n";
}

// 清理旧字段
Schema::table('products', function ($table) {
    foreach (['demo_qr_h5', 'demo_qr_miniapp'] as $col) {
        if (Schema::hasColumn('products', $col)) {
            $table->dropColumn($col);
        }
    }
});

echo "All done!\n";
