<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->string('coupon_code', 50)->nullable()->after('session_id');
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete()->after('coupon_code');
            $table->decimal('coupon_discount', 12, 2)->default(0)->after('coupon_id');
            $table->text('notes')->nullable()->after('coupon_discount');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->decimal('unit_price', 12, 2)->default(0)->after('quantity');
            $table->decimal('original_price', 12, 2)->default(0)->after('unit_price');
            $table->decimal('subtotal', 12, 2)->default(0)->after('original_price');
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropColumn(['coupon_code', 'coupon_id', 'coupon_discount', 'notes']);
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropColumn(['unit_price', 'original_price', 'subtotal']);
        });
    }
};
