<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_bundles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 255)->comment('套餐名称');
            $table->string('slug', 255)->unique()->comment('URL标识');
            $table->text('description')->nullable()->comment('套餐描述');
            $table->string('image', 500)->nullable()->comment('套餐图片');
            $table->decimal('bundle_price', 12, 2)->comment('套餐总价');
            $table->decimal('original_price', 12, 2)->nullable()->comment('原价总和（自动计算）');
            $table->string('currency', 10)->default('CNY');
            $table->string('billing_period', 30)->default('monthly')->comment('计费周期: monthly/yearly/one_time');
            $table->integer('stock')->nullable()->comment('库存（null=不限制）');
            $table->integer('max_purchase_per_user')->default(1)->comment('每人限购数量');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('bundle_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_bundle_id')->constrained()->cascadeOnDelete();
            $table->morphs('itemable');
            $table->string('name', 255)->comment('项目名称');
            $table->decimal('original_price', 12, 2)->comment('项目原价');
            $table->decimal('discount_percent', 5, 2)->default(0)->comment('折扣百分比');
            $table->integer('quantity')->default(1)->comment('数量');
            $table->string('type', 30)->nullable()->comment('类型: product/license/addon');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_bundle_id', 'itemable_type', 'itemable_id'], 'bundle_item_poly_idx');
        });

        Schema::create('bundle_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_bundle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('order_no', 64)->unique()->comment('订单号');
            $table->decimal('paid_amount', 12, 2)->comment('实付金额');
            $table->string('currency', 10)->default('CNY');
            $table->string('status', 30)->default('pending')->comment('pending/completed/refunded/partially_refunded');
            $table->json('purchased_items')->nullable()->comment('购买的明细快照');
            $table->timestamp('purchased_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bundle_purchases');
        Schema::dropIfExists('bundle_items');
        Schema::dropIfExists('product_bundles');
    }
};
