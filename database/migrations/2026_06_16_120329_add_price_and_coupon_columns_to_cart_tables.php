<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // carts 表缺少优惠券相关列
        Schema::table('carts', function (Blueprint $table) {
            if (!Schema::hasColumn('carts', 'coupon_code')) {
                $table->string('coupon_code', 50)->nullable()->after('session_id');
            }
            if (!Schema::hasColumn('carts', 'coupon_id')) {
                $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete()->after('coupon_code');
            }
            if (!Schema::hasColumn('carts', 'coupon_discount')) {
                $table->decimal('coupon_discount', 10, 2)->default(0)->after('coupon_id');
            }
            if (!Schema::hasColumn('carts', 'notes')) {
                $table->text('notes')->nullable()->after('coupon_discount');
            }
        });

        // cart_items 表缺少价格快照列
        Schema::table('cart_items', function (Blueprint $table) {
            if (!Schema::hasColumn('cart_items', 'unit_price')) {
                $table->decimal('unit_price', 10, 2)->default(0)->after('quantity');
            }
            if (!Schema::hasColumn('cart_items', 'original_price')) {
                $table->decimal('original_price', 10, 2)->default(0)->after('unit_price');
            }
            if (!Schema::hasColumn('cart_items', 'subtotal')) {
                $table->decimal('subtotal', 10, 2)->default(0)->after('original_price');
            }
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
