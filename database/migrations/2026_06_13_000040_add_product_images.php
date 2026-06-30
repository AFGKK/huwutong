<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('image_url')->nullable()->after('description')->comment('主图URL');
            $table->json('images')->nullable()->after('image_url')->comment('轮播图URL数组');
        });

        Schema::table('product_skus', function (Blueprint $table) {
            $table->string('image_url')->nullable()->after('name')->comment('SKU专属图');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['image_url', 'images']);
        });
        Schema::table('product_skus', function (Blueprint $table) {
            $table->dropColumn('image_url');
        });
    }
};
