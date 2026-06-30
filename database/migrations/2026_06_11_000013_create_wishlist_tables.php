<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 用户收藏夹分组
        Schema::create('wishlist_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100)->comment('分组名称: 如 默认/待购/对比');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'name']);
        });

        // 收藏的商品
        Schema::create('wishlist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('wishlist_groups')->nullOnDelete();
            $table->text('note')->nullable()->comment('备注/愿望备注');
            $table->boolean('notify_on_sale')->default(false)->comment('降价通知');
            $table->boolean('notify_on_stock')->default(false)->comment('到货通知');
            $table->integer('quantity')->default(1)->comment('期望数量');
            $table->decimal('target_price', 12, 2)->nullable()->comment('目标价格（降价到此价格时通知）');
            $table->integer('priority')->default(0)->comment('优先级: 0-普通/1-重要/2-紧急');
            $table->timestamps();

            $table->unique(['user_id', 'product_id', 'group_id'], 'wishlist_item_unique');
            $table->index(['user_id', 'priority']);
            $table->index(['product_id']);
        });

        // 心愿单分享
        Schema::create('wishlist_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wishlist_group_id')->nullable()->constrained('wishlist_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('share_token', 64)->unique()->comment('分享令牌');
            $table->string('share_type', 20)->default('public')->comment('public/private_link/email');
            $table->json('shared_with')->nullable()->comment('分享给谁: 邮箱列表');
            $table->timestamp('expires_at')->nullable()->comment('过期时间');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlist_shares');
        Schema::dropIfExists('wishlist_items');
        Schema::dropIfExists('wishlist_groups');
    }
};
