<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sku_currency_prices')) {
            return;
        }
        Schema::create('sku_currency_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_sku_id')->constrained()->cascadeOnDelete();
            $table->string('currency', 3); // CNY, USD, EUR
            $table->decimal('price', 12, 2);
            $table->decimal('compare_at_price', 12, 2)->nullable();
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->boolean('is_converted')->default(false);
            $table->string('source_currency', 3)->nullable();
            $table->timestamps();

            $table->unique(['product_sku_id', 'currency']);
            $table->index('currency');
        });

        Schema::table('product_skus', function (Blueprint $table) {
            $table->boolean('multi_currency_enabled')->default(false)->after('currency');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sku_currency_prices');

        Schema::table('product_skus', function (Blueprint $table) {
            $table->dropColumn('multi_currency_enabled');
        });
    }
};
