<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('rating')->comment('评分 1-5 星');
            $table->text('content')->nullable()->comment('评论内容');
            $table->json('images')->nullable()->comment('晒单图片');
            $table->json('tags')->nullable()->comment('评论标签: 好评/中评/差评 或 自定义');
            $table->string('status', 20)->default('pending')->comment('pending/approved/rejected');
            $table->text('admin_reply')->nullable()->comment('商家回复');
            $table->timestamp('reply_at')->nullable();
            $table->foreignId('replied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_anonymous')->default(false)->comment('匿名评价');
            $table->boolean('is_verified_purchase')->default(true)->comment('已购验证');
            $table->string('reject_reason')->nullable()->comment('驳回原因');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'status']);
            $table->index(['product_id', 'rating']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
    }
};
