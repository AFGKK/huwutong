<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('share_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('content_type', 50)->comment('oa_article|forum_post|blog_post|product');
            $table->unsignedBigInteger('content_id');
            $table->string('platform', 30)->comment('wechat|weibo|copy');
            $table->decimal('points_awarded', 10, 2)->default(0);
            $table->timestamps();

            // 同一用户分享同一内容到同一平台只奖励一次
            $table->unique(['user_id', 'content_type', 'content_id', 'platform'], 'share_reward_unique');
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('share_rewards');
    }
};
