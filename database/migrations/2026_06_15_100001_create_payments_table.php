<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payments')) {
            return;
        }

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 30)->comment('wechat/alipay/stripe/paypal/mock');
            $table->string('transaction_id', 200)->nullable()->unique()->comment('支付渠道交易号');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('CNY');
            $table->decimal('fee', 12, 2)->default(0)->comment('支付手续费');
            $table->decimal('net_amount', 12, 2)->default(0)->comment('实际到账金额');
            $table->string('status', 30)->default('pending')->comment('pending/completed/failed/refunding/refunded/partially_refunded');
            $table->timestamp('paid_at')->nullable()->comment('支付完成时间');
            $table->timestamp('refunded_at')->nullable();
            $table->decimal('refunded_amount', 12, 2)->default(0);
            $table->text('description')->nullable();
            $table->json('metadata')->nullable()->comment('渠道原始回调数据');
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'order_id']);
            $table->index(['channel', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
