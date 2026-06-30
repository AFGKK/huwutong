<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('cancelled_at')->comment('订单超时时间');
            }
            if (! Schema::hasColumn('orders', 'payment_extra')) {
                $table->json('payment_extra')->nullable()->after('transaction_id')->comment('支付额外数据');
            }
        });

        Schema::table('coupon_redemptions', function (Blueprint $table) {
            if (! Schema::hasColumn('coupon_redemptions', 'order_id')) {
                $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete()->after('subscription_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['expires_at', 'payment_extra']);
        });

        Schema::table('coupon_redemptions', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropColumn(['order_id']);
        });
    }
};
