<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('flash_sales')) {
            return;
        }
        Schema::create('flash_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sku_id')->constrained('product_skus')->cascadeOnDelete();
            $table->string('name', 200);
            $table->unsignedInteger('flash_price')->comment('秒杀价(分)');
            $table->unsignedInteger('original_price')->comment('原价(分)');
            $table->unsignedInteger('stock')->comment('秒杀库存');
            $table->unsignedInteger('max_per_user')->default(1);
            $table->timestamp('start_time');
            $table->timestamp('end_time');
            $table->string('status', 30)->default('scheduled')->comment('scheduled/active/paused/ended');
            $table->timestamp('preheat_at')->nullable()->comment('预热时间');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index('start_time');
        });

        Schema::create('flash_sale_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flash_sale_id')->constrained('flash_sales')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('queue_token', 100)->unique()->comment('排队令牌');
            $table->string('status', 30)->default('queued')->comment('queued/reserved/paid/expired/cancelled');
            $table->string('device_fingerprint', 100)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('reserved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['flash_sale_id', 'status']);
            $table->index('queue_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flash_sale_orders');
        Schema::dropIfExists('flash_sales');
    }
};
