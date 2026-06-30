<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 预售/众筹活动
        Schema::create('pre_sale_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->default('pre_sale')->comment('pre_sale: 预售, crowdfunding: 众筹');
            $table->string('name', 200)->comment('活动名称');
            $table->string('slug', 200)->unique()->comment('URL标识');
            $table->text('description')->nullable();
            $table->json('images')->nullable()->comment('活动图片');
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('target_amount', 14, 2)->nullable()->comment('众筹目标金额');
            $table->decimal('min_amount', 14, 2)->nullable()->comment('最低众筹金额');
            $table->decimal('raised_amount', 14, 2)->default(0)->comment('已筹集金额');
            $table->integer('target_backers')->nullable()->comment('目标支持人数');
            $table->integer('current_backers')->default(0)->comment('当前支持人数');
            $table->decimal('deposit_rate', 5, 2)->default(0)->comment('定金比例(%)');
            $table->decimal('deposit_amount', 12, 2)->default(0)->comment('定金金额(固定)');
            $table->string('currency', 3)->default('CNY');
            $table->timestamp('start_at')->comment('开始时间');
            $table->timestamp('end_at')->comment('结束时间');
            $table->timestamp('estimated_delivery_at')->nullable()->comment('预计发货时间');
            $table->string('status', 20)->default('draft')->comment('draft/pending/active/success/failed/cancelled/completed');
            $table->json('tiers')->nullable()->comment('众筹档位 JSON');
            $table->json('settings')->nullable()->comment('活动设置: auto_fulfill/refund_on_fail/max_per_backer 等');
            $table->text('fail_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['product_id']);
        });

        // 预售/众筹参与记录
        Schema::create('pre_sale_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('pre_sale_campaigns')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('order_no', 100)->unique()->comment('订单号');
            $table->integer('tier_index')->nullable()->comment('众筹档位索引');
            $table->string('tier_name', 200)->nullable()->comment('档位名称');
            $table->decimal('total_amount', 14, 2)->comment('总金额');
            $table->decimal('deposit_paid', 12, 2)->default(0)->comment('已付定金');
            $table->decimal('final_payment', 12, 2)->default(0)->comment('尾款金额');
            $table->decimal('final_paid', 12, 2)->default(0)->comment('已付尾款');
            $table->string('currency', 3)->default('CNY');
            $table->string('payment_status', 30)->default('deposit_pending')
                ->comment('deposit_pending/deposit_paid/final_pending/final_paid/refunding/refunded');
            $table->string('fulfillment_status', 20)->default('pending')
                ->comment('pending/processing/shipped/delivered');
            $table->integer('quantity')->default(1);
            $table->timestamp('deposit_paid_at')->nullable();
            $table->timestamp('final_paid_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['campaign_id', 'payment_status']);
            $table->index(['user_id']);
        });

        // 预售/众筹活动更新
        Schema::create('pre_sale_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('pre_sale_campaigns')->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('content');
            $table->string('type', 20)->default('update')->comment('update/milestone/announcement');
            $table->boolean('is_pinned')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pre_sale_updates');
        Schema::dropIfExists('pre_sale_orders');
        Schema::dropIfExists('pre_sale_campaigns');
    }
};
