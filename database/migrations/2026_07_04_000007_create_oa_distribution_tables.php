<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 平台账号绑定
        if (Schema::hasTable('oa_platform_accounts')) { return; }
        Schema::create('oa_platform_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id'); // official_accounts.id
            $table->string('platform', 50);           // wechat_mp, weibo, zhihu, toutiao, etc.
            $table->string('label', 100)->nullable();  // 自定义名称
            $table->string('app_id', 100)->nullable();
            $table->string('app_secret', 500)->nullable();
            $table->string('access_token', 1000)->nullable();
            $table->string('refresh_token', 500)->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('platform_user_id', 100)->nullable(); // 平台上的用户/公众号ID
            $table->string('platform_user_name', 100)->nullable();
            $table->string('platform_avatar', 500)->nullable();
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();       // 平台特定设置
            $table->timestamps();

            $table->foreign('account_id')->references('id')->on('official_accounts')->onDelete('cascade');
            $table->unique(['account_id', 'platform']);
        });

        // 文章分发记录
        if (Schema::hasTable('oa_article_distributions')) { return; }
        Schema::create('oa_article_distributions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('article_id');
            $table->unsignedBigInteger('platform_account_id');
            $table->string('platform', 50);
            $table->string('status', 20)->default('pending'); // pending, publishing, success, failed
            $table->string('external_id', 200)->nullable();    // 平台上的文章ID
            $table->string('external_url', 500)->nullable();   // 平台上的链接
            $table->text('error_message')->nullable();
            $table->json('platform_data')->nullable();         // 平台返回的原始数据
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->foreign('article_id')->references('id')->on('oa_articles')->onDelete('cascade');
            $table->foreign('platform_account_id')->references('id')->on('oa_platform_accounts')->onDelete('cascade');
            $table->index(['article_id', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oa_article_distributions');
        Schema::dropIfExists('oa_platform_accounts');
    }
};
