<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 支付回调处理记录
        Schema::create('payment_callbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('gateway', 50)->comment('wechat/alipay/stripe/paypal');
            $table->string('event_type', 60)->comment('payment_success/payment_failed/refund/chargeback');
            $table->string('transaction_id', 200)->nullable()->comment('支付网关交易号');
            $table->string('merchant_order_no', 100)->nullable()->comment('商户订单号');
            $table->decimal('amount', 12, 2)->nullable()->comment('回调金额');
            $table->string('currency', 10)->nullable()->default('CNY');
            $table->string('status', 30)->default('received')->comment('received/processing/completed/failed/duplicate');
            $table->text('raw_payload')->nullable()->comment('原始回调数据');
            $table->text('response')->nullable()->comment('处理响应');
            $table->text('error_message')->nullable();
            $table->string('idempotency_key', 100)->unique()->comment('幂等键: gateway+event_id');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['gateway', 'status', 'created_at']);
            $table->index(['order_id', 'event_type']);
        });

        // 订单支付记录（与订单1:1）
        if (Schema::hasTable('orders') && !Schema::hasColumn('orders', 'payment_callback_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreignId('payment_callback_id')->nullable()->after('paid_at')
                    ->constrained('payment_callbacks')->nullOnDelete();
                $table->string('payment_channel', 30)->nullable()->after('payment_callback_id');
                $table->string('payment_transaction_id', 200)->nullable()->after('payment_channel');
                $table->timestamp('payment_callback_at')->nullable()->after('payment_transaction_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_callbacks');
    }
};
