<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'is_sellable')) {
                $table->boolean('is_sellable')->default(false)->comment('是否可售卖（电商商品）');
            }
            if (!Schema::hasColumn('products', 'base_price')) {
                $table->decimal('base_price', 10, 2)->nullable()->comment('基础价格');
            }
            if (!Schema::hasColumn('products', 'sales_count')) {
                $table->integer('sales_count')->default(0)->comment('累计销量');
            }
            if (!Schema::hasColumn('products', 'tags')) {
                $table->json('tags')->nullable()->comment('标签');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $drop = ['is_sellable', 'base_price', 'sales_count', 'tags'];
            foreach ($drop as $col) {
                if (Schema::hasColumn('products', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
