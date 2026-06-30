<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 255)->comment('标题');
            $table->string('slug', 255)->unique()->comment('URL标识');
            $table->string('type', 30)->default('changelog')->comment('类型: blog/changelog/release_note');
            $table->longText('content')->comment('内容（Markdown）');
            $table->text('excerpt')->nullable()->comment('摘要');
            $table->string('featured_image', 500)->nullable()->comment('封面图');
            $table->string('author', 100)->nullable()->comment('作者');
            $table->json('tags')->nullable()->comment('标签');
            $table->string('version', 30)->nullable()->comment('版本号（仅changelog）');
            $table->boolean('is_published')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'is_published', 'published_at']);
            $table->index('slug');
            $table->index('published_at');
        });

        Schema::create('rss_feeds', function (Blueprint $table) {
            $table->id();
            $table->string('feed_type', 30)->unique()->comment('feed类型: blog/changelog/all');
            $table->string('title', 255)->comment('Feed标题');
            $table->text('description')->nullable()->comment('Feed描述');
            $table->string('language', 10)->default('zh-CN');
            $table->string('ttl', 10)->default('60')->comment('缓存时间（分钟）');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rss_feeds');
        Schema::dropIfExists('blog_posts');
    }
};
