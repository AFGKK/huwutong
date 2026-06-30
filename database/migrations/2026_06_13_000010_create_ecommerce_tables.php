<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SKU/库存表
        Schema::create('product_skus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku_code', 100)->unique()->comment('唯一SKU编码');
            $table->string('name')->comment('SKU名称，如"专业版-年付"');
            $table->json('specs')->nullable()->comment('规格值组合: {spec_group_id: spec_value_id}');
            $table->decimal('price', 10, 2)->comment('售价');
            $table->decimal('compare_at_price', 10, 2)->nullable()->comment('划线价');
            $table->string('currency', 3)->default('CNY');
            $table->integer('stock')->default(0)->comment('库存，-1=无限');
            $table->integer('sold_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('billing_cycle', 20)->nullable()->comment('monthly/quarterly/yearly/one-time');
            $table->timestamps();

            $table->index(['product_id', 'is_active']);
        });

        // 订单表
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 40)->unique()->comment('订单号');
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('total_amount', 10, 2)->comment('订单总额');
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('final_amount', 10, 2)->comment('实付金额');
            $table->string('currency', 3)->default('CNY');
            $table->string('status', 30)->default('pending')->comment('pending/paid/cancelled/refunding/refunded/partial_refund');
            $table->string('payment_method', 50)->nullable();
            $table->string('transaction_id', 255)->nullable()->comment('支付平台交易号');
            $table->json('coupon_info')->nullable();
            $table->json('billing_address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'created_at']);
            $table->index('user_id');
        });

        // 订单明细表
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sku_id')->nullable()->constrained('product_skus')->nullOnDelete();
            $table->string('item_type', 30)->default('license')->comment('license/subscription/addon');
            $table->string('name')->comment('商品名称快照');
            $table->decimal('unit_price', 10, 2);
            $table->integer('quantity')->default(1);
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->json('meta')->nullable()->comment('扩展信息');
            $table->timestamps();
        });

        // 发货记录表
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->string('delivery_type', 30)->comment('license_key/download_link/api_key/file_package/service_activation');
            $table->text('content')->nullable()->comment('交付内容（加密存储）');
            $table->string('delivery_channel', 30)->default('email')->comment('email/webhook/api/in_app');
            $table->string('status', 20)->default('pending')->comment('pending/sent/delivered/failed');
            $table->text('error_message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('product_skus');
    }
};
