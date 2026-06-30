<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('order_affiliates')) {
            return;
        }
        // 订单推广关联表
        if (!Schema::hasTable('order_affiliates')) {
            Schema::create('order_affiliates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id')->unique()->index();
                $table->unsignedBigInteger('agent_id')->index();
                $table->string('referral_code', 50)->nullable();
                $table->decimal('commission_rate', 5, 2)->default(5.00);
                $table->decimal('order_amount', 12, 2)->default(0);
                $table->decimal('commission_amount', 12, 2)->default(0);
                $table->string('status', 20)->default('pending')->comment('pending/settled/cancelled');
                $table->timestamp('settled_at')->nullable();
                $table->timestamps();

                $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
                $table->foreign('agent_id')->references('id')->on('agents')->cascadeOnDelete();
            });
        }

        // orders 表增加推广字段
        if (!Schema::hasColumn('orders', 'affiliate_agent_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->unsignedBigInteger('affiliate_agent_id')->nullable()->after('payment_extra');
                $table->string('referral_code', 50)->nullable()->after('affiliate_agent_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_affiliates');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['affiliate_agent_id', 'referral_code']);
        });
    }
};
